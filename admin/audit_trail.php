<?php
session_start();
include "../shared/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php");
    exit;
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin_sec') {
    header("Location: ../guest/shop.php");
    exit;
}

$username = $_SESSION['username'];

// Pagination setup
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

// Get total records
$total_result = $conn->query("SELECT COUNT(*) as total FROM audit_trail");
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $items_per_page);

// Ensure page is within range
$page = min($page, max(1, $total_pages));

// Calculate offset
$offset = ($page - 1) * $items_per_page;

// Get paginated records
$query = "SELECT * FROM audit_trail ORDER BY datetime DESC LIMIT $items_per_page OFFSET $offset";
$res = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - E-Shop</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap');

        :root {
            --bg: #f5f6fa;
            --card-bg: #ffffff;
            --text: #2b2b2b;
            --primary: #2d6cdf;
            --primary-hover: #1f54b8;
            --border: #dadada;
            --success: #2a9d8f;
            --warning: #f77f00;
            --danger: #d62828;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .header h1 {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .header-nav {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #b91f1f;
        }

        .user-info {
            text-align: right;
            font-size: 14px;
            color: #666;
        }

        .table-container {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
            border-bottom: 2px solid var(--border);
        }

        th {
            padding: 14px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .action-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
        }

        .action-login { background: #d1e7f7; color: #0c5aa0; }
        .action-logout { background: #f0f0f0; color: #333; }
        .action-create { background: #d4edda; color: #155724; }
        .action-update { background: #fff3cd; color: #856404; }
        .action-delete { background: #f8d7da; color: #721c24; }
        .action-failed { background: #f5c6cb; color: #721c24; }

        .datetime {
            font-size: 13px;
            color: #666;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 2rem;
            padding: 0;
            list-style: none;
        }

        .pagination li {
            display: inline;
        }

        .pagination a {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            text-decoration: none;
            color: var(--primary);
            transition: 0.2s;
            font-size: 14px;
        }

        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .active a {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .disabled a {
            color: #999;
            cursor: not-allowed;
            border-color: #ddd;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary);
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
            }

            .user-info {
                text-align: left;
            }

            th, td {
                padding: 10px;
                font-size: 12px;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📋 Audit Trail</h1>
            </div>
            <div class="header-nav">
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="../shared/logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>

        <div class="user-info">
            Logged in as: <strong><?= htmlspecialchars($username) ?></strong> (Admin)
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $total_records ?></div>
                <div class="stat-label">Total Activities</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_pages ?></div>
                <div class="stat-label">Pages</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $page ?> / <?= max(1, $total_pages) ?></div>
                <div class="stat-label">Current Page</div>
            </div>
        </div>

        <div class="table-container">
            <?php if ($res->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 20%;">User</th>
                            <th style="width: 15%;">Action</th>
                            <th style="width: 40%;">Details</th>
                            <th style="width: 25%;">Date/Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $res->fetch_assoc()): 
                            $action = strtoupper($row['action']);
                            $action_class = 'action-' . strtolower(str_replace(' ', '-', $action));
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                                <td>
                                    <span class="action-badge <?= $action_class ?>">
                                        <?= htmlspecialchars($action) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['details']) ?></td>
                                <td class="datetime"><?= date('M d, Y H:i:s', strtotime($row['datetime'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>No audit trail records found</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li><a href="?page=1">First</a></li>
                    <li><a href="?page=<?= $page - 1 ?>">← Previous</a></li>
                <?php else: ?>
                    <li class="disabled"><a>First</a></li>
                    <li class="disabled"><a>← Previous</a></li>
                <?php endif; ?>

                <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <li><a href="?page=1">1</a></li>
                        <?php if ($start_page > 2): ?>
                            <li class="disabled"><a>...</a></li>
                        <?php endif; ?>
                    <?php endif;

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="<?= $i === $page ? 'active' : '' ?>">
                            <a href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor;

                    if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="disabled"><a>...</a></li>
                        <?php endif; ?>
                        <li><a href="?page=<?= $total_pages ?>"><?= $total_pages ?></a></li>
                    <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <li><a href="?page=<?= $page + 1 ?>">Next →</a></li>
                    <li><a href="?page=<?= $total_pages ?>">Last</a></li>
                <?php else: ?>
                    <li class="disabled"><a>Next →</a></li>
                    <li class="disabled"><a>Last</a></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>


