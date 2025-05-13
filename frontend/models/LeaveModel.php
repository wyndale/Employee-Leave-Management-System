<?php
require_once __DIR__ . '/../../backend/src/Database.php';

class LeaveModel {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getLeaveBalance($employeeId) {
        $stmt = $this->pdo->prepare("SELECT leave_type_id, balance FROM leave_balances WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingLeaveRequests($employeeId) {
        $stmt = $this->pdo->prepare("SELECT request_id, start_date, end_date, duration, reason, status FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function submitLeaveRequest($employeeId, $startDate, $endDate, $reason) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, status) VALUES (?, 1, ?, ?, ?, 'pending')");
            return $stmt->execute([$employeeId, $startDate, $endDate, $reason]);
        } catch (PDOException $e) {
            error_log("Leave request submission failed: " . $e->getMessage());
            return false;
        }
    }
}