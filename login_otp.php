<?php
session_start();
include "database.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['otp'])) {
    header("Location: index.php");
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_otp = trim($_POST['otp']);

    if (time() > $_SESSION['otp_expires']) {
        $message = "OTP has expired. Please register/login again.";
        session_unset();
        session_destroy();
    } elseif ($input_otp == $_SESSION['otp']) {

        unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['otp_for']);

        $audit = $conn->prepare("INSERT INTO audit_trail (user_id, username, action, details) VALUES (?, ?, ?, ?)");
        $action = "OTP Verified";
        $details = "OTP verified successfully.";
        $audit->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $action, $details);
        $audit->execute();

        header("Location: records.php");
        exit;
    } else {
        $message = "Invalid OTP. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OTP Verification</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap');

body {
    margin: 0;
    padding: 0;
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f5f6fa;
    font-family: 'Inter', sans-serif;
    color: #2b2b2b;
}

.otp-container {
    background: #fff;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    width: 350px;
    text-align: center;
}

h2 {
    margin-bottom: 1rem;
    font-weight: 600;
}

form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1rem;
}

input[type="number"] {
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #dadada;
    font-size: 14px;
}

button {
    padding: 12px;
    background: #2d6cdf;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    font-weight: 500;
    transition: 0.2s;
}

button:hover {
    background: #1f54b8;
}

.error {
    color: #d62828;
    font-size: 14px;
    padding: 10px;
    border-radius: 6px;
    background: rgba(214, 40, 40, 0.12);
    border: 1px solid rgba(214, 40, 40, 0.3);
    margin-bottom: 1rem;
}

.note {
    font-size: 12px;
    color: #707070;
    margin-top: 1rem;
}
</style>
</head>
<body>

<div class="otp-container">
    <h2>OTP Verification</h2>
    <p>Enter the OTP sent for verification.</p>
    <p><strong>OTP:</strong> <?= $_SESSION['otp']; ?></p>

    <?php if (!empty($message)): ?>
        <div class="error"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="number" name="otp" placeholder="Enter OTP" required>
        <button type="submit">Verify</button>
    </form>

    <p class="note"><strong>Note:</strong> OTP is valid for 3 minutes.</p>
</div>

</body>
</html>
