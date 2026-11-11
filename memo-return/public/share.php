<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/memo_render.php';

$config = ame_config();
$token = (string) ($_GET['t'] ?? '');
try {
    $memo = ame_service($config)->findByToken($token);
} catch (Throwable) {
    http_response_code(404);
    echo 'ফেরত মেমো পাওয়া যায়নি।';
    exit;
}
$shareUrl = ame_share_url($token, $config);
$shop = ame_shop($config);
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
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
<div class="sheet-wrap">
    <?php ame_render_memo($memo, $shop, $shareUrl, true); ?>
</div>
</body>
</html>
