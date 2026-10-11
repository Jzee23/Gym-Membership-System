<?php
$conn = mysqli_connect("localhost", "root", "", "system_gym", 3306);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>
