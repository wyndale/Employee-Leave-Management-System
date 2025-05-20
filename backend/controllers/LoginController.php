<?php
require_once __DIR__ . '/../models/Auth.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../utils/redirect.php';

class LoginController {
    private $auth;
    private $baseUrl = '/employee-leave-management-system';
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 15 * 60;
    private $pdo;

    public function __construct() {
        Session::start();
        $this->auth = new Auth();
        $this->pdo = Database::getInstance()->getConnection();
        if (!Session::get('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }
    }

    private function logAudit($employeeId, $action, $details) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt = $this->pdo->prepare("INSERT INTO audit_logs (employee_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$employeeId, $action, $details, $ipAddress, $userAgent]);
    }

    public function handleLogin() {
        $error = '';
        $showChangePasswordForm = false;
        $email = '';
        $message = Session::get('message');
        $messageType = Session::get('message_type');
        Session::set('message', null);
        Session::set('message_type', null);

        // Check for a remember token and auto-login if valid
        if (!Session::isLoggedIn() && isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            if ($this->auth->validateRememberToken($token)) {
                $role = Session::getRole();
                $firstLogin = Session::get('first_login');
                $userId = Session::get('user_id');

                // Log the auto-login event
                $this->logAudit($userId, 'auto_login', 'User auto-logged in via remember token');

                if ($firstLogin) {
                    $showChangePasswordForm = true;
                    $email = Session::get('email');
                } else {
                    $redirectUrl = $role === 'employee' 
                        ? $this->baseUrl . '/frontend/views/employee_dashboard.php'
                        : $this->baseUrl . '/frontend/views/manager/manager_dashboard.php';
                    redirect($redirectUrl, 'Welcome back! You were automatically logged in.', 'success');
                }
            } else {
                // Invalid token, clear the cookie
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }
        }

        // If the user is already logged in (via session or token validation), redirect them
        if (Session::isLoggedIn()) {
            $role = Session::getRole();
            $firstLogin = Session::get('first_login');
            $email = Session::get('email');

            if ($firstLogin) {
                $showChangePasswordForm = true;
            } else {
                $redirectUrl = $role === 'employee' 
                    ? $this->baseUrl . '/frontend/views/employee_dashboard.php'
                    : $this->baseUrl . '/frontend/views/manager/manager_dashboard.php';
                redirect($redirectUrl, 'You are already logged in.');
            }
        }

        // Brute force lockout check
        $loginAttempts = Session::get('login_attempts', 0);
        $lastAttemptTime = Session::get('last_attempt_time', 0);
        if ($loginAttempts >= $this->maxLoginAttempts && (time() - $lastAttemptTime) < $this->lockoutDuration) {
            $remainingTime = $this->lockoutDuration - (time() - $lastAttemptTime);
            $errorMessage = "Too many login attempts. Try again in " . ceil($remainingTime / 60) . " minutes.";
            redirect($this->baseUrl . '/frontend/public/login.php', $errorMessage, 'error');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $sessionCsrfToken = Session::get('csrf_token');
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $sessionCsrfToken) {
                redirect($this->baseUrl . '/frontend/public/login.php', 'Invalid CSRF token.', 'error');
            }

            if (isset($_POST['change_password'])) {
                $newPassword = trim($_POST['new_password'] ?? '');
                $confirmPassword = trim($_POST['confirm_password'] ?? '');

                if (empty($newPassword) || strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || 
                    !preg_match('/[0-9]/', $newPassword) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
                    redirect($this->baseUrl . '/frontend/public/login.php', 'Password must be at least 8 characters, include 1 uppercase letter, 1 number, and 1 special character.', 'error');
                }

                $userId = Session::get('user_id');
                $email = Session::get('email');
                $currentPasswordHash = $this->auth->getCurrentPasswordHash($email);

                if ($currentPasswordHash && password_verify($newPassword, $currentPasswordHash)) {
                    redirect($this->baseUrl . '/frontend/public/login.php', 'Please enter a different password. You’re currently using this one.', 'error');
                }

                if ($newPassword === $confirmPassword && $newPassword !== '') {
                    if ($this->auth->updatePassword($userId, $newPassword)) {
                        $this->logAudit($userId, 'password_change', 'User changed their password');
                        $redirectUrl = Session::getRole() === 'employee' 
                            ? $this->baseUrl . '/frontend/views/employee_dashboard.php'
                            : $this->baseUrl . '/frontend/views/manager/manager_dashboard.php';
                        redirect($redirectUrl, 'Password updated successfully!', 'success');
                    } else {
                        redirect($this->baseUrl . '/frontend/public/login.php', 'Failed to update password. Please try again.', 'error');
                    }
                } else {
                    redirect($this->baseUrl . '/frontend/public/login.php', 'Passwords do not match.', 'error');
                }
            } else {
                $email = trim($_POST['email'] ?? '');
                $password = trim($_POST['password'] ?? '');

                // First, attempt to authenticate the user
                if ($this->auth->login($email, $password)) {
                    // If authentication succeeds, check the user's status
                    $stmt = $this->pdo->prepare("SELECT employee_id, status FROM employees WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user && $user['status'] === 'inactive') {
                        // Log the failed login attempt due to inactive status
                        $this->logAudit($user['employee_id'], 'login_failed', 'Account is inactive');
                        // Clear the session since login should not proceed
                        $this->auth->logout();
                        redirect($this->baseUrl . '/frontend/public/login.php', 'Your account is inactive. Please contact HR.', 'error');
                    }

                    // If status is active, proceed with login
                    $role = Session::getRole();
                    $firstLogin = Session::get('first_login');
                    Session::set('email', $email);

                    Session::set('login_attempts', 0);
                    Session::set('last_attempt_time', 0);

                    $userId = Session::get('user_id');
                    $this->logAudit($userId, 'login_success', 'User logged in successfully');

                    if (isset($_POST['remember_me'])) {
                        $token = $this->auth->generateRememberToken($userId);
                        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                    }

                    if ($firstLogin) {
                        $showChangePasswordForm = true;
                    } else {
                        $redirectUrl = $role === 'employee' 
                            ? $this->baseUrl . '/frontend/views/employee_dashboard.php'
                            : $this->baseUrl . '/frontend/views/manager/manager_dashboard.php';
                        redirect($redirectUrl, 'Login successful!');
                    }
                } else {
                    // If authentication fails, increment login attempts and show generic error
                    $loginAttempts++;
                    Session::set('login_attempts', $loginAttempts);
                    Session::set('last_attempt_time', time());
                    $errorMessage = 'Invalid email or password.';

                    // Check if the email exists to log the failed attempt
                    $stmt = $this->pdo->prepare("SELECT employee_id FROM employees WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        $this->logAudit($user['employee_id'], 'login_failed', 'Invalid password');
                    }

                    redirect($this->baseUrl . '/frontend/public/login.php', $errorMessage, 'error');
                }
            }

            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }

        return [
            'error' => $error,
            'showChangePasswordForm' => $showChangePasswordForm,
            'email' => $email,
            'message' => $message,
            'messageType' => $messageType,
            'csrfToken' => Session::get('csrf_token')
        ];
    }
}
?>