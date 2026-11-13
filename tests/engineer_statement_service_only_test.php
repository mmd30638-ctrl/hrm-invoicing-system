<?php

require_once __DIR__ . '/../deploy/public_html/includes/engineer_statement_post.php';

function ame_stmt_expect(bool $ok, string $note): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL {$note}\n");
        exit(1);
    }
}

$bill = ame_engineer_statement_normalize(7, '', 1500);
ame_stmt_expect($bill !== null && $bill['engineer_id'] === 7, 'engineer is kept');
ame_stmt_expect($bill['service_type'] === 'কাজের বিল', 'a fixed amount without a description is still a work bill');
ame_stmt_expect(abs($bill['amount'] - 1500) < 0.001, 'the submitted amount is kept');
ame_stmt_expect(ame_engineer_statement_normalize(0, 'কাজ', 500) === null, 'an amount without an engineer is not guessed here');
ame_stmt_expect(ame_engineer_statement_normalize(4, 'মেরামত', 0) === null, 'a zero amount is not a bill');

$share = ame_engineer_statement_credit(2000, 1000);
ame_stmt_expect($share['show'] && abs($share['credit'] - 1000) < 0.001 && $share['label'] === 'কাজের ভাগ', 'a percentage share stays the statement credit');
$only = ame_engineer_statement_credit(800, 0);
ame_stmt_expect($only['show'] && abs($only['credit'] - 800) < 0.001 && $only['label'] === 'কাজের বিল', 'a service-only bill with no share still appears');
ame_stmt_expect(ame_engineer_statement_credit(0, 0)['show'] === false, 'an empty row stays off the statement');

$root = dirname(__DIR__) . '/deploy/public_html';
$post = (string)file_get_contents($root . '/includes/engineer_statement_post.php');
ame_stmt_expect(!str_contains($post, 'UPDATE customers'), 'customer balances are not rewritten');
ame_stmt_expect(!str_contains($post, 'UPDATE suppliers'), 'supplier balances are not rewritten');
ame_stmt_expect(str_contains($post, "status === 'completed'"), 'a draft memo does not post the statement');
ame_stmt_expect(str_contains($post, "s.status = 'completed'"), 'only a completed memo is backfilled');

$finance = (string)file_get_contents($root . '/includes/sales/sale_finance.php');
ame_stmt_expect(str_contains($finance, 'কাজের বিল'), 'the sale accepts a fixed engineer amount');
ame_stmt_expect(str_contains($finance, 'ame_engineer_statement_post_stored'), 'saving the memo posts the engineer statement');
ame_stmt_expect(!str_contains($finance, "status = 'active'"), 'the engineer does not have to be flagged active');

$pos = (string)file_get_contents($root . '/modules/sales/pos_workspace.php');
ame_stmt_expect(str_contains($pos, 'function resolvedEngineerId'), 'a chosen engineer is resolved before submit');
ame_stmt_expect(str_contains($pos, 'complete: engId > 0 && amt > 0'), 'a product is not required for the work bill');
ame_stmt_expect(str_contains($pos, 'keepCompleteBillOnCart'), 'the fixed amount stays on the memo');

$desk = (string)file_get_contents($root . '/includes/worker_desk.php');
ame_stmt_expect(str_contains($desk, 'ame_engineer_statement_credit'), 'the engineer statement shows the work bill');
ame_stmt_expect(str_contains($desk, 'service_amount'), 'the statement reads the work bill amount');

$version = trim((string)file_get_contents($root . '/VERSION.txt'));
ame_stmt_expect($version === '10.98.822', 'the update version is 10.98.822');

echo "OK service-only engineer statement\n";
