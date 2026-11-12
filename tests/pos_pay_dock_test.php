<?php

function ame_dock_expect(bool $ok, string $note): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL {$note}\n");
        exit(1);
    }
}

$root = dirname(__DIR__) . '/deploy/public_html';
$css = (string)file_get_contents($root . '/assets/css/pos-pay-dock.css');
$js = (string)file_get_contents($root . '/assets/js/pos-pay-dock.js');
$footer = (string)file_get_contents($root . '/partials/footer.php');
$pos = (string)file_get_contents($root . '/modules/sales/pos_workspace.php');

ame_dock_expect(str_contains($css, '#amePosPay.is-open'), 'the payment panel can slide open');
ame_dock_expect(str_contains($css, 'translateX(105%)'), 'the payment panel starts tucked on the right');
ame_dock_expect(str_contains($css, 'grid-template-columns: minmax(0, 1fr) !important'), 'the cart keeps the width while payment is tucked');
ame_dock_expect(str_contains($js, "getElementById('amePosPay')"), 'the dock uses the existing payment panel');
ame_dock_expect(str_contains($js, 'deskPaid') === false, 'the dock does not rewrite the paid amount');
ame_dock_expect(str_contains($js, 'calculateAll') === false, 'the dock does not recalculate the sale');
ame_dock_expect(str_contains($footer, 'pos-pay-dock.css'), 'the dock style loads with the shop');
ame_dock_expect(str_contains($footer, 'pos-pay-dock.js'), 'the dock control loads with the shop');
ame_dock_expect(str_contains($pos, "getElementById('deskPayArrow')"), 'the second-account arrow stays in place');

$version = trim((string)file_get_contents($root . '/VERSION.txt'));
ame_dock_expect($version === '10.98.807', 'the update version is 10.98.807');

echo "OK pos payment dock\n";
