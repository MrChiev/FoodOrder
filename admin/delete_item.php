<?php
$allowed_roles = ['admin'];
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $item_id = (int)$_POST['item_id'];

    $item = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM menu_items WHERE item_id = $item_id"));

    if ($item) {
        // order_items.item_id has ON DELETE SET NULL, so past orders keep their item_name snapshot
        mysqli_query($conn, "DELETE FROM menu_items WHERE item_id = $item_id");

        if ($item['image'] && file_exists(__DIR__ . '/../assets/uploads/' . $item['image'])) {
            unlink(__DIR__ . '/../assets/uploads/' . $item['image']);
        }
        $_SESSION['menu_message'] = "Item deleted.";
    }
}

redirect('/foodorder/admin/manage_menu.php');
?>
