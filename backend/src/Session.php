<?php
class Session {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        self::start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    public static function destroy() {
        self::start();
        session_unset();
        session_destroy();
    }

    public static function isLoggedIn() {
        return self::get('user_id') !== null;
    }

    public static function getRole() {
        return self::get('role');
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ../frontend/index.php');
            exit;
        }
    }
}
?>