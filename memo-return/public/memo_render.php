<?php
declare(strict_types=1);

/** @param array<string, mixed> $memo @param array<string, mixed> $shop */
function ame_render_memo(array $memo, array $shop, string $shareUrl, bool $toolbar): void
{
    $when = (string) ($memo['created_at'] ?? '');
    try {
        $stamp = $when !== ''
            ? (new DateTimeImmutable($when))->setTimezone(new DateTimeZone('Asia/Dhaka'))->format('d-m-Y h:i A')
            : '';
    } catch (Throwable) {
        $stamp = $when;
    }
    $previous = (string) ($memo['balance_before'] ?? '0.00');
    $returned = (string) ($memo['account_minus'] ?? $memo['return_net'] ?? '0.00');
    $total = (string) ($memo['balance_after'] ?? '0.00');
    $registered = !empty($memo['registered']);
    $shareText = "ফেরত মেমো " . ($memo['return_no'] ?? '') . "\n"
        . "বিক্রয় মেমো: " . ($memo['memo'] ?? '') . "\n"
        . "কাস্টমার: " . ($memo['customer_name'] ?? '') . "\n"
        . "সাবেক টাকা: ৳ " . $previous . "\n"
        . "ফেরত: ৳ " . ($memo['return_net'] ?? '') . "\n"
        . "টোটাল: ৳ " . $total . "\n"
        . $shareUrl;
    ?>
    <div id="memo-container" class="memo-wrapper invoice-box">
        <?php if (!empty($memo['voided'])): ?>
            <div class="void-stamp">বাতিল</div>
        <?php endif; ?>
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <?php if (!empty($shop['logo'])): ?>
                        <img class="ame-print-logo" src="<?= ame_h($shop['logo']) ?>" alt="">
                    <?php endif; ?>
                </td>
                <td class="shop-cell">
                    <div class="shop-name"><?= ame_h($shop['name'] ?? 'টেস্টিং') ?></div>
                    <div class="shop-tag"><?= ame_h($shop['tagline'] ?? '') ?></div>
                    <?php if (!empty($shop['address'])): ?><div><?= ame_h($shop['address']) ?></div><?php endif; ?>
                    <?php if (!empty($shop['phone'])): ?><div>ফোন: <?= ame_h($shop['phone']) ?></div><?php endif; ?>
                </td>
                <td class="meta-cell">
                    <div class="doc-kind">ফেরত মেমো</div>
                    <div>নং: <b><?= ame_h($memo['return_no'] ?? '') ?></b></div>
                    <div>তারিখ: <?= ame_h($stamp) ?></div>
                    <div>বিক্রয় মেমো: <b><?= ame_h($memo['memo'] ?? '') ?></b></div>
                </td>
            </tr>
        </table>
        <table class="party-table">
            <tr>
                <td>কাস্টমার</td>
                <td><b><?= ame_h($registered ? ($memo['customer_name'] ?: 'রেজিস্টার্ড কাস্টমার') : 'খুচরা') ?></b></td>
                <td>ধরন</td>
                <td><?= $registered ? 'রেজিস্টার্ড' : 'খুচরা' ?></td>
            </tr>
        </table>
        <table class="memo-table main-table">
            <thead>
                <tr>
                    <th>ক্রম</th>
                    <th>পণ্য</th>
                    <th class="num">পরিমাণ</th>
                    <th class="num">দর</th>
                    <th class="num">টাকা</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($memo['lines'] ?? []) as $index => $line): ?>
                    <tr>
                        <td><?= (int) $index + 1 ?></td>
                        <td><?= ame_h($line['name'] ?? '') ?></td>
                        <td class="num"><?= ame_h($line['qty'] ?? '') ?></td>
                        <td class="num"><?= ame_h($line['rate'] ?? '') ?></td>
                        <td class="num"><?= ame_h($line['line_net'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <table class="summary-table">
            <tr>
                <td>পণ্য ফেরত (নীট)</td>
                <td class="num">৳ <?= ame_h($memo['return_net'] ?? '0.00') ?></td>
            </tr>
            <tr>
                <td>সাবেক টাকা</td>
                <td class="num">৳ <?= ame_h($registered ? $previous : '0.00') ?></td>
            </tr>
            <tr>
                <td>(-) বর্তমান ফেরত</td>
                <td class="num">৳ <?= ame_h($registered ? $returned : ($memo['return_net'] ?? '0.00')) ?></td>
            </tr>
            <tr class="total-row">
                <td>টোটাল</td>
                <td class="num">৳ <?= ame_h($registered ? $total : ($memo['refund'] ?? $memo['return_net'] ?? '0.00')) ?></td>
            </tr>
        </table>
        <p class="memo-note">রেজিস্টার্ড কাস্টমারের সাবেক টাকা থেকে এই ফেরতের টাকা মাইনাস করে টোটাল দেখানো হয়েছে। স্টকে ফেরত পণ্য যোগ হয়েছে।</p>
        <table class="sign-table">
            <tr>
                <td>গ্রহীতার স্বাক্ষর</td>
                <td>দোকানের স্বাক্ষর</td>
            </tr>
        </table>
    </div>
    <?php if ($toolbar): ?>
        <div class="share-bar no-print" data-share-url="<?= ame_h($shareUrl) ?>" data-share-text="<?= ame_h($shareText) ?>">
            <button type="button" data-act="print">প্রিন্ট</button>
            <a data-act="whatsapp" href="https://wa.me/?text=<?= ame_h(rawurlencode($shareText)) ?>">হোয়াটসঅ্যাপ</a>
            <a data-act="facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?= ame_h(rawurlencode($shareUrl)) ?>" target="_blank" rel="noopener">ফেসবুক</a>
            <a data-act="sms" href="sms:?&body=<?= ame_h(rawurlencode($shareText)) ?>">এসএমএস</a>
            <button type="button" data-act="copy">লিংক কপি</button>
            <button type="button" data-act="native">শেয়ার</button>
            <p class="share-link"><?= ame_h($shareUrl) ?></p>
        </div>
        <script>
            (function () {
                var bar = document.querySelector('.share-bar');
                if (!bar) return;
                var url = bar.getAttribute('data-share-url') || '';
                var text = bar.getAttribute('data-share-text') || '';
                bar.addEventListener('click', function (event) {
                    var node = event.target.closest('[data-act]');
                    if (!node) return;
                    var act = node.getAttribute('data-act');
                    if (act === 'print') {
                        event.preventDefault();
                        window.print();
                    }
                    if (act === 'copy') {
                        event.preventDefault();
                        var done = function () { node.textContent = 'কপি হয়েছে'; };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(url).then(done);
                        }
                    }
                    if (act === 'native') {
                        event.preventDefault();
                        if (navigator.share) {
                            navigator.share({ title: 'ফেরত মেমো', text: text, url: url });
                        }
                    }
                });
            })();
        </script>
    <?php endif; ?>
    <?php
}
