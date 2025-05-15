<?php
require_once __DIR__ . '/../../backend/controllers/EmployeeDashboardController.php';
require_once __DIR__ . '/../../backend/src/Session.php';
require_once __DIR__ . '/../../backend/utils/redirect.php';

Session::start();
Session::validate();

// Check if user is logged in and is an employee
if (!Session::isLoggedIn() || Session::getRole() !== 'employee') {
    redirect('../frontend/public/login.php', 'Please log in to access this page.', 'error');
}

// Generate CSRF token if not set
if (!Session::get('csrf_token')) {
    Session::set('csrf_token', bin2hex(random_bytes(32)));
}

$controller = new EmployeeDashboardController();
$employee = $controller->getEmployee(Session::get('user_id'));
$leaveRequests = $controller->getLeaveRequests(Session::get('user_id'));
$leaveBalances = $controller->getLeaveBalances(Session::get('user_id'));
$notifications = $controller->getNotifications(Session::get('user_id'));
$unreadCount = $controller->getUnreadNotificationCount(Session::get('user_id'));

// Time-based greeting (12:10 PM PST, May 15, 2025)
$hour = (int)date('H', strtotime('2025-05-15 12:10:00 -0700')); // PST
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

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
                <a href="#dashboard" class="sidebar-link active">
                    <i class="fas fa-home"></i><span class="sidebar-text">Dashboard</span>
                </a>
                <a href="#leave-submission" class="sidebar-link">
                    <i class="fas fa-file-signature"></i><span class="sidebar-text">Leave Submission</span>
                </a>
                <a href="#leave-history" class="sidebar-link">
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
                        <form action="../backend/controllers/SearchController.php" method="GET" class="search-form">
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
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item" data-id="<?php echo $notification['notification_id']; ?>">
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <span class="notification-time"><?php echo date('d M Y, H:i', strtotime($notification['created_at'])); ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($notifications)): ?>
                                    <p class="no-notifications">No notifications available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="profile-container">
                        <button id="profile-toggle" class="profile-button">
                            <img src="../frontend/assets/img/profile.png" alt="Profile" class="profile-image">
                            <span class="profile-name"><?php echo htmlspecialchars($employee['first_name']); ?></span>
                        </button>
                        <div id="profile-dropdown" class="profile-dropdown background-white shadow hidden">
                            <a href="../views/account_settings.php" class="dropdown-item">Account Settings</a>
                            <a href="../backend/controllers/LogoutController.php" class="dropdown-item">Logout</a>
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

            <!-- Dashboard Content -->
            <main class="main padding-20">
                <!-- Greeting Text -->
                <div class="greeting-text">
                    <h1 class="greeting-title"><?php echo "Good afternoon, " . htmlspecialchars($employee['first_name']) . "! Ready to tackle today with a fresh start?"; ?></h1>
                </div>

                <!-- Key Info Cards -->
                <div class="card-container">
                    <!-- First Row -->
                    <div class="card-grid card-grid-top">
                        <a href="../views/leave_submission.php" class="card-link-wrapper">
                            <div class="card card-highlight shadow">
                                <div class="card-icon card-icon-large">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div class="card-content">
                                    <h3 class="card-title card-title-white">Submit a Leave Request</h3>
                                    <p class="card-value card-value-white">Request Now</p>
                                </div>
                            </div>
                        </a>
                        <div class="card background-white shadow">
                            <div class="card-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Remaining Leave Days</h3>
                                <p class="card-value"><?php echo array_sum(array_column($leaveBalances, 'balance')); ?> Days</p>
                            </div>
                        </div>
                    </div>

                    <!-- Second Row -->
                    <div class="card-grid card-grid-bottom">
                        <a href="../views/pending_requests.php" class="card-link-wrapper">
                            <div class="card background-white shadow">
                                <div class="card-icon">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <div class="card-content">
                                    <h3 class="card-title">Pending Requests</h3>
                                    <p class="card-value"><?php echo count(array_filter($leaveRequests, fn($req) => $req['status'] === 'pending')); ?> Request<?php echo count(array_filter($leaveRequests, fn($req) => $req['status'] === 'pending')) !== 1 ? 's' : ''; ?></p>
                                </div>
                            </div>
                        </a>
                        <a href="../views/approved_requests.php" class="card-link-wrapper">
                            <div class="card background-white shadow">
                                <div class="card-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="card-content">
                                    <h3 class="card-title">Approved Requests</h3>
                                    <?php
                                    $approved = array_filter($leaveRequests, fn($req) => $req['status'] === 'approved');
                                    $approvedCount = count($approved);
                                    ?>
                                    <p class="card-value"><?php echo $approvedCount; ?> Request<?php echo $approvedCount !== 1 ? 's' : ''; ?></p>
                                </div>
                            </div>
                        </a>
                        <a href="../views/leave_history.php" class="card-link-wrapper">
                            <div class="card background-white shadow">
                                <div class="card-icon">
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <div class="card-content">
                                    <h3 class="card-title">Leave History</h3>
                                    <p class="card-value">View All</p>
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