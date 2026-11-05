<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$enc = Encryption::getInstance();
$empCode = Auth::getInstance()->getEmployeeCode();
$emp = $db->fetchOne("SELECT * FROM employees WHERE employee_code = ?", [$empCode]);

if (!$emp) {
    header('Location: /dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('profile', 'edit_own')) {
    $db->update('employees', [
        'phone_number' => sanitize($_POST['phone_number'] ?? ''),
        'personal_email' => sanitize($_POST['personal_email'] ?? ''),
        'address_current' => sanitize($_POST['address_current'] ?? ''),
        'emergency_contact_name' => sanitize($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_phone' => sanitize($_POST['emergency_contact_phone'] ?? ''),
        'emergency_contact_relation' => sanitize($_POST['emergency_contact_relation'] ?? ''),
    ], 'employee_code = ?', [$empCode]);

    showSuccess('Profile updated successfully');
    header('Location: ?');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('profile', 'view');

$fullName = $emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name'];
$initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
?>
<div class="page-header">
    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">View and manage your personal information</p>
</div>

<div class="profile-header">
    <div class="profile-avatar">
        <?php if ($emp['passport_photo'] && file_exists(__DIR__ . '/../../' . $emp['passport_photo'])): ?>
        <img src="<?= htmlspecialchars($emp['passport_photo']) ?>" alt="Photo">
        <?php else: ?>
        <div class="avatar-placeholder"><?= $initials ?></div>
        <?php endif; ?>
    </div>
    <div class="profile-info">
        <h2><?= htmlspecialchars($fullName) ?></h2>
        <p><strong>Employee Code:</strong> <?= htmlspecialchars($emp['employee_code']) ?></p>
        <p><strong>Department:</strong> <?= htmlspecialchars($emp['department'] ?? '-') ?></p>
        <p><strong>Designation:</strong> <?= htmlspecialchars($emp['designation'] ?? '-') ?></p>
        <a href="idcard.php?employee_code=<?= htmlspecialchars($emp['employee_code']) ?>" class="btn btn-sm btn-info">View ID Card</a>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h3>Personal Details</h3></div>
            <div class="card-body">
                <table class="table">
                    <tr><td><strong>Date of Birth</strong></td><td><?= formatDate($emp['date_of_birth']) ?></td></tr>
                    <tr><td><strong>Gender</strong></td><td><?= htmlspecialchars($emp['gender'] ?? '-') ?></td></tr>
                    <tr><td><strong>Marital Status</strong></td><td><?= htmlspecialchars($emp['marital_status'] ?? '-') ?></td></tr>
                    <tr><td><strong>Blood Group</strong></td><td><?= htmlspecialchars($emp['blood_group'] ?? '-') ?></td></tr>
                    <tr><td><strong>Nationality</strong></td><td><?= htmlspecialchars($emp['nationality'] ?? '-') ?></td></tr>
                    <tr><td><strong>Religion</strong></td><td><?= htmlspecialchars($emp['religion'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h3>Employment Details</h3></div>
            <div class="card-body">
                <table class="table">
                    <tr><td><strong>Date of Joining</strong></td><td><?= formatDate($emp['date_of_joining']) ?></td></tr>
                    <tr><td><strong>Employment Type</strong></td><td><?= htmlspecialchars($emp['employment_type'] ?? '-') ?></td></tr>
                    <tr><td><strong>Work Location</strong></td><td><?= htmlspecialchars($emp['work_location'] ?? '-') ?></td></tr>
                    <tr><td><strong>Shift Timing</strong></td><td><?= htmlspecialchars($emp['shift_timing'] ?? '-') ?></td></tr>
                    <tr><td><strong>Weekly Off</strong></td><td><?= htmlspecialchars($emp['weekly_off'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header"><h3>Contact & Address</h3></div>
            <div class="card-body">
                <table class="table">
                    <tr><td><strong>Phone</strong></td><td><?= htmlspecialchars($emp['phone_number'] ?? '-') ?></td></tr>
                    <tr><td><strong>Alternate Phone</strong></td><td><?= htmlspecialchars($emp['alternate_phone'] ?? '-') ?></td></tr>
                    <tr><td><strong>Personal Email</strong></td><td><?= htmlspecialchars($emp['personal_email'] ?? '-') ?></td></tr>
                    <tr><td><strong>Official Email</strong></td><td><?= htmlspecialchars($emp['official_email'] ?? '-') ?></td></tr>
                    <tr><td><strong>Emergency Contact</strong></td><td><?= htmlspecialchars($emp['emergency_contact_name'] ?? '-') ?> (<?= htmlspecialchars($emp['emergency_contact_relation'] ?? '-') ?>) - <?= htmlspecialchars($emp['emergency_contact_phone'] ?? '-') ?></td></tr>
                    <tr><td><strong>Current Address</strong></td><td><?= nl2br(htmlspecialchars($emp['address_current'] ?? '-')) ?></td></tr>
                    <tr><td><strong>Permanent Address</strong></td><td><?= nl2br(htmlspecialchars($emp['address_permanent'] ?? '-')) ?></td></tr>
                    <tr><td><strong>City / State / Pincode</strong></td><td><?= htmlspecialchars($emp['city'] ?? '-') ?> / <?= htmlspecialchars($emp['state'] ?? '-') ?> / <?= htmlspecialchars($emp['pincode'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (hasPermission('profile', 'edit_own')): ?>
<div class="card mt-3">
    <div class="card-header"><h3>Edit Contact & Emergency Info</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars($emp['phone_number'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Personal Email</label>
                    <input type="email" name="personal_email" class="form-control" value="<?= htmlspecialchars($emp['personal_email'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Emergency Contact Phone</label>
                    <input type="text" name="emergency_contact_phone" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact_phone'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Emergency Contact Relation</label>
                    <input type="text" name="emergency_contact_relation" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact_relation'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Current Address</label>
                    <textarea name="address_current" class="form-control" rows="2"><?= htmlspecialchars($emp['address_current'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
