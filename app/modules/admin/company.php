<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'company_name' => sanitize($_POST['company_name'] ?? ''),
        'company_code' => sanitize($_POST['company_code'] ?? ''),
        'gst_number' => sanitize($_POST['gst_number'] ?? ''),
        'pan_number' => sanitize($_POST['pan_number'] ?? ''),
        'address' => sanitize($_POST['address'] ?? ''),
        'city' => sanitize($_POST['city'] ?? ''),
        'state' => sanitize($_POST['state'] ?? ''),
        'pincode' => sanitize($_POST['pincode'] ?? ''),
        'country' => sanitize($_POST['country'] ?? 'India'),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'website' => sanitize($_POST['website'] ?? ''),
        'logo' => sanitize($_POST['logo'] ?? ''),
        'founded_date' => sanitize($_POST['founded_date'] ?? ''),
        'registration_number' => sanitize($_POST['registration_number'] ?? ''),
        'industry_type' => sanitize($_POST['industry_type'] ?? '')
    ];

    if (empty($data['company_name'])) {
        showError('Company name is required.');
        header('Location: company.php');
        exit;
    }

    $existing = $db->fetchOne("SELECT id FROM companies WHERE id != ? AND is_active = 1", [$id ?: 0]);
    if ($id > 0) {
        $db->update('companies', $data, 'id = ?', [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'update', 'company', 'Updated company profile');
        showSuccess('Company updated successfully.');
    } else {
        if ($existing) {
            showError('An active company already exists. Deactivate it first.');
            header('Location: company.php');
            exit;
        }
        $db->insert('companies', $data);
        $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'company', 'Created company profile');
        showSuccess('Company created successfully.');
    }
    header('Location: company.php');
    exit;
}

if ($action === 'deactivate' && $id > 0) {
    requirePermission('company');
    $db->update('companies', ['is_active' => 0], 'id = ?', [$id]);
    $db->logActivity(getUserId(), getEmployeeCode(), 'deactivate', 'company', 'Deactivated company');
    showSuccess('Company deactivated.');
    header('Location: company.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('company');

$company = $db->fetchOne("SELECT * FROM companies WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$companies = $db->fetchAll("SELECT * FROM companies ORDER BY created_at DESC");
?>

<div class="page-header">
    <h1 class="page-title">Company Profile</h1>
    <?php if (!$company && hasPermission('company', 'create')): ?>
        <a href="?action=add" class="btn btn-primary">Add Company</a>
    <?php endif; ?>
</div>

<div class="row">
    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3><?= $action === 'edit' ? 'Edit' : 'Add' ?> Company</h3>
                <a href="company.php" class="btn btn-sm btn-secondary">Back</a>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Company Code</label>
                            <input type="text" name="company_code" class="form-control" value="<?= htmlspecialchars($company['company_code'] ?? generateCode('CMP', 4)) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>GST Number</label>
                            <input type="text" name="gst_number" class="form-control" value="<?= htmlspecialchars($company['gst_number'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>PAN Number</label>
                            <input type="text" name="pan_number" class="form-control" value="<?= htmlspecialchars($company['pan_number'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>City</label>
                            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($company['city'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>State</label>
                            <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($company['state'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Pincode</label>
                            <input type="text" name="pincode" class="form-control" value="<?= htmlspecialchars($company['pincode'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Country</label>
                            <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($company['country'] ?? 'India') ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($company['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Website</label>
                            <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($company['website'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Founded Date</label>
                            <input type="date" name="founded_date" class="form-control" value="<?= htmlspecialchars($company['founded_date'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Industry Type</label>
                            <input type="text" name="industry_type" class="form-control" value="<?= htmlspecialchars($company['industry_type'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Registration Number</label>
                            <input type="text" name="registration_number" class="form-control" value="<?= htmlspecialchars($company['registration_number'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Logo URL</label>
                            <input type="text" name="logo" class="form-control" value="<?= htmlspecialchars($company['logo'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="action-bar">
                        <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Update' : 'Save' ?> Company</button>
                        <a href="company.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-md-8">
        <?php if ($company): ?>
        <div class="card">
            <div class="card-header">
                <h3><?= htmlspecialchars($company['company_name']) ?></h3>
                <div>
                    <?php if (hasPermission('company', 'edit')): ?>
                        <a href="?action=edit&id=<?= $company['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <a href="?action=deactivate&id=<?= $company['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Deactivate this company?')">Deactivate</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr><th style="width:200px">Company Code</th><td><?= htmlspecialchars($company['company_code']) ?></td></tr>
                    <tr><th>GST Number</th><td><?= htmlspecialchars($company['gst_number'] ?: '-') ?></td></tr>
                    <tr><th>PAN Number</th><td><?= htmlspecialchars($company['pan_number'] ?: '-') ?></td></tr>
                    <tr><th>Address</th><td><?= nl2br(htmlspecialchars($company['address'] ?: '-')) ?></td></tr>
                    <tr><th>City</th><td><?= htmlspecialchars($company['city'] ?: '-') ?></td></tr>
                    <tr><th>State</th><td><?= htmlspecialchars($company['state'] ?: '-') ?></td></tr>
                    <tr><th>Pincode</th><td><?= htmlspecialchars($company['pincode'] ?: '-') ?></td></tr>
                    <tr><th>Country</th><td><?= htmlspecialchars($company['country'] ?: '-') ?></td></tr>
                    <tr><th>Phone</th><td><?= htmlspecialchars($company['phone'] ?: '-') ?></td></tr>
                    <tr><th>Email</th><td><?= htmlspecialchars($company['email'] ?: '-') ?></td></tr>
                    <tr><th>Website</th><td><?= htmlspecialchars($company['website'] ?: '-') ?></td></tr>
                    <tr><th>Founded Date</th><td><?= formatDate($company['founded_date']) ?></td></tr>
                    <tr><th>Industry Type</th><td><?= htmlspecialchars($company['industry_type'] ?: '-') ?></td></tr>
                    <tr><th>Registration Number</th><td><?= htmlspecialchars($company['registration_number'] ?: '-') ?></td></tr>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="mb-3">No active company profile found.</p>
                <?php if (hasPermission('company', 'create')): ?>
                    <a href="?action=add" class="btn btn-primary">Create Company Profile</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3>All Companies</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr><th>Name</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['company_name']) ?></td>
                            <td><?= getStatusBadge($c['is_active'] ? 'active' : 'inactive') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($companies)): ?>
                        <tr><td colspan="2" class="text-center">No companies found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
