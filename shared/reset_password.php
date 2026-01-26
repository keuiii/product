<?php
session_start();
include "database.php";

$message = "";
$message_type = "";
$token_valid = false;
$token = "";

// Check if token is provided
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token
    $stmt = $conn->prepare("SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.username, u.email 
                            FROM password_resets pr 
                            JOIN users u ON pr.user_id = u.id 
                            WHERE pr.token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $reset = $result->fetch_assoc();
        
        // Check if token is expired
        if (time() > strtotime($reset['expires_at'])) {
            $message = "This password reset link has expired. Please request a new one.";
            $message_type = "error";
        } elseif ($reset['used'] == 1) {
            $message = "This password reset link has already been used. Please request a new one.";
            $message_type = "error";
        } else {
            $token_valid = true;
        }
    } else {
        $message = "Invalid password reset link.";
        $message_type = "error";
    }
} else {
    $message = "No reset token provided.";
    $message_type = "error";
}

// Handle password reset form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && $token_valid) {
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    
    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        $message = "All fields are required.";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    } elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $message_type = "error";
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $reset['user_id']);
        
        if ($stmt->execute()) {
            // Mark token as used
            $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            
            // Reset failed login attempts
            resetFailedAttempts($conn, $reset['user_id']);
            
            // Log the action
            logAudit($conn, $reset['user_id'], $reset['username'], "PASSWORD_RESET", "Password reset completed successfully");
            
            // Send confirmation email
            $subject = "Password Changed Successfully - E-Shop";
            $body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                    <h2 style='color: #2a9d8f;'>Password Changed Successfully</h2>
                    <p>Hello <strong>{$reset['username']}</strong>,</p>
                    <p>Your password has been successfully changed.</p>
                    <p>If you did not make this change, please contact support immediately.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . "/login.php' style='background-color: #2d6cdf; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Login to Your Account</a>
                    </div>
                    <hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>
                    <p style='font-size: 12px; color: #666;'>This is an automated message from E-Shop. Please do not reply to this email.</p>
                </div>
            </body>
            </html>
            ";
            
            sendEmail($reset['email'], $subject, $body);
            
            $message = "Your password has been reset successfully! You can now login with your new password.";
            $message_type = "success";
            $token_valid = false; // Prevent form from showing again
        } else {
            $message = "Failed to reset password. Please try again.";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - E-Shop</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap');

        :root {
            --bg: #f5f6fa;
            --card-bg: #ffffff;
            --text: #2b2b2b;
            --primary: #2d6cdf;
            --primary-hover: #1f54b8;
            --error: #d62828;
            --success: #2a9d8f;
            --border: #dadada;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            background: var(--bg);
            font-family: 'Inter', sans-serif;
            color: var(--text);
            padding: 20px;
        }

        .container {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.05);
        }

        .logo {
            text-align: center;
            font-size: 32px;
            margin-bottom: 1rem;
        }

        h2 {
            font-weight: 600;
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 14px;
        }

        form {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: #fafafa;
            font-size: 14px;
            transition: 0.2s;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
        }

        button {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 500;
            transition: 0.2s;
            margin-top: 0.5rem;
        }

        button:hover {
            background: var(--primary-hover);
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 13px;
        }

        .error {
            color: var(--error);
            background: rgba(214, 40, 40, 0.12);
            border: 1px solid rgba(214, 40, 40, 0.3);
        }

        .success {
            color: var(--success);
            background: rgba(42, 157, 143, 0.12);
            border: 1px solid rgba(42, 157, 143, 0.3);
        }

        .auth-link {
            text-align: center;
            margin-top: 1rem;
            font-size: 14px;
        }

        .auth-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-link a:hover {
            text-decoration: underline;
        }

        .password-field {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-field input {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: #fafafa;
            font-size: 14px;
            transition: 0.2s;
            font-family: inherit;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 0.5rem;
            padding-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🔑</div>
        <h2>Reset Password</h2>
        <p class="subtitle">Enter your new password below</p>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($token_valid && $message_type !== "success"): ?>
        <form method="POST">
            <label style="font-weight: 500; margin-bottom: 5px; display: block;">New Password:</label>
            <div class="password-field">
                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">
                    <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-closed-icon" style="display:none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>
            <div class="password-requirements">
                Must be at least 8 characters long
            </div>

            <label style="font-weight: 500; margin-bottom: 5px; display: block; margin-top: 10px;">Confirm Password:</label>
            <div class="password-field">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                    <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-closed-icon" style="display:none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>

            <button type="submit">Reset Password</button>
        </form>
        <?php endif; ?>

        <div class="auth-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, button) {
            const field = document.getElementById(fieldId);
            const eyeIcon = button.querySelector('.eye-icon');
            const eyeClosedIcon = button.querySelector('.eye-closed-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                eyeIcon.style.display = 'none';
                eyeClosedIcon.style.display = 'block';
            } else {
                field.type = 'password';
                eyeIcon.style.display = 'block';
                eyeClosedIcon.style.display = 'none';
            }
        }
    </script>
</body>
</html>
