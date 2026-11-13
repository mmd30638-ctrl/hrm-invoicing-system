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
if (!str_contains($partial, 'page_layout.php')) {
    ame_fit_fail('the page does not call the layout store');
}
foreach (['localStorage.setItem', 'সেভ ও লক', "type = 'button'", 'ame-page-fit:', 'AME_PAGE_FIT'] as $needle) {
    if (!str_contains($js, $needle)) {
        ame_fit_fail('fit script missing: ' . $needle);
    }
}
if (preg_match('/process_payment|customer_payments|receivable_balance|UPDATE\s+|INSERT\s+/i', $js)) {
    ame_fit_fail('the fit tool writes business data');
}
$api = (string) file_get_contents($root . '/modules/settings/actions/page_layout.php');
foreach (['ame_page_layouts', 'layout_json', 'user_id = ?', 'X-CSRF-Token'] as $needle) {
    if (!str_contains($api, $needle) && $needle !== 'X-CSRF-Token') {
        ame_fit_fail('layout store missing: ' . $needle);
    }
}
if (!str_contains($api, 'HTTP_X_CSRF_TOKEN')) {
    ame_fit_fail('layout save is not checked');
}
if (preg_match('/customer_payments|receivable_balance|payable_balance|customers\b|suppliers\b/i', $api)) {
    ame_fit_fail('layout store touches a money table');
}

echo "OK page fit\n";
