<?php
require_once __DIR__ . '/../../../backend/src/Database.php';
require_once __DIR__ . '/../../../backend/src/Session.php';
require_once __DIR__ . '/../../../backend/models/Auth.php';
require_once __DIR__ . '/../../../backend/middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../../../backend/utils/redirect.php';
require_once __DIR__ . '/../../../backend/controllers/ManagerDashboardController.php';

// Initialize session and middleware
Session::start();
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->handle() || Session::get('role') !== 'manager') {
    redirect('/employee-leave-management-system', 'Unauthorized access.', 'error');
}

$controller = new ManagerDashboardController();
$managerId = Session::get('user_id');

// Get manager and department details
$dashboardData = $controller->getManagerDashboardData($managerId);
$departmentName = htmlspecialchars($dashboardData['department_name'] ?? 'Department');

// Get leave types for dropdown
$leaveTypes = $controller->getLeaveTypes();

// Handle filters and pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$filters = [
    'employee_name' => isset($_GET['employee_name']) ? trim($_GET['employee_name']) : '',
    'leave_type_id' => isset($_GET['leave_type_id']) && is_numeric($_GET['leave_type_id']) ? (int)$_GET['leave_type_id'] : '',
    'status' => isset($_GET['status']) && in_array($_GET['status'], ['approved', 'rejected']) ? $_GET['status'] : '',
    'start_date' => isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : '',
    'end_date' => isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : ''
];
$historyData = $controller->getLeaveHistory($managerId, $page, 10, $filters);
$leaveHistory = $historyData['leave_history'] ?? [];
$totalPages = $historyData['total_pages'] ?? 1;
$currentPage = $historyData['current_page'] ?? 1;
$totalRecords = $historyData['total_records'] ?? 0;

// Notifications
$notifications = $controller->getNotifications($managerId);
$unreadCount = $controller->getUnreadNotificationCount($managerId);

// Flash messages
$message = Session::get('message');
$messageType = Session::get('message_type');
Session::set('message', null);
Session::set('message_type', null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $departmentName; ?> Leave History - Leave Management System</title>
    <link rel="stylesheet" href="../../assets/css/manager_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .filter-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 1.5rem;
        }
        .filter-container h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 0.3rem;
        }
        .filter-group input, .filter-group select {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #1f2937;
            background: #f9fafb;
        }
        .filter-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .applied-filters {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #6b7280;
        }
        .applied-filters span {
            background: #e0e7ff;
            padding: 0.2rem 0.5rem;
            border-radius: 0.3rem;
            margin-right: 0.5rem;
        }
        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 1.5rem;
        }
        .table-container h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #1f2937;
        }
        td {
            color: #374151;
        }
        .status-approved {
            color: #2e7d32;
            font-weight: 500;
        }
        .status-rejected {
            color: #d32f2f;
            font-weight: 500;
        }
        .no-records {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
            font-size: 1rem;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 1rem;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            color: #4a90e2;
            background: #f9fafb;
            transition: background 0.3s ease, color 0.3s ease;
        }
        .pagination a:hover {
            background: #4a90e2;
            color: #fff;
        }
        .pagination .active {
            background: #4a90e2;
            color: #fff;
            font-weight: 600;
        }
        .pagination .disabled {
            color: #9ca3af;
            background: #f9fafb;
            pointer-events: none;
        }
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            .filter-container, .table-container {
                padding: 15px;
            }
            .filter-container h2, .table-container h2 {
                font-size: 1.2rem;
            }
            table {
                font-size: 0.8rem;
            }
            th, td {
                padding: 8px;
            }
            .pagination a, .pagination span {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body class="background-gradient font-poppins">
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar sidebar-collapsed">
            <div class="sidebar-header">
                <button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
            </div>
            <nav class="sidebar-nav">
                <a href="manager_dashboard.php" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span class="sidebar-text">Home</span>
                </a>
                <a href="manage_requests.php" class="sidebar-link active">
                    <i class="fas fa-tasks"></i>
                    <span class="sidebar-text">Manage Requests</span>
                </a>
                <a href="leave_history.php" class="sidebar-link active">
                    <i class="fas fa-history"></i>
                    <span class="sidebar-text">Leave History</span>
                </a>
                <a href="reporting.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span class="sidebar-text">Reporting Module</span>
                </a>
                <a href="hr_import.php" class="sidebar-link">
                    <i class="fas fa-upload"></i>
                    <span class="sidebar-text">HR Data Import</span>
                </a>
                <a href="settings.php" class="sidebar-link sidebar-link-bottom">
                    <i class="fas fa-cog"></i>
                    <span class="sidebar-text">Settings</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <div class="header-center">
                    <div class="search-bar">
                        <form class="search-form">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" placeholder="Search...">
                        </form>
                    </div>
                </div>
                <div class="header-right">
                    <div class="profile-container">
                        <button class="profile-button">
                            <img src="../../assets/images/profile-placeholder.png" alt="Profile" class="profile-image">
                            <span class="profile-name"><?php echo htmlspecialchars($dashboardData['manager']['first_name'] ?? 'Manager'); ?></span>
                        </button>
                        <div class="profile-dropdown">
                            <a href="settings.php" class="dropdown-item">Account Settings</a>
                            <a href="/employee-leave-management-system/backend/controllers/LogoutController.php?action=logout" class="dropdown-item">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="main padding-20">
                <!-- Flash Message -->
                <?php if ($message): ?>
                    <div class="toast-container">
                        <div class="toast toast-<?php echo htmlspecialchars($messageType); ?> visible">
                            <span><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="greeting-text">
                    <h1 class="greeting-title">Leave History - <?php echo $departmentName; ?></h1>
                </div>

                <!-- Filter Form -->
                <div class="filter-container">
                    <h2>Filter Leave Requests</h2>
                    <form class="filter-form" method="GET" action="leave_history.php">
                        <div class="filter-group">
                            <label for="employee_name">Employee Name</label>
                            <input type="text" id="employee_name" name="employee_name" value="<?php echo htmlspecialchars($filters['employee_name']); ?>" placeholder="Enter name">
                        </div>
                        <div class="filter-group">
                            <label for="leave_type_id">Leave Type</label>
                            <select id="leave_type_id" name="leave_type_id">
                                <option value="">All Types</option>
                                <?php foreach ($leaveTypes as $type): ?>
                                    <option value="<?php echo $type['leave_type_id']; ?>" <?php echo $filters['leave_type_id'] == $type['leave_type_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="approved" <?php echo $filters['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $filters['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="submit-button">Apply Filters</button>
                            <a href="leave_history.php" class="submit-button" style="background: #6b7280;">Reset</a>
                        </div>
                    </form>
                    <?php if (!empty(array_filter($filters))): ?>
                        <div class="applied-filters">
                            Applied Filters:
                            <?php if ($filters['employee_name']): ?>
                                <span>Name: <?php echo htmlspecialchars($filters['employee_name']); ?></span>
                            <?php endif; ?>
                            <?php if ($filters['leave_type_id']): ?>
                                <span>Type: <?php echo htmlspecialchars($leaveTypes[array_search($filters['leave_type_id'], array_column($leaveTypes, 'leave_type_id'))]['name']); ?></span>
                            <?php endif; ?>
                            <?php if ($filters['status']): ?>
                                <span>Status: <?php echo ucfirst(htmlspecialchars($filters['status'])); ?></span>
                            <?php endif; ?>
                            <?php if ($filters['start_date']): ?>
                                <span>Start: <?php echo date('M d, Y', strtotime($filters['start_date'])); ?></span>
                            <?php endif; ?>
                            <?php if ($filters['end_date']): ?>
                                <span>End: <?php echo date('M d, Y', strtotime($filters['end_date'])); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Leave History Table -->
                <div class="table-container">
                    <h2>Approved and Rejected Leave Requests</h2>
                    <?php if (!empty($leaveHistory)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Approval Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaveHistory as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['employee_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($record['leave_type'] ?? ''); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($record['start_date'] ?? 'now')); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($record['end_date'] ?? 'now')); ?></td>
                                        <td class="status-<?php echo strtolower($record['status'] ?? ''); ?>">
                                            <?php echo ucfirst(htmlspecialchars($record['status'] ?? '')); ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($record['approved_at'] ?? 'now')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-records">No approved or rejected leave requests found.</div>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <a href="?page=<?php echo max(1, $currentPage - 1); ?>&<?php echo http_build_query($filters); ?>" 
                               class="<?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                                Prev
                            </a>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>" 
                                   class="<?php echo $i === $currentPage ? 'active' : ''; ?>">
                                   <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            <a href="?page=<?php echo min($totalPages, $currentPage + 1); ?>&<?php echo http_build_query($filters); ?>" 
                               class="<?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                                Next
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Notification Mark Read
        document.getElementById('mark-read-btn')?.addEventListener('click', () => {
            fetch('../../../backend/controllers/ManagerDashboardController.php?action=mark_notifications_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Debug-Request': 'true' }
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP error! status: ${response.status}, response: ${text}`);
                    });
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        document.querySelectorAll('.notification-badge').forEach(badge => badge.remove());
                        location.reload();
                    } else {
                        console.error('Failed to mark notifications read:', data.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, 'Raw response:', text);
                    alert('Invalid server response: ' + text.substring(0, 100));
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error marking notifications read: ' + error.message);
            });
        });
    </script>
    <script src="../../assets/js/manager_dashboard.js"></script>
</body>
</html>