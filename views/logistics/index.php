<?php
/** Project Delivery & Demo Item Tracker view. */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';

$canCreate = Auth::hasPermission('logistics', 'create');
$canEdit = Auth::hasPermission('logistics', 'edit');
$canDelete = Auth::hasPermission('logistics', 'delete');
$statusLabels = [
    'scheduled' => 'Scheduled',
    'in_transit' => 'In Transit',
    'delivered' => 'Delivered',
    'received' => 'Received',
    'cancelled' => 'Cancelled',
];
$statusStyles = [
    'scheduled' => ['#DBEAFE', '#1D4ED8'],
    'in_transit' => ['#FEF3C7', '#B45309'],
    'delivered' => ['#DCFCE7', '#15803D'],
    'received' => ['#EDE9FE', '#6D28D9'],
    'cancelled' => ['#F1F5F9', '#64748B'],
];
$shippingLabels = [
    'air_freight' => 'Air Freight',
    'ground_freight' => 'Ground Freight',
    'sea_freight' => 'Sea Freight',
];
?>

<style>
.logistics-page { padding:24px; }
.logistics-hero { background:linear-gradient(135deg,#0F172A 0%,#0F766E 100%);color:#fff;border-radius:20px;padding:28px;margin-bottom:22px;position:relative;overflow:hidden;box-shadow:0 14px 30px rgba(15,118,110,.18); }
.logistics-hero:after { content:'';position:absolute;width:260px;height:260px;border-radius:50%;right:-70px;top:-110px;background:rgba(255,255,255,.09); }
.logistics-hero-inner { position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap; }
.logistics-kicker { display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.13);color:#99F6E4;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em; }
.logistics-hero h1 { font-size:27px;margin:10px 0 6px; }
.logistics-hero p { color:#CCFBF1;margin:0;max-width:720px;font-size:13px;line-height:1.6; }
.logistics-add-btn { display:inline-flex;align-items:center;gap:8px;background:#fff;color:#0F766E;border:0;border-radius:10px;padding:11px 17px;font-weight:800;cursor:pointer;box-shadow:0 6px 16px rgba(0,0,0,.14); }
.logistics-stats { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px; }
.logistics-stat { background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;padding:17px;display:flex;align-items:center;gap:13px; }
.logistics-stat-icon { width:42px;height:42px;border-radius:12px;display:grid;place-items:center;font-size:17px;flex:0 0 auto; }
.logistics-stat strong { display:block;color:var(--text-dark);font-size:24px;line-height:1.1; }
.logistics-stat span { color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase; }
.logistics-filter { background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;padding:14px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center; }
.logistics-filter input,.logistics-filter select { border:1px solid var(--border-color);background:var(--bg-primary);color:var(--text-dark);border-radius:9px;padding:9px 11px;font-size:13px;min-height:38px; }
.logistics-filter input { min-width:240px;flex:1; }
.logistics-filter button,.logistics-filter a { border:0;border-radius:9px;padding:10px 14px;text-decoration:none;font-size:12px;font-weight:700;cursor:pointer; }
.logistics-table-card { background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;overflow:hidden; }
.logistics-table-wrap { overflow-x:auto; }
.logistics-table { width:100%;border-collapse:collapse;font-size:12px; }
.logistics-table th { padding:12px 14px;text-align:left;background:var(--bg-primary);color:var(--text-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap; }
.logistics-table td { padding:13px 14px;border-top:1px solid var(--border-color);vertical-align:middle;color:var(--text-dark); }
.logistics-table tr.overdue-row td:first-child { border-left:4px solid #EF4444; }
.logistics-type { display:inline-flex;align-items:center;gap:6px;border-radius:7px;padding:4px 8px;font-size:10px;font-weight:800;text-transform:uppercase;white-space:nowrap; }
.logistics-status { display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:10px;font-weight:800;white-space:nowrap; }
.due-badge { display:block;font-size:10px;font-weight:700;margin-top:4px; }
.action-btn { width:30px;height:30px;display:inline-grid;place-items:center;border:1px solid var(--border-color);border-radius:7px;background:transparent;color:var(--text-dark);cursor:pointer;margin:1px; }
.action-btn:hover { background:var(--bg-primary); }
.form-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px; }
.form-group.full,.form-section-title { grid-column:1/-1; }
.form-group label { display:block;color:var(--text-dark);font-size:12px;font-weight:700;margin-bottom:6px; }
.form-group input,.form-group select,.form-group textarea { width:100%;box-sizing:border-box;border:1px solid var(--border-color);background:var(--bg-primary);color:var(--text-dark);border-radius:9px;padding:10px 11px;font:inherit;font-size:13px; }
.form-group textarea { resize:vertical; }
.record-type-picker { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
.record-type-option { border:1px solid var(--border-color);border-radius:10px;padding:12px;display:flex;align-items:center;gap:9px;cursor:pointer;color:var(--text-dark);font-size:12px;font-weight:700; }
.record-type-option:has(input:checked) { border-color:#0F766E;background:#F0FDFA;color:#0F766E; }
.form-section-title { color:#0F766E;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border-top:1px solid var(--border-color);padding-top:15px;margin-top:2px; }
.detail-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px; }
.detail-cell { padding:11px;border:1px solid var(--border-color);border-radius:9px;background:var(--bg-primary); }
.detail-cell.full { grid-column:1/-1; }
.detail-cell small { display:block;color:var(--text-muted);font-size:10px;text-transform:uppercase;font-weight:800;margin-bottom:4px; }
.detail-cell strong { color:var(--text-dark);font-size:13px;word-break:break-word; }
@media (max-width:900px) { .logistics-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:600px) { .logistics-page { padding:14px; }.logistics-hero { padding:21px; }.logistics-stats,.form-grid,.detail-grid { grid-template-columns:1fr; }.form-group.full,.form-section-title,.detail-cell.full { grid-column:auto; }.logistics-filter>* { width:100%; }.logistics-filter input { min-width:0; } }
</style>

<div class="logistics-page">
    <section class="logistics-hero">
        <div class="logistics-hero-inner">
            <div>
                <div class="logistics-kicker"><i class="fa-solid fa-boxes-packing"></i> Operations Tracker</div>
                <h1>Logistics & Inventory Tracker</h1>
                <p>Track items received from suppliers, project deliveries to clients, demo items, and internal inventory. Project delivery reminders are sent seven days before the delivery date.</p>
            </div>
            <?php if ($canCreate): ?>
            <button class="logistics-add-btn" type="button" onclick="openRecordForm()"><i class="fa-solid fa-plus"></i> Add Record</button>
            <?php endif; ?>
        </div>
    </section>

    <section class="logistics-stats" style="grid-template-columns: repeat(5, 1fr);">
        <div class="logistics-stat"><div class="logistics-stat-icon" style="background:#DBEAFE;color:#1D4ED8;"><i class="fa-solid fa-truck-fast"></i></div><div><strong><?= $stats['active_deliveries'] ?></strong><span>Active Deliveries</span></div></div>
        <div class="logistics-stat"><div class="logistics-stat-icon" style="background:#EDE9FE;color:#6D28D9;"><i class="fa-solid fa-box-open"></i></div><div><strong><?= $stats['demo_items'] ?></strong><span>Demo Items</span></div></div>
        <div class="logistics-stat"><div class="logistics-stat-icon" style="background:#D1FAE5;color:#059669;"><i class="fa-solid fa-boxes-stacked"></i></div><div><strong><?= $stats['inventory_items'] ?? 0 ?></strong><span>Inventory</span></div></div>
        <div class="logistics-stat"><div class="logistics-stat-icon" style="background:#FEF3C7;color:#B45309;"><i class="fa-solid fa-clock"></i></div><div><strong><?= $stats['due_soon'] ?></strong><span>Due in 7 Days</span></div></div>
        <div class="logistics-stat"><div class="logistics-stat-icon" style="background:#FEE2E2;color:#DC2626;"><i class="fa-solid fa-triangle-exclamation"></i></div><div><strong><?= $stats['overdue'] ?></strong><span>Overdue</span></div></div>
    </section>

    <form class="logistics-filter" method="get" action="index.php">
        <input type="hidden" name="page" value="logistics">
        <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search reference, item, supplier, client or PIC contact...">
        <select name="type">
            <option value="">All Types</option>
            <option value="project_delivery" <?= $typeFilter === 'project_delivery' ? 'selected' : '' ?>>Project Delivery</option>
            <option value="demo_item" <?= $typeFilter === 'demo_item' ? 'selected' : '' ?>>Demo Item</option>
            <option value="inventory" <?= $typeFilter === 'inventory' ? 'selected' : '' ?>>Inventory</option>
        </select>
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="background:#0F766E;color:#fff;"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="index.php?page=logistics" style="background:var(--bg-primary);color:var(--text-muted);">Reset</a>
    </form>

    <section class="logistics-table-card">
        <div style="padding:16px 18px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div><strong style="color:var(--text-dark);font-size:15px;">All Records</strong><div style="color:var(--text-muted);font-size:11px;margin-top:3px;"><?= count($records) ?> matching record(s)</div></div>
            <div style="font-size:11px;color:var(--text-muted);"><i class="fa-solid fa-bell" style="color:#F59E0B;"></i> Project delivery reminder: 7 days before delivery date</div>
        </div>
        <div class="logistics-table-wrap">
            <table class="logistics-table">
                <thead><tr><th>Reference</th><th>Type / Item</th><th>Supplier / Client</th><th>Quantity</th><th>PIC</th><th>Key Date</th><th>Status</th><th style="text-align:center;">Actions</th></tr></thead>
                <tbody>
                <?php if (!$records): ?>
                    <tr><td colspan="8" style="text-align:center;padding:42px;color:var(--text-muted);"><i class="fa-solid fa-box-open" style="display:block;font-size:28px;margin-bottom:10px;opacity:.45;"></i>No logistics or inventory records found.</td></tr>
                <?php else: foreach ($records as $record):
                    $isProjectDelivery = $record['record_type'] === 'project_delivery';
                    $style = $statusStyles[$record['status']] ?? ['#F1F5F9', '#64748B'];
                    $days = $record['days_until_due'] === null ? null : (int)$record['days_until_due'];
                ?>
                    <tr class="<?= $record['is_overdue'] ? 'overdue-row' : '' ?>">
                        <td><strong style="color:#0F766E;white-space:nowrap;"><?= htmlspecialchars($record['reference_no']) ?></strong><div style="font-size:10px;color:var(--text-muted);margin-top:3px;"><?= date('d M Y', strtotime($record['created_at'])) ?></div></td>
                        <td><span class="logistics-type" style="background:<?= $isProjectDelivery ? '#DBEAFE;color:#1D4ED8' : ($record['record_type'] === 'inventory' ? '#D1FAE5;color:#059669' : '#EDE9FE;color:#6D28D9') ?>;"><i class="fa-solid <?= $isProjectDelivery ? 'fa-truck-fast' : ($record['record_type'] === 'inventory' ? 'fa-boxes-stacked' : 'fa-box-open') ?>"></i><?= $isProjectDelivery ? 'Project Delivery' : ($record['record_type'] === 'inventory' ? 'Inventory' : 'Demo Item') ?></span><div style="font-weight:700;margin-top:6px;max-width:230px;"><?= htmlspecialchars($record['item_name']) ?></div></td>
                        <td><strong><?= htmlspecialchars($record['supplier_name'] ?: 'Supplier not set') ?></strong><div style="font-size:10px;color:var(--text-muted);margin-top:3px;"><?= $isProjectDelivery ? 'Client: ' . htmlspecialchars($record['client_name'] ?: '-') : 'Supplier' ?></div></td>
                        <td><?= number_format((float)$record['quantity'], ((float)$record['quantity'] == (int)$record['quantity']) ? 0 : 2) ?> <?= htmlspecialchars($record['unit']) ?></td>
                        <td><i class="fa-regular fa-user" style="color:#0F766E;margin-right:4px;"></i><?= htmlspecialchars($record['responsible_name']) ?></td>
                        <td style="white-space:nowrap;">
                            <strong><?= $isProjectDelivery ? ($record['delivery_date'] ? date('d M Y', strtotime($record['delivery_date'])) : '-') : ($record['received_date'] ? date('d M Y', strtotime($record['received_date'])) : '-') ?></strong>
                            <span class="due-badge" style="color:var(--text-muted);"><?= $isProjectDelivery ? 'Delivery date' : 'Date received' ?></span>
                            <?php if ($isProjectDelivery && $record['is_overdue']): ?><span class="due-badge" style="color:#DC2626;"><?= abs((int)$days) ?> day(s) overdue</span>
                            <?php elseif ($isProjectDelivery && !in_array($record['status'], ['delivered','cancelled'], true) && $days !== null && $days <= 7): ?><span class="due-badge" style="color:#B45309;">Due in <?= $days ?> day(s)</span><?php endif; ?>
                        </td>
                        <td><span class="logistics-status" style="background:<?= $style[0] ?>;color:<?= $style[1] ?>;"><?= $statusLabels[$record['status']] ?? ucfirst($record['status']) ?></span></td>
                        <td style="text-align:center;white-space:nowrap;">
                            <button class="action-btn" type="button" onclick="viewRecord(<?= (int)$record['id'] ?>)" title="View"><i class="fa-solid fa-eye"></i></button>
                            <?php if ($canEdit): ?>
                            <button class="action-btn" type="button" onclick="editRecord(<?= (int)$record['id'] ?>)" title="Edit" style="color:#B45309;"><i class="fa-solid fa-pen"></i></button>
                            <?php if ($isProjectDelivery && !in_array($record['status'], ['delivered','cancelled'], true)): ?>
                            <button class="action-btn" type="button" onclick="completeRecord(<?= (int)$record['id'] ?>)" title="Mark Delivered" style="color:#15803D;"><i class="fa-solid fa-check"></i></button>
                            <?php endif; endif; ?>
                            <?php if ($canDelete): ?><button class="action-btn" type="button" onclick="deleteRecord(<?= (int)$record['id'] ?>)" title="Delete" style="color:#DC2626;"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php if ($canCreate || $canEdit): ?>
<div class="modal-overlay" id="recordFormModal">
    <div class="modal-box" style="max-width:800px;">
        <div class="modal-header"><h3 class="modal-title" id="recordFormTitle"><i class="fa-solid fa-boxes-packing" style="color:#0F766E;"></i> Add Record</h3><button class="modal-close" type="button" onclick="App.closeModal('recordFormModal')">&times;</button></div>
        <form id="recordForm" onsubmit="saveRecord(event)">
            <input type="hidden" name="id" id="recordId">
            <div class="modal-body" style="padding:20px;max-height:70vh;overflow:auto;">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Record Type <span style="color:#EF4444;">*</span></label>
                        <div class="record-type-picker">
                            <label class="record-type-option"><input type="radio" name="record_type" value="project_delivery" checked onchange="updateTypeFields()"><i class="fa-solid fa-truck-fast"></i> Project Delivery</label>
                            <label class="record-type-option"><input type="radio" name="record_type" value="demo_item" onchange="updateTypeFields()"><i class="fa-solid fa-box-open"></i> Demo Item</label>
                            <label class="record-type-option"><input type="radio" name="record_type" value="inventory" onchange="updateTypeFields()"><i class="fa-solid fa-boxes-stacked"></i> Inventory</label>
                        </div>
                    </div>
                    <div class="form-group full"><label>Item / Equipment Name <span style="color:#EF4444;">*</span></label><input type="text" name="item_name" required maxlength="255" placeholder="e.g. RFID Reader Package"></div>
                    <div class="form-group"><label>Supplier <span style="color:#EF4444;">*</span></label><input type="text" name="supplier_name" required maxlength="255" placeholder="Supplier / company name"></div>
                    <div class="form-group"><label>Date Item Received <span style="color:#EF4444;">*</span></label><input type="date" name="received_date" required></div>
                    <div class="form-group"><label>Quantity <span style="color:#EF4444;">*</span></label><input type="number" name="quantity" min="0.01" step="0.01" value="1" required></div>
                    <div class="form-group"><label>Unit <span style="color:#EF4444;">*</span></label><input type="text" name="unit" value="unit" required maxlength="50" placeholder="unit, box, set..."></div>
                    <div class="form-group full"><label>PIC <span style="color:#EF4444;">*</span></label><select name="responsible_user_id" required><option value="">Select PIC...</option><?php foreach ($users as $user): if (($user['status'] ?? 'active') !== 'active') continue; ?><option value="<?= (int)$user['id'] ?>" <?= (int)$user['id'] === (int)Auth::id() ? 'selected' : '' ?>><?= htmlspecialchars($user['name']) ?> — <?= htmlspecialchars(Auth::roleLabel($user['role'])) ?></option><?php endforeach; ?></select></div>

                    <div class="form-section-title project-only">Project Delivery Details</div>
                    <div class="form-group project-only"><label>Client Name <span style="color:#EF4444;">*</span></label><input type="text" name="client_name" data-project-required maxlength="255" placeholder="Client / company name"></div>
                    <div class="form-group project-only"><label>Delivery Date <span style="color:#EF4444;">*</span></label><input type="date" name="delivery_date" data-project-required></div>
                    <div class="form-group project-only"><label>Type of Shipping <span style="color:#EF4444;">*</span></label><select name="shipping_type" data-project-required><option value="">Select shipping type...</option><option value="air_freight">Air Freight</option><option value="ground_freight">Ground Freight</option><option value="sea_freight">Sea Freight</option></select></div>
                    <div class="form-group project-only"><label>Status <span style="color:#EF4444;">*</span></label><select name="status" id="recordStatus" data-project-required></select></div>
                    <div class="form-group project-only"><label>Client Contact Person <span style="color:#EF4444;">*</span></label><input type="text" name="contact_person" data-project-required maxlength="150" placeholder="Person our team should contact"></div>
                    <div class="form-group project-only"><label>Contact Phone</label><input type="tel" name="contact_phone" maxlength="50" placeholder="Optional phone number"></div>

                    <div class="form-group full"><label>Description / Specification</label><textarea name="description" rows="2" placeholder="Serial number, specification, accessories..."></textarea></div>
                    <div class="form-group full"><label>Notes</label><textarea name="notes" rows="2" placeholder="Receiving condition, delivery instructions or other notes..."></textarea></div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:10px;padding:15px 20px;"><button type="button" class="btn btn-secondary" onclick="App.closeModal('recordFormModal')">Cancel</button><button type="submit" class="btn btn-primary" id="saveRecordBtn" style="background:#0F766E;border-color:#0F766E;"><i class="fa-solid fa-floppy-disk"></i> Save Record</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="recordDetailModal">
    <div class="modal-box" style="max-width:680px;">
        <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-clipboard-list" style="color:#0F766E;"></i> Record Details</h3><button class="modal-close" type="button" onclick="App.closeModal('recordDetailModal')">&times;</button></div>
        <div class="modal-body" id="recordDetailContent" style="padding:20px;"></div>
        <div class="modal-footer" style="text-align:right;padding:15px 20px;"><button class="btn btn-secondary" onclick="App.closeModal('recordDetailModal')">Close</button></div>
    </div>
</div>

<script>
const logisticsStatusLabels = <?= json_encode($statusLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const logisticsShippingLabels = <?= json_encode($shippingLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function selectedRecordType() {
    return document.querySelector('input[name="record_type"]:checked')?.value || 'project_delivery';
}

function updateTypeFields(statusToSelect = null) {
    const isProjectDelivery = selectedRecordType() === 'project_delivery';
    document.querySelectorAll('.project-only').forEach(element => {
        element.style.display = isProjectDelivery ? '' : 'none';
    });
    document.querySelectorAll('[data-project-required]').forEach(element => {
        element.required = isProjectDelivery;
    });
    const statuses = isProjectDelivery ? ['scheduled', 'in_transit', 'delivered', 'cancelled'] : ['received'];
    const select = document.getElementById('recordStatus');
    if (select) {
        select.innerHTML = statuses.map(status => `<option value="${status}">${logisticsStatusLabels[status]}</option>`).join('');
        if (statusToSelect && statuses.includes(statusToSelect)) select.value = statusToSelect;
    }
}

function openRecordForm() {
    const form = document.getElementById('recordForm');
    form.reset();
    document.getElementById('recordId').value = '';
    document.getElementById('recordFormTitle').innerHTML = '<i class="fa-solid fa-boxes-packing" style="color:#0F766E;"></i> Add Record';
    const me = form.querySelector('[name="responsible_user_id"] option[selected]');
    if (me) form.elements.responsible_user_id.value = me.value;
    form.elements.quantity.value = '1';
    form.elements.unit.value = 'unit';
    form.elements.received_date.value = new Date().toISOString().slice(0, 10);
    updateTypeFields();
    App.openModal('recordFormModal');
}

async function fetchRecord(id) {
    const response = await fetch(`index.php?action=get_logistics_record&id=${encodeURIComponent(id)}`, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const result = await response.json();
    if (result.status !== 'success') throw new Error(result.message || 'Unable to load record.');
    return result.data;
}

async function editRecord(id) {
    try {
        const record = await fetchRecord(id);
        const form = document.getElementById('recordForm');
        form.reset();
        form.elements.id.value = record.id;
        const typeOption = form.querySelector(`[name="record_type"][value="${record.record_type}"]`);
        if (typeOption) typeOption.checked = true;
        updateTypeFields(record.status);
        ['item_name','description','quantity','unit','supplier_name','client_name','contact_person','contact_phone','received_date','delivery_date','shipping_type','responsible_user_id','notes'].forEach(name => {
            if (form.elements[name]) form.elements[name].value = record[name] ?? '';
        });
        if (form.elements.status) form.elements.status.value = record.status;
        document.getElementById('recordFormTitle').innerHTML = `<i class="fa-solid fa-pen" style="color:#B45309;"></i> Edit ${escapeHtml(record.reference_no)}`;
        App.openModal('recordFormModal');
    } catch (error) {
        App.showToast('error', error.message);
    }
}

async function saveRecord(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const isEdit = Boolean(form.elements.id.value);
    const result = await App.post(`index.php?action=${isEdit ? 'update_logistics_record' : 'add_logistics_record'}`, new FormData(form), document.getElementById('saveRecordBtn'));
    if (result?.status === 'success') {
        App.closeModal('recordFormModal');
        setTimeout(() => location.reload(), 650);
    }
}

async function viewRecord(id) {
    App.openModal('recordDetailModal');
    const container = document.getElementById('recordDetailContent');
    container.innerHTML = '<div style="text-align:center;padding:25px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';
    try {
        const record = await fetchRecord(id);
        const isProjectDelivery = record.record_type === 'project_delivery';
        const overdue = Number(record.is_overdue) === 1;
        container.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px;">
                <div><div style="color:#0F766E;font-size:12px;font-weight:800;">${escapeHtml(record.reference_no)}</div><h3 style="color:var(--text-dark);margin:4px 0;">${escapeHtml(record.item_name)}</h3></div>
                <span class="logistics-type" style="background:${isProjectDelivery ? '#DBEAFE;color:#1D4ED8' : '#EDE9FE;color:#6D28D9'};">${isProjectDelivery ? 'Project Delivery' : 'Demo Item'}</span>
            </div>
            ${overdue ? '<div style="background:#FEF2F2;color:#B91C1C;border:1px solid #FECACA;border-radius:9px;padding:9px 11px;margin-bottom:14px;font-size:12px;font-weight:700;"><i class="fa-solid fa-triangle-exclamation"></i> This project delivery is overdue.</div>' : ''}
            <div class="detail-grid">
                <div class="detail-cell"><small>Supplier</small><strong>${escapeHtml(record.supplier_name || '-')}</strong></div>
                <div class="detail-cell"><small>Date Item Received</small><strong>${formatDate(record.received_date)}</strong></div>
                <div class="detail-cell"><small>Quantity</small><strong>${formatQuantity(record.quantity)} ${escapeHtml(record.unit)}</strong></div>
                <div class="detail-cell"><small>PIC</small><strong>${escapeHtml(record.responsible_name)}</strong></div>
                <div class="detail-cell"><small>Status</small><strong>${escapeHtml(logisticsStatusLabels[record.status] || record.status)}</strong></div>
                ${isProjectDelivery ? `
                    <div class="detail-cell"><small>Client Name</small><strong>${escapeHtml(record.client_name || '-')}</strong></div>
                    <div class="detail-cell"><small>Delivery Date</small><strong>${formatDate(record.delivery_date)}</strong></div>
                    <div class="detail-cell"><small>Type of Shipping</small><strong>${escapeHtml(logisticsShippingLabels[record.shipping_type] || '-')}</strong></div>
                    <div class="detail-cell"><small>Client Contact Person</small><strong>${escapeHtml(record.contact_person || '-')}</strong></div>
                    <div class="detail-cell"><small>Contact Phone</small><strong>${escapeHtml(record.contact_phone || '-')}</strong></div>
                ` : ''}
                <div class="detail-cell full"><small>Description / Specification</small><strong>${escapeHtml(record.description || '-')}</strong></div>
                <div class="detail-cell full"><small>Notes</small><strong>${escapeHtml(record.notes || '-')}</strong></div>
                <div class="detail-cell full"><small>Created By</small><strong>${escapeHtml(record.creator_name)} · ${formatDate(record.created_at, true)}</strong></div>
            </div>`;
    } catch (error) {
        container.innerHTML = `<p style="color:#DC2626;">${escapeHtml(error.message)}</p>`;
    }
}

async function completeRecord(id) {
    if (!await App.confirm('Mark as Delivered', 'Confirm this project item has been delivered to the client?')) return;
    const data = new FormData();
    data.append('id', id);
    data.append('status', 'delivered');
    const result = await App.post('index.php?action=update_logistics_status', data);
    if (result?.status === 'success') setTimeout(() => location.reload(), 650);
}

async function deleteRecord(id) {
    if (!await App.confirm('Delete Record', 'Permanently delete this logistics record?')) return;
    const data = new FormData();
    data.append('id', id);
    const result = await App.post('index.php?action=delete_logistics_record', data);
    if (result?.status === 'success') setTimeout(() => location.reload(), 650);
}

function escapeHtml(value) { return String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char])); }
function formatDate(value, withTime = false) { if (!value) return '-'; const date = new Date(String(value).replace(' ', 'T')); return new Intl.DateTimeFormat('en-GB', {day:'2-digit',month:'short',year:'numeric', ...(withTime ? {hour:'2-digit',minute:'2-digit'} : {})}).format(date); }
function formatQuantity(value) { const number = Number(value); return Number.isInteger(number) ? String(number) : number.toFixed(2); }

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('recordStatus')) updateTypeFields();
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
