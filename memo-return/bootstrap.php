<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

require __DIR__ . '/src/Money.php';
require __DIR__ . '/src/Schema.php';
require __DIR__ . '/src/MemoReturnService.php';

function ame_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return array<string, mixed> */
function ame_config(): array
{
    $defaults = [
        'dsn' => '',
        'user' => '',
        'password' => '',
        'gate_password' => '',
        'public_url' => '',
        'shop' => [
            'name' => 'টেস্টিং',
            'tagline' => 'স্পেয়ার পার্টস ব্যবসা ও ইঞ্জিনিয়ারিং ওয়ার্কশপ',
            'phone' => '',
            'address' => '',
            'logo' => '/assets/img/ma-logo.png',
        ],
    ];
    $local = __DIR__ . '/config.local.php';
    if (is_file($local)) {
        $loaded = require $local;
        if (!is_array($loaded)) {
            throw new RuntimeException('config.local.php must return an array.');
        }
        $config = array_replace_recursive($defaults, $loaded);
    } else {
        $config = $defaults;
        $fromEnv = ame_env_config();
        if ($fromEnv !== null) {
            $config = array_replace_recursive($config, $fromEnv);
        }
    }
    $dsn = getenv('AME_DB_DSN');
    if (is_string($dsn) && $dsn !== '') {
        $config['dsn'] = $dsn;
        $config['user'] = getenv('AME_DB_USER') ?: '';
        $config['password'] = getenv('AME_DB_PASSWORD') ?: '';
    }
    $gate = getenv('AME_GATE_PASSWORD');
    if (is_string($gate) && $gate !== '') {
        $config['gate_password'] = $gate;
    }
    return $config;
}

/** @return array<string, mixed>|null */
function ame_env_config(): ?array
{
    $dir = __DIR__;
    for ($i = 0; $i < 4; $i++) {
        $file = $dir . '/.env';
        if (is_file($file) && is_readable($file)) {
            $vars = ame_parse_env((string) file_get_contents($file));
            $host = $vars['DB_HOST'] ?? '';
            $name = $vars['DB_NAME'] ?? ($vars['DB_DATABASE'] ?? '');
            $user = $vars['DB_USER'] ?? ($vars['DB_USERNAME'] ?? '');
            $password = $vars['DB_PASSWORD'] ?? ($vars['DB_PASS'] ?? '');
            if ($host !== '' && $name !== '' && $user !== '') {
                $port = $vars['DB_PORT'] ?? '3306';
                return [
                    'dsn' => 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4',
                    'user' => $user,
                    'password' => $password,
                ];
            }
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    return null;
}

/** @return array<string, string> */
function ame_parse_env(string $raw): array
{
    $vars = [];
    foreach (preg_split('/\R/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        if (preg_match('/^[A-Z0-9_]+$/', $key)) {
            $vars[$key] = $value;
        }
    }
    return $vars;
}

function ame_pdo(array $config): PDO
{
    if (!is_string($config['dsn']) || $config['dsn'] === '') {
        throw new RuntimeException('Database is not configured.');
    }
    $pdo = new PDO((string) $config['dsn'], (string) $config['user'], (string) $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
    return $pdo;
}

function ame_service(?array $config = null): MemoReturnService
{
    $config ??= ame_config();
    $pdo = ame_pdo($config);
    $override = __DIR__ . '/storage/schema.json';
    if (is_file($override)) {
        $json = json_decode((string) file_get_contents($override), true);
        if (!is_array($json)) {
            throw new RuntimeException('storage/schema.json is not valid.');
        }
        $schema = Schema::fromArray((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), $json);
    } else {
        $schema = Schema::detect($pdo);
    }
    return new MemoReturnService($pdo, $schema);
}

function ame_base(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    return rtrim(dirname($script), '/');
}

function ame_share_url(string $token, array $config): string
{
    $public = rtrim((string) ($config['public_url'] ?? ''), '/');
    if ($public !== '') {
        return $public . '/share.php?t=' . rawurlencode($token);
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.:-]+$/', $host)) {
        $host = 'localhost';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    return ($https ? 'https' : 'http') . '://' . $host . ame_base() . '/share.php?t=' . rawurlencode($token);
}

/** @param array<string, mixed> $config */
function ame_shop(array $config): array
{
    $shop = $config['shop'] ?? [];
    return is_array($shop) ? $shop : [];
}

function ame_password_ok(string $given, string $expected): bool
{
    if ($expected === '') {
        return false;
    }
    if (str_starts_with($expected, '$2y$') || str_starts_with($expected, '$argon2')) {
        return password_verify($given, $expected);
    }
    return hash_equals($expected, $given);
}

function ame_csrf(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function ame_csrf_ok(): bool
{
    $sent = (string) ($_POST['csrf'] ?? '');
    $known = (string) ($_SESSION['csrf'] ?? '');
    return $sent !== '' && $known !== '' && hash_equals($known, $sent);
}

function ame_site_user(): bool
{
    if (!isset($_COOKIE['APONSHOP_STG'])) {
        return false;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        return false;
    }
    session_name('APONSHOP_STG');
    session_start();
    $keys = ['user_id', 'admin_id', 'uid', 'staff_id', 'employee_id', 'username', 'user_name', 'user', 'userid', 'role'];
    $ok = false;
    foreach ($keys as $key) {
        if (!empty($_SESSION[$key])) {
            $ok = true;
            break;
        }
    }
    session_write_close();
    session_name('AME_RETURN');
    return $ok;
}

function ame_bangla_error(Throwable $error): string
{
    $code = $error instanceof ReturnException ? $error->errorCode : '';
    return match ($code) {
        'MEMO_NOT_FOUND' => 'এই মেমো নম্বরে কোনো বিক্রয় নেই।',
        'MEMO_AMBIGUOUS' => 'এই মেমো নম্বর একাধিক বিক্রয়ে আছে।',
        'RETURN_NOT_ALLOWED' => 'ড্রাফট বা বাতিল মেমো থেকে ফেরত নেওয়া যায় না।',
        'QTY_EXCEEDED' => 'ফেরতের পরিমাণ মেমোতে বাকি পরিমাণের চেয়ে বেশি।',
        'QTY_REQUIRED' => 'ফেরতের পরিমাণ শূন্যের বেশি দিন।',
        'LINES_REQUIRED' => 'অন্তত একটি পণ্য বেছে নিন।',
        'CUSTOMER_NOT_FOUND' => 'মেমোর রেজিস্টার্ড কাস্টমার খুঁজে পাওয়া যায়নি।',
        'PRODUCT_NOT_FOUND' => 'মেমোর একটি পণ্য স্টকে নেই।',
        'ALREADY_VOID' => 'এই ফেরত মেমো আগেই বাতিল।',
        'STOCK_ALREADY_USED' => 'ফেরতের পণ্য আবার বিক্রি হয়ে গেছে, তাই বাতিল করা যায়নি।',
        'RETURN_NOT_FOUND' => 'ফেরত মেমো পাওয়া যায়নি।',
        default => 'ফেরত সেভ হয়নি। তথ্য দেখে আবার চেষ্টা করুন।',
    };
}
