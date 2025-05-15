<?php
require_once __DIR__ . '/backend/controllers/LogoutController.php';
require_once __DIR__ . '/backend/middlewares/AuthMiddleware.php';
require_once __DIR__ . '/backend/src/Session.php';

$baseUrl = '/employee-leave-management-system';
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove the base URL prefix for easier matching
$route = str_replace($baseUrl, '', $requestUri);

// Start the session for all routes
Session::start();

// Basic routing logic
if ($route === '/' || $route === '') {
    header('Location: ' . $baseUrl . '/frontend/public/login.php');
    exit;
} elseif ($route === '/logout') {
    $logoutController = new LogoutController();
    $logoutController->handleLogout();
    exit;
} elseif ($route === '/frontend/views/employee_dashboard.php') {
    $authMiddleware = new AuthMiddleware();
    if ($authMiddleware->handle()) {
        require __DIR__ . '/frontend/views/employee_dashboard.php';
    }
    exit;
}

// Default redirect for unmatched routes
header('Location: ' . $baseUrl . '/frontend/public/login.php');
exit;
?>