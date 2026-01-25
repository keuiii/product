<?php
session_start();
include "../shared/database.php";

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php");
    exit;
}

if ($_SESSION['role'] !== 'admin_sec') {
    header("Location: ../guest/shop.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = "";
$message_type = "";

// Handle AJAX search request
if (isset($_GET['ajax_search'])) {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $records_per_page = 10;
    $offset = ($page - 1) * $records_per_page;

    // Build query with search
    if (!empty($search)) {
        $search_param = "%$search%";
        $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE username LIKE ? OR email LIKE ? OR role LIKE ?");
        $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
        
        $users_stmt = $conn->prepare("SELECT id, username, email, role, is_active, failed_login_attempts, is_locked FROM users WHERE username LIKE ? OR email LIKE ? OR role LIKE ? ORDER BY role, username LIMIT ? OFFSET ?");
        $users_stmt->bind_param("sssii", $search_param, $search_param, $search_param, $records_per_page, $offset);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();
    } else {
        $total_records = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
        $users_stmt = $conn->prepare("SELECT id, username, email, role, is_active, failed_login_attempts, is_locked FROM users ORDER BY role, username LIMIT ? OFFSET ?");
        $users_stmt->bind_param("ii", $records_per_page, $offset);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();
    }

    $total_pages = ceil($total_records / $records_per_page);

    // Generate table rows HTML
    $rows_html = '';
    if ($users_result->num_rows > 0) {
        while ($user = $users_result->fetch_assoc()) {
            $status_html = '';
            if ($user['is_locked']) {
                $status_html = '<span style="color: red; font-weight: bold;">LOCKED</span>';
            } elseif ($user['is_active']) {
                $status_html = '<span style="color: green;">Active</span>';
            } else {
                $status_html = '<span style="color: orange;">Inactive</span>';
            }

            $delete_button = '';
            if ($user['id'] != $user_id) {
                $delete_button = '<button class="btn-danger" onclick="if(confirm(\'Are you sure?\')) { deleteUser(' . $user['id'] . ') }">Delete</button>';
            }

            $rows_html .= '<tr>
                <td>' . htmlspecialchars($user['username']) . '</td>
                <td>' . htmlspecialchars($user['email']) . '</td>
                <td>' . htmlspecialchars($user['role']) . '</td>
                <td>' . $status_html . '</td>
                <td>' . $user['failed_login_attempts'] . '/3</td>
                <td>
                    <div class="dropdown">
                        <button class="dropdown-btn" onclick="toggleDropdown(event)">•••</button>
                        <div class="dropdown-content">
                            <button onclick="openEditModal(' . $user['id'] . ', \'' . htmlspecialchars($user['email']) . '\', \'' . htmlspecialchars($user['role']) . '\')">Edit</button>
                            <button onclick="openResetModal(' . $user['id'] . ', \'' . htmlspecialchars($user['username']) . '\')">Reset Password</button>
                            ' . $delete_button . '
                        </div>
                    </div>
                </td>
            </tr>';
        }
    } else {
        $rows_html = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">No users found</td></tr>';
    }

    // Generate pagination HTML
    $pagination_html = '';
    if ($total_pages > 1) {
        $pagination_html .= '<div class="pagination">';
        
        // Previous button
        if ($page > 1) {
            $pagination_html .= '<a href="#" onclick="loadPage(' . ($page - 1) . '); return false;">Previous</a>';
        } else {
            $pagination_html .= '<span class="disabled">Previous</span>';
        }
        
        // Page numbers
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if ($start_page > 1) {
            $pagination_html .= '<a href="#" onclick="loadPage(1); return false;">1</a>';
            if ($start_page > 2) {
                $pagination_html .= '<span>...</span>';
            }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $page) {
                $pagination_html .= '<span class="active">' . $i . '</span>';
            } else {
                $pagination_html .= '<a href="#" onclick="loadPage(' . $i . '); return false;">' . $i . '</a>';
            }
        }
        
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $pagination_html .= '<span>...</span>';
            }
            $pagination_html .= '<a href="#" onclick="loadPage(' . $total_pages . '); return false;">' . $total_pages . '</a>';
        }
        
        // Next button
        if ($page < $total_pages) {
            $pagination_html .= '<a href="#" onclick="loadPage(' . ($page + 1) . '); return false;">Next</a>';
        } else {
            $pagination_html .= '<span class="disabled">Next</span>';
        }
        
        $pagination_html .= '</div>';
    }

    // Return JSON response
    echo json_encode([
        'rows' => $rows_html,
        'pagination' => $pagination_html,
        'total_records' => $total_records,
        'search_term' => $search
    ]);
    exit;
}

// Handle user creation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_user') {
        $new_username = trim($_POST['new_username']);
        $new_email = trim($_POST['new_email']);
        $new_password = trim($_POST['new_password']);
        $new_role = trim($_POST['new_role']);

        if (strlen($new_password) < 8) {
            $message = "Password must be at least 8 characters long.";
            $message_type = "error";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, TRUE)");
            $stmt->bind_param("ssss", $new_username, $new_email, $hashed_password, $new_role);
            
            if ($stmt->execute()) {
                $message = "User '" . htmlspecialchars($new_username) . "' created successfully as " . htmlspecialchars($new_role) . ".";
                $message_type = "success";
                logAudit($conn, $user_id, $username, "USER_CREATE", "Created new user: $new_username with role: $new_role");
            } else {
                $message = "Error creating user. Username may already exist.";
                $message_type = "error";
            }
        }
    }

    if ($_POST['action'] === 'reset_password') {
        $reset_user_id = intval($_POST['reset_user_id']);
        $new_password = trim($_POST['reset_password']);

        if (strlen($new_password) < 8) {
            $message = "Password must be at least 8 characters long.";
            $message_type = "error";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $reset_user_id);
            
            if ($stmt->execute()) {
                // Get user info for audit
                $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                $user_stmt->bind_param("i", $reset_user_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result()->fetch_assoc();
                
                $message = "Password reset successfully for user '" . htmlspecialchars($user_result['username']) . "'.";
                $message_type = "success";
                logAudit($conn, $user_id, $username, "PASSWORD_RESET", "Admin reset password for user: " . $user_result['username']);
            } else {
                $message = "Error resetting password.";
                $message_type = "error";
            }
        }
    }

    if ($_POST['action'] === 'delete_user') {
        $delete_user_id = intval($_POST['delete_user_id']);
        
        // Get user info before deletion
        $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $delete_user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result()->fetch_assoc();
        
        if ($delete_user_id !== $user_id) { // Prevent self-deletion
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $delete_user_id);
            
            if ($stmt->execute()) {
                $message = "User '" . htmlspecialchars($user_result['username']) . "' deleted successfully.";
                $message_type = "success";
                logAudit($conn, $user_id, $username, "USER_DELETE", "Deleted user: " . $user_result['username']);
            } else {
                $message = "Error deleting user.";
                $message_type = "error";
            }
        } else {
            $message = "You cannot delete your own account.";
            $message_type = "error";
        }
    }

    if ($_POST['action'] === 'edit_user') {
        $edit_user_id = intval($_POST['edit_user_id']);
        $edit_email = trim($_POST['edit_email']);
        $edit_role = trim($_POST['edit_role']);

        $stmt = $conn->prepare("UPDATE users SET email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssi", $edit_email, $edit_role, $edit_user_id);
        
        if ($stmt->execute()) {
            $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $edit_user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result()->fetch_assoc();
            
            $message = "User '" . htmlspecialchars($user_result['username']) . "' updated successfully.";
            $message_type = "success";
            logAudit($conn, $user_id, $username, "USER_UPDATE", "Updated user: " . $user_result['username']);
        } else {
            $message = "Error updating user.";
            $message_type = "error";
        }
    }
}

// Get dashboard statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'total_orders' => $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'],
    'pending_orders' => $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch_assoc()['count'],
    'shipped_orders' => $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'shipped'")->fetch_assoc()['count'],
];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { color: #333; }
        .user-info { color: #666; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .logout-btn:hover { background: #c82333; }
        
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .number { color: #007bff; font-size: 32px; font-weight: bold; }
        
        .section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { color: #333; margin-bottom: 15px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        
        form { margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #333; font-weight: 500; }
        input, select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: #007bff; box-shadow: 0 0 5px rgba(0,123,255,0.25); }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        
        /* Search bar styles */
        .search-bar { margin-bottom: 20px; }
        .search-bar input[type="text"] { width: 100%; max-width: 500px; padding: 10px 15px; font-size: 15px; }
        .search-info { color: #666; font-size: 14px; margin-bottom: 10px; margin-top: 10px; }
        .loading { color: #007bff; font-style: italic; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        tr:hover { background: #f5f5f5; }
        
        .form-inline { display: flex; gap: 10px; }
        .form-inline input { flex: 1; }
        
        .action-buttons { display: flex; gap: 10px; }
        .action-buttons button { padding: 6px 12px; font-size: 12px; }
        
        /* Pagination styles */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; }
        .pagination a:hover { background: #007bff; color: white; border-color: #007bff; }
        .pagination .active { background: #007bff; color: white; border-color: #007bff; }
        .pagination .disabled { color: #999; cursor: not-allowed; }
        
        .dropdown { position: relative; display: inline-block; }
        .dropdown-btn { background: #007bff; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 18px; line-height: 1; }
        .dropdown-btn:hover { background: #0056b3; }
        .dropdown-content { display: none; position: absolute; right: 0; background-color: white; min-width: 160px; box-shadow: 0px 8px 16px rgba(0,0,0,0.2); z-index: 1; border-radius: 4px; }
        .dropdown-content.active { display: block; }
        .dropdown-content button, .dropdown-content form { width: 100%; }
        .dropdown-content button { background: none; border: none; color: #333; padding: 12px 16px; text-align: left; cursor: pointer; font-size: 14px; }
        .dropdown-content form { display: contents; }
        .dropdown-content button:hover { background-color: #f1f1f1; }
        .dropdown-content .btn-danger { color: #dc3545; background: white; padding: 12px 16px; }
        .dropdown-content .btn-danger:hover { background-color: #f1f1f1; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .modal.active { display: block; }
        .modal-content { background-color: white; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <div class="user-info">
                Logged in as: <strong><?= htmlspecialchars($username) ?></strong> (Admin)
                <a href="../shared/logout.php" class="logout-btn">Logout</a>
                <a href="audit_trail.php" style="margin-left: 10px; background: #17a2b8; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">View Audit Trail</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="number"><?= $stats['total_users'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="number"><?= $stats['total_orders'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Orders</h3>
                <div class="number"><?= $stats['pending_orders'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Shipped Orders</h3>
                <div class="number"><?= $stats['shipped_orders'] ?></div>
            </div>
        </div>

        <div class="section">
            <h2>Create New User</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="new_username" required>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="new_email" required>
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Role:</label>
                    <select name="new_role" required>
                        <option value="staff_user">Staff User</option>
                        <option value="regular_user">Regular User</option>
                        <option value="guest_user">Guest User</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>

        <div class="section">
            <h2>Manage Users</h2>
            
            <!-- Live Search Bar -->
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="🔍 Type to search by username, email, or role..." autocomplete="off">
            </div>
            
            <div id="searchInfo" class="search-info"></div>
            
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Failed Attempts</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <!-- Content will be loaded via AJAX -->
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div id="paginationContainer"></div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit User</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="edit_user_id" id="edit_user_id">
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="edit_email" id="edit_email" required>
                </div>
                <div class="form-group">
                    <label>Role:</label>
                    <select name="edit_role" id="edit_role" required>
                        <option value="staff_user">Staff User</option>
                        <option value="regular_user">Regular User</option>
                        <option value="guest_user">Guest User</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update User</button>
                <button type="button" onclick="closeEditModal()" class="btn" style="background: #6c757d; color: white;">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeResetModal()">&times;</span>
            <h2>Reset Password for <span id="reset_username"></span></h2>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="reset_user_id" id="reset_user_id">
                <div class="form-group">
                    <label>New Password:</label>
                    <input type="password" name="reset_password" required>
                </div>
                <button type="submit" class="btn btn-danger">Reset Password</button>
                <button type="button" onclick="closeResetModal()" class="btn" style="background: #6c757d; color: white;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let searchTimeout;
        const currentUserId = <?= $user_id ?>;

        // Load users data
        function loadUsers(page = 1) {
            currentPage = page;
            const searchTerm = document.getElementById('searchInput').value;
            const searchInfo = document.getElementById('searchInfo');
            
            // Show loading state
            searchInfo.innerHTML = '<span class="loading">Loading...</span>';
            
            // Make AJAX request
            fetch(`?ajax_search=1&search=${encodeURIComponent(searchTerm)}&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('userTableBody').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    
                    // Update search info
                    if (data.search_term) {
                        searchInfo.innerHTML = `Showing results for "<strong>${escapeHtml(data.search_term)}</strong>" - ${data.total_records} user(s) found`;
                    } else {
                        searchInfo.innerHTML = '';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    searchInfo.innerHTML = '<span style="color: red;">Error loading users</span>';
                });
        }

        // Load page function for pagination
        function loadPage(page) {
            loadUsers(page);
        }

        // Search input event listener with debounce
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadUsers(1); // Reset to page 1 when searching
            }, 300); // Wait 300ms after user stops typing
        });

        // Initial load
        loadUsers(1);

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdown = event.target.parentElement;
            const content = dropdown.querySelector('.dropdown-content');
            content.classList.toggle('active');
        }

        function closeAllDropdowns() {
            const dropdowns = document.querySelectorAll('.dropdown-content');
            dropdowns.forEach(dropdown => dropdown.classList.remove('active'));
        }

        document.addEventListener('click', function() {
            closeAllDropdowns();
        });

        function deleteUser(userId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="delete_user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function openEditModal(userId, email, role) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('editModal').classList.add('active');
            closeAllDropdowns();
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function openResetModal(userId, username) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username').textContent = username;
            document.getElementById('resetModal').classList.add('active');
            closeAllDropdowns();
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('active');
        }

        window.onclick = function(event) {
            let editModal = document.getElementById('editModal');
            let resetModal = document.getElementById('resetModal');
            if (event.target === editModal) {
                editModal.classList.remove('active');
            }
            if (event.target === resetModal) {
                resetModal.classList.remove('active');
            }
        }
    </script>
</body>
</html>