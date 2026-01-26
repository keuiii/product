<?php
session_start();
include "../shared/database.php";

$message = "";
$message_type = "";
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $is_logged_in ? $_SESSION['role'] : 'guest_user';

// Handle add to cart
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    if (!$is_logged_in) {
        $message = "Please log in to add items to cart.";
        $message_type = "error";
    } else if ($user_role === 'guest_user') {
        $message = "Guest users cannot add items to cart. Please register to continue.";
        $message_type = "error";
    } else if ($user_role === 'regular_user') {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($quantity > 0) {
            // Check if product exists
            $check_stmt = $conn->prepare("SELECT id, quantity FROM products WHERE id = ?");
            $check_stmt->bind_param("i", $product_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $product = $check_result->fetch_assoc();
                
                if ($product['quantity'] < $quantity) {
                    $message = "Not enough stock available. Only " . $product['quantity'] . " items in stock.";
                    $message_type = "error";
                } else {
                    // Add to cart or update if exists
                    $user_id = $_SESSION['user_id'];
                    
                    // Check if product already in cart
                    $existing = $conn->prepare("SELECT id FROM cart WHERE user_id = ? AND product_id = ?");
                    $existing->bind_param("ii", $user_id, $product_id);
                    $existing->execute();
                    $existing_result = $existing->get_result();
                    
                    if ($existing_result->num_rows > 0) {
                        // Update quantity
                        $update_stmt = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
                        $update_stmt->bind_param("iii", $quantity, $user_id, $product_id);
                        $update_stmt->execute();
                        $message = "Product quantity updated in cart!";
                    } else {
                        // Add new item
                        $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                        $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
                        $insert_stmt->execute();
                        $message = "Product added to cart!";
                    }
                    $message_type = "success";
                    logAudit($conn, $user_id, $_SESSION['username'], "ADD_TO_CART", "Added product ID $product_id (qty: $quantity) to cart");
                }
            } else {
                $message = "Product not found.";
                $message_type = "error";
            }
        } else {
            $message = "Invalid quantity.";
            $message_type = "error";
        }
    }
}

// Get all products
$products_result = $conn->query("SELECT id, product_name, description, price, quantity FROM products ORDER BY id DESC");

// Get cart count for logged in users
$cart_count = 0;
if ($is_logged_in && $user_role === 'regular_user') {
    $cart_stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
    $cart_stmt->bind_param("i", $_SESSION['user_id']);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result()->fetch_assoc();
    $cart_count = $cart_result['count'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shop - E-Commerce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        header { background: white; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 24px; font-weight: bold; color: #333; }
        
        .header-links { display: flex; gap: 15px; align-items: center; }
        .header-links a { text-decoration: none; color: #007bff; }
        .header-links a:hover { color: #0056b3; }
        
        .cart-link { 
        display: flex; 
        align-items: center; 
        gap: 6px;
        background: #28a745;
        color: #ffffff !important;
        padding: 8px 15px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        }

        .cart-badge { 
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .cart-link:hover { background: #218838; }

        .logout-btn { 
            background: #6c757d; 
            color: #ffffff !important; 
            padding: 8px 12px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .logout-btn:hover { background: #5a6268; }
        .logout-btn svg { display: block; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .page-title { color: #333; margin-bottom: 30px; text-align: center; }
        
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        
        .product-card { 
            background: white; 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover { 
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .product-image { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
        }
        
        .product-info { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        .product-name { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .product-desc { font-size: 13px; color: #666; margin-bottom: 10px; }
        
        .product-price { font-size: 20px; font-weight: bold; color: #28a745; margin-bottom: 10px; }
        
        .product-stock { font-size: 12px; color: #999; margin-bottom: 10px; }
        .stock-available { color: #28a745; }
        .stock-low { color: #ffc107; }
        .stock-out { color: #dc3545; }
        
        .product-actions { display: flex; gap: 10px; margin-top: auto; }
        .quantity-input { width: 60px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; }
        
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-disabled { background: #ccc; color: #666; cursor: not-allowed; }
        
        .guest-notice { 
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .auth-message {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        .auth-message a { color: #0056b3; text-decoration: none; font-weight: 600; }
        .auth-message a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">🛒 E-Shop</div>
            <div class="header-links">
                <?php if ($is_logged_in): ?>
                    <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($user_role) ?>)</span>
                    <?php if ($user_role === 'regular_user'): ?>
                        <a href="cart.php" class="cart-link">
                            🛒 Cart
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-badge"><?= $cart_count ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <a href="../shared/logout.php" class="logout-btn" title="Logout">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </a>
                <?php else: ?>
                    <a href="index.php">Register</a>
                    <a href="login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (!$is_logged_in): ?>
            <div class="auth-message">
                <p>Please <a href="login.php">login</a> or <a href="index.php">register</a> to add items to cart and checkout.</p>
            </div>
        <?php elseif ($user_role === 'guest_user'): ?>
            <div class="guest-notice">
                <strong>⚠️ Guest Access:</strong> As a guest user, you can only view products. To add items to cart and make purchases, please <a href="index.php">register here</a>.
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <h1 class="page-title">Available Products</h1>

        <div class="products-grid">
            <?php 
            if ($products_result->num_rows > 0):
                while ($product = $products_result->fetch_assoc()): 
            ?>
                <div class="product-card">
                    <div class="product-image">Product Image</div>
                    <div class="product-info">
                        <div class="product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                        <div class="product-desc"><?= htmlspecialchars(substr($product['description'], 0, 80)) ?></div>
                        <div class="product-price">₱<?= number_format($product['price'], 2) ?></div>
                        <div class="product-stock">
                            <?php if ($product['quantity'] > 0): ?>
                                <span class="stock-available">✓ In Stock (<?= $product['quantity'] ?> items)</span>
                            <?php else: ?>
                                <span class="stock-out">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($product['quantity'] > 0 && $is_logged_in && $user_role === 'regular_user'): ?>
                            <form method="POST" class="product-actions">
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <input type="number" name="quantity" class="quantity-input" value="1" min="1" max="<?= $product['quantity'] ?>" required>
                                <button type="submit" class="btn btn-success">Add to Cart</button>
                            </form>
                        <?php elseif ($product['quantity'] > 0 && (!$is_logged_in || $user_role === 'guest_user')): ?>
                            <div class="product-actions">
                                <button class="btn btn-disabled" disabled>Add to Cart</button>
                            </div>
                        <?php else: ?>
                            <div class="product-actions">
                                <button class="btn btn-disabled" disabled>Out of Stock</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php 
                endwhile;
            else: 
            ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #666;">
                    <p>No products available yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


