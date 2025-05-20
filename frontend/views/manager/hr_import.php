<?php
require_once __DIR__ . '/../../../backend/src/Database.php';
require_once __DIR__ . '/../../../backend/src/Session.php';
require_once __DIR__ . '/../../../backend/models/Auth.php';
require_once __DIR__ . '/../../../backend/middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../../../backend/utils/redirect.php';
require_once __DIR__ . '/../../../backend/controllers/HRImportController.php';

// Initialize session and middleware
Session::start();
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->handle() || Session::get('role') !== 'manager') {
    redirect('/', 'Unauthorized access.', 'error');
}

$controller = new HRImportController();
$managerId = Session::get('user_id');
$manager = $controller->getManager($managerId);

// Flash messages
$message = Session::get('message');
Session::set('message', null);

// Import results
$importResults = Session::get('import_results') ?? [];
Session::set('import_results', null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Data Import - Leave Management System</title>
    <link rel="stylesheet" href="../../assets/css/manager_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            padding: 12px 20px;
            margin-bottom: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
            max-width: 400px;
        }

        .toast.visible {
            opacity: 1;
        }

        .toast-success {
            border-left: 4px solid #28a745;
        }

        .toast-error {
            border-left: 4px solid #dc3545;
        }

        .import-results {
            margin-top: 20px;
        }

        .import-results .summary {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .import-results .summary .success {
            color: #28a745;
            font-weight: bold;
        }

        .import-results .summary .skipped {
            color: #dc3545;
            font-weight: bold;
        }

        .import-results h3 {
            font-size: 1.3rem;
            margin: 10px 0;
        }

        .import-results .result-table,
        .import-results .error-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .import-results .result-table th,
        .import-results .result-table td,
        .import-results .error-table th,
        .import-results .error-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .import-results .result-table th,
        .import-results .error-table th {
            background: #f4f4f4;
            font-weight: bold;
        }

        .import-results .result-table tr:nth-child(even),
        .import-results .error-table tr:nth-child(even) {
            background: #f9f9f9;
        }

        .import-results .error-table .action {
            color: #666;
            font-style: italic;
        }

        .tooltip-container {
            position: relative;
            display: inline-block;
            margin-left: 8px;
        }

        .tooltip-icon {
            color: #4a90e2;
            font-size: 1rem;
            cursor: pointer;
        }

        .tooltip-text {
            visibility: hidden;
            width: 300px;
            background-color: #fff;
            color: #1f2937;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 9999;
            top: -120%;
            left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            font-size: 0.9rem;
            line-height: 1.4;
            white-space: normal;
            word-wrap: break-word;
            transition: top 0.1s ease;
        }

        .tooltip-text.bottom {
            top: auto;
            bottom: 125%;
        }

        .tooltip-text::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #fff transparent transparent transparent;
        }

        .tooltip-text.bottom::after {
            bottom: auto;
            top: -10px;
            border-color: transparent transparent #fff transparent;
        }

        .tooltip-container:hover .tooltip-text {
            visibility: visible;
        }

        .card-container {
            position: relative;
            min-height: 0;
        }

        .card {
            position: relative;
            min-height: 0;
            overflow: visible !important;
        }

        .form-group {
            overflow: visible !important;
        }

        @media (max-width: 768px) {
            .tooltip-text {
                width: 180px;
                font-size: 0.75rem;
                top: -140%;
            }

            .tooltip-text.bottom {
                top: auto;
                bottom: 150%;
            }
        }
    </style>
</head>
<body class="background-gradient font-poppins">
    <div class="container">
        <aside class="sidebar sidebar-collapsed">
            <div class="sidebar-header">
                <button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
            </div>
            <nav class="sidebar-nav">
                <a href="manager_dashboard.php" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span class="sidebar-text">Home</span>
                </a>
                <a href="manage_requests.php" class="sidebar-link">
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
                <a href="hr_import.php" class="sidebar-link active">
                    <i class="fas fa-upload"></i>
                    <span class="sidebar-text">HR Data Import</span>
                </a>
                <a href="settings.php" class="sidebar-link sidebar-link-bottom">
                    <i class="fas fa-cog"></i>
                    <span class="sidebar-text">Settings</span>
                </a>
            </nav>
        </aside>

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
                <div class="greeting-text">
                    <h1 class="greeting-title">HR Data Import</h1>
                    <p>Upload a CSV file to add employee data.</p>
                </div>

                <div class="card-container">
                    <h2>Import Employee Data</h2>
                    <div class="card">
                        <form action="../../../backend/controllers/HRImportController.php?action=import_csv" method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="csv_file">Upload CSV File
                                    <span class="tooltip-container">
                                        <i class="fas fa-info-circle tooltip-icon"></i>
                                        <span class="tooltip-text">CSV must include headers: FIRST_NAME, LAST_NAME, EMAIL, PHONE_NUMBER, HIRE_DATE, MANAGER_ID, DEPARTMENT_ID, ROLE</span>
                                    </span>
                                </label>
                                <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                            </div>
                            <button type="submit" class="submit-button">Import CSV</button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($importResults)): ?>
                    <div class="card-container import-results">
                        <h2>Import Results</h2>
                        <div class="summary">
                            <div class="success">Employees Added Successfully: <?php echo $importResults['success_count']; ?></div>
                            <div class="skipped">Records Skipped: <?php echo $importResults['skipped_count']; ?></div>
                        </div>
                        <?php if (!empty($importResults['success_rows'])): ?>
                            <h3>Successfully Imported Employees</h3>
                            <table class="result-table">
                                <thead>
                                    <tr>
                                        <th>Employee ID</th>
                                        <th>First Name</th>
                                        <th>Last Name</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($importResults['success_rows'] as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        <?php if (!empty($importResults['errors'])): ?>
                            <h3>Issues Encountered</h3>
                            <table class="error-table">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Issue</th>
                                        <th>Action Needed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($importResults['errors'] as $error): ?>
                                        <?php
                                        $email = 'Unknown';
                                        $issue = $error;
                                        $action = 'Review CSV data and try again.';
                                        if (preg_match('/Duplicate email \(([^)]+)\)/', $error, $matches)) {
                                            $email = $matches[1];
                                            $issue = 'Duplicate email already exists.';
                                            $action = 'Use a unique email address.';
                                        } elseif (preg_match('/Missing required fields/', $error, $matches)) {
                                            $issue = 'Missing required fields (e.g., first_name, last_name, email).';
                                            $action = 'Ensure all required fields are filled.';
                                        } elseif (preg_match('/Invalid email \(([^)]+)\)/', $error, $matches)) {
                                            $email = $matches[1];
                                            $issue = 'Invalid email address format.';
                                            $action = 'Verify the email address format.';
                                        } elseif (preg_match('/Invalid department_id \(([^)]+)\)/', $error, $matches)) {
                                            $email = preg_match('/email \(([^)]+)\)/', $error, $emailMatch) ? $emailMatch[1] : 'Unknown';
                                            $issue = "Invalid department_id ({$matches[1]}).";
                                            $action = 'Ensure the department_id exists in the departments table.';
                                        } elseif (preg_match('/Invalid manager_id \(([^)]+)\)/', $error, $matches)) {
                                            $email = preg_match('/email \(([^)]+)\)/', $error, $emailMatch) ? $emailMatch[1] : 'Unknown';
                                            $issue = "Invalid manager_id ({$matches[1]}).";
                                            $action = 'Ensure the manager_id exists or leave blank.';
                                        } elseif (strpos($error, 'Insufficient columns') !== false) {
                                            $issue = 'Incorrect number of columns in CSV row.';
                                            $action = 'Ensure all rows have the correct number of columns.';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($email); ?></td>
                                            <td><?php echo htmlspecialchars($issue); ?></td>
                                            <td class="action"><?php echo htmlspecialchars($action); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../../assets/js/manager_dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            form.addEventListener('submit', function (event) {
                const fileInput = document.getElementById('csv_file');
                if (!fileInput.files.length) {
                    event.preventDefault();
                    showToast('Please select a CSV file.', 'error', 5000);
                    return;
                }
                const file = fileInput.files[0];
                if (!file.name.endsWith('.csv')) {
                    event.preventDefault();
                    showToast('Only CSV files are allowed.', 'error', 5000);
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    event.preventDefault();
                    showToast('File size exceeds 5MB limit.', 'error', 5000);
                    return;
                }
            });

            <?php if ($message): ?>
                showToast('<?php echo addslashes(htmlspecialchars($message['text'])); ?>', '<?php echo htmlspecialchars($message['type']); ?>', 5000);
            <?php endif; ?>

            <?php if (!empty($importResults)): ?>
                showToast('CSV import completed with <?php echo $importResults['success_count']; ?> employees added.', 'success', 5000);
            <?php endif; ?>

            const tooltipIcon = document.querySelector('.tooltip-icon');
            if (tooltipIcon) {
                tooltipIcon.addEventListener('mouseover', function () {
                    const tooltip = this.nextElementSibling;
                    const rect = tooltip.getBoundingClientRect();
                    const viewportHeight = window.innerHeight;

                    if (rect.top < 10 || rect.bottom > viewportHeight - 10) {
                        tooltip.classList.add('bottom');
                        tooltip.style.top = 'auto';
                    } else {
                        tooltip.classList.remove('bottom');
                        tooltip.style.top = '-120%';
                    }
                });

                tooltipIcon.addEventListener('mouseout', function () {
                    const tooltip = this.nextElementSibling;
                    tooltip.classList.remove('bottom');
                    tooltip.style.top = '-120%';
                });
            }
        });
    </script>
</body>
</html>