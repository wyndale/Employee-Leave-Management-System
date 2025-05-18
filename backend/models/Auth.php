<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';

class Auth {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            throw new InvalidArgumentException("Email and password are required.");
        }

        $stmt = $this->pdo->prepare("SELECT employee_id AS user_id, first_name, last_name, email, password_hash, role, first_login FROM employees WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            Session::set('user_id', $user['user_id']);
            Session::set('role', $user['role']);
            Session::set('first_name', $user['first_name']);
            Session::set('last_name', $user['last_name']);
            Session::set('first_login', $user['first_login']);
            Session::set('email', $email);
            return true;
        } else {
            error_log("Login failed for $email: " . ($user ? "Password mismatch" : "User not found"));
            return false;
        }
    }

    public function generateRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $this->pdo->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $tokenHash, $expiresAt]);

        return $token;
    }

    public function validateRememberToken($token) {
        if (empty($token)) {
            return false;
        }

        $tokenHash = hash('sha256', $token);
        $currentTime = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("SELECT user_id FROM remember_tokens WHERE token_hash = ? AND expires_at > ?");
        $stmt->execute([$tokenHash, $currentTime]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $userId = $result['user_id'];
            $stmt = $this->pdo->prepare("SELECT employee_id AS user_id, first_name, last_name, email, role, first_login FROM employees WHERE employee_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                Session::set('user_id', $user['user_id']);
                Session::set('role', $user['role']);
                Session::set('first_name', $user['first_name']);
                Session::set('last_name', $user['last_name']);
                Session::set('first_login', $user['first_login']);
                Session::set('email', $user['email']);
                return true;
            }
        }

        return false;
    }

    public function deleteRememberTokens($userId) {
        $stmt = $this->pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    public function updatePassword($user_id, $new_password) {
        $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE employees SET password_hash = ?, first_login = FALSE WHERE employee_id = ?");
        $success = $stmt->execute([$hashedPassword, $user_id]);

        if ($success) {
            $this->deleteRememberTokens($user_id);
        }

        return $success;
    }

    public function getCurrentPasswordHash($email) {
        $stmt = $this->pdo->prepare("SELECT password_hash FROM employees WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['password_hash'] : null;
    }

    public function logout() {
        $userId = Session::get('user_id');
        if ($userId) {
            $this->deleteRememberTokens($userId);
        }
        Session::destroy();
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }

    public function getEmployeeById($employeeId) {
        $stmt = $this->pdo->prepare("SELECT employee_id, first_name, last_name, email, role FROM employees WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getManagerId($employeeId) {
        $stmt = $this->pdo->prepare("SELECT manager_id FROM employees WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        return $stmt->fetchColumn();
    }
}
?>