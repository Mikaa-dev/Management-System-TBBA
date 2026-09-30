<?php
/**
 * Finance Sales Operations View — TBBA ERP Module
 * Company: The Bridge Business Alliance (TBBA)
 */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';

// Status badges helper
function getStatusBadgeHtml($status) {
    $statusMap = [
        'draft'      => ['bg' => '#F1F5F9', 'color' => '#64748B', 'label' => 'Draft'],
        'sent'       => ['bg' => '#E0F2FE', 'color' => '#0369A1', 'label' => 'Sent / Issued'],
        'confirmed'  => ['bg' => '#DCFCE7', 'color' => '#15803D', 'label' => 'Confirmed'],
        'delivered'  => ['bg' => '#D1FAE5', 'color' => '#047857', 'label' => 'Delivered'],
        'in_transit' => ['bg' => '#FEF3C7', 'color' => '#B45309', 'label' => 'In Transit'],
        'paid'       => ['bg' => '#DCFCE7', 'color' => '#166534', 'label' => 'Paid'],
        'completed'  => ['bg' => '#DCFCE7', 'color' => '#15803D', 'label' => 'Completed'],
        'processed'  => ['bg' => '#E0E7FF', 'color' => '#4338CA', 'label' => 'Processed'],
        'issued'     => ['bg' => '#FCE7F3', 'color' => '#BE185D', 'label' => 'Issued'],
        'overdue'    => ['bg' => '#FEE2E2', 'color' => '#B91C1C', 'label' => 'Overdue'],
        'cancelled'  => ['bg' => '#F3F4F6', 'color' => '#4B5563', 'label' => 'Cancelled']
    ];
    $badge = $statusMap[strtolower($status)] ?? ['bg' => '#F1F5F9', 'color' => '#64748B', 'label' => ucfirst($status)];
    return sprintf(
        '<span style="background:%s; color:%s; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; display:inline-block;">%s</span>',
        $badge['bg'],
        $badge['color'],
        htmlspecialchars($badge['label'])
    );
}
?>

<div class="page-content" style="padding: 24px;">
    <!-- Top Navigation Category Tabs (Quotations, Sale Orders, Delivery Orders, Invoices, Credit Notes, Payments, Refunds) -->
    <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 12px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color);">
        <a href="index.php?page=sales&tab=all" class="btn <?= $activeTab === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; white-space: nowrap; <?= $activeTab === 'all' ? 'background:#3B82F6;color:#fff;border:none;' : 'background:transparent;border:1px solid var(--border-color);' ?>">
            <i class="fa-solid fa-list" style="margin-right:6px;"></i> All Sales Records
        </a>
        <?php foreach ($docTypes as $key => $info): ?>
        <a href="index.php?page=sales&tab=<?= $key ?>" class="btn <?= $activeTab === $key ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; white-space: nowrap; <?= $activeTab === $key ? 'background:'.$info['color'].';color:#fff;border:none;' : 'background:transparent;border:1px solid var(--border-color);' ?>">
            <i class="fa-solid <?= $info['icon'] ?>" style="margin-right:6px; <?= $activeTab === $key ? 'color:#fff;' : 'color:'.$info['color'].';' ?>"></i> <?= htmlspecialchars($info['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Summary Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 20px; border-radius: 12px; border-left: 5px solid #3B82F6; background: var(--bg-card); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Sales Volume</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--text-dark); margin-top: 8px;">RM <?= number_format($stats['total_sum'] ?? 0, 2) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;"><?= $stats['total_count'] ?? 0 ?> total documents</div>
        </div>
        <div class="card" style="padding: 20px; border-radius: 12px; border-left: 5px solid #F59E0B; background: var(--bg-card); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Pending / Sent / Draft</div>
            <div style="font-size: 24px; font-weight: 800; color: #D97706; margin-top: 8px;">RM <?= number_format($stats['pending_sum'] ?? 0, 2) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Awaiting confirmation</div>
        </div>
        <div class="card" style="padding: 20px; border-radius: 12px; border-left: 5px solid #10B981; background: var(--bg-card); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Confirmed / Delivered</div>
            <div style="font-size: 24px; font-weight: 800; color: #10B981; margin-top: 8px;">RM <?= number_format($stats['confirmed_sum'] ?? 0, 2) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Active orders & invoices</div>
        </div>
        <div class="card" style="padding: 20px; border-radius: 12px; border-left: 5px solid #8B5CF6; background: var(--bg-card); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Paid / Settled Volume</div>
            <div style="font-size: 24px; font-weight: 800; color: #8B5CF6; margin-top: 8px;">RM <?= number_format($stats['paid_sum'] ?? 0, 2) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Completed revenue</div>
        </div>
    </div>

    <!-- Action Bar & Filter -->
    <div class="card" style="padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; background: var(--bg-card);">
        <div>
            <h3 style="margin: 0; font-size: 18px; color: var(--text-dark); display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid <?= $currentTypeInfo['icon'] ?? 'fa-chart-line' ?>" style="color: <?= $currentTypeInfo['color'] ?? '#3B82F6' ?>;"></i>
                <?= htmlspecialchars($currentTypeInfo['label'] ?? 'All Sales Records') ?> Management
            </h3>
            <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">Manage and track customer transactions, quotations, invoices, and payments.</p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <?php if ($canEdit): ?>
            <button class="btn btn-primary" onclick="openNewRecordModal()" style="padding: 10px 18px; font-weight: 600; border-radius: 8px; background: <?= $currentTypeInfo['color'] ?? '#3B82F6' ?>; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-plus"></i> Add New <?= htmlspecialchars($currentTypeInfo['label'] === 'All Sales Records' ? 'Sales Record' : rtrim($currentTypeInfo['label'], 's')) ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="card" style="padding: 20px; border-radius: 12px; background: var(--bg-card); overflow-x: auto;">
        <table class="table datatable" id="salesTable" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); text-align: left; color: var(--text-muted); font-weight: 700;">
                    <th style="padding: 12px;">Ref / Doc No</th>
                    <?php if ($activeTab === 'all'): ?><th style="padding: 12px;">Category</th><?php endif; ?>
                    <th style="padding: 12px;">Customer / Party</th>
                    <th style="padding: 12px;">Title / Summary</th>
                    <th style="padding: 12px;">Doc Date</th>
                    <th style="padding: 12px;">Due Date</th>
                    <th style="padding: 12px; text-align: right;">Total (RM)</th>
                    <th style="padding: 12px; text-align: center;">Status</th>
                    <th style="padding: 12px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                <tr>
                    <td colspan="<?= $activeTab === 'all' ? 9 : 8 ?>" style="text-align: center; padding: 36px; color: var(--text-muted);">
                        <i class="fa-solid fa-folder-open" style="font-size: 32px; margin-bottom: 12px; opacity: 0.5; display: block;"></i>
                        No sales records found in this category. Click "Add New" above to create one.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($records as $r): 
                    $docInfo = $docTypes[$r['document_type']] ?? ['label' => ucfirst(str_replace('_', ' ', $r['document_type'])), 'color' => '#64748B'];
                ?>
                <tr class="sales-row" style="border-bottom: 1px solid var(--border-color); transition: background 0.15s;" onmouseover="this.style.background='rgba(59, 130, 246, 0.04)'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 14px 12px; font-weight: 700; color: var(--text-dark);">
                        <?= htmlspecialchars($r['reference_no']) ?>
                    </td>
                    <?php if ($activeTab === 'all'): ?>
                    <td style="padding: 14px 12px;">
                        <span style="color: <?= $docInfo['color'] ?>; font-weight: 600; font-size: 12px;"><?= htmlspecialchars($docInfo['label']) ?></span>
                    </td>
                    <?php endif; ?>
                    <td style="padding: 14px 12px; font-weight: 600; color: var(--text-dark);">
                        <?= htmlspecialchars($r['party_name']) ?>
                    </td>
                    <td style="padding: 14px 12px; color: var(--text-dark);">
                        <?= htmlspecialchars($r['title']) ?>
                        <?php if (!empty($r['notes'])): ?>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars(substr($r['notes'], 0, 60)) ?><?= strlen($r['notes']) > 60 ? '...' : '' ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 14px 12px; color: var(--text-muted);">
                        <?= htmlspecialchars($r['document_date']) ?>
                    </td>
                    <td style="padding: 14px 12px; color: var(--text-muted);">
                        <?= !empty($r['due_date']) ? htmlspecialchars($r['due_date']) : '<span style="opacity:0.4;">—</span>' ?>
                    </td>
                    <td style="padding: 14px 12px; text-align: right; font-weight: 700; color: var(--text-dark);">
                        <?= number_format($r['total_amount'], 2) ?>
                        <?php if ($r['tax_amount'] > 0): ?>
                        <div style="font-size: 10px; color: var(--text-muted); font-weight: 400;">Tax: <?= number_format($r['tax_amount'], 2) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 14px 12px; text-align: center;">
                        <?= getStatusBadgeHtml($r['status']) ?>
                    </td>
                    <td style="padding: 14px 12px; text-align: center;">
                        <?php if (!empty($r['attachment'])): ?>
                        <a href="index.php?action=download_finance_attachment&id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: #3B82F6; cursor: pointer; margin-right: 4px; text-decoration: none; display: inline-block;" title="View Supporting Document">
                            <i class="fa-solid fa-paperclip"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($canEdit): ?>
                        <button class="btn btn-primary btn-sm" onclick='editFinanceRecord(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="padding: 5px 10px; border-radius: 6px; border: none; background: #3B82F6; color: #fff; cursor: pointer; margin-right: 4px;" title="Edit Record">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteFinanceRecord(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['reference_no'])) ?>')" style="padding: 5px 10px; border-radius: 6px; border: none; background: #EF4444; color: #fff; cursor: pointer;" title="Delete Record">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <?php else: ?>
                        <span style="font-size: 11px; color: var(--text-muted);">View Only</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add / Edit Sales Record -->
<div class="modal-overlay" id="financeRecordModal">
    <div class="modal-box" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" id="financeModalTitle"><i class="fa-solid fa-file-signature" style="color: #3B82F6; margin-right:8px;"></i> Add New Sales Record</h3>
            <button class="modal-close" onclick="App.closeModal('financeRecordModal')">&times;</button>
        </div>
        <form id="financeRecordForm" onsubmit="submitFinanceRecord(event)" enctype="multipart/form-data">
            <input type="hidden" name="id" id="financeRecordId" value="0">
            <input type="hidden" name="category" value="sales">
            <div class="modal-body" style="padding: 20px; max-height: 70vh; overflow-y: auto;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Document Type <span style="color: #EF4444;">*</span></label>
                        <select name="document_type" id="financeDocType" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark); font-weight: 600;">
                            <?php foreach ($docTypes as $key => $info): ?>
                            <option value="<?= $key ?>" <?= ($activeTab === $key) ? 'selected' : '' ?>><?= htmlspecialchars($info['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Reference Number <span style="color: #EF4444;">*</span></label>
                        <input type="text" name="reference_no" id="financeRefNo" required placeholder="e.g. QT-2026-003 or INV-2026-012" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark); font-weight: 600;">
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Customer / Party Name <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="party_name" id="financePartyName" required placeholder="e.g. Maybank Berhad or Petronas Dagangan Bhd" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text-dark);">Project Reference</label><input type="text" name="project_ref" id="financeProjectRef" placeholder="e.g. PRJ-2026-001" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-primary);color:var(--text-dark);"></div>
                    <div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text-dark);">Cost Centre</label><select name="cost_center_id" id="financeCostCenter" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-primary);color:var(--text-dark);"><option value="">Not assigned</option><?php foreach($costCenters as $cc): ?><option value="<?= (int)$cc['id'] ?>"><?= htmlspecialchars($cc['code'].' — '.$cc['name']) ?></option><?php endforeach; ?></select></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Document Date <span style="color: #EF4444;">*</span></label>
                        <input type="date" name="document_date" id="financeDocDate" required value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Due Date (Optional)</label>
                        <input type="date" name="due_date" id="financeDueDate" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Title / Summary <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="title" id="financeTitle" required placeholder="e.g. Cloud Infrastructure Migration Phase 1 Billing" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Base Amount (RM) <span style="color: #EF4444;">*</span></label>
                        <input type="number" step="0.01" name="amount" id="financeAmount" required placeholder="0.00" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark); font-weight: 700;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Tax Amount (RM)</label>
                        <input type="number" step="0.01" name="tax_amount" id="financeTaxAmount" value="0.00" placeholder="0.00" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Status <span style="color: #EF4444;">*</span></label>
                        <select name="status" id="financeStatus" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark); font-weight: 600;">
                            <option value="draft">Draft</option>
                            <option value="sent">Sent / Issued</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="delivered">Delivered</option>
                            <option value="in_transit">In Transit</option>
                            <option value="paid">Paid</option>
                            <option value="completed">Completed</option>
                            <option value="processed">Processed</option>
                            <option value="issued">Issued</option>
                            <option value="overdue">Overdue</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Internal Notes</label>
                        <input type="text" name="notes" id="financeNotes" placeholder="Optional remarks or payment terms..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Supporting Document</label>
                    <input type="file" name="attachment" id="financeAttachment" class="form-control" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    <div id="financeCurrentAttachment" style="font-size: 12px; margin-top: 6px; color: var(--text-muted);"></div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('financeRecordModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitFinance" style="padding: 8px 18px; border-radius: 6px; background: #3B82F6; color: #fff; border: none; font-weight: 600; cursor: pointer;">Save Sales Record</button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewRecordModal() {
    document.getElementById('financeRecordId').value = '0';
    document.getElementById('financeRecordForm').reset();
    document.getElementById('financeDocDate').value = new Date().toISOString().split('T')[0];
    const activeTab = '<?= $activeTab ?>';
    if (activeTab && activeTab !== 'all') {
        const docTypeSelect = document.getElementById('financeDocType');
        if (docTypeSelect) docTypeSelect.value = activeTab;
    }
    const attInfo = document.getElementById('financeCurrentAttachment');
    if (attInfo) attInfo.innerHTML = '';
    document.getElementById('financeModalTitle').innerHTML = '<i class="fa-solid fa-file-signature" style="color: #3B82F6; margin-right: 8px;"></i>Add New Sales Record';
    document.getElementById('btnSubmitFinance').textContent = 'Save Sales Record';
    App.openModal('financeRecordModal');
}

function editFinanceRecord(r) {
    document.getElementById('financeRecordId').value = r.id;
    document.getElementById('financeDocType').value = r.document_type || 'quotations';
    document.getElementById('financeRefNo').value = r.reference_no || '';
    document.getElementById('financePartyName').value = r.party_name || '';
    document.getElementById('financeProjectRef').value = r.project_ref || '';
    document.getElementById('financeCostCenter').value = r.cost_center_id || '';
    document.getElementById('financeDocDate').value = r.document_date || '';
    document.getElementById('financeDueDate').value = r.due_date || '';
    document.getElementById('financeTitle').value = r.title || '';
    document.getElementById('financeAmount').value = r.amount || 0;
    document.getElementById('financeTaxAmount').value = r.tax_amount || 0;
    document.getElementById('financeStatus').value = r.status || 'draft';
    document.getElementById('financeNotes').value = r.notes || '';

    const attInfo = document.getElementById('financeCurrentAttachment');
    if (attInfo) {
        if (r.attachment) {
            attInfo.innerHTML = `<a href="index.php?action=download_finance_attachment&id=${encodeURIComponent(r.id)}" target="_blank" rel="noopener" style="color: #3B82F6; font-weight: 600; text-decoration: underline;"><i class="fa-solid fa-paperclip"></i> View Current Supporting Document</a> <span style="color: var(--text-muted);">(Upload a new file above to replace it)</span>`;
        } else {
            attInfo.innerHTML = 'No supporting document attached yet.';
        }
    }

    const modalTitle = document.getElementById('financeModalTitle');
    modalTitle.innerHTML = '<i class="fa-solid fa-pen-to-square" style="color: #3B82F6; margin-right: 8px;"></i>';
    modalTitle.append(document.createTextNode('Edit Sales Record: ' + String(r.reference_no ?? '')));
    document.getElementById('btnSubmitFinance').textContent = 'Update Sales Record';
    App.openModal('financeRecordModal');
}

async function submitFinanceRecord(e) {
    e.preventDefault();
    const form = document.getElementById('financeRecordForm');
    const formData = new FormData(form);
    const btn = document.getElementById('btnSubmitFinance');
    const res = await App.post('index.php?action=save_finance_record', formData, btn);
    if (res && res.status === 'success') {
        App.closeModal('financeRecordModal');
        setTimeout(() => location.reload(), 500);
    }
}

async function deleteFinanceRecord(id, refNo) {
    const confirmed = await App.confirm(
        'Delete Record',
        `Are you sure you want to delete sales record "${refNo}"? This action cannot be undone.`,
        'Yes, Delete'
    );
    if (!confirmed) return;
    const formData = new FormData();
    formData.append('id', id);
    const res = await App.post('index.php?action=delete_finance_record', formData);
    if (res && res.status === 'success') {
        setTimeout(() => location.reload(), 500);
    }
}

</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
