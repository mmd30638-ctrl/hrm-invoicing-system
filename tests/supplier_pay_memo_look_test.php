<?php
$root = dirname(__DIR__) . '/deploy/public_html';
$receipt = (string) file_get_contents($root . '/modules/suppliers/receipt.php');
$css = (string) file_get_contents($root . '/assets/css/supplier-settlement.css');

function spay_fail(string $msg): void
{
    fwrite(STDERR, "FAIL {$msg}\n");
    exit(1);
}

foreach ([
    'number_format($ameDueShow, 2)',
    'number_format($amePaidShow, 2)',
    'number_format($ameRemainShow, 2)',
    'number_format($ameAdvanceShow, 2)',
    "number_format((float)\$line['allocated'], 2)",
    '$ledger[\'formula\']',
] as $needle) {
    if (!str_contains($receipt, $needle)) {
        spay_fail('amount display missing: ' . $needle);
    }
}
if (preg_match('/\$ameDueShow\s*[-+*\/]/', $receipt) || preg_match('/\$ledger\[.due.\]\s*-/', $receipt)) {
    spay_fail('receipt recomputes a balance');
}
if (!str_contains($receipt, 'memo-badge') || !str_contains($receipt, 'memo-table') || !str_contains($receipt, 'inv-btn-print')) {
    spay_fail('sales memo design is missing');
}
if (str_contains($receipt, 'ame-paper-80mm')) {
    spay_fail('receipt is still forced onto the thermal roll');
}
if (!str_contains($css, '#F1F5F9') || !str_contains($css, '.memo-badge')) {
    spay_fail('sales colors missing from the supplier stylesheet');
}
if (preg_match('/#spayMemo table thead th,[\s\S]{0,500}background:\s*#0b1e4d/i', $css)) {
    spay_fail('memo table header is still the old navy bar');
}

echo "OK supplier pay memo look\n";
