<?php
include_once "helpers.php";
include_once "auth.php";
include_once "db.php";

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    if (!tableExists($conn, 'walkin_attendance')) {
        $error = 'The walkin_attendance table is missing. Run the walk-in/archive table SQL file first.';
    } elseif ($action === 'checkout') {
        $walkinId = filter_input(INPUT_POST, 'walkin_id', FILTER_VALIDATE_INT);
        if ($walkinId) {
            $stmt = mysqli_prepare($conn, "UPDATE walkin_attendance SET check_out = NOW() WHERE walkin_id = ? AND check_out IS NULL");
            mysqli_stmt_bind_param($stmt, 'i', $walkinId);
            mysqli_stmt_execute($stmt);
            $message = mysqli_stmt_affected_rows($stmt) ? 'Check-out recorded.' : 'This visit was already checked out or could not be found.';
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Invalid walk-in record.';
        }
    } else {
        $name = trim($_POST['customer_name'] ?? '');
        $contact = trim($_POST['contact_number'] ?? '');
        $amount = filter_var($_POST['amount_paid'] ?? '', FILTER_VALIDATE_FLOAT);
        $method = $_POST['payment_method'] ?? 'Cash';
        $notes = trim($_POST['notes'] ?? '');
        $allowedMethods = ['Cash', 'GCash', 'Bank Transfer'];

        if ($name === '') {
            $error = 'Please enter the customer name.';
        } elseif ($amount === false || $amount < 0) {
            $error = 'Enter a valid Day Pass fee (0 or greater).';
        } elseif (!in_array($method, $allowedMethods, true)) {
            $error = 'Choose a valid payment method.';
        } else {
            $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
            if ($adminId > 0) {
                $stmt = mysqli_prepare($conn, "INSERT INTO walkin_attendance (customer_name, contact_number, amount_paid, payment_method, admin_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'ssdsis', $name, $contact, $amount, $method, $adminId, $notes);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO walkin_attendance (customer_name, contact_number, amount_paid, payment_method, notes) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'ssdss', $name, $contact, $amount, $method, $notes);
            }
            if ($stmt && mysqli_stmt_execute($stmt)) {
                $message = 'Day Pass recorded. Check-in time was saved automatically.';
            } else {
                $error = 'Could not save this Day Pass record. Check the database columns and try again.';
            }
            if ($stmt) mysqli_stmt_close($stmt);
        }
    }
}

include "header.php";
?>
<div class="topbar"><div><p class="label">RECORDS</p><h1>Day Pass / Walk-in Attendance</h1><p class="muted">Record one-day gym access and track customer check-in/check-out.</p></div></div>
<?php if ($message !== ''): ?><div class="notice success"><?= esc($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error"><?= esc($error) ?></div><?php endif; ?>

<?php if (!tableExists($conn, 'walkin_attendance')): ?>
    <div class="card"><h2>Database table missing</h2><p class="muted">The <code>walkin_attendance</code> table does not exist in the selected database. Run the provided <code>fix_walkin_archives.sql</code> in phpMyAdmin, selecting the <code>gym_management</code> database first.</p></div>
<?php else: ?>
<div class="card" style="margin-bottom:20px">
    <h2>New Day Pass</h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add">
        <div><label for="customer_name">Customer name *</label><input id="customer_name" name="customer_name" required maxlength="100"></div>
        <div><label for="contact_number">Contact number</label><input id="contact_number" name="contact_number" maxlength="20"></div>
        <div><label for="amount_paid">Day Pass fee (₱) *</label><input id="amount_paid" name="amount_paid" type="number" min="0" step="0.01" value="100.00" required></div>
        <div><label for="payment_method">Payment method *</label><select id="payment_method" name="payment_method" required><option>Cash</option><option>GCash</option><option>Bank Transfer</option></select></div>
        <div class="full"><label for="notes">Notes (optional)</label><input id="notes" name="notes" maxlength="255"></div>
        <div class="full"><button class="btn" type="submit">Save Day Pass &amp; Check In</button></div>
    </form>
</div>
<div class="table-wrap"><table class="table"><thead><tr><th>ID</th><th>Customer</th><th>Contact</th><th>Day Pass Fee</th><th>Method</th><th>Check-in</th><th>Check-out</th><th>Action</th></tr></thead><tbody>
<?php
$result = mysqli_query($conn, "SELECT * FROM walkin_attendance ORDER BY walkin_id DESC");
if (mysqli_num_rows($result) === 0) {
    echo '<tr><td class="empty" colspan="8">No walk-in / Day Pass records yet.</td></tr>';
} else {
    while ($row = mysqli_fetch_assoc($result)) {
        echo '<tr><td>'.esc($row['walkin_id']).'</td><td>'.esc($row['customer_name']).'</td><td>'.esc($row['contact_number'] ?? '').'</td><td>₱'.number_format((float)$row['amount_paid'], 2).'</td><td>'.esc($row['payment_method']).'</td><td>'.esc($row['check_in']).'</td><td>'.esc($row['check_out'] ?? '—').'</td><td>';
        if (empty($row['check_out'])) {
            echo '<form method="post" onsubmit="return confirm(\'Record check-out for this customer?\')" style="margin:0"><input type="hidden" name="action" value="checkout"><input type="hidden" name="walkin_id" value="'.(int)$row['walkin_id'].'"><button class="btn" type="submit">Check Out</button></form>';
        } else {
            echo '<span class="muted">Completed</span>';
        }
        echo '</td></tr>';
    }
}
?>
</tbody></table></div>
<?php endif; ?>
<?php include "footer.php"; ?>
