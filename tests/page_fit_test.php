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
$header = (string) file_get_contents($root . '/partials/header.php');
$menu = (string) file_get_contents($root . '/partials/sidebar-shortcuts.php');
$css = (string) file_get_contents($root . '/assets/css/ame-page-fit.css');

if (!str_contains($footer, 'page_fit.php') || !str_contains($partial, 'ame-page-fit.js')) {
    ame_fit_fail('the fit control is not on the shared pages');
}
if (!str_contains($partial, 'page_layout.php')) {
    ame_fit_fail('the page does not call the layout store');
}
if (!str_contains($menu, 'id="amePageFitToggle"') || !str_contains($menu, 'পেজ সাজান') || !str_contains($menu, 'sb-quick')) {
    ame_fit_fail('the arrange switch is not in the left icon list');
}
if (!str_contains($js, 'isRunningText') || !str_contains($js, 'keepTicker') || !str_contains($css, 'ame-tick-keep')) {
    ame_fit_fail('the running text is not kept after reload');
}
if (str_contains($header, 'hdr-i-fit') || str_contains($header, 'ameHdrMore')) {
    ame_fit_fail('the arrange switch is still in the top icon row');
}
foreach (['localStorage.setItem', 'সেভ ও লক', 'amePageFitToggle', 'ame-page-fit:', 'AME_PAGE_FIT', 'bar.hidden = true'] as $needle) {
    if (!str_contains($js, $needle)) {
        ame_fit_fail('fit script missing: ' . $needle);
    }
}
if (!str_contains($css, 'translate(-50%, -50%)') || !str_contains($css, '.ame-fit-bar[hidden]')) {
    ame_fit_fail('the arrange panel is not centered or cannot hide');
}
if (!str_contains($js, 'সাদা অংশ') || !str_contains($js, 'data-ame-sheet') || !str_contains($js, "mode === 'w'") || !str_contains($js, "maxWidth = 'none'")) {
    ame_fit_fail('the white background cannot be resized sideways');
}
if (!str_contains($js, 'restoreOriginal') || !str_contains($js, 'data-ame-bit')) {
    ame_fit_fail('reset or overflowing text is missing');
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
