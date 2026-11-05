<?php
/**
 * Migration: Add scope to role permissions
 *
 * Transforms the permissions JSON from the old format:
 *   {"module": ["action1", "action2"]}
 * to the new format:
 *   {"module": {"actions": ["action1", "action2"], "scope": "all"}}
 *
 * Safe to run multiple times — idempotent.
 */
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
$pdo = $db->getPdo();

echo "=== Scope Permissions Migration ===\n\n";

$roles = $db->fetchAll("SELECT id, role_name, permissions FROM roles ORDER BY id");
echo "Found " . count($roles) . " roles to check.\n\n";

$updated = 0;
$skipped = 0;

foreach ($roles as $role) {
    $perms = json_decode($role['permissions'], true);
    if (!is_array($perms)) {
        echo "  SKIP role #{$role['id']} '{$role['role_name']}': invalid JSON\n";
        $skipped++;
        continue;
    }

    $changed = false;
    $newPerms = [];

    foreach ($perms as $module => $value) {
        // Already in new format with actions and scope
        if (is_array($value) && isset($value['actions']) && is_array($value['actions'])) {
            if (!isset($value['scope'])) {
                $value['scope'] = 'all';
                $changed = true;
            }
            $newPerms[$module] = $value;
            continue;
        }

        // Old format: flat indexed array of action strings
        if (is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
            $newPerms[$module] = [
                'actions' => $value,
                'scope' => 'all'
            ];
            $changed = true;
            continue;
        }

        // Unexpected format — keep as-is but warn
        echo "  WARN role #{$role['id']} module '{$module}': unexpected format, skipping\n";
        $newPerms[$module] = $value;
    }

    if ($changed) {
        $db->update('roles', ['permissions' => json_encode($newPerms)], 'id = ?', [$role['id']]);
        echo "  UPDATE role #{$role['id']} '{$role['role_name']}' — added scope=all\n";
        $updated++;
    } else {
        $skipped++;
    }
}

echo "\nDone. $updated roles updated, $skipped roles unchanged.\n";
