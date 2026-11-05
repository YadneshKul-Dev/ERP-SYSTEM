<?php
require_once __DIR__ . '/../../init.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$db = Database::getInstance();

switch ($action) {
    case 'get_account':
        $a = $db->fetchOne("SELECT * FROM chart_of_accounts WHERE id = ?", [(int)$_GET['id']]);
        echo json_encode($a ?: []);
        break;

    case 'get_ledger':
        $accountCode = $_GET['account_code'] ?? '';
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-1 year'));
        $to = $_GET['to'] ?? date('Y-m-d');
        $entries = $db->fetchAll("
            SELECT je.entry_number, je.entry_date, je.description AS je_desc,
                   jei.description AS item_desc, jei.debit_amount, jei.credit_amount
            FROM journal_entry_items jei
            JOIN journal_entries je ON je.id = jei.journal_entry_id
            WHERE jei.account_code = ? AND je.status = 'posted'
              AND je.entry_date BETWEEN ? AND ?
            ORDER BY je.entry_date ASC, je.id ASC
        ", [$accountCode, $from, $to]);

        $opening = $db->fetchColumn("
            SELECT SUM(jei.debit_amount) - SUM(jei.credit_amount)
            FROM journal_entry_items jei
            JOIN journal_entries je ON je.id = jei.journal_entry_id
            WHERE jei.account_code = ? AND je.status = 'posted' AND je.entry_date < ?
        ", [$accountCode, $from]) ?: 0;

        echo json_encode(['entries' => $entries, 'opening' => $opening]);
        break;

    case 'get_trial_balance':
        $asOf = $_GET['as_of'] ?? date('Y-m-d');
        $data = $db->fetchAll("
            SELECT coa.account_code, coa.account_name, coa.account_type,
                   COALESCE(SUM(jei.debit_amount), 0) as total_debit,
                   COALESCE(SUM(jei.credit_amount), 0) as total_credit
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_items jei ON jei.account_code = coa.account_code
            LEFT JOIN journal_entries je ON je.id = jei.journal_entry_id AND je.status = 'posted' AND je.entry_date <= ?
            GROUP BY coa.id
            ORDER BY coa.account_code
        ", [$asOf]);
        echo json_encode($data);
        break;

    case 'get_journal':
        $id = (int)$_GET['id'];
        $je = $db->fetchOne("SELECT * FROM journal_entries WHERE id = ?", [$id]);
        $items = $db->fetchAll("SELECT * FROM journal_entry_items WHERE journal_entry_id = ?", [$id]);
        echo json_encode(['entry' => $je, 'items' => $items]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
