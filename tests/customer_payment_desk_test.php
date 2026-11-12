<?php

function ame_cpd_expect(bool $ok, string $note): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL {$note}\n");
        exit(1);
    }
}

$root = dirname(__DIR__) . '/deploy/public_html';
$page = (string)file_get_contents($root . '/modules/customers/payment-receive.php');
$desk = (string)file_get_contents($root . '/modules/customers/actions/payment_desk.php');

ame_cpd_expect(str_contains($page, 'actions/process_payment.php'), 'payment still posts to the existing receiver');
ame_cpd_expect(str_contains($page, 'name="amount"'), 'the entered amount keeps the same field');
ame_cpd_expect(str_contains($page, 'name="discount"'), 'discount keeps the same field');
ame_cpd_expect(str_contains($page, 'const newBalance = balance - relief;'), 'the on-screen preview uses the existing balance');
ame_cpd_expect(str_contains($page, 'গ্রহণকৃত পেমেন্ট সমূহ'), 'received payments sit beside the form');
ame_cpd_expect(str_contains($page, 'প্রত্যাশিত বকেয়া পেমেন্ট এর তারিখ'), 'the expected due date table is on the desk');
ame_cpd_expect(str_contains($page, 'id="cpdBalance"'), 'the balance box shows the stored balance');

ame_cpd_expect(!preg_match('/\b(UPDATE|INSERT|DELETE)\b/i', $desk), 'the desk does not write any amount');
ame_cpd_expect(str_contains($desk, 'running_balance'), 'the due column reads the stored ledger balance');
ame_cpd_expect(str_contains($desk, 'next_due_amount'), 'the expected amount is the stored next due');
ame_cpd_expect(str_contains($desk, 'receivable_balance'), 'the customer balance is read, not recalculated');

$version = trim((string)file_get_contents($root . '/VERSION.txt'));
ame_cpd_expect($version === '10.98.804', 'the update version is 10.98.804');

echo "OK customer payment desk\n";
