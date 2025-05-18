<?php
require_once __DIR__ . '/../../backend/src/Session.php';
require_once __DIR__ . '/../../backend/utils/redirect.php';
require_once __DIR__ . '/../../backend/src/Database.php';
require_once __DIR__ . '/../../backend/controllers/EmployeeDashboardController.php';

Session::start();
Session::validate();

// Check if user is logged in and is an employee
if (!Session::isLoggedIn() || Session::getRole() !== 'employee') {
    redirect('/', 'Please log in to access this page.', 'error');
}

// Generate CSRF token if not set
if (!Session::get('csrf_token')) {
    Session::set('csrf_token', bin2hex(random_bytes(32)));
}

// Fetch employee first name using EmployeeDashboardController
$employeeId = Session::get('user_id');
$controller = new EmployeeDashboardController();
$employee = $controller->getEmployee($employeeId);
$employeeName = $employee['first_name'] ?? 'Unknown';

// Fetch leave types from the database
$db = Database::getInstance()->getConnection();
$leaveTypeStmt = $db->prepare("SELECT name, eligibility_criteria FROM leave_types ORDER BY name ASC");
$leaveTypeStmt->execute();
$leaveTypes = $leaveTypeStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch notifications for the employee
function getNotifications($employeeId) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT notification_id, message, created_at, status FROM notifications WHERE employee_id = ? ORDER BY created_at DESC");
    $stmt->execute([$employeeId]);
    return $stmt->fetchAll();
}

function getUnreadNotificationCount($employeeId) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE employee_id = ? AND status = 'pending'");
    $stmt->execute([$employeeId]);
    return (int)$stmt->fetchColumn();
}

$notifications = getNotifications($employeeId);
$unreadCount = getUnreadNotificationCount($employeeId);

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
    <title>Leave Submission - Leave Management System</title>
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
                <a href="employee_dashboard.php" class="sidebar-link">
                    <i class="fas fa-home"></i><span class="sidebar-text">Dashboard</span>
                </a>
                <a href="leave_submission.php" class="sidebar-link active">
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
                            <img src="/employee-leave-management-system/frontend/assets/img/profile.png" alt="Profile" class="profile-image">
                            <span class="profile-name"><?php echo htmlspecialchars($employeeName); ?></span>
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

            <!-- Leave Submission Form -->
            <main class="main padding-20">
                <div class="greeting-text">
                    <h1 class="greeting-title">Leave Request Submission</h1>
                </div>
                <div class="form-container">
                    <form id="leaveSubmissionForm" action="/employee-leave-management-system/backend/controllers/LeaveSubmissionController.php?action=submit" method="POST" class="leave-form">
                        <input type="hidden" name="csrf_token" value="<?php echo Session::get('csrf_token'); ?>">
                        <input type="hidden" name="action" value="submit">
                        <div class="form-group">
                            <label for="leaveType">Leave Type:</label>
                            <select id="leaveType" name="leaveType" class="form-input" required>
                                <option value="">Select Leave Type</option>
                                <?php foreach ($leaveTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['name']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($type['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="startDate">Start Date:</label>
                            <input type="date" id="startDate" name="startDate" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="endDate">End Date:</label>
                            <input type="date" id="endDate" name="endDate" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="reason">Reason:</label>
                            <textarea id="reason" name="reason" class="form-input" rows="4" placeholder="Enter reason for leave" required></textarea>
                        </div>
                        <button type="submit" class="submit-button">Submit Request</button>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('leaveSubmissionForm');
            console.log('Form loaded:', form); // Debug: Confirm form is found
            if (form) {
                form.addEventListener('submit', (e) => {
                    console.log('Submit event triggered'); // Debug: Confirm event listener works
                    const startDateInput = document.getElementById('startDate');
                    const endDateInput = document.getElementById('endDate');
                    const startDate = new Date(startDateInput.value);
                    const endDate = new Date(endDateInput.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0); // Normalize to midnight for comparison

                    console.log('Start Date:', startDate, 'End Date:', endDate, 'Today:', today); // Debug: Log dates

                    let hasError = false;

                    if (!startDateInput.value || !endDateInput.value) {
                        e.preventDefault();
                        showToast('Please select both start and end dates.', 'error');
                        hasError = true;
                        console.log('Validation failed: Missing dates');
                    } else {
                        if (startDate < today) {
                            e.preventDefault();
                            showToast('Start date cannot be in the past. Please select a future date.', 'error');
                            hasError = true;
                            console.log('Validation failed: Start date in the past');
                        }
                        if (startDate > endDate) {
                            e.preventDefault();
                            showToast('End date must be after start date. Please adjust the dates.', 'error');
                            hasError = true;
                            console.log('Validation failed: End date before start date');
                        }
                    }

                    if (hasError) {
                        e.preventDefault(); // Ensure form doesn’t submit if any validation fails
                    }
                });
            }
        });
    </script>
</body>
</html>