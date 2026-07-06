<?php
require_once __DIR__ . '/../includes/functions.php';

// Logout now requires a POST with a valid CSRF token, so a third-party page
// can't silently force a visitor's session to end just by loading an <img>
// or link pointed at this URL.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/foodorder/index.php');
}
verifyCsrfToken();

$_SESSION = [];
session_destroy();

redirect('/foodorder/auth/login.php');
?>
