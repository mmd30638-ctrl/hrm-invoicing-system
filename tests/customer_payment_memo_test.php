<?php

function ame_cpay_memo_fail(string $msg): void
{
    fwrite(STDERR, "FAIL {$msg}\n");
    exit(1);
}

$root = dirname(__DIR__) . '/deploy/public_html';
$receipt = (string) file_get_contents($root . '/modules/customers/receipt.php');

foreach ([
    '$pay_relief = $pay_amount + $pay_discount',
    'ame_customer_pay_memo_ledger($previous_balance, $pay_relief, $current_balance)',
    'number_format($pay_amount, 2)',
    'number_format($pay_discount, 2)',
    'number_format($pay_relief, 2)',
    'number_format($remain_due, 2)',
    'number_format($advance, 2)',
    "ame_memo_paper_money((float)\$pay_ledger['previous'], true)",
    "\$pay_ledger['formula']",
    '$pay_amount = (float)($payment[\'amount\'] ?? 0)',
] as $needle) {
    if (!str_contains($receipt, $needle)) {
        ame_cpay_memo_fail('payment memo amount missing: ' . $needle);
    }
}
if (!str_contains($receipt, 'id="memo-container"') || !str_contains($receipt, 'memo-customer-name') || !str_contains($receipt, 'memo-clear.css')) {
    ame_cpay_memo_fail('customer payment memo is not using the sales memo colors');
}
if (!str_contains($receipt, "ameMemoCapture(act, fileBase, 'memo-container')")) {
    ame_cpay_memo_fail('customer payment memo does not share through the sales memo capture');
}

echo "OK customer payment memo\n";
