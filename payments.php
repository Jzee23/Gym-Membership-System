<?php
include "helpers.php";
include "header.php";
?>
<div class="topbar">
    <div>
        <p class="label">RECORDS</p>
        <h1>Payments</h1>
        <p class="muted">View payment transactions and revenue-related records.</p>
    </div>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                    <th>ID</th>
                    <th>Subscription / Plan</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Payment Date</th>
                    <th>Processed By</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!tableExists($conn, 'payments')): ?>
                <tr><td colspan="6" class="empty">payments table not found.</td></tr>
            <?php else: ?>
                <?php
                $r = mysqli_query($conn, "SELECT p.*, mp.plan_name, CONCAT(m.first_name, ' ', m.last_name) AS member_name, a.full_name AS admin_name FROM payments p JOIN subscriptions s ON s.subscription_id = p.subscription_id JOIN membership_plans mp ON mp.plan_id = s.plan_id JOIN members m ON m.member_id = s.member_id JOIN admins a ON a.admin_id = p.admin_id ORDER BY p.payment_id DESC");
                while ($r && ($x = mysqli_fetch_assoc($r))):
                ?>
                    <tr>
                            <td><?= esc($x['payment_id']) ?></td>
                            <td><?= esc($x['plan_name']) ?><div class="muted" style="font-size:11px;margin-top:3px"><?= esc($x['member_name']) ?></div></td>
                            <td>₱<?= number_format((float) $x['amount'], 2) ?></td>
                            <td><?= esc($x['payment_method']) ?></td>
                            <td><?= esc($x['payment_date']) ?></td>
                            <td><?= esc($x['admin_name']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include "footer.php"; ?>
