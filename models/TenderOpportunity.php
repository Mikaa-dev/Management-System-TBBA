<?php
/** New Tender Board model. */
require_once __DIR__ . '/../config/database.php';

class TenderOpportunity {
    public static function getAll(int $currentUserId): array {
        $tenders = Database::query(
            "SELECT t.*, u.name AS creator_name,
                    COUNT(DISTINCT p.id) AS participant_count,
                    MAX(CASE WHEN p.user_id=? THEN 1 ELSE 0 END) AS joined_by_me,
                    (SELECT k.id FROM tenders k WHERE k.tender_opportunity_id=t.id AND k.user_id=? LIMIT 1) AS user_kpi_id,
                    (SELECT k.status FROM tenders k WHERE k.tender_opportunity_id=t.id AND k.user_id=? LIMIT 1) AS user_tender_status,
                    (SELECT k.indicative_price FROM tenders k WHERE k.tender_opportunity_id=t.id AND k.user_id=? LIMIT 1) AS user_indicative_price,
                    (SELECT k.cost FROM tenders k WHERE k.tender_opportunity_id=t.id AND k.user_id=? LIMIT 1) AS user_cost,
                    (SELECT k.selling_price FROM tenders k WHERE k.tender_opportunity_id=t.id AND k.user_id=? LIMIT 1) AS user_selling_price,
                    (SELECT k.pricing_attachment_name FROM tenders k WHERE k.tender_opportunity_id=t.id AND k.user_id=? LIMIT 1) AS user_pricing_attachment_name,
                    (SELECT k.pricing_updated_at FROM tenders k WHERE k.tender_opportunity_id=t.id AND k.user_id=? LIMIT 1) AS user_pricing_updated_at
             FROM `tender_opportunities` t
             INNER JOIN `users` u ON u.id=t.created_by
             LEFT JOIN `tender_opportunity_participants` p ON p.tender_id=t.id
             GROUP BY t.id, u.name
             ORDER BY t.tender_date DESC, t.created_at DESC",
            [
                $currentUserId, $currentUserId,
                $currentUserId, $currentUserId, $currentUserId,
                $currentUserId, $currentUserId, $currentUserId,
            ]
        )->fetchAll();

        if (!$tenders) return [];
        $ids = array_map(fn($row) => (int)$row['id'], $tenders);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $participants = Database::query(
            "SELECT p.tender_id, p.user_id, p.created_at,
                    u.name, COALESCE(pos.title, u.position) AS position_title,
                    d.name AS department_name
             FROM `tender_opportunity_participants` p
             INNER JOIN `users` u ON u.id=p.user_id
             LEFT JOIN `positions` pos ON pos.id=u.position_id
             LEFT JOIN `departments` d ON d.id=u.department_id
             WHERE p.tender_id IN ({$placeholders})
             ORDER BY u.name ASC",
            $ids
        )->fetchAll();

        $byTender = [];
        foreach ($participants as $participant) {
            $byTender[(int)$participant['tender_id']][] = $participant;
        }
        foreach ($tenders as &$tender) {
            $tender['id'] = (int)$tender['id'];
            $tender['participant_count'] = (int)$tender['participant_count'];
            $tender['joined_by_me'] = (bool)$tender['joined_by_me'];
            $tender['user_kpi_id'] = $tender['user_kpi_id'] !== null ? (int)$tender['user_kpi_id'] : null;
            $tender['user_indicative_price'] = $tender['user_indicative_price'] !== null ? (float)$tender['user_indicative_price'] : null;
            $tender['user_cost'] = $tender['user_cost'] !== null ? (float)$tender['user_cost'] : null;
            $tender['user_selling_price'] = $tender['user_selling_price'] !== null ? (float)$tender['user_selling_price'] : null;
            $tender['user_pricing_complete'] = $tender['user_indicative_price'] !== null
                && $tender['user_cost'] !== null
                && $tender['user_selling_price'] !== null;
            $tender['participants'] = $byTender[$tender['id']] ?? [];
        }
        unset($tender);
        return $tenders;
    }

    public static function findById(int $id): ?array {
        $row = Database::query(
            "SELECT t.*, u.name AS creator_name
             FROM `tender_opportunities` t
             INNER JOIN `users` u ON u.id=t.created_by
             WHERE t.id=? LIMIT 1",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(string $qtNumber, string $title, string $closingDate, int $createdBy): int {
        Database::query(
            "INSERT INTO `tender_opportunities` (`qt_number`,`title`,`tender_date`,`closing_date`,`created_by`)
             VALUES (?,?,CURDATE(),?,?)",
            [$qtNumber, $title, $closingDate, $createdBy]
        );
        return (int)Database::lastInsertId();
    }

    public static function update(int $id, string $qtNumber, string $title, string $closingDate): void {
        $pdo = Database::getInstance();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            Database::query(
                "UPDATE `tender_opportunities` SET `qt_number`=?, `title`=?, `closing_date`=? WHERE `id`=?",
                [$qtNumber, $title, $closingDate, $id]
            );
            Database::query(
                "UPDATE `tenders` SET `project_name`=?, `client_name`=?, `closing_date`=?
                 WHERE `tender_opportunity_id`=?",
                [$title, 'QT: ' . $qtNumber, $closingDate, $id]
            );
            if ($ownsTransaction) $pdo->commit();
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): void {
        Database::query("DELETE FROM `tender_opportunities` WHERE `id`=?", [$id]);
    }

    public static function isQtNumberTaken(string $qtNumber, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM `tender_opportunities` WHERE `qt_number`=?";
        $params = [$qtNumber];
        if ($excludeId !== null) {
            $sql .= " AND `id`<>?";
            $params[] = $excludeId;
        }
        return (int)Database::query($sql, $params)->fetchColumn() > 0;
    }

    /**
     * Returns true when joined and false when the current assignee leaves.
     * The tender row is locked so two staff members cannot claim it concurrently.
     */
    public static function toggleParticipant(int $tenderId, int $userId): bool {
        $pdo = Database::getInstance();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();

        try {
            $tenderExists = Database::query(
                "SELECT `id` FROM `tender_opportunities` WHERE `id`=? FOR UPDATE",
                [$tenderId]
            )->fetchColumn();
            if (!$tenderExists) {
                throw new DomainException('Tender record not found.');
            }

            $participant = Database::query(
                "SELECT `user_id` FROM `tender_opportunity_participants` WHERE `tender_id`=? LIMIT 1",
                [$tenderId]
            )->fetch();

            if ($participant) {
                if ((int)$participant['user_id'] !== $userId) {
                    throw new DomainException('This tender has already been assigned to another staff member.');
                }
                Database::query(
                    "DELETE FROM `tender_opportunity_participants` WHERE `tender_id`=? AND `user_id`=?",
                    [$tenderId, $userId]
                );
                if ($ownsTransaction) $pdo->commit();
                return false;
            }

            Database::query(
                "INSERT INTO `tender_opportunity_participants` (`tender_id`,`user_id`) VALUES (?,?)",
                [$tenderId, $userId]
            );
            if ($ownsTransaction) $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function isParticipant(int $tenderId, int $userId): bool {
        return (bool)Database::query(
            "SELECT 1 FROM `tender_opportunity_participants` WHERE `tender_id`=? AND `user_id`=? LIMIT 1",
            [$tenderId, $userId]
        )->fetchColumn();
    }
}
