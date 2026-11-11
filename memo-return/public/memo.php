<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/memo_render.php';

$config = ame_config();
$siteUser = false;
if (session_status() !== PHP_SESSION_ACTIVE) {
    try {
        $siteUser = ame_site_user();
    } catch (Throwable) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $siteUser = false;
    }
    session_name('AME_RETURN');
    session_start();
}
if ($siteUser) {
    $_SESSION['ame_return_ok'] = true;
}
if (empty($_SESSION['ame_return_ok'])) {
    header('Location: ' . ame_base() . '/index.php');
    exit;
}

try {
    $memo = ame_service($config)->findByNo((string) ($_GET['no'] ?? ''));
} catch (Throwable) {
    http_response_code(404);
    echo 'ফেরত মেমো পাওয়া যায়নি।';
    exit;
}
$token = (string) ($memo['share_token'] ?? '');
$shareUrl = $token !== '' ? ame_share_url($token, $config) : '';
$shop = ame_shop($config);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>ফেরত মেমো <?= ame_h($memo['return_no'] ?? '') ?></title>
    <link rel="stylesheet" href="<?= ame_h(ame_base()) ?>/assets/memo.css">
</head>
<body>
<p class="desk-nav no-print" style="padding:12px 16px"><a href="<?= ame_h(ame_base()) ?>/index.php">আরেকটি ফেরত</a></p>
<div class="sheet-wrap">
    <?php ame_render_memo($memo, $shop, $shareUrl, $shareUrl !== ''); ?>
</div>
</body>
</html>
