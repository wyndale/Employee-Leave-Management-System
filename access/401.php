<?php
session_start();
$message = $_GET['message'] ?? 'You do not have permission to access this page.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized | Leave Request</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="content" style="text-align: center; padding-top: 50px;">
        <h1>Unauthorized Access</h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="/employee-leave-management-system/login" class="btn">Login</a>
    </div>
    <style>
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 20px;
        }
        .btn:hover {
            background: #2980b9;
        }
    </style>
</body>
</html>