<?php
session_start();
include "database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['action']) || !isset($_GET['id'])) {
    header("Location: records.php");
    exit;
}

$record_id = intval($_GET['id']);
$action = $_GET['action'];

if (!isset($_SESSION['otp'])) {
    $_SESSION['otp'] = rand(100000, 999999);
    $_SESSION['otp_action'] = $action;
    $_SESSION['otp_record'] = $record_id;

    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, username, action, details) VALUES (?, ?, ?, ?)");
    $action_text = "OTP Generated";
    $details = "OTP generated for $action record ID $record_id";
    $stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $action_text, $details);
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>OTP Verification</title>
<style>
body { font-family: Arial; padding: 20px; }
input { padding: 10px; width: 200px; }
button { padding: 10px 15px; }
</style>
</head>
<body>

<h2>OTP Verification</h2>
<p>Enter the 6-digit OTP to continue.</p>

<p><strong>OTP:</strong> <?= $_SESSION['otp']; ?></p>

<form method="POST" action="otp_verify_process.php">
    <input type="hidden" name="id" value="<?= $record_id ?>">
    <input type="hidden" name="action" value="<?= $action ?>">
    <input type="number" name="otp" placeholder="Enter OTP" required>

    <button type="submit">Verify OTP</button>
    <a href="records.php">Cancel</a>
</form>

</body>
</html>
