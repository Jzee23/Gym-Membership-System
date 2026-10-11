<?php
session_start();

if (isset($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>AnyFitness Admin Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="logo">ANY<span>FITNESS</span></div>
            <p class="label">ADMINISTRATOR</p>
            <h1>Welcome Back</h1>
            <p class="description">Sign in to manage your gym.</p>

            <form action="login-process.php" method="POST">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter username" value="<?= htmlspecialchars($_SESSION['login_username'] ?? "", ENT_QUOTES, 'UTF-8') ?>" required>

                <label>Password</label>
                <input type="password" name="password" placeholder="Enter password" required>
                <?php if (!empty($_SESSION["login_error"])): ?><p class="field-error"><?= htmlspecialchars($_SESSION["login_error"], ENT_QUOTES, "UTF-8") ?></p><?php unset($_SESSION["login_error"], $_SESSION["login_username"]); endif; ?>

                <button type="submit">Admin Login</button>
            </form>
        </div>
    </div>
</body>
</html>
