<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the content type header immediately
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../src/Session.php';
    require_once __DIR__ . '/../src/Database.php';
    require_once __DIR__ . '/EmployeeDashboardController.php';

    Session::start();
    Session::requireLogin();
    $employeeId = $_POST['employee_id'] ?? '';
    if (!$employeeId) {
        echo json_encode(['success' => false, 'message' => 'Employee ID not provided']);
        exit;
    }

    $controller = new EmployeeDashboardController();
    $status = $_POST['status'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $leaveType = $_POST['leave_type'] ?? '';
    $page = max(1, (int)($_POST['page'] ?? 1));
    $perPage = 10;

    $leaveHistory = $controller->leaveModel->getLeaveHistory($employeeId, $status, $startDate, $endDate, $leaveType, $page, $perPage);
    $totalRecords = $controller->leaveModel->getTotalLeaveHistoryCount($employeeId, $status, $startDate, $endDate, $leaveType);
    $totalPages = ceil($totalRecords / $perPage);

    ob_start();
    if (!empty($leaveHistory)) {
        ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6b7280; border-bottom: 1px solid #e5e7eb;">Leave Type</th>
                    <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6b7280; border-bottom: 1px solid #e5e7eb;">Start Date</th>
                    <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6b7280; border-bottom: 1px solid #e5e7eb;">End Date</th>
                    <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6b7280; border-bottom: 1px solid #e5e7eb;">Status</th>
                    <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: #6b7280; border-bottom: 1px solid #e5e7eb;">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaveHistory as $request): ?>
                    <tr>
                        <td style="text-align: left; padding: 0.75rem; color: #1f2937; border-bottom: 1px solid #e5e7eb;">
                            <?php echo htmlspecialchars(ucfirst($request['leave_type_name'])); ?>
                        </td>
                        <td style="text-align: left; padding: 0.75rem; color: #1f2937; border-bottom: 1px solid #e5e7eb;">
                            <?php echo date('d M Y', strtotime($request['start_date'])); ?>
                        </td>
                        <td style="text-align: left; padding: 0.75rem; color: #1f2937; border-bottom: 1px solid #e5e7eb;">
                            <?php echo date('d M Y', strtotime($request['end_date'])); ?>
                        </td>
                        <td style="text-align: left; padding: 0.75rem; color: #1f2937; border-bottom: 1px solid #e5e7eb;">
                            <?php echo htmlspecialchars(ucfirst($request['status'])); ?>
                        </td>
                        <td style="text-align: left; padding: 0.75rem; color: #1f2937; border-bottom: 1px solid #e5e7eb;">
                            <?php echo htmlspecialchars($request['comments'] ?? 'No details available'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    } else {
        ?>
        <p style="text-align: center; padding: 1rem; color: #6b7280;">No leave history available for the selected filters.</p>
        <?php
    }
    $tableHtml = ob_get_clean();

    ob_start();
    if ($totalPages > 1) {
        ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="#" class="pagination-link <?php echo $page === $i ? 'active' : ''; ?>" data-page="<?php echo $i; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php
    }
    $paginationHtml = ob_get_clean();

    echo json_encode([
        'success' => true,
        'tableHtml' => $tableHtml,
        'paginationHtml' => $paginationHtml,
        'debug' => [
            'employeeId' => $employeeId,
            'status' => $status,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'leaveType' => $leaveType,
            'page' => $page,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages,
            'leaveHistoryCount' => count($leaveHistory)
        ]
    ]);
} catch (Exception $e) {
    error_log("Error in GetLeaveHistoryController.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching leave history: ' . $e->getMessage()
    ]);
}
exit;