<?php
session_start();
include "database.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, username, password, role, is_locked, locked_until, failed_login_attempts FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user_id = $user["id"];

        // Check if account is locked
        if ($user['is_locked']) {
            if (time() > strtotime($user['locked_until'])) {
                // Lock has expired, reset it
                unlockAccount($conn, $user_id);
            } else {
                // Account is still locked
                $remaining_time = ceil((strtotime($user['locked_until']) - time()) / 60);
                $message = "Account is locked due to multiple failed login attempts. Please try again in " . $remaining_time . " minutes.";
                $message_type = "error";
            }
        }

        // If account is not locked, verify password
        if (empty($message)) {
            if (password_verify($password, $user["password"])) {
                // Login successful, reset failed attempts
                resetFailedAttempts($conn, $user_id);
                
                $_SESSION["user_id"] = $user_id;
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];
                
                logAudit($conn, $user_id, $user["username"], "LOGIN", "User logged in successfully");
                
                // Redirect based on role
                if ($user["role"] === "admin_sec") {
                    header("Location: ../admin/dashboard.php");
                } elseif ($user["role"] === "staff_user") {
                    header("Location: ../staff/dashboard.php");
                } elseif ($user["role"] === "regular_user") {
                    header("Location: ../guest/shop.php");
                } else {
                    header("Location: ../guest/shop.php");
                }
                exit;
            } else {
                // Incorrect password
                $account_locked = incrementFailedAttempts($conn, $user_id);
                
                $stmt = $conn->prepare("SELECT failed_login_attempts FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $attempts_result = $stmt->get_result()->fetch_assoc();
                $attempts = $attempts_result['failed_login_attempts'];

                if ($account_locked) {
                    $message = "Account locked due to " . MAX_LOGIN_ATTEMPTS . " failed login attempts. Please try again in 15 minutes.";
                    logAudit($conn, $user_id, $username, "LOGIN_FAILED", "Account locked after " . MAX_LOGIN_ATTEMPTS . " failed attempts");
                } else {
                    $remaining_attempts = MAX_LOGIN_ATTEMPTS - $attempts;
                    $message = "Incorrect password! You have " . $remaining_attempts . " attempt(s) remaining.";
                    logAudit($conn, $user_id, $username, "LOGIN_FAILED", "Failed login attempt (" . $attempts . "/" . MAX_LOGIN_ATTEMPTS . ")");
                }
                $message_type = "error";
            }
        }
    } else {
        $message = "User not found!";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Shop</title>
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

        input[type="text"],
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

        .help-text {
            font-size: 12px;
            color: #666;
            text-align: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🛒</div>
        <h2>Login</h2>
        <p class="subtitle">Sign in to your account to continue shopping</p>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label style="font-weight: 500; margin-bottom: 5px; display: block;">Username:</label>
            <input type="text" name="username" placeholder="Enter your username" required autofocus>

            <label style="font-weight: 500; margin-bottom: 5px; display: block; margin-top: 10px;">Password:</label>
            <div class="password-field">
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <button type="button" class="password-toggle" onclick="togglePassword('password')">
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

            <button type="submit">Login</button>
        </form>

        <div class="auth-link">
            Don't have an account? <a href="index.php">Register here</a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = event.target.closest('.password-toggle');
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


