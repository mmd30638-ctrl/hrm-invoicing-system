<?php
function is_en(): bool
{
    return true;
}
require_once dirname(__DIR__) . '/deploy/public_html/includes/memo_lang.php';
$enDate = ame_ui_date(strtotime('2026-11-11'));
if ($enDate !== '11 November 2026') {
    fwrite(STDERR, "date {$enDate}\n");
    exit(1);
}
if (ame_ui_sale_type('retail') !== 'Retail' || ame_ui_sale_type('খুচরা') !== 'Retail') {
    fwrite(STDERR, "sale type\n");
    exit(1);
}
if (ame_ui('হোয়াটসঅ্যাপ', 'WhatsApp') !== 'WhatsApp') {
    fwrite(STDERR, "button\n");
    exit(1);
}
echo "OK en\n";
