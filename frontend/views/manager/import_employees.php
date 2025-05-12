<?php
require_once __DIR__ . '/../../../backend/src/Auth.php';
require_once __DIR__ . '/../../../backend/src/Session.php';
require_once __DIR__ . '/../../../backend/utils/redirect.php';
require_once __DIR__ . '/../../../backend/src/Database.php';

// Ensure user is authenticated and is a manager
if (!Session::isLoggedIn() || Session::getRole() !== 'manager') {
    redirect('/frontend/views/login.php', 'Please log in as a manager to import employees.', 'error');
}

// Initialize database connection
$db = Database::getInstance();
$pdo = $db->getConnection();

// Fetch user details for sidebar
$employee_id = Session::get('user_id');
$stmt = $pdo->prepare("SELECT first_name, last_name, role FROM Employees WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    redirect('/frontend/views/login.php', 'User not found. Please log in again.', 'error');
}

// Handle CSV upload
$errors = [];
$success = '';
$credentials = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading file. Please try again.';
    } elseif ($file['type'] !== 'text/csv' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $errors[] = 'Please upload a valid CSV file.';
    } else {
        $importDir = __DIR__ . '/../assets/imports/';
        if (!is_dir($importDir)) {
            mkdir($importDir, 0755, true);
        }

        $filePath = $importDir . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            $errors[] = 'Failed to move uploaded file.';
        } else {
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                $errors[] = 'Unable to open CSV file.';
            } else {
                $header = fgetcsv($handle);
                $expectedHeaders = ['employee_id', 'first_name', 'last_name', 'email', 'password', 'role'];
                if ($header !== $expectedHeaders) {
                    $errors[] = 'Invalid CSV format. Expected headers: ' . implode(', ', $expectedHeaders);
                } else {
                    $imported = 0;
                    $failed = 0;

                    $stmt = $pdo->prepare("INSERT INTO Employees (employee_id, first_name, last_name, email, password, role, first_login) VALUES (?, ?, ?, ?, ?, ?, TRUE)");

                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) !== count($expectedHeaders)) {
                            $failed++;
                            continue;
                        }

                        list($employee_id, $first_name, $last_name, $email, $password, $role) = $row;

                        if (!is_numeric($employee_id) || empty($first_name) || empty($last_name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['employee', 'manager'])) {
                            $failed++;
                            continue;
                        }

                        $tempPassword = bin2hex(random_bytes(4));
                        $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);
                        $credentials[] = "Email: $email, Temporary Password: $tempPassword";

                        try {
                            $stmt->execute([$employee_id, $first_name, $last_name, $email, $hashedPassword, $role]);
                            $imported++;
                        } catch (PDOException $e) {
                            $failed++;
                        }
                    }

                    fclose($handle);
                    unlink($filePath);

                    $success = "Imported $imported employees successfully. $failed records failed to import.";
                    if (!empty($credentials)) {
                        $success .= '<br><strong>Please notify employees with these credentials:</strong><br><pre>' . implode("\n", $credentials) . '</pre>';
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Employees - Employee Leave Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-toggle">
                <i class="fas fa-bars" id="toggle-btn"></i>
            </div>
            <div class="sidebar-header">
                <h3>Manager Dashboard</h3>
                <p class="sidebar-user"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> (Manager)</p>
            </div>
            <nav class="sidebar-nav">
                <a href="import_employees.php" class="active" data-tooltip="Import Employees"><i class="fas fa-upload"></i><span>Import Employees</span></a>
                <form method="POST" action="">
                    <button type="submit" name="logout" class="logout-btn" data-tooltip="Logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></button>
                </form>
            </nav>
        </aside>

        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Import Employees</h1>
                    <p>Upload a CSV file to bulk import employee data.</p>
                </div>
            </header>

            <section class="card">
                <h2>Upload CSV File</h2>
                <?php if (!empty($errors)): ?>
                    <div class="error-messages">
                        <?php foreach ($errors as $error): ?>
                            <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <p style="color: green;"><?php echo $success; ?></p>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="csv_file" accept=".csv" required>
                    <button type="submit" class="submit-btn">Import</button>
                </form>
                <p><strong>Expected CSV Format:</strong> employee_id,first_name,last_name,email,password,role</p>
                <p><a href="../assets/imports/sample_employees.csv" download>Download Sample CSV</a></p>
            </section>
        </main>
    </div>

    <script>
        const toggleBtn = document.getElementById('toggle-btn');
        const sidebar = document.getElementById('sidebar');
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });
    </script>
</body>
</html>