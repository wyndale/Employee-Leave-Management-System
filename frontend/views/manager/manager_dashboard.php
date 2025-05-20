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
$dashboardData = $controller->getManagerDashboardData($managerId);
$notifications = $controller->getNotifications($managerId);
$unreadCount = $controller->getUnreadNotificationCount($managerId);

// Dynamic greeting with department
$greeting = "Hi, " . htmlspecialchars($dashboardData['manager']['first_name'] ?? 'Manager') . "! Manage your " . htmlspecialchars($dashboardData['department_name'] ?? 'team') . " team’s leave requests efficiently.";

// Flash messages
$message = Session::get('message');
$messageType = Session::get('message_type');
Session::set('message', null);
Session::set('message_type', null);

// Prepare Insights chart data (Leave Type Distribution)
$insightsLabels = [];
$insightsCounts = [];
$insightsColors = [
    '#4a90e2',
    '#2ecc71',
    '#e74c3c',
    '#f1c40f',
    '#9b59b6',
    '#1abc9c',
    '#34495e',
    '#e67e22',
    '#7f8c8d',
    '#3498db'
];
$colorIndex = 0;
if (!empty($dashboardData['leave_type_distribution'])) {
    foreach ($dashboardData['leave_type_distribution'] as $type) {
        if (isset($type['name'], $type['count'])) {
            $insightsLabels[] = $type['name'];
            $insightsCounts[] = $type['count'];
            $insightsColors[$colorIndex] = $insightsColors[$colorIndex % count($insightsColors)];
            $colorIndex++;
        }
    }
}

// Prepare Trends chart data (Monthly Requests)
$trendsLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$trendsData = array_fill(0, 12, 0);
if (!empty($dashboardData['leave_trends'])) {
    foreach ($dashboardData['leave_trends'] as $month => $count) {
        $monthIndex = (int) substr($month, 5, 2) - 1;
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $trendsData[$monthIndex] = $count;
        }
    }
}

// Current year
$currentYear = date('Y');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dashboardData['department_name'] ?? 'Manager'); ?> Dashboard - Leave Management
        System</title>
    <link rel="stylesheet" href="../../assets/css/manager_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="background-gradient font-poppins">
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar sidebar-collapsed">
            <div class="sidebar-header">
                <button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
            </div>
            <nav class="sidebar-nav">
                <a href="manager_dashboard.php" class="sidebar-link active">
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
                            <span
                                class="profile-name"><?php echo htmlspecialchars($dashboardData['manager']['first_name'] ?? 'Manager'); ?></span>
                        </button>
                        <div class="profile-dropdown">
                            <a href="settings.php" class="dropdown-item">Account Settings</a>
                            <a href="/employee-leave-management-system/backend/controllers/LogoutController.php?action=logout"
                                class="dropdown-item">Logout</a>
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
                    <h1 class="greeting-title"><?php echo $greeting; ?></h1>
                </div>

                <!-- Summary Card: Annual Leave Request Overview -->
                <div class="card-container">
                    <h2><?php echo $currentYear; ?> Leave Request Overview -
                        <?php echo htmlspecialchars($dashboardData['department_name'] ?? 'Department'); ?></h2>
                    <div class="card-grid card-grid-bottom">
                        <a href="manage_requests.php" class="card-link-wrapper">
                            <div class="card card-highlight">
                                <div class="card-icon"><i class="fas fa-clock"></i></div>
                                <div class="card-content">
                                    <h3 class="card-title card-title-white">Pending</h3>
                                    <p class="card-value card-value-white">
                                        <?php echo $dashboardData['summary_stats']['pending_count'] ?? 0; ?></p>
                                </div>
                            </div>
                        </a>
                        <div class="card">
                            <div class="card-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="card-content">
                                <h3 class="card-title">Approved</h3>
                                <p class="card-value">
                                    <?php echo $dashboardData['summary_stats']['approved_count'] ?? 0; ?></p>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-icon"><i class="fas fa-times-circle"></i></div>
                            <div class="card-content">
                                <h3 class="card-title">Rejected</h3>
                                <p class="card-value">
                                    <?php echo $dashboardData['summary_stats']['rejected_count'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graph Cards: Insights and Trends -->
                <div class="card-grid card-grid-charts">
                    <a href="reporting.php" class="card-link-wrapper">
                        <div class="card-container">
                            <h2>Leave Requests by Type (<?php echo $currentYear; ?>) -
                                <?php echo htmlspecialchars($dashboardData['department_name'] ?? 'Department'); ?></h2>
                            <div class="chart-container">
                                <canvas id="leaveInsightsChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </a>
                    <a href="reporting.php" class="card-link-wrapper">
                        <div class="card-container">
                            <h2>Leave Request Trends (<?php echo $currentYear; ?>) -
                                <?php echo htmlspecialchars($dashboardData['department_name'] ?? 'Department'); ?></h2>
                            <div class="chart-container">
                                <canvas id="leaveTrendsChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        window.dashboardData = {
            insightsLabels: <?php echo json_encode($insightsLabels); ?>,
            insightsCounts: <?php echo json_encode($insightsCounts); ?>,
            insightsColors: <?php echo json_encode($insightsColors); ?>,
            trendsLabels: <?php echo json_encode($trendsLabels); ?>,
            trendsData: <?php echo json_encode($trendsData); ?>,
            currentYear: <?php echo json_encode($currentYear); ?>
        };

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', () => {
            const insightsCtx = document.getElementById('leaveInsightsChart').getContext('2d');
            new Chart(insightsCtx, {
                type: 'pie',
                data: {
                    labels: window.dashboardData.insightsLabels,
                    datasets: [{
                        data: window.dashboardData.insightsCounts,
                        backgroundColor: window.dashboardData.insightsColors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: false }
                    }
                }
            });

            const trendsCtx = document.getElementById('leaveTrendsChart').getContext('2d');
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: window.dashboardData.trendsLabels,
                    datasets: [{
                        label: 'Leave Requests',
                        data: window.dashboardData.trendsData,
                        borderColor: '#4a90e2',
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

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
        });
    </script>
    <script src="../../assets/js/manager_dashboard.js"></script>
</body>

</html>