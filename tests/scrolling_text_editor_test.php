<?php

function ame_tick_fail(string $msg): void
{
    fwrite(STDERR, "FAIL {$msg}\n");
    exit(1);
}

$path = dirname(__DIR__) . '/deploy/public_html/modules/settings/scrolling_text.php';
$php = (string) file_get_contents($path);
foreach ([
    'process_display_widget.php',
    'name="message"',
    'name="speed_seconds"',
    'name="direction"',
    'ame_icon_picker_html',
    'name="font_size"',
    'id="pvTrack"',
    '--dw-speed',
    'is-right',
    'dw-live-left',
    'dw-live-right',
    'csrf_field()',
] as $needle) {
    if (!str_contains($php, $needle)) {
        ame_tick_fail('editor missing: ' . $needle);
    }
}
if (preg_match('/customer_payments|receivable_balance|payable_balance|UPDATE\s+customers|UPDATE\s+suppliers/i', $php)) {
    ame_tick_fail('the running-text editor writes a money table');
}

echo "OK scrolling text editor\n";
