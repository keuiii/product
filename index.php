<?php
session_start();
include "database.php";

$message = "";
$show_welcome = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $cpassword = trim($_POST["cpassword"]);

    if ($password !== $cpassword) {
        $message = "Password and Confirm Password do not match!";
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
        } else {

            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 1) {

                $user = $res->fetch_assoc();
                if (password_verify($password, $user['password'])) {

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    $audit = $conn->prepare("INSERT INTO audit_trail (user_id, username, action, details) VALUES (?, ?, ?, ?)");
                    $action = "Login";
                    $details = "User logged in.";
                    $audit->bind_param("isss", $user['id'], $user['username'], $action, $details);
                    $audit->execute();

                    header("Location: records.php");
                    exit;
                } else {
                    $message = "Incorrect password!";
                }
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $ins->bind_param("ss", $username, $hashed);
                if ($ins->execute()) {
                $new_id = $ins->insert_id;
                $_SESSION['user_id'] = $new_id;
                $_SESSION['username'] = $username;

                $otp = rand(100000, 999999);
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_expires'] = time() + 180;
                $_SESSION['otp_for'] = "registration";

                $audit = $conn->prepare("INSERT INTO audit_trail (user_id, username, action, details) VALUES (?, ?, ?, ?)");
                $action = "OTP Generated (Registration)";
                $details = "OTP generated for new user registration.";
                $audit->bind_param("isss", $new_id, $username, $action, $details);
                $audit->execute();

                header("Location: login_otp.php");
                exit;

                } else {
                    $message = "Unable to register user. Try again.";
                }
            }
        }
    }
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login</title>
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
}
.login-container {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 2rem;
    width: 340px;
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    transition: 0.3s;
}
.login-container.welcome {
    width: 420px;
}
h2 {
    font-weight: 600;
    font-size: 1.4rem;
    margin-bottom: 0.5rem;
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
}
input:focus {
    outline: none;
    border-color: var(--primary);
    background: #fff;
}
.input-success {
    border-color: var(--success);
}
.input-error {
    border-color: var(--error);
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
#password-checklist {
    padding: 0;
    margin: 0;
    display: none;
    font-size: 12px;
    text-align: left;
}
#password-checklist li {
    list-style: none;
    margin: 4px 0;
    padding-left: 20px;
    position: relative;
    color: #707070;
}
#password-checklist li::before {
    content: "✖";
    position: absolute;
    left: 0;
    color: var(--error);
    font-size: 12px;
}
#password-checklist li.valid {
    color: var(--success);
}
#password-checklist li.valid::before {
    content: "✔";
    color: var(--success);
}
#confirm-msg {
    font-size: 12px;
    text-align: left;
    margin-top: -4px;
}
.error {
    color: var(--error);
    font-size: 14px;
    padding: 10px;
    border-radius: 6px;
    background: rgba(214, 40, 40, 0.12);
    border: 1px solid rgba(214, 40, 40, 0.3);
    text-align: center;
}
</style>
</head>
<body>
 
<div class="login-container <?php echo $show_welcome ? 'welcome' : ''; ?>">
 
<?php if ($show_welcome): ?>
    <h2>Welcome</h2>
    <div><?php echo "Hello, " . htmlspecialchars($_SESSION["username"]) . "!"; ?></div>
    <div><?php echo "Your password is: " . htmlspecialchars($_SESSION["password"]); ?></div>
 
<?php else: ?>
 
<h2>Login</h2>
<?php if (!empty($message)): ?>
    <div class="error"><?php echo $message; ?></div>
<?php endif; ?>
 
<form method="POST">
    <input type="text" name="username" placeholder="Username" required />

    <input type="password" id="password" name="password" placeholder="Password" required />
 
    <ul id="password-checklist">
        <li id="check-length">At least 8 characters</li>
        <li id="check-upper">Contains uppercase</li>
        <li id="check-lower">Contains lowercase</li>
        <li id="check-number">Contains number</li>
        <li id="check-special">Contains special character</li>
    </ul>
 
    <input type="password" id="cpassword" name="cpassword" placeholder="Confirm Password" required />
    <div id="confirm-msg"></div>
 
    <button type="submit">Login</button>
</form>
 
<?php endif; ?>
</div>
 
<script>
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
        confirmMsg.textContent = "✔ Passwords match";
    } else {
        cpassword.classList.add("input-error");
        cpassword.classList.remove("input-success");
        confirmMsg.style.color = "#e63946";
        confirmMsg.textContent = "✖ Passwords do not match";
    }
});
 
function validate(condition, element) {
    element.classList.toggle("valid", condition);
}
</script>
 
</body>
</html>
