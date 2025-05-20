<?php
require_once __DIR__ . '/../../backend/src/Database.php';

class LeaveModel {
    private $db;

    public function __construct($db) {
        $this->db = $db->getConnection();
    }

    public function getAllLeaveTypes() {
        $stmt = $this->db->prepare("SELECT * FROM leave_types");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRequestsForManager($managerId) {
        $stmt = $this->db->prepare("
            SELECT lr.*, e.first_name, e.last_name, lt.name as leave_type_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            LEFT JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE e.manager_id = ? AND lr.status = 'pending'
            ORDER BY lr.created_at DESC
        ");
        $stmt->execute([$managerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummaryStats() {
        $stmt = $this->db->prepare("SELECT status, COUNT(*) as count FROM leave_requests GROUP BY status");
        $stmt->execute();
        $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[$row['status']] = $row['count'];
        }
        return $stats;
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
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['balance' => 0];
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

    public function getLeaveHistory($employeeId, $status = null, $startDate = null, $endDate = null, $leaveType = null, $page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;
        $query = "
            SELECT lr.*, lt.name as leave_type_name
            FROM leave_requests lr
            LEFT JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.employee_id = ?
        ";
        $params = [$employeeId];

        if ($status) {
            $query .= " AND lr.status = ?";
            $params[] = $status;
        }
        if ($startDate) {
            $query .= " AND lr.start_date >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $query .= " AND lr.end_date <= ?";
            $params[] = $endDate;
        }
        if ($leaveType) {
            $query .= " AND lt.name = ?";
            $params[] = $leaveType;
        }

        $query .= " ORDER BY lr.created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$perPage;
        $params[] = (int)$offset;

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalLeaveHistoryCount($employeeId, $status = null, $startDate = null, $endDate = null, $leaveType = null) {
        $query = "SELECT COUNT(*) FROM leave_requests lr LEFT JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.employee_id = ?";
        $params = [$employeeId];

        if ($status) {
            $query .= " AND lr.status = ?";
            $params[] = $status;
        }
        if ($startDate) {
            $query .= " AND lr.start_date >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $query .= " AND lr.end_date <= ?";
            $params[] = $endDate;
        }
        if ($leaveType) {
            $query .= " AND lt.name = ?";
            $params[] = $leaveType;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
?>