<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../models/Auth.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../utils/redirect.php';
require_once __DIR__ . '/../controllers/ManagerDashboardController.php';
require_once __DIR__ . '/../vendor/tcpdf.php';

// Initialize session and middleware
Session::start();
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->handle() || Session::get('role') !== 'manager') {
    redirect('/employee-leave-management-system', 'Unauthorized access.', 'error');
}

$controller = new ManagerDashboardController();
$managerId = Session::get('user_id');

// Get manager and department details
$dashboardData = $controller->getManagerDashboardData($managerId);
$departmentName = $dashboardData['department_name'] ?? 'Department';
$managerName = $dashboardData['manager']['first_name'] . ' ' . $dashboardData['manager']['last_name'];

// Get available years for validation
$availableYears = $controller->getAvailableYears($managerId);

// Get and validate year from POST
$year = isset($_POST['year']) && is_numeric($_POST['year']) && in_array((int)$_POST['year'], $availableYears) 
    ? (int)$_POST['year'] 
    : date('Y');

$summaryData = $controller->getMonthlyLeaveSummary($managerId, $year);

// Initialize TCPDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($managerName);
$pdf->SetTitle('Monthly Leave Summary Report');
$pdf->SetSubject('Leave Summary for ' . $departmentName . ', ' . $year);
$pdf->SetKeywords('leave, summary, report, ' . $departmentName);

// Set default header data
$pdf->SetHeaderData('', 0, 'Monthly Leave Summary Report', "Department: $departmentName\nManager: $managerName\nYear: $year\nGenerated on: " . date('d F Y'));

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set font
$pdf->SetFont('helvetica', '', 12);

// Add a page
$pdf->AddPage();

// Create HTML content for the table
$html = '
<h1 style="text-align: center;">Monthly Leave Summary Report</h1>
<p style="text-align: center;">
    Department: ' . htmlspecialchars($departmentName) . '<br>
    Manager: ' . htmlspecialchars($managerName) . '<br>
    Year: ' . $year . '<br>
    Generated on: ' . date('d F Y') . '
</p>
<table border="1" cellpadding="4" cellspacing="0">
    <thead>
        <tr style="background-color: #f0f0f0;">
            <th><b>Month</b></th>
            <th><b>Pending</b></th>
            <th><b>Approved</b></th>
            <th><b>Rejected</b></th>
            <th><b>Total Days</b></th>
        </tr>
    </thead>
    <tbody>';

foreach ($summaryData as $row) {
    $html .= '
        <tr>
            <td>' . htmlspecialchars($row['month_name']) . '</td>
            <td>' . $row['pending_count'] . '</td>
            <td>' . $row['approved_count'] . '</td>
            <td>' . $row['rejected_count'] . '</td>
            <td>' . $row['total_days'] . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>
<p style="text-align: center; font-size: 10pt;">Monthly Leave Summary for ' . htmlspecialchars($departmentName) . ', ' . $year . '</p>';

// Write HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output('leave_summary_' . $year . '.pdf', 'D');

exit;
?>