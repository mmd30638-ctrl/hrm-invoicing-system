<?php
declare(strict_types=1);

$router = (string) file_get_contents(dirname(__DIR__) . '/deploy/public_html/router.php');
$fail = 0;

function ame_ts_expect(bool $ok, string $message): void
{
    global $fail;
    if ($ok) {
        return;
    }
    echo "FAIL {$message}\n";
    $fail++;
}

ame_ts_expect(str_contains($router, "require \$ame_home"), 'the testing site home opens from the router');
ame_ts_expect(str_contains($router, 'href="/index.php"'), 'the missing page returns to the home file');
ame_ts_expect(!preg_match('/\b(UPDATE|INSERT|DELETE)\b/', $router), 'the router does not write amounts');

if ($fail > 0) {
    exit(1);
}
echo "OK testing site router\n";
