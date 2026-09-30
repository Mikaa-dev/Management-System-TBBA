<?php
/**
 * Purchase Requests View — TBBA ERP Module 4
 */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<style>
    .purchase-actions {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        white-space: nowrap;
    }

    .purchase-action-btn {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        flex: 0 0 34px !important;
        width: 34px !important;
        min-width: 34px !important;
        height: 34px !important;
        min-height: 34px !important;
        padding: 0 !important;
        border-radius: 8px !important;
        box-shadow: none !important;
        line-height: 1 !important;
    }

    .purchase-action-btn i {
        display: block;
        width: 1em;
        line-height: 1;
        text-align: center;
    }

    @media (max-width: 768px) {
        .purchase-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .purchase-actions .purchase-action-btn {
            flex: 0 0 40px !important;
            width: 40px !important;
            min-width: 40px !important;
            height: 40px !important;
        }
    }
</style>

<div class="page-content" style="padding: 24px;">
    <?php
    $purchasePendingCount = count(array_filter($requests ?? [], fn($request) => ($request['status'] ?? '') === 'pending'));
    $purchaseActiveCount = count(array_filter($requests ?? [], fn($request) => in_array(($request['status'] ?? ''), ['approved', 'ordered'], true)));
    $purchaseTotalValue = array_sum(array_map(fn($request) => (float)($request['total_amount'] ?? 0), $requests ?? []));
    ?>
    <div class="module-stat-grid">
        <div class="card module-stat-card"><div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Total requests</div><div style="font-size:26px;font-weight:800;color:var(--text-dark);margin-top:8px;"><?= count($requests ?? []) ?></div><div style="font-size:11px;color:var(--text-muted);margin-top:4px;">All procurement submissions</div></div>
        <div class="card module-stat-card"><div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Pending approval</div><div style="font-size:26px;font-weight:800;color:#D97706;margin-top:8px;"><?= $purchasePendingCount ?></div><div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Requires review</div></div>
        <div class="card module-stat-card"><div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Approved / ordered</div><div style="font-size:26px;font-weight:800;color:#059669;margin-top:8px;"><?= $purchaseActiveCount ?></div><div style="font-size:11px;color:var(--text-muted);margin-top:4px;">In procurement flow</div></div>
        <div class="card module-stat-card"><div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Total request value</div><div style="font-size:22px;font-weight:800;color:var(--text-dark);margin-top:8px;">MYR <?= number_format($purchaseTotalValue, 2) ?></div><div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Current visible records</div></div>
    </div>

    <!-- Action Bar -->
    <div class="card module-toolbar" style="padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; background: var(--bg-card);">
        <div>
            <h3 style="margin: 0; font-size: 18px; color: var(--text-dark);"><i class="fa-solid fa-cart-plus" style="color: #0891B2;"></i> Purchase Requests (PR)</h3>
            <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">Create procurement requests with itemized unit pricing and vendor quotes.</p>
        </div>
        <div>
            <?php if (Auth::hasPermission('purchase', 'create')): ?>
            <button class="btn btn-primary" onclick="App.openModal('newPrModal')" style="padding: 10px 18px; font-weight: 600; border-radius: 8px; background: #0891B2; color: #fff; border: none; cursor: pointer;">
                <i class="fa-solid fa-plus"></i> Create Purchase Request
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- PR Table -->
    <div class="card module-data-card" style="padding: 20px; border-radius: 12px; background: var(--bg-card); overflow-x: auto;">
        <table class="datatable table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                    <th style="padding: 12px;">PR No</th>
                    <?php if ($canApprove): ?><th style="padding: 12px;">Requester</th><?php endif; ?>
                    <th style="padding: 12px;">Title / Description</th>
                    <th style="padding: 12px;">Vendor</th>
                    <th style="padding: 12px;">Required Date</th>
                    <th style="padding: 12px; text-align: right;">Total Amount (MYR)</th>
                    <th style="padding: 12px;">Status</th>
                    <th style="padding: 12px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="<?= $canApprove ? 8 : 7 ?>" style="text-align: center; padding: 24px; color: var(--text-muted);">No purchase requests found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($requests as $r): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px; font-weight: 700; color: #0891B2;"><?= htmlspecialchars($r['pr_no'] ?? '#') ?></td>
                    <?php if ($canApprove): ?>
                    <td style="padding: 12px;">
                        <div style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($r['user_name'] ?? 'Unknown') ?></div>
                        <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($r['department_name'] ?? 'General') ?></div>
                    </td>
                    <?php endif; ?>
                    <td style="padding: 12px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($r['title']) ?></td>
                    <td style="padding: 12px; color: var(--text-muted);"><?= htmlspecialchars($r['vendor'] ?: '-') ?></td>
                    <td style="padding: 12px;"><?= $r['required_date'] ? date('d M Y', strtotime($r['required_date'])) : '-' ?></td>
                    <td style="padding: 12px; text-align: right; font-weight: 700; color: #10B981;"><?= number_format($r['total_amount'], 2) ?></td>
                    <td style="padding: 12px;">
                        <?php
                        $statusColors = [
                            'pending' => ['#FEF3C7', '#D97706', 'Pending'],
                            'approved' => ['#D1FAE5', '#059669', 'Approved'],
                            'rejected' => ['#FEE2E2', '#DC2626', 'Rejected'],
                            'ordered' => ['#E0E7FF', '#4338CA', 'Ordered'],
                            'received' => ['#DCFCE7', '#15803D', 'Received'],
                            'draft' => ['#F1F5F9', '#64748B', 'Draft']
                        ];
                        $sc = $statusColors[$r['status']] ?? ['#F1F5F9', '#64748B', ucfirst($r['status'])];
                        ?>
                        <span style="background: <?= $sc[0] ?>; color: <?= $sc[1] ?>; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;">
                            <?= $sc[2] ?>
                        </span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <div class="purchase-actions" role="group" aria-label="Actions for <?= htmlspecialchars($r['pr_no'] ?? 'purchase request') ?>">
                        <button type="button" class="btn btn-secondary btn-sm purchase-action-btn" onclick="viewPrDetail(<?= $r['id'] ?>)" style="border: 1px solid var(--border-color); background: transparent; cursor: pointer; color: var(--text-dark);" title="View request details" aria-label="View request details">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <?php if ($canApprove && $r['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-success btn-sm purchase-action-btn" onclick="approvePr(<?= $r['id'] ?>, 'approved')" style="border: none; background: #10B981; color: #fff; cursor: pointer;" title="Approve request" aria-label="Approve request">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-danger btn-sm purchase-action-btn" onclick="approvePr(<?= $r['id'] ?>, 'rejected')" style="border: none; background: #EF4444; color: #fff; cursor: pointer;" title="Reject request" aria-label="Reject request">
                            <i class="fa-solid fa-times"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($canApprove && $r['status'] === 'approved'): ?>
                        <button type="button" class="btn btn-primary btn-sm purchase-action-btn" onclick="updatePrStatus(<?= $r['id'] ?>, 'ordered')" style="border: none; background: #4338CA; color: #fff; cursor: pointer;" title="Mark as ordered" aria-label="Mark as ordered">
                            <i class="fa-solid fa-truck"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($canApprove && $r['status'] === 'ordered'): ?>
                        <button type="button" class="btn btn-success btn-sm purchase-action-btn" onclick="updatePrStatus(<?= $r['id'] ?>, 'received')" style="border: none; background: #15803D; color: #fff; cursor: pointer;" title="Mark as received" aria-label="Mark as received">
                            <i class="fa-solid fa-box-open"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (($r['user_id'] == Auth::id() && $r['status'] === 'pending') || Auth::hasPermission('purchase','delete')): ?>
                        <button type="button" class="btn btn-danger btn-sm purchase-action-btn" onclick="deletePr(<?= $r['id'] ?>)" style="border: none; background: #DC2626; color: #fff; cursor: pointer;" title="Delete request" aria-label="Delete request">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New PR -->
<div class="modal-overlay module-clean-modal" id="newPrModal">
    <div class="modal-box" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-cart-plus" style="color: #0891B2;"></i> Create Purchase Request</h3>
            <button class="modal-close" onclick="App.closeModal('newPrModal')">&times;</button>
        </div>
        <form id="newPrForm" onsubmit="submitPurchaseRequest(event)">
            <div class="modal-body" style="padding: 20px;">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">PR Title / Summary <span style="color: #EF4444;">*</span></label>
                        <input type="text" name="title" required placeholder="e.g. Server Hardware Upgrade - Q3" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Required Date</label>
                        <input type="date" name="required_date" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Suggested Vendor / Supplier</label>
                        <input type="text" name="vendor" placeholder="e.g. Dell Malaysia Sdn Bhd" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Department</label>
                        <select name="department_id" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($currentUser['department_id'] == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-size: 13px; font-weight: 600; color: var(--text-dark);">Itemized List <span style="color: #EF4444;">*</span></label>
                        <button type="button" onclick="addPrRow()" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer; color: var(--text-dark);">
                            <i class="fa-solid fa-plus"></i> Add Item
                        </button>
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;" id="prItemsTable">
                        <thead>
                            <tr style="background: var(--bg-primary); text-align: left; border-bottom: 1px solid var(--border-color);">
                                <th style="padding: 8px;">Description / Specification</th>
                                <th style="padding: 8px; width: 80px;">Qty</th>
                                <th style="padding: 8px; width: 80px;">Unit</th>
                                <th style="padding: 8px; width: 110px;">Unit Price</th>
                                <th style="padding: 8px; width: 110px; text-align: right;">Total Price</th>
                                <th style="padding: 8px; width: 40px; text-align: center;">-</th>
                            </tr>
                        </thead>
                        <tbody id="prRowsBody">
                            <tr>
                                <td style="padding: 6px;"><input type="text" class="item-desc" required placeholder="Item description" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-dark);"></td>
                                <td style="padding: 6px;"><input type="number" step="1" class="item-qty" value="1" required oninput="calculatePrTotal()" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-dark);"></td>
                                <td style="padding: 6px;"><input type="text" class="item-unit" value="unit" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-dark);"></td>
                                <td style="padding: 6px;"><input type="number" step="0.01" class="item-price" required placeholder="0.00" oninput="calculatePrTotal()" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-dark); text-align: right;"></td>
                                <td style="padding: 6px; text-align: right; font-weight: 700; color: #10B981;" class="row-total">0.00</td>
                                <td style="padding: 6px; text-align: center;"><button type="button" onclick="removePrRow(this)" style="background: none; border: none; color: #EF4444; cursor: pointer;"><i class="fa-solid fa-trash"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="padding: 8px; text-align: right; font-weight: 700; color: var(--text-dark);">Total Estimated Cost:</td>
                                <td style="padding: 8px; text-align: right; font-weight: 800; color: #10B981; font-size: 14px;" id="displayPrTotalAmt">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Business Justification</label>
                    <textarea name="justification" rows="2" placeholder="Explain why this purchase is necessary for company operations..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark); resize: vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('newPrModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitPr" style="padding: 8px 16px; border-radius: 6px; background: #0891B2; color: #fff; border: none; font-weight: 600; cursor: pointer;">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: View PR Detail -->
<div class="modal-overlay module-clean-modal" id="viewPrModal">
    <div class="modal-box" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-cart-shopping" style="color: #0891B2;"></i> Purchase Request Details</h3>
            <button class="modal-close" onclick="App.closeModal('viewPrModal')">&times;</button>
        </div>
        <div class="modal-body" id="prDetailContent" style="padding: 20px; font-size: 13px; line-height: 1.6;">
            <p>Loading...</p>
        </div>
        <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); text-align: right;">
            <button class="btn btn-secondary" onclick="App.closeModal('viewPrModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Close</button>
        </div>
    </div>
</div>

<script>
function addPrRow() {
    const tbody = document.getElementById('prRowsBody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td style="padding: 6px;"><input type="text" class="item-desc" required placeholder="Item description" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-dark);"></td>
        <td style="padding: 6px;"><input type="number" step="1" class="item-qty" value="1" required oninput="calculatePrTotal()" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-dark);"></td>
        <td style="padding: 6px;"><input type="text" class="item-unit" value="unit" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-dark);"></td>
        <td style="padding: 6px;"><input type="number" step="0.01" class="item-price" required placeholder="0.00" oninput="calculatePrTotal()" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-dark); text-align: right;"></td>
        <td style="padding: 6px; text-align: right; font-weight: 700; color: #10B981;" class="row-total">0.00</td>
        <td style="padding: 6px; text-align: center;"><button type="button" onclick="removePrRow(this)" style="background: none; border: none; color: #EF4444; cursor: pointer;"><i class="fa-solid fa-trash"></i></button></td>
    `;
    tbody.appendChild(row);
}

function removePrRow(btn) {
    const tbody = document.getElementById('prRowsBody');
    if (tbody.rows.length <= 1) {
        alert('You must have at least one item.');
        return;
    }
    btn.closest('tr').remove();
    calculatePrTotal();
}

function calculatePrTotal() {
    let total = 0;
    document.querySelectorAll('#prRowsBody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value || 0);
        const price = parseFloat(row.querySelector('.item-price').value || 0);
        const rowAmt = qty * price;
        row.querySelector('.row-total').textContent = rowAmt.toFixed(2);
        total += rowAmt;
    });
    document.getElementById('displayPrTotalAmt').textContent = total.toFixed(2);
}

async function submitPurchaseRequest(e) {
    e.preventDefault();
    const items = [];
    document.querySelectorAll('#prRowsBody tr').forEach(row => {
        items.push({
            description: row.querySelector('.item-desc').value.trim(),
            quantity: parseFloat(row.querySelector('.item-qty').value || 1),
            unit: row.querySelector('.item-unit').value.trim() || 'unit',
            unit_price: parseFloat(row.querySelector('.item-price').value || 0)
        });
    });

    const form = document.getElementById('newPrForm');
    const formData = new FormData(form);
    formData.append('items', JSON.stringify(items));

    const btn = document.getElementById('btnSubmitPr');
    const res = await App.post('index.php?action=add_purchase', formData, btn);
    if (res && res.status === 'success') {
        App.closeModal('newPrModal');
        form.reset();
        setTimeout(() => location.reload(), 1000);
    }
}

async function viewPrDetail(id) {
    App.openModal('viewPrModal');
    const content = document.getElementById('prDetailContent');
    content.innerHTML = '<p style="text-align:center;"><i class="fa-solid fa-spinner fa-spin"></i> Loading PR details...</p>';
    try {
        const res = await fetch(`index.php?action=get_purchase&id=${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            const { pr, items } = data.data;
            let itemsHtml = '';
            items.forEach((item, idx) => {
                itemsHtml += `
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 8px;">${idx + 1}</td>
                        <td style="padding: 8px; font-weight: 600;">${htmlspecialchars(item.description)}</td>
                        <td style="padding: 8px;">${parseFloat(item.quantity)} ${htmlspecialchars(item.unit)}</td>
                        <td style="padding: 8px; text-align: right;">${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td style="padding: 8px; text-align: right; font-weight: 700; color:#10B981;">${parseFloat(item.total_price).toFixed(2)}</td>
                    </tr>
                `;
            });

            content.innerHTML = `
                <div style="margin-bottom: 16px; padding: 12px; background: var(--bg-primary); border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="font-size: 15px; color: var(--text-dark); display: block;">${htmlspecialchars(pr.title)}</strong>
                        <span style="font-size: 12px; color: var(--text-muted);">PR No: ${htmlspecialchars(pr.pr_no)} | Vendor: ${htmlspecialchars(pr.vendor || 'N/A')}</span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 18px; font-weight: 800; color: #10B981; display: block;">MYR ${parseFloat(pr.total_amount).toFixed(2)}</span>
                        <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">${pr.status}</span>
                    </div>
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background: var(--bg-primary); text-align: left; border-bottom: 2px solid var(--border-color);">
                            <th style="padding: 8px; width: 30px;">#</th>
                            <th style="padding: 8px;">Description</th>
                            <th style="padding: 8px; width: 80px;">Qty</th>
                            <th style="padding: 8px; width: 90px; text-align: right;">Unit Price</th>
                            <th style="padding: 8px; width: 100px; text-align: right;">Total (MYR)</th>
                        </tr>
                    </thead>
                    <tbody>${itemsHtml}</tbody>
                </table>
                ${pr.justification ? `<div style="margin-top: 16px; padding: 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px;"><strong>Justification:</strong> ${htmlspecialchars(pr.justification)}</div>` : ''}
            `;
        } else {
            content.innerHTML = '<p style="color:#EF4444;">Failed to load PR details.</p>';
        }
    } catch (e) {
        content.innerHTML = '<p style="color:#EF4444;">Error connecting to server.</p>';
    }
}

function htmlspecialchars(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function approvePr(id, decision) {
    let reason = '';
    if (decision === 'rejected') {
        reason = prompt('Reason for rejecting this PR:');
        if (reason === null) return;
    } else {
        if (!await App.confirm('Approve PR', 'Confirm approval of this purchase request?')) return;
    }
    const formData = new FormData();
    formData.append('id', id); formData.append('decision', decision);
    if (reason) formData.append('reason', reason);
    const res = await App.post('index.php?action=update_purchase_status', formData);
    if (res && res.status === 'success') setTimeout(() => location.reload(), 1000);
}

async function updatePrStatus(id, status) {
    if (!await App.confirm('Update Status', `Confirm status change to ${status.toUpperCase()}?`)) return;
    const formData = new FormData(); formData.append('id', id); formData.append('status', status);
    const res = await App.post('index.php?action=set_purchase_status', formData);
    if (res && res.status === 'success') setTimeout(() => location.reload(), 1000);
}

async function deletePr(id) {
    if (!await App.confirm('Delete PR', 'Are you sure you want to permanently delete this PR?')) return;
    const formData = new FormData(); formData.append('id', id);
    const res = await App.post('index.php?action=delete_purchase', formData);
    if (res && res.status === 'success') setTimeout(() => location.reload(), 1000);
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
