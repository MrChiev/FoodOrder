<?php
// Database connection - raw mysqli, no frameworks

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "food_ordering_system";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Sorry, the site is temporarily unavailable. Please try again shortly.");
}
?>
