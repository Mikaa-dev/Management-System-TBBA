<?php
/** Project Delivery & Demo Item Tracker model. */
require_once __DIR__ . '/../config/database.php';

class LogisticsRecord
{
    public const PROJECT_DELIVERY_STATUSES = ['scheduled', 'in_transit', 'delivered', 'cancelled'];
    public const DEMO_ITEM_STATUSES = ['received'];
    public const INVENTORY_STATUSES = ['received'];
    public const SHIPPING_TYPES = ['air_freight', 'ground_freight', 'sea_freight'];

    public static function statusesFor(string $type): array
    {
        if ($type === 'inventory') return self::INVENTORY_STATUSES;
        return $type === 'demo_item' ? self::DEMO_ITEM_STATUSES : self::PROJECT_DELIVERY_STATUSES;
    }

    public static function getAll(?string $type = null, ?string $status = null, ?string $search = null): array
    {
        $sql = "SELECT lr.*, ru.name AS responsible_name, cu.name AS creator_name,
                       CASE
                           WHEN lr.record_type='project_delivery'
                                AND lr.status NOT IN ('delivered','cancelled')
                                AND lr.delivery_date < CURDATE() THEN 1
                           ELSE 0
                       END AS is_overdue,
                       CASE WHEN lr.delivery_date IS NOT NULL
                            THEN DATEDIFF(lr.delivery_date, CURDATE())
                            ELSE NULL END AS days_until_due
                FROM `logistics_records` lr
                JOIN `users` ru ON ru.id=lr.responsible_user_id
                JOIN `users` cu ON cu.id=lr.created_by";
        $where = [];
        $params = [];

        if (in_array($type, ['project_delivery', 'demo_item', 'inventory'], true)) {
            $where[] = 'lr.record_type=?';
            $params[] = $type;
        }
        $validStatuses = array_values(array_unique(array_merge(self::PROJECT_DELIVERY_STATUSES, self::DEMO_ITEM_STATUSES, self::INVENTORY_STATUSES)));
        if ($status && in_array($status, $validStatuses, true)) {
            $where[] = 'lr.status=?';
            $params[] = $status;
        }
        if ($search !== null && trim($search) !== '') {
            $where[] = "CONCAT_WS(' ',lr.reference_no,lr.item_name,COALESCE(lr.supplier_name,''),COALESCE(lr.client_name,''),COALESCE(lr.contact_person,''),COALESCE(lr.description,''),COALESCE(lr.notes,'')) LIKE ?";
            $params[] = '%' . trim($search) . '%';
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY
                    CASE WHEN lr.record_type='project_delivery' AND lr.status NOT IN ('delivered','cancelled') AND lr.delivery_date < CURDATE() THEN 0 ELSE 1 END,
                    CASE WHEN lr.record_type='project_delivery' AND lr.status NOT IN ('delivered','cancelled') THEN lr.delivery_date END ASC,
                    lr.created_at DESC";

        return Database::query($sql, $params)->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $row = Database::query(
            "SELECT lr.*, ru.name AS responsible_name, cu.name AS creator_name,
                    CASE WHEN lr.record_type='project_delivery'
                                   AND lr.status NOT IN ('delivered','cancelled')
                                   AND lr.delivery_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue,
                    CASE WHEN lr.delivery_date IS NOT NULL
                         THEN DATEDIFF(lr.delivery_date, CURDATE()) ELSE NULL END AS days_until_due
             FROM `logistics_records` lr
             JOIN `users` ru ON ru.id=lr.responsible_user_id
             JOIN `users` cu ON cu.id=lr.created_by
             WHERE lr.id=? LIMIT 1",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function getStats(): array
    {
        $row = Database::query(
            "SELECT
                COUNT(*) AS total,
                SUM(record_type='project_delivery' AND status NOT IN ('delivered','cancelled')) AS active_deliveries,
                SUM(record_type='demo_item') AS demo_items,
                SUM(record_type='inventory') AS inventory_items,
                SUM(record_type='project_delivery' AND status NOT IN ('delivered','cancelled') AND delivery_date < CURDATE()) AS overdue,
                SUM(record_type='project_delivery' AND status NOT IN ('delivered','cancelled') AND delivery_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS due_soon
             FROM `logistics_records`"
        )->fetch();
        return [
            'total' => (int)($row['total'] ?? 0),
            'active_deliveries' => (int)($row['active_deliveries'] ?? 0),
            'demo_items' => (int)($row['demo_items'] ?? 0),
            'inventory_items' => (int)($row['inventory_items'] ?? 0),
            'overdue' => (int)($row['overdue'] ?? 0),
            'due_soon' => (int)($row['due_soon'] ?? 0),
        ];
    }

    public static function getForResponsible(int $userId, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));
        return Database::query(
            "SELECT lr.*,
                    CASE WHEN lr.record_type='project_delivery'
                                   AND lr.status NOT IN ('delivered','cancelled')
                                   AND lr.delivery_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue,
                    CASE WHEN lr.delivery_date IS NOT NULL
                         THEN DATEDIFF(lr.delivery_date, CURDATE()) ELSE NULL END AS days_until_due
             FROM `logistics_records` lr
             WHERE lr.responsible_user_id=?
               AND (lr.record_type='demo_item' OR lr.status NOT IN ('delivered','cancelled'))
             ORDER BY is_overdue DESC,
                      CASE WHEN lr.record_type='project_delivery' THEN lr.delivery_date END ASC,
                      lr.created_at DESC
             LIMIT {$limit}",
            [$userId]
        )->fetchAll();
    }

    public static function getStatsForResponsible(int $userId): array
    {
        $row = Database::query(
            "SELECT
                COUNT(*) AS active,
                SUM(record_type='project_delivery' AND status NOT IN ('delivered','cancelled') AND delivery_date < CURDATE()) AS overdue,
                SUM(record_type='project_delivery' AND status NOT IN ('delivered','cancelled') AND delivery_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS due_soon
             FROM `logistics_records`
             WHERE responsible_user_id=?
               AND (record_type='demo_item' OR status NOT IN ('delivered','cancelled'))",
            [$userId]
        )->fetch();
        return [
            'active' => (int)($row['active'] ?? 0),
            'overdue' => (int)($row['overdue'] ?? 0),
            'due_soon' => (int)($row['due_soon'] ?? 0),
        ];
    }

    public static function create(array $data): int
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $referenceNo = self::nextReferenceNo($data['record_type']);
            Database::query(
                "INSERT INTO `logistics_records`
                 (`reference_no`,`record_type`,`item_name`,`description`,`quantity`,`unit`,`supplier_name`,`client_name`,`contact_person`,`contact_phone`,`received_date`,`delivery_date`,`shipping_type`,`responsible_user_id`,`status`,`notes`,`created_by`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $referenceNo, $data['record_type'], $data['item_name'], $data['description'] ?? null,
                    $data['quantity'], $data['unit'], $data['supplier_name'], $data['client_name'] ?? null,
                    $data['contact_person'] ?? null, $data['contact_phone'] ?? null, $data['received_date'],
                    $data['delivery_date'] ?? null, $data['shipping_type'] ?? null, $data['responsible_user_id'],
                    $data['status'], $data['notes'] ?? null, $data['created_by'],
                ]
            );
            $id = (int) Database::lastInsertId();
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function update(int $id, array $data): void
    {
        $isComplete = $data['status'] === 'delivered';
        Database::query(
            "UPDATE `logistics_records` SET
                `record_type`=?,`item_name`=?,`description`=?,`quantity`=?,`unit`=?,`supplier_name`=?,`client_name`=?,
                `contact_person`=?,`contact_phone`=?,`received_date`=?,
                `reminder_sent_at`=CASE
                    WHEN NOT (`delivery_date` <=> ?) OR `responsible_user_id`<>? OR `record_type`<>?
                    THEN NULL ELSE `reminder_sent_at` END,
                `delivery_date`=?,`shipping_type`=?,`responsible_user_id`=?,
                `status`=?,`notes`=?,
                `completed_at`=CASE WHEN ?=1 THEN COALESCE(`completed_at`,NOW()) ELSE NULL END
             WHERE `id`=?",
            [
                $data['record_type'], $data['item_name'], $data['description'] ?? null, $data['quantity'],
                $data['unit'], $data['supplier_name'], $data['client_name'] ?? null,
                $data['contact_person'] ?? null, $data['contact_phone'] ?? null, $data['received_date'],
                $data['delivery_date'] ?? null, $data['responsible_user_id'], $data['record_type'],
                $data['delivery_date'] ?? null, $data['shipping_type'] ?? null, $data['responsible_user_id'],
                $data['status'], $data['notes'] ?? null, $isComplete ? 1 : 0, $id,
            ]
        );
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::query(
            "UPDATE `logistics_records` SET `status`=?, `completed_at`=? WHERE `id`=?",
            [$status, $status === 'delivered' ? date('Y-m-d H:i:s') : null, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::query("DELETE FROM `logistics_records` WHERE `id`=?", [$id]);
    }

    public static function dueForReminder(string $targetDate): array
    {
        return Database::query(
            "SELECT lr.*, ru.name AS responsible_name
             FROM `logistics_records` lr
             JOIN `users` ru ON ru.id=lr.responsible_user_id
             WHERE lr.record_type='project_delivery'
               AND lr.delivery_date BETWEEN CURDATE() AND ?
               AND lr.status NOT IN ('delivered','cancelled')
               AND lr.reminder_sent_at IS NULL
             ORDER BY lr.id",
            [$targetDate]
        )->fetchAll();
    }

    public static function markReminderSent(int $id): void
    {
        Database::query("UPDATE `logistics_records` SET `reminder_sent_at`=NOW() WHERE `id`=?", [$id]);
    }

    private static function nextReferenceNo(string $type): string
    {
        $prefix = $type === 'demo_item' ? 'DEMO' : 'PDV';
        $period = date('Ym');
        $base = $prefix . '-' . $period . '-';
        $last = Database::query(
            "SELECT `reference_no` FROM `logistics_records` WHERE `reference_no` LIKE ? ORDER BY `id` DESC LIMIT 1 FOR UPDATE",
            [$base . '%']
        )->fetchColumn();
        $sequence = $last ? ((int) substr((string)$last, -4) + 1) : 1;
        return $base . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    }
}
