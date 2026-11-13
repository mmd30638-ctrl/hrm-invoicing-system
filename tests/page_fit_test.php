<?php

function ame_fit_fail(string $msg): void
{
    fwrite(STDERR, "FAIL {$msg}\n");
    exit(1);
}

$root = dirname(__DIR__) . '/deploy/public_html';
$js = (string) file_get_contents($root . '/assets/js/ame-page-fit.js');
$footer = (string) file_get_contents($root . '/partials/footer.php');
$partial = (string) file_get_contents($root . '/partials/page_fit.php');

if (!str_contains($footer, 'page_fit.php') || !str_contains($partial, 'ame-page-fit.js')) {
    ame_fit_fail('the fit control is not on the shared pages');
}
foreach (['localStorage.setItem', 'লক করুন', "type = 'button'", 'ame-page-fit:'] as $needle) {
    if (!str_contains($js, $needle)) {
        ame_fit_fail('fit script missing: ' . $needle);
    }
}
if (preg_match('/process_payment|customer_payments|receivable_balance|UPDATE\s+|INSERT\s+/i', $js)) {
    ame_fit_fail('the fit tool writes business data');
}

echo "OK page fit\n";
