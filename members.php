<?php
include "helpers.php";
include "header.php";

$message = "";
$contactInvalid = false;
$emailInvalid = false;
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$adminId = (int)($_SESSION['admin_id'] ?? 1);

// Archive a member and keep copies of the related records.
if ($action === 'archive' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "SELECT * FROM members WHERE member_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $member = mysqli_fetch_assoc($result);
        if (!$member) {
            throw new Exception("Member not found.");
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO archived_members (original_member_id, first_name, last_name, gender, birthdate, contact_number, email, address, status, date_joined) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssssssss", $member['member_id'], $member['first_name'], $member['last_name'], $member['gender'], $member['birthdate'], $member['contact_number'], $member['email'], $member['address'], $member['status'], $member['date_joined']);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));

        $stmt = mysqli_prepare($conn, "INSERT INTO archived_subscriptions (original_subscription_id, original_member_id, plan_id, start_date, end_date, status) SELECT subscription_id, member_id, plan_id, start_date, end_date, status FROM subscriptions WHERE member_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));

        $stmt = mysqli_prepare($conn, "INSERT INTO archived_payments (original_payment_id, original_subscription_id, admin_id, amount, payment_method, payment_date) SELECT p.payment_id, p.subscription_id, p.admin_id, p.amount, p.payment_method, p.payment_date FROM payments p JOIN subscriptions s ON p.subscription_id = s.subscription_id WHERE s.member_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));

        $stmt = mysqli_prepare($conn, "INSERT INTO archived_attendance (original_attendance_id, original_member_id, class_id, admin_id, check_in, check_out) SELECT attendance_id, member_id, class_id, admin_id, check_in, check_out FROM attendance WHERE member_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));

        $stmt = mysqli_prepare($conn, "DELETE FROM members WHERE member_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_commit($conn);
        $message = "Member and related records archived successfully.";
        $action = '';
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $message = "Unable to archive member: " . $e->getMessage();
        $action = '';
    }
}

// Save new member or update an existing member.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? 'Other';
    $birthdate = $_POST['birthdate'] ?? '';
    $contact = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $classes = $_POST['classes'] ?? [];
    $classes = is_array($classes) ? array_values(array_unique(array_filter(array_map('intval', $classes)))) : [];
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    $paymentAmountRaw = trim((string) ($_POST['payment_amount'] ?? ''));
    $paymentAmount = $paymentAmountRaw === '' ? 0.0 : (float) $paymentAmountRaw;
    $startDate = $_POST['start_date'] ?? date('Y-m-d');

    $contactInvalid = ($contact !== '' && !preg_match('/^(09[0-9]{9}|\+639[0-9]{9})$/D', $contact));
    $emailInvalid = ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL));

    if ($first === '' || $last === '' || $birthdate === '') {
        $message = 'Please enter the first name, last name, and birthdate.';
    } elseif ($contactInvalid) {
        $message = 'Enter a valid Philippine mobile number in 09XXXXXXXXX or +639XXXXXXXXX format.';
    } elseif ($emailInvalid) {
        $message = 'A valid email address is required.';
    } elseif (!in_array($gender, ['Male', 'Female', 'Other'], true) || !in_array($status, ['Active', 'Expired', 'Inactive'], true)) {
        $message = 'Please select a valid gender and status.';
    } elseif ($planId <= 0) {
        $message = 'Please select a membership plan.';
    } elseif ($action === 'add' && $paymentAmount <= 0) {
        $message = 'Please enter a payment amount greater than zero.';
    } elseif ($paymentAmount < 0 || ($paymentAmount > 0 && !in_array($paymentMethod, ['Cash', 'GCash', 'Bank Transfer'], true))) {
        $message = 'Please enter a valid payment amount and payment method.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !checkdate((int) substr($startDate, 5, 2), (int) substr($startDate, 8, 2), (int) substr($startDate, 0, 4))) {
        $message = 'Please select a valid membership start date.';
    } else {
        mysqli_begin_transaction($conn);

        try {
            $contact = $contact === '' ? null : $contact;
            $address = $address === '' ? null : $address;

            // Confirm the selected plan exists before changing any records.
            $stmt = mysqli_prepare($conn, 'SELECT plan_id, plan_name, duration_days, price FROM membership_plans WHERE plan_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $planId);
            mysqli_stmt_execute($stmt);
            $plan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if (!$plan) {
                throw new Exception('Selected membership plan was not found.');
            }

            // Verify every submitted class is active. Invalid IDs abort the whole save.
            foreach ($classes as $classId) {
                $stmt = mysqli_prepare($conn, "SELECT class_id FROM fitness_classes WHERE class_id = ? AND status = 'Active'");
                mysqli_stmt_bind_param($stmt, 'i', $classId);
                mysqli_stmt_execute($stmt);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                    throw new Exception('One of the selected fitness classes is unavailable. Please refresh and try again.');
                }
            }

            $planChanged = false;

            if ($action === 'add') {
                $stmt = mysqli_prepare($conn, 'INSERT INTO members (first_name, last_name, gender, birthdate, contact_number, email, address, status, date_joined) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())');
                mysqli_stmt_bind_param($stmt, 'ssssssss', $first, $last, $gender, $birthdate, $contact, $email, $address, $status);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_stmt_error($stmt));
                }
                $memberId = mysqli_insert_id($conn);

                $endDate = date('Y-m-d', strtotime($startDate . ' +' . (int) $plan['duration_days'] . ' days'));
                $subscriptionStatus = 'Active';
                $stmt = mysqli_prepare($conn, 'INSERT INTO subscriptions (member_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iisss', $memberId, $planId, $startDate, $endDate, $subscriptionStatus);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_stmt_error($stmt));
                }
                $subscriptionId = mysqli_insert_id($conn);

                $stmt = mysqli_prepare($conn, 'INSERT INTO payments (subscription_id, admin_id, amount, payment_method) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iids', $subscriptionId, $adminId, $paymentAmount, $paymentMethod);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_stmt_error($stmt));
                }

                $message = 'Member, subscription, and payment saved successfully.';
            } else {
                // Lock and load the member's current/latest subscription for a safe update.
                $stmt = mysqli_prepare($conn, 'SELECT member_id FROM members WHERE member_id = ? FOR UPDATE');
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                    throw new Exception('Member not found.');
                }

                $stmt = mysqli_prepare($conn, 'UPDATE members SET first_name = ?, last_name = ?, gender = ?, birthdate = ?, contact_number = ?, email = ?, address = ?, status = ? WHERE member_id = ?');
                mysqli_stmt_bind_param($stmt, 'ssssssssi', $first, $last, $gender, $birthdate, $contact, $email, $address, $status, $id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_stmt_error($stmt));
                }

                $stmt = mysqli_prepare($conn, "SELECT subscription_id, plan_id, start_date, status FROM subscriptions WHERE member_id = ? ORDER BY (status = 'Active') DESC, subscription_id DESC LIMIT 1 FOR UPDATE");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $currentSubscription = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                $subscriptionId = $currentSubscription ? (int) $currentSubscription['subscription_id'] : 0;
                $planChanged = !$currentSubscription || (int) $currentSubscription['plan_id'] !== $planId;

                if ($planChanged) {
                    // Keep previous subscription/payment history intact. Close the old subscription
                    // and create a new subscription for the newly selected plan.
                    if ($currentSubscription && $currentSubscription['status'] === 'Active') {
                        $oldSubscriptionId = (int) $currentSubscription['subscription_id'];
                        $cancelled = 'Cancelled';
                        $stmt = mysqli_prepare($conn, 'UPDATE subscriptions SET status = ? WHERE subscription_id = ?');
                        mysqli_stmt_bind_param($stmt, 'si', $cancelled, $oldSubscriptionId);
                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception(mysqli_stmt_error($stmt));
                        }
                    }

                    $endDate = date('Y-m-d', strtotime($startDate . ' +' . (int) $plan['duration_days'] . ' days'));
                    $subscriptionStatus = 'Active';
                    $stmt = mysqli_prepare($conn, 'INSERT INTO subscriptions (member_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'iisss', $id, $planId, $startDate, $endDate, $subscriptionStatus);
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception(mysqli_stmt_error($stmt));
                    }
                    $subscriptionId = mysqli_insert_id($conn);
                } elseif ($currentSubscription) {
                    // The plan is unchanged: update the current subscription dates only.
                    $endDate = date('Y-m-d', strtotime($startDate . ' +' . (int) $plan['duration_days'] . ' days'));
                    $stmt = mysqli_prepare($conn, 'UPDATE subscriptions SET start_date = ?, end_date = ? WHERE subscription_id = ?');
                    mysqli_stmt_bind_param($stmt, 'ssi', $startDate, $endDate, $subscriptionId);
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception(mysqli_stmt_error($stmt));
                    }
                }

                // Optional payment is a new transaction. Existing payments are never overwritten.
                if ($paymentAmount > 0) {
                    $stmt = mysqli_prepare($conn, 'INSERT INTO payments (subscription_id, admin_id, amount, payment_method) VALUES (?, ?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'iids', $subscriptionId, $adminId, $paymentAmount, $paymentMethod);
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception(mysqli_stmt_error($stmt));
                    }
                }

                // Synchronize the member's selected fitness classes atomically.
                $stmt = mysqli_prepare($conn, 'DELETE FROM member_fitness_classes WHERE member_id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_stmt_error($stmt));
                }
                foreach ($classes as $classId) {
                    $stmt = mysqli_prepare($conn, 'INSERT INTO member_fitness_classes (member_id, class_id) VALUES (?, ?)');
                    mysqli_stmt_bind_param($stmt, 'ii', $id, $classId);
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception(mysqli_stmt_error($stmt));
                    }
                }

                $message = $planChanged
                    ? 'Member details and classes updated. A new subscription was created; previous payments were preserved.'
                    : 'Member details, subscription dates, and fitness classes updated.';
                if ($paymentAmount > 0) {
                    $message .= ' New payment recorded.';
                }
            }

            // Keep class enrollment in sync for newly added members as well.
            if ($action === 'add') {
                foreach ($classes as $classId) {
                    $stmt = mysqli_prepare($conn, 'INSERT INTO member_fitness_classes (member_id, class_id) VALUES (?, ?)');
                    mysqli_stmt_bind_param($stmt, 'ii', $memberId, $classId);
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception(mysqli_stmt_error($stmt));
                    }
                }
            }

            // Day Pass members are recorded as walk-ins. Existing linked rows are reused.
            if (strcasecmp(trim($plan['plan_name']), 'Day Pass') === 0 && tableExists($conn, 'walkin_attendance')) {
                $columnCheck = mysqli_query($conn, "SHOW COLUMNS FROM walkin_attendance LIKE 'member_id'");
                if ($columnCheck && mysqli_num_rows($columnCheck) > 0) {
                    $walkinName = trim($first . ' ' . $last);
                    $walkinNotes = 'Automatically recorded Day Pass for member #' . ($action === 'add' ? $memberId : $id);
                    $walkinMemberId = $action === 'add' ? $memberId : $id;
                    $stmt = mysqli_prepare($conn, 'INSERT INTO walkin_attendance (member_id, customer_name, contact_number, amount_paid, payment_method, admin_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name), contact_number = VALUES(contact_number), amount_paid = IF(VALUES(amount_paid) > 0, VALUES(amount_paid), amount_paid), payment_method = IF(VALUES(amount_paid) > 0, VALUES(payment_method), payment_method), notes = VALUES(notes)');
                    mysqli_stmt_bind_param($stmt, 'issdsis', $walkinMemberId, $walkinName, $contact, $paymentAmount, $paymentMethod, $adminId, $walkinNotes);
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception(mysqli_stmt_error($stmt));
                    }
                }
            }

            mysqli_commit($conn);
            $action = '';
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $message = (strpos($e->getMessage(), 'Duplicate') !== false)
                ? 'That email address is already used.'
                : 'Unable to save member: ' . $e->getMessage();
        }
    }
}

$plans = mysqli_query($conn, "SELECT plan_id, plan_name, duration_days, price FROM membership_plans ORDER BY price");
$classesResult = mysqli_query($conn, "SELECT class_id, class_name, schedule_day, start_time, end_time FROM fitness_classes WHERE status = 'Active' ORDER BY class_name");
$selectedMember = null;
$selectedSubscription = null;
$selectedClasses = [];
if (in_array($action, ['edit', 'view'], true) && $id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM members WHERE member_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $selectedMember = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($selectedMember) {
        $stmt = mysqli_prepare($conn, "SELECT s.subscription_id, s.plan_id, s.start_date, s.end_date, s.status, p.plan_name, p.price, p.duration_days FROM subscriptions s JOIN membership_plans p ON p.plan_id = s.plan_id WHERE s.member_id = ? ORDER BY (s.status = 'Active') DESC, s.subscription_id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $selectedSubscription = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        $stmt = mysqli_prepare($conn, "SELECT class_id FROM member_fitness_classes WHERE member_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $cr = mysqli_stmt_get_result($stmt);
        while ($c = mysqli_fetch_assoc($cr)) $selectedClasses[] = (int)$c['class_id'];
    }
}
?>
<div class="topbar">
    <div><p class="label">MANAGEMENT</p><h1>Members</h1><p class="muted">Add, view, edit, and archive gym members.</p></div>
    <div class="actions" style="margin-top:0">
        <form method="get" action="members.php" class="top-view-form">
            <select name="id" aria-label="Choose member to view" required>
                <option value="">Choose member</option>
                <?php $viewOptions = mysqli_query($conn, "SELECT member_id, first_name, last_name FROM members ORDER BY first_name, last_name"); while ($vo = $viewOptions ? mysqli_fetch_assoc($viewOptions) : null): ?>
                    <option value="<?= (int)$vo['member_id'] ?>"><?= esc($vo['first_name'] . ' ' . $vo['last_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <input type="hidden" name="action" value="view">
            <button type="submit" class="btn secondary">View Details</button>
        </form>
        <a class="btn" href="members.php?action=add">+ Add Member</a>
    </div>
</div>
<?php if ($message !== ''): ?><div class="alert"><?= esc($message) ?></div><?php endif; ?>

<?php if ($action === 'add' || ($action === 'edit' && $selectedMember)): ?>
<div class="card form-card">
    <h2><?= $action === 'add' ? 'Add Member' : 'Edit Member' ?></h2>
    <form id="memberForm" method="post" novalidate action="members.php?action=<?= esc($action) ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
        <div class="form-grid">
            <div class="form-group"><label>First Name</label><input class="form-control" name="first_name" required value="<?= esc($_POST['first_name'] ?? ($selectedMember['first_name'] ?? '')) ?>"></div>
            <div class="form-group"><label>Last Name</label><input class="form-control" name="last_name" required value="<?= esc($_POST['last_name'] ?? ($selectedMember['last_name'] ?? '')) ?>"></div>
            <div class="form-group"><label>Gender</label><select class="form-control" name="gender"><option <?= (($selectedMember['gender'] ?? 'Male')==='Male')?'selected':'' ?>>Male</option><option <?= (($selectedMember['gender'] ?? '')==='Female')?'selected':'' ?>>Female</option><option <?= (($selectedMember['gender'] ?? '')==='Other')?'selected':'' ?>>Other</option></select></div>
            <div class="form-group"><label>Birthdate</label><input class="form-control" type="date" name="birthdate" required value="<?= esc($_POST['birthdate'] ?? ($selectedMember['birthdate'] ?? '')) ?>"></div>
            <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input id="contact_number" class="form-control <?= $contactInvalid ? 'is-invalid' : '' ?>" type="tel" name="contact_number" maxlength="13" inputmode="tel" placeholder="09XXXXXXXXX or +639XXXXXXXXX" pattern="(09[0-9]{9}|\+639[0-9]{9})" value="<?= esc($_POST['contact_number'] ?? ($selectedMember['contact_number'] ?? '')) ?>" aria-describedby="contactError">
                <p id="contactError" class="field-error" <?= $contactInvalid ? '' : 'hidden' ?>><?= $contactInvalid ? 'Enter a valid Philippine mobile number: 09XXXXXXXXX or +639XXXXXXXXX.' : '' ?></p>
            </div>
            <div class="form-group">
                <label for="email">Email *</label>
                <input id="email" class="form-control <?= $emailInvalid ? 'is-invalid' : '' ?>" type="email" name="email" required maxlength="100" value="<?= esc($_POST['email'] ?? ($selectedMember['email'] ?? '')) ?>" aria-describedby="emailError">
                <p id="emailError" class="field-error" <?= $emailInvalid ? '' : 'hidden' ?>><?= $emailInvalid ? 'Enter a valid email address. Email is required.' : '' ?></p>
            </div>
            <div class="form-group full"><label>Address</label><input class="form-control" name="address" value="<?= esc($_POST['address'] ?? ($selectedMember['address'] ?? '')) ?>"></div>
            <?php if ($action === 'edit'): ?>
            <div class="form-group">
                <label for="status">Member Status</label>
                <select class="form-control" id="status" name="status">
                    <?php foreach (['Active', 'Expired', 'Inactive'] as $memberStatus): ?>
                        <option value="<?= esc($memberStatus) ?>" <?= ($_POST['status'] ?? ($selectedMember['status'] ?? 'Active')) === $memberStatus ? 'selected' : '' ?>><?= esc($memberStatus) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="plan_id">Membership Plan</label>
                <select class="form-control" id="plan_id" name="plan_id" required>
                    <option value="">Choose a plan</option>
                    <?php if ($plans) { mysqli_data_seek($plans, 0); while ($pl = mysqli_fetch_assoc($plans)): ?>
                        <?php $chosenPlan = $_POST['plan_id'] ?? ($selectedSubscription['plan_id'] ?? ''); ?>
                        <option value="<?= (int) $pl['plan_id'] ?>" <?= (string) $chosenPlan === (string) $pl['plan_id'] ? 'selected' : '' ?>>
                            <?= esc($pl['plan_name']) ?> — ₱<?= number_format((float) $pl['price'], 2) ?> (<?= (int) $pl['duration_days'] ?> days)
                        </option>
                    <?php endwhile; } ?>
                </select>
                <?php if ($action === 'edit' && $selectedSubscription): ?>
                    <small class="muted">Current subscription: <?= esc($selectedSubscription['plan_name']) ?>, <?= esc($selectedSubscription['start_date']) ?> to <?= esc($selectedSubscription['end_date']) ?>.</small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="start_date">Membership Start Date</label>
                <input class="form-control" id="start_date" type="date" name="start_date" value="<?= esc($_POST['start_date'] ?? ($selectedSubscription['start_date'] ?? date('Y-m-d'))) ?>" required>
            </div>
            <div class="form-group">
                <label for="payment_amount">Payment Amount (₱) <?= $action === 'edit' ? '(optional: record a new payment)' : '' ?></label>
                <input class="form-control" id="payment_amount" type="number" min="0.01" step="0.01" name="payment_amount" value="<?= esc($_POST['payment_amount'] ?? '') ?>" <?= $action === 'add' ? 'required' : '' ?> placeholder="<?= $action === 'edit' ? 'Leave blank if no new payment was collected' : 'Enter amount collected' ?>">
            </div>
            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select class="form-control" id="payment_method" name="payment_method">
                    <?php foreach (['Cash', 'GCash', 'Bank Transfer'] as $method): ?>
                        <option value="<?= esc($method) ?>" <?= ($_POST['payment_method'] ?? 'Cash') === $method ? 'selected' : '' ?>><?= esc($method) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label>Fitness Classes (optional)</label>
                <div class="class-options">
                    <?php if ($classesResult) { mysqli_data_seek($classesResult, 0); while ($cl = mysqli_fetch_assoc($classesResult)): ?>
                        <?php $checkedClasses = array_map('intval', (array) ($_POST['classes'] ?? $selectedClasses)); ?>
                        <label class="check-option">
                            <input type="checkbox" name="classes[]" value="<?= (int) $cl['class_id'] ?>" <?= in_array((int) $cl['class_id'], $checkedClasses, true) ? 'checked' : '' ?>>
                            <?= esc($cl['class_name']) ?> — <?= esc($cl['schedule_day']) ?>, <?= esc(substr($cl['start_time'], 0, 5)) ?>–<?= esc(substr($cl['end_time'], 0, 5)) ?>
                        </label>
                    <?php endwhile; } ?>
                </div>
            </div>
        </div>
        <div class="actions"><button type="submit">Save <?= $action==='add'?'Member':'Changes' ?></button><a class="btn secondary" href="members.php">Cancel</a></div>
    </form>
</div>
<?php elseif ($action === 'view' && $id > 0 && $selectedMember): ?>
<div class="card"><h2>Member Details</h2><div class="details-grid">
<?php foreach (['member_id'=>'Member ID','first_name'=>'First Name','last_name'=>'Last Name','gender'=>'Gender','birthdate'=>'Birthdate','contact_number'=>'Contact Number','email'=>'Email','address'=>'Address','status'=>'Status','date_joined'=>'Date Joined'] as $key=>$label): ?><div><small class="muted"><?= esc($label) ?></small><p><?php if ($key === 'status'): ?><span class="status-badge <?= strtolower(esc($selectedMember[$key] ?? '')) ?>"><?= esc($selectedMember[$key] ?? '—') ?></span><?php else: ?><?= esc($selectedMember[$key] ?? '—') ?><?php endif; ?></p></div><?php endforeach; ?>
<div><small class="muted">Membership Plan</small><p><?php $stmt=mysqli_prepare($conn,"SELECT p.plan_name FROM subscriptions s JOIN membership_plans p ON p.plan_id=s.plan_id WHERE s.member_id=? ORDER BY (s.status = 'Active') DESC, s.subscription_id DESC LIMIT 1"); mysqli_stmt_bind_param($stmt,"i",$id); mysqli_stmt_execute($stmt); $pr=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); echo esc($pr['plan_name'] ?? 'No plan'); ?></p></div>
<div><small class="muted">Fitness Classes</small><p><?php $stmt=mysqli_prepare($conn,"SELECT GROUP_CONCAT(fc.class_name SEPARATOR ', ') AS names FROM member_fitness_classes mc JOIN fitness_classes fc ON fc.class_id=mc.class_id WHERE mc.member_id=?"); mysqli_stmt_bind_param($stmt,"i",$id); mysqli_stmt_execute($stmt); $cr=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); echo esc($cr['names'] ?? 'None selected'); ?></p></div>
</div><div class="actions"><a class="btn" href="members.php?action=edit&id=<?= $id ?>">Edit Member</a><a class="btn secondary" href="members.php">Back to Members</a></div></div>
<?php elseif ($action === 'view'): ?>
<div class="card"><p>Select a member from the table below, then click <b>View</b> to see all details.</p></div>
<?php endif; ?>

<?php if (!in_array($action, ['add','edit'], true)): ?>
<div class="table-wrap"><table class="table"><thead><tr><th>ID</th><th>Name</th><th>Gender</th><th>Contact</th><th>Status</th><th>Date Joined</th><th>Actions</th></tr></thead><tbody>
<?php $r=mysqli_query($conn,"SELECT * FROM members ORDER BY member_id DESC LIMIT 100"); if($r && mysqli_num_rows($r)>0): while($x=mysqli_fetch_assoc($r)): ?>
<tr><td><?= (int)$x['member_id'] ?></td><td><?= esc($x['first_name'].' '.$x['last_name']) ?></td><td><?= esc($x['gender']) ?></td><td><?= esc($x['contact_number'] ?? '') ?></td><td><span class="status-badge <?= strtolower(esc($x['status'])) ?>"><?= esc($x['status']) ?></span></td><td><?= esc($x['date_joined']) ?></td><td><div class="row-actions"><a class="icon-action" title="View details" aria-label="View details" href="members.php?action=view&id=<?= (int)$x['member_id'] ?>">◉</a><a class="icon-action" title="Edit member" aria-label="Edit member" href="members.php?action=edit&id=<?= (int)$x['member_id'] ?>">✎</a><form method="post" action="members.php?action=archive&id=<?= (int)$x['member_id'] ?>" onsubmit="return confirm('Archive this member and their related records?');"><button class="icon-action archive-icon" type="submit" title="Archive member" aria-label="Archive member">▣</button></form></div></td></tr>
<?php endwhile; else: ?><tr><td colspan="7" class="empty">No members found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php endif; ?>
<script>
(function () {
    const form = document.getElementById('memberForm');
    if (!form) return;
    const contact = document.getElementById('contact_number');
    const email = document.getElementById('email');
    const contactError = document.getElementById('contactError');
    const emailError = document.getElementById('emailError');
    const phMobile = /^(09[0-9]{9}|\+639[0-9]{9})$/;
    const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    function showError(input, messageEl, message) {
        const invalid = Boolean(message);
        input.classList.toggle('is-invalid', invalid);
        messageEl.textContent = message || '';
        messageEl.hidden = !invalid;
        return !invalid;
    }
    function validateContact() {
        const value = contact.value.trim();
        return showError(contact, contactError, value && !phMobile.test(value) ? 'Enter a valid Philippine mobile number: 09XXXXXXXXX or +639XXXXXXXXX.' : '');
    }
    function validateEmail() {
        const value = email.value.trim();
        return showError(email, emailError, !value ? 'Email is required.' : (!validEmail.test(value) ? 'Enter a valid email address.' : ''));
    }
    contact.addEventListener('input', validateContact);
    email.addEventListener('input', validateEmail);
    form.addEventListener('submit', function (event) {
        const contactOkay = validateContact();
        const emailOkay = validateEmail();
        if (!contactOkay || !emailOkay) {
            event.preventDefault();
            (!contactOkay ? contact : email).focus();
        }
    });
})();
</script>
<?php include "footer.php"; ?>
