<?php

require_once __DIR__ . '/../deploy/public_html/includes/engineer_bill_lock.php';

function ame_eng_expect(bool $ok, string $note): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL {$note}\n");
        exit(1);
    }
}

$two = [
    ['id' => 4, 'name' => 'করিম'],
    ['id' => 9, 'name' => 'রহিম'],
];
ame_eng_expect(ame_engineer_bill_pick_id($two, 'রহিম') === 9, 'name match picks that engineer');
ame_eng_expect(ame_engineer_bill_pick_id($two, '') === 0, 'two engineers stay separate');
ame_eng_expect(ame_engineer_bill_pick_id([['id' => 3, 'name' => 'করিম']], '') === 3, 'one engineer is used');
ame_eng_expect(ame_engineer_bill_lock_protects('includes/engineer_bill_lock.php'), 'lock file stays protected');
ame_eng_expect(!ame_engineer_bill_lock_protects('modules/sales/invoice.php'), 'sales memo stays editable');
ame_eng_expect(!ame_engineer_bill_lock_protects('includes/running_ledger.php'), 'customer ledger stays on its own lock');

$root = dirname(__DIR__) . '/deploy/public_html';
$lock = (string)file_get_contents($root . '/includes/engineer_bill_lock.php');
ame_eng_expect(!str_contains($lock, 'UPDATE customers'), 'lock does not rewrite customers');
ame_eng_expect(!str_contains($lock, 'UPDATE suppliers'), 'lock does not rewrite suppliers');
ame_eng_expect(str_contains($lock, 'service_commissions'), 'bills are posted to the engineer list');

$hrm = (string)file_get_contents($root . '/includes/hrm_functions.php');
ame_eng_expect(str_contains($hrm, "function_exists('ame_engineer_bill_record')"), 'sale posting uses the lock');
ame_eng_expect(str_contains($hrm, 'WHERE id = ?'), 'engineer sync no longer renames every row for one employee');
ame_eng_expect(str_contains($hrm, 'ss.engineer_id IN (SELECT id FROM engineers WHERE employee_id = ?)'), 'work list includes every engineer of that employee');

$finance = (string)file_get_contents($root . '/includes/sales/sale_finance.php');
ame_eng_expect(!str_contains($finance, "status = 'active'"), 'a real engineer is accepted even if the flag is off');

$desk = (string)file_get_contents($root . '/includes/worker_desk.php');
ame_eng_expect(str_contains($desk, 'ss.engineer_id IN (SELECT id FROM engineers WHERE employee_id = ?)'), 'history follows the engineer id');
ame_eng_expect(str_contains($desk, "bind_param('ssiis'"), 'history binds the engineer employee twice');

$pos = (string)file_get_contents($root . '/modules/sales/pos_workspace.php');
ame_eng_expect(!str_contains($pos, "throw new Error('AME_DUTY:"), 'a chosen engineer bill is not dropped');
ame_eng_expect(str_contains($pos, 'ame_engineer_bill_expand_roster'), 'every engineer is on the picker');

$easy = (string)file_get_contents($root . '/includes/easy_update_functions.php');
ame_eng_expect(str_contains($easy, 'ame_engineer_bill_lock_protects'), 'later zips skip the locked file');

echo "OK engineer bill lock\n";
