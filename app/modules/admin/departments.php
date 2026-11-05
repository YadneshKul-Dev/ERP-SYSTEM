<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_name = sanitize($_POST['department_name'] ?? '');
    $department_code = sanitize($_POST['department_code'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $head_employee_code = sanitize($_POST['head_employee_code'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($department_name)) {
        showError('Department name is required.');
        header('Location: departments.php');
        exit;
    }

    if ($id > 0) {
        $db->update('departments', [
            'department_name' => $department_name,
            'department_code' => $department_code,
            'description' => $description,
            'head_employee_code' => $head_employee_code,
            'is_active' => $is_active
        ], 'id = ?', [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'update', 'departments', 'Updated department ' . $department_name);
        showSuccess('Department updated successfully.');
    } else {
        if (empty($department_code)) {
            $count = $db->fetchColumn("SELECT COUNT(*) FROM departments");
            $department_code = 'DEPT-' . strtoupper(substr($department_name, 0, 3)) . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        }
        $db->insert('departments', [
            'department_name' => $department_name,
            'department_code' => $department_code,
            'description' => $description,
            'head_employee_code' => $head_employee_code,
            'is_active' => $is_active
        ]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'departments', 'Created department ' . $department_name);
        showSuccess('Department created successfully. Code: ' . $department_code);
    }
    header('Location: departments.php');
    exit;
}

if ($action === 'delete' && $id > 0 && hasPermission('departments', 'delete')) {
    $dept = $db->fetchOne("SELECT department_name FROM departments WHERE id = ?", [$id]);
    if ($dept) {
        $db->delete('departments', 'id = ?', [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'delete', 'departments', 'Deleted department ' . $dept['department_name']);
        showSuccess('Department deleted successfully.');
    }
    header('Location: departments.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('departments');

$editDept = null;
if (($action === 'edit' || $action === 'view') && $id > 0) {
    $editDept = $db->fetchOne("SELECT * FROM departments WHERE id = ?", [$id]);
}

$departments = $db->fetchAll("SELECT * FROM departments ORDER BY created_at DESC");
?>

<div class="page-header">
    <h1 class="page-title">Departments</h1>
    <?php if (hasPermission('departments', 'create')): ?>
        <a href="?action=add" class="btn btn-primary">Add Department</a>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><h3>All Departments</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($dept['department_name']) ?></strong>
                                <?php if ($dept['description']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($dept['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($dept['department_code']) ?></td>
                            <td><?= getStatusBadge($dept['is_active'] ? 'active' : 'inactive') ?></td>
                            <td class="action-cell">
                                <?php if (hasPermission('departments', 'edit')): ?>
                                    <a href="?action=edit&id=<?= $dept['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <?php endif; ?>
                                <?php if (hasPermission('departments', 'delete')): ?>
                                    <a href="?action=delete&id=<?= $dept['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete department <?= htmlspecialchars($dept['department_name']) ?>?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($departments)): ?>
                        <tr><td colspan="4" class="text-center">No departments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="card">
            <div class="card-header">
                <h3><?= $action === 'edit' ? 'Edit' : 'Add' ?> Department</h3>
                <a href="departments.php" class="btn btn-sm btn-secondary">Back</a>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Department Name <span class="text-danger">*</span></label>
                            <input type="text" name="department_name" class="form-control" value="<?= htmlspecialchars($editDept['department_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Department Code</label>
                            <input type="text" name="department_code" class="form-control" value="<?= htmlspecialchars($editDept['department_code'] ?? '') ?>" placeholder="Auto-generated if empty">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editDept['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Head Employee Code</label>
                            <input type="text" name="head_employee_code" class="form-control" value="<?= htmlspecialchars($editDept['head_employee_code'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="checkbox-label mt-4">
                                <input type="checkbox" name="is_active" value="1" <?= (!isset($editDept['is_active']) || $editDept['is_active']) ? 'checked' : '' ?>> Active
                            </label>
                        </div>
                    </div>
                    <div class="action-bar">
                        <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Update' : 'Create' ?> Department</button>
                        <a href="departments.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
