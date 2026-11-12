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
ame_dock_expect(str_contains($css, 'translateX(110%)'), 'the payment panel starts tucked on the right');
ame_dock_expect(str_contains($css, 'grid-template-columns: minmax(0, 1fr) !important'), 'the cart keeps the width while payment is tucked');
ame_dock_expect(str_contains($js, "getElementById('amePosPay')"), 'the dock uses the existing payment panel');
ame_dock_expect(str_contains($js, "setProperty(prop, value, 'important')"), 'the dock styles win over the sales lock sheet');
ame_dock_expect(str_contains($js, 'appendChild(pay)') === false, 'the payment panel stays inside the sales desk');
ame_dock_expect(str_contains($js, "setStyle(node, 'overflow', 'visible')"), 'the drawer is not clipped by the sales page');
ame_dock_expect(str_contains($css, 'max-width: 16px !important'), 'payment icons stay the small heading size');
ame_dock_expect(str_contains($js, "'900000'"), 'the payment panel sits above the sales toolbar');
ame_dock_expect(str_contains($css, 'z-index: 900000 !important'), 'the payment panel stays above the customer bar');
ame_dock_expect(str_contains($js, 'translateX(110%)'), 'a closed panel stays fully off the right edge');
ame_dock_expect(str_contains($js, 'deskPaid') === false, 'the dock does not rewrite the paid amount');
ame_dock_expect(str_contains($js, 'calculateAll') === false, 'the dock does not recalculate the sale');
ame_dock_expect(str_contains($footer, 'pos-pay-dock.css'), 'the dock style loads with the shop');
ame_dock_expect(str_contains($footer, 'pos-pay-dock.js'), 'the dock control loads with the shop');
ame_dock_expect(str_contains($pos, "getElementById('deskPayArrow')"), 'the second-account arrow stays in place');

$version = trim((string)file_get_contents($root . '/VERSION.txt'));
ame_dock_expect($version === '10.98.810', 'the update version is 10.98.810');

echo "OK pos payment dock\n";
