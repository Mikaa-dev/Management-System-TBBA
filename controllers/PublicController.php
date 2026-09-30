<?php
/**
 * Controller Awam (Public Controller) untuk Website Korporat TBBA
 * Menguruskan penghantaran borang pertanyaan (Contact Us) dan fungsi awam lain.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../core/SecurityFirewall.php';

class PublicController {

    // Simpan data pertanyaan dari borang Contact Us ke dalam MySQL (table inquiries)
    public static function submitInquiry() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
            return;
        }

        if (!Helper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Helper::json('error', 'Your form session has expired. Please refresh the page and try again.');
        }
        if (trim((string)($_POST['website'] ?? '')) !== '') {
            Helper::json('success', 'Thank you! Your inquiry has been successfully submitted.');
        }
        if (!SecurityFirewall::consumeInquirySlot(5, 3600)) {
            http_response_code(429);
            Helper::json('error', 'Too many inquiries were submitted from this network. Please try again later.');
        }

        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $subject = trim($_POST['subject'] ?? 'General Inquiry');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($message)) {
            Helper::json('error', 'Please fill in your name, email, and message.');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Helper::json('error', 'Invalid email address format.');
            return;
        }
        if (mb_strlen($name) > 150 || mb_strlen($email) > 254 || mb_strlen($phone) > 50
            || mb_strlen($subject) > 255 || mb_strlen($message) > 5000) {
            Helper::json('error', 'One or more fields exceed the allowed length.');
        }

        $pdo = Database::connect();
        try {
            $pdo->beginTransaction();
            $ownerId = Database::query(
                "SELECT id FROM users WHERE status='active' AND role IN ('super_admin','admin') ORDER BY FIELD(role,'super_admin','admin'), id LIMIT 1 FOR UPDATE"
            )->fetchColumn();
            if (!$ownerId) throw new RuntimeException('No active inquiry owner is configured.');

            // 1. Simpan ke jadual inquiries (rekod awam)
            Database::query(
                "INSERT INTO inquiries (name, email, phone, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [$name, $email, $phone, $subject, $message]
            );
            $inqId = Database::lastInsertId();

            // 2. Simpan secara automatik ke jadual letters (Pengurusan Surat & e-Mel Masuk untuk Admin/Assistant CEO)
            $refNo = 'EML-INQ-' . $inqId;
            $senderReceiver = $name . ' (' . ($phone ?: 'No Phone') . ')';
            Database::query(
                "INSERT INTO letters (user_id, type, ref_no, title, sender_receiver, letter_date, status, remarks, email_source) VALUES (?, 'IN', ?, ?, ?, CURDATE(), 'pending', ?, ?)",
                [(int)$ownerId, $refNo, $subject, $senderReceiver, $message, $email]
            );

            $pdo->commit();

            Helper::json('success', 'Thank you! Your inquiry has been successfully submitted. The Bridge Business Alliance team will contact you shortly.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[PublicController] Inquiry submission failed: ' . $e->getMessage());
            Helper::json('error', 'Unable to submit your inquiry right now. Please try again later.');
        }
    }
}
?>
