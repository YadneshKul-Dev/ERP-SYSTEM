<?php
require_once __DIR__ . '/../../includes/header.php';
requirePermission('crm');

$db = Database::getInstance();

// Stats
$totalCustomers = $db->fetchColumn("SELECT COUNT(*) FROM customers");
$totalLeads = $db->fetchColumn("SELECT COUNT(*) FROM leads");
$leadsByStatus = $db->fetchAll("SELECT status, COUNT(*) as count FROM leads GROUP BY status");

// Recent leads
$recentLeads = $db->fetchAll("SELECT * FROM leads ORDER BY created_at DESC LIMIT 10");
?>

<div class="page-header">
    <h1 class="page-title">CRM Dashboard</h1>
    <p class="page-subtitle">Customer Relationship Management Overview</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-primary">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $totalCustomers ?></span>
            <span class="stat-label">Total Customers</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $totalLeads ?></span>
            <span class="stat-label">Total Leads</span>
        </div>
    </div>
    <?php foreach ($leadsByStatus as $ls): ?>
    <div class="stat-card">
        <div class="stat-icon bg-info">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $ls['count'] ?></span>
            <span class="stat-label"><?= ucfirst($ls['status']) ?> Leads</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h3>Recent Leads</h3>
        <a href="leads.php" class="btn btn-sm btn-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Lead Code</th>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Phone</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLeads)): ?>
                <tr><td colspan="8" class="text-center">No leads found</td></tr>
                <?php else: ?>
                <?php foreach ($recentLeads as $lead): ?>
                <tr>
                    <td><?= htmlspecialchars($lead['lead_code']) ?></td>
                    <td><?= htmlspecialchars($lead['lead_name']) ?></td>
                    <td><?= htmlspecialchars($lead['company_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($lead['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($lead['source'] ?? '-') ?></td>
                    <td><?= getStatusBadge($lead['status']) ?></td>
                    <td><?= htmlspecialchars($lead['assigned_to'] ?? '-') ?></td>
                    <td><?= formatDateTime($lead['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
