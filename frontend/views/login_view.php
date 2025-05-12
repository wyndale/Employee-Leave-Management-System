<?php
extract($data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Leave Request</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
    <div class="main-container">
        <div class="illustration-container">
            <img src="../assets/img/employees-ill.png" alt="Professional Employee Illustration" class="illustration">
        </div>
        <div class="form-container">
            <div class="login-card">
                <h2><?php echo $showChangePasswordForm ? 'Change Password' : 'Sign in'; ?></h2>
                <p><?php echo $showChangePasswordForm ? 'You must change your password before proceeding.' : 'Helping you take the breaks you deserve — the smart way.'; ?></p>
                <?php if ($message): ?>
                    <p class="message <?php echo htmlspecialchars($messageType); ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" action="" class="login-form" style="<?php echo $showChangePasswordForm ? 'display: none;' : ''; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
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

                <!-- Change Password Form -->
                <form method="POST" action="" class="change-password-form" style="<?php echo $showChangePasswordForm ? '' : 'display: none;'; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="input-group">
                        <input type="password" id="new_password" name="new_password" required oninput="validatePassword()">
                        <label for="new_password">New Password</label>
                        <span class="password-toggle" data-input="new_password">
                            <i class="fas fa-eye" id="toggle-new-password"></i>
                        </span>
                    </div>
                    <div class="input-group">
                        <input type="password" id="confirm_password" name="confirm_password" required oninput="validatePassword()">
                        <label for="confirm_password">Confirm Password</label>
                        <span class="password-toggle" data-input="confirm_password">
                            <i class="fas fa-eye" id="toggle-confirm-password"></i>
                        </span>
                        <span id="password-mismatch-error" class="error-message" style="display: none;">Passwords do not match.</span>
                    </div>
                    <button type="submit" name="change_password" id="change-password-btn" disabled>Change Password</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Modal (Moved outside form-container) -->
    <div id="password-modal" class="password-modal">
        <div class="modal-content">
            <h4>Password Requirements</h4>
            <ul>
                <li id="req-length" class="requirement">
                    <span class="icon"></span>At least 8 characters
                </li>
                <li id="req-uppercase" class="requirement">
                    <span class="icon"></span>At least 1 capital letter
                </li>
                <li id="req-number" class="requirement">
                    <span class="icon"></span>At least 1 number
                </li>
                <li id="req-special" class="requirement">
                    <span class="icon"></span>At least 1 special character
                </li>
            </ul>
            <div class="strength-bar-container">
                <div id="strength-bar" class="strength-bar"></div>
                <label>Password Strength:</label>
                <span id="strength-text" class="strength-text"></span>
            </div>
        </div>
        <div class="modal-arrow"></div>
    </div>

    <script src="../assets/js/login.js"></script>
    <script src="../assets/js/password_validation.js"></script>
</body>
</html>