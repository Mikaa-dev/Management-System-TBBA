<?php
/** Project Delivery & Demo Item Tracker controller. */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/LogisticsRecord.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../core/NotificationService.php';

class LogisticsController
{
    public static function index(): void
    {
        Auth::requirePermission('logistics', 'view');
        $pageTitle = 'Project Delivery & Demo Item Tracker';
        $typeFilter = trim($_GET['type'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');
        $search = trim($_GET['search'] ?? '');
        $records = LogisticsRecord::getAll($typeFilter ?: null, $statusFilter ?: null, $search ?: null);
        $stats = LogisticsRecord::getStats();
        $users = User::getAll();
        include __DIR__ . '/../views/logistics/index.php';
    }

    public static function getRecord(): void
    {
        Auth::requirePermission('logistics', 'view');
        $record = LogisticsRecord::findById((int)($_GET['id'] ?? 0));
        if (!$record) {
            Helper::json('error', 'Record not found.');
        }
        Helper::json('success', 'Record retrieved.', $record);
    }

    public static function store(): void
    {
        Auth::requirePermission('logistics', 'create');
        self::requirePostAndCsrf();
        $data = self::validatePayload();
        $data['created_by'] = (int)Auth::id();

        try {
            $id = LogisticsRecord::create($data);
            $record = LogisticsRecord::findById($id);
            self::sendDueReminderIfNeeded($record);
            AuditLog::record('LOGISTICS_CREATE', "Created {$data['record_type']} record {$record['reference_no']}: {$data['item_name']}");
            Helper::json('success', 'Logistics record created successfully.', ['id' => $id]);
        } catch (Throwable $e) {
            error_log('[LogisticsController] create error: ' . $e->getMessage());
            Helper::json('error', 'Unable to create the record. Please try again.');
        }
    }

    public static function update(): void
    {
        Auth::requirePermission('logistics', 'edit');
        self::requirePostAndCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $existing = LogisticsRecord::findById($id);
        if (!$existing) {
            Helper::json('error', 'Record not found.');
        }

        $data = self::validatePayload();
        try {
            LogisticsRecord::update($id, $data);
            self::sendDueReminderIfNeeded(LogisticsRecord::findById($id));
            AuditLog::record('LOGISTICS_UPDATE', "Updated {$existing['reference_no']}: {$data['item_name']} ({$data['status']})");
            Helper::json('success', 'Record updated successfully.');
        } catch (Throwable $e) {
            error_log('[LogisticsController] update error: ' . $e->getMessage());
            Helper::json('error', 'Unable to update the record. Please try again.');
        }
    }

    public static function updateStatus(): void
    {
        Auth::requirePermission('logistics', 'edit');
        self::requirePostAndCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $record = LogisticsRecord::findById($id);
        if (!$record) {
            Helper::json('error', 'Record not found.');
        }
        if (!in_array($status, LogisticsRecord::statusesFor($record['record_type']), true)) {
            Helper::json('error', 'Invalid status for this record type.');
        }
        LogisticsRecord::updateStatus($id, $status);
        AuditLog::record('LOGISTICS_STATUS', "Updated {$record['reference_no']} status to {$status}");
        Helper::json('success', 'Status updated successfully.');
    }

    public static function delete(): void
    {
        Auth::requirePermission('logistics', 'delete');
        self::requirePostAndCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $record = LogisticsRecord::findById($id);
        if (!$record) {
            Helper::json('error', 'Record not found.');
        }
        LogisticsRecord::delete($id);
        AuditLog::record('LOGISTICS_DELETE', "Deleted {$record['reference_no']}: {$record['item_name']}");
        Helper::json('success', 'Record deleted successfully.');
    }

    private static function requirePostAndCsrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }
        if (!Helper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Helper::json('error', 'Invalid CSRF token security.');
        }
    }

    private static function validatePayload(): array
    {
        $type = trim($_POST['record_type'] ?? '');
        $isProjectDelivery = $type === 'project_delivery';
        $itemName = trim($_POST['item_name'] ?? '');
        $supplierName = trim($_POST['supplier_name'] ?? '');
        $receivedDate = trim($_POST['received_date'] ?? '');
        $clientName = trim($_POST['client_name'] ?? '');
        $deliveryDate = trim($_POST['delivery_date'] ?? '');
        $shippingType = trim($_POST['shipping_type'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $responsibleId = (int)($_POST['responsible_user_id'] ?? 0);
        $quantityRaw = $_POST['quantity'] ?? 1;
        $quantity = is_numeric($quantityRaw) ? (float)$quantityRaw : 0;
        $unit = trim($_POST['unit'] ?? '');

        if (!in_array($type, ['project_delivery', 'demo_item', 'inventory'], true)) {
            Helper::json('error', 'Please select a valid record type.');
        }
        if ($itemName === '') {
            Helper::json('error', 'Item name is required.');
        }
        if ($supplierName === '') {
            Helper::json('error', 'Supplier name is required.');
        }
        if (!self::validDate($receivedDate)) {
            Helper::json('error', 'A valid date item received is required.');
        }
        if ($quantity <= 0) {
            Helper::json('error', 'Quantity must be greater than zero.');
        }
        if ($unit === '') {
            Helper::json('error', 'Unit is required.');
        }
        if (!User::findById($responsibleId)) {
            Helper::json('error', 'Please select a valid PIC.');
        }

        if ($isProjectDelivery) {
            if ($clientName === '') {
                Helper::json('error', 'Client name is required for a project delivery.');
            }
            if (!self::validDate($deliveryDate)) {
                Helper::json('error', 'A valid delivery date is required.');
            }
            if ($deliveryDate < $receivedDate) {
                Helper::json('error', 'Delivery date cannot be earlier than the date item received.');
            }
            if (!in_array($shippingType, LogisticsRecord::SHIPPING_TYPES, true)) {
                Helper::json('error', 'Please select a valid shipping type.');
            }
            if ($contactPerson === '') {
                Helper::json('error', 'Client contact person is required.');
            }
            $status = trim($_POST['status'] ?? '');
            if (!in_array($status, LogisticsRecord::PROJECT_DELIVERY_STATUSES, true)) {
                Helper::json('error', 'Invalid project delivery status.');
            }
        } else {
            $clientName = '';
            $deliveryDate = '';
            $shippingType = '';
            $contactPerson = '';
            $status = 'received';
        }

        return [
            'record_type' => $type,
            'item_name' => $itemName,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'quantity' => $quantity,
            'unit' => $unit,
            'supplier_name' => $supplierName,
            'client_name' => $clientName ?: null,
            'contact_person' => $contactPerson ?: null,
            'contact_phone' => $isProjectDelivery ? (trim($_POST['contact_phone'] ?? '') ?: null) : null,
            'received_date' => $receivedDate,
            'delivery_date' => $deliveryDate ?: null,
            'shipping_type' => $shippingType ?: null,
            'responsible_user_id' => $responsibleId,
            'status' => $status,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
        ];
    }

    private static function validDate(string $date): bool
    {
        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    /** Immediately notify the PIC when a delivery is created or updated inside the H-7 window. */
    private static function sendDueReminderIfNeeded(?array $record): void
    {
        if (!$record || $record['record_type'] !== 'project_delivery' || empty($record['delivery_date'])) {
            return;
        }
        if (!empty($record['reminder_sent_at']) || in_array($record['status'], ['delivered', 'cancelled'], true)) {
            return;
        }

        $today = date('Y-m-d');
        $sevenDaysFromNow = date('Y-m-d', strtotime('+7 days'));
        if ($record['delivery_date'] < $today || $record['delivery_date'] > $sevenDaysFromNow) {
            return;
        }

        $days = max(0, (int)$record['days_until_due']);
        NotificationService::notifyAndPush(
            (int)$record['responsible_user_id'],
            'delivery_due_reminder',
            '🚚 Project Delivery Reminder',
            "'{$record['item_name']}' must be delivered to {$record['client_name']} in {$days} day(s) ({$record['delivery_date']}).",
            'fa-truck-fast',
            '#0F766E',
            'index.php?page=logistics',
            'delivery_due_reminder'
        );
        LogisticsRecord::markReminderSent((int)$record['id']);
    }
}
