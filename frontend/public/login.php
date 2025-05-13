<?php
require_once __DIR__ . '/../../backend/controllers/LoginController.php';

$controller = new LoginController();
$data = $controller->handleLogin();
require_once __DIR__ . '/../views/login_view.php';
?>