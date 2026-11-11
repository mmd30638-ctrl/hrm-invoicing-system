<?php
/**
 * মেমোর ভাষা ও স্পষ্ট লেখা। টাকার খাতা ছোঁয় না।
 */
$root = dirname(__DIR__) . '/deploy/public_html';
require_once $root . '/includes/memo_lang.php';

function memo_clear_fail(string $msg): void
{
    fwrite(STDERR, "FAIL {$msg}\n");
    exit(1);
}

if (ame_ui_bn() !== true) {
    memo_clear_fail('default language is Bengali');
}
$bnDate = ame_ui_date(strtotime('2026-11-11'));
if (!str_contains($bnDate, 'নভেম্বর') || str_contains($bnDate, 'Nov')) {
    memo_clear_fail('Bengali date still has an English month: ' . $bnDate);
}
if (ame_ui_sale_type('retail') !== 'খুচরা') {
    memo_clear_fail('retail must read খুচরা in Bengali');
}
if (ame_ui('হোয়াটসঅ্যাপ', 'WhatsApp') !== 'হোয়াটসঅ্যাপ') {
    memo_clear_fail('Bengali share button');
}

$en = shell_exec('php ' . escapeshellarg(__DIR__ . '/memo_clear_lang_en.php'));
if (!is_string($en) || !str_contains($en, 'OK en')) {
    memo_clear_fail('English selection: ' . trim((string) $en));
}

$js = (string) file_get_contents($root . '/assets/js/ame-memo-file.js');
if (str_contains($js, '#C9A227') || str_contains($js, '#1a1204')) {
    memo_clear_fail('memo capture still paints the blurry gold shadow');
}
if (!str_contains($js, '#9B1C1C') || !str_contains($js, '#0B3A82')) {
    memo_clear_fail('clear colors missing from the memo script');
}
$css = (string) file_get_contents($root . '/assets/css/memo-clear.css');
if (!str_contains($css, 'text-shadow: none') || !str_contains($css, '#9B1C1C')) {
    memo_clear_fail('clear stylesheet missing');
}

$view = (string) file_get_contents($root . '/modules/returns/v2/view.php');
if (str_contains($view, "date('d M Y'") || str_contains($view, '>WhatsApp<') || str_contains($view, '>Telegram<')) {
    memo_clear_fail('return memo still mixes English into the Bengali layout');
}
if (!str_contains($view, 'ame_ui_sale_type') || !str_contains($view, 'ame_ui_date')) {
    memo_clear_fail('return memo does not use the language helper');
}

$invoice = (string) file_get_contents($root . '/modules/sales/invoice.php');
if (str_contains($invoice, '<html lang="bn">') || str_contains($invoice, '>WhatsApp<') || str_contains($invoice, 'color: #4B5563')) {
    memo_clear_fail('sales memo still has the gray line or a fixed Bengali page');
}

echo "OK memo clear language\n";
