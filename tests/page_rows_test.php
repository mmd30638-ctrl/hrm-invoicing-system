<?php

function ame_rows_fail(string $msg): void
{
    fwrite(STDERR, "FAIL {$msg}\n");
    exit(1);
}

$root = dirname(__DIR__) . '/deploy/public_html';
$receipt = (string) file_get_contents($root . '/modules/customers/receipt.php');
$list = (string) file_get_contents($root . '/modules/suppliers/payments-list.php');
$report = (string) file_get_contents($root . '/modules/returns/return_report.php');

if (!str_contains($receipt, 'grid-template-columns: 1fr;') || !str_contains($receipt, 'data-k="টাকা"')) {
    ame_rows_fail('customer receipt lines are not stacked left to right');
}
if (!str_contains($receipt, 'class="cpay-reason"') || !str_contains($receipt, '#cpayReverse .ame-pay-sec') || !str_contains($receipt, 'actions/process_payment_reverse.php')) {
    ame_rows_fail('the marked cancel box is not a left-right row');
}
if (!str_contains($receipt, 'number_format($pay_amount, 2)') || !str_contains($receipt, 'ame_customer_pay_memo_ledger($previous_balance, $pay_relief, $current_balance)')) {
    ame_rows_fail('customer receipt amounts changed');
}
foreach ([
    'ame_spay_history($db, $filters)',
    "number_format((float)\$p['amount_bdt'], 2)",
    "number_format((float)\$p['amount'], 2)",
    'class="spay spay-list"',
] as $needle) {
    if (!str_contains($list, $needle)) {
        ame_rows_fail('payment history missing: ' . $needle);
    }
}
if (preg_match('/\b(UPDATE|INSERT|DELETE)\b/i', $list)) {
    ame_rows_fail('payment history writes an amount');
}
foreach ([
    'SELECT SUM(total_return_amount) as total, COUNT(*) as c FROM sale_returns',
    'number_format($total_return_amount, 2)',
    'number_format($this_month_return, 2)',
    "number_format((float)(\$p['total_qty'] ?? 0), 2)",
    "number_format((float)(\$p['total_amount'] ?? 0), 2)",
    'class="rr-line rr-line-sum"',
] as $needle) {
    if (!str_contains($report, $needle)) {
        ame_rows_fail('return report missing: ' . $needle);
    }
}
if (preg_match('/\b(UPDATE|INSERT|DELETE)\b/i', $report)) {
    ame_rows_fail('return report writes an amount');
}

echo "OK lengthwise pages\n";
