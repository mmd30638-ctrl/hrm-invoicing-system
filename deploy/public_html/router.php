<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
if (session_status() === PHP_SESSION_NONE) session_start();

$uri = strtolower($_SERVER['REQUEST_URI'] ?? '');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = strtolower((string)$path);

$ame_base = realpath(__DIR__);
$ame_home = __DIR__ . '/index.php';
if (($path === '/' || $path === '' || $path === '/index.php') && is_file($ame_home)) {
    require $ame_home;
    exit;
}
$ame_rel = ltrim($path, '/');
if ($ame_base && $ame_rel !== '' && !str_contains($ame_rel, '..')) {
    $ame_candidates = [__DIR__ . '/' . $ame_rel];
    if (!str_ends_with($ame_rel, '.php')) {
        $ame_candidates[] = __DIR__ . '/' . $ame_rel . '.php';
        $ame_candidates[] = __DIR__ . '/' . $ame_rel . '/index.php';
    }
    foreach ($ame_candidates as $ame_candidate) {
        $ame_file = realpath($ame_candidate);
        if ($ame_file && is_file($ame_file) && str_starts_with($ame_file, $ame_base . DIRECTORY_SEPARATOR) && str_ends_with(strtolower($ame_file), '.php')) {
            require $ame_file;
            exit;
        }
    }
}

if (is_file(__DIR__ . '/includes/public_host_files.php')) {
    require_once __DIR__ . '/includes/public_host_files.php';
    if (function_exists('ame_ensure_public_host_files')) {
        ame_ensure_public_host_files();
    }
}
$hostPublic = [
    '/assets/css/sale-cream.css' => ['file' => __DIR__ . '/assets/css/sale-cream.css', 'type' => 'text/css; charset=UTF-8'],
    '/favicon.png' => ['file' => __DIR__ . '/favicon.png', 'type' => 'image/png'],
    '/robots.txt' => ['file' => __DIR__ . '/robots.txt', 'type' => 'text/plain; charset=UTF-8'],
];
if (isset($hostPublic[$path]) && is_file($hostPublic[$path]['file'])) {
    header('Content-Type: ' . $hostPublic[$path]['type']);
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($hostPublic[$path]['file']);
    exit;
}

$aliases = [
    '/modules/dashboard/index.php' => '/index.php',
    '/modules/dashboard/' => '/index.php',
    '/modules/dashboard' => '/index.php',
];
if (isset($aliases[$path])) {
    header('Location: ' . $aliases[$path], true, 301);
    exit();
}

function ame_router_safe_target(string $raw): ?string
{
    $raw = str_replace('\\', '/', $raw);
    $raw = ltrim($raw, '/');
    if ($raw === '' || str_contains($raw, "\0") || str_contains($raw, '..')) {
        return null;
    }
    if (preg_match('#^(?:[a-z]+:)?//#i', $raw) || str_starts_with($raw, '/')) {
        return null;
    }
    if (!preg_match('#^(modules|accounts|actions|inventory)/[A-Za-z0-9_./-]+\.php$#', $raw)) {
        return null;
    }
    $base = realpath(__DIR__);
    $candidate = realpath(__DIR__ . '/' . $raw);
    if (!$base || !$candidate || !is_file($candidate)) {
        return null;
    }
    $prefix = $base . DIRECTORY_SEPARATOR;
    if (!str_starts_with($candidate, $prefix)) {
        return null;
    }
    return $candidate;
}

// ১. ডাটাবেজ-ভিত্তিক ডায়নামিক রুট ম্যাপিং (system_routes টেবিল থাকলে)
$connect_path = __DIR__ . '/includes/ame_db_connect.php';
if (is_file($connect_path)) {
    require_once $connect_path;
    if (!isset($db) || !($db instanceof mysqli)) {
        $opened = ame_open_mysqli();
        if ($opened instanceof mysqli) {
            $db = $opened;
        }
    }
}
$db_path = __DIR__ . '/includes/db.php';
if ((!isset($db) || !($db instanceof mysqli)) && file_exists($db_path)) {
    require_once $db_path;
}

if (isset($db) && $db instanceof mysqli) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'system_routes'");
        if ($check && $check->num_rows > 0) {
            $stmt = $db->prepare("SELECT target_file FROM system_routes WHERE request_slug = ? LIMIT 1");
            $stmt->bind_param("s", $uri);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result && !empty($result['target_file'])) {
                $target = ame_router_safe_target((string)$result['target_file']);
                if ($target) {
                    require_once $target;
                    exit();
                }
            }
        }
    } catch (mysqli_sql_exception $e) {
        // রাউট লুকআপ ফেইল করলেও সাইট যেন ভেঙে না পড়ে
    }
}

// ২. Missing URL logging through the server error log. Runtime logs and
// throttle files must not be written into the public document root.
$missing_url = ($_SERVER['HTTP_HOST'] ?? 'unknown') . ($_SERVER['REQUEST_URI'] ?? '/');
$missing_url = str_replace(["\r", "\n"], '', $missing_url);
error_log('404 Not Found: ' . $missing_url);

// ৩. Return correct HTTP semantics instead of masking broken links with a
// redirect to the authenticated dashboard.
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>পৃষ্ঠা পাওয়া যায়নি</title>
</head><body style="font-family:sans-serif;text-align:center;padding:3rem"><h1>404</h1><p>পৃষ্ঠা পাওয়া যায়নি।</p><p>এটি টেবিলের সমস্যা নয়। এই ঠিকানায় কোনো পাতা নেই।</p><p><a href="/index.php">মূল পাতায় ফিরুন</a></p></body></html>';
exit();
