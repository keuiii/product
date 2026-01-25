<?php
session_start();
include "../shared/database.php";

// Verify user is logged in and is regular_user
if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php");
    exit;
}

if ($_SESSION['role'] !== 'regular_user') {
    header("Location: shop.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = "";
$message_type = "";

// Handle cart operations
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_quantity') {
        $cart_id = intval($_POST['cart_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($quantity > 0) {
            // Check product stock
            $stock_stmt = $conn->prepare("SELECT p.quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ?");
            $stock_stmt->bind_param("i", $cart_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result()->fetch_assoc();
            
            if ($stock_result && $stock_result['quantity'] >= $quantity) {
                $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                $update_stmt->bind_param("iii", $quantity, $cart_id, $user_id);
                $update_stmt->execute();
                $message = "Cart updated!";
                $message_type = "success";
            } else {
                $message = "Not enough stock available.";
                $message_type = "error";
            }
        } else {
            // Delete if quantity is 0
            $delete_stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $delete_stmt->bind_param("ii", $cart_id, $user_id);
            $delete_stmt->execute();
            $message = "Item removed from cart!";
            $message_type = "success";
        }
    }
    
    if ($_POST['action'] === 'remove_item') {
        $cart_id = intval($_POST['cart_id']);
        $delete_stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $cart_id, $user_id);
        $delete_stmt->execute();
        $message = "Item removed from cart!";
        $message_type = "success";
    }
    
    if ($_POST['action'] === 'checkout') {
        // Get cart items
        $cart_stmt = $conn->prepare("
            SELECT c.id, c.quantity, p.id as product_id, p.price 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_items = $cart_stmt->get_result();
        
        if ($cart_items->num_rows === 0) {
            $message = "Cart is empty!";
            $message_type = "error";
        } else {
            // Calculate total and verify stock
            $total_amount = 0;
            $items_to_order = [];
            $has_stock_issue = false;
            
            while ($item = $cart_items->fetch_assoc()) {
                // Check stock again
                $stock_check = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
                $stock_check->bind_param("i", $item['product_id']);
                $stock_check->execute();
                $stock_res = $stock_check->get_result()->fetch_assoc();
                
                if (!$stock_res || $stock_res['quantity'] < $item['quantity']) {
                    $has_stock_issue = true;
                    break;
                }
                
                $items_to_order[] = $item;
                $total_amount += $item['price'] * $item['quantity'];
            }
            
            if ($has_stock_issue) {
                $message = "Some items are no longer in stock. Please update your cart.";
                $message_type = "error";
            } else {
                // Create order
                $order_stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'pending')");
                $order_stmt->bind_param("id", $user_id, $total_amount);
                
                if ($order_stmt->execute()) {
                    $order_id = $order_stmt->insert_id;
                    
                    // Add order items and update stock
                    $success = true;
                    foreach ($items_to_order as $item) {
                        $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                        
                        if (!$item_stmt->execute()) {
                            $success = false;
                            break;
                        }
                        
                        // Update product quantity
                        $update_stock = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                        $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
                        $update_stock->execute();
                    }
                    
                    if ($success) {
                        // Clear cart
                        $clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                        $clear_cart->bind_param("i", $user_id);
                        $clear_cart->execute();
                        
                        logAudit($conn, $user_id, $username, "CHECKOUT", "Completed checkout for order ID $order_id, Total: \$$total_amount");
                        
                        $message = "Order placed successfully! Order ID: " . $order_id . ". Staff will review and ship your order soon.";
                        $message_type = "success";
                    } else {
                        $message = "Error creating order. Please try again.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Error creating order. Please try again.";
                    $message_type = "error";
                }
            }
        }
    }
}

// Get cart items
$cart_result = $conn->prepare("
    SELECT c.id, c.quantity, p.id as product_id, p.product_name, p.price 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
    ORDER BY c.added_at DESC
");
$cart_result->bind_param("i", $user_id);
$cart_result->execute();
$cart_items = $cart_result->get_result();

// Calculate total
$total_amount = 0;
$cart_data = [];
while ($item = $cart_items->fetch_assoc()) {
    $cart_data[] = $item;
    $total_amount += $item['price'] * $item['quantity'];
}
$cart_items->data_seek(0); // Reset result pointer
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shopping Cart</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        header { background: white; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 24px; font-weight: bold; color: #333; }
        
        .header-links { display: flex; gap: 15px; }
        .header-links a { text-decoration: none; color: #007bff; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .logout-btn:hover { background: #c82333; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .page-title { color: #333; margin-bottom: 30px; }
        
        .cart-layout { display: grid; grid-template-columns: 1fr 350px; gap: 20px; }
        
        .cart-items { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        .empty-cart { text-align: center; padding: 40px 20px; color: #666; }
        .empty-cart p { margin-bottom: 15px; }
        .btn-shop { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .btn-shop:hover { background: #0056b3; }
        
        .cart-item { border-bottom: 1px solid #eee; padding: 15px 0; display: flex; gap: 15px; align-items: flex-start; }
        .cart-item:last-child { border-bottom: none; }
        
        .item-image { 
            width: 80px; 
            height: 80px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }
        
        .item-details { flex-grow: 1; }
        .item-name { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .item-price { color: #28a745; font-weight: 600; margin-bottom: 10px; }
        
        .quantity-control { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        .quantity-control input { width: 60px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; }
        
        .item-actions { display: flex; gap: 10px; }
        .btn-small { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        
        .cart-summary { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); height: fit-content; position: sticky; top: 20px; }
        
        .summary-item { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .summary-label { color: #666; }
        .summary-value { font-weight: 600; color: #333; }
        
        .total-row { border-top: 2px solid #ddd; padding-top: 15px; display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; color: #333; margin-bottom: 20px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; width: 100%; }
        .btn-checkout { background: #28a745; color: white; font-weight: 600; }
        .btn-checkout:hover { background: #218838; }
        .btn-checkout:disabled { background: #ccc; cursor: not-allowed; }
        
        @media (max-width: 768px) {
            .cart-layout { grid-template-columns: 1fr; }
            .cart-summary { position: static; }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">🛒 E-Shop</div>
            <div class="header-links">
                <a href="shop.php">Continue Shopping</a>
                <a href="../shared/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">Shopping Cart</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="cart-layout">
            <div class="cart-items">
                <?php if (empty($cart_data)): ?>
                    <div class="empty-cart">
                        <p>Your cart is empty</p>
                        <a href="shop.php" class="btn-shop">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($cart_data as $item): ?>
                        <div class="cart-item">
                            <div class="item-image"></div>
                            <div class="item-details">
                                <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div class="item-price">₱<?= number_format($item['price'], 2) ?></div>
                                
                                <form method="POST" class="quantity-control">
                                    <input type="hidden" name="action" value="update_quantity">
                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                    <label>Qty:</label>
                                    <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="0" required>
                                    <button type="submit" class="btn-small btn-primary">Update</button>
                                </form>
                                
                                <div class="item-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="remove_item">
                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn-small btn-danger">Remove</button>
                                    </form>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 18px; font-weight: bold; color: #333;">
                                    ₱<?= number_format($item['price'] * $item['quantity'], 2) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="cart-summary">
                <h3 style="margin-bottom: 20px; color: #333;">Order Summary</h3>
                
                <div class="summary-item">
                    <span class="summary-label">Subtotal:</span>
                    <span class="summary-value">₱<?= number_format($total_amount, 2) ?></span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Shipping:</span>
                    <span class="summary-value">Free</span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Tax:</span>
                    <span class="summary-value">₱0.00</span>
                </div>
                
                <div class="total-row">
                    <span>Total:</span>
                    <span>₱<?= number_format($total_amount, 2) ?></span>
                </div>

                <?php if (!empty($cart_data)): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="checkout">
                        <button type="submit" class="btn btn-checkout">Proceed to Checkout</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>


