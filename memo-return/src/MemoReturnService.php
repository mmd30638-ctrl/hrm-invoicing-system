<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

final class ReturnException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}

/**
 * Memo return.
 *
 * A registered customer's balance is reduced by the net value of the goods
 * taken back. Stock is increased by the returned quantity. The same
 * idempotency key cannot post twice. A retail memo changes stock only.
 */
final class MemoReturnService
{
    private bool $manualTx = false;

    public function __construct(private readonly PDO $pdo, private readonly Schema $schema)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** @param list<array{sale_line_id?: int|string, qty?: string}> $requests */
    /** @return array<string, mixed> */
    public function inspect(string $memo): array
    {
        $this->ensureLedger();
        $memo = trim($memo);
        if ($memo === '' || strlen($memo) > 64) {
            throw new ReturnException('MEMO_REQUIRED', 'Enter the memo number.');
        }
        $sales = $this->loadSales($memo, false);
        if ($sales === []) {
            throw new ReturnException('MEMO_NOT_FOUND', 'No sale was found for this memo number.');
        }
        if (count($sales) > 1) {
            throw new ReturnException('MEMO_AMBIGUOUS', 'More than one sale uses this memo number.');
        }
        $sale = $sales[0];
        $status = strtolower(trim((string) ($sale['status'] ?? '')));
        if (in_array($status, ['draft', 'cancelled', 'canceled', 'void', 'hold', 'quotation'], true)) {
            throw new ReturnException('RETURN_NOT_ALLOWED', 'This memo is not a completed sale.');
        }
        $registered = $this->isRegistered($sale);
        $customer = $registered ? $this->lockCustomer((int) $sale['customer_id'], false) : null;
        $already = $this->alreadyReturned((int) $sale['id']);
        $lines = [];
        foreach ($this->loadLines((int) $sale['id'], false) as $line) {
            $sold = Money::milli((string) $line['qty']);
            $columnReturned = isset($line['returned_qty']) && $line['returned_qty'] !== null && $line['returned_qty'] !== ''
                ? Money::milli((string) $line['returned_qty'])
                : 0;
            $used = max($columnReturned, $already[(int) $line['id']] ?? 0);
            $remaining = max(0, $sold - $used);
            $lines[] = [
                'sale_line_id' => (int) $line['id'],
                'name' => (string) ($line['name'] ?? ''),
                'product_id' => (int) $line['product_id'],
                'sold' => Money::formatMilli($sold),
                'remaining' => Money::formatMilli($remaining),
                'remaining_raw' => Money::plainMilli($remaining),
                'rate' => Money::formatPoisha(Money::poisha((string) $line['price'])),
            ];
        }
        return [
            'memo' => (string) $sale['memo'],
            'registered' => $registered,
            'customer_name' => $customer['name'] ?? '',
            'previous_due' => $customer === null ? null : Money::formatPoisha(Money::poisha((string) $customer['balance'])),
            'lines' => $lines,
        ];
    }

    public function preview(string $memo, array $requests, bool $minusAccount = true): array
    {
        $this->ensureLedger();
        return $this->calculate(trim($memo), $requests, $minusAccount, false)['result'];
    }

    public function findByNo(string $returnNo): array
    {
        $this->ensureLedger();
        $header = $this->fetchOne(
            'SELECT * FROM ' . $this->schema->quote('ame_memo_returns') . ' WHERE return_no = ?',
            [trim($returnNo)]
        );
        if ($header === null) {
            throw new ReturnException('RETURN_NOT_FOUND', 'Return memo was not found.');
        }
        return $this->storedResult($header, false);
    }

    public function findByToken(string $token): array
    {
        $this->ensureLedger();
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new ReturnException('RETURN_NOT_FOUND', 'Return memo was not found.');
        }
        $header = $this->fetchOne(
            'SELECT * FROM ' . $this->schema->quote('ame_memo_returns') . ' WHERE share_token = ?',
            [$token]
        );
        if ($header === null) {
            throw new ReturnException('RETURN_NOT_FOUND', 'Return memo was not found.');
        }
        return $this->storedResult($header, false);
    }

    /** @param list<array{sale_line_id?: int|string, qty?: string}> $requests */
    public function commit(string $memo, array $requests, string $idempotencyKey, bool $minusAccount = true): array
    {
        $this->ensureLedger();
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 80) {
            throw new ReturnException('IDEMPOTENCY_KEY', 'A return reference is required.');
        }
        $this->begin();
        try {
            $existing = $this->fetchOne(
                'SELECT * FROM ' . $this->schema->quote('ame_memo_returns') . ' WHERE idempotency_key = ?',
                [$idempotencyKey]
            );
            if ($existing !== null) {
                $result = $this->storedResult($existing, true);
                $this->commitTx();
                return $result;
            }
            $calculated = $this->calculate(trim($memo), $requests, $minusAccount, true);
            $saved = $this->apply($calculated, $idempotencyKey);
            $result = $calculated['result'];
            $result['return_no'] = $saved['return_no'];
            $result['share_token'] = $saved['share_token'];
            $result['replayed'] = false;
            $this->commitTx();
            return $result;
        } catch (Throwable $error) {
            $this->rollback();
            if ($error instanceof ReturnException || $error instanceof InvalidArgumentException) {
                throw $error;
            }
            throw new ReturnException('RETURN_FAILED', 'The return could not be saved.');
        }
    }

    public function void(string $returnNo): array
    {
        $this->ensureLedger();
        $returnNo = trim($returnNo);
        $this->begin();
        try {
            $header = $this->fetchOne(
                'SELECT * FROM ' . $this->schema->quote('ame_memo_returns') . ' WHERE return_no = ?',
                [$returnNo]
            );
            if ($header === null) {
                throw new ReturnException('RETURN_NOT_FOUND', 'Return document was not found.');
            }
            if (($header['voided_at'] ?? null) !== null && $header['voided_at'] !== '') {
                throw new ReturnException('ALREADY_VOID', 'This return is already void.');
            }
            $lines = $this->fetchAll(
                'SELECT * FROM ' . $this->schema->quote('ame_memo_return_lines') . ' WHERE return_id = ? ORDER BY id',
                [(int) $header['id']]
            );
            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];
                if ($productId <= 0) {
                    continue;
                }
                $product = $this->lockProduct($productId);
                $stock = Money::milli((string) $product['stock']);
                $qty = (int) $line['qty_milli'];
                if ($stock < $qty) {
                    throw new ReturnException('STOCK_ALREADY_USED', 'Void stopped because the returned stock has already been sold again.');
                }
                $this->updateStock($productId, $stock - $qty);
                $this->addReturnedQty((int) $line['sale_line_id'], -$qty);
            }
            $customerId = (int) ($header['customer_id'] ?? 0);
            $minus = (int) $header['account_minus_poisha'];
            $balanceBefore = null;
            $balanceAfter = null;
            if ((int) $header['registered'] === 1 && $customerId > 0 && $minus !== 0) {
                $customer = $this->lockCustomer($customerId);
                $balanceBefore = Money::poisha((string) $customer['balance']);
                $balanceAfter = $balanceBefore + $minus;
                $this->updateBalance($customerId, $balanceAfter);
            }
            $this->pdo->prepare(
                'UPDATE ' . $this->schema->quote('ame_memo_returns') . ' SET voided_at = ? WHERE id = ? AND voided_at IS NULL'
            )->execute([(new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'), (int) $header['id']]);
            $this->commitTx();
            return [
                'ok' => true,
                'return_no' => $returnNo,
                'voided' => true,
                'balance_before' => $balanceBefore === null ? null : Money::formatPoisha($balanceBefore),
                'balance_after' => $balanceAfter === null ? null : Money::formatPoisha($balanceAfter),
            ];
        } catch (Throwable $error) {
            $this->rollback();
            if ($error instanceof ReturnException) {
                throw $error;
            }
            throw new ReturnException('VOID_FAILED', 'The return could not be voided.');
        }
    }

    /**
     * @param list<array{sale_line_id?: int|string, qty?: string}> $requests
     * @return array{result: array<string, mixed>, write: array<string, mixed>}
     */
    private function calculate(string $memo, array $requests, bool $minusAccount, bool $lock): array
    {
        if ($memo === '' || strlen($memo) > 64) {
            throw new ReturnException('MEMO_REQUIRED', 'Enter the memo number.');
        }
        if (count($requests) > 200) {
            throw new ReturnException('TOO_MANY_LINES', 'Too many return lines.');
        }
        $sales = $this->loadSales($memo, $lock);
        if ($sales === []) {
            throw new ReturnException('MEMO_NOT_FOUND', 'No sale was found for this memo number.');
        }
        if (count($sales) > 1) {
            throw new ReturnException('MEMO_AMBIGUOUS', 'More than one sale uses this memo number.');
        }
        $sale = $sales[0];
        $status = strtolower(trim((string) ($sale['status'] ?? '')));
        if (in_array($status, ['draft', 'cancelled', 'canceled', 'void', 'hold', 'quotation'], true)) {
            throw new ReturnException('RETURN_NOT_ALLOWED', 'This memo is not a completed sale.');
        }
        $saleLines = $this->loadLines((int) $sale['id'], $lock);
        if ($saleLines === []) {
            throw new ReturnException('MEMO_EMPTY', 'This memo has no product lines.');
        }
        $already = $this->alreadyReturned((int) $sale['id']);
        $chosen = $this->resolveRequests($saleLines, $requests, $already);
        $saleGross = 0;
        foreach ($saleLines as $line) {
            $saleGross += $this->lineGross($line);
        }
        $returnGross = 0;
        foreach ($chosen as $line) {
            $returnGross += $line['gross'];
        }
        $discount = $this->moneyColumn($sale, 'discount');
        $vatAmount = $this->moneyColumn($sale, 'vat_amount');
        $rounding = $this->moneyColumn($sale, 'rounding');
        $base = $saleGross > 0 ? $saleGross : 1;
        $returnNet = $returnGross - Money::share($returnGross, $base, $discount) + Money::share($returnGross, $base, $vatAmount) + Money::share($returnGross, $base, $rounding);
        if ($this->schema->optional('vat_amount') === null && $this->schema->optional('vat_percent') !== null) {
            $percent = Money::parse((string) ($sale['vat_percent'] ?? '0'), 2);
            if ($percent > 0) {
                $afterDiscount = $returnGross - Money::share($returnGross, $base, $discount);
                $returnNet += intdiv($afterDiscount * $percent + 5000, 10000);
            }
        }
        if ($returnNet < 0) {
            $returnNet = 0;
        }
        $registered = $this->isRegistered($sale);
        $customer = null;
        $balanceBefore = null;
        $accountMinus = 0;
        if ($registered) {
            $customer = $this->lockCustomer((int) $sale['customer_id'], $lock);
            $balanceBefore = Money::poisha((string) $customer['balance']);
            if ($minusAccount) {
                $accountMinus = $returnNet;
            }
        }
        $balanceAfter = $balanceBefore === null ? null : $balanceBefore - $accountMinus;
        $lineNets = $this->splitNets($chosen, $returnGross, $returnNet);
        $resultLines = [];
        $writeLines = [];
        foreach ($chosen as $index => $line) {
            $productId = (int) $line['product_id'];
            $stockBefore = null;
            $stockAfter = null;
            if ($productId > 0) {
                $product = $this->lockProduct($productId, $lock);
                $stockBefore = Money::milli((string) $product['stock']);
                $stockAfter = $stockBefore + $line['qty'];
            }
            $resultLines[] = [
                'sale_line_id' => $line['id'],
                'product_id' => $productId,
                'name' => (string) ($line['name'] ?? ''),
                'qty' => Money::formatMilli($line['qty']),
                'rate' => Money::formatPoisha($line['price']),
                'line_net' => Money::formatPoisha($lineNets[$index]),
                'stock_before' => $stockBefore === null ? null : Money::formatMilli($stockBefore),
                'stock_after' => $stockAfter === null ? null : Money::formatMilli($stockAfter),
            ];
            $writeLines[] = [
                'sale_line_id' => $line['id'],
                'product_id' => $productId,
                'name' => $line['name'],
                'price' => $line['price'],
                'qty' => $line['qty'],
                'line_net' => $lineNets[$index],
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
            ];
        }
        $openDue = max(0, $this->moneyColumn($sale, 'total') - $this->moneyColumn($sale, 'paid'));
        $result = [
            'ok' => true,
            'replayed' => false,
            'return_no' => null,
            'memo' => (string) $sale['memo'],
            'sale_id' => (int) $sale['id'],
            'registered' => $registered,
            'customer_id' => $registered ? (int) $sale['customer_id'] : null,
            'customer_name' => $customer['name'] ?? null,
            'return_net' => Money::formatPoisha($returnNet),
            'account_minus' => Money::formatPoisha($accountMinus),
            'refund' => Money::formatPoisha($returnNet - $accountMinus),
            'balance_before' => $balanceBefore === null ? null : Money::formatPoisha($balanceBefore),
            'balance_after' => $balanceAfter === null ? null : Money::formatPoisha($balanceAfter),
            'memo_open_due' => Money::formatPoisha($openDue),
            'lines' => $resultLines,
        ];
        return [
            'result' => $result,
            'write' => [
                'sale' => $sale,
                'registered' => $registered,
                'customer_id' => $registered ? (int) $sale['customer_id'] : null,
                'return_net' => $returnNet,
                'account_minus' => $accountMinus,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'lines' => $writeLines,
                'fully_returned' => $this->fullyReturned($saleLines, $already, $chosen),
            ],
        ];
    }

    /**
     * @param array{result: array<string, mixed>, write: array<string, mixed>} $calculated
     * @return array{return_no: string, share_token: string}
     */
    private function apply(array $calculated, string $idempotencyKey): array
    {
        $write = $calculated['write'];
        $returnNo = $this->nextReturnNo();
        $shareToken = bin2hex(random_bytes(16));
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
        $header = $this->pdo->prepare(
            'INSERT INTO ' . $this->schema->quote('ame_memo_returns') . ' (
                return_no, idempotency_key, share_token, sale_id, memo_no, customer_id, customer_name, registered,
                return_net_poisha, account_minus_poisha, refund_poisha,
                balance_before_poisha, balance_after_poisha, voided_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)'
        );
        $header->execute([
            $returnNo,
            $idempotencyKey,
            $shareToken,
            (int) $write['sale']['id'],
            (string) $write['sale']['memo'],
            $write['customer_id'],
            (string) ($calculated['result']['customer_name'] ?? ''),
            $write['registered'] ? 1 : 0,
            $write['return_net'],
            $write['account_minus'],
            $write['return_net'] - $write['account_minus'],
            $write['balance_before'],
            $write['balance_after'],
            $now,
        ]);
        $returnId = (int) $this->pdo->lastInsertId();
        $lineInsert = $this->pdo->prepare(
            'INSERT INTO ' . $this->schema->quote('ame_memo_return_lines') . ' (
                return_id, sale_line_id, product_id, product_name, qty_milli, unit_poisha, line_net_poisha, stock_before_milli, stock_after_milli
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($write['lines'] as $line) {
            $lineInsert->execute([
                $returnId,
                $line['sale_line_id'],
                $line['product_id'],
                $line['name'],
                $line['qty'],
                $line['price'],
                $line['line_net'],
                $line['stock_before'],
                $line['stock_after'],
            ]);
            if ($line['product_id'] > 0 && $line['stock_after'] !== null) {
                $this->updateStock($line['product_id'], $line['stock_after']);
            }
            $this->addReturnedQty($line['sale_line_id'], $line['qty']);
        }
        if ($write['registered'] && $write['customer_id'] && $write['account_minus'] !== 0 && $write['balance_after'] !== null) {
            $this->updateBalance((int) $write['customer_id'], (int) $write['balance_after']);
            $this->writeCustomerLedger((int) $write['customer_id'], (int) $write['account_minus'], $returnNo, (string) $write['sale']['memo']);
        }
        $this->writeStatus($write['sale'], (bool) $write['fully_returned']);
        return ['return_no' => $returnNo, 'share_token' => $shareToken];
    }

    /** @param array<string, mixed> $header */
    private function storedResult(array $header, bool $replayed): array
    {
        $lines = $this->fetchAll(
            'SELECT * FROM ' . $this->schema->quote('ame_memo_return_lines') . ' WHERE return_id = ? ORDER BY id',
            [(int) $header['id']]
        );
        $view = [];
        foreach ($lines as $line) {
            $view[] = [
                'sale_line_id' => (int) $line['sale_line_id'],
                'product_id' => (int) $line['product_id'],
                'name' => (string) ($line['product_name'] ?? ''),
                'qty' => Money::formatMilli((int) $line['qty_milli']),
                'rate' => Money::formatPoisha((int) ($line['unit_poisha'] ?? 0)),
                'line_net' => Money::formatPoisha((int) $line['line_net_poisha']),
                'stock_before' => $line['stock_before_milli'] === null ? null : Money::formatMilli((int) $line['stock_before_milli']),
                'stock_after' => $line['stock_after_milli'] === null ? null : Money::formatMilli((int) $line['stock_after_milli']),
            ];
        }
        return [
            'ok' => true,
            'replayed' => $replayed,
            'return_no' => (string) $header['return_no'],
            'memo' => (string) $header['memo_no'],
            'sale_id' => (int) $header['sale_id'],
            'registered' => (int) $header['registered'] === 1,
            'customer_id' => $header['customer_id'] === null ? null : (int) $header['customer_id'],
            'customer_name' => (string) ($header['customer_name'] ?? ''),
            'return_net' => Money::formatPoisha((int) $header['return_net_poisha']),
            'account_minus' => Money::formatPoisha((int) $header['account_minus_poisha']),
            'refund' => Money::formatPoisha((int) $header['refund_poisha']),
            'balance_before' => $header['balance_before_poisha'] === null ? null : Money::formatPoisha((int) $header['balance_before_poisha']),
            'balance_after' => $header['balance_after_poisha'] === null ? null : Money::formatPoisha((int) $header['balance_after_poisha']),
            'memo_open_due' => null,
            'lines' => $view,
            'voided' => ($header['voided_at'] ?? null) !== null && $header['voided_at'] !== '',
            'share_token' => (string) ($header['share_token'] ?? ''),
            'created_at' => (string) ($header['created_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $sale */
    private function isRegistered(array $sale): bool
    {
        $customerId = (int) ($sale['customer_id'] ?? 0);
        if ($customerId <= 0) {
            return false;
        }
        $type = strtolower(trim((string) ($sale['customer_type'] ?? '')));
        return !in_array($type, ['retail', 'walk-in', 'walkin', 'cash', 'guest', 'খুচরা'], true);
    }

    /** @return list<array<string, mixed>> */
    private function loadSales(string $memo, bool $lock): array
    {
        $id = $this->schema->quote($this->schema->col('sales_id'));
        $memoCol = $this->schema->quote($this->schema->col('memo'));
        $sql = 'SELECT ' . $id . ' AS id, ' . $memoCol . ' AS memo, '
            . $this->schema->quote($this->schema->col('sale_customer_id')) . ' AS customer_id, '
            . $this->optionalSelect('customer_type', 'customer_type')
            . $this->schema->quote($this->schema->col('total')) . ' AS total, '
            . $this->optionalSelect('subtotal', 'subtotal')
            . $this->optionalSelect('discount', 'discount')
            . $this->optionalSelect('paid', 'paid')
            . $this->optionalSelect('vat_amount', 'vat_amount')
            . $this->optionalSelect('vat_percent', 'vat_percent')
            . $this->optionalSelect('rounding', 'rounding')
            . $this->optionalSelect('status', 'status')
            . '1 AS present FROM ' . $this->schema->quote($this->schema->col('sales_table'))
            . ' WHERE ' . $memoCol . ' = ?';
        if ($lock && $this->schema->driver === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        return $this->fetchAll($sql, [$memo]);
    }

    /** @return list<array<string, mixed>> */
    private function loadLines(int $saleId, bool $lock): array
    {
        $sql = 'SELECT ' . $this->schema->quote($this->schema->col('line_id')) . ' AS id, '
            . $this->schema->quote($this->schema->col('line_product_id')) . ' AS product_id, '
            . $this->schema->quote($this->schema->col('line_qty')) . ' AS qty, '
            . $this->schema->quote($this->schema->col('line_price')) . ' AS price, '
            . $this->optionalSelect('line_returned', 'returned_qty')
            . ($this->schema->optional('product_name') !== null
                ? '(SELECT ' . $this->schema->quote((string) $this->schema->optional('product_name'))
                    . ' FROM ' . $this->schema->quote($this->schema->col('products_table'))
                    . ' WHERE ' . $this->schema->quote($this->schema->col('product_id')) . ' = '
                    . $this->schema->quote($this->schema->col('lines_table')) . '.'
                    . $this->schema->quote($this->schema->col('line_product_id')) . ') AS name, '
                : "'' AS name, ")
            . '1 AS present FROM ' . $this->schema->quote($this->schema->col('lines_table'))
            . ' WHERE ' . $this->schema->quote($this->schema->col('line_sale_id')) . ' = ? ORDER BY '
            . $this->schema->quote($this->schema->col('line_id'));
        return $this->fetchAll($sql, [$saleId]);
    }

    /** @return array<int, int> */
    private function alreadyReturned(int $saleId): array
    {
        $rows = $this->fetchAll(
            'SELECT l.sale_line_id AS sale_line_id, SUM(l.qty_milli) AS qty
             FROM ' . $this->schema->quote('ame_memo_return_lines') . ' l
             INNER JOIN ' . $this->schema->quote('ame_memo_returns') . ' h ON h.id = l.return_id
             WHERE h.sale_id = ? AND h.voided_at IS NULL
             GROUP BY l.sale_line_id',
            [$saleId]
        );
        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['sale_line_id']] = (int) $row['qty'];
        }
        return $totals;
    }

    /**
     * @param list<array<string, mixed>> $saleLines
     * @param list<array{sale_line_id?: int|string, qty?: string}> $requests
     * @param array<int, int> $already
     * @return list<array{id: int, product_id: int, qty: int, gross: int, name: string}>
     */
    private function resolveRequests(array $saleLines, array $requests, array $already): array
    {
        if ($requests === []) {
            throw new ReturnException('LINES_REQUIRED', 'Choose at least one product to take back.');
        }
        $byId = [];
        foreach ($saleLines as $line) {
            $sold = Money::milli((string) $line['qty']);
            $columnReturned = isset($line['returned_qty']) && $line['returned_qty'] !== null && $line['returned_qty'] !== ''
                ? Money::milli((string) $line['returned_qty'])
                : 0;
            $ledgerReturned = $already[(int) $line['id']] ?? 0;
            $used = max($columnReturned, $ledgerReturned);
            $byId[(int) $line['id']] = [
                'id' => (int) $line['id'],
                'product_id' => (int) $line['product_id'],
                'remaining' => max(0, $sold - $used),
                'price' => Money::poisha((string) $line['price']),
                'name' => (string) ($line['name'] ?? ''),
            ];
        }
        $chosen = [];
        $seen = [];
        foreach ($requests as $request) {
            $lineId = (int) ($request['sale_line_id'] ?? 0);
            if (!isset($byId[$lineId])) {
                throw new ReturnException('LINE_NOT_ON_MEMO', 'A selected line is not on this memo.');
            }
            if (isset($seen[$lineId])) {
                throw new ReturnException('LINE_REPEATED', 'The same memo line was sent twice.');
            }
            $seen[$lineId] = true;
            $qty = Money::milli((string) ($request['qty'] ?? '0'));
            if ($qty <= 0) {
                throw new ReturnException('QTY_REQUIRED', 'Return quantity must be greater than zero.');
            }
            if ($qty > $byId[$lineId]['remaining']) {
                throw new ReturnException('QTY_EXCEEDED', 'Return quantity is higher than the quantity still on the memo.');
            }
            $gross = intdiv($byId[$lineId]['price'] * $qty + 500, 1000);
            $chosen[] = [
                'id' => $lineId,
                'product_id' => $byId[$lineId]['product_id'],
                'qty' => $qty,
                'gross' => $gross,
                'price' => $byId[$lineId]['price'],
                'name' => $byId[$lineId]['name'],
            ];
        }
        return $chosen;
    }

    /** @param array<string, mixed> $line */
    private function lineGross(array $line): int
    {
        return intdiv(Money::poisha((string) $line['price']) * Money::milli((string) $line['qty']) + 500, 1000);
    }

    /** @param array<string, mixed> $sale */
    private function moneyColumn(array $sale, string $key): int
    {
        if (!array_key_exists($key, $sale) || $sale[$key] === null || $sale[$key] === '') {
            return 0;
        }
        return Money::poisha((string) $sale[$key]);
    }

    /**
     * @param list<array{gross: int}> $chosen
     * @return list<int>
     */
    private function splitNets(array $chosen, int $returnGross, int $returnNet): array
    {
        $nets = [];
        $used = 0;
        $last = count($chosen) - 1;
        foreach ($chosen as $index => $line) {
            if ($index === $last) {
                $nets[] = $returnNet - $used;
                continue;
            }
            $share = $returnGross > 0 ? Money::share($line['gross'], $returnGross, $returnNet) : 0;
            $nets[] = $share;
            $used += $share;
        }
        return $nets;
    }

    /**
     * @param list<array<string, mixed>> $saleLines
     * @param array<int, int> $already
     * @param list<array{id: int, qty: int}> $chosen
     */
    private function fullyReturned(array $saleLines, array $already, array $chosen): bool
    {
        $returning = [];
        foreach ($chosen as $line) {
            $returning[$line['id']] = $line['qty'];
        }
        foreach ($saleLines as $line) {
            $sold = Money::milli((string) $line['qty']);
            $columnReturned = isset($line['returned_qty']) && $line['returned_qty'] !== null && $line['returned_qty'] !== ''
                ? Money::milli((string) $line['returned_qty'])
                : 0;
            $used = max($columnReturned, $already[(int) $line['id']] ?? 0) + ($returning[(int) $line['id']] ?? 0);
            if ($used < $sold) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string, mixed> */
    private function lockCustomer(int $customerId, bool $lock = true): array
    {
        $sql = 'SELECT ' . $this->schema->quote($this->schema->col('customer_id')) . ' AS id, '
            . $this->schema->quote($this->schema->col('customer_balance')) . ' AS balance, '
            . ($this->schema->optional('customer_name') !== null
                ? $this->schema->quote((string) $this->schema->optional('customer_name')) . ' AS name'
                : "'' AS name")
            . ' FROM ' . $this->schema->quote($this->schema->col('customers_table'))
            . ' WHERE ' . $this->schema->quote($this->schema->col('customer_id')) . ' = ?';
        if ($lock && $this->schema->driver === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $row = $this->fetchOne($sql, [$customerId]);
        if ($row === null) {
            throw new ReturnException('CUSTOMER_NOT_FOUND', 'The registered customer on this memo was not found.');
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function lockProduct(int $productId, bool $lock = true): array
    {
        $sql = 'SELECT ' . $this->schema->quote($this->schema->col('product_id')) . ' AS id, '
            . $this->schema->quote($this->schema->col('product_stock')) . ' AS stock FROM '
            . $this->schema->quote($this->schema->col('products_table'))
            . ' WHERE ' . $this->schema->quote($this->schema->col('product_id')) . ' = ?';
        if ($lock && $this->schema->driver === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $row = $this->fetchOne($sql, [$productId]);
        if ($row === null) {
            throw new ReturnException('PRODUCT_NOT_FOUND', 'A product on this memo is no longer in stock records.');
        }
        return $row;
    }

    private function updateBalance(int $customerId, int $poisha): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE ' . $this->schema->quote($this->schema->col('customers_table'))
            . ' SET ' . $this->schema->quote($this->schema->col('customer_balance')) . ' = ? WHERE '
            . $this->schema->quote($this->schema->col('customer_id')) . ' = ?'
        );
        $statement->execute([Money::plainPoisha($poisha), $customerId]);
    }

    private function updateStock(int $productId, int $milli): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE ' . $this->schema->quote($this->schema->col('products_table'))
            . ' SET ' . $this->schema->quote($this->schema->col('product_stock')) . ' = ? WHERE '
            . $this->schema->quote($this->schema->col('product_id')) . ' = ?'
        );
        $statement->execute([Money::plainMilli($milli), $productId]);
    }

    private function addReturnedQty(int $saleLineId, int $deltaMilli): void
    {
        $column = $this->schema->optional('line_returned');
        if ($column === null) {
            return;
        }
        $row = $this->fetchOne(
            'SELECT ' . $this->schema->quote($column) . ' AS returned_qty FROM '
            . $this->schema->quote($this->schema->col('lines_table')) . ' WHERE '
            . $this->schema->quote($this->schema->col('line_id')) . ' = ?',
            [$saleLineId]
        );
        $current = Money::milli((string) ($row['returned_qty'] ?? '0'));
        $next = $current + $deltaMilli;
        if ($next < 0) {
            $next = 0;
        }
        $this->pdo->prepare(
            'UPDATE ' . $this->schema->quote($this->schema->col('lines_table'))
            . ' SET ' . $this->schema->quote($column) . ' = ? WHERE '
            . $this->schema->quote($this->schema->col('line_id')) . ' = ?'
        )->execute([Money::plainMilli($next), $saleLineId]);
    }

    /** @param array<string, mixed> $sale */
    private function writeStatus(array $sale, bool $fullyReturned): void
    {
        $column = $this->schema->optional('status');
        if ($column === null) {
            return;
        }
        $next = $fullyReturned ? 'returned' : 'partial_return';
        if (!$this->statusAccepts($next)) {
            return;
        }
        $this->pdo->prepare(
            'UPDATE ' . $this->schema->quote($this->schema->col('sales_table'))
            . ' SET ' . $this->schema->quote($column) . ' = ? WHERE '
            . $this->schema->quote($this->schema->col('sales_id')) . ' = ?'
        )->execute([$next, (int) $sale['id']]);
    }

    private function statusAccepts(string $value): bool
    {
        if ($this->schema->driver !== 'mysql') {
            return true;
        }
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$this->schema->col('sales_table'), $this->schema->col('status')]);
        $type = strtolower((string) $statement->fetchColumn());
        if (!str_starts_with($type, 'enum(')) {
            return true;
        }
        return str_contains($type, "'" . $value . "'");
    }

    private function writeCustomerLedger(int $customerId, int $creditPoisha, string $returnNo, string $memo): void
    {
        $table = $this->schema->optional('ledger_table');
        $customerColumn = $this->schema->optional('ledger_customer_id');
        $creditColumn = $this->schema->optional('ledger_credit');
        if ($table === null || $customerColumn === null || $creditColumn === null) {
            return;
        }
        $columns = [$this->schema->quote($customerColumn), $this->schema->quote($creditColumn)];
        $values = [$customerId, Money::plainPoisha($creditPoisha)];
        $holders = ['?', '?'];
        $debit = $this->schema->optional('ledger_debit');
        if ($debit !== null) {
            $columns[] = $this->schema->quote($debit);
            $holders[] = '?';
            $values[] = '0.00';
        }
        $note = $this->schema->optional('ledger_note');
        if ($note !== null) {
            $columns[] = $this->schema->quote($note);
            $holders[] = '?';
            $values[] = 'Memo return ' . $returnNo . ' (' . $memo . ')';
        }
        $created = $this->schema->optional('ledger_created_at');
        if ($created !== null) {
            $columns[] = $this->schema->quote($created);
            $holders[] = '?';
            $values[] = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }
        $this->pdo->prepare(
            'INSERT INTO ' . $this->schema->quote($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $holders) . ')'
        )->execute($values);
    }

    private function optionalSelect(string $schemaKey, string $alias): string
    {
        $column = $this->schema->optional($schemaKey);
        if ($column === null) {
            return 'NULL AS ' . $alias . ', ';
        }
        return $this->schema->quote($column) . ' AS ' . $alias . ', ';
    }

    private function nextReturnNo(): string
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $this->schema->quote('ame_memo_returns'))->fetchColumn();
        return sprintf('SR-%s-%04d', (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd'), $count + 1);
    }

    public function ensureLedger(): void
    {
        if ($this->schema->driver === 'mysql') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS ame_memo_returns (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    return_no VARCHAR(32) NOT NULL,
                    idempotency_key VARCHAR(80) NOT NULL,
                    share_token CHAR(32) NOT NULL,
                    sale_id INT NOT NULL,
                    memo_no VARCHAR(64) NOT NULL,
                    customer_id INT NULL,
                    customer_name VARCHAR(191) NOT NULL DEFAULT \'\',
                    registered TINYINT NOT NULL,
                    return_net_poisha INT NOT NULL,
                    account_minus_poisha INT NOT NULL,
                    refund_poisha INT NOT NULL,
                    balance_before_poisha INT NULL,
                    balance_after_poisha INT NULL,
                    voided_at VARCHAR(40) NULL,
                    created_at VARCHAR(40) NOT NULL,
                    UNIQUE KEY uq_ame_return_no (return_no),
                    UNIQUE KEY uq_ame_return_key (idempotency_key),
                    UNIQUE KEY uq_ame_return_share (share_token)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS ame_memo_return_lines (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    return_id INT NOT NULL,
                    sale_line_id INT NOT NULL,
                    product_id INT NOT NULL,
                    product_name VARCHAR(191) NOT NULL DEFAULT \'\',
                    qty_milli INT NOT NULL,
                    unit_poisha INT NOT NULL DEFAULT 0,
                    line_net_poisha INT NOT NULL,
                    stock_before_milli INT NULL,
                    stock_after_milli INT NULL,
                    KEY idx_ame_return_line (return_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            return;
        }
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ame_memo_returns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                return_no TEXT NOT NULL UNIQUE,
                idempotency_key TEXT NOT NULL UNIQUE,
                share_token TEXT NOT NULL UNIQUE,
                sale_id INTEGER NOT NULL,
                memo_no TEXT NOT NULL,
                customer_id INTEGER NULL,
                customer_name TEXT NOT NULL DEFAULT \'\',
                registered INTEGER NOT NULL,
                return_net_poisha INTEGER NOT NULL,
                account_minus_poisha INTEGER NOT NULL,
                refund_poisha INTEGER NOT NULL,
                balance_before_poisha INTEGER NULL,
                balance_after_poisha INTEGER NULL,
                voided_at TEXT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ame_memo_return_lines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                return_id INTEGER NOT NULL,
                sale_line_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                product_name TEXT NOT NULL DEFAULT \'\',
                qty_milli INTEGER NOT NULL,
                unit_poisha INTEGER NOT NULL DEFAULT 0,
                line_net_poisha INTEGER NOT NULL,
                stock_before_milli INTEGER NULL,
                stock_after_milli INTEGER NULL
            )'
        );
    }

    private function begin(): void
    {
        if ($this->schema->driver === 'sqlite') {
            $this->pdo->exec('BEGIN IMMEDIATE');
            $this->manualTx = true;
            return;
        }
        $this->pdo->beginTransaction();
    }

    private function commitTx(): void
    {
        if ($this->manualTx) {
            $this->pdo->exec('COMMIT');
            $this->manualTx = false;
            return;
        }
        $this->pdo->commit();
    }

    private function rollback(): void
    {
        if ($this->manualTx) {
            $this->pdo->exec('ROLLBACK');
            $this->manualTx = false;
            return;
        }
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** @param list<mixed> $params @return array<string, mixed>|null */
    private function fetchOne(string $sql, array $params): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @param list<mixed> $params @return list<array<string, mixed>> */
    private function fetchAll(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
