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

// Get available years
$availableYears = $controller->getAvailableYears($managerId);

// Handle year filter
$year = isset($_GET['year']) && in_array($_GET['year'], $availableYears) ? (int)$_GET['year'] : date('Y');
$summaryData = $controller->getMonthlyLeaveSummary($managerId, $year);

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
    <title><?php echo $departmentName; ?> Reporting - Leave Management System</title>
    <link rel="stylesheet" href="../../assets/css/manager_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .report-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 1.5rem;
        }
        .report-container h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.5rem;
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
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #1f2937;
            background: #f9fafb;
        }
        .download-button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background: #4a90e2;
            color: #fff;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .download-button:hover {
            background: #357abd;
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
        .no-records {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
            font-size: 1rem;
        }
        @media (max-width: 768px) {
            .report-container {
                padding: 15px;
            }
            .report-container h2 {
                font-size: 1.2rem;
            }
            .filter-form {
                flex-direction: column;
                align-items: flex-start;
            }
            table {
                font-size: 0.8rem;
            }
            th, td {
                padding: 8px;
            }
            .download-button {
                width: 100%;
                text-align: center;
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
                <a href="leave_history.php" class="sidebar-link">
                    <i class="fas fa-history"></i>
                    <span class="sidebar-text">Leave History</span>
                </a>
                <a href="reporting.php" class="sidebar-link active">
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
                    <h1 class="greeting-title">Reporting - <?php echo $departmentName; ?></h1>
                </div>

                <!-- Report Container -->
                <div class="report-container">
                    <h2>Monthly Leave Summary</h2>
                    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem;">
                        <form class="filter-form" method="GET" action="reporting.php">
                            <div class="filter-group">
                                <label for="year">Select Year</label>
                                <select id="year" name="year" onchange="this.form.submit()">
                                    <?php foreach ($availableYears as $y): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                        <form action="/employee-leave-management-system/backend/services/generate_report.php" method="POST">
                            <input type="hidden" name="year" value="<?php echo $year; ?>">
                            <button type="submit" class="download-button">Download PDF</button>
                        </form>
                    </div>

                    <!-- Summary Table -->
                    <?php if (!empty($summaryData)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Pending Requests</th>
                                    <th>Approved Requests</th>
                                    <th>Rejected Requests</th>
                                    <th>Total Leave Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summaryData as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['month_name']); ?></td>
                                        <td><?php echo $row['pending_count']; ?></td>
                                        <td><?php echo $row['approved_count']; ?></td>
                                        <td><?php echo $row['rejected_count']; ?></td>
                                        <td><?php echo $row['total_days']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-records">No leave requests found for <?php echo $year; ?>.</div>
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