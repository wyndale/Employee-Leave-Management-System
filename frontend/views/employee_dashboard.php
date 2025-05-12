<?php
require_once __DIR__ . '/../../backend/src/Auth.php';
require_once __DIR__ . '/../../backend/src/Session.php';
require_once __DIR__ . '/../../backend/utils/redirect.php';
require_once __DIR__ . '/../../backend/src/Database.php';

// Ensure user is authenticated and is an employee
if (!Session::isLoggedIn() || Session::getRole() !== 'employee') {
    redirect('/frontend/views/login.php', 'Your session has expired. Please log in again to continue.', 'error');
}

// Initialize database connection using the Singleton pattern
$db = Database::getInstance();
$pdo = $db->getConnection();

// Fetch employee details
$employee_id = Session::get('user_id');
$stmt = $pdo->prepare("SELECT first_name, last_name, email, role FROM Employees WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if employee data was found
if (!$employee) {
    redirect('/frontend/views/login.php', 'Employee not found. Please log in again.', 'error');
}

// Fetch leave balances
$stmt = $pdo->prepare("
    SELECT lt.name, lb.balance 
    FROM Leave_Balances lb 
    JOIN Leave_Types lt ON lb.leave_type_id = lt.leave_type_id 
    WHERE lb.employee_id = ?
");
$stmt->execute([$employee_id]);
$leave_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent leave requests (limit to 5)
$stmt = $pdo->prepare("
    SELECT lr.request_id, lr.start_date, lr.end_date, lt.name AS leave_type, lr.status 
    FROM Leave_Requests lr 
    JOIN Leave_Types lt ON lr.leave_type_id = lt.leave_type_id 
    WHERE lr.employee_id = ? 
    ORDER BY lr.created_at DESC 
    LIMIT 5
");
$stmt->execute([$employee_id]);
$leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    $auth = new Auth();
    $auth->logout();
    redirect('/frontend/views/login.php', 'You have been logged out.', 'success');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Employee Leave Management System</title>
    <link rel="stylesheet" href="../assets/css/employee_dashboard.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-toggle">
                <i class="fas fa-bars" id="toggle-btn"></i>
            </div>
            <div class="sidebar-header">
                <h3>Employee Dashboard</h3>
                <p class="sidebar-user"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> (<?php echo htmlspecialchars($employee['role']); ?>)</p>
            </div>
            <nav class="sidebar-nav">
                <a href="employee_dashboard.php" class="active" data-tooltip="Home"><i class="fas fa-home"></i><span>Dashboard</span></a>
                <a href="request_leave.php" data-tooltip="Request a Leave"><i class="fas fa-calendar-plus"></i><span>Request Leave</span></a>
                <a href="leave_history.php" data-tooltip="View Leave History"><i class="fas fa-history"></i><span>Leave History</span></a>
                <form method="POST" action="">
                    <button type="submit" name="logout" class="logout-btn" data-tooltip="Logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></button>
                </form>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Welcome, <?php echo htmlspecialchars($employee['first_name']); ?>!</h1>
                    <p>Manage your leave requests and track your balances below.</p>
                </div>
                <div class="header-right">
                    <div class="search-bar">
                        <input type="text" placeholder="Search leave requests..." id="search-input">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="notification-bell" data-tooltip="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="notification-count">3</span>
                    </div>
                </div>
            </header>

            <!-- Leave Balance Card -->
            <section class="card leave-balance-card">
                <h2>Leave Balances</h2>
                <?php if (empty($leave_balances)): ?>
                    <p>No leave balances available.</p>
                <?php else: ?>
                    <div class="grid-container">
                        <?php foreach ($leave_balances as $balance): ?>
                            <div class="grid-item" data-tooltip="Remaining: <?php echo htmlspecialchars($balance['balance']); ?> days">
                                <h3><?php echo htmlspecialchars($balance['name']); ?></h3>
                                <p><?php echo htmlspecialchars($balance['balance']); ?> Days</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Recent Leave Requests Card -->
            <section class="card recent-requests-card">
                <h2>Recent Leave Requests</h2>
                <?php if (empty($leave_requests)): ?>
                    <p>No recent leave requests.</p>
                <?php else: ?>
                    <table class="requests-table" id="requests-table">
                        <thead>
                            <tr>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Leave Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leave_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                                    <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                                    <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                    <td class="status-<?php echo htmlspecialchars($request['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($request['status'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        // Toggle Sidebar
        const toggleBtn = document.getElementById('toggle-btn');
        const sidebar = document.getElementById('sidebar');
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });

        // Search Functionality (Client-side Filtering)
        const searchInput = document.getElementById('search-input');
        const requestsTable = document.getElementById('requests-table');
        if (requestsTable) {
            const rows = requestsTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            searchInput.addEventListener('input', () => {
                const searchTerm = searchInput.value.toLowerCase();
                for (let row of rows) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                }
            });
        }
    </script>
</body>
</html>