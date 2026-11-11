<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

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
$authed = !empty($_SESSION['ame_return_ok']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'login') {
    $tries = (int) ($_SESSION['gate_tries'] ?? 0);
    if ($tries >= 8) {
        $error = 'অনেকবার ভুল পাসওয়ার্ড। একটু পরে আবার চেষ্টা করুন।';
    } elseif (ame_password_ok((string) ($_POST['password'] ?? ''), (string) $config['gate_password'])) {
        session_regenerate_id(true);
        $_SESSION['ame_return_ok'] = true;
        $_SESSION['gate_tries'] = 0;
        $authed = true;
    } else {
        $_SESSION['gate_tries'] = $tries + 1;
        $error = 'পাসওয়ার্ড মেলেনি।';
    }
}

$inspect = null;
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action !== 'login' && !ame_csrf_ok()) {
        $error = 'ফর্মের মেয়াদ শেষ। আবার চেষ্টা করুন।';
    } elseif ($action === 'lookup') {
        try {
            $inspect = ame_service($config)->inspect((string) ($_POST['memo'] ?? ''));
            $key = bin2hex(random_bytes(16));
            $_SESSION['return_key'] = $key;
            $_SESSION['return_memo'] = $inspect['memo'];
        } catch (Throwable $exception) {
            $error = ame_bangla_error($exception);
        }
    } elseif ($action === 'commit') {
        $memo = (string) ($_POST['memo'] ?? '');
        $requests = [];
        $ids = $_POST['sale_line_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        if (is_array($ids) && is_array($qtys)) {
            foreach ($ids as $index => $id) {
                $qty = (string) ($qtys[$index] ?? '0');
                if ((float) $qty > 0) {
                    $requests[] = ['sale_line_id' => (string) $id, 'qty' => $qty];
                }
            }
        }
        try {
            $saved = ame_service($config)->commit(
                $memo,
                $requests,
                (string) ($_SESSION['return_key'] ?? ''),
                true
            );
            unset($_SESSION['return_key'], $_SESSION['return_memo']);
            header('Location: ' . ame_base() . '/memo.php?no=' . rawurlencode((string) $saved['return_no']));
            exit;
        } catch (Throwable $exception) {
            $error = ame_bangla_error($exception);
            try {
                $inspect = ame_service($config)->inspect($memo);
            } catch (Throwable) {
                $inspect = null;
            }
        }
    }
}

$csrf = ame_csrf();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>মেমো ফেরত</title>
    <link rel="stylesheet" href="<?= ame_h(ame_base()) ?>/assets/memo.css">
</head>
<body>
<main class="desk">
    <h1>বিক্রয় ফেরত</h1>
    <p class="lead">মেমো নম্বর দিয়ে পণ্য ফেরত নিন। রেজিস্টার্ড কাস্টমারের সাবেক টাকা থেকে ফেরতের টাকা কাটা হবে, স্টক ফিরবে, এবং বিক্রয় মেমোর মতো একটি ফেরত মেমো তৈরি হবে।</p>
    <?php if ($error !== ''): ?><div class="alert"><?= ame_h($error) ?></div><?php endif; ?>
    <?php if (!$authed): ?>
        <?php if ((string) $config['gate_password'] === '' && !$siteUser): ?>
            <div class="panel">config.local.php ফাইলে gate_password সেট করুন। দোকানের লগইন সেশন থাকলে সেটা দিয়েও ঢোকা যায়।</div>
        <?php else: ?>
            <form class="panel" method="post">
                <input type="hidden" name="action" value="login">
                <label for="password">পাসওয়ার্ড</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
                <p><button type="submit">ঢুকুন</button></p>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <form class="panel" method="post">
            <input type="hidden" name="action" value="lookup">
            <input type="hidden" name="csrf" value="<?= ame_h($csrf) ?>">
            <label for="memo">বিক্রয় মেমো নম্বর</label>
            <input id="memo" name="memo" type="text" required maxlength="64" value="<?= ame_h($inspect['memo'] ?? ($_POST['memo'] ?? '')) ?>" autocomplete="off">
            <p><button type="submit">মেমো দেখুন</button></p>
        </form>
        <?php if (is_array($inspect)): ?>
            <form class="panel" method="post" style="margin-top:12px">
                <input type="hidden" name="action" value="commit">
                <input type="hidden" name="csrf" value="<?= ame_h($csrf) ?>">
                <input type="hidden" name="memo" value="<?= ame_h($inspect['memo']) ?>">
                <p><b><?= ame_h($inspect['customer_name'] !== '' ? $inspect['customer_name'] : 'খুচরা') ?></b>
                    <?php if ($inspect['registered']): ?> · সাবেক টাকা ৳ <?= ame_h($inspect['previous_due']) ?><?php endif; ?></p>
                <table class="lines">
                    <thead><tr><th>পণ্য</th><th class="num">দর</th><th class="num">বাকি</th><th class="num">ফেরত</th></tr></thead>
                    <tbody>
                    <?php foreach ($inspect['lines'] as $index => $line): ?>
                        <tr>
                            <td><?= ame_h($line['name']) ?></td>
                            <td class="num"><?= ame_h($line['rate']) ?></td>
                            <td class="num"><?= ame_h($line['remaining']) ?></td>
                            <td class="num">
                                <input type="hidden" name="sale_line_id[<?= (int) $index ?>]" value="<?= (int) $line['sale_line_id'] ?>">
                                <input name="qty[<?= (int) $index ?>]" type="number" min="0" max="<?= ame_h($line['remaining_raw']) ?>" step="0.001" value="<?= ame_h($line['remaining_raw']) ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit">ফেরত নিশ্চিত করুন</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
