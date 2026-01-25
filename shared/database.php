<?php
// Include PHPMailer mailer
require_once __DIR__ . '/mailer.php';

$host = "localhost";
$user = "root"; 
$pass = "";
$dbname = "product_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Security constants
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCKOUT_DURATION', 15 * 60); // 15 minutes in seconds
define('OTP_EXPIRY', 10 * 60); // 10 minutes in seconds
define('OTP_LENGTH', 6);

// Function to check user permissions
function hasPermission($conn, $user_id, $permission) {
    $stmt = $conn->prepare("SELECT u.role FROM users u WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $user = $result->fetch_assoc();
    $role = $user['role'];
    
    $perm_stmt = $conn->prepare("SELECT id FROM permissions WHERE role = ? AND permission = ?");
    $perm_stmt->bind_param("ss", $role, $permission);
    $perm_stmt->execute();
    $perm_result = $perm_stmt->get_result();
    
    return $perm_result->num_rows > 0;
}

// Function to get user role
function getUserRole($conn, $user_id) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        return $user['role'];
    }
    
    return null;
}

// Function to log audit trail
function logAudit($conn, $user_id, $username, $action, $details) {
    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, username, action, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $username, $action, $details);
    return $stmt->execute();
}

// Function to check if account is locked
function isAccountLocked($conn, $user_id) {
    $stmt = $conn->prepare("SELECT is_locked, locked_until FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $user = $result->fetch_assoc();
    
    if (!$user['is_locked']) {
        return false;
    }
    
    // Check if lock has expired
    if (time() > strtotime($user['locked_until'])) {
        unlockAccount($conn, $user_id);
        return false;
    }
    
    return true;
}

// Function to lock account
function lockAccount($conn, $user_id) {
    $locked_until = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
    $stmt = $conn->prepare("UPDATE users SET is_locked = TRUE, locked_until = ? WHERE id = ?");
    $stmt->bind_param("si", $locked_until, $user_id);
    return $stmt->execute();
}

// Function to unlock account
function unlockAccount($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE users SET is_locked = FALSE, locked_until = NULL, failed_login_attempts = 0 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Function to increment failed login attempts
function incrementFailedAttempts($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Check if should lock account
    $check_stmt = $conn->prepare("SELECT failed_login_attempts FROM users WHERE id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['failed_login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            lockAccount($conn, $user_id);
            return true; // Account locked
        }
    }
    
    return false; // Account not locked
}

// Function to reset failed login attempts
function resetFailedAttempts($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Function to generate OTP
function generateOTP() {
    return str_pad(mt_rand(0, pow(10, OTP_LENGTH) - 1), OTP_LENGTH, '0', STR_PAD_LEFT);
}

// sendEmail() function is now provided by mailer.php
?>