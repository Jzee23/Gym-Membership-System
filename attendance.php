<?php
include "helpers.php";
include "header.php";

$message = '';
$messageType = 'success';
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

// Attendance actions use database-generated timestamps and run inside transactions.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminId > 0) {
    $attendanceAction = $_POST['attendance_action'] ?? '';
    $memberId = (int) ($_POST['member_id'] ?? 0);
    $classId = (int) ($_POST['class_id'] ?? 0);
    $attendanceId = (int) ($_POST['attendance_id'] ?? 0);

    try {
        mysqli_begin_transaction($conn);

        if ($attendanceAction === 'check_in' && $memberId > 0) {
            // Lock the member row to serialize check-in attempts for the same member.
            $stmt = mysqli_prepare($conn, 'SELECT member_id FROM members WHERE member_id = ? FOR UPDATE');
            mysqli_stmt_bind_param($stmt, 'i', $memberId);
            mysqli_stmt_execute($stmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                throw new Exception('Member not found.');
            }

            $stmt = mysqli_prepare($conn, 'SELECT attendance_id FROM attendance WHERE member_id = ? AND check_out IS NULL LIMIT 1 FOR UPDATE');
            mysqli_stmt_bind_param($stmt, 'i', $memberId);
            mysqli_stmt_execute($stmt);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                throw new Exception('This member is already checked in. Check out before starting another attendance session.');
            }

            $nullableClassId = $classId > 0 ? $classId : null;
            $stmt = mysqli_prepare($conn, 'INSERT INTO attendance (member_id, class_id, admin_id, check_in, check_out) VALUES (?, ?, ?, NOW(), NULL)');
            mysqli_stmt_bind_param($stmt, 'iii', $memberId, $nullableClassId, $adminId);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_stmt_error($stmt));
            }
            $message = 'Member checked in successfully.';
        } elseif ($attendanceAction === 'check_out' && $attendanceId > 0) {
            $stmt = mysqli_prepare($conn, 'SELECT attendance_id, check_out FROM attendance WHERE attendance_id = ? FOR UPDATE');
            mysqli_stmt_bind_param($stmt, 'i', $attendanceId);
            mysqli_stmt_execute($stmt);
            $attendance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$attendance) {
                throw new Exception('Attendance session not found.');
            }
            if ($attendance['check_out'] !== null) {
                throw new Exception('This attendance session has already been checked out.');
            }

            $stmt = mysqli_prepare($conn, 'UPDATE attendance SET check_out = NOW() WHERE attendance_id = ? AND check_out IS NULL');
            mysqli_stmt_bind_param($stmt, 'i', $attendanceId);
            if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception('Unable to check out this attendance session. It may already be closed.');
            }
            $message = 'Member checked out successfully.';
        } else {
            throw new Exception('Invalid attendance action.');
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $message = $e->getMessage();
        $messageType = 'error';
    }
}
?>
<div class="topbar">
    <div>
        <p class="label">RECORDS</p>
        <h1>Attendance</h1>
        <p class="muted">Monitor member check-ins, check-outs, and class attendance.</p>
    </div>
    <form method="post" class="actions attendance-checkin-form">
        <label for="checkin_member">Check in a member</label>
        <select class="form-control" name="member_id" id="checkin_member" required>
            <option value="">Choose member</option>
            <?php
            $memberOptions = mysqli_query($conn, "SELECT member_id, first_name, last_name FROM members WHERE status = 'Active' ORDER BY first_name, last_name");
            while ($memberOptions && ($memberOption = mysqli_fetch_assoc($memberOptions))):
            ?>
                <option value="<?= (int) $memberOption['member_id'] ?>"><?= esc($memberOption['first_name'] . ' ' . $memberOption['last_name']) ?></option>
            <?php endwhile; ?>
        </select>
        <button class="btn" type="submit" name="attendance_action" value="check_in">Check In</button>
    </form>
</div>

<?php if ($message !== ''): ?>
    <div class="notice <?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="table-wrap">
    <table class="table attendance-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Member</th>
                <th>Class</th>
                <th>Check-in Date &amp; Time</th>
                <th>Check-out Date &amp; Time</th>
                <th>Status</th>
                <th>Recorded By</th>
                <th class="actions-column">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!tableExists($conn, 'attendance')): ?>
                <tr><td colspan="8" class="empty">Attendance table not found.</td></tr>
            <?php else: ?>
                <?php
                $r = mysqli_query($conn, "SELECT att.*, CONCAT(m.first_name, ' ', m.last_name) AS member_name, fc.class_name, a.full_name AS admin_name FROM attendance att JOIN members m ON m.member_id = att.member_id LEFT JOIN fitness_classes fc ON fc.class_id = att.class_id LEFT JOIN admins a ON a.admin_id = att.admin_id ORDER BY att.attendance_id DESC");
                $hasRows = false;
                while ($r && ($x = mysqli_fetch_assoc($r))):
                    $hasRows = true;
                    $isOpen = $x['check_out'] === null;
                ?>
                    <tr>
                        <td><?= (int) $x['attendance_id'] ?></td>
                        <td><?= esc($x['member_name']) ?></td>
                        <td><?= esc($x['class_name'] ?? 'Regular Gym') ?></td>
                        <td><?= esc(date('M d, Y h:i A', strtotime($x['check_in']))) ?></td>
                        <td><?= $x['check_out'] ? esc(date('M d, Y h:i A', strtotime($x['check_out']))) : '—' ?></td>
                        <td><span class="status-badge <?= $isOpen ? 'active' : 'inactive' ?>"><?= $isOpen ? 'Checked In' : 'Checked Out' ?></span></td>
                        <td><?= esc($x['admin_name'] ?? '—') ?></td>
                        <td class="actions-column">
                            <div class="row-actions attendance-row-actions">
                                <form method="post" onsubmit="return confirm('Start a new attendance session for this member?');">
                                    <input type="hidden" name="member_id" value="<?= (int) $x['member_id'] ?>">
                                    <input type="hidden" name="class_id" value="<?= (int) ($x['class_id'] ?? 0) ?>">
                                    <button class="icon-action checkin-action" type="submit" name="attendance_action" value="check_in" title="Check in again" aria-label="Check in again" <?= $isOpen ? 'disabled' : '' ?>>↳</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Check out this member now?');">
                                    <input type="hidden" name="attendance_id" value="<?= (int) $x['attendance_id'] ?>">
                                    <button class="icon-action checkout-action" type="submit" name="attendance_action" value="check_out" title="Check out" aria-label="Check out" <?= !$isOpen ? 'disabled' : '' ?>>↪</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if (!$hasRows): ?>
                    <tr><td colspan="8" class="empty">No attendance records found. Select an active member above to record a check-in.</td></tr>
                <?php endif; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include "footer.php"; ?>
