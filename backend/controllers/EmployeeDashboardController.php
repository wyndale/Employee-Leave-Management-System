<?php
require_once __DIR__ . '/../models/Auth.php';
require_once __DIR__ . '/../models/LeaveModel.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../utils/redirect.php';

class EmployeeDashboardController {
    private $auth;
    public $leaveModel;
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

    public function updateLeaveRequestStatus($requestId, $status) {
        $employeeId = Session::get('user_id');
        $stmt = $this->pdo->prepare("SELECT employee_id FROM leave_requests WHERE leave_request_id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request || $request['employee_id'] != $employeeId) {
            return false;
        }

        $managerId = $this->auth->getManagerId($employeeId);
        $this->pdo->beginTransaction();
        try {
            $updateStmt = $this->pdo->prepare("UPDATE leave_requests SET status = ?, updated_at = NOW(), manager_id = ? WHERE leave_request_id = ?");
            $updateStmt->execute([$status, $managerId, $requestId]);

            if ($status === 'approved' || $status === 'rejected') {
                $message = "Your leave request (ID: $requestId) has been $status.";
                $this->addNotification($employeeId, $message);
                $this->logAudit($employeeId, "leave_request_$status", "Leave request $requestId updated to $status by manager $managerId");
            }

            if ($status === 'approved') {
                $leaveTypeStmt = $this->pdo->prepare("SELECT leave_type_id, start_date, end_date FROM leave_requests WHERE leave_request_id = ?");
                $leaveTypeStmt->execute([$requestId]);
                $leaveData = $leaveTypeStmt->fetch(PDO::FETCH_ASSOC);
                $daysDiff = ((int) (new DateTime($leaveData['end_date']))->diff(new DateTime($leaveData['start_date']))->days) + 1;
                $this->leaveModel->updateLeaveBalance($employeeId, $leaveData['leave_type_id'], $daysDiff);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error updating leave request: " . $e->getMessage());
            return false;
        }
    }

    private function addNotification($employeeId, $message) {
        $stmt = $this->pdo->prepare("INSERT INTO notifications (employee_id, message, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->execute([$employeeId, $message]);
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    $controller = new EmployeeDashboardController();
    if ($_GET['action'] === 'mark_notifications_read') {
        $controller->markNotificationsRead();
    } elseif ($_GET['action'] === 'update_leave_status' && isset($_POST['request_id']) && isset($_POST['status'])) {
        $result = $controller->updateLeaveRequestStatus($_POST['request_id'], $_POST['status']);
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
        exit;
    }
}