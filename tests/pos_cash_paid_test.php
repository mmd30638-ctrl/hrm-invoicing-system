<?php

function ame_cash_expect(bool $ok, string $note): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL {$note}\n");
        exit(1);
    }
}

$root = dirname(__DIR__) . '/deploy/public_html';
$pos = (string)file_get_contents($root . '/modules/sales/pos_workspace.php');
$js = (string)file_get_contents($root . '/assets/js/pos-cash-paid.js');
$css = (string)file_get_contents($root . '/assets/css/pos-cash-paid.css');
$footer = (string)file_get_contents($root . '/partials/footer.php');
$invoice = (string)file_get_contents($root . '/modules/sales/invoice.php');

$cashAt = strpos($pos, 'id="amePosCashPaid"');
$completeAt = strpos($pos, 'id="amePosRecentToggle"');
ame_cash_expect($cashAt !== false && $completeAt !== false && $cashAt < $completeAt, 'the cash box is before the complete box');
ame_cash_expect(str_contains($pos, 'ame-pos-complete-row'), 'the cash box shares a row with the complete box');
ame_cash_expect(str_contains($css, 'flex-direction: row'), 'the cash box sits on the left of the complete box');
ame_cash_expect(str_contains($footer, 'pos-cash-paid.css'), 'the cash box style loads on the sales desk');
ame_cash_expect(str_contains($footer, 'pos-cash-paid.js'), 'the cash box control loads on the sales desk');
ame_cash_expect(str_contains($js, 'dataset.saleBill'), 'the paid amount comes from the existing sale bill');
ame_cash_expect(str_contains($js, 'ameExchangeTotal'), 'an exchange already on the sale is kept');
ame_cash_expect(str_contains($js, 'submitFinalSale'), 'the existing sale completion is used');
ame_cash_expect(str_contains($js, 'share=1'), 'the finished memo opens the share sheet');
ame_cash_expect(str_contains($invoice, 'amePosCashShare'), 'the invoice can open share after this cash completion');
ame_cash_expect(str_contains($js, "getElementById('discount')") === false, 'the cash box does not rewrite the discount');
ame_cash_expect(str_contains($js, "getElementById('sub-total')") === false, 'the cash box does not rewrite the subtotal');
ame_cash_expect(str_contains($js, 'customer_id') === false, 'the cash box does not change the customer balance');
ame_cash_expect(str_contains($js, 'Registered'), 'a registered customer is asked before the cash shortcut');
ame_cash_expect(str_contains($js, 'এই কাস্টমারের সম্পূর্ণ টাকা কি পরিশোধ হয়েছে?'), 'the cash shortcut asks whether the full amount is paid');
ame_cash_expect(str_contains($css, 'ame-cash-ask-bubble'), 'the question is shown as a designed message');
ame_cash_expect(str_contains($css, 'ame-cash-ask-head'), 'the cash message has a colored header');
ame_cash_expect(str_contains($js, 'ame-cash-ask-mark'), 'the cash message carries a colored mark');
ame_cash_expect(str_contains($invoice, 'memo-customer-name'), 'the memo customer name can be drawn heavier');
$ink = (string)file_get_contents($root . '/assets/css/memo-clear.css');
ame_cash_expect(str_contains($ink, '#memo-container .memo-customer-name'), 'the memo customer name has its own color');
ame_cash_expect(str_contains($ink, '#9F1239'), 'the customer name color is separate from the other lines');
ame_cash_expect(str_contains($invoice, 'number_format($paid_amount, 2)'), 'the paid figure on the memo is unchanged');

echo "OK cash paid complete\n";
