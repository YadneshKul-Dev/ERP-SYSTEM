<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$enc = Encryption::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
    requirePermission('employees', $action === 'add' ? 'create' : 'edit');
    $user_id = sanitize($_GET['user_id'] ?? '');

    $data = [
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'middle_name' => sanitize($_POST['middle_name'] ?? ''),
        'date_of_birth' => sanitize($_POST['date_of_birth'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? ''),
        'marital_status' => sanitize($_POST['marital_status'] ?? ''),
        'blood_group' => sanitize($_POST['blood_group'] ?? ''),
        'nationality' => sanitize($_POST['nationality'] ?? 'Indian'),
        'religion' => sanitize($_POST['religion'] ?? ''),
        'department' => sanitize($_POST['department'] ?? ''),
        'designation' => sanitize($_POST['designation'] ?? ''),
        'date_of_joining' => sanitize($_POST['date_of_joining'] ?? ''),
        'employment_type' => sanitize($_POST['employment_type'] ?? ''),
        'work_location' => sanitize($_POST['work_location'] ?? ''),
        'reporting_to' => sanitize($_POST['reporting_to'] ?? ''),
        'shift_timing' => sanitize($_POST['shift_timing'] ?? ''),
        'weekly_off' => sanitize($_POST['weekly_off'] ?? ''),
        'phone_number' => sanitize($_POST['phone_number'] ?? ''),
        'alternate_phone' => sanitize($_POST['alternate_phone'] ?? ''),
        'personal_email' => sanitize($_POST['personal_email'] ?? ''),
        'official_email' => sanitize($_POST['official_email'] ?? ''),
        'emergency_contact_name' => sanitize($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_phone' => sanitize($_POST['emergency_contact_phone'] ?? ''),
        'emergency_contact_relation' => sanitize($_POST['emergency_contact_relation'] ?? ''),
        'address_aadhar' => sanitize($_POST['address_aadhar'] ?? ''),
        'address_permanent' => sanitize($_POST['address_permanent'] ?? ''),
        'address_current' => sanitize($_POST['address_current'] ?? ''),
        'city' => sanitize($_POST['city'] ?? ''),
        'state' => sanitize($_POST['state'] ?? ''),
        'pincode' => sanitize($_POST['pincode'] ?? ''),
        'highest_education' => sanitize($_POST['highest_education'] ?? ''),
        'university' => sanitize($_POST['university'] ?? ''),
        'year_of_passing' => sanitize($_POST['year_of_passing'] ?? ''),
        'previous_company' => sanitize($_POST['previous_company'] ?? ''),
        'previous_designation' => sanitize($_POST['previous_designation'] ?? ''),
        'years_of_experience' => sanitize($_POST['years_of_experience'] ?? ''),
        'skills' => sanitize($_POST['skills'] ?? ''),
        'bank_name' => sanitize($_POST['bank_name'] ?? ''),
        'ifsc_code' => sanitize($_POST['ifsc_code'] ?? ''),
        'bank_branch' => sanitize($_POST['bank_branch'] ?? ''),
    ];

    $aadhar = sanitize($_POST['aadhar_number'] ?? '');
    $pan = sanitize($_POST['pan_number'] ?? '');
    $bankAc = sanitize($_POST['bank_account_number'] ?? '');
    $uan = sanitize($_POST['uan_number'] ?? '');
    $esic = sanitize($_POST['esic_number'] ?? '');

    $data['aadhar_number_encrypted'] = $enc->encrypt($aadhar);
    $data['pan_number_encrypted'] = $enc->encrypt($pan);
    $data['bank_account_number_encrypted'] = $enc->encrypt($bankAc);
    $data['uan_number_encrypted'] = $enc->encrypt($uan);
    $data['esic_number_encrypted'] = $enc->encrypt($esic);

    $uploadDir = 'data/uploads/hr/';
    $fullUploadDir = __DIR__ . '/../../' . $uploadDir;
    if (!is_dir($fullUploadDir)) {
        mkdir($fullUploadDir, 0755, true);
    }

    $fileFields = ['aadhar_photo' => 'aadhar_photo', 'pan_photo' => 'pan_photo', 'passport_photo' => 'passport_photo'];
    foreach ($fileFields as $field => $dbCol) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
            $filename = $field . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $fullUploadDir . $filename;
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
                $data[$dbCol] = $uploadDir . $filename;
            }
        }
    }

    if ($action === 'add') {
        $data['employee_code'] = $enc->generateEmployeeCode('EMP');
        if ($user_id) {
            $data['user_id'] = (int)$user_id;
        }
        $db->insert('employees', $data);
        showSuccess('Employee added successfully');
    } else {
        assertOwnership('employees', 'employees', $id, ['user_id']);
        $db->update('employees', $data, 'id = ?', [$id]);
        showSuccess('Employee updated successfully');
    }
    header('Location: ?action=list');
    exit;
}

    if ($action === 'delete') {
    requirePermission('employees', 'delete');
    assertOwnership('employees', 'employees', $id, ['user_id']);
    $db->delete('employees', 'id = ?', [$id]);
    showSuccess('Employee deleted successfully');
    header('Location: ?action=list');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

if ($action === 'list') {
    requirePermission('employees', 'view');

    $search = sanitize($_GET['search'] ?? '');
    $dept = sanitize($_GET['department'] ?? '');
    $status = sanitize($_GET['status'] ?? '');

    $where = "WHERE 1=1";
    $params = [];

    if ($search) {
        $where .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ? OR e.designation LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    if ($dept) {
        $where .= " AND e.department = ?";
        $params[] = $dept;
    }
    if ($status !== '') {
        $where .= " AND e.is_active = ?";
        $params[] = $status;
    }

    list($scopeWhere, $scopeParams) = applyScopeFilter('employees', ['user_id']);
    if ($scopeWhere) {
        $where .= " AND $scopeWhere";
        $params = array_merge($params, $scopeParams);
    }

    $total = $db->fetchColumn("SELECT COUNT(*) FROM employees e $where", $params);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $employees = $db->fetchAll(
        "SELECT e.*, u.username FROM employees e LEFT JOIN users u ON e.user_id = u.id $where ORDER BY e.created_at DESC LIMIT $perPage OFFSET $offset",
        $params
    );

    $departments = $db->fetchAll("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department");

    $statuses = ['' => 'All Status', '1' => 'Active', '0' => 'Inactive'];
    ?>
    <div class="page-header">
        <h1 class="page-title">Employees</h1>
        <p class="page-subtitle">Manage all employee records</p>
    </div>

    <div class="action-bar">
        <div class="search-bar">
            <form method="GET" class="filter-bar">
                <input type="hidden" name="action" value="list">
                <input type="text" name="search" class="form-control" placeholder="Search employees..." value="<?= htmlspecialchars($search) ?>">
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d['department']) ?>" <?= $dept === $d['department'] ? 'selected' : '' ?>><?= htmlspecialchars($d['department']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-select">
                    <?php foreach ($statuses as $k => $v): ?>
                    <option value="<?= $k ?>" <?= (string)$status === (string)$k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="?action=list" class="btn btn-secondary">Reset</a>
            </form>
        </div>
        <div class="action-buttons">
            <?php if (hasPermission('employees', 'create')): ?>
            <a href="?action=add" class="btn btn-success">+ Add Employee</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Employee Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td>
                            <?php if ($emp['passport_photo'] && file_exists(__DIR__ . '/../../' . $emp['passport_photo'])): ?>
                            <img src="<?= htmlspecialchars($emp['passport_photo']) ?>" alt="Photo" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                            <?php else: ?>
                            <div style="width:40px;height:40px;border-radius:50%;background:#e0e0e0;display:flex;align-items:center;justify-content:center;font-weight:bold;">
                                <?= strtoupper(substr($emp['first_name'], 0, 1)) . strtoupper(substr($emp['last_name'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($emp['employee_code']) ?></strong></td>
                        <td><?= htmlspecialchars($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']) ?></td>
                        <td><?= htmlspecialchars($emp['department'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($emp['designation'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($emp['phone_number'] ?? '-') ?></td>
                        <td><?= getStatusBadge($emp['is_active'] ? 'active' : 'inactive') ?></td>
                        <td>
                            <a href="?action=edit&id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                            <?php if (hasPermission('employees', 'delete')): ?>
                            <a href="?action=delete&id=<?= $emp['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this employee?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($employees)): ?>
                    <tr><td colspan="8" style="text-align:center">No employees found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?= paginate($total, $page) ?>
    <?php

} elseif ($action === 'add' || $action === 'edit') {
    requirePermission('employees', 'create');

    $emp = [];

    if ($action === 'edit') {
        requirePermission('employees', 'edit');
        assertOwnership('employees', 'employees', $id, ['user_id']);
        $emp = $db->fetchOne("SELECT * FROM employees WHERE id = ?", [$id]);
        if (!$emp) {
            showError('Employee not found');
            header('Location: ?action=list');
            exit;
        }
        if ($emp['aadhar_number_encrypted']) $emp['aadhar_number'] = $enc->decrypt($emp['aadhar_number_encrypted']);
        if ($emp['pan_number_encrypted']) $emp['pan_number'] = $enc->decrypt($emp['pan_number_encrypted']);
        if ($emp['bank_account_number_encrypted']) $emp['bank_account_number'] = $enc->decrypt($emp['bank_account_number_encrypted']);
        if ($emp['uan_number_encrypted']) $emp['uan_number'] = $enc->decrypt($emp['uan_number_encrypted']);
        if ($emp['esic_number_encrypted']) $emp['esic_number'] = $enc->decrypt($emp['esic_number_encrypted']);
    }

    $title = $action === 'add' ? 'Add Employee' : 'Edit Employee';
    $departments = $db->fetchAll("SELECT department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
    $employeesList = $db->fetchAll("SELECT employee_code, first_name, last_name FROM employees WHERE is_active = 1 ORDER BY first_name");
    ?>
    <div class="page-header">
        <h1 class="page-title"><?= $title ?></h1>
        <p class="page-subtitle"><?= $action === 'add' ? 'Create a new employee record' : 'Update employee information' ?></p>
    </div>

    <form method="POST" enctype="multipart/form-data" class="card">
        <input type="hidden" name="action" value="<?= $action ?>">
        <div class="card-header"><h3>Personal Information</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>First Name *</label>
                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($emp['first_name'] ?? '') ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($emp['middle_name'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($emp['last_name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($emp['date_of_birth'] ?? '') ?>">
                </div>
                <div class="form-group col-md-3">
                    <label>Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Select</option>
                        <option value="Male" <?= ($emp['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($emp['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($emp['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Marital Status</label>
                    <select name="marital_status" class="form-select">
                        <option value="">Select</option>
                        <option value="Single" <?= ($emp['marital_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                        <option value="Married" <?= ($emp['marital_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                        <option value="Divorced" <?= ($emp['marital_status'] ?? '') === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                        <option value="Widowed" <?= ($emp['marital_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Blood Group</label>
                    <select name="blood_group" class="form-select">
                        <option value="">Select</option>
                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                        <option value="<?= $bg ?>" <?= ($emp['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Nationality</label>
                    <input type="text" name="nationality" class="form-control" value="<?= htmlspecialchars($emp['nationality'] ?? 'Indian') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Religion</label>
                    <input type="text" name="religion" class="form-control" value="<?= htmlspecialchars($emp['religion'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="card-header"><h3>Employment Details</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Department</label>
                    <select name="department" class="form-select">
                        <option value="">Select</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= htmlspecialchars($d['department_name']) ?>" <?= ($emp['department'] ?? '') === $d['department_name'] ? 'selected' : '' ?>><?= htmlspecialchars($d['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Designation</label>
                    <input type="text" name="designation" class="form-control" value="<?= htmlspecialchars($emp['designation'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Date of Joining</label>
                    <input type="date" name="date_of_joining" class="form-control" value="<?= htmlspecialchars($emp['date_of_joining'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Employment Type</label>
                    <select name="employment_type" class="form-select">
                        <option value="">Select</option>
                        <option value="Permanent" <?= ($emp['employment_type'] ?? '') === 'Permanent' ? 'selected' : '' ?>>Permanent</option>
                        <option value="Probation" <?= ($emp['employment_type'] ?? '') === 'Probation' ? 'selected' : '' ?>>Probation</option>
                        <option value="Contract" <?= ($emp['employment_type'] ?? '') === 'Contract' ? 'selected' : '' ?>>Contract</option>
                        <option value="Intern" <?= ($emp['employment_type'] ?? '') === 'Intern' ? 'selected' : '' ?>>Intern</option>
                        <option value="Trainee" <?= ($emp['employment_type'] ?? '') === 'Trainee' ? 'selected' : '' ?>>Trainee</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Work Location</label>
                    <input type="text" name="work_location" class="form-control" value="<?= htmlspecialchars($emp['work_location'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Reporting To</label>
                    <select name="reporting_to" class="form-select">
                        <option value="">Select</option>
                        <?php foreach ($employeesList as $e): ?>
                        <option value="<?= htmlspecialchars($e['employee_code']) ?>" <?= ($emp['reporting_to'] ?? '') === $e['employee_code'] ? 'selected' : '' ?>><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?> (<?= htmlspecialchars($e['employee_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Shift Timing</label>
                    <input type="text" name="shift_timing" class="form-control" value="<?= htmlspecialchars($emp['shift_timing'] ?? '') ?>" placeholder="e.g. 9:00 AM - 6:00 PM">
                </div>
                <div class="form-group col-md-6">
                    <label>Weekly Off</label>
                    <select name="weekly_off" class="form-select">
                        <option value="">Select</option>
                        <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day): ?>
                        <option value="<?= $day ?>" <?= ($emp['weekly_off'] ?? '') === $day ? 'selected' : '' ?>><?= $day ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-header"><h3>Documents</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Aadhar Number</label>
                    <input type="text" name="aadhar_number" class="form-control" value="<?= htmlspecialchars($emp['aadhar_number'] ?? '') ?>" placeholder="XXXX XXXX XXXX">
                </div>
                <div class="form-group col-md-4">
                    <label>Aadhar Photo</label>
                    <div class="file-upload">
                        <input type="file" name="aadhar_photo" class="file-upload-label">
                        <?php if (!empty($emp['aadhar_photo'])): ?>
                        <a href="<?= htmlspecialchars($emp['aadhar_photo']) ?>" target="_blank">View</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label>PAN Number</label>
                    <input type="text" name="pan_number" class="form-control" value="<?= htmlspecialchars($emp['pan_number'] ?? '') ?>" placeholder="ABCDE1234F">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>PAN Photo</label>
                    <div class="file-upload">
                        <input type="file" name="pan_photo" class="file-upload-label">
                        <?php if (!empty($emp['pan_photo'])): ?>
                        <a href="<?= htmlspecialchars($emp['pan_photo']) ?>" target="_blank">View</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label>Passport Photo</label>
                    <div class="file-upload">
                        <input type="file" name="passport_photo" class="file-upload-label">
                        <?php if (!empty($emp['passport_photo'])): ?>
                        <a href="<?= htmlspecialchars($emp['passport_photo']) ?>" target="_blank">View</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-header"><h3>Contact Information</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars($emp['phone_number'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Alternate Phone</label>
                    <input type="text" name="alternate_phone" class="form-control" value="<?= htmlspecialchars($emp['alternate_phone'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Personal Email</label>
                    <input type="email" name="personal_email" class="form-control" value="<?= htmlspecialchars($emp['personal_email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Official Email</label>
                    <input type="email" name="official_email" class="form-control" value="<?= htmlspecialchars($emp['official_email'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact_name'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Emergency Contact Phone</label>
                    <input type="text" name="emergency_contact_phone" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact_phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Emergency Contact Relation</label>
                    <input type="text" name="emergency_contact_relation" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact_relation'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="card-header"><h3>Address Details</h3></div>
        <div class="card-body">
            <div class="form-group">
                <label>Aadhar Address</label>
                <textarea name="address_aadhar" class="form-control" rows="2"><?= htmlspecialchars($emp['address_aadhar'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Permanent Address</label>
                <textarea name="address_permanent" class="form-control" rows="2"><?= htmlspecialchars($emp['address_permanent'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Current Address</label>
                <textarea name="address_current" class="form-control" rows="2"><?= htmlspecialchars($emp['address_current'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>City</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($emp['city'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>State</label>
                    <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($emp['state'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?= htmlspecialchars($emp['pincode'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="card-header"><h3>Education & Experience</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Highest Education</label>
                    <input type="text" name="highest_education" class="form-control" value="<?= htmlspecialchars($emp['highest_education'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>University</label>
                    <input type="text" name="university" class="form-control" value="<?= htmlspecialchars($emp['university'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Year of Passing</label>
                    <input type="text" name="year_of_passing" class="form-control" value="<?= htmlspecialchars($emp['year_of_passing'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Previous Company</label>
                    <input type="text" name="previous_company" class="form-control" value="<?= htmlspecialchars($emp['previous_company'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Previous Designation</label>
                    <input type="text" name="previous_designation" class="form-control" value="<?= htmlspecialchars($emp['previous_designation'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Years of Experience</label>
                    <input type="text" name="years_of_experience" class="form-control" value="<?= htmlspecialchars($emp['years_of_experience'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Skills</label>
                <textarea name="skills" class="form-control" rows="3"><?= htmlspecialchars($emp['skills'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="card-header"><h3>Bank Details</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($emp['bank_name'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Bank Account Number</label>
                    <input type="text" name="bank_account_number" class="form-control" value="<?= htmlspecialchars($emp['bank_account_number'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>IFSC Code</label>
                    <input type="text" name="ifsc_code" class="form-control" value="<?= htmlspecialchars($emp['ifsc_code'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Bank Branch</label>
                    <input type="text" name="bank_branch" class="form-control" value="<?= htmlspecialchars($emp['bank_branch'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>UAN Number</label>
                    <input type="text" name="uan_number" class="form-control" value="<?= htmlspecialchars($emp['uan_number'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>ESIC Number</label>
                    <input type="text" name="esic_number" class="form-control" value="<?= htmlspecialchars($emp['esic_number'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $action === 'add' ? 'Add Employee' : 'Update Employee' ?></button>
                <a href="?action=list" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
    <?php
}

require_once __DIR__ . '/../../includes/footer.php'; ?>
