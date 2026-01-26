<?php
session_start();
include "database.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $username = $user['username'];
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
        
        // Store token in database
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, used = 0");
        $stmt->bind_param("issss", $user_id, $token, $expires_at, $token, $expires_at);
        $stmt->execute();
        
        // Send reset email
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
        
        $subject = "Password Reset Request - E-Shop";
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                <h2 style='color: #2d6cdf;'>Password Reset Request</h2>
                <p>Hello <strong>{$username}</strong>,</p>
                <p>We received a request to reset your password for your E-Shop account.</p>
                <p>Click the button below to reset your password:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$reset_link}' style='background-color: #2d6cdf; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                </div>
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; color: #666; font-size: 12px;'>{$reset_link}</p>
                <p><strong>This link will expire in 1 hour.</strong></p>
                <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                <hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>
                <p style='font-size: 12px; color: #666;'>This is an automated message from E-Shop. Please do not reply to this email.</p>
            </div>
        </body>
        </html>
        ";
        
        if (sendEmail($email, $subject, $body)) {
            $message = "Password reset instructions have been sent to your email address.";
            $message_type = "success";
            
            // Log the action
            logAudit($conn, $user_id, $username, "PASSWORD_RESET_REQUEST", "Password reset email sent to {$email}");
        } else {
            $message = "Failed to send reset email. Please try again later.";
            $message_type = "error";
        }
    } else {
        // Don't reveal if email exists or not for security
        $message = "If that email address is in our system, we have sent a password reset link to it.";
        $message_type = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - E-Shop</title>
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

        input[type="email"] {
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

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid var(--primary);
            padding: 12px;
            margin-bottom: 1rem;
            font-size: 13px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🔐</div>
        <h2>Forgot Password?</h2>
        <p class="subtitle">Enter your email address and we'll send you a link to reset your password</p>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($message_type !== "success"): ?>
        <form method="POST">
            <label style="font-weight: 500; margin-bottom: 5px; display: block;">Email Address:</label>
            <input type="email" name="email" placeholder="Enter your registered email" required autofocus>

            <button type="submit">Send Reset Link</button>
        </form>
        <?php endif; ?>

        <div class="auth-link">
            Remember your password? <a href="login.php">Back to Login</a>
        </div>

        <div class="auth-link">
            Don't have an account? <a href="index.php">Register here</a>
        </div>
    </div>
</body>
</html>
