<?php
require_once __DIR__ . '/../../includes/header.php';
requirePermission('settings');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_name = sanitize($_POST['app_name'] ?? '');
    if (!empty($app_name)) {
        $existing = $db->fetchOne("SELECT key_value FROM system_config WHERE key_name = 'app_name'");
        if ($existing) {
            $db->update('system_config', ['key_value' => $app_name], "key_name = 'app_name'");
        } else {
            $db->insert('system_config', ['key_name' => 'app_name', 'key_value' => $app_name]);
        }
        $db->logActivity(getUserId(), getEmployeeCode(), 'update', 'settings', 'Changed application name to ' . $app_name);
        showSuccess('Application name updated successfully.');
    } else {
        showError('Application name cannot be empty.');
    }
    header('Location: settings.php');
    exit;
}

$appNameConfig = $db->fetchOne("SELECT key_value FROM system_config WHERE key_name = 'app_name'");
$currentAppName = $appNameConfig['key_value'] ?? APP_NAME;

$dbSize = $db->getTableSize();
$dbSizeFormatted = $dbSize ? round($dbSize / 1024, 2) . ' KB' : 'N/A';

$userCount = $db->fetchColumn("SELECT COUNT(*) FROM users");
$empCount = $db->fetchColumn("SELECT COUNT(*) FROM employees WHERE is_active = 1");
$deptCount = $db->fetchColumn("SELECT COUNT(*) FROM departments WHERE is_active = 1");
$roleCount = $db->fetchColumn("SELECT COUNT(*) FROM roles");
?>

<div class="page-header">
    <h1 class="page-title">System Settings</h1>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3>Application Settings</h3>
            </div>
            <div class="card-body">
                <form method="post" class="col-md-8">
                    <div class="form-group">
                        <label>Application Name</label>
                        <input type="text" name="app_name" class="form-control" value="<?= htmlspecialchars($currentAppName) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3>Database Backup</h3>
            </div>
            <div class="card-body">
                <p>Create a backup of the entire database including all encrypted data.</p>
                <a href="../backup/index.php" class="btn btn-success">Go to Backup & Restore</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3>System Information</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr><th style="width:200px">Application</th><td><?= APP_NAME ?> v<?= APP_VERSION ?></td></tr>
                    <tr><th>PHP Version</th><td><?= phpversion() ?></td></tr>
                    <tr><th>Database</th><td>MySQL</td></tr>
                    <tr><th>Database Size</th><td><?= $dbSizeFormatted ?></td></tr>
                    <tr><th>Server Time</th><td><?= date('d-m-Y H:i:s') ?></td></tr>
                    <tr><th>Timezone</th><td><?= TIMEZONE ?></td></tr>
                    <tr><th>Total Users</th><td><?= $userCount ?></td></tr>
                    <tr><th>Active Employees</th><td><?= $empCount ?></td></tr>
                    <tr><th>Departments</th><td><?= $deptCount ?></td></tr>
                    <tr><th>Roles</th><td><?= $roleCount ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
