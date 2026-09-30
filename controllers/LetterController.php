<?php
/**
 * Controller Pengurusan Surat (Letter Controller) - AJAX
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/Letter.php';

class LetterController {
    // Dapatkan No. Rujukan Automatik seterusnya (AJAX GET)
    public static function getNextRef() {
        Auth::requireLogin();
        $type = $_GET['type'] ?? 'IN';
        $refNo = Letter::generateNextRef($type);
        Helper::json('success', 'Reference number generated', ['ref_no' => $refNo]);
    }

    // Hantar Borang Daftar Surat Baharu (AJAX POST dengan PDF upload)
    public static function store() {
        Auth::requirePermission('letters', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $user = Auth::user();
        $type = strtoupper(trim($_POST['type'] ?? 'IN'));
        $title = trim($_POST['title'] ?? '');
        $senderReceiver = trim($_POST['sender_receiver'] ?? '');
        $letterDate = trim($_POST['letter_date'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $status = $_POST['status'] ?? 'pending';

        if (!in_array($type, ['IN', 'OUT'])) {
            Helper::json('error', 'Invalid letter type.');
        }

        if (empty($title) || empty($senderReceiver) || empty($letterDate)) {
            Helper::json('error', 'Please fill in the letter title, sender/recipient, and letter date validly.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $letterDate);
        if (!$date || $date->format('Y-m-d') !== $letterDate) Helper::json('error', 'Please enter a valid letter date.');
        if (!in_array($status, ['pending', 'in_progress', 'replied', 'completed'], true)) Helper::json('error', 'Invalid letter status.');
        if (mb_strlen($title) > 255 || mb_strlen($senderReceiver) > 255 || mb_strlen($remarks) > 5000) {
            Helper::json('error', 'One or more fields exceed the allowed length.');
        }

        // Janakan No. Rujukan standard korporat dari server
        $refNo = Letter::generateNextRef($type);

        // Muat Naik Salinan Fail PDF / Dokumen
        $filePath = null;
        if (!empty($_FILES['file_path']['name'])) {
            $file = $_FILES['file_path'];
            try {
                $ext = Helper::validateUpload($file, [
                    'pdf' => ['application/pdf'],
                    'doc' => ['application/msword', 'application/octet-stream'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
                ], 10 * 1024 * 1024);
            } catch (InvalidArgumentException $e) {
                Helper::json('error', $e->getMessage());
            }

            $uploadDir = __DIR__ . '/../storage/private/letters/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0750, true);
            }

            $newFileName = 'LETTER_' . $type . '_' . time() . '_' . uniqid() . '.' . $ext;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $filePath = 'storage/private/letters/' . $newFileName;
            } else {
                Helper::json('error', 'Failed to upload letter file copy to server.');
            }
        }

        $letterData = [
            'user_id'         => $user['id'],
            'type'            => $type,
            'ref_no'          => $refNo,
            'title'           => $title,
            'sender_receiver' => $senderReceiver,
            'letter_date'     => $letterDate,
            'file_path'       => $filePath,
            'status'          => $status,
            'remarks'         => $remarks
        ];

        try {
            $created = Letter::create($letterData);
            $newId = $created['id'];
            $refNo = $created['ref_no'];
        } catch (Throwable $e) {
            if ($filePath) {
                $uploaded = realpath(__DIR__ . '/../' . $filePath);
                $root = realpath(__DIR__ . '/../storage/private/letters');
                if ($uploaded && $root && is_file($uploaded) && str_starts_with($uploaded, $root . DIRECTORY_SEPARATOR)) @unlink($uploaded);
            }
            error_log('[LetterController] Create failed: ' . $e->getMessage());
            Helper::json('error', 'Unable to register the letter. Please try again.');
        }

        Helper::json('success', "Official letter '$refNo' successfully registered into the system!", [
            'id'              => $newId,
            'type'            => $type,
            'ref_no'          => $refNo,
            'title'           => htmlspecialchars($title),
            'sender_receiver' => htmlspecialchars($senderReceiver),
            'letter_date'     => Helper::date($letterDate, 'd M Y'),
            'file_path'       => $filePath ? Helper::url('index.php?action=download_attachment&type=letter&id=' . $newId) : null,
            'status'          => $status,
            'remarks'         => htmlspecialchars($remarks),
            'staff_name'      => htmlspecialchars($user['name'])
        ]);
    }

    // Kemas kini Status Surat (AJAX POST)
    public static function updateStatus() {
        Auth::requirePermission('letters', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        $allowedStatuses = ['pending', 'in_progress', 'replied', 'completed'];
        if (!$id || !in_array($status, $allowedStatuses)) {
            Helper::json('error', 'Invalid letter ID or status.');
        }

        if (Letter::updateStatus($id, $status)) {
            Helper::json('success', 'Letter status successfully updated!');
        } else {
            Helper::json('error', 'No status change or record not found.');
        }
    }

    // Paparkan Laman Peti Masuk e-Mel & Surat dalam Portal (Admin / Assistant CEO)
    public static function index() {
        Auth::requirePermission('letters', 'view');
        
        $pageTitle = 'Inquiries & Letters Inbox';
        $inquiries = Letter::getAll();
        
        $total = count($inquiries);
        $unreadCount = 0;
        $readCount = 0;
        
        foreach ($inquiries as $inq) {
            $st = $inq['status'] ?? 'pending';
            if ($st === 'read' || $st === 'completed' || $st === 'replied') {
                $readCount++;
            } else {
                $unreadCount++;
            }
        }

        require __DIR__ . '/../views/inquiries/index.php';
    }

    // Ambil butiran 1 surat/e-mel untuk AJAX Modal
    public static function getLetter() {
        Auth::requirePermission('letters', 'view');
        
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            Helper::json('error', 'Invalid ID.');
        }

        $letter = Letter::getById($id);
        if (!$letter) {
            Helper::json('error', 'Letter record not found in the database.');
        }

        // Apabila ditekan dan dibuka, surat terus dikira sudah dibaca (Read)
        $st = $letter['status'] ?? 'pending';
        if ($st !== 'read' && $st !== 'completed' && $st !== 'replied') {
            Letter::updateStatus($id, 'read');
            $letter['status'] = 'read';
        }

        Helper::json('success', 'Data retrieved', ['inquiry' => $letter]);
    }

    // Simpan Minit Surat / Tandatangan Pengurusan (AJAX POST)
    public static function updateMinit() {
        Auth::requirePermission('letters', 'edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $id = intval($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? 'in_progress');
        $minitNotes = trim($_POST['minute_notes'] ?? '');
        $applySign = isset($_POST['apply_sign']) && $_POST['apply_sign'] == '1';

        if (!$id) {
            Helper::json('error', 'Invalid letter/email ID.');
        }

        $user = Auth::user();
        $userTitle = $user['name'] . ' (' . strtoupper($user['role']) . ')';
        
        if ($applySign) {
            Letter::updateMinitAndSign($id, $minitNotes, $userTitle, 'completed', true, $userTitle);
            Helper::json('success', "Letter successfully minuted and digitally signed by $userTitle!");
        } else {
            Letter::updateMinitAndSign($id, $minitNotes, $userTitle, $status, false, null);
            Helper::json('success', "Letter minute and status successfully updated by $userTitle!");
        }
    }

    // Padam rekod surat / e-mel (AJAX POST)
    public static function delete() {
        Auth::requirePermission('letters', 'delete');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            Helper::json('error', 'Invalid ID.');
        }

        Letter::delete($id);
        Helper::json('success', 'Letter/email record successfully deleted from the system.');
    }
}
?>
