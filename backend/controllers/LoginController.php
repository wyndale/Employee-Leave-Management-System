<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../utils/redirect.php';

class LoginController {
    private $auth;
    private $baseUrl = '/employee-leave-management-system';
    private $maxLoginAttempts = 5; // Brute force limit
    private $lockoutDuration = 15 * 60; // 15 minutes lockout

    public function __construct() {
        Session::start();
        $this->auth = new Auth();
        // Ensure a CSRF token exists in the session
        if (!Session::get('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }
    }

    public function handleLogin() {
        $error = '';
        $showChangePasswordForm = false;
        $email = '';
        $message = Session::get('message');
        $messageType = Session::get('message_type');
        Session::set('message', null);
        Session::set('message_type', null);

        // Check for brute force lockout
        $loginAttempts = Session::get('login_attempts', 0);
        $lastAttemptTime = Session::get('last_attempt_time', 0);
        if ($loginAttempts >= $this->maxLoginAttempts && (time() - $lastAttemptTime) < $this->lockoutDuration) {
            $remainingTime = $this->lockoutDuration - (time() - $lastAttemptTime);
            redirect($this->baseUrl . '/frontend/views/login.php', "Too many login attempts. Try again in " . ceil($remainingTime / 60) . " minutes.", 'error');
        }

        if (Session::isLoggedIn()) {
            $role = Session::getRole();
            $firstLogin = Session::get('first_login');
            $email = Session::get('email');

            if ($role === 'employee' && $firstLogin) {
                $showChangePasswordForm = true;
            } else {
                $redirectUrl = $role === 'employee' 
                    ? $this->baseUrl . '/frontend/views/employee_dashboard.php'
                    : $this->baseUrl . '/frontend/views/manager/employee_dashboard.php';
                redirect($redirectUrl, 'You are already logged in.');
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate CSRF token using the session value
            $sessionCsrfToken = Session::get('csrf_token');
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $sessionCsrfToken) {
                redirect($this->baseUrl . '/frontend/views/login.php', 'Invalid CSRF token.', 'error');
            }

            if (isset($_POST['change_password'])) {
                $newPassword = trim($_POST['new_password'] ?? '');
                $confirmPassword = trim($_POST['confirm_password'] ?? '');

                // Rely on client-side validation for empty fields and requirements
                // Client-side validation ensures submission only occurs if all checks pass
                $userId = Session::get('user_id');
                $email = Session::get('email'); // Get the email from session
                $currentPasswordHash = $this->auth->getCurrentPasswordHash($email);

                // Validate if new password matches current password
                if ($currentPasswordHash && password_verify($newPassword, $currentPasswordHash)) {
                    redirect($this->baseUrl . '/frontend/views/login.php', 'Please enter a different password. You’re currently using this one.', 'error');
                }

                if ($newPassword === $confirmPassword && $newPassword !== '') {
                    if ($this->auth->updatePassword($userId, $newPassword)) {
                        redirect($this->baseUrl . '/frontend/views/employee_dashboard.php', 'Password updated successfully!', 'success');
                    } else {
                        redirect($this->baseUrl . '/frontend/views/login.php', 'Failed to update password. Please try again.', 'error');
                    }
                } else {
                    redirect($this->baseUrl . '/frontend/views/login.php', 'Passwords do not match.', 'error');
                }
            } else {
                $email = trim($_POST['email'] ?? '');
                $password = trim($_POST['password'] ?? '');

                // Rely on client-side validation for empty fields
                if ($this->auth->login($email, $password)) {
                    $role = Session::getRole();
                    $firstLogin = Session::get('first_login');
                    Session::set('email', $email);

                    // Reset login attempts on successful login
                    Session::set('login_attempts', 0);
                    Session::set('last_attempt_time', 0);

                    if ($role === 'employee' && $firstLogin) {
                        $showChangePasswordForm = true;
                    } else {
                        $redirectUrl = $role === 'employee' 
                            ? $this->baseUrl . '/frontend/views/employee_dashboard.php'
                            : $this->baseUrl . '/frontend/views/manager/employee_dashboard.php';
                        redirect($redirectUrl, 'Login successful!');
                    }
                } else {
                    // Increment login attempts on failure
                    $loginAttempts++;
                    Session::set('login_attempts', $loginAttempts);
                    Session::set('last_attempt_time', time());
                    redirect($this->baseUrl . '/frontend/views/login.php', 'Invalid email or password.', 'error');
                }
            }

            // Generate new CSRF token after successful processing
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