<?php
// Prevent output before redirect
ob_start();

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
            redirect('/public/login.php', 'Unauthorized access.', 'error');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log("LeaveSubmissionController: Invalid request method");
            redirect('../../frontend/views/leave_submission.php', 'Invalid request method.', 'error');
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!hash_equals(Session::get('csrf_token'), $csrfToken)) {
            error_log("LeaveSubmissionController: Invalid CSRF token");
            redirect('../../frontend/views/leave_submission.php', 'Invalid CSRF token.', 'error');
        }

        $employeeId = Session::get('user_id');
        $leaveType = filter_input(INPUT_POST, 'leaveType', FILTER_SANITIZE_STRING);
        $startDate = filter_input(INPUT_POST, 'startDate', FILTER_SANITIZE_STRING);
        $endDate = filter_input(INPUT_POST, 'endDate', FILTER_SANITIZE_STRING);
        $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);

        error_log("Received form data: employeeId=$employeeId, leaveType=$leaveType, startDate=$startDate, endDate=$endDate, reason=$reason");

        if (empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
            error_log("LeaveSubmissionController: Missing required fields");
            redirect('../../frontend/views/leave_submission.php', 'All fields are required.', 'error');
        }

        $startDateObj = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        $today = new DateTime();
        $today->setTime(0, 0, 0); // Normalize to midnight for comparison
        if ($startDateObj < $today) {
            error_log("LeaveSubmissionController: Start date in the past");
            redirect('../../frontend/views/leave_submission.php', 'Start date cannot be in the past.', 'error');
        }
        if ($startDateObj > $endDateObj) {
            error_log("LeaveSubmissionController: End date before start date");
            redirect('../../frontend/views/leave_submission.php', 'End date must be after start date.', 'error');
        }

        // Map leaveType to leave_type_id with case-insensitive comparison
        $leaveTypeStmt = $this->db->prepare("SELECT leave_type_id, eligibility_criteria FROM leave_types WHERE LOWER(name) = LOWER(?)");
        $leaveTypeStmt->execute([$leaveType]);
        $leaveTypeData = $leaveTypeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$leaveTypeData) {
            error_log("LeaveSubmissionController: Invalid leave type: $leaveType");
            redirect('../../frontend/views/leave_submission.php', 'Invalid leave type.', 'error');
        }
        $leaveTypeId = $leaveTypeData['leave_type_id'];
        $eligibilityCriteria = $leaveTypeData['eligibility_criteria'];

        // Validate eligibility criteria (text-based: "6 months of service" or "Immediate upon hire")
        $employeeStmt = $this->db->prepare("SELECT hire_date FROM employees WHERE employee_id = ?");
        $employeeStmt->execute([$employeeId]);
        $employeeData = $employeeStmt->fetch(PDO::FETCH_ASSOC);
        if ($employeeData) {
            $hireDate = new DateTime($employeeData['hire_date']);
            $currentDate = new DateTime();
            $interval = $hireDate->diff($currentDate);
            $monthsOfService = ($interval->y * 12) + $interval->m + ($interval->d / 30); // Approximate months

            if ($eligibilityCriteria === 'Immediate upon hire') {
                // No additional validation needed
            } elseif (preg_match('/(\d+)\s*months?\s*of\s*service/i', $eligibilityCriteria, $matches)) {
                $requiredMonths = (int)$matches[1];
                if ($monthsOfService < $requiredMonths) {
                    error_log("LeaveSubmissionController: Insufficient service duration for $leaveType - Required: $requiredMonths months, Actual: $monthsOfService months");
                    redirect('../../frontend/views/leave_submission.php', "You need at least $requiredMonths months of service to request $leaveType leave.", 'error');
                }
            } else {
                error_log("LeaveSubmissionController: Unrecognized eligibility criteria for $leaveType: $eligibilityCriteria");
                redirect('../../frontend/views/leave_submission.php', 'Unrecognized eligibility criteria for this leave type.', 'error');
            }
        } else {
            error_log("LeaveSubmissionController: Employee data not found for ID $employeeId");
            redirect('../../frontend/views/leave_submission.php', 'Employee data not found.', 'error');
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
                redirect('../../frontend/views/leave_submission.php', "Insufficient leave balance. You have $balance days remaining, but requested $daysDiff days.", 'error');
            }

            // Insert leave request with 'pending' status
            $stmt = $this->db->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$employeeId, $leaveTypeId, $startDate, $endDate, $reason]);

            // Do not deduct balance here; deduction will occur when the request is approved
            $this->db->commit();
            error_log("LeaveSubmissionController: Leave request submitted successfully for employee $employeeId");
            redirect('../../frontend/views/leave_submission.php', 'Leave request submitted successfully. Awaiting approval.', 'success');
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Submission error: " . $e->getMessage());
            redirect('../../frontend/views/leave_submission.php', 'An error occurred: ' . $e->getMessage(), 'error');
        }
    }
}

// Handle request
if (isset($_GET['action']) && $_GET['action'] === 'submit') {
    error_log("LeaveSubmissionController: Processing submit action");
    $controller = new LeaveSubmissionController();
    $controller->submitLeave();
} else {
    error_log("LeaveSubmissionController: No valid action provided");
    http_response_code(400);
    echo "Invalid action.";
}

ob_end_flush();