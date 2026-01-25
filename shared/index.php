<?php
session_start();
include "database.php";

$message = "";
$message_type = "";
$show_registration = true;
$is_login_mode = isset($_GET['mode']) && $_GET['mode'] === 'login';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['register'])) {
        // Registration mode
        $username = trim($_POST["username"]);
        $email = trim($_POST["email"]);
        $password = trim($_POST["password"]);
        $cpassword = trim($_POST["cpassword"]);

        // Validate username
        if (strlen($username) < 3) {
            $message = "Username must be at least 3 characters long.";
            $message_type = "error";
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $message = "Username can only contain letters, numbers, underscores, and hyphens.";
            $message_type = "error";
        } elseif ($password !== $cpassword) {
            $message = "Password and Confirm Password do not match!";
            $message_type = "error";
        } else {
            $errors = [];

            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters long.";
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = "Password must contain at least one uppercase letter.";
            }
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = "Password must contain at least one lowercase letter.";
            }
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = "Password must contain at least one number.";
            }
            if (!preg_match('/[\W_]/', $password)) {
                $errors[] = "Password must contain at least one special character.";
            }

            if (!empty($errors)) {
                $message = implode("<br>", $errors);
                $message_type = "error";
            } else {
                // Check if username already exists
                $check_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $check_user->bind_param("s", $username);
                $check_user->execute();
                $check_user_result = $check_user->get_result();

                if ($check_user_result->num_rows > 0) {
                    $message = "Username already taken!";
                    $message_type = "error";
                } else {
                    // Check if email already exists
                    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $check->bind_param("s", $email);
                    $check->execute();
                    $check_result = $check->get_result();

                    if ($check_result->num_rows > 0) {
                        $message = "Email already registered!";
                        $message_type = "error";
                    } else {
                        // Generate OTP
                        $otp = generateOTP();
                        $expires_at = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

                        // Store OTP registration
                        $stmt = $conn->prepare("
                        INSERT INTO otp_registrations (email, otp_code, expires_at, is_verified)
                        VALUES (?, ?, ?, FALSE)
                        ON DUPLICATE KEY UPDATE
                            otp_code = VALUES(otp_code),
                            expires_at = VALUES(expires_at),
                            is_verified = FALSE
                        ");

                        $stmt->bind_param("sss", $email, $otp, $expires_at);

                        if ($stmt->execute()) {
                            // Store temporarily in session
                            $_SESSION['reg_username'] = $username;
                            $_SESSION['reg_email'] = $email;
                            $_SESSION['reg_password'] = $password;
                            $_SESSION['otp_for'] = 'registration';

                            // Send OTP email
                            $subject = "Email Verification - OTP Code";
                            $email_body = "
                            <html>
                            <body style='font-family: Inter, Arial, sans-serif;'>
                            <h2>Email Verification</h2>
                            <p>Welcome to E-Shop!</p>
                            <p>Your registration OTP code is:</p>
                            <div style='background: #f0f0f0; padding: 15px; border-radius: 6px; margin: 15px 0;'>
                                <p style='font-size: 32px; font-weight: bold; color: #2d6cdf; margin: 0;'>$otp</p>
                            </div>
                            <p>This code will expire in 10 minutes.</p>
                            <p>If you did not register, please ignore this email.</p>
                            <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                            <p style='font-size: 12px; color: #999;'>This is an automated message from E-Shop. Please do not reply to this email.</p>
                            </body>
                            </html>";
                            
                            sendEmail($email, $subject, $email_body);

                            $message = "OTP sent to your email. Please verify to complete registration.";
                            $message_type = "success";
                            
                            // Redirect to OTP verification
                            header("Location: otp_verify.php");
                            exit;
                        } else {
                            $message = "Error during registration. Please try again.";
                            $message_type = "error";
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - E-Shop</title>
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

        input[type="email"],
        input[type="password"],
        input[type="text"] {
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

        input[type="text"] {
            width: 100%;
        }

        .input-success {
            border-color: var(--success);
        }

        .input-error {
            border-color: var(--error);
        }

        .username-feedback {
            font-size: 12px;
            margin-top: 4px;
            margin-bottom: 8px;
            text-align: left;
        }

        .username-feedback.valid {
            color: var(--success);
        }

        .username-feedback.invalid {
            color: var(--error);
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

        #password-checklist {
            padding: 0;
            margin: 0.5rem 0;
            display: none;
            font-size: 12px;
            text-align: left;
            list-style: none;
        }

        #password-checklist li {
            list-style: none;
            margin: 4px 0;
            padding-left: 20px;
            position: relative;
            color: #707070;
        }

        #password-checklist li::before {
            content: "✗";
            position: absolute;
            left: 0;
            color: var(--error);
            font-size: 12px;
        }

        #password-checklist li.valid {
            color: var(--success);
        }

        #password-checklist li.valid::before {
            content: "✓";
            color: var(--success);
        }

        #confirm-msg {
            font-size: 12px;
            text-align: left;
            margin-top: -4px;
            margin-bottom: 0.5rem;
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

        .login-link {
            text-align: center;
            margin-top: 1rem;
            font-size: 14px;
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .logo {
            text-align: center;
            font-size: 32px;
            margin-bottom: 1rem;
        }

        .password-field {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-field input {
            width: 100%;
            padding: 12px;
            padding-right: 40px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🛒</div>
        <h2>Create Account</h2>
        <p class="subtitle">Register to start shopping and add items to cart</p>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label style="font-weight: 500; margin-bottom: 5px; display: block;">Username:</label>
            <input type="text" id="username" name="username" placeholder="Username" required>
            <div id="username-feedback" class="username-feedback" style="display: none;"></div>

            <label style="font-weight: 500; margin-bottom: 5px; display: block; margin-top: 10px;">Email Address:</label>
            <input type="email" name="email" placeholder="your@email.com" required>

            <label style="font-weight: 500; margin-bottom: 5px; display: block; margin-top: 10px;">Password:</label>
            <div class="password-field">
                <input type="password" id="password" name="password" placeholder="Password" required>
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

            <ul id="password-checklist">
                <li id="check-length">At least 8 characters</li>
                <li id="check-upper">Contains uppercase</li>
                <li id="check-lower">Contains lowercase</li>
                <li id="check-number">Contains number</li>
                <li id="check-special">Contains special character</li>
            </ul>

            <label style="font-weight: 500; margin-bottom: 5px; display: block;">Confirm Password:</label>
            <div class="password-field">
                <input type="password" id="cpassword" name="cpassword" placeholder="Confirm Password" required>
                <button type="button" class="password-toggle" onclick="togglePassword('cpassword')">
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
            <div id="confirm-msg"></div>

            <button type="submit" name="register">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
        const username = document.getElementById("username");
        const usernameFeedback = document.getElementById("username-feedback");

        username.addEventListener("input", () => {
            const val = username.value;
            const isValid = val.length >= 3 && /^[a-zA-Z0-9_-]+$/.test(val);

            if (val.length === 0) {
                usernameFeedback.style.display = "none";
                username.classList.remove("input-success", "input-error");
            } else if (val.length < 3) {
                usernameFeedback.style.display = "block";
                usernameFeedback.className = "username-feedback invalid";
                usernameFeedback.textContent = "✗ Username must be at least 3 characters";
                username.classList.add("input-error");
                username.classList.remove("input-success");
            } else if (!/^[a-zA-Z0-9_-]+$/.test(val)) {
                usernameFeedback.style.display = "block";
                usernameFeedback.className = "username-feedback invalid";
                usernameFeedback.textContent = "✗ Only letters, numbers, underscores, and hyphens allowed";
                username.classList.add("input-error");
                username.classList.remove("input-success");
            } else {
                usernameFeedback.style.display = "block";
                usernameFeedback.className = "username-feedback valid";
                usernameFeedback.textContent = "✓ Username looks good";
                username.classList.add("input-success");
                username.classList.remove("input-error");
            }
        });

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

        const password = document.getElementById("password");
        const cpassword = document.getElementById("cpassword");
        const checklist = document.getElementById("password-checklist");
        const checkLength = document.getElementById("check-length");
        const checkUpper = document.getElementById("check-upper");
        const checkLower = document.getElementById("check-lower");
        const checkNumber = document.getElementById("check-number");
        const checkSpecial = document.getElementById("check-special");
        const confirmMsg = document.getElementById("confirm-msg");

        password.addEventListener("input", () => {
            const val = password.value;
            checklist.style.display = val.length > 0 ? "block" : "none";

            validate(val.length >= 8, checkLength);
            validate(/[A-Z]/.test(val), checkUpper);
            validate(/[a-z]/.test(val), checkLower);
            validate(/[0-9]/.test(val), checkNumber);
            validate(/[\W_]/.test(val), checkSpecial);

            const allValid =
                val.length >= 8 &&
                /[A-Z]/.test(val) &&
                /[a-z]/.test(val) &&
                /[0-9]/.test(val) &&
                /[\W_]/.test(val);

            if (allValid) {
                password.classList.add("input-success");
                password.classList.remove("input-error");
                checklist.style.display = "none";
            } else {
                password.classList.add("input-error");
                password.classList.remove("input-success");
            }
        });

        cpassword.addEventListener("input", () => {
            if (cpassword.value === password.value && cpassword.value !== "") {
                cpassword.classList.add("input-success");
                cpassword.classList.remove("input-error");
                confirmMsg.style.color = "#2a9d8f";
                confirmMsg.textContent = "✓ Passwords match";
            } else {
                cpassword.classList.add("input-error");
                cpassword.classList.remove("input-success");
                confirmMsg.style.color = "#e63946";
                confirmMsg.textContent = "✗ Passwords do not match";
            }
        });

        function validate(condition, element) {
            element.classList.toggle("valid", condition);
        }
    </script>
</body>
</html>



