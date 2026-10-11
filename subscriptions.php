<?php
include "helpers.php";
include "header.php";
?>
<div class="topbar">
    <div>
        <p class="label">MANAGEMENT</p>
        <h1>Subscriptions</h1>
        <p class="muted">Track member plans, start dates, end dates, and status.</p>
    </div>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Membership Plan</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!tableExists($conn, 'subscriptions')): ?>
                <tr><td colspan="6" class="empty">subscriptions table not found.</td></tr>
            <?php else: ?>
                <?php
                $r = mysqli_query($conn, "SELECT s.*, CONCAT(m.first_name, ' ', m.last_name) AS member_name, p.plan_name FROM subscriptions s JOIN members m ON m.member_id=s.member_id JOIN membership_plans p ON p.plan_id=s.plan_id ORDER BY s.subscription_id DESC");
                while ($r && ($x = mysqli_fetch_assoc($r))):
                ?>
                    <tr>
                            <td><?= esc($x['subscription_id']) ?></td>
                            <td><?= esc($x['member_name']) ?></td>
                            <td><?= esc($x['plan_name']) ?></td>
                            <td><?= esc($x['start_date']) ?></td>
                            <td><?= esc($x['end_date']) ?></td>
                            <td><span class="badge <?= strtolower(esc($x['status'])) ?>"><?= esc($x['status']) ?></span></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include "footer.php"; ?>
