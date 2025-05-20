<?php
// Start output buffering
ob_start();

// Enable error logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../models/Auth.php';
require_once __DIR__ . '/../utils/redirect.php';

class LeaveRequestController {
    private $pdo;
    private $auth;

    public function __construct() {
        try {
            $this->pdo = Database::getInstance()->getConnection();
            $this->auth = new Auth();
        } catch (Exception $e) {
            error_log("Constructor failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function getManager($managerId) {
        return $this->auth->getEmployeeById($managerId);
    }

    private function logAudit($managerId, $action, $details) {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $stmt = $this->pdo->prepare("INSERT INTO audit_logs (employee_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$managerId, $action, $details, $ipAddress, $userAgent]);
        } catch (PDOException $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }

    private function addNotification($employeeId, $message) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO notifications (employee_id, message, status, created_at) VALUES (?, ?, 'pending', NOW())");
            $stmt->execute([$employeeId, $message]);
        } catch (PDOException $e) {
            error_log("Notification insert failed: " . $e->getMessage());
            throw new Exception("Failed to add notification.");
        }
    }

    public function getLeaveRequests($managerId, $page = 1) {
        try {
            $limit = 10;
            $offset = ($page - 1) * $limit;

            // Get manager's department_id
            $stmt = $this->pdo->prepare("SELECT department_id FROM employees WHERE employee_id = ? AND role = 'manager'");
            $stmt->execute([$managerId]);
            $managerDept = $stmt->fetchColumn();
            if ($managerDept === false) {
                throw new Exception('Manager or department not found.');
            }

            $query = "
                SELECT 
                    lr.request_id,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    lt.name AS leave_type,
                    lr.start_date,
                    lr.end_date,
                    lr.status,
                    lr.reason
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                WHERE lr.manager_id = ? AND e.department_id = ? AND lr.status = 'pending'
                ORDER BY lr.created_at DESC
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$managerId, $managerDept, $limit, $offset]);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $countQuery = "
                SELECT COUNT(*) 
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                WHERE lr.manager_id = ? AND e.department_id = ? AND lr.status = 'pending'
            ";
            $countStmt = $this->pdo->prepare($countQuery);
            $countStmt->execute([$managerId, $managerDept]);
            $total = $countStmt->fetchColumn();

            return [
                'requests' => $requests,
                'total' => $total,
                'limit' => $limit,
                'page' => $page
            ];
        } catch (PDOException $e) {
            error_log("getLeaveRequests failed: " . $e->getMessage());
            return ['requests' => [], 'total' => 0, 'limit' => 10, 'page' => 1];
        }
    }

    public function approveRequest($requestId, $managerId, $comment = '') {
        $this->pdo->beginTransaction();
        try {
            // Verify request and manager's department
            $stmt = $this->pdo->prepare("
                SELECT lr.status, lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date, e.department_id
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                WHERE lr.request_id = ? AND lr.manager_id = ?
            ");
            $stmt->execute([$requestId, $managerId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request || $request['status'] !== 'pending') {
                throw new Exception('Request not found or not pending.');
            }

            // Verify manager's department
            $stmt = $this->pdo->prepare("SELECT department_id FROM employees WHERE employee_id = ? AND role = 'manager'");
            $stmt->execute([$managerId]);
            $managerDept = $stmt->fetchColumn();
            if ($managerDept === false || $managerDept != $request['department_id']) {
                throw new Exception('Unauthorized: Request not in manager\'s department.');
            }

            // Validate employee and leave type
            $stmt = $this->pdo->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
            $stmt->execute([$request['employee_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Invalid employee.');
            }

            $stmt = $this->pdo->prepare("SELECT name FROM leave_types WHERE leave_type_id = ?");
            $stmt->execute([$request['leave_type_id']]);
            $leaveType = $stmt->fetchColumn();
            if (!$leaveType) {
                throw new Exception('Invalid leave type.');
            }

            // Calculate leave duration (inclusive, whole days)
            $startDate = new DateTime($request['start_date']);
            $endDate = new DateTime($request['end_date']);
            $interval = $startDate->diff($endDate);
            $days = number_format($interval->days + 1, 2, '.', ''); // e.g., 3.00

            // Check leave balance
            $stmt = $this->pdo->prepare("
                SELECT balance 
                FROM leave_balances 
                WHERE employee_id = ? AND leave_type_id = ?
            ");
            $stmt->execute([$request['employee_id'], $request['leave_type_id']]);
            $balance = $stmt->fetchColumn();

            if ($balance === false || $balance < $days) {
                throw new Exception('Insufficient leave balance.');
            }

            // Update leave balance
            $stmt = $this->pdo->prepare("
                UPDATE leave_balances 
                SET balance = balance - ?, updated_at = NOW()
                WHERE employee_id = ? AND leave_type_id = ?
            ");
            $stmt->execute([$days, $request['employee_id'], $request['leave_type_id']]);

            // Update request
            $stmt = $this->pdo->prepare("UPDATE leave_requests SET status = 'approved', manager_comment = ?, approved_at = NOW(), updated_at = NOW() WHERE request_id = ?");
            $stmt->execute([$comment ?: NULL, $requestId]);

            // Notify employee
            $startDateFormatted = date('M d, Y', strtotime($request['start_date']));
            $endDateFormatted = date('M d, Y', strtotime($request['end_date']));
            $notification = "Your leave request ($leaveType, $startDateFormatted - $endDateFormatted) was approved." . ($comment ? " Comment: $comment" : "");
            $this->addNotification($request['employee_id'], $notification);

            // Log action
            $this->logAudit($managerId, 'approve_leave', "Approved leave request ID: $requestId for employee ID: {$request['employee_id']} with $days days deducted" . ($comment ? " with comment: $comment" : ""));

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Leave request approved successfully.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("approveRequest failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error approving request: ' . $e->getMessage()];
        }
    }

    public function rejectRequest($requestId, $managerId, $comment = '') {
        $this->pdo->beginTransaction();
        try {
            // Verify request and manager's department
            $stmt = $this->pdo->prepare("
                SELECT lr.status, lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date, e.department_id
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                WHERE lr.request_id = ? AND lr.manager_id = ?
            ");
            $stmt->execute([$requestId, $managerId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request || $request['status'] !== 'pending') {
                throw new Exception('Request not found or not pending.');
            }

            // Verify manager's department
            $stmt = $this->pdo->prepare("SELECT department_id FROM employees WHERE employee_id = ? AND role = 'manager'");
            $stmt->execute([$managerId]);
            $stmt->execute([$managerId]);
            $managerDept = $stmt->fetchColumn();
            if ($managerDept === false || $managerDept != $request['department_id']) {
                throw new Exception('Unauthorized: Request not in manager\'s department.');
            }

            // Validate employee and leave type
            $stmt = $this->pdo->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
            $stmt->execute([$request['employee_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Invalid employee.');
            }

            $stmt = $this->pdo->prepare("SELECT name FROM leave_types WHERE leave_type_id = ?");
            $stmt->execute([$request['leave_type_id']]);
            $leaveType = $stmt->fetchColumn();
            if (!$leaveType) {
                throw new Exception('Invalid leave type.');
            }

            // Update request
            $stmt = $this->pdo->prepare("UPDATE leave_requests SET status = 'rejected', manager_comment = ?, rejected_at = NOW(), updated_at = NOW() WHERE request_id = ?");
            $stmt->execute([$comment ?: NULL, $requestId]);

            // Notify employee
            $startDate = date('M d, Y', strtotime($request['start_date']));
            $endDate = date('M d, Y', strtotime($request['end_date']));
            $notification = "Your leave request ($leaveType, $startDate - $endDate) was rejected." . ($comment ? " Reason: $comment" : "");
            $this->addNotification($request['employee_id'], $notification);

            // Log action
            $this->logAudit($managerId, 'reject_leave', "Rejected leave request ID: $requestId for employee ID: {$request['employee_id']}" . ($comment ? " with comment: $comment" : ""));

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Leave request rejected successfully.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("rejectRequest failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error rejecting request: ' . $e->getMessage()];
        }
    }
}

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear output buffer
    ob_end_clean();
    
    header('Content-Type: application/json; charset=utf-8');

    try {
        // Read JSON input
        $input = file_get_contents('php://input');
        error_log("Received input: " . $input);
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input: ' . json_last_error_msg());
        }

        if (!isset($data['action']) || !isset($data['id'])) {
            throw new Exception('Missing required fields: action and id');
        }

        $controller = new LeaveRequestController();
        $managerId = Session::get('user_id');
        if (!$managerId) {
            error_log("Session error: No manager ID found.");
            throw new Exception('No manager ID in session.');
        }

        $requestId = filter_var($data['id'], FILTER_VALIDATE_INT);
        if ($requestId === false) {
            throw new Exception('Invalid request ID.');
        }

        $comment = $data['comment'] ?? '';

        $result = ['success' => false, 'message' => 'Invalid action.'];

        if ($data['action'] === 'approve') {
            $result = $controller->approveRequest($requestId, $managerId, $comment);
        } elseif ($data['action'] === 'reject') {
            $result = $controller->rejectRequest($requestId, $managerId, $comment);
        }

        // Log response for debugging
        error_log("Response: " . json_encode($result));

        echo json_encode($result);
    } catch (Exception $e) {
        error_log("Request handling failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }

    exit;
}
?>