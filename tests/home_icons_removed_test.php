<?php

function ame_icon_fail(string $msg): void
{
    fwrite(STDERR, "FAIL {$msg}\n");
    exit(1);
}

$root = dirname(__DIR__) . '/deploy/public_html';
$php = (string) file_get_contents($root . '/includes/visual_design.php');
$js = (string) file_get_contents($root . '/assets/js/page-layout-global.js');
$css = (string) file_get_contents($root . '/assets/css/page-layout-global.css');
$footer = (string) file_get_contents($root . '/partials/footer.php');
$drop = (string) file_get_contents($root . '/PATCH_DELETE.txt');

if (str_contains($php, 'id="vdLaunch"') || str_contains($php, 'visual-design.js') || str_contains($footer, 'vd_editor_html')) {
    ame_icon_fail('the palette button is still printed');
}
if (!str_contains($php, "return '';")) {
    ame_icon_fail('the palette launcher function still builds a button');
}
if (str_contains($js, 'injectIcon') || str_contains($js, "a.id = 'ameLayoutFab'") || str_contains($js, "textContent = '📐'")) {
    ame_icon_fail('the layout button is still created');
}
if (!str_contains($css, '#vdLaunch') || !str_contains($css, '#ameLayoutFab') || !str_contains($css, 'display: none !important')) {
    ame_icon_fail('a leftover button can still show');
}
if (!str_contains($drop, 'assets/js/visual-design.js') || !str_contains($drop, 'assets/css/visual-design.css')) {
    ame_icon_fail('the palette program files are not removed on import');
}
foreach ([$php, $js, $css] as $src) {
    if (preg_match('/customer_payments|receivable_balance|payable_balance|UPDATE\s+customers|UPDATE\s+suppliers/i', $src)) {
        ame_icon_fail('icon removal touches customer or supplier money');
    }
}

echo "OK home icons removed\n";
