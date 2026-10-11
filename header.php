<?php
include_once "auth.php";
include_once "db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>AnyFitness Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="logo">ANY<span>FTINESS</span></div>
            <small class="muted">ADMIN PANEL</small>
        </div>
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>

            <div class="nav-section">MANAGEMENT</div>
            <a href="members.php">Members</a>
            <a href="plans.php">Membership Plans</a>
            <a href="classes.php">Fitness Classes</a>
            <a href="subscriptions.php">Subscriptions</a>

            <div class="nav-section">RECORDS</div>
            <a href="payments.php">Payments</a>
            <a href="attendance.php">Attendance</a>
            <a href="walkin.php">Walk-in Attendance</a>
            <a href="archives.php">Archived Records</a>

            <div class="nav-section">SYSTEM</div>
            <a href="admins.php">Admins / Staff</a>
            <a class="logout" href="logout.php">Logout</a>
        </nav>
    </aside>
    <main class="main">
