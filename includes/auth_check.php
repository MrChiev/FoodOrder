<?php
// Include this file at the very top of any protected page.
// Usage: set $allowed_roles = ['admin']; before including this file.
// If $allowed_roles is not set, this file only checks that the user is logged in.

require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    redirect('/foodorder/auth/login.php');
}

if (isset($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
    // Logged in, but wrong role -> send them back to their own home page
    switch ($_SESSION['role']) {
        case 'admin':
            redirect('/foodorder/admin/dashboard.php');
            break;
        case 'staff':
            redirect('/foodorder/staff/order_queue.php');
            break;
        default:
            redirect('/foodorder/customer/menu.php');
    }
}
?>
