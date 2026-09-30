<?php
/** Finance workflow, treasury, budgeting and reconciliation service. */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/FinanceRecord.php';

class FinanceWorkflow
{
    public static function dashboard(int $year): array
    {
        $summary = Database::query(
            "SELECT
                COALESCE(SUM(CASE WHEN category='sales' AND document_type='invoices' THEN balance_due ELSE 0 END),0) receivable,
                COALESCE(SUM(CASE WHEN category='purchases' AND document_type='bills' THEN balance_due ELSE 0 END),0) payable,
                COALESCE(SUM(CASE WHEN category='sales' AND document_type='invoices' THEN total_amount ELSE 0 END),0) invoiced,
                COALESCE(SUM(CASE WHEN category='purchases' AND document_type='bills' THEN total_amount ELSE 0 END),0) billed
             FROM finance_records WHERE YEAR(document_date)=?",
            [$year]
        )->fetch() ?: [];

        $cash = Database::query(
            "SELECT
                COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END),0) cash_in,
                COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) cash_out
             FROM finance_payments
             WHERE status!='void' AND YEAR(payment_date)=? AND MONTH(payment_date)=MONTH(CURDATE())",
            [$year]
        )->fetch() ?: [];

        $budget = Database::query(
            "SELECT COALESCE(SUM(allocated_amount),0) allocated FROM finance_budgets WHERE fiscal_year=?",
            [$year]
        )->fetch() ?: [];

        $actual = Database::query(
            "SELECT COALESCE(SUM(total_amount),0) actual
             FROM finance_records
             WHERE category='purchases' AND document_type IN ('bills','purchase_orders')
               AND status NOT IN ('cancelled','rejected') AND YEAR(document_date)=?",
            [$year]
        )->fetch() ?: [];

        return array_merge($summary, $cash, $budget, $actual);
    }

    public static function aging(string $category): array
    {
        $documentType = $category === 'sales' ? 'invoices' : 'bills';
        return Database::query(
            "SELECT f.*,
                GREATEST(DATEDIFF(CURDATE(), COALESCE(f.due_date,f.document_date)),0) overdue_days,
                CASE
                    WHEN COALESCE(f.due_date,f.document_date) >= CURDATE() THEN 'current'
                    WHEN DATEDIFF(CURDATE(),COALESCE(f.due_date,f.document_date)) <= 30 THEN '1-30'
                    WHEN DATEDIFF(CURDATE(),COALESCE(f.due_date,f.document_date)) <= 60 THEN '31-60'
                    WHEN DATEDIFF(CURDATE(),COALESCE(f.due_date,f.document_date)) <= 90 THEN '61-90'
                    ELSE '90+' END aging_bucket
             FROM finance_records f
             WHERE f.category=? AND f.document_type=? AND f.balance_due>0
               AND f.status NOT IN ('cancelled','rejected')
             ORDER BY COALESCE(f.due_date,f.document_date), f.id",
            [$category, $documentType]
        )->fetchAll();
    }

    public static function workflowRecords(int $limit = 100): array
    {
        $limit = max(1, min(250, $limit));
        return Database::query(
            "SELECT f.*, p.reference_no parent_reference, cc.code cost_center_code, cc.name cost_center_name,
                    u.name creator_name
             FROM finance_records f
             LEFT JOIN finance_records p ON p.id=f.parent_record_id
             LEFT JOIN finance_cost_centers cc ON cc.id=f.cost_center_id
             LEFT JOIN users u ON u.id=f.created_by
             ORDER BY f.updated_at DESC, f.id DESC LIMIT {$limit}"
        )->fetchAll();
    }

    public static function getRecordChain(int $recordId): array
    {
        $record = FinanceRecord::findById($recordId);
        if (!$record) return [];
        $rootId = (int)$recordId;
        while (!empty($record['parent_record_id'])) {
            $rootId = (int)$record['parent_record_id'];
            $parent = FinanceRecord::findById($rootId);
            if (!$parent) break;
            $record = $parent;
        }
        return Database::query(
            "WITH RECURSIVE chain AS (
                SELECT * FROM finance_records WHERE id=?
                UNION ALL
                SELECT f.* FROM finance_records f INNER JOIN chain c ON f.parent_record_id=c.id
             ) SELECT * FROM chain ORDER BY document_date,id",
            [$rootId]
        )->fetchAll();
    }

    public static function allowedTargets(array $record): array
    {
        $sales = [
            'quotations' => ['sale_orders'],
            'sale_orders' => ['delivery_orders', 'invoices'],
            'delivery_orders' => ['invoices'],
            'invoices' => [],
        ];
        $purchases = [
            'purchase_orders' => ['grn', 'bills'],
            'grn' => ['bills'],
            'bills' => [],
        ];
        $map = ($record['category'] ?? '') === 'sales' ? $sales : $purchases;
        return $map[$record['document_type'] ?? ''] ?? [];
    }

    public static function convertRecord(int $sourceId, string $targetType, int $userId): int
    {
        $source = FinanceRecord::findById($sourceId);
        if (!$source || !in_array($targetType, self::allowedTargets($source), true)) {
            throw new InvalidArgumentException('This document cannot be converted to the selected workflow stage.');
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $reference = self::nextReference($targetType);
            $dueDate = in_array($targetType, ['invoices','bills'], true)
                ? date('Y-m-d', strtotime('+' . (int)self::setting('invoice_terms_days', '30') . ' days'))
                : ($source['due_date'] ?: null);
            $status = match ($targetType) {
                'sale_orders' => 'confirmed',
                'delivery_orders' => 'pending',
                'purchase_orders' => 'approved',
                'grn' => 'received',
                'invoices' => 'sent',
                'bills' => 'pending_payment',
                default => 'draft',
            };
            $balance = in_array($targetType, ['invoices','bills'], true) ? (float)$source['total_amount'] : 0.0;
            Database::query(
                "INSERT INTO finance_records
                 (parent_record_id,source_request_id,cost_center_id,project_ref,currency,category,document_type,
                  reference_no,party_name,document_date,due_date,title,amount,tax_amount,tax_rate,total_amount,
                  paid_amount,balance_due,status,notes,attachment,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $sourceId, $source['source_request_id'] ?? null, $source['cost_center_id'] ?? null,
                    $source['project_ref'] ?? null, $source['currency'] ?? 'MYR', $source['category'], $targetType,
                    $reference, $source['party_name'], date('Y-m-d'), $dueDate, $source['title'], $source['amount'],
                    $source['tax_amount'], $source['tax_rate'] ?? 0, $source['total_amount'], 0, $balance, $status,
                    'Converted from ' . $source['reference_no'] . (!empty($source['notes']) ? '. ' . $source['notes'] : ''),
                    $source['attachment'] ?? null, $userId,
                ]
            );
            $newId = (int)Database::lastInsertId();
            $pdo->commit();
            return $newId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function convertPurchaseRequest(int $requestId, int $userId): int
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $request = Database::query("SELECT * FROM purchase_requests WHERE id=? FOR UPDATE", [$requestId])->fetch();
            if (!$request || !in_array($request['status'], ['approved','ordered'], true)) {
                throw new InvalidArgumentException('Only an approved purchase request can be converted into a purchase order.');
            }
            $existing = Database::query("SELECT id FROM finance_records WHERE source_request_id=? LIMIT 1", [$requestId])->fetchColumn();
            if ($existing) throw new InvalidArgumentException('A purchase order already exists for this purchase request.');

            $reference = self::nextReference('purchase_orders');
            Database::query(
            "INSERT INTO finance_records
             (source_request_id,project_ref,currency,category,document_type,reference_no,party_name,document_date,due_date,
              title,amount,tax_amount,tax_rate,total_amount,paid_amount,balance_due,status,notes,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $requestId, $request['project_ref'] ?? null, $request['currency'] ?: 'MYR', 'purchases', 'purchase_orders',
                $reference, $request['vendor'] ?: 'Vendor not assigned', date('Y-m-d'), $request['required_date'] ?: null,
                $request['title'], $request['total_amount'], 0, 0, $request['total_amount'], 0, 0, 'approved',
                'Generated from ' . $request['pr_no'] . '. ' . ($request['justification'] ?? ''), $userId,
            ]
            );
            $id = (int)Database::lastInsertId();
            Database::query("UPDATE purchase_requests SET status='ordered' WHERE id=?", [$requestId]);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function approvedRequestsWithoutPo(): array
    {
        return Database::query(
            "SELECT pr.*,u.name requester_name FROM purchase_requests pr
             LEFT JOIN users u ON u.id=pr.user_id
             LEFT JOIN finance_records f ON f.source_request_id=pr.id
             WHERE pr.status='approved' AND f.id IS NULL ORDER BY pr.created_at"
        )->fetchAll();
    }

    public static function recordPayment(int $recordId, array $data, int $userId): array
    {
        $record = FinanceRecord::findById($recordId);
        if (!$record || !in_array($record['document_type'], ['invoices','bills'], true)) {
            throw new InvalidArgumentException('Payments can only be recorded against invoices or bills.');
        }
        $amount = round((float)($data['amount'] ?? 0), 2);
        $balance = round((float)$record['balance_due'], 2);
        if ($amount <= 0 || $amount > $balance) {
            throw new InvalidArgumentException('Payment amount must be greater than zero and cannot exceed the outstanding balance.');
        }

        $direction = $record['category'] === 'sales' ? 'in' : 'out';
        $paymentNo = self::nextPaymentNumber($direction);
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            Database::query(
                "INSERT INTO finance_payments
                 (finance_record_id,payment_no,direction,payment_date,amount,method,bank_account,bank_reference,notes,status,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,'cleared',?)",
                [
                    $recordId, $paymentNo, $direction, $data['payment_date'] ?: date('Y-m-d'), $amount,
                    $data['method'] ?: 'bank_transfer', $data['bank_account'] ?: null,
                    $data['bank_reference'] ?: null, $data['notes'] ?: null, $userId,
                ]
            );
            $paymentId = (int)Database::lastInsertId();
            $paid = round((float)$record['paid_amount'] + $amount, 2);
            $remaining = max(0, round((float)$record['total_amount'] - $paid, 2));
            $status = $remaining <= 0 ? 'paid' : 'partially_paid';
            Database::query(
                "UPDATE finance_records SET paid_amount=?,balance_due=?,status=? WHERE id=?",
                [$paid, $remaining, $status, $recordId]
            );
            $pdo->commit();
            return ['id' => $paymentId, 'payment_no' => $paymentNo, 'balance_due' => $remaining];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function payments(int $limit = 150): array
    {
        $limit = max(1, min(500, $limit));
        return Database::query(
            "SELECT p.*,f.reference_no,f.party_name,f.document_type,u.name creator_name
             FROM finance_payments p
             JOIN finance_records f ON f.id=p.finance_record_id
             LEFT JOIN users u ON u.id=p.created_by
             ORDER BY p.payment_date DESC,p.id DESC LIMIT {$limit}"
        )->fetchAll();
    }

    public static function reconcilePayment(int $paymentId, int $userId): void
    {
        Database::query(
            "UPDATE finance_payments SET status='reconciled',reconciled_by=?,reconciled_at=NOW()
             WHERE id=? AND status IN ('pending','cleared')",
            [$userId, $paymentId]
        );
    }

    public static function bankTransactions(int $limit = 150): array
    {
        $limit = max(1, min(500, $limit));
        return Database::query(
            "SELECT bt.*,p.payment_no FROM finance_bank_transactions bt
             LEFT JOIN finance_payments p ON p.id=bt.matched_payment_id
             ORDER BY bt.transaction_date DESC,bt.id DESC LIMIT {$limit}"
        )->fetchAll();
    }

    public static function saveBankTransaction(array $data, int $userId): int
    {
        $amount = round((float)($data['amount'] ?? 0), 2);
        $direction = in_array($data['direction'] ?? '', ['in','out'], true) ? $data['direction'] : '';
        if ($amount <= 0 || $direction === '' || trim($data['bank_account'] ?? '') === '' || trim($data['description'] ?? '') === '') {
            throw new InvalidArgumentException('Bank account, direction, amount and description are required.');
        }
        Database::query(
            "INSERT INTO finance_bank_transactions
             (transaction_date,bank_account,bank_reference,description,direction,amount,created_by)
             VALUES (?,?,?,?,?,?,?)",
            [$data['transaction_date'] ?: date('Y-m-d'),trim($data['bank_account']),trim($data['bank_reference'] ?? '') ?: null,
             trim($data['description']),$direction,$amount,$userId]
        );
        return (int)Database::lastInsertId();
    }

    public static function matchBankTransaction(int $transactionId, int $paymentId, int $userId): void
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $transaction = Database::query("SELECT * FROM finance_bank_transactions WHERE id=? FOR UPDATE", [$transactionId])->fetch();
            $payment = Database::query("SELECT * FROM finance_payments WHERE id=? FOR UPDATE", [$paymentId])->fetch();
            if (!$transaction || $transaction['status'] !== 'unmatched') throw new InvalidArgumentException('Bank transaction is no longer available for matching.');
            if (!$payment || in_array($payment['status'], ['reconciled','void'], true)) throw new InvalidArgumentException('Payment is not available for reconciliation.');
            if ($transaction['direction'] !== $payment['direction'] || abs((float)$transaction['amount'] - (float)$payment['amount']) > 0.009) {
                throw new InvalidArgumentException('Bank transaction direction and amount must exactly match the payment.');
            }
            Database::query("UPDATE finance_bank_transactions SET matched_payment_id=?,status='matched' WHERE id=?", [$paymentId,$transactionId]);
            Database::query("UPDATE finance_payments SET status='reconciled',reconciled_by=?,reconciled_at=NOW() WHERE id=?", [$userId,$paymentId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function cashFlow(int $year): array
    {
        $rows = Database::query(
            "SELECT MONTH(payment_date) month_no,
                    SUM(CASE WHEN direction='in' AND status!='void' THEN amount ELSE 0 END) cash_in,
                    SUM(CASE WHEN direction='out' AND status!='void' THEN amount ELSE 0 END) cash_out
             FROM finance_payments WHERE YEAR(payment_date)=? GROUP BY MONTH(payment_date)",
            [$year]
        )->fetchAll();
        $byMonth = array_fill(1, 12, ['cash_in' => 0.0, 'cash_out' => 0.0]);
        foreach ($rows as $row) {
            $byMonth[(int)$row['month_no']] = ['cash_in' => (float)$row['cash_in'], 'cash_out' => (float)$row['cash_out']];
        }
        return $byMonth;
    }

    public static function costCenters(): array
    {
        return Database::query(
            "SELECT cc.*,d.name department_name FROM finance_cost_centers cc
             LEFT JOIN departments d ON d.id=cc.department_id ORDER BY cc.code"
        )->fetchAll();
    }

    public static function saveCostCenter(array $data, int $userId): int
    {
        $code = strtoupper(trim($data['code'] ?? ''));
        $name = trim($data['name'] ?? '');
        if ($code === '' || $name === '') throw new InvalidArgumentException('Cost center code and name are required.');
        Database::query(
            "INSERT INTO finance_cost_centers (code,name,department_id,annual_budget,created_by)
             VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),department_id=VALUES(department_id),annual_budget=VALUES(annual_budget),is_active=1",
            [$code, $name, !empty($data['department_id']) ? (int)$data['department_id'] : null, (float)($data['annual_budget'] ?? 0), $userId]
        );
        return (int)Database::lastInsertId();
    }

    public static function budgets(int $year): array
    {
        return Database::query(
            "SELECT b.*,cc.code,cc.name cost_center_name,
                COALESCE((SELECT SUM(f.total_amount) FROM finance_records f
                    WHERE f.cost_center_id=b.cost_center_id AND YEAR(f.document_date)=b.fiscal_year
                    AND (b.project_ref='' OR f.project_ref=b.project_ref)
                    AND f.category='purchases' AND f.document_type='bills' AND f.status NOT IN ('cancelled','rejected')),0) actual_spend
             FROM finance_budgets b JOIN finance_cost_centers cc ON cc.id=b.cost_center_id
             WHERE b.fiscal_year=? ORDER BY cc.code,b.project_ref",
            [$year]
        )->fetchAll();
    }

    public static function saveBudget(array $data, int $userId): void
    {
        $year = (int)($data['fiscal_year'] ?? date('Y'));
        $costCenter = (int)($data['cost_center_id'] ?? 0);
        $amount = (float)($data['allocated_amount'] ?? 0);
        if ($costCenter <= 0 || $year < 2020 || $amount < 0) throw new InvalidArgumentException('Enter a valid budget scope and amount.');
        Database::query(
            "INSERT INTO finance_budgets (fiscal_year,cost_center_id,project_ref,allocated_amount,notes,created_by)
             VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE allocated_amount=VALUES(allocated_amount),notes=VALUES(notes),created_by=VALUES(created_by)",
            [$year, $costCenter, trim($data['project_ref'] ?? ''), $amount, trim($data['notes'] ?? '') ?: null, $userId]
        );
    }

    public static function profitability(int $year): array
    {
        return Database::query(
            "SELECT project_ref,
                    SUM(revenue) revenue,SUM(cost) cost,SUM(revenue)-SUM(cost) profit
             FROM (
                SELECT project_ref,total_amount revenue,0 cost FROM finance_records
                 WHERE category='sales' AND document_type='invoices' AND project_ref IS NOT NULL AND project_ref!=''
                   AND status NOT IN ('cancelled','rejected') AND YEAR(document_date)=?
                UNION ALL
                SELECT project_ref,0 revenue,total_amount cost FROM finance_records
                 WHERE category='purchases' AND document_type='bills' AND project_ref IS NOT NULL AND project_ref!=''
                   AND status NOT IN ('cancelled','rejected') AND YEAR(document_date)=?
                UNION ALL
                SELECT project_ref,0 revenue,total_amount cost FROM expense_claims
                 WHERE project_ref IS NOT NULL AND project_ref!='' AND status IN ('approved','paid') AND YEAR(expense_date)=?
             ) x GROUP BY project_ref ORDER BY profit DESC",
            [$year, $year, $year]
        )->fetchAll();
    }

    public static function recurringRules(): array
    {
        return Database::query(
            "SELECT r.*,cc.code cost_center_code FROM finance_recurring_rules r
             LEFT JOIN finance_cost_centers cc ON cc.id=r.cost_center_id ORDER BY r.next_run_date"
        )->fetchAll();
    }

    public static function saveRecurringRule(array $data, int $userId): int
    {
        if (trim($data['party_name'] ?? '') === '' || trim($data['title'] ?? '') === '' || (float)($data['amount'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Vendor, title and amount are required for a recurring bill.');
        }
        Database::query(
            "INSERT INTO finance_recurring_rules
             (party_name,title,amount,tax_rate,frequency,next_run_date,due_days,cost_center_id,project_ref,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [trim($data['party_name']),trim($data['title']),(float)$data['amount'],(float)($data['tax_rate'] ?? 0),
             in_array($data['frequency'] ?? '', ['monthly','quarterly','yearly'], true) ? $data['frequency'] : 'monthly',
             $data['next_run_date'] ?: date('Y-m-d'),max(0,(int)($data['due_days'] ?? 30)),
             !empty($data['cost_center_id']) ? (int)$data['cost_center_id'] : null,trim($data['project_ref'] ?? '') ?: null,$userId]
        );
        return (int)Database::lastInsertId();
    }

    public static function generateDueRecurring(int $userId): int
    {
        $rules = Database::query("SELECT * FROM finance_recurring_rules WHERE is_active=1 AND next_run_date<=CURDATE()")->fetchAll();
        $count = 0;
        foreach ($rules as $rule) {
            $tax = round((float)$rule['amount'] * ((float)$rule['tax_rate'] / 100), 2);
            $total = round((float)$rule['amount'] + $tax, 2);
            $reference = self::nextReference('bills');
            $dueDate = date('Y-m-d', strtotime($rule['next_run_date'] . ' +' . (int)$rule['due_days'] . ' days'));
            Database::query(
                "INSERT INTO finance_records
                 (recurring_rule_id,cost_center_id,project_ref,category,document_type,reference_no,party_name,document_date,due_date,
                  title,amount,tax_amount,tax_rate,total_amount,paid_amount,balance_due,status,notes,created_by)
                 VALUES (?,?,?,'purchases','bills',?,?,?,?,?,?,?,?,?,0,?,'pending_payment','Generated from recurring bill rule',?)",
                [$rule['id'],$rule['cost_center_id'],$rule['project_ref'],$reference,$rule['party_name'],$rule['next_run_date'],$dueDate,
                 $rule['title'],$rule['amount'],$tax,$rule['tax_rate'],$total,$total,$userId]
            );
            $interval = match ($rule['frequency']) {'quarterly' => '+3 months','yearly' => '+1 year',default => '+1 month'};
            Database::query("UPDATE finance_recurring_rules SET next_run_date=? WHERE id=?", [date('Y-m-d', strtotime($rule['next_run_date'] . ' ' . $interval)), $rule['id']]);
            $count++;
        }
        return $count;
    }

    public static function settings(): array
    {
        $rows = Database::query("SELECT setting_key,setting_value FROM finance_settings")->fetchAll();
        return array_column($rows, 'setting_value', 'setting_key');
    }

    public static function saveSettings(array $settings, int $userId): void
    {
        $allowed = ['default_currency','sst_rate','invoice_terms_days','fiscal_year_start_month'];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $settings)) continue;
            Database::query(
                "INSERT INTO finance_settings (setting_key,setting_value,updated_by) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)",
                [$key, trim((string)$settings[$key]), $userId]
            );
        }
    }

    public static function setting(string $key, string $default = ''): string
    {
        try {
            $value = Database::query("SELECT setting_value FROM finance_settings WHERE setting_key=?", [$key])->fetchColumn();
            return $value !== false ? (string)$value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    public static function paymentReceipt(int $paymentId): ?array
    {
        $row = Database::query(
            "SELECT p.*,f.reference_no document_reference,f.party_name,f.title,f.category,f.document_type,f.total_amount,f.balance_due,
                    u.name created_by_name
             FROM finance_payments p JOIN finance_records f ON f.id=p.finance_record_id
             LEFT JOIN users u ON u.id=p.created_by WHERE p.id=?",
            [$paymentId]
        )->fetch();
        return $row ?: null;
    }

    public static function nextReference(string $documentType): string
    {
        $prefixes = [
            'quotations'=>'QT','sale_orders'=>'SO','delivery_orders'=>'DO','invoices'=>'INV','receipts'=>'RCPT',
            'purchase_orders'=>'PO','grn'=>'GRN','bills'=>'BILL','credit_notes'=>'CN','payments'=>'PAY','refunds'=>'RF',
        ];
        $prefix = $prefixes[$documentType] ?? 'FIN';
        $year = date('Y');
        $pattern = $prefix . '-' . $year . '-%';
        $last = Database::query(
            "SELECT reference_no FROM finance_records WHERE reference_no LIKE ? ORDER BY reference_no DESC LIMIT 1 FOR UPDATE",
            [$pattern]
        )->fetchColumn();
        $number = $last && preg_match('/(\d+)$/', (string)$last, $m) ? (int)$m[1] + 1 : 1;
        return sprintf('%s-%s-%04d', $prefix, $year, $number);
    }

    private static function nextPaymentNumber(string $direction): string
    {
        $prefix = $direction === 'in' ? 'RCPT' : 'PAY';
        $year = date('Y');
        $last = Database::query(
            "SELECT payment_no FROM finance_payments WHERE payment_no LIKE ? ORDER BY id DESC LIMIT 1",
            [$prefix . '-' . $year . '-%']
        )->fetchColumn();
        $number = $last && preg_match('/(\d+)$/', (string)$last, $m) ? (int)$m[1] + 1 : 1;
        return sprintf('%s-%s-%04d', $prefix, $year, $number);
    }
}
