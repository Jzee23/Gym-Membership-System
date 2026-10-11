<?php
session_start();
include "db.php";

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {
    die("Please enter username and password. <br><br><a href='index.php'>Back</a>");
}

$stmt = mysqli_prepare($conn, "SELECT * FROM admins WHERE username = ?");
if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 1) {
    $admins = mysqli_fetch_assoc($result);

    if (password_verify($password, $admins["password_hash"])) {
        session_regenerate_id(true);
        $_SESSION["admin_id"] = $admins["admin_id"];
        $_SESSION["admin_username"] = $admins["username"];
        $_SESSION["admin_name"] = $admins["full_name"];

        header("Location: dashboard.php");
        exit();
    }

    $_SESSION["login_error"] = "Incorrect password. Please try again.";
    $_SESSION["login_username"] = $username;
    header("Location: index.php");
    exit();
}

$_SESSION["login_error"] = "Admin account not found.";
header("Location: index.php");
exit();
?>
