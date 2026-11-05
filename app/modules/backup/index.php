<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $backupFile = $db->backup();
        $db->logActivity(getUserId(), getEmployeeCode(), 'create_backup', 'backup', 'Created backup: ' . basename($backupFile));
        showSuccess('Backup created successfully: ' . basename($backupFile));
    } catch (Exception $e) {
        showError('Backup failed: ' . $e->getMessage());
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    if (!isset($_POST['backup_file']) || empty($_POST['backup_file'])) {
        showError('Please select a backup file to restore.');
    } else {
        $backupFile = sanitize($_POST['backup_file']);
        $fullPath = BACKUP_PATH . '/' . basename($backupFile);
        if (!file_exists($fullPath)) {
            showError('Backup file not found.');
        } else {
            try {
                $db->restore($fullPath);
                $db->logActivity(getUserId(), getEmployeeCode(), 'restore_backup', 'backup', 'Restored backup: ' . basename($fullPath));
                showSuccess('Database has been restored from backup: ' . basename($fullPath));
            } catch (Exception $e) {
                showError('Restore failed: ' . $e->getMessage());
            }
        }
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['backup_file']) || empty($_POST['backup_file'])) {
        showError('Please select a backup file to delete.');
    } else {
        $backupFile = sanitize($_POST['backup_file']);
        $fullPath = BACKUP_PATH . '/' . basename($backupFile);
        if (file_exists($fullPath)) {
            unlink($fullPath);
            $db->logActivity(getUserId(), getEmployeeCode(), 'delete_backup', 'backup', 'Deleted backup: ' . basename($fullPath));
            showSuccess('Backup file deleted: ' . basename($fullPath));
        } else {
            showError('Backup file not found.');
        }
    }
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('backup');

$dbSize = $db->getTableSize();
$dbSizeFormatted = $dbSize > 0 ? number_format($dbSize / 1024, 2) . ' KB' : 'Unknown';

$backupDir = BACKUP_PATH;
$lastBackupDate = '-';
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.db');
    if (!empty($files)) {
        $latest = max(array_map('filemtime', $files));
        $lastBackupDate = date(DATE_FORMAT . ' ' . TIME_FORMAT, $latest);
    }
}

$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.db');
    rsort($files);
    foreach ($files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'size' => filesize($file),
            'date' => date(DATE_FORMAT . ' ' . TIME_FORMAT, filemtime($file))
        ];
    }
}
$backupCount = count($backups);
?>

<div class="page-header">
    <h1 class="page-title">Backup & Restore</h1>
    <p class="page-subtitle">Manage database backups and restoration</p>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Database Info</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Database Size</span>
                        <span class="info-value"><?= $dbSizeFormatted ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Backup</span>
                        <span class="info-value"><?= htmlspecialchars($lastBackupDate) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Backups Available</span>
                        <span class="info-value"><?= $backupCount ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Create Backup</h3>
            </div>
            <div class="card-body">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="create">
                    <button type="submit" class="btn btn-primary">Create New Backup</button>
                </form>
                <p class="mt-3" style="color:#6c757d;font-size:0.875rem;">Creates a complete snapshot of the current database including all records and configurations.</p>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3>Restore Database</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Warning:</strong> Restoring will overwrite the current database with the selected backup. This action cannot be undone. We recommend creating a current backup before restoring.
                </div>
                <?php if ($backupCount > 0): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="restore">
                    <div class="form-group">
                        <label for="backup_file" class="form-label">Select Backup File</label>
                        <select id="backup_file" name="backup_file" class="form-select" required>
                            <option value="">-- Select Backup --</option>
                            <?php foreach ($backups as $bk): ?>
                            <option value="<?= htmlspecialchars($bk['filename']) ?>"><?= htmlspecialchars($bk['filename']) ?> (<?= htmlspecialchars($bk['date']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-danger" onclick="return confirm('WARNING: This will completely overwrite the current database with the selected backup. Are you sure you want to proceed?')">Restore Database</button>
                </form>
                <?php else: ?>
                <p>No backup files available. Create a backup first.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3>Existing Backups (<?= $backupCount ?>)</h3>
            </div>
            <div class="card-body p-0">
                <?php if (empty($backups)): ?>
                <p style="padding:20px;text-align:center;">No backup files found.</p>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $bk): ?>
                        <tr>
                            <td><?= htmlspecialchars($bk['filename']) ?></td>
                            <td><?= number_format($bk['size'] / 1024, 2) ?> KB</td>
                            <td><?= htmlspecialchars($bk['date']) ?></td>
                            <td>
                                <a href="<?= BACKUP_PATH ?>/<?= urlencode($bk['filename']) ?>" class="btn btn-sm btn-info" download>Download</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this backup file? This cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="backup_file" value="<?= htmlspecialchars($bk['filename']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
