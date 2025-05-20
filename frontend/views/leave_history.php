<?php
require_once __DIR__ . '/../../backend/src/Session.php';
require_once __DIR__ . '/../../backend/controllers/EmployeeDashboardController.php';

Session::start();
Session::requireLogin();

// Check if user is logged in and is an employee
if (!Session::isLoggedIn() || Session::getRole() !== 'employee') {
    redirect('/employee-leave-management-system', 'Please log in to access this page.', 'error');
}

$employeeId = Session::get('user_id');
$controller = new EmployeeDashboardController();
$employee = $controller->getEmployee($employeeId);
$notifications = $controller->getNotifications($employeeId);
$unreadCount = $notifications['unreadCount'] ?? 0;
$notificationList = $notifications['notifications'] ?? [];

$status = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$leaveType = $_GET['leave_type'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$leaveHistory = $controller->leaveModel->getLeaveHistory($employeeId, $status, $startDate, $endDate, $leaveType, $page, $perPage);
$totalRecords = $controller->leaveModel->getTotalLeaveHistoryCount($employeeId, $status, $startDate, $endDate, $leaveType);
$totalPages = ceil($totalRecords / $perPage);

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
    <title>Leave History - Leave Management System</title>
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
        /* Pagination */
        .pagination {
            margin-top: 2rem;
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
            z-index: 10;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .pagination a {
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: #4a90e2;
            background: #f9fafb;
            border-radius: 0.5rem;
            transition: background 0.3s ease;
        }
        .pagination a.active {
            background: #4a90e2;
            color: #ffffff;
        }
        .pagination a:hover {
            background: #e0e7ff;
        }
        /* Filter Form */
        .leave-form {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            background: #f9fafb;
            border-radius: 0.5rem;
            width: 100%;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .form-container {
            margin-bottom: 1rem;
            position: relative;
            display: flex;
            justify-content: center;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .form-group label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        .form-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            box-sizing: border-box;
        }
        .button-group {
            display: flex;
            gap: 0.5rem;
        }
        .submit-button,
        .clear-button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background-color 0.3s ease;
        }
        .submit-button {
            background-color: #4a90e2;
            color: white;
        }
        .submit-button:hover {
            background-color: #357abd;
        }
        .clear-button {
            background-color: #e5e7eb;
            color: #6b7280;
        }
        .clear-button:hover {
            background-color: #d1d5db;
        }
        .loading-spinner {
            display: none;
            width: 24px;
            height: 24px;
            border: 3px solid #4a90e2;
            border-top: 3px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .loading-spinner.hidden {
            display: none;
        }
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        /* Table and Card */
        .card-container {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            margin-top: 0.5rem;
            position: relative;
            width: 100%;
        }
        .card {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .card-content {
            padding: 1rem;
            padding-bottom: 2rem;
        }
        .leave-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .leave-table th {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            padding: 0.75rem;
            text-align: center;
            background: #f3f4f6;
        }
        .leave-table td {
            font-size: 0.875rem;
            color: #1f2937;
            padding: 0.75rem;
            text-align: center;
            line-height: 1.5;
        }
        .leave-table tr.row-even {
            background: #ffffff;
        }
        .leave-table tr.row-odd {
            background: #f9fafb;
        }
        .leave-table tr:hover {
            background: #f1f5f9;
        }
        .leave-table td[colspan="3"] {
            font-style: italic;
            color: #6b7280;
        }
        /* Responsive Design */
        @media (min-width: 640px) {
            .leave-form {
                flex-direction: row;
                align-items: flex-end;
                gap: 1rem;
                padding: 0.5rem 1rem;
            }
            .form-group {
                flex-direction: row;
                align-items: center;
                gap: 0.5rem;
                flex: 1;
            }
            .form-input {
                max-width: 120px;
            }
            .button-group {
                flex-shrink: 0;
            }
        }
        @media (max-width: 640px) {
            .form-input {
                width: 100%;
            }
            .submit-button,
            .clear-button {
                width: 100%;
                text-align: center;
            }
            .leave-table {
                font-size: 0.75rem;
            }
            .leave-table th,
            .leave-table td {
                padding: 0.5rem;
            }
            .card {
                margin: 0 0.5rem;
            }
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
                <a href="leave_submission.php" class="sidebar-link">
                    <i class="fas fa-file-signature"></i><span class="sidebar-text">Leave Submission</span>
                </a>
                <a href="leave_history.php" class="sidebar-link active">
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
                            <span id="notification-badge" class="notification-badge" style="display: <?php echo $unreadCount > 0 ? 'block' : 'none'; ?>;"><?php echo $unreadCount; ?></span>
                        </button>
                        <div id="notification-dropdown" class="notification-dropdown background-white shadow hidden">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <button id="delete-all-notifications" class="delete-all">Delete All</button>
                            </div>
                            <div id="notification-list">
                                <?php if (empty($notificationList)): ?>
                                    <p class="no-notifications">No notifications available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="profile-container">
                        <button id="profile-toggle" class="profile-button">
                            <img src="/employee-leave-management-system/frontend/assets/img/profile.png" alt="Profile" class="profile-image">
                            <span class="profile-name"><?php echo htmlspecialchars($employee['first_name'] ?? 'Unknown'); ?></span>
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

            <!-- Leave History -->
            <main class="main padding-20">
                <div class="greeting-text">
                    <h1 class="greeting-title">Leave History</h1>
                </div>
                <div class="form-container">
                    <form method="GET" class="leave-form">
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select id="status" name="status" class="form-input" placeholder="Select...">
                                <option value="">All</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" id="start_date" name="start_date" class="form-input" value="<?php echo htmlspecialchars($startDate); ?>" placeholder="Select date">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date:</label>
                            <input type="date" id="end_date" name="end_date" class="form-input" value="<?php echo htmlspecialchars($endDate); ?>" placeholder="Select date">
                        </div>
                        <div class="form-group">
                            <label for="leave_type">Leave Type:</label>
                            <select id="leave_type" name="leave_type" class="form-input" placeholder="Select...">
                                <option value="">All</option>
                                <?php
                                $leaveTypes = $controller->leaveModel->getAllLeaveTypes();
                                foreach ($leaveTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['name']); ?>" <?php echo $leaveType === $type['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($type['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="submit-button">Filter</button>
                            <button type="button" class="clear-button">Clear</button>
                        </div>
                    </form>
                    <div class="loading-spinner hidden"></div>
                </div>
                <div class="card-container">
                    <div class="card">
                        <div class="card-content">
                            <table class="leave-table">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Status</th>
                                        <th>Dates</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($leaveHistory)): ?>
                                        <?php foreach ($leaveHistory as $index => $request): ?>
                                            <tr class="<?php echo $index % 2 === 0 ? 'row-even' : 'row-odd'; ?>">
                                                <td><?php echo htmlspecialchars(ucfirst($request['leave_type_name'])); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($request['status'])); ?></td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($request['start_date'])) . ' to ' . date('d M Y', strtotime($request['end_date'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3">No leave history available.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <!-- Pagination -->
                            <div class="pagination">
                                <?php if ($totalPages > 1): ?>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&leave_type=<?php echo urlencode($leaveType); ?>"
                                            class="pagination-link <?php echo $page === $i ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('.leave-form');
            const clearButton = document.querySelector('.clear-button');
            const spinner = document.querySelector('.loading-spinner');

            // Show spinner on form submit
            form.addEventListener('submit', () => {
                spinner.classList.remove('hidden');
                setTimeout(() => {
                    spinner.classList.add('hidden');
                }, 1000); // Simulated delay for demo
            });

            // Clear form and reload page
            clearButton.addEventListener('click', () => {
                form.reset();
                window.location.href = 'leave_history.php';
            });

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
                fetch('/employee-leave-management-system/backend/controllers/EmployeeDashboardController.php', {
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
                    fetch('/employee-leave-management-system/backend/controllers/EmployeeDashboardController.php', {
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
                    fetch('/employee-leave-management-system/backend/controllers/EmployeeDashboardController.php', {
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
                    fetch('/employee-leave-management-system/backend/controllers/EmployeeDashboardController.php', {
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
        });
    </script>
</body>
</html>