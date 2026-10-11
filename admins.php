<?php
include "helpers.php";
include "header.php";
?>
<div class="topbar">
    <div>
        <p class="label">SYSTEM</p>
        <h1>Admins / Staff</h1>
        <p class="muted">Accounts that use the gym management system and process records.</p>
    </div>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Role</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!tableExists($conn, 'admins')): ?>
                <tr><td colspan="4" class="empty">admins table not found.</td></tr>
            <?php else: ?>
                <?php
                $r = mysqli_query($conn, "SELECT * FROM admins ORDER BY admin_id");
                while ($r && ($x = mysqli_fetch_assoc($r))):
                ?>
                    <tr>
                            <td><?= esc($x['admin_id']) ?></td>
                            <td><?= esc($x['username']) ?></td>
                            <td><?= esc($x['full_name']) ?></td>
                            <td><?= esc($x['role']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include "footer.php"; ?>
