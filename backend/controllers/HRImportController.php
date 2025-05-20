<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../models/Auth.php';
require_once __DIR__ . '/../utils/redirect.php';

class HRImportController {
    private $pdo;
    private $auth;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
        $this->auth = new Auth();
    }

    public function getManager($managerId) {
        return $this->auth->getEmployeeById($managerId);
    }

    private function logAudit($managerId, $action, $details) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt = $this->pdo->prepare("INSERT INTO audit_logs (employee_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$managerId, $action, $details, $ipAddress, $userAgent]);
    }

    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function cleanString($string) {
        $string = preg_replace('/[^a-zA-Z0-9]/', ' ', strtolower($string));
        return trim(ucwords($string));
    }

    private function checkDuplicate($email, $existingEmails) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        return in_array(strtolower($email), $existingEmails);
    }

    private function validateRole($role) {
        $validRoles = ['employee', 'manager'];
        $role = strtolower(trim($role));
        return in_array($role, $validRoles) ? $role : 'employee';
    }

    private function validateDepartment($departmentId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_id = ?");
        $stmt->execute([$departmentId]);
        return $stmt->fetchColumn() > 0;
    }

    private function validateManager($managerId, $processedEmployeeIds) {
        if (empty($managerId)) {
            return null;
        }
        $managerId = (int) $managerId;
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id = ?");
        $stmt->execute([$managerId]);
        if ($stmt->fetchColumn() > 0 || in_array($managerId, $processedEmployeeIds)) {
            return $managerId;
        }
        return null;
    }

    private function validateHireDate($hireDate) {
        if (empty($hireDate)) {
            return date('Y-m-d');
        }
        $date = DateTime::createFromFormat('Y-m-d', $hireDate);
        if ($date && $date->format('Y-m-d') === $hireDate) {
            return $hireDate;
        }
        return date('Y-m-d');
    }

    private function generateDynamicPassword($firstName, $lastName, $hireDate) {
        $firstName = strtolower(preg_replace('/[^a-zA-Z]/', '', $firstName));
        $lastName = strtolower(preg_replace('/[^a-zA-Z]/', '', $lastName));

        $firstLetter = substr($firstName, 0, 1) ?: 'x';
        $lastNamePart = strlen($lastName) >= 3 ? substr($lastName, 0, 3) : $lastName;
        $lastNamePart = $lastNamePart ?: 'xxx';

        $hireDay = date('d');
        if ($hireDate && strtotime($hireDate)) {
            $hireDay = date('d', strtotime($hireDate));
        }

        $plainPassword = "{$firstLetter}{$lastNamePart}{$hireDay}!";
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        return ['plain' => $plainPassword, 'hashed' => $hashedPassword];
    }

    public function importCSV() {
        $managerId = Session::get('user_id');
        $results = ['success_count' => 0, 'skipped_count' => 0, 'errors' => [], 'success_rows' => []];
        $logDir = __DIR__ . '/../../../employee-leave-management-system/logs/';
        $logFile = $logDir . 'imported_default_password.log';
        $existingEmails = [];
        $departmentManagers = [];
        $processedEmployeeIds = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
            Session::set('message', ['text' => 'No file uploaded.', 'type' => 'error']);
            redirect('../../frontend/views/manager/hr_import.php');
        }

        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::set('message', ['text' => 'File upload error: ' . $file['error'], 'type' => 'error']);
            redirect('../../frontend/views/manager/hr_import.php');
        }

        if ($file['type'] !== 'text/csv' || $file['size'] > 5 * 1024 * 1024) {
            Session::set('message', ['text' => 'Invalid file type or size (max 5MB).', 'type' => 'error']);
            redirect('../../frontend/views/manager/hr_import.php');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            Session::set('message', ['text' => 'Unable to read file.', 'type' => 'error']);
            redirect('../../frontend/views/manager/hr_import.php');
        }

        $expectedHeaders = ['FIRST_NAME', 'LAST_NAME', 'EMAIL', 'PHONE_NUMBER', 'HIRE_DATE', 'MANAGER_ID', 'DEPARTMENT_ID', 'ROLE'];
        $headers = fgetcsv($handle);
        if (!$headers || array_diff($expectedHeaders, array_map('strtoupper', array_map('trim', $headers)))) {
            fclose($handle);
            Session::set('message', ['text' => 'Invalid CSV headers. Expected: ' . implode(', ', $expectedHeaders), 'type' => 'error']);
            redirect('../../frontend/views/manager/hr_import.php');
        }

        if (!file_exists($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
            $logFile = null;
        }

        $this->pdo->beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < count($expectedHeaders)) {
                    $results['skipped_count']++;
                    $results['errors'][] = "Row skipped: Insufficient columns.";
                    continue;
                }

                $data = array_combine($expectedHeaders, array_slice($row, 0, count($expectedHeaders)));
                $email = trim($data['EMAIL']);

                if (empty($data['FIRST_NAME']) || empty($data['LAST_NAME']) || empty($email)) {
                    $results['skipped_count']++;
                    $results['errors'][] = "Row skipped: Missing required fields (first_name, last_name, email).";
                    continue;
                }

                if (!$this->validateEmail($email)) {
                    $results['skipped_count']++;
                    $results['errors'][] = "Row skipped: Invalid email ($email).";
                    continue;
                }

                if ($this->checkDuplicate($email, $existingEmails)) {
                    $results['skipped_count']++;
                    $results['errors'][] = "Row skipped: Duplicate email ($email).";
                    continue;
                }

                $existingEmails[] = strtolower($email);

                $data['FIRST_NAME'] = $this->cleanString($data['FIRST_NAME']);
                $data['LAST_NAME'] = $this->cleanString($data['LAST_NAME']);
                $data['EMAIL'] = strtolower($email);
                $data['PHONE_NUMBER'] = trim($data['PHONE_NUMBER']) ?: null;
                $data['HIRE_DATE'] = $this->validateHireDate($data['HIRE_DATE']);
                $data['DEPARTMENT_ID'] = (int) ($data['DEPARTMENT_ID'] ?: 0);
                $data['ROLE'] = $this->validateRole($data['ROLE']);

                if (!$this->validateDepartment($data['DEPARTMENT_ID'])) {
                    $results['skipped_count']++;
                    $results['errors'][] = "Row skipped: Invalid department_id ({$data['DEPARTMENT_ID']}) for email ($email).";
                    continue;
                }

                $data['MANAGER_ID'] = $this->validateManager($data['MANAGER_ID'], $processedEmployeeIds);

                $passwordData = $this->generateDynamicPassword($data['FIRST_NAME'], $data['LAST_NAME'], $data['HIRE_DATE']);
                $defaultPassword = $passwordData['hashed'];
                $plainPassword = $passwordData['plain'];

                $stmt = $this->pdo->prepare("
                    INSERT INTO employees (first_name, last_name, email, phone_number, hire_date, manager_id, department_id, role, password_hash, status, first_login, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, NOW(), NOW())
                ");
                $stmt->execute([
                    $data['FIRST_NAME'],
                    $data['LAST_NAME'],
                    $data['EMAIL'],
                    $data['PHONE_NUMBER'],
                    $data['HIRE_DATE'],
                    $data['MANAGER_ID'],
                    $data['DEPARTMENT_ID'],
                    $data['ROLE'],
                    $defaultPassword
                ]);

                $newEmployeeId = $this->pdo->lastInsertId();
                $processedEmployeeIds[] = $newEmployeeId;
                $this->initializeLeaveBalances($newEmployeeId, $data['HIRE_DATE']);

                if ($data['ROLE'] === 'manager' && !isset($departmentManagers[$data['DEPARTMENT_ID']])) {
                    $departmentManagers[$data['DEPARTMENT_ID']] = $newEmployeeId;
                }

                if ($logFile && is_writable($logDir)) {
                    $logEntry = "[" . date('Y-m-d H:i:s') . "] Employee ID: $newEmployeeId, Email: {$data['EMAIL']}, Password: $plainPassword\n";
                    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
                }

                $results['success_rows'][] = [
                    'employee_id' => $newEmployeeId,
                    'first_name' => $data['FIRST_NAME'],
                    'last_name' => $data['LAST_NAME'],
                    'email' => $data['EMAIL']
                ];
                $results['success_count']++;
                $this->logAudit($managerId, 'hr_import', "Imported employee ID: $newEmployeeId, Email: {$data['EMAIL']}");
            }

            foreach ($departmentManagers as $deptId => $managerId) {
                $stmt = $this->pdo->prepare("
                    UPDATE employees 
                    SET manager_id = ? 
                    WHERE department_id = ? AND (manager_id IS NULL OR manager_id = 0) AND role != 'manager'
                ");
                $stmt->execute([$managerId, $deptId]);
            }

            $this->pdo->commit();
            fclose($handle);
            Session::set('import_results', $results);
            Session::set('message', ['text' => "CSV import completed: {$results['success_count']} employees added, {$results['skipped_count']} skipped.", 'type' => 'success']);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            fclose($handle);
            error_log("Import error: " . $e->getMessage());
            Session::set('message', ['text' => 'Import failed: ' . $e->getMessage(), 'type' => 'error']);
        }

        redirect('../../frontend/views/manager/hr_import.php');
    }

    private function initializeLeaveBalances($employeeId, $hireDate) {
        $currentDate = new DateTime('2025-05-19 21:03:00'); // Current time: 09:03 PM PST, May 19, 2025
        $hireDateTime = new DateTime($hireDate);
        $interval = $currentDate->diff($hireDateTime);
        $months = ($interval->y * 12) + $interval->m + ($interval->d / 30.44);

        // Fetch all leave types
        $stmt = $this->pdo->prepare("
            SELECT leave_type_id, name, max_days, eligibility_criteria, is_paid
            FROM leave_types
        ");
        $stmt->execute();
        $leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($leaveTypes as $leaveType) {
            $leaveTypeId = $leaveType['leave_type_id'];
            $name = $leaveType['name'];
            $maxDays = $leaveType['max_days'] !== null ? (float) $leaveType['max_days'] : null;
            $eligibility = $leaveType['eligibility_criteria'] ?? '';
            $isPaid = $leaveType['is_paid'];

            $initialBalance = 0.00;
            $maxBalance = 0.00;
            $accrualRate = null;

            // Handle NULL max_days
            if ($maxDays === null) {
                if ($name === 'Jury Duty Leave') {
                    $maxDays = 10.00; // Policy default: 10 days
                    $maxBalance = 10.00;
                } else { // Unpaid Leave
                    $maxDays = 0.00;
                    $maxBalance = 0.00;
                }
            } else {
                $maxBalance = $maxDays; // Default: max_balance = max_days
            }

            // Special case: Sick Leave (match example)
            if ($leaveTypeId == 2) { // Sick Leave
                $maxBalance = 30.00; // Per example
                $accrualRate = 1.25; // Per example
            }

            // Parse eligibility criteria
            if (stripos($eligibility, 'Immediate upon hire') !== false) {
                // Full entitlement, no proration
                $initialBalance = $maxDays ?? 0.00;
                if ($maxDays !== null && $name !== 'Sick Leave') {
                    $accrualRate = round($maxDays / 12, 2);
                }
            } elseif (preg_match('/(\d+)\s*(?:months?|years?)\s*of\s*service/i', $eligibility, $matches)) {
                $requiredPeriod = (int) $matches[1];
                $isYears = stripos($eligibility, 'year') !== false;
                $requiredMonths = $isYears ? $requiredPeriod * 12 : $requiredPeriod;

                if ($months >= $requiredMonths && $maxDays !== null) {
                    // Prorate from eligibility date
                    $prorationFactor = min(($months - $requiredMonths) / 12, 1);
                    $initialBalance = round($maxDays * $prorationFactor, 2);
                    $accrualRate = round($maxDays / 12, 2);
                }
            }

            // Ensure max_balance for unpaid leaves with max_days
            if ($isPaid === 0 && $maxDays !== null) {
                $maxBalance = $maxDays; // e.g., Personal Leave: max_balance = 10.00
            }

            // Set balance
            $balance = $initialBalance;

            // Insert balance
            $stmt = $this->pdo->prepare("
                INSERT INTO leave_balances (employee_id, leave_type_id, initial_balance, balance, max_balance, accrual_rate, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    initial_balance = VALUES(initial_balance),
                    balance = VALUES(balance),
                    max_balance = VALUES(max_balance),
                    accrual_rate = VALUES(accrual_rate),
                    updated_at = NOW()
            ");
            $stmt->execute([$employeeId, $leaveTypeId, $initialBalance, $balance, $maxBalance, $accrualRate]);
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'import_csv') {
    $controller = new HRImportController();
    $controller->importCSV();
}
?>