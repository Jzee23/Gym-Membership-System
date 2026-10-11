<?php
include "helpers.php";
include "header.php";
?>
<div class="topbar">
    <div>
        <p class="label">MANAGEMENT</p>
        <h1>Fitness Classes</h1>
        <p class="muted">Manage Zumba, Yoga, Boxing, and other group classes.</p>
    </div>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                    <th>ID</th>
                    <th>Class</th>
                    <th>Instructor</th>
                    <th>Schedule</th>
                    <th>Capacity</th>
                    <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!tableExists($conn, 'fitness_classes')): ?>
                <tr><td colspan="6" class="empty">fitness_classes table not found.</td></tr>
            <?php else: ?>
                <?php
                $r = mysqli_query($conn, "SELECT * FROM fitness_classes ORDER BY class_id DESC");
                while ($r && ($x = mysqli_fetch_assoc($r))):
                ?>
                    <tr>
                            <td><?= esc($x['class_id']) ?></td>
                            <td><?= esc($x['class_name']) ?></td>
                            <td><?= esc($x['instructor']) ?></td>
                            <td>
                            <?= esc($x['schedule_day']) ?>
                            <?= esc(substr($x['start_time'], 0, 5)) ?>-<?= esc(substr($x['end_time'], 0, 5)) ?>
                        </td>
                            <td><?= esc($x['max_capacity']) ?></td>
                            <td><span class="badge <?= strtolower(esc($x['status'])) ?>"><?= esc($x['status']) ?></span></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include "footer.php"; ?>
