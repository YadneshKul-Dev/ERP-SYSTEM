<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$enc = Encryption::getInstance();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$search = sanitize($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'reset_password' && $id > 0 && hasPermission('users', 'edit')) {
        $newHash = $enc->hashPassword('reset@123');
        $db->update('users', ['password_hash' => $newHash], 'id = ?', [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'reset_password', 'users', 'Reset password for user ID ' . $id);
        showSuccess('Password reset to reset@123 successfully.');
        header('Location: users.php');
        exit;
    }

    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $role_id = (int)($_POST['role_id'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    if (empty($username)) {
        showError('Username is required.');
        header('Location: users.php');
        exit;
    }

    $existing = $db->fetchOne("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id ?: 0]);
    if ($existing) {
        showError('Username already exists.');
        header('Location: users.php');
        exit;
    }

    if ($id > 0) {
        $userData = [
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'role_id' => $role_id,
            'is_active' => $is_active
        ];
        if (!empty($password)) {
            $userData['password_hash'] = $enc->hashPassword($password);
        }
        $db->update('users', $userData, 'id = ?', [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'update', 'users', 'Updated user ' . $username);
        showSuccess('User updated successfully.');
    } else {
        if (empty($password)) {
            showError('Password is required for new users.');
            header('Location: users.php');
            exit;
        }
        $empCode = $enc->generateEmployeeCode('USR');
        $db->insert('users', [
            'username' => $username,
            'password_hash' => $enc->hashPassword($password),
            'employee_code' => $empCode,
            'email' => $email,
            'phone' => $phone,
            'role_id' => $role_id,
            'is_active' => $is_active
        ]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'create', 'users', 'Created user ' . $username);
        showSuccess('User created successfully. Employee Code: ' . $empCode);
    }
    header('Location: users.php');
    exit;
}

if ($action === 'delete' && $id > 0 && hasPermission('users', 'delete')) {
    $user = $db->fetchOne("SELECT username FROM users WHERE id = ?", [$id]);
    if ($user) {
        $db->delete('users', 'id = ?', [$id]);
        $db->logActivity(getUserId(), getEmployeeCode(), 'delete', 'users', 'Deleted user ' . $user['username']);
        showSuccess('User deleted successfully.');
    }
    header('Location: users.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
requirePermission('users');

$roles = $db->fetchAll("SELECT id, role_name FROM roles ORDER BY role_name");
$editUser = null;
if ($action === 'edit' && $id > 0) {
    $editUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where = '';
$params = [];
if ($search) {
    $where = "WHERE (u.username LIKE ? OR u.email LIKE ? OR u.employee_code LIKE ? OR u.phone LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
$total = $db->fetchColumn("SELECT COUNT(*) FROM users u $where", $params);
$offset = ($page - 1) * $perPage;
$users = $db->fetchAll(
    "SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
?>

<div class="page-header">
    <h1 class="page-title">User Management</h1>
    <?php if (hasPermission('users', 'create')): ?>
        <a href="?action=add" class="btn btn-primary">Add User</a>
    <?php endif; ?>
</div>

<div class="search-bar">
    <form method="get" class="form-inline">
        <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="users.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card-header">
        <h3><?= $action === 'edit' ? 'Edit' : 'Add' ?> User</h3>
        <a href="users.php" class="btn btn-sm btn-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="post" class="col-md-6">
            <div class="form-group">
                <label>Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Password <?= $action === 'edit' ? '(leave blank to keep current)' : '<span class="text-danger">*</span>' ?></label>
                <input type="password" name="password" class="form-control" <?= $action === 'add' ? 'required' : '' ?>>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role_id" class="form-control">
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>" <?= (isset($editUser['role_id']) && $editUser['role_id'] == $role['id']) ? 'selected' : '' ?>><?= htmlspecialchars($role['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1" <?= (!isset($editUser['is_active']) || $editUser['is_active']) ? 'checked' : '' ?>> Active
                </label>
            </div>
            <div class="action-bar">
                <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Update' : 'Create' ?> User</button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="card-header"><h3>All Users</h3></div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Employee Code</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['employee_code']) ?></td>
                    <td><?= htmlspecialchars($user['email'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($user['role_name'] ?: '-') ?></td>
                    <td><?= getStatusBadge($user['is_active'] ? 'active' : 'inactive') ?></td>
                    <td><?= formatDateTime($user['last_login']) ?></td>
                    <td class="action-cell">
                        <?php if (hasPermission('users', 'edit')): ?>
                            <a href="?action=edit&id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                            <a href="?action=reset_password&id=<?= $user['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Reset password to reset@123?')">Reset Pwd</a>
                        <?php endif; ?>
                        <?php if (hasPermission('users', 'delete')): ?>
                            <a href="?action=delete&id=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?')">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="7" class="text-center">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer">
        <?= paginate($total, $page, $perPage) ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
