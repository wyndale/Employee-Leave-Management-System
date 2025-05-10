<?php
require_once __DIR__ . '/../../backend/src/Auth.php';
require_once __DIR__ . '/../../backend/src/Session.php';
require_once __DIR__ . '/../../backend/utils/redirect.php';

$auth = new Auth();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        redirect('/frontend/views/login.php', 'Please fill in all fields.', 'error');
    }

    if ($auth->login($username, $password)) {
        $role = Session::getRole();
        $redirectUrl = $role === 'employee' ? '/frontend/views/employee_dashboard.php' : '/frontend/views/manager_dashboard.php';
        redirect($redirectUrl, 'Login successful!');
    } else {
        redirect('/frontend/views/login.php', 'Invalid email or password.', 'error');
    }
}

$message = Session::get('message');
$messageType = Session::get('message_type');
Session::set('message', null);
Session::set('message_type', null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Employee Leave Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="main-container">
        <div class="illustration-container">
            <img src="../assets/img/login-ill.png" alt="Professional Employee Illustration" class="illustration">
        </div>
        <div class="form-container">
            <div class="login-card">
                <h2>Sign in</h2>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                <?php if ($message): ?>
                    <p class="message <?php echo htmlspecialchars($messageType); ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="input-group">
                        <input type="email" id="email" name="email" required>
                        <label for="email">Email address</label>
                    </div>
                    <div class="input-group">
                        <input type="password" id="password" name="password" required>
                        <label for="password">Password</label>
                    </div>
                    <div class="options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember_me">
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                    </div>
                    <button type="submit">Sign in</button>
                    <div class="links">
                        <a href="#">Forgot password?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>