<?php
require_once __DIR__ . '/../models/Auth.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../utils/redirect.php';

class AuthMiddleware {
    private $auth;
    private $baseUrl = '/employee-leave-management-system';

    public function __construct() {
        Session::start();
        $this->auth = new Auth();
    }

    public function handle() {
        if (Session::isLoggedIn()) {
            return true;
        }

        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            if ($this->auth->validateRememberToken($token)) {
                $role = Session::getRole();
                $firstLogin = Session::get('first_login');
                if ($firstLogin && $role === 'employee') {
                    redirect($this->baseUrl . '/frontend/public/login.php', 'Please change your password on first login.', 'info');
                }
                return true;
            } else {
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }
        }

        redirect($this->baseUrl . '/frontend/public/login.php', 'Please log in to access this page.', 'info');
        return false;
    }
}