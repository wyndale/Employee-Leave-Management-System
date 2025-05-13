<?php
require_once __DIR__ . '/../../backend/src/Session.php';
require_once __DIR__ . '/../../frontend/models/Auth.php';
require_once __DIR__ . '/../../frontend/models/LeaveModel.php';
require_once __DIR__ . '/../../backend/utils/redirect.php';

class EmployeeDashboardController {
    private $auth;
    private $leaveModel;
    private $baseUrl = '/employee-leave-management-system';

    public function __construct() {
        Session::start();
        $this->auth = new Auth();
        $this->leaveModel = new LeaveModel();
        if (!Session::isLoggedIn() || Session::getRole() !== 'employee') {
            redirect($this->baseUrl . '/login', 'Unauthorized access.', 'error');
        }
    }

    public function handleDashboard() {
        $userId = Session::get('user_id');
        $firstName = Session::get('first_name');
        $lastName = Session::get('last_name');
        $leaveBalances = $this->leaveModel->getLeaveBalance($userId);
        $pendingRequests = $this->leaveModel->getPendingLeaveRequests($userId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle leave request submission (PRG pattern)
            $startDate = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
            $endDate = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
            $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);

            if ($startDate && $endDate && $reason) {
                if ($this->leaveModel->submitLeaveRequest($userId, $startDate, $endDate, $reason)) {
                    redirect($this->baseUrl . '/employee-dashboard', 'Leave request submitted successfully!', 'success');
                } else {
                    redirect($this->baseUrl . '/employee-dashboard', 'Failed to submit leave request. Please try again.', 'error');
                }
            }
        }

        return [
            'firstName' => htmlspecialchars($firstName),
            'lastName' => htmlspecialchars($lastName),
            'leaveBalances' => $leaveBalances,
            'pendingRequests' => $pendingRequests
        ];
    }
}