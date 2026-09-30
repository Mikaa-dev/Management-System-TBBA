<?php
/** Unified finance control center controller. */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/FinanceWorkflow.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/AuditLog.php';

class FinanceWorkflowController
{
    public static function index(): void
    {
        Auth::requirePermission('finance_control', 'view');
        $pageTitle = 'Finance Control Center';
        $year = max(2020, min(2100, (int)($_GET['year'] ?? date('Y'))));
        $summary = FinanceWorkflow::dashboard($year);
        $cashFlow = FinanceWorkflow::cashFlow($year);
        $receivables = FinanceWorkflow::aging('sales');
        $payables = FinanceWorkflow::aging('purchases');
        $records = FinanceWorkflow::workflowRecords();
        $payments = FinanceWorkflow::payments();
        $bankTransactions = FinanceWorkflow::bankTransactions();
        $costCenters = FinanceWorkflow::costCenters();
        $budgets = FinanceWorkflow::budgets($year);
        $profitability = FinanceWorkflow::profitability($year);
        $recurringRules = FinanceWorkflow::recurringRules();
        $approvedRequests = FinanceWorkflow::approvedRequestsWithoutPo();
        $settings = FinanceWorkflow::settings();
        $departments = Department::getAll();
        $canCreate = Auth::hasPermission('finance_control', 'create');
        $canEdit = Auth::hasPermission('finance_control', 'edit');
        $canReconcile = Auth::hasPermission('finance_control', 'reconcile');
        $canSettings = Auth::hasPermission('finance_control', 'settings');
        include __DIR__ . '/../views/finance/control.php';
    }

    public static function convertRecord(): void
    {
        self::postGuard('create');
        try {
            $id = FinanceWorkflow::convertRecord((int)($_POST['source_id'] ?? 0), trim($_POST['target_type'] ?? ''), (int)Auth::id());
            AuditLog::record('FINANCE_CONVERT', 'Converted finance document into record #' . $id);
            Helper::json('success', 'Document converted successfully.', ['id' => $id]);
        } catch (Throwable $e) {
            Helper::json('error', $e->getMessage());
        }
    }

    public static function convertPurchaseRequest(): void
    {
        self::postGuard('create');
        try {
            $id = FinanceWorkflow::convertPurchaseRequest((int)($_POST['request_id'] ?? 0), (int)Auth::id());
            AuditLog::record('PR_TO_PO', 'Converted approved purchase request into PO record #' . $id);
            Helper::json('success', 'Purchase order created from the approved request.', ['id' => $id]);
        } catch (Throwable $e) {
            Helper::json('error', $e->getMessage());
        }
    }

    public static function recordPayment(): void
    {
        self::postGuard('create');
        try {
            $result = FinanceWorkflow::recordPayment((int)($_POST['record_id'] ?? 0), $_POST, (int)Auth::id());
            AuditLog::record('FINANCE_PAYMENT', 'Recorded payment ' . $result['payment_no']);
            Helper::json('success', 'Payment recorded successfully.', $result);
        } catch (Throwable $e) {
            Helper::json('error', $e->getMessage());
        }
    }

    public static function reconcilePayment(): void
    {
        self::postGuard('reconcile');
        $id = (int)($_POST['payment_id'] ?? 0);
        FinanceWorkflow::reconcilePayment($id, (int)Auth::id());
        AuditLog::record('BANK_RECONCILE', 'Reconciled finance payment #' . $id);
        Helper::json('success', 'Payment marked as reconciled.');
    }

    public static function saveBankTransaction(): void
    {
        self::postGuard('reconcile');
        try {
            $id = FinanceWorkflow::saveBankTransaction($_POST, (int)Auth::id());
            AuditLog::record('BANK_TRANSACTION', 'Added bank statement transaction #' . $id);
            Helper::json('success', 'Bank transaction added for reconciliation.', ['id'=>$id]);
        } catch (Throwable $e) {
            Helper::json('error', $e->getMessage());
        }
    }

    public static function matchBankTransaction(): void
    {
        self::postGuard('reconcile');
        try {
            FinanceWorkflow::matchBankTransaction((int)($_POST['transaction_id']??0),(int)($_POST['payment_id']??0),(int)Auth::id());
            AuditLog::record('BANK_MATCH', 'Matched bank transaction #' . (int)($_POST['transaction_id']??0) . ' to payment #' . (int)($_POST['payment_id']??0));
            Helper::json('success', 'Bank transaction matched and payment reconciled.');
        } catch (Throwable $e) {
            Helper::json('error', $e->getMessage());
        }
    }

    public static function saveCostCenter(): void
    {
        self::postGuard('settings');
        try {
            FinanceWorkflow::saveCostCenter($_POST, (int)Auth::id());
            AuditLog::record('FINANCE_COST_CENTER', 'Saved cost center ' . strtoupper(trim($_POST['code'] ?? '')));
            Helper::json('success', 'Cost center saved successfully.');
        } catch (Throwable $e) {
            Helper::json('error', $e->getMessage());
        }
    }

    public static function saveBudget(): void
    {
        self::postGuard('settings');
        try {
            FinanceWorkflow::saveBudget($_POST, (int)Auth::id());
            AuditLog::record('FINANCE_BUDGET', 'Updated finance budget for ' . ($_POST['fiscal_year'] ?? date('Y')));
            Helper::json('success', 'Budget saved successfully.');
        } catch (Throwable $e) {
            Helper::json('error', $e->getMessage());
        }
    }

    public static function saveRecurring(): void
    {
        self::postGuard('settings');
        try {
            FinanceWorkflow::saveRecurringRule($_POST, (int)Auth::id());
            AuditLog::record('FINANCE_RECURRING', 'Created recurring bill rule');
            Helper::json('success', 'Recurring bill rule created successfully.');
        } catch (Throwable $e) {
            Helper::json('error', $e->getMessage());
        }
    }

    public static function generateRecurring(): void
    {
        self::postGuard('create');
        $count = FinanceWorkflow::generateDueRecurring((int)Auth::id());
        AuditLog::record('FINANCE_RECURRING_RUN', "Generated {$count} recurring bill(s)");
        Helper::json('success', $count ? "Generated {$count} due recurring bill(s)." : 'No recurring bills are due today.', ['count' => $count]);
    }

    public static function saveSettings(): void
    {
        self::postGuard('settings');
        FinanceWorkflow::saveSettings($_POST, (int)Auth::id());
        AuditLog::record('FINANCE_SETTINGS', 'Updated finance settings');
        Helper::json('success', 'Finance settings saved successfully.');
    }

    public static function receipt(): void
    {
        Auth::requirePermission('finance_control', 'view');
        $payment = FinanceWorkflow::paymentReceipt((int)($_GET['id'] ?? 0));
        if (!$payment) {
            http_response_code(404);
            die('Payment receipt not found.');
        }
        include __DIR__ . '/../views/finance/receipt.php';
    }

    private static function postGuard(string $permission): void
    {
        Auth::requirePermission('finance_control', $permission);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid request method.');
        if (!Helper::verifyCsrf($_POST['csrf_token'] ?? '')) Helper::json('error', 'Invalid CSRF token. Refresh the page and try again.');
    }
}
