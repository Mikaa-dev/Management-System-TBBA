<?php
/** Destructive-to-temporary-data regression smoke test; all TEST records are removed in finally. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/FinanceRecord.php';
require_once __DIR__ . '/../models/FinanceWorkflow.php';
require_once __DIR__ . '/../models/AccountSecurity.php';

$marker = 'TEST-' . bin2hex(random_bytes(4));
$email = strtolower($marker) . '@example.invalid';
$userId = 0;
$costCenterId = 0;

function assertSmoke(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

function testTotp(string $secret): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($secret) as $char) $bits .= str_pad(decbin((int)strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
    $key = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $key .= chr(bindec($byte));
    $counter = (int)floor(time() / 30);
    $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $counter), $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset+1]) & 0xff) << 16) | ((ord($hash[$offset+2]) & 0xff) << 8) | (ord($hash[$offset+3]) & 0xff);
    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}

try {
    $userId = User::create($marker, $email, password_hash('Temporary!Pass123', PASSWORD_DEFAULT), 'staff', 'Tester');
    assertSmoke($userId > 0, 'temporary security user created');
    assertSmoke(AccountSecurity::validatePassword('Weak123') !== [], 'weak password rejected');
    assertSmoke(AccountSecurity::validatePassword('Strong!Password123') === [], 'strong password accepted');
    $token = AccountSecurity::createResetToken($userId, '127.0.0.1');
    assertSmoke(AccountSecurity::findResetUser($token) !== null, 'password reset token validates');
    $twoFactorSetup = AccountSecurity::beginTwoFactor($userId);
    $totp = testTotp($twoFactorSetup['secret']);
    assertSmoke(AccountSecurity::enableTwoFactor($userId, $totp), 'TOTP secret encrypts and authenticator code enables 2FA');
    assertSmoke(AccountSecurity::disableTwoFactor($userId, 'Temporary!Pass123', $totp), 'password and authenticator code disable 2FA');

    FinanceWorkflow::saveCostCenter(['code'=>$marker,'name'=>'Temporary Test Cost Centre','annual_budget'=>10000], $userId);
    $costCenterId = (int)Database::query("SELECT id FROM finance_cost_centers WHERE code=?", [$marker])->fetchColumn();
    assertSmoke($costCenterId > 0, 'cost centre saved');

    $quotationId = FinanceRecord::create([
        'category'=>'sales','document_type'=>'quotations','reference_no'=>$marker.'-QT','party_name'=>$marker,
        'document_date'=>date('Y-m-d'),'due_date'=>date('Y-m-d',strtotime('+14 days')),'title'=>'Temporary workflow test',
        'amount'=>1000,'tax_amount'=>80,'tax_rate'=>8,'total_amount'=>1080,'status'=>'sent','notes'=>'Regression test',
        'created_by'=>$userId,'cost_center_id'=>$costCenterId,'project_ref'=>$marker,
    ]);
    $orderId = FinanceWorkflow::convertRecord($quotationId, 'sale_orders', $userId);
    $invoiceId = FinanceWorkflow::convertRecord($orderId, 'invoices', $userId);
    assertSmoke($invoiceId > 0, 'quotation converts through sale order to invoice');
    $payment = FinanceWorkflow::recordPayment($invoiceId, ['amount'=>400,'payment_date'=>date('Y-m-d'),'method'=>'bank_transfer','bank_account'=>'TEST','bank_reference'=>$marker,'notes'=>'Test partial receipt'], $userId);
    $invoice = FinanceRecord::findById($invoiceId);
    assertSmoke(abs((float)$invoice['balance_due'] - 680.0) < 0.001, 'partial receipt updates invoice balance');
    assertSmoke(str_starts_with($payment['payment_no'], 'RCPT-'), 'incoming payment receives receipt number');
    $bankTransactionId = FinanceWorkflow::saveBankTransaction(['transaction_date'=>date('Y-m-d'),'bank_account'=>'TEST','bank_reference'=>$marker,'description'=>'Test bank receipt','direction'=>'in','amount'=>400], $userId);
    FinanceWorkflow::matchBankTransaction($bankTransactionId, (int)$payment['id'], $userId);
    $matched = Database::query("SELECT status FROM finance_bank_transactions WHERE id=?", [$bankTransactionId])->fetchColumn();
    assertSmoke($matched === 'matched', 'bank statement line matches and reconciles exact receipt');

    FinanceWorkflow::saveBudget(['fiscal_year'=>date('Y'),'cost_center_id'=>$costCenterId,'project_ref'=>$marker,'allocated_amount'=>5000], $userId);
    assertSmoke(count(FinanceWorkflow::budgets((int)date('Y'))) >= 1, 'budget reporting query succeeds');
    FinanceWorkflow::saveRecurringRule(['party_name'=>$marker,'title'=>'Monthly test bill','amount'=>100,'tax_rate'=>8,'frequency'=>'monthly','next_run_date'=>date('Y-m-d'),'due_days'=>30,'cost_center_id'=>$costCenterId,'project_ref'=>$marker], $userId);
    assertSmoke(FinanceWorkflow::generateDueRecurring($userId) === 1, 'due recurring rule generates a supplier bill');
    assertSmoke(is_array(FinanceWorkflow::dashboard((int)date('Y'))), 'finance dashboard query succeeds');

    $_SESSION['user_id'] = $userId;
    AccountSecurity::registerSession($userId);
    assertSmoke(AccountSecurity::validateCurrentSession($userId), 'server-side device session validates');
    echo "SMOKE TEST COMPLETE\n";
} finally {
    try {
        Database::query("DELETE FROM finance_bank_transactions WHERE created_by=?", [$userId]);
        Database::query("DELETE FROM finance_payments WHERE created_by=?", [$userId]);
        Database::query("DELETE FROM finance_records WHERE created_by=?", [$userId]);
        Database::query("DELETE FROM finance_budgets WHERE created_by=?", [$userId]);
        Database::query("DELETE FROM finance_recurring_rules WHERE created_by=?", [$userId]);
        if ($costCenterId > 0) Database::query("DELETE FROM finance_cost_centers WHERE id=?", [$costCenterId]);
        Database::query("DELETE FROM security_events WHERE user_id=?", [$userId]);
        Database::query("DELETE FROM password_reset_tokens WHERE user_id=?", [$userId]);
        Database::query("DELETE FROM user_sessions WHERE user_id=?", [$userId]);
        if ($userId > 0) Database::query("DELETE FROM users WHERE id=?", [$userId]);
    } catch (Throwable $cleanupError) {
        fwrite(STDERR, 'Cleanup warning: ' . $cleanupError->getMessage() . PHP_EOL);
    }
}
