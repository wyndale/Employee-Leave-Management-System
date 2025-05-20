<?php
require_once __DIR__ . '/../../../backend/src/Database.php';
require_once __DIR__ . '/../../../backend/src/Session.php';
require_once __DIR__ . '/../../../backend/models/Auth.php';
require_once __DIR__ . '/../../../backend/middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../../../backend/utils/redirect.php';
require_once __DIR__ . '/../../../backend/controllers/LeaveRequestController.php';

// Initialize session and middleware
Session::start();
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->handle() || Session::get('role') !== 'manager') {
    redirect('/employee-leave-management-system', 'Unauthorized access.', 'error');
}

$controller = new LeaveRequestController();
$managerId = Session::get('user_id');
$manager = $controller->getManager($managerId);

// Get manager's department
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT d.name FROM departments d JOIN employees e ON d.department_id = e.department_id WHERE e.employee_id = ?");
$stmt->execute([$managerId]);
$departmentName = $stmt->fetchColumn() ?: 'Unknown';

// Get page parameter (default: 1)
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$data = $controller->getLeaveRequests($managerId, $page);
$requests = $data['requests'];
$total = $data['total'];
$limit = $data['limit'];
$totalPages = ceil($total / $limit);

// Flash messages
$message = Session::get('message');
Session::set('message', null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Requests - Leave Management System</title>
    <link rel="stylesheet" href="../../assets/css/manager_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .requests-table th,
        .requests-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .requests-table th {
            background: #f4f4f4;
            font-weight: bold;
        }
        .requests-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .requests-table .reason {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .requests-table .reason:hover {
            white-space: normal;
            overflow: visible;
            text-overflow: inherit;
            background: #f0f0f0;
            position: relative;
            z-index: 10;
        }
        .action-button {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-right: 5px;
            transition: background 0.2s ease;
        }
        .approve-button {
            background: #28a745;
            color: white;
        }
        .reject-button {
            background: #dc3545;
            color: white;
        }
        .action-button:hover {
            opacity: 0.9;
        }
        .pagination {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-size: 0.9rem;
            transition: background 0.2s ease;
        }
        .pagination a.active {
            background: #4a90e2;
            color: white;
            border-color: #4a90e2;
            cursor: default;
        }
        .pagination a:not(.active):hover {
            background: #e6f0fa;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal.show {
            opacity: 1;
        }
        .modal-content {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            width: 450px;
            max-width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }
        .modal.show .modal-content {
            transform: translateY(0);
        }
        .modal-content h2 {
            margin: 0 0 12px;
            font-size: 1.5rem;
            color: #333;
        }
        .modal-content p {
            margin: 0 0 16px;
            color: #666;
        }
        .modal-content textarea {
            width: 100%;
            height: 120px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px;
            font-size: 0.9rem;
            resize: vertical;
            transition: border-color 0.2s ease;
        }
        .modal-content textarea:focus {
            border-color: #4a90e2;
            outline: none;
        }
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
        }
        .modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s ease;
        }
        .modal-buttons .confirm-button {
            background: #28a745;
            color: white;
        }
        .modal-buttons .confirm-reject {
            background: #dc3545;
            color: white;
        }
        .modal-buttons .cancel-button {
            background: #6c757d;
            color: white;
        }
        .modal-buttons button:hover {
            opacity: 0.9;
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
                            <span class="profile-name"><?php echo htmlspecialchars($manager['first_name'] ?? 'Manager'); ?></span>
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
                        <div class="toast toast-success visible">
                            <span><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="greeting-text">
                    <h1 class="greeting-title">Manage Leave Requests - <?php echo htmlspecialchars($departmentName); ?></h1>
                    <p>Review and take action on pending leave requests for your department.</p>
                </div>

                <!-- Requests Table -->
                <div class="card-container">
                    <h2>Pending Leave Requests</h2>
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="6">No pending leave requests found in your department.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                        <td class="reason" title="<?php echo htmlspecialchars($request['reason'] ?? 'No reason provided'); ?>">
                                            <?php echo htmlspecialchars(substr($request['reason'] ?? 'No reason provided', 0, 50)) . (strlen($request['reason'] ?? '') > 50 ? '...' : ''); ?>
                                        </td>
                                        <td>
                                            <button class="action-button approve-button" data-id="<?php echo $request['request_id']; ?>" onclick="openModal('approve', <?php echo $request['request_id']; ?>)">Approve</button>
                                            <button class="action-button reject-button" data-id="<?php echo $request['request_id']; ?>" onclick="openModal('reject', <?php echo $request['request_id']; ?>)">Reject</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>">Previous</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div id="approve-modal" class="modal">
        <div class="modal-content">
            <h2>Approve Leave Request</h2>
            <p>Add an optional comment for this approval.</p>
            <textarea id="approve-comment" placeholder="Optional comment"></textarea>
            <div class="modal-buttons">
                <button class="cancel-button" onclick="closeModal('approve')">Cancel</button>
                <button class="confirm-button" onclick="submitAction('approve')">Approve</button>
            </div>
        </div>
    </div>
    <div id="reject-modal" class="modal">
        <div class="modal-content">
            <h2>Reject Leave Request</h2>
            <p>Please provide a reason for rejecting this request.</p>
            <textarea id="reject-comment" placeholder="Enter reason for rejection" required></textarea>
            <div class="modal-buttons">
                <button class="cancel-button" onclick="closeModal('reject')">Cancel</button>
                <button class="confirm-reject" onclick="submitAction('reject')">Reject</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/manager_dashboard.js"></script>
    <script>
        let currentRequestId = null;

        function openModal(action, requestId) {
            currentRequestId = requestId;
            const modal = document.getElementById(`${action}-modal`);
            modal.style.display = 'flex';
            modal.classList.add('show');
            document.getElementById(`${action}-comment`).value = '';
        }

        function closeModal(action) {
            const modal = document.getElementById(`${action}-modal`);
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                currentRequestId = null;
            }, 300);
        }

        function showToast(message, type, duration) {
            console.log('showToast called:', { message, type, duration });
            const toast = document.createElement('div');
            toast.className = `toast toast-${type} visible`;
            toast.innerHTML = `<span>${message}</span>`;
            document.querySelector('.toast-container')?.appendChild(toast) || document.body.appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('visible');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        function submitAction(action) {
            const comment = document.getElementById(`${action}-comment`).value;
            if (action === 'reject' && !comment.trim()) {
                showToast('Please provide a reason for rejection.', 'error', 5000);
                return;
            }

            fetch('../../../backend/controllers/LeaveRequestController.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Debug-Request': 'true'
                },
                body: JSON.stringify({ action, id: currentRequestId, comment })
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('HTTP error! status:', response.status, 'response:', text);
                        throw new Error(`HTTP error! status: ${response.status}, response: ${text}`);
                    });
                }
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                if (!text) {
                    throw new Error('Empty response from server');
                }
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showToast(data.message, 'success', 5000);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message || 'Unknown error', 'error', 5000);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e, 'Raw response:', text);
                    showToast('Invalid server response: ' + (text || 'No response'), 'error', 5000);
                }
                closeModal(action);
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showToast('Error processing request: ' + error.message, 'error', 5000);
                closeModal(action);
            });
        }
    </script>
</body>
</html>