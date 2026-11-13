<?php
declare(strict_types=1);

$root = dirname(__DIR__) . '/deploy/public_html';
$footer = (string) file_get_contents($root . '/partials/footer.php');
$css = (string) file_get_contents($root . '/assets/css/partner-desk.css');
$js = (string) file_get_contents($root . '/assets/js/partner-desk.js');
$fail = 0;

function ame_pd_expect(bool $ok, string $message): void
{
    global $fail;
    if ($ok) {
        return;
    }
    echo "FAIL {$message}\n";
    $fail++;
}

ame_pd_expect(str_contains($footer, 'partner-desk.css'), 'the partnership desk style is linked');
ame_pd_expect(str_contains($footer, '/modules/accounts/partner_'), 'the style loads on the partnership pages');
ame_pd_expect(str_contains($css, '.pa-cards') && str_contains($css, '.pa-sale-line'), 'the partnership cards and rows are styled');
ame_pd_expect(!preg_match('/\b(UPDATE|INSERT|DELETE|pa_taka)\b/', $css), 'the style sheet does not rewrite amounts');
ame_pd_expect(str_contains($footer, 'partner-desk.js'), 'the simpler partnership flow is linked');
ame_pd_expect(str_contains($js, 'insertAdjacentElement') && str_contains($js, '.pa-cards') && str_contains($js, '.pa-start'), 'numbers come first and the start date moves down');
ame_pd_expect(str_contains($js, 'save_capital') && str_contains($js, 'transaction_date') && str_contains($css, '.pa-extra'), 'extra partner money sits in one dated card');
ame_pd_expect(str_contains($js, 'edit=') && str_contains($js, 'pa-extra-pick') && str_contains($css, '.pa-extra-pick'), 'every partner printed on the page can receive extra money');
ame_pd_expect(!preg_match('/\.val|pa_taka|innerHTML/', $js), 'the simpler flow does not rewrite any amount text');

if ($fail > 0) {
    exit(1);
}
echo "OK partnership desk\n";
