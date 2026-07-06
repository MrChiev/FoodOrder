<?php
$allowed_roles = ['customer'];
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $customer_id = $_SESSION['user_id'];
    $item_id = (int)$_POST['item_id'];
    $quantity = min(20, max(1, (int)$_POST['quantity']));

    // Confirm the item actually exists and is available
    $check = mysqli_query($conn, "SELECT item_id FROM menu_items WHERE item_id = $item_id AND is_available = 1");
    if (mysqli_num_rows($check) === 1) {
        // Upsert: if it's already in the cart, bump the quantity instead of duplicating the row (capped at 20 total)
        $sql = "INSERT INTO cart (customer_id, item_id, quantity)
                VALUES ($customer_id, $item_id, $quantity)
                ON DUPLICATE KEY UPDATE quantity = LEAST(20, quantity + $quantity)";
        mysqli_query($conn, $sql);
        $_SESSION['cart_message'] = "Item added to cart.";
    }
}

redirect('/foodorder/customer/menu.php');
?>
