<?php
declare(strict_types=1);

$boundary = (string) file_get_contents(dirname(__DIR__) . '/deploy/public_html/assets/js/error-boundary.js');
$widgets = (string) file_get_contents(dirname(__DIR__) . '/deploy/public_html/assets/js/display-widgets.js');
$fit = (string) file_get_contents(dirname(__DIR__) . '/deploy/public_html/assets/js/ame-page-fit.js');
$fail = 0;

function ame_eb_expect(bool $ok, string $message): void
{
    global $fail;
    if ($ok) {
        echo "OK {$message}\n";
        return;
    }
    echo "FAIL {$message}\n";
    $fail++;
}

$guard = strpos($boundary, 'if (benign(message))');
$report = strpos($boundary, 'report(id, message, event.filename');
ame_eb_expect($guard !== false && $report !== false && $guard < $report, 'the harmless browser note is ignored before it is logged');
ame_eb_expect(str_contains($boundary, 'ResizeObserver loop'), 'the resize note is named');
ame_eb_expect(str_contains($widgets, 'requestAnimationFrame'), 'the header measure waits for the next frame');
ame_eb_expect(str_contains($widgets, "position === 'fixed'"), 'a seated running line is left in place');
ame_eb_expect(str_contains($fit, 'data-ame-seated'), 'the running line is not moved again on every tick');
ame_eb_expect(!preg_match('/\b(UPDATE|INSERT|DELETE)\b/', $boundary . $widgets), 'these scripts do not write amounts');

if ($fail > 0) {
    exit(1);
}
echo "OK error boundary\n";
