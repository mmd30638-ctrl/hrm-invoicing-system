<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/return_v2_functions.php';
require_once dirname(__DIR__) . '/includes/attendance_functions.php';
require_once dirname(__DIR__) . '/includes/staff_duty.php';
require_once dirname(__DIR__) . '/modules/customers/customer_ledger_functions.php';

function ame_fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function ame_expect(bool $ok, string $message): void
{
    if (!$ok) {
        ame_fail($message);
    }
}

ame_expect(abs(ame_return_due_after(1500.00, 400.50) - 1099.50) < 0.001, 'due minus return');
ame_expect(abs(ame_return_due_after(100.00, 250.00) - (-150.00)) < 0.001, 'return larger than due becomes advance');
ame_expect(ame_return_is_registered_invoice(false, 8), 'registered invoice');
ame_expect(!ame_return_is_registered_invoice(true, 8), 'manual is not the invoice path');
ame_expect(!ame_return_is_registered_invoice(false, 0), 'walk-in is not registered');
ame_expect(ame_staff_is_morning_leader('manager', 0), 'manager leads the morning');
ame_expect(ame_staff_is_morning_leader('cashier', 1), 'super admin flag leads');
ame_expect(ame_staff_is_morning_leader('super_admin', 0), 'super admin role leads');
ame_expect(!ame_staff_is_morning_leader('cashier', 0), 'cashier stays off');
ame_expect(!ame_staff_is_morning_leader('admin', 0), 'plain admin is not auto-on');

$js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/ame-idle-lock.js');
ame_expect(str_contains($js, '20 * 60 * 1000'), 'browser lock is 20 minutes');
ame_expect(str_contains($js, '/logout.php?idle=1'), 'browser lock logs out');
$cfg = (string) file_get_contents(dirname(__DIR__) . '/includes/config.php');
ame_expect(str_contains($cfg, '$idle_limit = 20 * 60;'), 'server lock is 20 minutes');
ame_expect(str_contains($cfg, 'session_ping.php'), 'ping does not extend the lock');
$view = (string) file_get_contents(dirname(__DIR__) . '/modules/returns/v2/view.php');
foreach (['সাবেক বকেয়া', 'সর্বমোট ব্যালেন্স', 'data-ret-act="share"', 'WhatsApp', 'share-telegram', 'memo-container'] as $needle) {
    ame_expect(str_contains($view, $needle), 'memo missing ' . $needle);
}
$save = (string) file_get_contents(dirname(__DIR__) . '/modules/returns/v2/actions/save_invoice_return.php');
ame_expect(str_contains($save, 'ame_return_is_registered_invoice'), 'registered invoice forces due cut');

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli('127.0.0.1', 'ame', 'amepass', '', 3306);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if ($db->connect_errno) {
    ame_fail('mysql: ' . $db->connect_error);
}
$db->set_charset('utf8mb4');
$db->query('CREATE DATABASE IF NOT EXISTS ame_return_lock_test');
$db->select_db('ame_return_lock_test');
$db->query('DROP TABLE IF EXISTS attendance, users, employees, customers, customer_ledger, sale_returns_v2');
$db->query("CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    removed_at DATETIME NULL,
    purged_at DATETIME NULL
) ENGINE=InnoDB");
$db->query("CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(100) NOT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    employee_id INT NULL,
    is_super_admin TINYINT NOT NULL DEFAULT 0,
    removed_at DATETIME NULL,
    purged_at DATETIME NULL
) ENGINE=InnoDB");
$db->query("CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    customer_code VARCHAR(30) NOT NULL,
    receivable_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    payable_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_supplier TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB");
$db->query("CREATE TABLE customer_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    module_type VARCHAR(50) NULL,
    ref_no VARCHAR(100) NULL,
    description TEXT NULL,
    debit DECIMAL(12,2) DEFAULT 0,
    credit DECIMAL(12,2) DEFAULT 0,
    running_balance DECIMAL(12,2) DEFAULT 0,
    txn_date DATE NULL,
    balance_field VARCHAR(30) NOT NULL DEFAULT 'receivable_balance'
) ENGINE=InnoDB");

ame_staff_duty_schema($db);
ensure_return_v2_schema($db);

$db->query("INSERT INTO employees (id, name) VALUES (1, 'Manager Mia'), (2, 'Super Ali'), (3, 'Cashier Rina')");
$db->query("INSERT INTO users (id, username, password, role, employee_id, is_super_admin) VALUES
    (1, 'manager1', 'x', 'manager', 1, 0),
    (2, 'super1', 'x', 'admin', 2, 1),
    (3, 'cash1', 'x', 'cashier', 3, 0)");

$zone = new DateTimeZone('Asia/Dhaka');
ame_staff_duty_set_clock(new DateTime('2026-11-12 08:30:00', $zone));
@unlink(ame_staff_morning_stamp_path());
ame_expect(ame_staff_morning_auto_on($db) === 0, 'before 9am nobody auto-starts');
ame_expect(!ame_staff_is_duty_active($db, 1), 'manager still off at 8:30');

ame_staff_duty_set_clock(new DateTime('2026-11-12 09:05:00', $zone));
$started = ame_staff_morning_auto_on($db);
ame_expect($started === 2, 'manager and super admin start, got ' . $started);
ame_expect(ame_staff_is_duty_active($db, 1), 'manager is on');
ame_expect(ame_staff_is_duty_active($db, 2), 'super admin is on');
ame_expect(!ame_staff_is_duty_active($db, 3), 'cashier stays off');

$slept = ame_staff_sleep($db, 1, 'manual_off');
ame_expect(is_array($slept) && empty($slept['already']) && empty($slept['present']), 'manual off');
ame_expect(!ame_staff_is_duty_active($db, 1), 'manager is off after manual off');
ame_expect(ame_staff_morning_auto_on($db) === 0, 'same morning does not turn them back on');
ame_expect(!ame_staff_is_duty_active($db, 1), 'manual off sticks');
ame_expect(ame_staff_is_duty_active($db, 2), 'super admin stays on');

$db->query("INSERT INTO customers (id, name, customer_code, receivable_balance) VALUES (7, 'Karim', 'CUS-000007', 1800.00)");
$before = ame_return_customer_due($db, 7);
ame_expect(abs($before - 1800) < 0.001, 'opening due');
add_ledger_entry($db, 7, 'বিক্রয় ফেরত', 'SR-TEST', 'ফেরত', 0, 450.00, '2026-11-12');
$after = ame_return_customer_due($db, 7);
ame_expect(abs($after - ame_return_due_after($before, 450)) < 0.001, 'ledger due fell by the return, got ' . $after);
ame_return_store_due_snapshot($db, 0, $before, $after);
$db->query("INSERT INTO sale_returns_v2 (return_no, customer_id, customer_name, return_amount, refund_method, return_type, status)
    VALUES ('SR-TEST', 7, 'Karim', 450.00, 'customer_balance', 'invoice_based', 'completed')");
$rid = (int) $db->insert_id;
ame_return_store_due_snapshot($db, $rid, $before, $after);
$got = $db->query('SELECT previous_due, balance_after FROM sale_returns_v2 WHERE id = ' . $rid)->fetch_assoc();
ame_expect(abs((float) $got['previous_due'] - 1800) < 0.001, 'memo stores previous due');
ame_expect(abs((float) $got['balance_after'] - 1350) < 0.001, 'memo stores total balance');

echo "OK registered return, 20-minute lock, and 9am duty\n";
