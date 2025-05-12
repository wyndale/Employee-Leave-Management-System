<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

class Auth {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Authenticate a user by email and password.
     *
     * @param string $email User's email address
     * @param string $password User's plain-text password
     * @return bool True if login successful, false otherwise
     * @throws InvalidArgumentException If email or password is empty
     */
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
            return true;
        } else {
            error_log("Login failed for $email: " . ($user ? "Password mismatch" : "User not found"));
            return false;
        }
    }

    /**
     * Update a user's password and set first_login to false.
     *
     * @param int $user_id The ID of the user
     * @param string $new_password The new plain-text password
     * @return bool True if update successful, false otherwise
     */
    public function updatePassword($user_id, $new_password) {
        $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE employees SET password_hash = ?, first_login = FALSE WHERE employee_id = ?");
        return $stmt->execute([$hashedPassword, $user_id]);
    }

    /**
     * Retrieve the current password hash for a user.
     *
     * @param string $email User's email address
     * @return string|null The password hash or null if not found
     */
    public function getCurrentPasswordHash($email) {
        $stmt = $this->pdo->prepare("SELECT password_hash FROM employees WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['password_hash'] : null;
    }

    /**
     * Log out the current user by destroying the session.
     */
    public function logout() {
        Session::destroy();
    }
}
?>