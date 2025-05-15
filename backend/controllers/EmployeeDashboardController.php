<?php
require_once __DIR__ . '/../../frontend/models/Auth.php';
require_once __DIR__ . '/../../frontend/models/LeaveModel.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../utils/redirect.php';

class EmployeeDashboardController {
    private $auth;
    private $leaveModel;
    private $pdo;

    public function __construct() {
        $this->auth = new Auth();
        $this->leaveModel = new LeaveModel();
        $this->pdo = Database::getInstance()->getConnection();
    }

    private function logAudit($employeeId, $action, $details) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt = $this->pdo->prepare("INSERT INTO audit_logs (employee_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$employeeId, $action, $details, $ipAddress, $userAgent]);
    }

    public function getEmployee($employeeId) {
        return $this->auth->getEmployeeById($employeeId);
    }

    public function getLeaveRequests($employeeId) {
        return $this->leaveModel->getLeaveRequestsByEmployeeId($employeeId);
    }

    public function getLeaveBalances($employeeId) {
        return $this->leaveModel->getLeaveBalancesByEmployeeId($employeeId);
    }

    public function getNotifications($employeeId) {
        $stmt = $this->pdo->prepare("SELECT notification_id, message, created_at FROM notifications WHERE employee_id = ? ORDER BY created_at DESC");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadNotificationCount($employeeId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE employee_id = ? AND status = 'pending'");
        $stmt->execute([$employeeId]);
        return (int)$stmt->fetchColumn();
    }

    public function getTotalRequestsForYear($employeeId, $year) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND YEAR(created_at) = ?");
        $stmt->execute([$employeeId, $year]);
        return (int)$stmt->fetchColumn();
    }

    public function markNotificationsRead() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }

        $employeeId = Session::get('user_id');
        $stmt = $this->pdo->prepare("UPDATE notifications SET status = 'read' WHERE employee_id = ? AND status = 'pending'");
        $stmt->execute([$employeeId]);

        echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
        exit;
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    $controller = new EmployeeDashboardController();
    if ($_GET['action'] === 'mark_notifications_read') {
        $controller->markNotificationsRead();
    }
}
?>