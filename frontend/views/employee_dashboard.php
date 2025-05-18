<?php
require_once __DIR__ . '/../../backend/src/Session.php';
require_once __DIR__ . '/../../backend/controllers/EmployeeDashboardController.php';

Session::start();
Session::requireLogin();

$controller = new EmployeeDashboardController();
$employee = $controller->getEmployee(Session::get('user_id'));
$leaveRequests = $controller->getLeaveRequests(Session::get('user_id'));
$leaveBalances = $controller->getLeaveBalances(Session::get('user_id'));
$notifications = $controller->getNotifications(Session::get('user_id'));
$unreadCount = $controller->getUnreadNotificationCount(Session::get('user_id'));

// Static greeting aligned with the system's purpose
$greeting = "Hi, " . htmlspecialchars($employee['first_name'] ?? 'User') . "! Ready to manage your leave requests?";

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
    <title>Employee Dashboard - Leave Management System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="background-gradient font-poppins">
    <div class="container full-height">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar sidebar-collapsed">
            <div class="sidebar-header">
                <button id="sidebar-toggle" class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <nav class="sidebar-nav">
                <a href="employee_dashboard.php" class="sidebar-link active">
                    <i class="fas fa-home"></i><span class="sidebar-text">Dashboard</span>
                </a>
                <a href="leave_submission.php" class="sidebar-link">
                    <i class="fas fa-file-signature"></i><span class="sidebar-text">Leave Submission</span>
                </a>
                <a href="leave_history.php" class="sidebar-link">
                    <i class="fas fa-folder-open"></i><span class="sidebar-text">Leave History</span>
                </a>
                <a href="#settings" class="sidebar-link sidebar-link-bottom">
                    <i class="fas fa-cog"></i><span class="sidebar-text">Settings</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header background-gradient-light">
                <div class="header-center">
                    <div class="search-bar">
                        <i class="fas fa-search search-icon"></i>
                        <form action="/employee-leave-management-system/backend/controllers/SearchController.php" method="GET" class="search-form">
                            <input type="text" id="search-input" name="query" placeholder="Search..." class="search-input">
                        </form>
                    </div>
                </div>
                <div class="header-right">
                    <div class="notification-container">
                        <button id="notification-toggle" class="notification-button">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notification-badge"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <div id="notification-dropdown" class="notification-dropdown background-white shadow hidden">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                            </div>
                            <div id="notification-list">
                                <?php if (!empty($notifications)): ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item" data-id="<?php echo $notification['notification_id']; ?>">
                                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <span class="notification-time"><?php echo date('d M Y, H:i', strtotime($notification['created_at'])); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="no-notifications">No notifications available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="profile-container">
                        <button id="profile-toggle" class="profile-button">
                            <img src="/employee-leave-management-system/frontend/assets/img/profile.png" alt="Profile" class="profile-image">
                            <span class="profile-name"><?php echo htmlspecialchars($employee['first_name'] ?? 'User'); ?></span>
                        </button>
                        <div id="profile-dropdown" class="profile-dropdown background-white shadow hidden">
                            <a href="/employee-leave-management-system/views/account_settings.php" class="dropdown-item">Account Settings</a>
                            <a href="/employee-leave-management-system/backend/controllers/LogoutController.php?action=logout" class="dropdown-item">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Toast Container -->
            <div id="toast-container" class="toast-container">
                <?php if ($message): ?>
                    <div class="toast toast-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                        <span><?php echo htmlspecialchars($message); ?></span>
                        <?php if ($messageType !== 'success'): ?>
                            <button class="toast-close">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Main -->
            <main class="main padding-20">
                <div class="greeting-text">
                    <h1 class="greeting-title"><?php echo $greeting; ?></h1>
                </div>

                <div class="card-container">
                    <div class="card-grid card-grid-top">
                        <a href="leave_submission.php" class="card-link-wrapper">
                            <div class="card card-highlight">
                                <div class="card-icon card-icon-large">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <div class="card-content">
                                    <div class="card-title card-title-white">Submit a Leave Request</div>
                                    <div class="card-value card-value-white">Request Now</div>
                                </div>
                            </div>
                        </a>

                        <div class="card">
                            <div class="card-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="card-content">
                                <div class="card-title">Remaining Leave Days</div>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left; padding: 0.5rem; font-weight: 500; color: #6b7280;">Leave Type</th>
                                            <th style="text-align: right; padding: 0.5rem; font-weight: 500; color: #6b7280;">Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($leaveBalances)): ?>
                                            <?php foreach ($leaveBalances as $balance): ?>
                                                <tr>
                                                    <td style="text-align: left; padding: 0.5rem; color: #1f2937;"><?php echo htmlspecialchars(ucfirst($balance['name'])); ?></td>
                                                    <td style="text-align: right; padding: 0.5rem; color: #1f2937;"><?php echo htmlspecialchars($balance['balance']); ?> Days</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="2" style="text-align: center; padding: 0.5rem; color: #6b7280;">No leave balances available.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card-grid card-grid-bottom">
                        <a href="leave_history.php?status=pending" class="card-link-wrapper">
                            <div class="card">
                                <div class="card-icon">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <div class="card-content">
                                    <div class="card-title">Pending Requests</div>
                                    <div class="card-value">
                                        <?php
                                        $pending = array_filter($leaveRequests, fn($req) => $req['status'] === 'pending');
                                        $pendingCount = count($pending);
                                        ?>
                                        <?php echo $pendingCount; ?> Request<?php echo $pendingCount !== 1 ? 's' : ''; ?>
                                    </div>
                                </div>
                            </div>
                        </a>

                        <a href="leave_history.php?status=approved" class="card-link-wrapper">
                            <div class="card">
                                <div class="card-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="card-content">
                                    <div class="card-title">Approved Requests</div>
                                    <div class="card-value">
                                        <?php
                                        $approved = array_filter($leaveRequests, fn($req) => $req['status'] === 'approved');
                                        $approvedCount = count($approved);
                                        ?>
                                        <?php echo $approvedCount; ?> Request<?php echo $approvedCount !== 1 ? 's' : ''; ?>
                                    </div>
                                </div>
                            </div>
                        </a>

                        <a href="leave_history.php" class="card-link-wrapper">
                            <div class="card">
                                <div class="card-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div class="card-content">
                                    <div class="card-title">Leave History</div>
                                    <div class="card-value">View All</div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>