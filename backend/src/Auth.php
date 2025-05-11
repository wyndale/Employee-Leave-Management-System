<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

class Auth {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT user_id, username, password, role FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            Session::set('user_id', $user['user_id']);
            Session::set('role', $user['role']);
            return true;
        }
        return false;
    }

    public function logout() {
        Session::destroy();
    }
}
?>