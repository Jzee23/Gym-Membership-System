<?php
include "helpers.php";
include "header.php";

$stats = [
    ['Members',       'members'],
    ['Plans',         'membership_plans'],
    ['Classes',       'fitness_classes'],
    ['Subscriptions', 'subscriptions'],
    ['Payments',      'payments'],
    ['Attendance',    'attendance'],
    ['Admins',        'admins'],
];
?>
<div class="topbar">
    <div>
        <p class="label">ADMIN DASHBOARD</p>
        <h1>Gym Overview</h1>
        <p class="muted">Manage members, memberships, classes, payments, and attendance.</p>
    </div>
    <div class="admin-info">
        Logged in as<br>
        <strong><?= esc($_SESSION['admin_name'] ?? $_SESSION['admin_username']) ?></strong>
    </div>
</div>

<div class="cards">
    <?php foreach ($stats as $c): ?>
        <div class="card">
            <div class="icon">●</div>
            <h3><?= esc($c[0]) ?></h3>
            <div class="number"><?= countRows($conn, $c[1]) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid-2 section">
    <div class="card">
        <h2>Quick Actions</h2>
        <p class="muted">Common tasks for the administrator.</p>
        <div class="actions">
            <a class="btn" href="members.php?action=add">Add Member</a>
            <a class="btn secondary" href="payments.php">View Payments</a>
        </div>
        <div class="actions">
            <a class="btn secondary" href="attendance.php">Record Attendance</a>
            <a class="btn secondary" href="classes.php">Manage Classes</a>
        </div>
    </div>

    <div class="card">
        <h2>System Features</h2>
        <p class="muted">Based on your database design.</p>
        <p>Member records • Membership plans • Subscriptions • Fitness classes • Payments • Attendance • Admin processing</p>
    </div>
</div>
<?php include "footer.php"; ?>
