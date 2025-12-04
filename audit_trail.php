<?php
session_start();
include "database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$res = $conn->query("SELECT * FROM audit_trail ORDER BY datetime DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Audit Trail</title>
    <style>
        body{ font-family: Arial; padding: 20px; }
        table{ border-collapse: collapse; width: 100%; }
        th,td{ border:1px solid #ccc; padding:8px; text-align:left; }
    </style>
</head>
<body>
    <h2>Audit Trail</h2>
    <p><a href="records.php">Back to Records</a> | <a href="logout.php">Logout</a></p>
    <table>
        <thead>
            <tr><th>User</th><th>Action</th><th>Details</th><th>Date/Time</th></tr>
        </thead>
        <tbody>
            <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['action']) ?></td>
                    <td><?= htmlspecialchars($row['details']) ?></td>
                    <td><?= $row['datetime'] ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
