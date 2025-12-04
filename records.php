<?php
session_start();
include "database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

function save_audit($conn, $user_id, $username, $action, $details) {
    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, username, action, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $username, $action, $details);
    $stmt->execute();
}

if (isset($_POST['add'])) {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);

    $stmt = $conn->prepare("INSERT INTO records (first_name, last_name) VALUES (?, ?)");
    $stmt->bind_param("ss", $fname, $lname);
    if ($stmt->execute()) {
        $rid = $stmt->insert_id;
        save_audit($conn, $user_id, $username, "ADD", "Added record ID $rid with last name $lname");
        header("Location: records.php");
        exit;
    }
}

if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);

    $oldq = $conn->prepare("SELECT last_name FROM records WHERE id = ?");
    $oldq->bind_param("i", $id);
    $oldq->execute();
    $oldres = $oldq->get_result()->fetch_assoc();
    $old_last = $oldres ? $oldres['last_name'] : '';

    $stmt = $conn->prepare("UPDATE records SET first_name = ?, last_name = ? WHERE id = ?");
    $stmt->bind_param("ssi", $fname, $lname, $id);
    if ($stmt->execute()) {
        save_audit($conn, $user_id, $username, "UPDATE", "Edited record ID $id, updated last name from $old_last to $lname");
        header("Location: records.php");
        exit;
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $old = $conn->prepare("SELECT last_name FROM records WHERE id = ?");
    $old->bind_param("i", $id);
    $old->execute();
    $old_row = $old->get_result()->fetch_assoc();
    $old_last = $old_row ? $old_row['last_name'] : '';

    $del = $conn->prepare("DELETE FROM records WHERE id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        save_audit($conn, $user_id, $username, "DELETE", "Deleted record ID $id with last name $old_last");
        header("Location: records.php");
        exit;
    }
}

$edit_record = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $q = $conn->prepare("SELECT * FROM records WHERE id = ?");
    $q->bind_param("i", $eid);
    $q->execute();
    $edit_record = $q->get_result()->fetch_assoc();
}

$all = $conn->query("SELECT * FROM records ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Records - CRUD</title>
    <style>
        body{ font-family: Arial; padding: 20px; }
        table{ border-collapse: collapse; width: 100%; }
        th,td{ border:1px solid #ccc; padding:8px; text-align:left; }
        form{ margin-bottom: 20px; }
    </style>
</head>
<body>
    <h2>RECORDS</h2>
    <p>Logged in as: <strong><?=htmlspecialchars($username)?></strong> — <a href="logout.php">Logout</a> | <a href="audit_trail.php">View Audit Trail</a></p>

    <form method="POST">
        <input type="hidden" name="id" value="<?= $edit_record ? $edit_record['id'] : '' ?>">
        <input type="text" name="fname" placeholder="First name" value="<?= $edit_record ? htmlspecialchars($edit_record['first_name']) : '' ?>" required>
        <input type="text" name="lname" placeholder="Last name" value="<?= $edit_record ? htmlspecialchars($edit_record['last_name']) : '' ?>" required>

        <?php if ($edit_record): ?>
            <button type="submit" name="update">Update</button>
            <a href="records.php">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add">Add</button>
        <?php endif; ?>
    </form>

    <table>
        <thead>
            <tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php while ($r = $all->fetch_assoc()): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['first_name']) ?></td>
                    <td><?= htmlspecialchars($r['last_name']) ?></td>
                    <td>
                        <a href="otp_verify.php?action=edit&id=<?= $r['id'] ?>">Edit</a> |
                        <a href="otp_verify.php?action=delete&id=<?= $r['id'] ?>">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
