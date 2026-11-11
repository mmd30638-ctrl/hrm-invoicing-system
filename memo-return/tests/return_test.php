<?php
declare(strict_types=1);

require __DIR__ . '/../src/Money.php';
require __DIR__ . '/../src/Schema.php';
require __DIR__ . '/../src/MemoReturnService.php';

$passed = 0;

function check(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) {
        fwrite(STDERR, "FAIL {$message}\n");
        exit(1);
    }
    $passed++;
}

function pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function shopA(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT, phone TEXT, balance TEXT, credit_limit TEXT)');
    $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, sku TEXT, stock TEXT)');
    $pdo->exec('CREATE TABLE sales (id INTEGER PRIMARY KEY, invoice_no TEXT, customer_id INTEGER, customer_type TEXT, subtotal TEXT, discount TEXT, grand_total TEXT, paid TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE sale_items (id INTEGER PRIMARY KEY, sale_id INTEGER, product_id INTEGER, qty TEXT, unit_price TEXT, returned_qty TEXT)');
    $pdo->exec('CREATE TABLE customer_ledger (id INTEGER PRIMARY KEY, customer_id INTEGER, debit TEXT, credit TEXT, note TEXT, created_at TEXT)');
    $pdo->exec("INSERT INTO customers (id, name, balance) VALUES (4, 'করিম', '1500.00')");
    $pdo->exec("INSERT INTO products (id, name, sku, stock) VALUES (1, 'ফিল্টার', 'F-1', '5'), (2, 'বেল্ট', 'B-1', '3')");
    $pdo->exec("INSERT INTO sales (id, invoice_no, customer_id, customer_type, subtotal, discount, grand_total, paid, status) VALUES (10, 'M-100', 4, 'Registered', '1000.00', '100.00', '900.00', '0.00', 'completed')");
    $pdo->exec("INSERT INTO sale_items (id, sale_id, product_id, qty, unit_price, returned_qty) VALUES (1, 10, 1, '2', '400.00', '0'), (2, 10, 2, '1', '200.00', '0')");
}

function shopB(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE clients (id INTEGER PRIMARY KEY, name TEXT, due_amount TEXT)');
    $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, qty_on_hand TEXT)');
    $pdo->exec('CREATE TABLE invoices (id INTEGER PRIMARY KEY, memo_no TEXT, cust_id INTEGER, cus_type TEXT, sub_total TEXT, discount_amount TEXT, net_total TEXT, amount_paid TEXT, sale_status TEXT)');
    $pdo->exec('CREATE TABLE invoice_lines (id INTEGER PRIMARY KEY, sale_id INTEGER, product_id INTEGER, quantity TEXT, rate TEXT)');
    $pdo->exec("INSERT INTO clients (id, name, due_amount) VALUES (7, 'রহিম', '800.00')");
    $pdo->exec("INSERT INTO items (id, name, qty_on_hand) VALUES (3, 'প্লাগ', '4')");
    $pdo->exec("INSERT INTO invoices (id, memo_no, cust_id, cus_type, sub_total, discount_amount, net_total, amount_paid, sale_status) VALUES (1, 'B-9', 7, 'Registered', '200.00', '0.00', '200.00', '0.00', 'sold')");
    $pdo->exec("INSERT INTO invoice_lines (id, sale_id, product_id, quantity, rate) VALUES (5, 1, 3, '2', '100.00')");
}

check(Money::share(400, 1000, 100) === 40, 'discount share');
check(Money::poisha('1500.00') === 150000, 'poisha');
check(Money::formatPoisha(114000) === '1,140.00', 'format');

$a = pdo();
shopA($a);
$service = new MemoReturnService($a, Schema::detect($a));
$seen = $service->inspect('M-100');
check($seen['previous_due'] === '1,500.00', 'inspect previous due');
check($seen['lines'][0]['name'] === 'ফিল্টার', 'product name on inspect');

$saved = $service->commit('M-100', [['sale_line_id' => 1, 'qty' => '1']], 'key-1', true);
check($saved['return_net'] === '360.00', 'net after discount');
check($saved['account_minus'] === '360.00', 'registered account minus');
check($saved['balance_before'] === '1,500.00', 'সাবেক');
check($saved['balance_after'] === '1,140.00', 'total after minus');
check($saved['lines'][0]['stock_after'] === '6', 'stock restored');
check($saved['customer_name'] === 'করিম', 'customer on result');
check(strlen((string) $saved['share_token']) === 32, 'share token');

$balance = $a->query('SELECT balance FROM customers WHERE id = 4')->fetchColumn();
check($balance === '1140.00', 'customer balance stored');
$stock = $a->query('SELECT stock FROM products WHERE id = 1')->fetchColumn();
check($stock === '6', 'product stock stored');
$returnedQty = $a->query('SELECT returned_qty FROM sale_items WHERE id = 1')->fetchColumn();
check($returnedQty === '1', 'returned qty on sale line');
$credit = $a->query('SELECT credit FROM customer_ledger')->fetchColumn();
check($credit === '360.00', 'ledger credit');

$again = $service->commit('M-100', [['sale_line_id' => 1, 'qty' => '1']], 'key-1', true);
check($again['replayed'] === true, 'replay');
check($again['return_no'] === $saved['return_no'], 'same document');
check($a->query('SELECT balance FROM customers WHERE id = 4')->fetchColumn() === '1140.00', 'replay does not minus twice');

$shared = $service->findByToken((string) $saved['share_token']);
check($shared['return_no'] === $saved['return_no'], 'share token opens memo');
check($shared['balance_before'] === '1,500.00' && $shared['balance_after'] === '1,140.00', 'shared memo totals');
check($shared['lines'][0]['name'] === 'ফিল্টার', 'shared line name');

try {
    $service->commit('M-100', [['sale_line_id' => 1, 'qty' => '5']], 'key-over', true);
    check(false, 'over return should fail');
} catch (ReturnException $error) {
    check($error->errorCode === 'QTY_EXCEEDED', 'over return code');
}

$second = $service->commit('M-100', [['sale_line_id' => 1, 'qty' => '1']], 'key-2', true);
check($second['account_minus'] === '360.00', 'second unit minus');
check($a->query('SELECT balance FROM customers WHERE id = 4')->fetchColumn() === '780.00', 'balance after second unit');

$voided = $service->void((string) $second['return_no']);
check($voided['voided'] === true, 'void');
check($a->query('SELECT balance FROM customers WHERE id = 4')->fetchColumn() === '1140.00', 'void restores balance');
check($a->query('SELECT stock FROM products WHERE id = 1')->fetchColumn() === '6', 'void restores stock');
try {
    $service->void((string) $second['return_no']);
    check(false, 'double void should fail');
} catch (ReturnException $error) {
    check($error->errorCode === 'ALREADY_VOID', 'double void');
}

$a->exec("INSERT INTO sales (id, invoice_no, customer_id, customer_type, subtotal, discount, grand_total, paid, status) VALUES (11, 'R-1', 4, 'Retail', '100.00', '0', '100.00', '100.00', 'completed')");
$a->exec("INSERT INTO sale_items (id, sale_id, product_id, qty, unit_price, returned_qty) VALUES (3, 11, 2, '1', '100.00', '0')");
$beforeRetail = $a->query('SELECT balance FROM customers WHERE id = 4')->fetchColumn();
$retail = $service->commit('R-1', [['sale_line_id' => 3, 'qty' => '1']], 'key-retail', true);
check($retail['registered'] === false, 'retail is not an account return');
check($retail['account_minus'] === '0.00', 'retail does not minus');
check($a->query('SELECT balance FROM customers WHERE id = 4')->fetchColumn() === $beforeRetail, 'retail leaves balance');
check($a->query('SELECT stock FROM products WHERE id = 2')->fetchColumn() === '4', 'retail still restocks');

$a->exec("INSERT INTO sales (id, invoice_no, customer_id, customer_type, subtotal, discount, grand_total, paid, status) VALUES (12, 'D-1', 4, 'Registered', '50', '0', '50', '0', 'draft')");
$a->exec("INSERT INTO sale_items (id, sale_id, product_id, qty, unit_price, returned_qty) VALUES (4, 12, 2, '1', '50', '0')");
try {
    $service->commit('D-1', [['sale_line_id' => 4, 'qty' => '1']], 'key-draft', true);
    check(false, 'draft should fail');
} catch (ReturnException $error) {
    check($error->errorCode === 'RETURN_NOT_ALLOWED', 'draft blocked');
}

$b = pdo();
shopB($b);
$other = new MemoReturnService($b, Schema::detect($b));
$alias = $other->commit('B-9', [['sale_line_id' => 5, 'qty' => '1']], 'key-b', true);
check($alias['balance_before'] === '800.00', 'alias previous');
check($alias['account_minus'] === '100.00', 'alias minus');
check($alias['balance_after'] === '700.00', 'alias total');
check($b->query('SELECT due_amount FROM clients WHERE id = 7')->fetchColumn() === '700.00', 'due_amount column updated');
check($b->query('SELECT qty_on_hand FROM items WHERE id = 3')->fetchColumn() === '5', 'qty_on_hand restocked');

echo "OK {$passed} checks\n";
