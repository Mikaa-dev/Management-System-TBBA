<?php
/**
 * Model Pengurusan Surat (Letter Model) - PDO
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Helper.php';

class Letter {
    // Penjanaan No. Rujukan Automatik mengikut piawaian korporat (TBBA/IN/2026/07/001)
    public static function generateNextRef($type = 'IN') {
        $year = date('Y');
        $month = date('m');
        
        // Cari urutan terakhir bagi bulan & tahun ini
        $prefix = sprintf("TBBA/%s/%s/%s/%%", strtoupper($type), $year, $month);
        $stmt = Database::query("SELECT ref_no FROM letters WHERE ref_no LIKE ? ORDER BY id DESC LIMIT 1", [$prefix]);
        $lastRef = $stmt->fetchColumn();

        if ($lastRef) {
            $parts = explode('/', $lastRef);
            $lastSeq = (int) end($parts);
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return Helper::generateLetterRef($type, $nextSeq);
    }

    // Tambah Surat Baharu
    public static function create($data): array {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $type = strtoupper((string)$data['type']);
            $year = date('Y');
            $month = date('m');
            $prefix = sprintf('TBBA/%s/%s/%s/', $type, $year, $month);
            $lastRef = Database::query(
                "SELECT ref_no FROM letters WHERE ref_no LIKE ? ORDER BY ref_no DESC LIMIT 1 FOR UPDATE",
                [$prefix . '%']
            )->fetchColumn();
            $lastSequence = $lastRef && preg_match('/(\d+)$/', (string)$lastRef, $match) ? (int)$match[1] : 0;
            $refNo = Helper::generateLetterRef($type, $lastSequence + 1);

            Database::query("INSERT INTO letters (user_id, type, ref_no, title, sender_receiver, letter_date, file_path, status, remarks)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                $data['user_id'], $type, $refNo, $data['title'], $data['sender_receiver'],
                $data['letter_date'], $data['file_path'], $data['status'] ?? 'pending', $data['remarks'] ?? null
            ]);
            $id = (int)Database::lastInsertId();
            $pdo->commit();
            return ['id' => $id, 'ref_no' => $refNo];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    // Dapatkan semua surat (Admin / Staf)
    public static function getAll($type = null, $userId = null) {
        $sql = "SELECT l.*, u.name as staff_name, u.position 
                FROM letters l 
                JOIN users u ON l.user_id = u.id ";
        $params = [];
        $conditions = [];

        if ($type) {
            $conditions[] = "l.type = ?";
            $params[] = strtoupper($type);
        }

        if ($userId) {
            $conditions[] = "l.user_id = ?";
            $params[] = $userId;
        }

        if (!empty($conditions)) {
            $sql .= "WHERE " . implode(" AND ", $conditions) . " ";
        }

        $sql .= "ORDER BY l.created_at DESC";
        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    // Kemas kini status surat (cth: Belum Dijawab -> Telah Dijawab)
    public static function updateStatus($id, $status) {
        $stmt = Database::query("UPDATE letters SET status = ? WHERE id = ?", [$status, $id]);
        return $stmt->rowCount() > 0;
    }

    // Jumlah surat belum dibaca (unread) untuk Admin Dashboard
    public static function getPendingCount() {
        $stmt = Database::query("SELECT COUNT(*) FROM letters WHERE status NOT IN ('read', 'completed', 'replied')");
        return (int) $stmt->fetchColumn();
    }

    // Ambil butiran 1 surat mengikut ID
    public static function getById($id) {
        $stmt = Database::query("SELECT l.*, u.name as staff_name, u.position FROM letters l LEFT JOIN users u ON l.user_id = u.id WHERE l.id = ?", [$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Kemas kini Minit Surat & Tandatangan Digital CEO / Assistant CEO
    public static function updateMinitAndSign($id, $minitNotes, $minitBy, $status, $applySign = false, $signedBy = null) {
        if ($applySign && $signedBy) {
            $sql = "UPDATE letters SET minit_notes = ?, minit_by = ?, minit_date = NOW(), status = ?, req_ceo_sign = 1, signed_by = ?, signed_date = NOW(), ceo_signature = ? WHERE id = ?";
            $signatureText = "DIGITALLY SIGNED BY: " . $signedBy;
            $stmt = Database::query($sql, [$minitNotes, $minitBy, $status, $signedBy, $signatureText, $id]);
        } else {
            $sql = "UPDATE letters SET minit_notes = ?, minit_by = ?, minit_date = NOW(), status = ? WHERE id = ?";
            $stmt = Database::query($sql, [$minitNotes, $minitBy, $status, $id]);
        }
        return true;
    }

    // Padam rekod surat / e-mel
    public static function delete($id) {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $letter = Database::query("SELECT ref_no,file_path FROM letters WHERE id=? FOR UPDATE", [$id])->fetch();
            if (!$letter) { $pdo->rollBack(); return false; }
            if (str_starts_with((string)$letter['ref_no'], 'EML-INQ-')) {
                $inqId = (int)substr((string)$letter['ref_no'], strlen('EML-INQ-'));
                if ($inqId > 0) Database::query("DELETE FROM inquiries WHERE id=?", [$inqId]);
            }
            Database::query("DELETE FROM letters WHERE id=?", [$id]);
            $pdo->commit();

            $path = str_replace('\\', '/', ltrim((string)($letter['file_path'] ?? ''), '/'));
            if ($path !== '' && !str_contains($path, '..')) {
                $absolute = realpath(__DIR__ . '/../' . $path);
                $root = realpath(__DIR__ . '/../storage/private/letters');
                if ($absolute && $root && is_file($absolute) && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) @unlink($absolute);
            }
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
?>
