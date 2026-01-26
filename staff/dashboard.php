<?php
session_start();
include "../shared/database.php";

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php");
    exit;
}

if ($_SESSION['role'] !== 'staff_user') {
    header("Location: ../guest/shop.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = "";
$message_type = "";

// Handle product management
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_product') {
        $product_name = trim($_POST['product_name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);

        $stmt = $conn->prepare("INSERT INTO products (product_name, description, price, quantity, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdii", $product_name, $description, $price, $quantity, $user_id);        
        if ($stmt->execute()) {
            $message = "Product added successfully!";
            $message_type = "success";
            logAudit($conn, $user_id, $username, "PRODUCT_ADD", "Added product: $product_name, Price: $price, Quantity: $quantity");
        } else {
            $message = "Error adding product.";
            $message_type = "error";
        }
    }

    if ($_POST['action'] === 'edit_product') {
        $product_id = intval($_POST['product_id']);
        $product_name = trim($_POST['product_name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);

        $stmt = $conn->prepare("UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ?");
        $stmt->bind_param("ssdii", $product_name, $description, $price, $quantity, $product_id);
        
        if ($stmt->execute()) {
            $message = "Product updated successfully!";
            $message_type = "success";
            logAudit($conn, $user_id, $username, "PRODUCT_EDIT", "Edited product ID $product_id: $product_name, Price: $price, Quantity: $quantity");
        } else {
            $message = "Error updating product.";
            $message_type = "error";
        }
    }

    if ($_POST['action'] === 'delete_product') {
        $product_id = intval($_POST['product_id']);
        
        $prod_stmt = $conn->prepare("SELECT product_name FROM products WHERE id = ?");
        $prod_stmt->bind_param("i", $product_id);
        $prod_stmt->execute();
        $prod_result = $prod_stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        
        if ($stmt->execute()) {
            $message = "Product deleted successfully!";
            $message_type = "success";
            logAudit($conn, $user_id, $username, "PRODUCT_DELETE", "Deleted product: " . $prod_result['product_name']);
        } else {
            $message = "Error deleting product.";
            $message_type = "error";
        }
    }

    if ($_POST['action'] === 'approve_order') {
        $order_id = intval($_POST['order_id']);
        
        $order_stmt = $conn->prepare("SELECT user_id, total_amount FROM orders WHERE id = ?");
        $order_stmt->bind_param("i", $order_id);
        $order_stmt->execute();
        $order = $order_stmt->get_result()->fetch_assoc();
        
        $user_stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $order['user_id']);
        $user_stmt->execute();
        $customer = $user_stmt->get_result()->fetch_assoc();
        
        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $user_id, $order_id);
        
        if ($stmt->execute()) {
            // Send email to customer
            $to = $customer['email'];
            $subject = "Order #" . $order_id . " Approved";
            $email_body = "
            <html>
            <body>
            <h2>Order Approved</h2>
            <p>Dear " . htmlspecialchars($customer['username']) . ",</p>
            <p>Your order #" . $order_id . " has been approved and will be shipped soon.</p>
            <p>Order Total: \$" . number_format($order['total_amount'], 2) . "</p>
            <p>Thank you for your business!</p>
            </body>
            </html>";
            
            sendEmail($to, $subject, $email_body);
            
            $message = "Order approved and customer notified!";
            $message_type = "success";
            logAudit($conn, $user_id, $username, "ORDER_APPROVED", "Approved order ID $order_id for customer ID " . $order['user_id']);
        } else {
            $message = "Error approving order.";
            $message_type = "error";
        }
    }
}

// Get all products for staff
$products_result = $conn->query("SELECT id, product_name, description, price, quantity FROM products ORDER BY id DESC");

// Get pending orders
$orders_result = $conn->query("
    SELECT o.id, o.user_id, u.username, u.email, o.total_amount, o.order_date, o.status
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.status = 'pending'
    ORDER BY o.order_date DESC
");

$stats = [
    'total_products' => $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'],
    'pending_orders' => $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch_assoc()['count'],
];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Staff Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { color: #333; }
        .user-info { color: #666; }
        .logout-btn { background: #6c757d; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .logout-btn:hover { background: #5a6268; }
        .logout-btn svg { display: block; }
        
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .number { color: #28a745; font-size: 32px; font-weight: bold; }
        
        .section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { color: #333; margin-bottom: 15px; border-bottom: 2px solid #28a745; padding-bottom: 10px; }
        
        form { margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #333; font-weight: 500; }
        input, select, textarea { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-family: inherit; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #28a745; box-shadow: 0 0 5px rgba(40,167,69,0.25); }
        textarea { resize: vertical; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #28a745; color: white; }
        .btn-primary:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        tr:hover { background: #f5f5f5; }
        
        .action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .action-buttons button { padding: 6px 12px; font-size: 12px; }
        
        .dropdown { position: relative; display: inline-block; }
        .dropdown-btn { background: #007bff; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 18px; line-height: 1; }
        .dropdown-btn:hover { background: #0056b3; }
        .dropdown-content { display: none; position: absolute; right: 0; background-color: white; min-width: 160px; box-shadow: 0px 8px 16px rgba(0,0,0,0.2); z-index: 1; border-radius: 4px; }
        .dropdown-content.active { display: block; }
        .dropdown-content button { width: 100%; background: none; border: none; color: #333; padding: 12px 16px; text-align: left; cursor: pointer; font-size: 14px; }
        .dropdown-content button:hover { background-color: #f1f1f1; }
        .dropdown-content .btn-danger { color: #dc3545; background: white; padding: 12px 16px; }
        .dropdown-content .btn-danger:hover { background-color: #f1f1f1; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); overflow-y: auto; }
        .modal.active { display: block; }
        .modal-content { background-color: white; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Staff Dashboard</h1>
            <div class="user-info">
                Logged in as: <strong><?= htmlspecialchars($username) ?></strong> (Staff)
                <a href="../shared/logout.php" class="logout-btn" title="Logout">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <h3>Total Products</h3>
                <div class="number"><?= $stats['total_products'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Orders</h3>
                <div class="number"><?= $stats['pending_orders'] ?></div>
            </div>
        </div>

        <div class="section">
            <h2>Add New Product</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_product">
                <div class="form-group">
                    <label>Product Name:</label>
                    <input type="text" name="product_name" required>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Price:</label>
                    <input type="number" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Quantity:</label>
                    <input type="number" name="quantity" required>
                </div>
                <button type="submit" class="btn btn-primary">Add Product</button>
            </form>
        </div>

        <div class="section">
            <h2>Manage Products</h2>
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($product = $products_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                            <td><?= htmlspecialchars(substr($product['description'], 0, 50)) ?></td>
                            <td>$<?= number_format($product['price'], 2) ?></td>
                            <td><?= $product['quantity'] ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="dropdown-btn" onclick="toggleDropdown(event)">•••</button>
                                    <div class="dropdown-content">
                                        <button onclick="openEditModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['product_name']) ?>', '<?= htmlspecialchars($product['description']) ?>', <?= $product['price'] ?>, <?= $product['quantity'] ?>)">Edit</button>
                                        <button class="btn-danger" onclick="if(confirm('Are you sure?')) { deleteProduct(<?= $product['id'] ?>) }">Delete</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Pending Orders</h2>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders_result->num_rows > 0): ?>
                        <?php while ($order = $orders_result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $order['id'] ?></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['email']) ?></td>
                                <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve_order">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <button type="submit" class="btn btn-success">Approve & Ship</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #666;">No pending orders</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Product</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="form-group">
                    <label>Product Name:</label>
                    <input type="text" name="product_name" id="edit_product_name" required>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" id="edit_product_description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Price:</label>
                    <input type="number" name="price" id="edit_product_price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Quantity:</label>
                    <input type="number" name="quantity" id="edit_product_quantity" required>
                </div>
                <button type="submit" class="btn btn-primary">Update Product</button>
                <button type="button" onclick="closeEditModal()" class="btn" style="background: #6c757d; color: white;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
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

        function deleteProduct(productId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="product_id" value="${productId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function openEditModal(id, name, desc, price, qty) {
            document.getElementById('edit_product_id').value = id;
            document.getElementById('edit_product_name').value = name;
            document.getElementById('edit_product_description').value = desc;
            document.getElementById('edit_product_price').value = price;
            document.getElementById('edit_product_quantity').value = qty;
            document.getElementById('editModal').classList.add('active');
            closeAllDropdowns();
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        window.onclick = function(event) {
            let modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.classList.remove('active');
            }
        }
    </script>
</body>
</html>


