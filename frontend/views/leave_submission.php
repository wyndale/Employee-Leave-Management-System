<?php
require_once __DIR__ . '/../../backend/src/Session.php';
require_once __DIR__ . '/../../backend/utils/redirect.php';
require_once __DIR__ . '/../../backend/src/Database.php';
require_once __DIR__ . '/../../backend/controllers/EmployeeDashboardController.php';

Session::start();
Session::validate();

// Check if user is logged in and is an employee
if (!Session::isLoggedIn() || Session::getRole() !== 'employee') {
    redirect('/employee-leave-management-system', 'Please log in to access this page.', 'error');
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
    <style>
        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            position: relative;
        }
        .notification-item.sent {
            background-color: #f9f9f9;
            opacity: 0.7;
        }
        .notification-item .notification-actions {
            margin-top: 5px;
            display: flex;
            gap: 10px;
        }
        .notification-item .notification-actions button {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.2s ease;
        }
        .notification-item .mark-read {
            background: #28a745;
            color: white;
        }
        .notification-item .delete {
            background: #dc3545;
            color: white;
        }
        .notification-item .mark-read:hover, .notification-item .delete:hover {
            opacity: 0.9;
        }
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .notification-header .delete-all {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .notification-header .delete-all:hover {
            opacity: 0.9;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8rem;
        }
    </style>
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
                            <span id="notification-badge" class="notification-badge" style="display: none;"></span>
                        </button>
                        <div id="notification-dropdown" class="notification-dropdown background-white shadow hidden">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <button id="delete-all-notifications" class="delete-all">Delete All</button>
                            </div>
                            <div id="notification-list"></div>
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
                    <div class="toast toast-<?php echo $messageType === 'success' ? 'success' : 'error'; ?> visible">
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Leave Submission Form -->
            <main class="main padding-20">
                <div class="greeting-text">
                    <h1 class="greeting-title">Leave Request Submission</h1>
                </div>
                <div class="form-container">
                    <form id="leaveSubmissionForm" class="leave-form">
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
            // Show toast notification
            function showToast(message, type) {
                const toast = document.createElement('div');
                toast.className = `toast toast-${type} visible`;
                toast.innerHTML = `<span>${message}</span>`;
                document.getElementById('toast-container').appendChild(toast);
                setTimeout(() => {
                    toast.classList.remove('visible');
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            }

            // Update notifications
            function updateNotifications() {
                fetch('/employee-leave-management-system/backend/controllers/LeaveSubmissionController.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_notifications' })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const notificationList = document.getElementById('notification-list');
                        const badge = document.getElementById('notification-badge');
                        notificationList.innerHTML = '';
                        if (data.notifications.length === 0) {
                            notificationList.innerHTML = '<p class="no-notifications">No notifications available.</p>';
                            badge.style.display = 'none';
                        } else {
                            data.notifications.forEach(notification => {
                                const div = document.createElement('div');
                                div.className = `notification-item ${notification.status === 'sent' ? 'sent' : ''}`;
                                div.dataset.id = notification.notification_id;
                                div.innerHTML = `
                                    <p>${notification.message}</p>
                                    <span class="notification-time">${new Date(notification.created_at).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                                    <div class="notification-actions">
                                        ${notification.status === 'pending' ? `<button class="mark-read">Mark as Read</button>` : ''}
                                        <button class="delete">Delete</button>
                                    </div>
                                `;
                                notificationList.appendChild(div);
                            });
                            badge.textContent = data.unreadCount;
                            badge.style.display = data.unreadCount > 0 ? 'block' : 'none';
                        }
                    } else {
                        showToast(data.message || 'Error fetching notifications', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                    showToast('Error fetching notifications', 'error');
                });
            }

            // Poll for notifications every 10 seconds
            updateNotifications();
            setInterval(updateNotifications, 10000);

            // Handle notification actions
            document.getElementById('notification-list').addEventListener('click', (e) => {
                const target = e.target;
                const notificationItem = target.closest('.notification-item');
                if (!notificationItem) return;
                const notificationId = notificationItem.dataset.id;

                if (target.classList.contains('mark-read')) {
                    fetch('/employee-leave-management-system/backend/controllers/LeaveSubmissionController.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            updateNotifications();
                        } else {
                            showToast(data.message || 'Error marking notification as read', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error marking notification as read:', error);
                        showToast('Error marking notification as read', 'error');
                    });
                } else if (target.classList.contains('delete')) {
                    fetch('/employee-leave-management-system/backend/controllers/LeaveSubmissionController.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete_notification', notification_id: notificationId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            updateNotifications();
                        } else {
                            showToast(data.message || 'Error deleting notification', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting notification:', error);
                        showToast('Error deleting notification', 'error');
                    });
                }
            });

            // Handle delete all notifications
            document.getElementById('delete-all-notifications').addEventListener('click', () => {
                if (confirm('Are you sure you want to delete all notifications?')) {
                    fetch('/employee-leave-management-system/backend/controllers/LeaveSubmissionController.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete_all' })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            updateNotifications();
                        } else {
                            showToast(data.message || 'Error deleting all notifications', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting all notifications:', error);
                        showToast('Error deleting all notifications', 'error');
                    });
                }
            });

            // Form submission with fetch
            const form = document.getElementById('leaveSubmissionForm');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    console.log('Submit event triggered');

                    const leaveType = document.getElementById('leaveType').value;
                    const startDate = document.getElementById('startDate').value;
                    const endDate = document.getElementById('endDate').value;
                    const reason = document.getElementById('reason').value;
                    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

                    const startDateObj = new Date(startDate);
                    const endDateObj = new Date(endDate);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    let hasError = false;

                    if (!leaveType || !startDate || !endDate || !reason) {
                        showToast('Please fill in all required fields.', 'error');
                        hasError = true;
                        console.log('Validation failed: Missing fields');
                    } else if (startDateObj < today) {
                        showToast('Start date cannot be in the past. Please select a future date.', 'error');
                        hasError = true;
                        console.log('Validation failed: Start date in the past');
                    } else if (startDateObj > endDateObj) {
                        showToast('End date must be after start date. Please adjust the dates.', 'error');
                        hasError = true;
                        console.log('Validation failed: End date before start date');
                    }

                    if (!hasError) {
                        fetch('/employee-leave-management-system/backend/controllers/LeaveSubmissionController.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'submit',
                                csrf_token: csrfToken,
                                leaveType,
                                startDate,
                                endDate,
                                reason
                            })
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(data => {
                                    throw new Error(data.message || `HTTP error! status: ${response.status}`);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                showToast(data.message, 'success');
                                form.reset();
                            } else {
                                showToast(data.message || 'Error submitting leave request', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error submitting form:', error);
                            showToast('Error submitting leave request: ' + error.message, 'error');
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>