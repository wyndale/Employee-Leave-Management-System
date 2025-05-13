<?php
require_once __DIR__ . '/../../frontend/models/Auth.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../utils/redirect.php';

class LogoutController {
    private $auth;
    private $baseUrl = '/employee-leave-management-system';

    public function __construct() {
        Session::start();
        $this->auth = new Auth();
    }

    public function handleLogout() {
        $this->auth->logout();
        redirect($this->baseUrl . '/frontend/views/login.php', 'You have been logged out.', 'success');
    }
}