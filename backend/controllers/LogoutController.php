<?php
require_once __DIR__ . '/../models/Auth.php';
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
        redirect($this->baseUrl . '/', 'You have been logged out.', 'success');
    }
}

// Handle request
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $controller = new LogoutController();
    $controller->handleLogout();
} else {
    error_log("LogoutController: No valid action provided");
    http_response_code(400);
    echo "Invalid action.";
}