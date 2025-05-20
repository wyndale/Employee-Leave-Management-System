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
        $this->pdo = Database::getInstance()->getConnection();
        $this->leaveModel = new LeaveModel(Database::getInstance());
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
        try {
            $stmt = $this->pdo->prepare("
                SELECT notification_id, message, created_at, status 
                FROM notifications 
                WHERE employee_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$employeeId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $unreadCount = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE employee_id = ? AND status = 'pending'");
            $unreadCount->execute([$employeeId]);
            $unread = (int)$unreadCount->fetchColumn();
            return ['success' => true, 'notifications' => $notifications, 'unreadCount' => $unread];
        } catch (Exception $e) {
            error_log("getNotifications error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error fetching notifications: ' . $e->getMessage()];
        }
    }

    public function markNotificationAsRead($notificationId, $employeeId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET status = 'sent' 
                WHERE notification_id = ? AND employee_id = ?
            ");
            $stmt->execute([$notificationId, $employeeId]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('Notification not found or not authorized');
            }
            return ['success' => true, 'message' => 'Notification marked as read'];
        } catch (Exception $e) {
            error_log("markNotificationAsRead error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error marking notification as read: ' . $e->getMessage()];
        }
    }

    public function deleteAllNotifications($employeeId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE employee_id = ?");
            $stmt->execute([$employeeId]);
            return ['success' => true, 'message' => 'All notifications deleted'];
        } catch (Exception $e) {
            error_log("deleteAllNotifications error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error deleting notifications: ' . $e->getMessage()];
        }
    }

    public function deleteNotification($notificationId, $employeeId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE notification_id = ? AND employee_id = ?");
            $stmt->execute([$notificationId, $employeeId]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('Notification not found or not authorized');
            }
            return ['success' => true, 'message' => 'Notification deleted'];
        } catch (Exception $e) {
            error_log("deleteNotification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error deleting notification: ' . $e->getMessage()];
        }
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
        $stmt = $this->pdo->prepare("UPDATE notifications SET status = 'sent' WHERE employee_id = ? AND status = 'pending'");
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
Session::start();
$controller = new EmployeeDashboardController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $action = $data['action'] ?? $_GET['action'] ?? '';

    header('Content-Type: application/json; charset=utf-8');

    try {
        $employeeId = Session::get('user_id');
        if (!$employeeId) {
            error_log("EmployeeDashboardController: No employee ID in session");
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: No employee ID in session']);
            exit;
        }

        if ($action === 'get_notifications') {
            $result = $controller->getNotifications($employeeId);
            echo json_encode($result);
        } elseif ($action === 'mark_read') {
            $notificationId = filter_var($data['notification_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($notificationId === false || $notificationId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
                exit;
            }
            $result = $controller->markNotificationAsRead($notificationId, $employeeId);
            echo json_encode($result);
        } elseif ($action === 'delete_all') {
            $result = $controller->deleteAllNotifications($employeeId);
            echo json_encode($result);
        } elseif ($action === 'delete_notification') {
            $notificationId = filter_var($data['notification_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($notificationId === false || $notificationId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
                exit;
            }
            $result = $controller->deleteNotification($notificationId, $employeeId);
            echo json_encode($result);
        } elseif ($action === 'mark_notifications_read') {
            $controller->markNotificationsRead();
        } elseif ($action === 'update_leave_status' && isset($data['request_id']) && isset($data['status'])) {
            $result = $controller->updateLeaveRequestStatus($data['request_id'], $data['status']);
            echo json_encode(['success' => $result]);
        } else {
            error_log("EmployeeDashboardController: Invalid action: $action");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("EmployeeDashboardController: Error processing request: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}
?>