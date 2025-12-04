<?php
session_start();
include "database.php";

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$input_otp = $_POST['otp'];
$record_id = intval($_POST['id']);
$action = $_POST['action'];

if (!isset($_SESSION['otp']) || $input_otp != $_SESSION['otp']) {

    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, username, action, details) VALUES (?, ?, ?, ?)");
    $action_text = "OTP Failed";
    $details = "Failed OTP for $action record ID $record_id";
    $stmt->bind_param("isss", $user_id, $username, $action_text, $details);
    $stmt->execute();

    unset($_SESSION['otp']);
    header("Location: records.php?error=Invalid OTP");
    exit;
}

$stmt = $conn->prepare("INSERT INTO audit_trail (user_id, username, action, details) VALUES (?, ?, ?, ?)");
$action_text = "OTP Verified";
$details = "Correct OTP for $action record ID $record_id";
$stmt->bind_param("isss", $user_id, $username, $action_text, $details);
$stmt->execute();

unset($_SESSION['otp']);

if ($action === "edit") {
    header("Location: records.php?edit=" . $record_id);
    exit;
}
if ($action === "delete") {
    header("Location: records.php?delete=" . $record_id);
    exit;
}

header("Location: records.php");
exit;
?>
