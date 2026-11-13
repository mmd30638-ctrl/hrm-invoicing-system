<?php
declare(strict_types=1);

$router = dirname(__DIR__) . '/deploy/public_html/router.php';
$source = (string) file_get_contents($router);
$fail = 0;

function ame_ts_expect(bool $ok, string $message): void
{
    global $fail;
    if ($ok) {
        echo "OK {$message}\n";
        return;
    }
    echo "FAIL {$message}\n";
    $fail++;
}

ame_ts_expect(str_contains($source, 'দোকানে যান'), 'the missing page has one home button');
ame_ts_expect(str_contains($source, 'এই ঠিকানায় কিছু নেই।'), 'the missing page uses a short sentence');
ame_ts_expect(!str_contains($source, 'টেবিল'), 'the missing page does not talk about tables');
ame_ts_expect(!preg_match('/\b(UPDATE|INSERT|DELETE)\b/', $source), 'the router does not write amounts');

function ame_ts_run(string $root, string $uri): array
{
    $statusFile = $root . '/status.txt';
    $bodyFile = $root . '/body.html';
    $child = $root . '/child.php';
    file_put_contents($child, <<<'PHP'
<?php
register_shutdown_function(static function (): void {
    file_put_contents(getenv('AME_STATUS'), (string) http_response_code());
});
$_SERVER['REQUEST_URI'] = getenv('AME_URI') ?: '/';
$_SERVER['HTTP_HOST'] = 'shop.test';
$_SERVER['DOCUMENT_ROOT'] = getenv('AME_ROOT');
chdir(getenv('AME_ROOT'));
include getenv('AME_ROOT') . '/router.php';
PHP);
    $proc = proc_open(
        [PHP_BINARY, '-d', 'display_errors=0', $child],
        [1 => ['file', $bodyFile, 'w'], 2 => ['file', $root . '/err.txt', 'w']],
        $pipes,
        $root,
        [
            'AME_URI' => $uri,
            'AME_ROOT' => $root,
            'AME_STATUS' => $statusFile,
        ]
    );
    if (!is_resource($proc)) {
        return [0, ''];
    }
    proc_close($proc);
    $status = is_file($statusFile) ? (int) file_get_contents($statusFile) : 0;
    $body = is_file($bodyFile) ? (string) file_get_contents($bodyFile) : '';
    return [$status, $body];
}

$withHome = sys_get_temp_dir() . '/ame-router-home-' . getmypid();
$noHome = sys_get_temp_dir() . '/ame-router-missing-' . getmypid();
foreach ([$withHome, $noHome] as $dir) {
    if (is_dir($dir)) {
        array_map('unlink', glob($dir . '/*') ?: []);
    }
    mkdir($dir);
    copy($router, $dir . '/router.php');
}
file_put_contents($withHome . '/index.php', "<?php echo 'HOME-OK';\n");

[$status, $body] = ame_ts_run($withHome, '/');
ame_ts_expect($status === 200 && str_contains($body, 'HOME-OK'), 'the site root opens the shop');

[$status, $body] = ame_ts_run($withHome, '/index.php');
ame_ts_expect($status === 200 && str_contains($body, 'HOME-OK'), 'index.php opens the shop');

[$status, $body] = ame_ts_run($withHome, '/router.php');
ame_ts_expect($status === 200 && str_contains($body, 'HOME-OK'), 'the front door opens the shop');

[$status, $body] = ame_ts_run($withHome, '/no-such-page');
ame_ts_expect($status === 404 && str_contains($body, 'পাতা নেই') && str_contains($body, 'দোকানে যান') && str_contains($body, '/no-such-page'), 'a missing address shows the simple page');
ame_ts_expect(!str_contains($body, 'টেবিল'), 'the live missing page does not talk about tables');

[$status, $body] = ame_ts_run($noHome, '/');
ame_ts_expect($status === 404 && str_contains($body, 'পাতা নেই') && !str_contains($body, 'HOME-OK'), 'a site without a home file does not crash');

if ($fail > 0) {
    exit(1);
}
echo "OK testing site router\n";
