<?php
$allowed_roles = ['staff', 'admin'];
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $order_id = (int)$_POST['order_id'];
    $new_status = clean($conn, $_POST['new_status']);
    $valid_statuses = ['pending', 'preparing', 'ready', 'delivered', 'cancelled'];
    $staff_id = $_SESSION['user_id'];

    if (in_array($new_status, $valid_statuses)) {
        $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM orders WHERE order_id = $order_id"));

        if ($current) {
            mysqli_query($conn, "UPDATE orders SET status = '$new_status' WHERE order_id = $order_id");
            $old_status = $current['status'];
            mysqli_query($conn, "INSERT INTO order_status_log (order_id, old_status, new_status, changed_by)
                                  VALUES ($order_id, '$old_status', '$new_status', $staff_id)");
        }
    }
}

redirect('/foodorder/staff/order_queue.php');
?>
