<?php
/**
 * Expense Claims View — TBBA ERP Module 3
 * Workflow v3: Staff -> Finance Review -> Approval Center -> Management Approval -> Paid
 */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';

$canDelete = Auth::hasPermission('expense', 'delete');
?>

<style>
.expense-tabs{display:inline-flex;gap:6px;padding:4px;border:1px solid var(--border-color);border-radius:9px;background:var(--bg-primary)}
.expense-tab{padding:7px 13px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:700;color:var(--text-muted)}
.expense-tab.active{background:#2563EB;color:#fff;box-shadow:0 3px 8px rgba(37,99,235,.18)}
.expense-status{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap}
.expense-flow{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:14px 0}
.expense-flow-step{padding:10px;border:1px solid var(--border-color);border-radius:9px;background:var(--bg-card);font-size:11px;color:var(--text-muted)}
.expense-flow-step.done{border-color:#A7F3D0;background:#ECFDF5;color:#047857}.expense-flow-step.active{border-color:#BFDBFE;background:#EFF6FF;color:#1D4ED8}.expense-flow-step.danger{border-color:#FECACA;background:#FEF2F2;color:#B91C1C}
.expense-category-summary{width:100%;border-collapse:collapse;font-size:12px}.expense-category-summary td{padding:8px 10px;border-bottom:1px solid var(--border-color)}
.expense-item-receipt{font-size:11px;max-width:190px}.expense-form-table{width:100%;border-collapse:collapse;font-size:12px}.expense-form-table th{padding:8px;text-align:left;background:var(--bg-primary);border-bottom:1px solid var(--border-color)}.expense-form-table td{padding:6px;vertical-align:middle}
@media(max-width:760px){.expense-flow{grid-template-columns:1fr 1fr}.expense-form-scroll{overflow-x:auto}.expense-form-table{min-width:760px}}
</style>

<div class="page-content" style="padding:24px;">
    <div class="module-stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:20px;">
        <div class="card module-stat-card" style="padding:20px;border-radius:12px;border-left:5px solid #10B981;background:var(--bg-card);">
            <div style="font-size:11px;font-weight:800;color:var(--text-muted);text-transform:uppercase;">My Approved / Paid Claims</div>
            <div style="font-size:25px;font-weight:800;color:var(--text-dark);margin-top:8px;">MYR <?= number_format($totalClaimed ?? 0, 2) ?></div>
        </div>
        <div class="card module-stat-card" style="padding:20px;border-radius:12px;border-left:5px solid #D97706;background:var(--bg-card);">
            <div style="font-size:11px;font-weight:800;color:var(--text-muted);text-transform:uppercase;">My Pending Claims</div>
            <div style="font-size:25px;font-weight:800;color:var(--text-dark);margin-top:8px;">MYR <?= number_format($totalPending ?? 0, 2) ?></div>
        </div>
    </div>

    <div class="card module-toolbar" style="padding:18px 20px;border-radius:12px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;background:var(--bg-card);">
        <div>
            <h3 style="margin:0;font-size:17px;color:var(--text-dark);"><i class="fa-solid fa-receipt" style="color:#BE185D;"></i> Expense Claims Management</h3>
            <p style="margin:4px 0 0;font-size:12px;color:var(--text-muted);">Staff submits → Finance checks → Management approves → Finance marks paid.</p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <?php if ($canViewAll): ?>
            <div class="expense-tabs">
                <a class="expense-tab <?= !$showAllStaff ? 'active' : '' ?>" href="index.php?page=expense&view=my"><i class="fa-solid fa-user"></i> My Claims</a>
                <a class="expense-tab <?= $showAllStaff ? 'active' : '' ?>" href="index.php?page=expense&view=all"><i class="fa-solid fa-users"></i> All Staff Claims</a>
            </div>
            <?php endif; ?>

            <?php if (Auth::hasPermission('expense', 'create')): ?>
            <button class="btn btn-primary" onclick="openNewExpenseModal()" style="padding:10px 16px;font-weight:700;border-radius:8px;background:#BE185D;color:#fff;border:none;cursor:pointer;">
                <i class="fa-solid fa-plus"></i> Submit New Claim
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card module-data-card" style="padding:20px;border-radius:12px;background:var(--bg-card);overflow-x:auto;">
        <table class="datatable table" style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid var(--border-color);text-align:left;">
                    <th style="padding:12px;">Claim No</th>
                    <?php if ($showAllStaff): ?><th style="padding:12px;">Staff Name</th><?php endif; ?>
                    <th style="padding:12px;">Title / Summary</th>
                    <th style="padding:12px;">Expense Date</th>
                    <th style="padding:12px;text-align:right;">Total (MYR)</th>
                    <th style="padding:12px;">Workflow Status</th>
                    <th style="padding:12px;text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($claims)): ?>
                <tr><td colspan="<?= $showAllStaff ? 7 : 6 ?>" style="padding:30px;text-align:center;color:var(--text-muted);">No expense claims found.</td></tr>
            <?php else: ?>
                <?php foreach ($claims as $c): ?>
                <?php
                    $stage = (string)($c['workflow_stage'] ?? 'draft');
                    $statusMap = [
                        'finance_review' => ['#FEF3C7','#B45309','fa-magnifying-glass-dollar'],
                        'management_approval' => ['#DBEAFE','#1D4ED8','fa-user-tie'],
                        'approved' => ['#D1FAE5','#047857','fa-circle-check'],
                        'rejected' => ['#FEE2E2','#B91C1C','fa-circle-xmark'],
                        'paid' => ['#E0E7FF','#4338CA','fa-money-bill-wave'],
                        'draft' => ['#F1F5F9','#64748B','fa-pen'],
                    ];
                    $sm = $statusMap[$stage] ?? $statusMap['draft'];
                    $isOwner = (int)$c['user_id'] === (int)Auth::id();
                    $alreadyForwarded = strtolower((string)($c['approval_status'] ?? '')) === 'pending';
                ?>
                <tr style="border-bottom:1px solid var(--border-color);">
                    <td style="padding:12px;font-weight:800;color:#2563EB;"><?= htmlspecialchars($c['claim_no'] ?? '#') ?></td>
                    <?php if ($showAllStaff): ?>
                    <td style="padding:12px;">
                        <div style="font-weight:700;color:var(--text-dark);"><?= htmlspecialchars($c['user_name'] ?? 'Unknown') ?></div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($c['department_name'] ?? 'General') ?></div>
                    </td>
                    <?php endif; ?>
                    <td style="padding:12px;">
                        <div style="font-weight:700;color:var(--text-dark);"><?= htmlspecialchars($c['title'] ?? '') ?></div>
                        <?php if (empty($c['applicant_signed']) && $c['status'] === 'pending'): ?>
                            <div style="font-size:10px;color:#D97706;margin-top:4px;"><i class="fa-solid fa-pen-nib"></i> Applicant signature pending</div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px;"><?= !empty($c['expense_date']) ? date('d M Y', strtotime($c['expense_date'])) : '-' ?></td>
                    <td style="padding:12px;text-align:right;font-weight:800;color:#10B981;"><?= number_format((float)$c['total_amount'],2) ?></td>
                    <td style="padding:12px;">
                        <span class="expense-status" style="background:<?= $sm[0] ?>;color:<?= $sm[1] ?>;"><i class="fa-solid <?= $sm[2] ?>"></i> <?= htmlspecialchars($c['workflow_label'] ?? ucfirst($c['status'])) ?></span>
                    </td>
                    <td style="padding:12px;text-align:center;white-space:nowrap;">
                        <button class="btn btn-secondary btn-sm" onclick="viewExpenseDetail(<?= (int)$c['id'] ?>)" style="padding:5px 8px;border-radius:5px;border:1px solid var(--border-color);background:transparent;cursor:pointer;color:var(--text-dark);" title="View details">
                            <i class="fa-solid fa-eye"></i>
                        </button>

                        <?php if ($canFinanceCheck && $showAllStaff && $stage === 'finance_review'): ?>
                            <?php if (!empty($c['applicant_signed'])): ?>
                            <button class="btn btn-primary btn-sm" onclick="financeVerifyExpense(<?= (int)$c['id'] ?>)" style="padding:5px 9px;border-radius:5px;border:none;background:#0F766E;color:#fff;cursor:pointer;font-weight:700;" title="Finance checked — send to Approval Center">
                                <i class="fa-solid fa-check-double"></i> Checked → Approval
                            </button>
                            <?php else: ?>
                            <button type="button" disabled style="padding:5px 9px;border-radius:5px;border:none;background:#E2E8F0;color:#94A3B8;font-weight:700;cursor:not-allowed;" title="Applicant signature is required first">
                                <i class="fa-solid fa-clock"></i> Awaiting Sign
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($canMarkPaid && $c['status'] === 'approved'): ?>
                        <button class="btn btn-primary btn-sm" onclick="markPaidExpense(<?= (int)$c['id'] ?>)" style="padding:5px 9px;border-radius:5px;border:none;background:#2563EB;color:#fff;cursor:pointer;" title="Mark as Paid">
                            <i class="fa-solid fa-money-bill-transfer"></i>
                        </button>
                        <?php endif; ?>

                        <?php if ($canDelete && $c['status'] === 'pending' && !$alreadyForwarded && ($isOwner || $canViewAll)): ?>
                        <button class="btn btn-danger btn-sm" onclick="deleteExpense(<?= (int)$c['id'] ?>)" style="padding:5px 8px;border-radius:5px;border:none;background:#DC2626;color:#fff;cursor:pointer;" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Submit Claim Modal -->
<div class="modal-overlay module-clean-modal" id="newExpenseModal">
    <div class="modal-box" style="max-width:900px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-receipt" style="color:#BE185D;"></i> Submit Expense Claim</h3>
            <button class="modal-close" onclick="App.closeModal('newExpenseModal')">&times;</button>
        </div>
        <form id="newExpenseForm" onsubmit="submitExpenseClaim(event)">
            <div class="modal-body" style="padding:20px;">
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:var(--text-dark);">Claim Title / Summary <span style="color:#EF4444;">*</span></label>
                        <input type="text" name="title" required placeholder="e.g. September travel & client expenses" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-primary);color:var(--text-dark);">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:var(--text-dark);">Expense Date <span style="color:#EF4444;">*</span></label>
                        <input type="date" name="expense_date" required value="<?= date('Y-m-d') ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-primary);color:var(--text-dark);">
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin:4px 0 8px;">
                    <div>
                        <div style="font-size:13px;font-weight:800;color:var(--text-dark);">Expense Items <span style="color:#EF4444;">*</span></div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Attach a receipt to each line item when available. One claim can therefore contain multiple receipts.</div>
                    </div>
                    <button type="button" onclick="addExpenseRow()" class="btn btn-secondary btn-sm" style="padding:6px 10px;font-size:11px;border-radius:6px;border:1px solid var(--border-color);background:transparent;cursor:pointer;color:var(--text-dark);">
                        <i class="fa-solid fa-plus"></i> Add Item
                    </button>
                </div>

                <div class="expense-form-scroll">
                    <table class="expense-form-table" id="expenseItemsTable">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style="width:210px;">Category</th>
                                <th style="width:115px;">Amount (MYR)</th>
                                <th style="width:205px;">Receipt / Proof</th>
                                <th style="width:38px;text-align:center;">-</th>
                            </tr>
                        </thead>
                        <tbody id="expenseRowsBody"></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="padding:10px;text-align:right;font-weight:800;color:var(--text-dark);">Grand Total:</td>
                                <td style="padding:10px;text-align:right;font-weight:900;color:#10B981;font-size:15px;" id="displayTotalAmt">0.00</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="margin-top:16px;">
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:var(--text-dark);">General Notes / Remarks</label>
                    <textarea name="notes" rows="2" placeholder="Additional details or justification..." style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-primary);color:var(--text-dark);resize:vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding:16px 20px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('newExpenseModal')" style="padding:8px 16px;border-radius:6px;border:1px solid var(--border-color);background:transparent;cursor:pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitExpense" style="padding:8px 16px;border-radius:6px;background:#BE185D;color:#fff;border:none;font-weight:700;cursor:pointer;"><i class="fa-solid fa-paper-plane"></i> Submit Claim</button>
            </div>
        </form>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay module-clean-modal" id="viewExpenseModal">
    <div class="modal-box" style="max-width:720px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-list-check" style="color:#BE185D;"></i> Expense Claim Details</h3>
            <button class="modal-close" onclick="App.closeModal('viewExpenseModal')">&times;</button>
        </div>
        <div class="modal-body" id="expenseDetailContent" style="padding:20px;font-size:13px;line-height:1.6;"><p>Loading...</p></div>
        <div class="modal-footer" style="padding:16px 20px;border-top:1px solid var(--border-color);text-align:right;">
            <button class="btn btn-secondary" onclick="App.closeModal('viewExpenseModal')" style="padding:8px 16px;border-radius:6px;border:1px solid var(--border-color);background:transparent;cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<script>
const EXPENSE_CATEGORIES = <?= json_encode($expenseCategories ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function expenseCategoryOptions(selected = '') {
    return EXPENSE_CATEGORIES.map(category => {
        const safe = htmlspecialchars(category);
        return `<option value="${safe}" ${category === selected ? 'selected' : ''}>${safe}</option>`;
    }).join('');
}

function openNewExpenseModal() {
    const form = document.getElementById('newExpenseForm');
    form.reset();
    document.getElementById('expenseRowsBody').innerHTML = '';
    addExpenseRow();
    calculateExpenseTotal();
    App.openModal('newExpenseModal');
}

function addExpenseRow() {
    const tbody = document.getElementById('expenseRowsBody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" class="item-desc" required placeholder="Item description" style="width:100%;padding:8px;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-dark);"></td>
        <td><select class="item-cat" required style="width:100%;padding:8px;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-dark);">${expenseCategoryOptions('Misc Expenses')}</select></td>
        <td><input type="number" min="0.01" step="0.01" class="item-amt" required placeholder="0.00" oninput="calculateExpenseTotal()" style="width:100%;padding:8px;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-dark);text-align:right;"></td>
        <td><input type="file" class="item-receipt expense-item-receipt" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip"></td>
        <td style="text-align:center;"><button type="button" onclick="removeExpenseRow(this)" style="background:none;border:none;color:#EF4444;cursor:pointer;"><i class="fa-solid fa-trash"></i></button></td>
    `;
    tbody.appendChild(row);
}

function removeExpenseRow(btn) {
    const tbody = document.getElementById('expenseRowsBody');
    if (tbody.rows.length <= 1) {
        App.showToast('warning', 'At least one expense item is required.');
        return;
    }
    btn.closest('tr').remove();
    calculateExpenseTotal();
}

function calculateExpenseTotal() {
    let total = 0;
    document.querySelectorAll('#expenseRowsBody .item-amt').forEach(el => total += parseFloat(el.value || 0));
    document.getElementById('displayTotalAmt').textContent = total.toFixed(2);
}

async function submitExpenseClaim(e) {
    e.preventDefault();
    const form = document.getElementById('newExpenseForm');
    const formData = new FormData(form);
    const items = [];

    document.querySelectorAll('#expenseRowsBody tr').forEach((row, index) => {
        items.push({
            description: row.querySelector('.item-desc').value.trim(),
            category: row.querySelector('.item-cat').value,
            amount: parseFloat(row.querySelector('.item-amt').value || 0)
        });

        const receipt = row.querySelector('.item-receipt');
        if (receipt?.files?.[0]) formData.append(`item_receipt_${index}`, receipt.files[0]);
    });

    formData.append('items', JSON.stringify(items));

    const btn = document.getElementById('btnSubmitExpense');
    const res = await App.post('index.php?action=add_expense', formData, btn);
    if (res && res.status === 'success') {
        App.closeModal('newExpenseModal');
        form.reset();
        document.getElementById('expenseRowsBody').innerHTML = '';
        addExpenseRow();
        calculateExpenseTotal();

        // Applicant signs immediately after submission. Finance cannot forward an unsigned claim.
        setTimeout(() => ESign.openModal('expense_claim', res.data.id, 'applicant'), 350);
    }
}

async function financeVerifyExpense(id) {
    const ok = await App.confirm(
        'Finance Check Complete',
        'Confirm that the amount, categories and supporting receipts have been checked? This claim will be sent to Approval Center for management approval.'
    );
    if (!ok) return;

    const formData = new FormData();
    formData.append('id', id);
    const res = await App.post('index.php?action=finance_verify_expense', formData);
    if (res && res.status === 'success') setTimeout(() => location.reload(), 650);
}

async function markPaidExpense(id) {
    const ok = await App.confirm('Mark as Paid', 'Confirm that this approved expense claim has been paid out?');
    if (!ok) return;

    const formData = new FormData();
    formData.append('id', id);
    const res = await App.post('index.php?action=mark_paid_expense', formData);
    if (res && res.status === 'success') setTimeout(() => location.reload(), 650);
}

async function deleteExpense(id) {
    const ok = await App.confirm('Delete Expense Claim', 'Are you sure you want to permanently delete this claim?');
    if (!ok) return;

    const formData = new FormData();
    formData.append('id', id);
    const res = await App.post('index.php?action=delete_expense', formData);
    if (res && res.status === 'success') setTimeout(() => location.reload(), 650);
}

function buildExpenseWorkflow(claim, workflow) {
    const stage = workflow?.stage || claim.workflow_stage || '';
    const applicantDone = Boolean(workflow?.applicant_signed);
    const financeDone = Boolean(workflow?.finance_verified);
    const managementDone = ['approved','rejected','paid'].includes(claim.status);
    const paymentDone = claim.status === 'paid';

    const cls = (done, active = false, danger = false) => danger ? 'danger' : (done ? 'done' : (active ? 'active' : ''));
    return `
        <div class="expense-flow">
            <div class="expense-flow-step ${cls(applicantDone, !applicantDone)}"><i class="fa-solid ${applicantDone ? 'fa-circle-check' : 'fa-pen-nib'}"></i><br><strong>Applicant</strong><br>${applicantDone ? 'Signed' : 'Signature Pending'}</div>
            <div class="expense-flow-step ${cls(financeDone, stage === 'finance_review')}"><i class="fa-solid ${financeDone ? 'fa-circle-check' : 'fa-magnifying-glass-dollar'}"></i><br><strong>Finance</strong><br>${financeDone ? 'Checked & Forwarded' : 'Pending Review'}</div>
            <div class="expense-flow-step ${cls(managementDone && claim.status !== 'rejected', stage === 'management_approval', claim.status === 'rejected')}"><i class="fa-solid ${claim.status === 'rejected' ? 'fa-circle-xmark' : (managementDone ? 'fa-circle-check' : 'fa-user-tie')}"></i><br><strong>Management</strong><br>${claim.status === 'rejected' ? 'Rejected' : (managementDone ? 'Approved' : 'Pending Approval')}</div>
            <div class="expense-flow-step ${cls(paymentDone, claim.status === 'approved')}"><i class="fa-solid ${paymentDone ? 'fa-circle-check' : 'fa-money-bill-wave'}"></i><br><strong>Payment</strong><br>${paymentDone ? 'Paid' : (claim.status === 'approved' ? 'Awaiting Payment' : 'Pending')}</div>
        </div>`;
}

function buildSignatureStatusHtml(sigData, type, id) {
    let html = '<div class="esign-status-card"><h4><i class="fa-solid fa-pen-nib"></i> E-Signatures</h4>';
    const signatures = Array.isArray(sigData?.signatures) ? sigData.signatures : [];
    const getSig = role => signatures.find(s => s.signer_role === role);
    const applicantSig = getSig('applicant');
    const approverSig = getSig('approver');

    if (applicantSig) {
        html += `<div class="esign-preview-box"><div class="esign-preview-info"><span class="esign-preview-name">${htmlspecialchars(applicantSig.user_name)} (Applicant)</span><span class="esign-preview-date">Signed: ${htmlspecialchars(applicantSig.signed_at_formatted)}</span></div><img src="${htmlspecialchars(applicantSig.signature_full_url)}" class="esign-preview-img" alt="Applicant signature"></div>`;
    } else {
        html += `<div class="esign-preview-box pending"><i class="fa-solid fa-clock"></i> Applicant Signature Pending</div>`;
    }

    if (approverSig) {
        html += `<div class="esign-preview-box"><div class="esign-preview-info"><span class="esign-preview-name">${htmlspecialchars(approverSig.user_name)} (Approver)</span><span class="esign-preview-date">Signed: ${htmlspecialchars(approverSig.signed_at_formatted)}</span></div><img src="${htmlspecialchars(approverSig.signature_full_url)}" class="esign-preview-img" alt="Approver signature"></div>`;
    } else {
        html += `<div class="esign-preview-box pending"><i class="fa-solid fa-clock"></i> Approver Signature Pending</div>`;
    }

    html += '<div class="esign-pdf-actions">';
    if (applicantSig && approverSig) {
        html += `<button class="btn btn-primary btn-sm" onclick="ESign.generatePdf('${type}', ${id}, this)" style="flex:1;"><i class="fa-solid fa-file-pdf"></i> Generate/Download PDF</button>`;
        html += `<button class="btn btn-secondary btn-sm" onclick="ESign.verify('${type}', ${id})"><i class="fa-solid fa-shield-halved"></i> Verify</button>`;
    } else if (!applicantSig && Number(<?= (int)Auth::id() ?>) === Number(window.currentExpenseOwnerId || 0)) {
        html += `<button class="btn btn-primary btn-sm" onclick="ESign.openModal('${type}', ${id}, 'applicant')" style="flex:1;"><i class="fa-solid fa-pen-nib"></i> Sign Now (Applicant)</button>`;
    }
    html += '</div></div>';
    return html;
}

async function viewExpenseDetail(id) {
    App.openModal('viewExpenseModal');
    const content = document.getElementById('expenseDetailContent');
    content.innerHTML = '<p style="text-align:center;padding:20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading claim...</p>';

    try {
        const [claimRes, sigRes] = await Promise.all([
            fetch(`index.php?action=get_expense&id=${encodeURIComponent(id)}`, {headers:{'X-Requested-With':'XMLHttpRequest'}}),
            fetch(`index.php?action=get_signature_status&document_type=expense_claim&document_id=${encodeURIComponent(id)}`, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        ]);

        const data = await claimRes.json();
        const sigData = await sigRes.json().catch(() => null);
        if (data.status !== 'success' || !data.data) throw new Error(data.message || 'Failed to load claim.');

        const { claim, items, category_totals, workflow } = data.data;
        window.currentExpenseOwnerId = Number(claim.user_id || 0);

        const categoryHtml = (category_totals || []).map(row => `
            <tr><td>${htmlspecialchars(row.category || 'Misc Expenses')}</td><td style="text-align:right;font-weight:800;">MYR ${Number(row.total || 0).toFixed(2)}</td></tr>
        `).join('');

        const itemsHtml = (items || []).map((item, idx) => `
            <tr style="border-bottom:1px solid var(--border-color);">
                <td style="padding:9px;">${idx + 1}</td>
                <td style="padding:9px;font-weight:700;">${htmlspecialchars(item.description)}</td>
                <td style="padding:9px;"><span style="background:var(--bg-primary);padding:3px 7px;border-radius:5px;font-size:10px;">${htmlspecialchars(item.category)}</span></td>
                <td style="padding:9px;text-align:right;font-weight:800;color:#10B981;">${Number(item.amount || 0).toFixed(2)}</td>
                <td style="padding:9px;text-align:center;">${item.receipt_path ? `<a href="index.php?action=download_expense_item_receipt&item_id=${encodeURIComponent(item.id)}" target="_blank" rel="noopener" style="color:#BE185D;font-weight:800;text-decoration:none;"><i class="fa-solid fa-paperclip"></i> View</a>` : '<span style="color:#94A3B8;">—</span>'}</td>
            </tr>`).join('');

        let sigHtml = '';
        if (sigData?.status === 'success') sigHtml = buildSignatureStatusHtml(sigData.data, 'expense_claim', id);

        content.innerHTML = `
            <div style="padding:14px;background:var(--bg-primary);border-radius:9px;display:flex;justify-content:space-between;gap:15px;align-items:flex-start;">
                <div><strong style="display:block;font-size:15px;color:var(--text-dark);">${htmlspecialchars(claim.title)}</strong><span style="font-size:11px;color:var(--text-muted);">${htmlspecialchars(claim.claim_no)} · ${htmlspecialchars(claim.user_name || '')} · ${htmlspecialchars(claim.expense_date || '')}</span></div>
                <div style="text-align:right;"><strong style="display:block;font-size:18px;color:#10B981;">MYR ${Number(claim.total_amount || 0).toFixed(2)}</strong><span style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;">${htmlspecialchars(claim.workflow_label || claim.status)}</span></div>
            </div>

            ${buildExpenseWorkflow(claim, workflow)}

            <div style="margin-top:16px;border:1px solid var(--border-color);border-radius:9px;overflow:hidden;">
                <div style="padding:10px 12px;background:var(--bg-primary);font-size:12px;font-weight:900;color:var(--text-dark);"><i class="fa-solid fa-chart-pie" style="color:#2563EB;"></i> Total by Category</div>
                <table class="expense-category-summary"><tbody>${categoryHtml || '<tr><td colspan="2">No category data.</td></tr>'}</tbody><tfoot><tr><td style="padding:10px;font-weight:900;">GRAND TOTAL</td><td style="padding:10px;text-align:right;font-weight:900;color:#10B981;">MYR ${Number(claim.total_amount || 0).toFixed(2)}</td></tr></tfoot></table>
            </div>

            ${claim.receipt_path ? `<div style="margin-top:14px;padding:10px;border:1px dashed #BE185D;border-radius:8px;background:#BE185D10;"><strong style="color:#BE185D;">Legacy Claim Receipt</strong> <a href="index.php?action=download_attachment&type=expense&id=${encodeURIComponent(id)}" target="_blank" rel="noopener" style="float:right;color:#BE185D;font-weight:800;">View</a></div>` : ''}

            <div style="margin-top:16px;overflow-x:auto;">
                <table style="width:100%;min-width:620px;border-collapse:collapse;font-size:11px;">
                    <thead><tr style="background:var(--bg-primary);border-bottom:2px solid var(--border-color);text-align:left;"><th style="padding:9px;width:30px;">#</th><th style="padding:9px;">Description</th><th style="padding:9px;width:190px;">Category</th><th style="padding:9px;width:100px;text-align:right;">Amount</th><th style="padding:9px;width:75px;text-align:center;">Receipt</th></tr></thead>
                    <tbody>${itemsHtml}</tbody>
                </table>
            </div>

            ${claim.notes ? `<div style="margin-top:14px;padding:10px;border:1px solid var(--border-color);border-radius:8px;"><strong>Notes:</strong> ${htmlspecialchars(claim.notes)}</div>` : ''}
            ${claim.rejection_reason ? `<div style="margin-top:12px;padding:10px;border-radius:8px;background:#FEE2E2;color:#B91C1C;"><strong>Rejection Reason:</strong> ${htmlspecialchars(claim.rejection_reason)}</div>` : ''}
            ${sigHtml}
        `;
    } catch (error) {
        console.error(error);
        content.innerHTML = `<p style="color:#EF4444;">${htmlspecialchars(error.message || 'Error connecting to server.')}</p>`;
    }
}

function htmlspecialchars(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Keep one clean row ready when the page first loads.
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('expenseRowsBody');
    if (tbody && tbody.children.length === 0) addExpenseRow();
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
