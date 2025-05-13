<?php
require_once __DIR__ . '/../../backend/middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../../backend/controllers/EmployeeDashboardController.php';

$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->handle()) {
    exit;
}

$controller = new EmployeeDashboardController();
$data = $controller->handleDashboard();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard | Leave Management</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Welcome, <?php echo $data['firstName'] . ' ' . $data['lastName']; ?>!</h1>
            <a href="/employee-leave-management-system/logout" class="logout-btn">Logout</a>
        </header>
        <main>
            <section class="leave-balance">
                <h2>Leave Balances</h2>
                <?php if (empty($data['leaveBalances'])): ?>
                    <p>No leave balance data available.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($data['leaveBalances'] as $balance): ?>
                            <li><?php echo htmlspecialchars($balance['leave_type_id']); ?>: <?php echo htmlspecialchars($balance['balance']); ?> days</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <section class="pending-requests">
                <h2>Pending Leave Requests</h2>
                <?php if (empty($data['pendingRequests'])): ?>
                    <p>No pending requests.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($data['pendingRequests'] as $request): ?>
                            <li>
                                <?php echo htmlspecialchars($request['start_date']) . ' to ' . htmlspecialchars($request['end_date']); ?>
                                (<?php echo htmlspecialchars($request['duration']); ?> days) - <?php echo htmlspecialchars($request['reason']); ?>
                                [Status: <?php echo htmlspecialchars($request['status']); ?>]
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <section class="leave-request-form">
                <h2>Submit Leave Request</h2>
                <?php if (isset($_GET['message']) && isset($_GET['message_type'])): ?>
                    <div class="message <?php echo htmlspecialchars($_GET['message_type']); ?>">
                        <?php echo htmlspecialchars($_GET['message']); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="input-group">
                        <input type="date" name="start_date" required>
                        <label>Start Date</label>
                    </div>
                    <div class="input-group">
                        <input type="date" name="end_date" required>
                        <label>End Date</label>
                    </div>
                    <div class="input-group">
                        <textarea name="reason" rows="4" required placeholder="Reason"></textarea>
                        <label>Reason</label>
                    </div>
                    <button type="submit">Submit Request</button>
                </form>
            </section>
        </main>
    </div>
</body>
</html>