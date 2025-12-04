<?php
session_start();
include "database.php";

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, username, action, details) VALUES (?, ?, ?, ?)");
    $action = "Logout";
    $details = "User logged out.";
    $stmt->bind_param("isss", $user_id, $username, $action, $details);
    $stmt->execute();
}

session_unset();
session_destroy();
header("Location: index.php");
exit;
?>
