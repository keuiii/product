<?php
session_start();
include "../shared/database.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $message = "All fields are required.";
        $message_type = "error";
    } else if (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
        $message_type = "error";
    } else {
        // Check if username exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = "Username already exists.";
            $message_type = "error";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $insert = $conn->prepare("INSERT INTO users (username, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, TRUE, NOW())");
            $insert->bind_param("ssss", $username, $email, $hashed_password, $role);

            if ($insert->execute()) {
                $user_id = $insert->insert_id;
                logAudit($conn, $user_id, $username, "ACCOUNT_CREATED", "Admin account created with role: $role");
                $message = "âœ“ Account created successfully! Username: $username | Role: $role";
                $message_type = "success";
            } else {
                $message = "Error creating account: " . $conn->error;
                $message_type = "error";
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
    <title>Create Admin Account</title>
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
            min-height: 100vh;
        }

        .container {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 2rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.05);
        }

        .logo {
            text-align: center;
            font-size: 32px;
            margin-bottom: 1rem;
        }

        h1 {
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
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        label {
            font-weight: 500;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: #fafafa;
            font-size: 14px;
            transition: 0.2s;
            font-family: 'Inter', sans-serif;
        }

        input:focus, select:focus {
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
            text-align: center;
            font-size: 13px;
            margin-bottom: 1rem;
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

        .role-info {
            background: #f0f4ff;
            border: 1px solid #d0deff;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #1f54b8;
            margin-top: 1rem;
        }

        .role-info strong {
            display: block;
            margin-bottom: 8px;
        }

        .role-info ul {
            margin-left: 20px;
        }

        .role-info li {
            margin: 4px 0;
        }

        .link {
            text-align: center;
            margin-top: 1rem;
            font-size: 13px;
        }

        .link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">ðŸ‘¤</div>
        <h1>Create Account</h1>
        <p class="subtitle">Setup admin, staff, or regular user accounts</p>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" placeholder="e.g., admin_sec" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" placeholder="e.g., admin@example.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" placeholder="Min 8 characters" required>
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="">-- Select Role --</option>
                    <option value="admin_sec">Admin (Full Access)</option>
                    <option value="staff_user">Staff (Orders & Products)</option>
                    <option value="regular_user">Regular User (Shopping)</option>
                    <option value="guest_user">Guest (View Only)</option>
                </select>
            </div>

            <button type="submit">Create Account</button>
        </form>

        <div class="role-info">
            <strong>Role Permissions:</strong>
            <ul>
                <li><strong>Admin:</strong> Manage users, view audit logs, reset passwords</li>
                <li><strong>Staff:</strong> Manage products, approve orders, send emails</li>
                <li><strong>Regular User:</strong> Shop, add to cart, checkout</li>
                <li><strong>Guest:</strong> View products only</li>
            </ul>
        </div>

        <div class="link">
            <a href="index.php">â† Back to Home</a>
        </div>
    </div>
</body>
</html>


