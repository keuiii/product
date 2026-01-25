<?php
session_start();
include "database.php";

// Log the logout action if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Unknown';

    // Insert logout action into audit trail
    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, username, action, details) VALUES (?, ?, ?, ?)");
    $action = "LOGOUT";
    $details = "User (" . $role . ") logged out successfully.";
    $stmt->bind_param("isss", $user_id, $username, $action, $details);
    $stmt->execute();
    $stmt->close();
}

// Destroy the session completely
session_unset();
session_destroy();

// Clear the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: login.php");
exit;
?>


