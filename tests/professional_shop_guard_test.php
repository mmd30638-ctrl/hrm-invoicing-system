<?php
/**
 * Guards for the professional shop patch.
 * Local database only. Customer and supplier balances must stay unchanged.
 */
mysqli_report(MYSQLI_REPORT_OFF);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$private = sys_get_temp_dir() . '/ame-pro-private';
if (!is_dir($private)) {
    mkdir($private, 0700, true);
}
define('AME_PRIVATE_DATA_DIR', $private);

$root = dirname(__DIR__) . '/deploy/public_html';
require $root . '/includes/memo_access.php';
require $root . '/includes/stock_lock.php';
require $root . '/includes/money_read.php';
require $root . '/includes/unit_engine.php';

function fail_test(string $message): void
{
    fwrite(STDERR, "FAIL {$message}\n");
    exit(1);
}

function check(bool $ok, string $message): void
{
    if (!$ok) {
        fail_test($message);
    }
}

$db = new mysqli('127.0.0.1', 'ame', 'amepass');
if ($db->connect_error) {
    fail_test('local MariaDB: ' . $db->connect_error);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!$db->select_db('ame_mavo_publish_test')) {
    fail_test('select test database');
}
$db->query('DROP TABLE IF EXISTS unit_stock_ledger, sale_payments, sales, customer_ledger, customers, suppliers, products');
$db->query("CREATE TABLE products (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    stock DECIMAL(15,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB");
$db->query("CREATE TABLE customers (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    is_customer TINYINT NOT NULL DEFAULT 1,
    receivable_balance DECIMAL(15,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB");
$db->query("CREATE TABLE suppliers (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    total_due DECIMAL(15,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB");
$db->query("CREATE TABLE customer_ledger (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    debit DECIMAL(15,2) NOT NULL DEFAULT 0,
    credit DECIMAL(15,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB");
$db->query("CREATE TABLE sales (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    sale_date DATE NULL,
    created_at DATETIME NULL,
    completed_at DATETIME NULL
) ENGINE=InnoDB");
$db->query("CREATE TABLE sale_payments (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    payment_status VARCHAR(20) NOT NULL
) ENGINE=InnoDB");

$db->query("INSERT INTO products (stock) VALUES (10)");
$productId = (int) $db->insert_id;
$db->query("INSERT INTO customers (is_customer, receivable_balance) VALUES (1, 1800)");
$customerId = (int) $db->insert_id;
$db->query("INSERT INTO customer_ledger (customer_id, debit, credit) VALUES ({$customerId}, 1800, 0)");
$db->query('INSERT INTO suppliers (total_due) VALUES (900)');

$beforeCustomer = '1800.00';
$beforeSupplier = '900.00';

ame_unit_move($db, $productId, '-3', ['kind' => 'sale', 'note' => 'test'], true);
ame_unit_move($db, $productId, '-2', ['kind' => 'sale', 'note' => 'test'], true);
$stock = $db->query("SELECT stock FROM products WHERE id = {$productId}")->fetch_assoc()['stock'];
check($stock === '5.00', 'stock moved by the exact quantity, got ' . $stock);

$blocked = false;
try {
    ame_unit_move($db, $productId, '-6', ['kind' => 'sale', 'note' => 'too much'], true);
} catch (Throwable $error) {
    $blocked = true;
}
check($blocked, 'a move larger than stock is refused');
$stock = $db->query("SELECT stock FROM products WHERE id = {$productId}")->fetch_assoc()['stock'];
check($stock === '5.00', 'refused move left stock unchanged');

$db->begin_transaction();
ame_lock_product_row($db, $productId);
$db2 = new mysqli('127.0.0.1', 'ame', 'amepass', 'ame_mavo_publish_test');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db2->query('SET innodb_lock_wait_timeout = 1');
$waited = false;
try {
    $stmt = $db2->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $error) {
    $waited = true;
}
$db->rollback();
check($waited, 'a second sale waits on the product row lock');

$token = ame_memo_token(41);
check($token !== '' && ame_memo_token_ok(41, $token), 'share token matches');
check(!ame_memo_token_ok(41, 'wrong') && !ame_memo_token_ok(42, $token), 'guessed token is refused');
check(!str_contains(ame_memo_public_url(41), 'id=41&id='), 'public memo url is a single link');
check(str_contains(ame_memo_public_url(41), 't='), 'public memo url carries the token');

$today = date('Y-m-d');
$db->query("INSERT INTO sales (paid_amount, status, sale_date) VALUES (100, 'completed', '{$today}')");
$saleOk = (int) $db->insert_id;
$db->query("INSERT INTO sale_payments (sale_id, amount, payment_status) VALUES ({$saleOk}, 100, 'posted')");
$db->query("INSERT INTO sales (paid_amount, status, sale_date) VALUES (50, 'completed', '{$today}')");
$saleBad = (int) $db->insert_id;
$db->query("INSERT INTO sale_payments (sale_id, amount, payment_status) VALUES ({$saleBad}, 40, 'posted')");
$gap = ame_money_sales_gap($db, $today, $today);
check($gap['invoices'] === 2 && $gap['mismatch'] === 1, 'full-day gap sees every invoice');
check(ame_money_customer_drift_count($db) === 0, 'matching ledger is not reported as drift');

$customer = $db->query("SELECT receivable_balance FROM customers WHERE id = {$customerId}")->fetch_assoc()['receivable_balance'];
$supplier = $db->query('SELECT total_due FROM suppliers WHERE id = 1')->fetch_assoc()['total_due'];
check($customer === $beforeCustomer && $supplier === $beforeSupplier, 'customer and supplier amounts stayed unchanged');

$forbidden = [];
$scan = [
    $root . '/includes/memo_access.php',
    $root . '/includes/stock_lock.php',
    $root . '/includes/money_read.php',
    $root . '/includes/unit_engine.php',
    $root . '/modules/reports/integrity_check.php',
    $root . '/modules/reports/day_closing.php',
    $root . '/modules/returns/add.php',
];
foreach ($scan as $file) {
    $text = (string) file_get_contents($file);
    if (preg_match('/UPDATE\s+`?(customers|suppliers)`?/i', $text)) {
        $forbidden[] = $file;
    }
}
check($forbidden === [], 'patched files do not update customer or supplier balances');
check(str_contains((string) file_get_contents($root . '/includes/daily_backup.php'), 'ame_db_backup_run($db, $dir, 30)'), 'backup retention is 30 days');
check(str_contains((string) file_get_contents($root . '/modules/returns/add.php'), 'create_invoice_return.php'), 'old return form redirects to v2');

echo "OK professional shop guard\n";
