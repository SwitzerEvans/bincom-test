
<?php
$conn = new mysqli("localhost", "root", "", "bincomphptest");

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");
?>
