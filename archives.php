<?php include_once "helpers.php"; include_once "header.php"; ?>
<div class="topbar"><div><p class="label">RECORDS</p><h1>Archived Records</h1><p class="muted">Read-only counts of records moved out of active tables.</p></div></div>
<div class="grid-2">
<?php
$tables = [
 ['Archived Members','archived_members'],
 ['Archived Subscriptions','archived_subscriptions'],
 ['Archived Payments','archived_payments'],
 ['Archived Attendance','archived_attendance']
];
foreach ($tables as $item) {
    echo '<div class="card"><h2>'.esc($item[0]).'</h2>';
    if (!tableExists($conn, $item[1])) {
        echo '<p class="muted">Table <code>'.esc($item[1]).'</code> is missing. Run <code>fix_walkin_archives.sql</code> in phpMyAdmin.</p>';
    } else {
        $result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM `'.$item[1].'`');
        $count = mysqli_fetch_assoc($result);
        echo '<p class="number">'.(int)$count['total'].'</p><p class="muted">records stored</p>';
    }
    echo '</div>';
}
?>
</div>
<?php include "footer.php"; ?>
