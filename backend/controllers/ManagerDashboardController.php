<?php
// Start output buffering
ob_start();

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../models/Auth.php';
require_once __DIR__ . '/../models/LeaveModel.php';

class ManagerDashboardController {
    private $auth;
    private $pdo;
    private $leaveModel;

    public function __construct() {
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
        $this->auth = new Auth();
        $this->pdo = Database::getInstance()->getConnection();
        $this->leaveModel = new LeaveModel(Database::getInstance());
    }

    private function logAudit($managerId, $action, $details) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt = $this->pdo->prepare("INSERT INTO audit_logs (employee_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$managerId, $action, $details, $ipAddress, $userAgent]);
    }

    private function getManagerDepartment($managerId) {
        $stmt = $this->pdo->prepare("SELECT department_id FROM employees WHERE employee_id = ? AND role = 'manager'");
        $stmt->execute([$managerId]);
        return $stmt->fetchColumn();
    }

    public function getManagerDashboardData($managerId) {
        $data = [];
        $data['manager'] = $this->auth->getEmployeeById($managerId);
        $data['department_name'] = $this->getDepartmentName($managerId);
        $data['summary_stats'] = $this->getSummaryStats($managerId);
        $data['leave_trends'] = $this->getLeaveTrends($managerId);
        $data['department_stats'] = $this->getDepartmentStats($managerId);
        $data['avg_approval_time'] = $this->getAverageApprovalTime($managerId);
        $data['leave_type_distribution'] = $this->getLeaveTypeDistribution($managerId);
        return $data;
    }

    private function getDepartmentName($managerId) {
        $stmt = $this->pdo->prepare("SELECT d.name FROM departments d JOIN employees e ON d.department_id = e.department_id WHERE e.employee_id = ? AND e.role = 'manager'");
        $stmt->execute([$managerId]);
        return $stmt->fetchColumn() ?: 'Unknown';
    }

    private function getSummaryStats($managerId) {
        $stats = [
            'pending_count' => 0,
            'approved_count' => 0,
            'rejected_count' => 0,
            'total_days' => 0
        ];
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return $stats;
        }

        $stmt = $this->pdo->prepare("
            SELECT lr.status, COUNT(*) as count, SUM(lr.duration) as total_days
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.manager_id = ? AND e.department_id = ? AND YEAR(lr.created_at) = YEAR(CURDATE())
            GROUP BY lr.status
        ");
        $stmt->execute([$managerId, $managerDept]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            if ($row['status'] === 'pending') {
                $stats['pending_count'] = (int)$row['count'];
            } elseif ($row['status'] === 'approved') {
                $stats['approved_count'] = (int)$row['count'];
                $stats['total_days'] += (int)$row['total_days'];
            } elseif ($row['status'] === 'rejected') {
                $stats['rejected_count'] = (int)$row['count'];
            }
        }

        return $stats;
    }

    private function getLeaveTrends($managerId) {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT DATE_FORMAT(lr.created_at, '%Y-%m') as month, COUNT(*) as count
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.manager_id = ? AND e.department_id = ? AND YEAR(lr.created_at) = YEAR(CURDATE())
            GROUP BY DATE_FORMAT(lr.created_at, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute([$managerId, $managerDept]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $trends = [];
        for ($i = 1; $i <= 12; $i++) {
            $month = sprintf('%04d-%02d', date('Y'), $i);
            $trends[$month] = 0;
        }
        foreach ($results as $row) {
            $trends[$row['month']] = (int)$row['count'];
        }
        return $trends;
    }

    private function getDepartmentStats($managerId) {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return [['department_name' => 'No Data', 'request_count' => 0]];
        }

        $stmt = $this->pdo->prepare("
            SELECT d.name as department_name, COUNT(lr.request_id) as request_count
            FROM employees e
            JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN leave_requests lr ON lr.employee_id = e.employee_id AND lr.manager_id = ?
            WHERE e.department_id = ?
            GROUP BY d.department_id, d.name
            ORDER BY request_count DESC
            LIMIT 3
        ");
        $stmt->execute([$managerId, $managerDept]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($results)) {
            return [['department_name' => 'No Data', 'request_count' => 0]];
        }
        return $results;
    }

    private function getAverageApprovalTime($managerId) {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT AVG(TIMESTAMPDIFF(HOUR, lr.created_at, lr.updated_at)) as avg_hours
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.manager_id = ? AND e.department_id = ? AND lr.status = 'approved'
        ");
        $stmt->execute([$managerId, $managerDept]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return round($result['avg_hours'] ?? 0, 1);
    }

    private function getLeaveTypeDistribution($managerId) {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT lt.name, COUNT(lr.request_id) as count
            FROM leave_types lt
            LEFT JOIN leave_requests lr ON lr.leave_type_id = lt.leave_type_id AND lr.manager_id = ?
            JOIN employees e ON lr.employee_id = e.employee_id
            WHERE e.department_id = ?
            GROUP BY lt.leave_type_id, lt.name
            ORDER BY count DESC
        ");
        $stmt->execute([$managerId, $managerDept]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingRequests($managerId, $status = 'pending', $page = 1, $perPage = 10) {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return [];
        }

        $offset = ($page - 1) * $perPage;
        $query = "
            SELECT lr.*, e.first_name, e.last_name, lt.name as leave_type_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            LEFT JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.manager_id = ? AND e.department_id = ? AND lr.status = ?
            ORDER BY lr.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$managerId, $managerDept, $status, $perPage, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalPendingRequestsCount($managerId, $status = 'pending') {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.manager_id = ? AND e.department_id = ? AND lr.status = ?
        ");
        $stmt->execute([$managerId, $managerDept, $status]);
        return (int)$stmt->fetchColumn();
    }

    public function getAllRequests($managerId, $status = null, $page = 1, $perPage = 10) {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return [];
        }

        $offset = ($page - 1) * $perPage;
        $query = "
            SELECT lr.*, e.first_name, e.last_name, lt.name as leave_type_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            LEFT JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.manager_id = ? AND e.department_id = ?
        ";
        $params = [$managerId, $managerDept];
        if ($status) {
            $query .= " AND lr.status = ?";
            $params[] = $status;
        }
        $query .= " ORDER BY lr.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalRequestsCount($managerId, $status = null) {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return 0;
        }

        $query = "
            SELECT COUNT(*) 
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.manager_id = ? AND e.department_id = ?
        ";
        $params = [$managerId, $managerDept];
        if ($status) {
            $query .= " AND lr.status = ?";
            $params[] = $status;
        }
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function updateLeaveRequestStatus($requestId, $status) {
        $managerId = Session::get('user_id');
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT lr.employee_id, e.department_id 
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.request_id = ? AND lr.manager_id = ? AND e.department_id = ?
        ");
        $stmt->execute([$requestId, $managerId, $managerDept]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) {
            error_log("Unauthorized: Request $requestId not in manager $managerId's department.");
            return false;
        }

        $employeeId = $request['employee_id'];
        $this->pdo->beginTransaction();
        try {
            $updateStmt = $this->pdo->prepare("UPDATE leave_requests SET status = ?, updated_at = NOW(), manager_id = ? WHERE request_id = ?");
            $updateStmt->execute([$status, $managerId, $requestId]);

            if ($status === 'approved' || $status === 'rejected') {
                $message = "Your leave request (ID: $requestId) has been $status.";
                $this->addNotification($employeeId, $message);
                $this->logAudit($managerId, "leave_request_$status", "Leave request $requestId updated to $status for employee $employeeId");
            }

            if ($status === 'approved') {
                $leaveTypeStmt = $this->pdo->prepare("SELECT leave_type_id, start_date, end_date FROM leave_requests WHERE request_id = ?");
                $leaveTypeStmt->execute([$requestId]);
                $leaveData = $leaveTypeStmt->fetch(PDO::FETCH_ASSOC);
                $daysDiff = ((int) (new DateTime($leaveData['end_date']))->diff(new DateTime($leaveData['start_date']))->days) + 1;
                $balance = $this->leaveModel->getLeaveBalance($employeeId, $leaveData['leave_type_id']);
                $newBalance = $balance['balance'] - $daysDiff;
                if ($newBalance < 0) {
                    throw new Exception("Insufficient leave balance for approval.");
                }
                $this->leaveModel->updateLeaveBalance($employeeId, $leaveData['leave_type_id'], $newBalance);
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

    public function markNotificationsRead() {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        $employeeId = Session::get('user_id');
        $stmt = $this->pdo->prepare("UPDATE notifications SET status = 'read' WHERE employee_id = ? AND status = 'pending'");
        $stmt->execute([$employeeId]);
        $response = ['success' => true, 'message' => 'Notifications marked as read'];
        error_log("markNotificationsRead response: " . json_encode($response));
        echo json_encode($response);
        exit;
    }

    public function getLeaveTypes() {
        try {
            $stmt = $this->pdo->prepare("SELECT leave_type_id, name FROM leave_types ORDER BY name");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getLeaveTypes: " . $e->getMessage());
            return [];
        }
    }

    public function getLeaveHistory($managerId, $page = 1, $perPage = 10, $filters = []) {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return ['leave_history' => [], 'total_pages' => 1, 'current_page' => 1, 'total_records' => 0];
        }

        try {
            // Build WHERE clause for filters
            $whereClauses = ["lr.manager_id = ? AND e.department_id = ? AND lr.status IN ('approved', 'rejected')"];
            $params = [$managerId, $managerDept];

            if (!empty($filters['employee_name'])) {
                $whereClauses[] = "CONCAT(e.first_name, ' ', e.last_name) LIKE ?";
                $params[] = "%{$filters['employee_name']}%";
            }
            if (!empty($filters['leave_type_id'])) {
                $whereClauses[] = "lr.leave_type_id = ?";
                $params[] = $filters['leave_type_id'];
            }
            if (!empty($filters['status'])) {
                $whereClauses[] = "lr.status = ?";
                $params[] = $filters['status'];
            }
            if (!empty($filters['start_date'])) {
                $whereClauses[] = "lr.start_date >= ?";
                $params[] = $filters['start_date'];
            }
            if (!empty($filters['end_date'])) {
                $whereClauses[] = "lr.end_date <= ?";
                $params[] = $filters['end_date'];
            }

            $whereClause = implode(' AND ', $whereClauses);

            // Get total count for pagination
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                WHERE $whereClause
            ");
            $stmt->execute($params);
            $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $totalPages = ceil($totalRecords / $perPage);

            // Get paginated leave history
            $offset = ($page - 1) * $perPage;
            $stmt = $this->pdo->prepare("
                SELECT 
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    lt.name AS leave_type,
                    lr.start_date,
                    lr.end_date,
                    lr.status,
                    lr.updated_at AS approved_at
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                WHERE $whereClause
                ORDER BY lr.updated_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);
            $leaveHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'leave_history' => $leaveHistory,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'total_records' => $totalRecords
            ];
        } catch (PDOException $e) {
            error_log("Database error in getLeaveHistory: " . $e->getMessage());
            return ['leave_history' => [], 'total_pages' => 1, 'current_page' => 1, 'total_records' => 0, 'error' => 'An error occurred while fetching leave history.'];
        }
    }

    public function getAvailableYears($managerId) {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT YEAR(lr.created_at) AS year
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                WHERE lr.manager_id = ? AND e.department_id = ?
                ORDER BY year DESC
            ");
            $stmt->execute([$managerId, $managerDept]);
            $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $years ?: [date('Y')];
        } catch (PDOException $e) {
            error_log("Database error in getAvailableYears: " . $e->getMessage());
            return [date('Y')];
        }
    }

    public function getMonthlyLeaveSummary($managerId, $year) {
        $managerDept = $this->getManagerDepartment($managerId);
        if ($managerDept === false) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE_FORMAT(lr.created_at, '%Y-%m') AS month,
                    MONTHNAME(lr.created_at) AS month_name,
                    COUNT(CASE WHEN lr.status = 'pending' THEN 1 END) AS pending_count,
                    COUNT(CASE WHEN lr.status = 'approved' THEN 1 END) AS approved_count,
                    COUNT(CASE WHEN lr.status = 'rejected' THEN 1 END) AS rejected_count,
                    SUM(CASE WHEN lr.status = 'approved' THEN lr.duration ELSE 0 END) AS total_days
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                WHERE lr.manager_id = ? AND e.department_id = ? AND YEAR(lr.created_at) = ?
                GROUP BY month, month_name
                ORDER BY month
            ");
            $stmt->execute([$managerId, $managerDept, $year]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Ensure all months are included, even with no data
            $summary = [];
            for ($i = 1; $i <= 12; $i++) {
                $month = sprintf('%04d-%02d', $year, $i);
                $month_name = date('F', mktime(0, 0, 0, $i, 1));
                $summary[$month] = [
                    'month' => $month,
                    'month_name' => $month_name,
                    'pending_count' => 0,
                    'approved_count' => 0,
                    'rejected_count' => 0,
                    'total_days' => 0
                ];
            }

            foreach ($results as $row) {
                $summary[$row['month']] = [
                    'month' => $row['month'],
                    'month_name' => $row['month_name'],
                    'pending_count' => (int)$row['pending_count'],
                    'approved_count' => (int)$row['approved_count'],
                    'rejected_count' => (int)$row['rejected_count'],
                    'total_days' => (int)$row['total_days']
                ];
            }

            return array_values($summary);
        } catch (PDOException $e) {
            error_log("Database error in getMonthlyLeaveSummary: " . $e->getMessage());
            return [];
        }
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $controller = new ManagerDashboardController();
    if ($_GET['action'] === 'mark_notifications_read') {
        $controller->markNotificationsRead();
    } elseif ($_GET['action'] === 'update_leave_status' && isset($_POST['request_id']) && isset($_POST['status'])) {
        $result = $controller->updateLeaveRequestStatus($_POST['request_id'], $_POST['status']);
        $response = ['success' => $result, 'message' => $result ? 'Status updated successfully' : 'Failed to update status'];
        error_log("updateLeaveRequestStatus response: " . json_encode($response));
        echo json_encode($response);
        exit;
    }
}
?>