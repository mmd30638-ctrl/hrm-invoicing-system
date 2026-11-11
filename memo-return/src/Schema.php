<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

final class SchemaException extends RuntimeException
{
}

final class Schema
{
    /** @param array<string, string|null> $columns */
    private function __construct(
        public readonly string $driver,
        public readonly array $columns
    ) {
    }

    public static function detect(PDO $pdo): self
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $tables = self::tables($pdo, $driver);
        $sales = self::pick($tables, [
            'sales', 'sale', 'invoices', 'invoice', 'sale_invoices', 'pos_sales', 'tbl_sales',
        ], [
            'memo' => ['invoice_no', 'memo_no', 'memo_number', 'bill_no', 'sale_no', 'invoice_number', 'voucher_no', 'memo'],
            'customer_id' => ['customer_id', 'cust_id', 'client_id'],
            'total' => ['grand_total', 'net_total', 'payable', 'invoice_total', 'final_total', 'total_amount', 'total'],
        ], [
            'customer_type' => ['customer_type', 'cus_type'],
            'subtotal' => ['subtotal', 'sub_total', 'gross_total'],
            'discount' => ['discount', 'discount_amount', 'discount_taka', 'total_discount'],
            'paid' => ['paid', 'paid_amount', 'amount_paid', 'payment_total', 'total_paid'],
            'vat_amount' => ['vat_amount', 'tax_amount'],
            'vat_percent' => ['vat_percent', 'tax_percent', 'vat_rate'],
            'rounding' => ['rounding', 'round_off', 'round_amount'],
            'status' => ['status', 'sale_status', 'invoice_status'],
            'id' => ['id'],
        ]);
        $lines = self::pick($tables, [
            'sale_items', 'sales_items', 'invoice_lines', 'invoice_items', 'sale_details', 'sales_details', 'invoice_details',
        ], [
            'sale_id' => ['sale_id', 'invoice_id', 'sales_id'],
            'product_id' => ['product_id', 'item_id', 'prod_id'],
            'qty' => ['qty', 'quantity', 'sale_qty'],
            'price' => ['unit_price', 'rate', 'price', 'sale_price', 'current_price'],
        ], [
            'returned' => ['returned_qty', 'return_qty', 'qty_returned'],
            'id' => ['id'],
        ]);
        $customers = self::pick($tables, [
            'customers', 'customer', 'clients', 'client', 'tbl_customers',
        ], [
            'balance' => ['balance', 'due', 'due_amount', 'current_due', 'due_balance', 'outstanding'],
        ], [
            'name' => ['name', 'customer_name', 'full_name'],
            'id' => ['id'],
        ], [], [
            ['invoice_no', 'memo_no', 'memo_number', 'bill_no', 'sale_no', 'invoice_number', 'voucher_no', 'memo'],
        ]);
        $products = self::pick($tables, [
            'products', 'product', 'items', 'inventory', 'tbl_products', 'stocks',
        ], [
            'stock' => ['stock', 'current_stock', 'qty_on_hand', 'stock_qty', 'quantity_on_hand', 'in_stock'],
        ], [
            'name' => ['name', 'product_name', 'title'],
            'id' => ['id'],
        ], [], [
            ['sale_id', 'invoice_id', 'sales_id'],
        ]);
        $ledger = self::pickOptional($tables, [
            'customer_ledger', 'customer_ledgers', 'client_ledger',
        ], [
            'customer_id' => ['customer_id', 'cust_id', 'client_id'],
            'credit' => ['credit', 'cr', 'credit_amount'],
        ], [
            'debit' => ['debit', 'dr', 'debit_amount'],
            'note' => ['note', 'narration', 'description', 'particulars', 'details'],
            'created_at' => ['created_at', 'entry_date', 'date'],
        ]);

        if ($sales['table'] === $lines['table'] || $products['table'] === $lines['table']) {
            throw new SchemaException('Sales, sale lines, and products resolved to the same table.');
        }

        $columns = [
            'sales_table' => $sales['table'],
            'sales_id' => $sales['columns']['id'] ?? 'id',
            'memo' => $sales['columns']['memo'],
            'sale_customer_id' => $sales['columns']['customer_id'],
            'customer_type' => $sales['columns']['customer_type'] ?? null,
            'total' => $sales['columns']['total'],
            'subtotal' => $sales['columns']['subtotal'] ?? null,
            'discount' => $sales['columns']['discount'] ?? null,
            'paid' => $sales['columns']['paid'] ?? null,
            'vat_amount' => $sales['columns']['vat_amount'] ?? null,
            'vat_percent' => $sales['columns']['vat_percent'] ?? null,
            'rounding' => $sales['columns']['rounding'] ?? null,
            'status' => $sales['columns']['status'] ?? null,
            'lines_table' => $lines['table'],
            'line_id' => $lines['columns']['id'] ?? 'id',
            'line_sale_id' => $lines['columns']['sale_id'],
            'line_product_id' => $lines['columns']['product_id'],
            'line_qty' => $lines['columns']['qty'],
            'line_price' => $lines['columns']['price'],
            'line_returned' => $lines['columns']['returned'] ?? null,
            'customers_table' => $customers['table'],
            'customer_id' => $customers['columns']['id'] ?? 'id',
            'customer_balance' => $customers['columns']['balance'],
            'customer_name' => $customers['columns']['name'] ?? null,
            'products_table' => $products['table'],
            'product_id' => $products['columns']['id'] ?? 'id',
            'product_stock' => $products['columns']['stock'],
            'product_name' => $products['columns']['name'] ?? null,
            'ledger_table' => $ledger['table'] ?? null,
            'ledger_customer_id' => $ledger['columns']['customer_id'] ?? null,
            'ledger_credit' => $ledger['columns']['credit'] ?? null,
            'ledger_debit' => $ledger['columns']['debit'] ?? null,
            'ledger_note' => $ledger['columns']['note'] ?? null,
            'ledger_created_at' => $ledger['columns']['created_at'] ?? null,
        ];
        return new self($driver, $columns);
    }

    /** @param array<string, mixed> $map */
    public static function fromArray(string $driver, array $map): self
    {
        $required = [
            'sales_table', 'memo', 'sale_customer_id', 'total', 'lines_table', 'line_sale_id',
            'line_product_id', 'line_qty', 'line_price', 'customers_table', 'customer_balance',
            'products_table', 'product_stock',
        ];
        foreach ($required as $key) {
            if (!isset($map[$key]) || !is_string($map[$key]) || $map[$key] === '') {
                throw new SchemaException('Schema map is missing ' . $key . '.');
            }
        }
        $map += [
            'sales_id' => 'id',
            'line_id' => 'id',
            'customer_id' => 'id',
            'product_id' => 'id',
        ];
        $clean = [];
        foreach ($map as $key => $value) {
            if ($value === null) {
                $clean[$key] = null;
                continue;
            }
            if (!is_string($value)) {
                throw new SchemaException('Schema map values must be strings.');
            }
            $clean[$key] = $value;
        }
        return new self($driver, $clean);
    }

    public function col(string $key): string
    {
        $value = $this->columns[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new SchemaException('Schema has no column for ' . $key . '.');
        }
        return $value;
    }

    public function optional(string $key): ?string
    {
        $value = $this->columns[$key] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function quote(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new SchemaException('Unsafe identifier.');
        }
        if ($this->driver === 'mysql') {
            return '`' . $identifier . '`';
        }
        return '"' . $identifier . '"';
    }

    /** @return array<string, array<string, string>> */
    private static function tables(PDO $pdo, string $driver): array
    {
        $tables = [];
        if ($driver === 'sqlite') {
            $names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($names as $name) {
                if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                    continue;
                }
                $info = $pdo->query('PRAGMA table_info("' . $name . '")')->fetchAll(PDO::FETCH_ASSOC);
                $columns = [];
                foreach ($info as $column) {
                    $columns[strtolower((string) $column['name'])] = (string) $column['name'];
                }
                $tables[$name] = $columns;
            }
            return $tables;
        }
        $statement = $pdo->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
        if ($statement === false) {
            throw new SchemaException('Could not read database columns.');
        }
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $table = (string) $row['TABLE_NAME'];
            $column = (string) $row['COLUMN_NAME'];
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
                continue;
            }
            $tables[$table][strtolower($column)] = $column;
        }
        return $tables;
    }

    /**
     * @param array<string, array<string, string>> $tables
     * @param list<string> $nameHints
     * @param array<string, list<string>> $required
     * @param array<string, list<string>> $optional
     * @param list<list<string>> $rejectSynonyms
     * @return array{table: string, columns: array<string, string>}
     */
    private static function pick(array $tables, array $nameHints, array $required, array $optional, array $rejectSynonyms = []): array
    {
        $found = self::rank($tables, $nameHints, $required, $optional, $rejectSynonyms);
        if ($found === []) {
            throw new SchemaException('Could not find a table for ' . implode(', ', array_keys($required)) . '.');
        }
        usort($found, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        if (isset($found[1]) && ($found[0]['score'] - $found[1]['score']) < 3) {
            throw new SchemaException('Ambiguous tables: ' . $found[0]['table'] . ' and ' . $found[1]['table'] . '.');
        }
        return ['table' => $found[0]['table'], 'columns' => $found[0]['columns']];
    }

    /**
     * @param array<string, array<string, string>> $tables
     * @param list<string> $nameHints
     * @param array<string, list<string>> $required
     * @param array<string, list<string>> $optional
     * @return array{table: string, columns: array<string, string>}|array{}
     */
    private static function pickOptional(array $tables, array $nameHints, array $required, array $optional): array
    {
        $found = self::rank($tables, $nameHints, $required, $optional, []);
        if ($found === []) {
            return [];
        }
        usort($found, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        if (isset($found[1]) && ($found[0]['score'] - $found[1]['score']) < 3) {
            return [];
        }
        return ['table' => $found[0]['table'], 'columns' => $found[0]['columns']];
    }

    /**
     * @param array<string, array<string, string>> $tables
     * @param list<string> $nameHints
     * @param array<string, list<string>> $required
     * @param array<string, list<string>> $optional
     * @param list<list<string>> $rejectSynonyms
     * @return list<array{score: int, table: string, columns: array<string, string>}>
     */
    private static function rank(array $tables, array $nameHints, array $required, array $optional, array $rejectSynonyms): array
    {
        $found = [];
        foreach ($tables as $table => $columns) {
            foreach ($rejectSynonyms as $synonyms) {
                if (self::matchColumn($columns, $synonyms) !== null) {
                    continue 2;
                }
            }
            $matched = [];
            $score = 0;
            $ok = true;
            foreach ($required as $key => $synonyms) {
                $column = self::matchColumn($columns, $synonyms);
                if ($column === null) {
                    $ok = false;
                    break;
                }
                $matched[$key] = $column;
                $score += 5;
            }
            if (!$ok) {
                continue;
            }
            if (in_array(strtolower($table), $nameHints, true)) {
                $score += 8;
            }
            foreach ($optional as $key => $synonyms) {
                $column = self::matchColumn($columns, $synonyms);
                if ($column !== null && !isset($matched[$key])) {
                    $matched[$key] = $column;
                    $score += 2;
                }
            }
            if (!isset($matched['id']) && isset($columns['id'])) {
                $matched['id'] = $columns['id'];
            }
            $found[] = ['score' => $score, 'table' => $table, 'columns' => $matched];
        }
        return $found;
    }

    /** @param array<string, string> $columns @param list<string> $synonyms */
    private static function matchColumn(array $columns, array $synonyms): ?string
    {
        foreach ($synonyms as $synonym) {
            if (isset($columns[$synonym])) {
                return $columns[$synonym];
            }
        }
        return null;
    }
}
