<?php
/**
 * Model Pengurusan Tender & KPI Tracker (PDO)
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../config/database.php';

class Tender {
    private static bool $schemaChecked = false;

    // Tambah Tender Baharu
    public static function create($data) {
        $stmt = Database::query("INSERT INTO tenders (user_id, project_name, client_name, project_value, closing_date, attachment, status, month_year) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [
            $data['user_id'],
            $data['project_name'],
            $data['client_name'],
            $data['project_value'],
            $data['closing_date'],
            $data['attachment'],
            $data['status'] ?? 'submitted',
            $data['month_year']
        ]);
        
        return Database::lastInsertId();
    }

    /** Create the current user's KPI entry when they join a Tender Board item. */
    public static function createFromOpportunity(int $userId, array $opportunity): int {
        Database::query(
            "INSERT INTO tenders
                (user_id, tender_opportunity_id, project_name, client_name, project_value, closing_date, attachment, status, month_year)
             VALUES (?, ?, ?, ?, 0, ?, NULL, 'in_progress', ?)",
            [
                $userId,
                (int)$opportunity['id'],
                $opportunity['title'],
                'QT: ' . $opportunity['qt_number'],
                $opportunity['closing_date'],
                date('Y-m'),
            ]
        );
        return (int)Database::lastInsertId();
    }

    public static function getByOpportunityAndUser(int $opportunityId, int $userId): ?array {
        $row = Database::query(
            "SELECT * FROM tenders WHERE tender_opportunity_id=? AND user_id=? LIMIT 1",
            [$opportunityId, $userId]
        )->fetch();
        return $row ?: null;
    }

    /** Find tenders closing on a specific date */
    public static function getClosingSoon(string $date): array {
        return Database::query(
            "SELECT t.*, u.name as user_name 
             FROM tenders t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.closing_date = ? AND t.status IN ('in_progress', 'submitted')",
            [$date]
        )->fetchAll();
    }

    /** Save the current participant's complete project pricing details. */
    public static function updateBoardPricing(
        int $opportunityId,
        int $userId,
        float $indicativePrice,
        float $cost,
        float $sellingPrice,
        ?string $attachmentPath,
        ?string $attachmentName
    ): void {
        Database::query(
            "UPDATE `tenders`
             SET `indicative_price`=?, `cost`=?, `selling_price`=?, `project_value`=?,
                 `pricing_attachment`=?, `pricing_attachment_name`=?, `pricing_updated_at`=NOW()
             WHERE `tender_opportunity_id`=? AND `user_id`=?",
            [
                $indicativePrice,
                $cost,
                $sellingPrice,
                $sellingPrice,
                $attachmentPath,
                $attachmentName,
                $opportunityId,
                $userId,
            ]
        );
    }

    /** Tender Board entries joined by one staff member for a selected month. */
    public static function getBoardMonthlyReport(int $userId, string $monthYear): array {
        self::ensureColumns();
        return Database::query(
            "SELECT t.id, t.status, t.month_year, t.created_at,
                    t.indicative_price, t.cost, t.selling_price,
                    t.pricing_attachment_name, t.pricing_updated_at,
                    t.post_mortem_summary, t.post_mortem_factors,
                    t.swot_s, t.swot_w, t.swot_o, t.swot_t,
                    o.id AS opportunity_id, o.qt_number, o.title,
                    o.tender_date, o.closing_date,
                    p.created_at AS joined_at,
                    u.name AS staff_name, u.email AS staff_email,
                    COALESCE(pos.title, u.position) AS position_title,
                    d.name AS department_name
             FROM `tenders` t
             INNER JOIN `tender_opportunities` o ON o.id=t.tender_opportunity_id
             INNER JOIN `tender_opportunity_participants` p
                     ON p.tender_id=o.id AND p.user_id=t.user_id
             INNER JOIN `users` u ON u.id=t.user_id
             LEFT JOIN `positions` pos ON pos.id=u.position_id
             LEFT JOIN `departments` d ON d.id=u.department_id
             WHERE t.user_id=? AND t.month_year=?
             ORDER BY p.created_at ASC, o.closing_date ASC, o.qt_number ASC",
            [$userId, $monthYear]
        )->fetchAll();
    }

    public static function deleteByOpportunityAndUser(int $opportunityId, int $userId): void {
        Database::query(
            "DELETE FROM tenders WHERE tender_opportunity_id=? AND user_id=?",
            [$opportunityId, $userId]
        );
    }

    public static function getPricingAttachmentsByOpportunity(int $opportunityId): array {
        return Database::query(
            "SELECT `pricing_attachment` FROM `tenders`
             WHERE `tender_opportunity_id`=? AND `pricing_attachment` IS NOT NULL",
            [$opportunityId]
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function deleteByOpportunity(int $opportunityId): void {
        Database::query("DELETE FROM `tenders` WHERE `tender_opportunity_id`=?", [$opportunityId]);
    }

    // Dapatkan tender berdasarkan ID
    public static function getById($id) {
        self::ensureColumns();
        $stmt = Database::query("SELECT * FROM tenders WHERE id = ? LIMIT 1", [$id]);
        return $stmt->fetch();
    }

    // Dapatkan semua tender (Admin View) - Urut supaya due date terdekat di atas
    public static function getAll($monthYear = null) {
        self::ensureColumns();
        $sql = "SELECT t.*, u.name as staff_name, u.position 
                FROM tenders t 
                JOIN users u ON t.user_id = u.id ";
        $params = [];

        if ($monthYear) {
            $sql .= "WHERE t.month_year = ? ";
            $params[] = $monthYear;
        }

        $sql .= "ORDER BY u.name ASC, (CASE WHEN t.closing_date >= CURDATE() THEN 0 ELSE 1 END) ASC, t.closing_date ASC, t.created_at DESC";
        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    // Dapatkan tender mengikut Staf - Urut supaya due date terdekat di atas
    public static function getByUser($userId, $monthYear = null) {
        self::ensureColumns();
        $sql = "SELECT t.*, u.name as staff_name 
                FROM tenders t 
                JOIN users u ON t.user_id = u.id 
                WHERE t.user_id = ? ";
        $params = [$userId];

        if ($monthYear) {
            $sql .= "AND t.month_year = ? ";
            $params[] = $monthYear;
        }

        $sql .= "ORDER BY (CASE WHEN t.closing_date >= CURDATE() THEN 0 ELSE 1 END) ASC, t.closing_date ASC, t.created_at DESC";
        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    // Kira KPI Bulan Semasa untuk Staf tertentu (Sasaran: 4 tender/bulan)
    public static function getKpi($userId, $monthYear = null) {
        if (!$monthYear) $monthYear = date('Y-m');

        $stmt = Database::query("SELECT COUNT(*) FROM tenders WHERE user_id = ? AND month_year = ?", [$userId, $monthYear]);
        $count = (int) $stmt->fetchColumn();
        $target = 4;
        $percentage = $count >= $target ? 100 : round(($count / $target) * 100);

        return [
            'count'      => $count,
            'target'     => $target,
            'percentage' => $percentage,
            'month_year' => $monthYear
        ];
    }

    // Kira Purata KPI Syarikat (Keseluruhan Staf) untuk Admin Dashboard
    public static function getCompanyKpi($monthYear = null) {
        if (!$monthYear) $monthYear = date('Y-m');

        // Dapatkan semua pengguna (staf & admin)
        $stmtStaff = Database::query("SELECT id, name FROM users");
        $staffList = $stmtStaff->fetchAll();

        if (empty($staffList)) {
            return ['total_tenders' => 0, 'average_percentage' => 0, 'staff_kpi' => []];
        }

        $totalPercentage = 0;
        $totalTenders = 0;
        $staffKpiData = [];

        foreach ($staffList as $staf) {
            $kpi = self::getKpi($staf['id'], $monthYear);
            $totalPercentage += $kpi['percentage'];
            $totalTenders += $kpi['count'];
            $staffKpiData[] = [
                'name'       => $staf['name'],
                'count'      => $kpi['count'],
                'percentage' => $kpi['percentage']
            ];
        }

        $avgPercentage = round($totalPercentage / count($staffList));

        return [
            'total_tenders'      => $totalTenders,
            'average_percentage' => $avgPercentage,
            'staff_kpi'          => $staffKpiData,
            'month_year'         => $monthYear
        ];
    }

    // Verify required columns; schema changes belong in migrations.
    public static function ensureColumns() {
        if (self::$schemaChecked) return;
        try {
            Database::query("SELECT post_mortem_summary, post_mortem_factors, swot_s, swot_w, swot_o, swot_t FROM tenders LIMIT 0");
            self::$schemaChecked = true;
        } catch (Throwable $e) {
            throw new RuntimeException('Tender schema is unavailable. Run the database migrations.', 0, $e);
        }
    }

    // Kemas kini status tender, post mortem & analisa SWOT (Admin & Pemilik)
    public static function updateStatus($id, $status, $summary = null, $factors = null, $swotS = null, $swotW = null, $swotO = null, $swotT = null) {
        self::ensureColumns();
        if ($summary !== null || $factors !== null || $swotS !== null) {
            return Database::query("UPDATE tenders SET status = ?, post_mortem_summary = ?, post_mortem_factors = ?, swot_s = ?, swot_w = ?, swot_o = ?, swot_t = ? WHERE id = ?", [$status, $summary, $factors, $swotS, $swotW, $swotO, $swotT, $id]);
        }
        return Database::query("UPDATE tenders SET status = ? WHERE id = ?", [$status, $id]);
    }

    // Delete tender record
    public static function delete($id) {
        self::ensureColumns();
        return Database::query("DELETE FROM tenders WHERE id = ?", [$id]);
    }
}
?>
