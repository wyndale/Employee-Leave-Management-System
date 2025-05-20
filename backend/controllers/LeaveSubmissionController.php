<?php
// Prevent output before redirect
ob_start();

// Enable error logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../utils/redirect.php';

class LeaveSubmissionController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function submitLeave() {
        error_log("LeaveSubmissionController: Entering submitLeave method");
        Session::start();
        if (!Session::isLoggedIn() || Session::getRole() !== 'employee') {
            error_log("LeaveSubmissionController: Unauthorized access");
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log("LeaveSubmissionController: Invalid request method");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("LeaveSubmissionController: Invalid JSON input: " . json_last_error_msg());
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            exit;
        }

        $csrfToken = $data['csrf_token'] ?? '';
        if (!hash_equals(Session::get('csrf_token'), $csrfToken)) {
            error_log("LeaveSubmissionController: Invalid CSRF token");
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        $employeeId = Session::get('user_id');
        $leaveType = filter_var($data['leaveType'] ?? '', FILTER_SANITIZE_STRING);
        $startDate = filter_var($data['startDate'] ?? '', FILTER_SANITIZE_STRING);
        $endDate = filter_var($data['endDate'] ?? '', FILTER_SANITIZE_STRING);
        $reason = filter_var($data['reason'] ?? '', FILTER_SANITIZE_STRING);

        error_log("Received form data: employeeId=$employeeId, leaveType=$leaveType, startDate=$startDate, endDate=$endDate, reason=$reason");

        if (empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
            error_log("LeaveSubmissionController: Missing required fields");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }

        $startDateObj = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        $today = new DateTime();
        $today->setTime(0, 0, 0); // Normalize to midnight for comparison
        if ($startDateObj < $today) {
            error_log("LeaveSubmissionController: Start date in the past");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Start date cannot be in the past']);
            exit;
        }
        if ($startDateObj > $endDateObj) {
            error_log("LeaveSubmissionController: End date before start date");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
            exit;
        }

        // Map leaveType to leave_type_id with case-insensitive comparison
        $leaveTypeStmt = $this->db->prepare("SELECT leave_type_id, eligibility_criteria FROM leave_types WHERE LOWER(name) = LOWER(?)");
        $leaveTypeStmt->execute([$leaveType]);
        $leaveTypeData = $leaveTypeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$leaveTypeData) {
            error_log("LeaveSubmissionController: Invalid leave type: $leaveType");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid leave type']);
            exit;
        }
        $leaveTypeId = $leaveTypeData['leave_type_id'];
        $eligibilityCriteria = $leaveTypeData['eligibility_criteria'];

        // Validate eligibility criteria
        $employeeStmt = $this->db->prepare("SELECT hire_date, manager_id FROM employees WHERE employee_id = ?");
        $employeeStmt->execute([$employeeId]);
        $employeeData = $employeeStmt->fetch(PDO::FETCH_ASSOC);
        if ($employeeData) {
            $hireDate = new DateTime($employeeData['hire_date']);
            $managerId = $employeeData['manager_id'];
            $currentDate = new DateTime();
            $interval = $hireDate->diff($currentDate);
            $monthsOfService = ($interval->y * 12) + $interval->m + ($interval->d / 30); // Approximate months

            if ($eligibilityCriteria === 'Immediate upon hire') {
                // No additional validation needed
            } elseif (preg_match('/(\d+)\s*months?\s*of\s*service/i', $eligibilityCriteria, $matches)) {
                $requiredMonths = (int)$matches[1];
                if ($monthsOfService < $requiredMonths) {
                    error_log("LeaveSubmissionController: Insufficient service duration for $leaveType - Required: $requiredMonths months, Actual: $monthsOfService months");
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "You need at least $requiredMonths months of service to request $leaveType leave"]);
                    exit;
                }
            } else {
                error_log("LeaveSubmissionController: Unrecognized eligibility criteria for $leaveType: $eligibilityCriteria");
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Unrecognized eligibility criteria for this leave type']);
                exit;
            }
        } else {
            error_log("LeaveSubmissionController: Employee data not found for ID $employeeId");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee data not found']);
            exit;
        }

        if (empty($managerId)) {
            error_log("LeaveSubmissionController: No manager assigned for employee ID $employeeId");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No manager assigned to you']);
            exit;
        }

        try {
            $this->db->beginTransaction();

            // Check leave balance
            $balanceStmt = $this->db->prepare("SELECT balance FROM leave_balances WHERE employee_id = ? AND leave_type_id = ?");
            $balanceStmt->execute([$employeeId, $leaveTypeId]);
            $balance = $balanceStmt->fetchColumn();

            $daysDiff = (int)($endDateObj->diff($startDateObj)->days) + 1;
            if ($balance === false || $balance < $daysDiff) {
                error_log("LeaveSubmissionController: Insufficient balance for employee $employeeId, type $leaveTypeId");
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Insufficient leave balance. You have $balance days remaining, but requested $daysDiff days"]);
                exit;
            }

            // Insert leave request with manager_id
            $stmt = $this->db->prepare("
                INSERT INTO leave_requests (employee_id, manager_id, leave_type_id, start_date, end_date, reason, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$employeeId, $managerId, $leaveTypeId, $startDate, $endDate, $reason]);

            $this->db->commit();
            error_log("LeaveSubmissionController: Leave request submitted successfully for employee $employeeId");
            echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully. Awaiting approval']);
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Submission error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
        exit;
    }

    public function getNotifications($employeeId) {
        try {
            $stmt = $this->db->prepare("
                SELECT notification_id, message, created_at, status 
                FROM notifications 
                WHERE employee_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$employeeId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $unreadCount = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE employee_id = ? AND status = 'pending'");
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
            $stmt = $this->db->prepare("
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
            $stmt = $this->db->prepare("DELETE FROM notifications WHERE employee_id = ?");
            $stmt->execute([$employeeId]);
            return ['success' => true, 'message' => 'All notifications deleted'];
        } catch (Exception $e) {
            error_log("deleteAllNotifications error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error deleting notifications: ' . $e->getMessage()];
        }
    }

    public function deleteNotification($notificationId, $employeeId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM notifications WHERE notification_id = ? AND employee_id = ?");
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
}

// Handle request
Session::start();
$controller = new LeaveSubmissionController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $action = $data['action'] ?? '';

    header('Content-Type: application/json; charset=utf-8');

    try {
        $employeeId = Session::get('user_id');
        if (!$employeeId) {
            error_log("LeaveSubmissionController: No employee ID in session");
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: No employee ID in session']);
            exit;
        }

        if ($action === 'submit') {
            $controller->submitLeave();
        } elseif ($action === 'get_notifications') {
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
        } else {
            error_log("LeaveSubmissionController: Invalid action: $action");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("LeaveSubmissionController: Error processing request: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
} else {
    error_log("LeaveSubmissionController: No valid action provided");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

ob_end_clean();
?>