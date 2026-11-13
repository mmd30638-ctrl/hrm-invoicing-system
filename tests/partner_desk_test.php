<?php
declare(strict_types=1);

$root = dirname(__DIR__) . '/deploy/public_html';
$footer = (string) file_get_contents($root . '/partials/footer.php');
$css = (string) file_get_contents($root . '/assets/css/partner-desk.css');
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

if ($fail > 0) {
    exit(1);
}
echo "OK partnership desk\n";
