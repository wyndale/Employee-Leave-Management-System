<?php
require_once __DIR__ . '/../../backend/src/Database.php';

class LeaveModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAllLeaveTypes() {
        $stmt = $this->db->prepare("SELECT * FROM leave_types");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeaveRequestsByEmployeeId($employeeId) {
        $stmt = $this->db->prepare("
            SELECT lr.*, lt.name as leave_type_name
            FROM leave_requests lr
            LEFT JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.employee_id = ?
            ORDER BY lr.created_at DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeaveBalancesByEmployeeId($employeeId) {
        $stmt = $this->db->prepare("
            SELECT lb.*, lt.name
            FROM leave_balances lb
            JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
            WHERE lb.employee_id = ?
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeaveBalance($employeeId, $leaveTypeId) {
        $stmt = $this->db->prepare("
            SELECT balance
            FROM leave_balances
            WHERE employee_id = ? AND leave_type_id = ?
        ");
        $stmt->execute([$employeeId, $leaveTypeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createLeaveRequest($data) {
        $stmt = $this->db->prepare("
            INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, status, manager_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $data['employee_id'],
            $data['leave_type_id'],
            $data['start_date'],
            $data['end_date'],
            $data['reason'],
            $data['status'],
            $data['manager_id']
        ]);
    }

    public function updateLeaveBalance($employeeId, $leaveTypeId, $newBalance) {
        $stmt = $this->db->prepare("
            UPDATE leave_balances
            SET balance = ?, updated_at = NOW()
            WHERE employee_id = ? AND leave_type_id = ?
        ");
        $stmt->execute([$newBalance, $employeeId, $leaveTypeId]);
    }
}
?>