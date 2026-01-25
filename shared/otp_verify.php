<?php
session_start();
include "database.php";

// Verify user has started registration
if (!isset($_SESSION['reg_email'])) {
    header("Location: index.php");
    exit;
}

$email = $_SESSION['reg_email'];
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verify_otp'])) {
    $otp_code = trim($_POST['otp_code']);

    // Check OTP
    $stmt = $conn->prepare("SELECT id, otp_code, is_verified, expires_at FROM otp_registrations WHERE email = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $message = "Invalid request. Please start registration again.";
        $message_type = "error";
    } else {
        $otp_record = $result->fetch_assoc();

        // Check if OTP has expired
        if (time() > strtotime($otp_record['expires_at'])) {
            $message = "OTP has expired. Please register again.";
            $message_type = "error";
        } else if ($otp_record['is_verified']) {
            $message = "This email is already verified. Please log in.";
            $message_type = "error";
        } else if ($otp_record['otp_code'] !== $otp_code) {
            $message = "Invalid OTP code. Please try again.";
            $message_type = "error";
        } else {
            // OTP is valid, mark as verified and create user account
            $username = $_SESSION['reg_username'];
            $password = $_SESSION['reg_password'];
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Check if username already exists (final check)
            $check_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check_user->bind_param("s", $username);
            $check_user->execute();
            if ($check_user->get_result()->num_rows > 0) {
                $message = "Username is no longer available. Please try registration again.";
                $message_type = "error";
            } else {
                // Create user account as regular_user
                $create_user = $conn->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, 'regular_user', TRUE)");
                $create_user->bind_param("sss", $username, $email, $hashed_password);

                if ($create_user->execute()) {
                    $user_id = $create_user->insert_id;

                    // Mark OTP as verified
                    $verify_otp = $conn->prepare("UPDATE otp_registrations SET is_verified = TRUE WHERE email = ?");
                    $verify_otp->bind_param("s", $email);
                    $verify_otp->execute();

                    // Log audit
                    logAudit($conn, $user_id, $username, "REGISTRATION", "New user registered with email: $email");

                    // Clean up session
                    unset($_SESSION['reg_username']);
                    unset($_SESSION['reg_email']);
                    unset($_SESSION['reg_password']);
                    unset($_SESSION['otp_for']);

                    // Set session for logged in user
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'regular_user';

                    $message = "Email verified! Account created successfully. Redirecting to shop...";
                    $message_type = "success";

                    // Redirect to shop after 2 seconds
                    header("Refresh: 2; URL=../guest/shop.php");
                } else {
                    $message = "Error creating account. Please try again.";
                    $message_type = "error";
                }
            }
        }
    }
}

// Check if resend is requested
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['resend_otp'])) {
    // Generate new OTP
    $new_otp = generateOTP();
    $expires_at = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

    // Update OTP registration
    $update_stmt = $conn->prepare("UPDATE otp_registrations SET otp_code = ?, expires_at = ? WHERE email = ?");
    $update_stmt->bind_param("sss", $new_otp, $expires_at, $email);

    if ($update_stmt->execute()) {
        // Send email
        $subject = "Email Verification - New OTP Code";
        $email_body = "
        <html>
        <body>
        <h2>Email Verification</h2>
        <p>Your new OTP code is: <strong style='font-size: 24px; color: #007bff;'>$new_otp</strong></p>
        <p>This code will expire in 10 minutes.</p>
        </body>
        </html>";
        
        sendEmail($email, $subject, $email_body);

        $message = "âœ“ OTP sent to $email (check spam folder if not received)";
        $message_type = "success";
    } else {
        $message = "Error resending OTP. Please try again.";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - E-Shop</title>
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

        .email-display {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            color: #007bff;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        form {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: #fafafa;
            font-size: 16px;
            transition: 0.2s;
            font-family: monospace;
            letter-spacing: 2px;
            text-align: center;
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

        .form-divider {
            text-align: center;
            color: #999;
            margin: 1rem 0;
            font-size: 12px;
        }

        .resend-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .resend-btn {
            background: #6c757d;
        }

        .resend-btn:hover {
            background: #5a6268;
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
        <h2>Verify Email</h2>
        <p class="subtitle">Enter the OTP code sent to your email</p>

        <div class="email-display"><?= htmlspecialchars($email) ?></div>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Display OTP for development/testing -->
        <?php if (isset($_SESSION['otp_code'])): ?>
            <div style="background: #e3f2fd; border: 2px solid #2196F3; padding: 15px; border-radius: 6px; margin-bottom: 1rem; text-align: center;">
                <p style="color: #1976D2; font-size: 12px; margin: 0 0 8px 0;"><strong>ðŸ“Œ Development Mode - OTP Code:</strong></p>
                <p style="color: #1565C0; font-size: 24px; font-weight: bold; font-family: monospace; letter-spacing: 4px; margin: 0;"><?= htmlspecialchars($_SESSION['otp_code']) ?></p>
                <p style="color: #1976D2; font-size: 11px; margin: 8px 0 0 0;">Copy this code and paste it below</p>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label style="font-weight: 500; margin-bottom: 5px; display: block;">OTP Code (6 digits):</label>
            <input type="text" name="otp_code" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required inputmode="numeric">
            <button type="submit" name="verify_otp">Verify OTP</button>
        </form>

        <div class="form-divider">OR</div>

        <form method="POST" class="resend-form">
            <button type="submit" name="resend_otp" class="resend-btn">Resend OTP</button>
        </form>

        <div class="help-text">
            Didn't receive the code? Check your spam folder or click resend above.
        </div>
    </div>
</body>
</html>


