<?php
require_once __DIR__ . '/../../init.php';
$db = Database::getInstance();
$enc = Encryption::getInstance();

$empCode = sanitize($_GET['employee_code'] ?? '');

if ($empCode) {
    $emp = $db->fetchOne("SELECT * FROM employees WHERE employee_code = ?", [$empCode]);
    if (!$emp) {
        header('Location: ?');
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';

if (!$empCode) {
    requirePermission('id_card', 'view');
    $employees = $db->fetchAll("SELECT employee_code, first_name, last_name, department, designation FROM employees WHERE is_active = 1 ORDER BY first_name");
    ?>
    <div class="page-header">
        <h1 class="page-title">ID Card Generator</h1>
        <p class="page-subtitle">Generate and print employee ID cards</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="form-row">
                <div class="form-group col-md-8">
                    <label>Select Employee</label>
                    <select name="employee_code" class="form-select" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= htmlspecialchars($e['employee_code']) ?>"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?> (<?= htmlspecialchars($e['employee_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn btn-primary">Generate ID Card</button>
                </div>
            </form>
        </div>
    </div>
    <?php
} else {
    if (!hasPermission('id_card', 'view') && $empCode !== Auth::getInstance()->getEmployeeCode()) {
        requirePermission('id_card', 'view');
    }

    $company = $db->fetchOne("SELECT * FROM companies LIMIT 1");
    $companyName = $company['company_name'] ?? APP_NAME;
    $companyLogo = $company['logo'] ?? '';

    $fullName = $emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name'];
    $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
    ?>
    <div class="page-header no-print">
        <h1 class="page-title">ID Card</h1>
        <p class="page-subtitle"><?= htmlspecialchars($fullName) ?></p>
    </div>

    <div class="action-bar no-print">
        <button onclick="window.print()" class="btn btn-primary">Print ID Card</button>
        <a href="?" class="btn btn-secondary">Back to Selection</a>
    </div>

    <div class="id-card-container">
        <div class="id-card">
            <div class="id-card-header">
                <div class="company-info">
                    <?php if ($companyLogo && file_exists(__DIR__ . '/../../' . $companyLogo)): ?>
                    <img src="<?= htmlspecialchars($companyLogo) ?>" alt="Logo" class="company-logo" style="height:50px;">
                    <?php endif; ?>
                    <h2><?= htmlspecialchars($companyName) ?></h2>
                </div>
            </div>
            <div class="id-card-body">
                <div class="id-card-photo">
                    <?php if ($emp['passport_photo'] && file_exists(__DIR__ . '/../../' . $emp['passport_photo'])): ?>
                    <img src="<?= htmlspecialchars($emp['passport_photo']) ?>" alt="Photo">
                    <?php else: ?>
                    <div class="photo-placeholder"><?= $initials ?></div>
                    <?php endif; ?>
                </div>
                <div class="id-card-info">
                    <div class="id-card-code"><?= htmlspecialchars($emp['employee_code']) ?></div>
                    <h3 class="employee-name"><?= htmlspecialchars($fullName) ?></h3>
                    <p class="employee-dept"><strong>Department:</strong> <?= htmlspecialchars($emp['department'] ?? '-') ?></p>
                    <p class="employee-desg"><strong>Designation:</strong> <?= htmlspecialchars($emp['designation'] ?? '-') ?></p>
                    <p class="employee-blood"><strong>Blood Group:</strong> <?= htmlspecialchars($emp['blood_group'] ?? '-') ?></p>
                    <p class="employee-doj"><strong>Date of Joining:</strong> <?= formatDate($emp['date_of_joining']) ?></p>
                </div>
            </div>
            <div class="id-card-footer">
                <div class="signature-area">
                    <p>Employee Signature</p>
                    <div class="signature-line"></div>
                </div>
                <div class="card-dates">
                    <p><strong>Issue Date:</strong> <?= formatDate(date('Y-m-d')) ?></p>
                    <p><strong>Expiry Date:</strong> <?= formatDate(date('Y-m-d', strtotime('+3 years'))) ?></p>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
            .sidebar, .top-bar, .main-footer { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .id-card { border: 2px solid #000 !important; box-shadow: none !important; }
        }
    </style>
    <?php
}

require_once __DIR__ . '/../../includes/footer.php'; ?>
