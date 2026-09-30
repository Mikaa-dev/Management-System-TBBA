<?php
/**
 * Controller Peti Masuk e-Mel & Pengurusan Minit Surat (Inquiry & Mailbox Controller)
 * Khusus Untuk Admin & Assistant CEO / CEO (TBBA Sdn. Bhd.)
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../config/database.php';

class InquiryController {
    
    // Paparkan Laman Peti Masuk e-Mel dalam Portal
    public static function index() {
        Auth::requireAdmin(); // Restricted to administrators and management.
        
        $sql = "SELECT * FROM inquiries ORDER BY created_at DESC";
        $inquiries = Database::query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Kiraan statistik ringkas
        $total = count($inquiries);
        $newCount = 0;
        $minitedCount = 0;
        $completedCount = 0;
        
        foreach ($inquiries as $inq) {
            if ($inq['status'] === 'Newly Received') {
                $newCount++;
            } elseif (strpos($inq['status'], 'Minuted') !== false) {
                $minitedCount++;
            } elseif ($inq['status'] === 'Completed / Closed') {
                $completedCount++;
            }
        }

        require __DIR__ . '/../views/inquiries/index.php';
    }

    // Ambil butiran 1 pertanyaan untuk paparan modal AJAX
    public static function getDetails() {
        Auth::requireAdmin();
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            Helper::json('error', 'Invalid inquiry ID.');
        }

        $stmt = Database::query("SELECT * FROM inquiries WHERE id = ?", [$id]);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inquiry) {
            Helper::json('error', 'Inquiry record not found.');
        }

        Helper::json('success', 'Data retrieved', ['inquiry' => $inquiry]);
    }

    // Simpan Minit Surat / Tandatangan Pengurusan (AJAX POST)
    public static function updateMinute() {
        Auth::requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $id = intval($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Newly Received');
        $minuteNotes = trim($_POST['minute_notes'] ?? '');
        $applySign = isset($_POST['apply_sign']) && $_POST['apply_sign'] == '1';

        if (!$id) {
            Helper::json('error', 'Invalid inquiry/letter ID.');
        }

        $user = Auth::user();
        $userTitle = $user['name'] . ' (' . strtoupper($user['role']) . ')';
        
        // Cek jika nak tandatangan digital
        if ($applySign) {
            $signedBy = $userTitle;
            $signedAt = date('Y-m-d H:i:s');
            
            $sql = "UPDATE inquiries SET status = ?, minute_notes = ?, minute_by = ?, signed_by = ?, signed_at = ? WHERE id = ?";
            Database::query($sql, [$status, $minuteNotes, $userTitle, $signedBy, $signedAt, $id]);
            
            Helper::json('success', "Letter successfully minuted and digitally signed by $userTitle!");
        } else {
            $sql = "UPDATE inquiries SET status = ?, minute_notes = ?, minute_by = ? WHERE id = ?";
            Database::query($sql, [$status, $minuteNotes, $userTitle, $id]);
            
            Helper::json('success', "Inquiry minute and status successfully updated by $userTitle!");
        }
    }

    // Padam pertanyaan (AJAX POST)
    public static function delete() {
        Auth::requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            Helper::json('error', 'Invalid ID.');
        }

        Database::query("DELETE FROM inquiries WHERE id = ?", [$id]);
        Database::query("DELETE FROM letters WHERE ref_no = ?", ['EML-INQ-' . $id]);
        Helper::json('success', 'Inquiry/letter successfully deleted from the system.');
    }
}
