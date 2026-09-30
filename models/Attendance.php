<?php
/**
 * Model Kehadiran GPS (Attendance Model) - PDO
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../config/database.php';

class Attendance {
    private static $schemaChecked = false;

    // Verify required columns; schema changes belong in migrations.
    public static function ensureColumns() {
        if (self::$schemaChecked) return;
        try {
            Database::query("SELECT status, reason, photo, is_out_of_range, clock_out_reason, clock_out_photo FROM attendance LIMIT 0");
            self::$schemaChecked = true;
        } catch (Throwable $e) {
            throw new RuntimeException('Attendance schema is unavailable. Run the database migrations.', 0, $e);
        }
    }

    // Dapatkan rekod kehadiran hari ini bagi pengguna tertentu
    public static function getToday($userId) {
        self::ensureColumns();
        $today = date('Y-m-d');
        $stmt = Database::query("SELECT * FROM attendance WHERE user_id = ? AND date = ? LIMIT 1", [$userId, $today]);
        return $stmt->fetch();
    }

    // Rekod masuk (Clock In) - Gunakan masa server!
    public static function clockIn($userId, $lat, $lng, $reason = null, $photo = null, $isOutOfRange = 0) {
        self::ensureColumns();
        $today = date('Y-m-d');
        $now   = date('Y-m-d H:i:s');
        
        // Semak sama ada lewat (Selepas jam 8:45 pagi dianggap lambat)
        $hour = (int) date('H');
        $minute = (int) date('i');
        $status = ($hour > 8 || ($hour == 8 && $minute > 45)) ? 'late' : 'present';

        $stmt = Database::query("INSERT INTO attendance (user_id, date, clock_in, clock_in_lat, clock_in_lng, status, reason, photo, is_out_of_range) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $userId, $today, $now, $lat, $lng, $status, $reason, $photo, $isOutOfRange ? 1 : 0
        ]);
        
        return Database::lastInsertId();
    }

    // Rekod keluar (Clock Out) - Gunakan masa server!
    public static function clockOut($userId, $lat, $lng, $reason = null, $photo = null, $isOutOfRange = 0) {
        self::ensureColumns();
        $today = date('Y-m-d');
        $now   = date('Y-m-d H:i:s');

        $stmt = Database::query("UPDATE attendance 
                                 SET clock_out = ?, clock_out_lat = ?, clock_out_lng = ?, clock_out_reason = COALESCE(?, clock_out_reason), clock_out_photo = COALESCE(?, clock_out_photo), is_out_of_range = GREATEST(COALESCE(is_out_of_range, 0), ?) 
                                 WHERE user_id = ? AND date = ?", [
            $now, $lat, $lng, $reason, $photo, $isOutOfRange ? 1 : 0, $userId, $today
        ]);

        return $stmt->rowCount() > 0;
    }

    // Dapatkan sejarah kehadiran (Bagi Admin: semua; Bagi Staf: sendiri) - Sokong penapis bulan, tahun, staf & department
    public static function getHistory($userId = null, $limit = 50, $month = null, $year = null, $staffId = null, $deptId = null) {
        self::ensureColumns();
        $conditions = [];
        $params = [];

        if ($userId) {
            $conditions[] = "a.user_id = ?";
            $params[] = $userId;
        } elseif ($staffId) {
            $conditions[] = "a.user_id = ?";
            $params[] = $staffId;
        }

        if ($deptId !== null && (int)$deptId > 0) {
            $conditions[] = "u.department_id = ?";
            $params[] = (int)$deptId;
        }

        if ($month && $month !== 'all') {
            $conditions[] = "MONTH(a.date) = ?";
            $params[] = (int)$month;
        }

        if ($year && $year !== 'all') {
            $conditions[] = "YEAR(a.date) = ?";
            $params[] = (int)$year;
        }

        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

        $sql = "SELECT a.*, u.name, u.position, u.department_id 
                FROM attendance a 
                JOIN users u ON a.user_id = u.id 
                {$whereClause} 
                ORDER BY a.date DESC, a.clock_in DESC";

        if ($limit !== null && (int)$limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    // Statistik ringkasan kehadiran mengikut penapis bulan / tahun / staf / department
    public static function getFilterSummary($month = null, $year = null, $userId = null, $staffId = null, $deptId = null) {
        $conditions = [];
        $params = [];

        if ($userId) {
            $conditions[] = "a.user_id = ?";
            $params[] = $userId;
        } elseif ($staffId) {
            $conditions[] = "a.user_id = ?";
            $params[] = $staffId;
        }

        if ($deptId !== null && (int)$deptId > 0) {
            $conditions[] = "u.department_id = ?";
            $params[] = (int)$deptId;
        }

        if ($month && $month !== 'all') {
            $conditions[] = "MONTH(a.date) = ?";
            $params[] = (int)$month;
        }

        if ($year && $year !== 'all') {
            $conditions[] = "YEAR(a.date) = ?";
            $params[] = (int)$year;
        }

        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

        $sql = "SELECT 
                    COUNT(*) as total_records,
                    SUM(CASE WHEN LOWER(a.status) IN ('present', 'on_time', 'on time') THEN 1 ELSE 0 END) as total_present,
                    SUM(CASE WHEN LOWER(a.status) = 'late' THEN 1 ELSE 0 END) as total_late,
                    SUM(CASE WHEN LOWER(a.status) IN ('mc', 'leave', 'half_day', 'absent') THEN 1 ELSE 0 END) as total_others
                FROM attendance a 
                LEFT JOIN users u ON a.user_id = u.id
                {$whereClause}";

        $stmt = Database::query($sql, $params);
        $row = $stmt->fetch();
        return $row ?: ['total_records' => 0, 'total_present' => 0, 'total_late' => 0, 'total_others' => 0];
    }


    // Statistik kehadiran harian untuk Admin Dashboard
    public static function getTodayStats() {
        $today = date('Y-m-d');
        
        // Jumlah hadir hari ini
        $stmtPresent = Database::query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = ?", [$today]);
        $presentCount = (int) $stmtPresent->fetchColumn();

        // Jumlah keseluruhan pengguna (staf & admin)
        $stmtTotal = Database::query("SELECT COUNT(*) FROM users");
        $totalStaff = (int) $stmtTotal->fetchColumn();

        // Jumlah lewat hari ini
        $stmtLate = Database::query("SELECT COUNT(*) FROM attendance WHERE date = ? AND status = 'late'", [$today]);
        $lateCount = (int) $stmtLate->fetchColumn();

        return [
            'present'     => $presentCount,
            'total_staff' => $totalStaff,
            'late'        => $lateCount,
            'percentage'  => $totalStaff > 0 ? round(($presentCount / $totalStaff) * 100) : 0
        ];
    }

    // Pastikan lajur status boleh menerima string bebas seperti 'MC'
    public static function ensureFlexibleStatusColumn() {
        self::ensureColumns();
    }

    // Dapatkan rekod kehadiran berdasarkan ID (Untuk Admin Edit)
    public static function getById($id) {
        $stmt = Database::query("SELECT a.*, u.name, u.position 
                                 FROM attendance a 
                                 JOIN users u ON a.user_id = u.id 
                                 WHERE a.id = ? LIMIT 1", [$id]);
        return $stmt->fetch();
    }

    // Dapatkan rekod kehadiran staf untuk tarikh tertentu
    public static function getByDate($userId, $date) {
        $stmt = Database::query("SELECT * FROM attendance WHERE user_id = ? AND date = ? LIMIT 1", [$userId, $date]);
        return $stmt->fetch();
    }

    // Simpan atau Kemas Kini Rekod Kehadiran Manual oleh Admin (tanpa API koordinat GPS)
    public static function saveManualRecord($data) {
        self::ensureFlexibleStatusColumn();

        $id       = !empty($data['id']) ? (int)$data['id'] : null;
        $userId   = (int)$data['user_id'];
        $date     = $data['date'];
        $status   = !empty($data['status']) ? trim($data['status']) : 'MC';

        $clockInTime  = !empty($data['clock_in']) ? trim($data['clock_in']) : null;
        $clockOutTime = !empty($data['clock_out']) ? trim($data['clock_out']) : null;

        $clockIn = null;
        if ($clockInTime) {
            if (strpos($clockInTime, '-') !== false && strlen($clockInTime) > 10) {
                $clockIn = $clockInTime;
            } else {
                $clockIn = $date . ' ' . (strlen($clockInTime) === 5 ? $clockInTime . ':00' : $clockInTime);
            }
        }

        $clockOut = null;
        if ($clockOutTime) {
            if (strpos($clockOutTime, '-') !== false && strlen($clockOutTime) > 10) {
                $clockOut = $clockOutTime;
            } else {
                $clockOut = $date . ' ' . (strlen($clockOutTime) === 5 ? $clockOutTime . ':00' : $clockOutTime);
            }
        }

        if ($id) {
            Database::query("UPDATE attendance 
                             SET user_id = ?, date = ?, clock_in = ?, clock_out = ?, status = ? 
                             WHERE id = ?", [
                $userId, $date, $clockIn, $clockOut, $status, $id
            ]);
            return $id;
        }

        $existing = self::getByDate($userId, $date);
        if ($existing) {
            Database::query("UPDATE attendance 
                             SET clock_in = ?, clock_out = ?, status = ? 
                             WHERE id = ?", [
                $clockIn, $clockOut, $status, $existing['id']
            ]);
            return $existing['id'];
        } else {
            Database::query("INSERT INTO attendance (user_id, date, clock_in, clock_out, clock_in_lat, clock_in_lng, clock_out_lat, clock_out_lng, status) 
                             VALUES (?, ?, ?, ?, NULL, NULL, NULL, NULL, ?)", [
                $userId, $date, $clockIn, $clockOut, $status
            ]);
            return Database::lastInsertId();
        }
    }
}
?>
