<?php
/**
 * Approval Center View — TBBA ERP Module 5
 */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<div class="page-content" style="padding: 24px;">
    <!-- Summary Counter Cards -->
    <div class="module-stat-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card module-stat-card" onclick="filterApprovalTab('leave')" style="padding: 20px; border-radius: 12px; border-left: 5px solid #2563EB; background: var(--bg-card); cursor: pointer; transition: transform 0.2s;" id="card_leave">
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;"><i class="fa-solid fa-calendar-minus" style="color: #2563EB;"></i> Pending Leave</div>
            <div style="font-size: 28px; font-weight: 800; color: var(--text-dark); margin-top: 8px;"><?= count($pendingLeave) ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">requests</span></div>
        </div>
        <div class="card module-stat-card" onclick="filterApprovalTab('expense')" style="padding: 20px; border-radius: 12px; border-left: 5px solid #BE185D; background: var(--bg-card); cursor: pointer; transition: transform 0.2s;" id="card_expense">
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;"><i class="fa-solid fa-receipt" style="color: #BE185D;"></i> Pending Expenses</div>
            <div style="font-size: 28px; font-weight: 800; color: var(--text-dark); margin-top: 8px;"><?= count($pendingExpense) ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">claims</span></div>
        </div>
        <div class="card module-stat-card" onclick="filterApprovalTab('purchase')" style="padding: 20px; border-radius: 12px; border-left: 5px solid #0891B2; background: var(--bg-card); cursor: pointer; transition: transform 0.2s;" id="card_purchase">
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;"><i class="fa-solid fa-cart-plus" style="color: #0891B2;"></i> Pending PRs</div>
            <div style="font-size: 28px; font-weight: 800; color: var(--text-dark); margin-top: 8px;"><?= count($pendingPurchase) ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">requests</span></div>
        </div>
    </div>

    <!-- Header & Filter Tabs -->
    <div class="card module-toolbar" style="padding: 20px; border-radius: 12px; margin-bottom: 24px; background: var(--bg-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h3 style="margin: 0; font-size: 18px; color: var(--text-dark);"><i class="fa-solid fa-check-double" style="color: #10B981;"></i> Universal Approval Inbox</h3>
                <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">Review and action all pending submissions requiring your authorization.</p>
            </div>
            <div class="module-tabs" style="display: flex; gap: 8px; background: var(--bg-primary); padding: 4px; border-radius: 8px; border: 1px solid var(--border-color);">
                <button class="btn-tab active" onclick="filterApprovalTab('all')" id="tab_all" style="padding: 8px 16px; border-radius: 6px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; background: #2563EB; color: #fff;">All (<?= $totalPending ?>)</button>
                <button class="btn-tab" onclick="filterApprovalTab('leave')" id="tab_leave" style="padding: 8px 16px; border-radius: 6px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; background: transparent; color: var(--text-dark);">Leave (<?= count($pendingLeave) ?>)</button>
                <button class="btn-tab" onclick="filterApprovalTab('expense')" id="tab_expense" style="padding: 8px 16px; border-radius: 6px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; background: transparent; color: var(--text-dark);">Expenses (<?= count($pendingExpense) ?>)</button>
                <button class="btn-tab" onclick="filterApprovalTab('purchase')" id="tab_purchase" style="padding: 8px 16px; border-radius: 6px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; background: transparent; color: var(--text-dark);">PRs (<?= count($pendingPurchase) ?>)</button>
            </div>
        </div>
    </div>

    <!-- Unified Approvals Table -->
    <div class="card module-data-card" style="padding: 20px; border-radius: 12px; background: var(--bg-card); overflow-x: auto;">
        <table class="datatable table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                    <th style="padding: 12px;">Module</th>
                    <th style="padding: 12px;">Ref / ID</th>
                    <th style="padding: 12px;">Requester</th>
                    <th style="padding: 12px;">Summary / Description</th>
                    <th style="padding: 12px;">Date Submitted</th>
                    <th style="padding: 12px; text-align: right;">Amount / Days</th>
                    <th style="padding: 12px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($totalPending === 0): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="font-size: 36px; color: #10B981; margin-bottom: 12px; display: block;"></i>
                        <strong style="font-size: 15px; color: var(--text-dark);">All caught up!</strong><br>
                        There are no pending requests requiring your approval right now.
                    </td>
                </tr>
                <?php else: ?>

                <!-- Leave Items -->
                <?php foreach ($pendingLeave as $l): ?>
                <tr class="approval-row row-leave" style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px;"><span style="background:#2563EB22; color:#2563EB; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700;">Leave</span></td>
                    <td style="padding: 12px; font-weight: 700;">#LR-<?= str_pad($l['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td style="padding: 12px;">
                        <div style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($l['user_name'] ?? 'Unknown') ?></div>
                        <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($l['department_name'] ?? 'General') ?></div>
                    </td>
                    <td style="padding: 12px;">
                        <strong><?= htmlspecialchars($l['leave_type_name']) ?></strong><br>
                        <span style="font-size:12px; color:var(--text-muted);"><?= date('d M Y', strtotime($l['start_date'])) ?> to <?= date('d M Y', strtotime($l['end_date'])) ?> <?= $l['reason'] ? '('.htmlspecialchars($l['reason']).')' : '' ?></span>
                        <?php if (!empty($l['attachment_path'])): ?>
                        <div style="margin-top: 4px;">
                            <a href="index.php?action=download_attachment&amp;type=leave&amp;id=<?= (int)$l['id'] ?>" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: #2563EB; background: #2563EB15; padding: 3px 8px; border-radius: 4px; text-decoration: none;">
                                <i class="fa-solid fa-paperclip"></i> View Proof
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($l['gps_coordinates'])): ?>
                        <div style="margin-top: 4px;">
                            <a href="https://maps.google.com/?q=<?= htmlspecialchars($l['gps_coordinates']) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: #10B981; background: #10B98115; padding: 3px 8px; border-radius: 4px; text-decoration: none;">
                                <i class="fa-solid fa-location-dot"></i> GPS Map
                            </a>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; color: var(--text-muted);"><?= date('d M Y, h:i A', strtotime($l['created_at'])) ?></td>
                    <td style="padding: 12px; text-align: right; font-weight: 700; color: var(--text-dark);"><?= $l['total_days'] ?> Days <?= $l['half_day'] ? '(Half)' : '' ?></td>
                    <td style="padding: 12px; text-align: center;">
                        <button class="btn btn-secondary btn-sm" onclick="viewLeaveDetail(<?= $l['id'] ?>)" style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; color: var(--text-dark); cursor: pointer; font-weight:600; margin-right: 4px;" title="View Details & Proof">
                            <i class="fa-solid fa-eye"></i> Detail
                        </button>
                        <button class="btn btn-success btn-sm" onclick="universalDecide('leave', <?= $l['id'] ?>, 'approved')" style="padding: 6px 12px; border-radius: 6px; border: none; background: #10B981; color: #fff; cursor: pointer; font-weight:600;" title="Approve">
                            <i class="fa-solid fa-check"></i> Approve
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="universalDecide('leave', <?= $l['id'] ?>, 'rejected')" style="padding: 6px 12px; border-radius: 6px; border: none; background: #EF4444; color: #fff; cursor: pointer; font-weight:600;" title="Reject">
                            <i class="fa-solid fa-times"></i> Reject
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>

                <!-- Expense Items -->
                <?php foreach ($pendingExpense as $e): ?>
                <tr class="approval-row row-expense" style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px;"><span style="background:#BE185D22; color:#BE185D; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700;">Expense</span></td>
                    <td style="padding: 12px; font-weight: 700; color: #BE185D;"><?= htmlspecialchars($e['claim_no'] ?? '#') ?></td>
                    <td style="padding: 12px;">
                        <div style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($e['user_name'] ?? 'Unknown') ?></div>
                        <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($e['department_name'] ?? 'General') ?></div>
                    </td>
                    <td style="padding: 12px;">
                        <strong><?= htmlspecialchars($e['title']) ?></strong><br>
                        <span style="font-size:12px; color:var(--text-muted);">Expense Date: <?= date('d M Y', strtotime($e['expense_date'])) ?> · Finance Verified</span>
                        <?php if (!empty($e['receipt_path'])): ?>
                        <div style="margin-top: 4px;">
                            <a href="index.php?action=download_attachment&amp;type=expense&amp;id=<?= (int)$e['id'] ?>" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: #BE185D; background: #BE185D15; padding: 3px 8px; border-radius: 4px; text-decoration: none;">
                                <i class="fa-solid fa-paperclip"></i> View Proof / Receipt
                            </a>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; color: var(--text-muted);"><?= date('d M Y, h:i A', strtotime($e['created_at'])) ?></td>
                    <td style="padding: 12px; text-align: right; font-weight: 800; color: #10B981;">MYR <?= number_format($e['total_amount'], 2) ?></td>
                    <td style="padding: 12px; text-align: center;">
                        <button class="btn btn-secondary btn-sm" onclick="viewExpenseDetail(<?= $e['id'] ?>)" style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; color: var(--text-dark); cursor: pointer; font-weight:600; margin-right: 4px;" title="View Line Items & Receipt">
                            <i class="fa-solid fa-eye"></i> Detail
                        </button>
                        <button class="btn btn-success btn-sm" onclick="universalDecide('expense', <?= $e['id'] ?>, 'approved')" style="padding: 6px 12px; border-radius: 6px; border: none; background: #10B981; color: #fff; cursor: pointer; font-weight:600;" title="Sign & Approve">
                            <i class="fa-solid fa-pen-nib"></i> Sign & Approve
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="universalDecide('expense', <?= $e['id'] ?>, 'rejected')" style="padding: 6px 12px; border-radius: 6px; border: none; background: #EF4444; color: #fff; cursor: pointer; font-weight:600;" title="Reject">
                            <i class="fa-solid fa-times"></i> Reject
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>

                <!-- Purchase Request Items -->
                <?php foreach ($pendingPurchase as $p): ?>
                <tr class="approval-row row-purchase" style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px;"><span style="background:#0891B222; color:#0891B2; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700;">Purchase</span></td>
                    <td style="padding: 12px; font-weight: 700; color: #0891B2;"><?= htmlspecialchars($p['pr_no'] ?? '#') ?></td>
                    <td style="padding: 12px;">
                        <div style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($p['user_name'] ?? 'Unknown') ?></div>
                        <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($p['department_name'] ?? 'General') ?></div>
                    </td>
                    <td style="padding: 12px;">
                        <strong><?= htmlspecialchars($p['title']) ?></strong><br>
                        <span style="font-size:12px; color:var(--text-muted);">Vendor: <?= htmlspecialchars($p['vendor'] ?: 'Not specified') ?> <?= $p['required_date'] ? '[Needed: '.date('d M Y', strtotime($p['required_date'])).']' : '' ?></span>
                    </td>
                    <td style="padding: 12px; color: var(--text-muted);"><?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></td>
                    <td style="padding: 12px; text-align: right; font-weight: 800; color: #10B981;">MYR <?= number_format($p['total_amount'], 2) ?></td>
                    <td style="padding: 12px; text-align: center;">
                        <button class="btn btn-success btn-sm" onclick="universalDecide('purchase', <?= $p['id'] ?>, 'approved')" style="padding: 6px 12px; border-radius: 6px; border: none; background: #10B981; color: #fff; cursor: pointer; font-weight:600;" title="Approve">
                            <i class="fa-solid fa-check"></i> Approve
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="universalDecide('purchase', <?= $p['id'] ?>, 'rejected')" style="padding: 6px 12px; border-radius: 6px; border: none; background: #EF4444; color: #fff; cursor: pointer; font-weight:600;" title="Reject">
                            <i class="fa-solid fa-times"></i> Reject
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterApprovalTab(module) {
    document.querySelectorAll('.btn-tab').forEach(b => {
        b.style.background = 'transparent';
        b.style.color = 'var(--text-dark)';
    });
    const activeTab = document.getElementById(`tab_${module}`);
    if (activeTab) {
        activeTab.style.background = '#2563EB';
        activeTab.style.color = '#fff';
    }

    document.querySelectorAll('.approval-row').forEach(row => {
        if (module === 'all' || row.classList.contains(`row-${module}`)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

async function universalDecide(module, id, decision) {
    // Expense approval uses signature-first flow. Rejection remains a direct decision.
    if (module === 'expense' && decision === 'approved') {
        await signAndApproveExpense(id);
        return;
    }

    let reason = '';
    if (decision === 'rejected') {
        reason = prompt(`Please enter reason for rejecting this ${module} request:`);
        if (reason === null) return;
    } else {
        if (!await App.confirm(`Confirm ${ucfirst(decision)}`, `Are you sure you want to approve this ${module} request?`)) return;
    }

    const formData = new FormData();
    formData.append('module', module);
    formData.append('id', id);
    formData.append('decision', decision);
    formData.append('csrf_token', '<?= Helper::csrfToken() ?>');
    if (reason) formData.append('reason', reason);

    const res = await App.post('index.php?action=universal_approval_decide', formData);
    if (res && res.status === 'success') {
        setTimeout(() => location.reload(), 650);
    }
}

async function signAndApproveExpense(id) {
    const confirmed = await App.confirm(
        'Sign & Approve Expense',
        'You will sign this Finance-verified expense claim digitally. Once your signature is saved, the claim will be approved automatically.'
    );
    if (!confirmed) return;

    // If the current approver has already signed (for example after a previous
    // interrupted attempt), skip the signature modal and finalize immediately.
    let alreadySigned = false;
    try {
        const current = await fetch(`index.php?action=check_expense_approver_signature&id=${encodeURIComponent(id)}`, {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            cache: 'no-store'
        });
        const currentData = await current.json();
        alreadySigned = currentData.status === 'success' && Boolean(currentData.data?.signed);
    } catch (e) {
        console.warn('[Expense Approval] Unable to check existing approver signature:', e);
    }

    if (alreadySigned) {
        await finalizeExpenseApproval(id);
        return;
    }

    // ESign already supports an onSuccess callback. Finalize the approval only
    // after sign_document has returned success and the signature is in the DB.
    ESign.openModal(
        'expense_claim',
        id,
        'approver',
        async function () {
            await finalizeExpenseApproval(id);
        }
    );
}

async function finalizeExpenseApproval(id) {
    const formData = new FormData();
    formData.append('module', 'expense');
    formData.append('id', id);
    formData.append('decision', 'approved');
    formData.append('csrf_token', '<?= Helper::csrfToken() ?>');

    const res = await App.post('index.php?action=universal_approval_decide', formData);
    if (res && res.status === 'success') {
        setTimeout(() => location.reload(), 650);
    }
}

function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Helper untuk bina UI status signature
function buildSignatureStatusHtml(sigData, type, id) {
    let html = '<div class="esign-status-card"><h4><i class="fa-solid fa-pen-nib"></i> E-Signatures</h4>';
    const getSig = (role) => sigData.signatures.find(s => s.signer_role === role);
    const applicantSig = getSig('applicant');
    const approverSig = getSig('approver');

    if (applicantSig) {
        html += `<div class="esign-preview-box"><div class="esign-preview-info"><span class="esign-preview-name">${htmlspecialchars(applicantSig.user_name)} (Applicant)</span><span class="esign-preview-date">Signed: ${htmlspecialchars(applicantSig.signed_at_formatted)}</span></div><img src="${htmlspecialchars(applicantSig.signature_full_url)}" class="esign-preview-img" alt="Sig"></div>`;
    } else {
        html += `<div class="esign-preview-box pending"><i class="fa-solid fa-clock"></i> Applicant Signature Pending</div>`;
    }

    if (approverSig) {
        html += `<div class="esign-preview-box"><div class="esign-preview-info"><span class="esign-preview-name">${htmlspecialchars(approverSig.user_name)} (Approver)</span><span class="esign-preview-date">Signed: ${htmlspecialchars(approverSig.signed_at_formatted)}</span></div><img src="${htmlspecialchars(approverSig.signature_full_url)}" class="esign-preview-img" alt="Sig"></div>`;
    } else {
        html += `<div class="esign-preview-box pending"><i class="fa-solid fa-clock"></i> Approver Signature Pending</div>`;
    }

    html += '<div class="esign-pdf-actions">';
    if (applicantSig && approverSig) {
        html += `<button class="btn btn-primary btn-sm" onclick="ESign.generatePdf('${type}', ${id}, this)" style="flex:1;"><i class="fa-solid fa-file-pdf"></i> Generate/Download PDF</button>`;
        html += `<button class="btn btn-secondary btn-sm" onclick="ESign.verify('${type}', ${id})" title="Verify signature integrity"><i class="fa-solid fa-shield-halved"></i> Verify</button>`;
    } else if (!applicantSig && type === 'expense_claim') {
        html += `<button class="btn btn-primary btn-sm" onclick="ESign.openModal('${type}', ${id}, 'applicant')" style="flex:1;"><i class="fa-solid fa-pen-nib"></i> Sign Now (Applicant)</button>`;
    }
    html += '</div></div>';
    return html;
}

function buildExpenseWorkflow(claim, workflow) {
    const stage = workflow?.stage || claim.workflow_stage || '';
    const applicantDone = Boolean(workflow?.applicant_signed);
    const financeDone = Boolean(workflow?.finance_verified);
    const managementDone = ['approved','rejected','paid'].includes(claim.status);
    const paymentDone = claim.status === 'paid';

    const step = (icon, title, text, state) => `
        <div style="padding:10px;border-radius:8px;border:1px solid ${state === 'done' ? '#A7F3D0' : (state === 'active' ? '#BFDBFE' : (state === 'danger' ? '#FECACA' : 'var(--border-color)'))};background:${state === 'done' ? '#ECFDF5' : (state === 'active' ? '#EFF6FF' : (state === 'danger' ? '#FEF2F2' : 'var(--bg-card)'))};color:${state === 'done' ? '#047857' : (state === 'active' ? '#1D4ED8' : (state === 'danger' ? '#B91C1C' : 'var(--text-muted)'))};font-size:11px;">
            <i class="fa-solid ${icon}"></i><br><strong>${title}</strong><br>${text}
        </div>`;

    return `<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:14px 0;">
        ${step(applicantDone ? 'fa-circle-check' : 'fa-pen-nib', 'Applicant', applicantDone ? 'Signed' : 'Signature Pending', applicantDone ? 'done' : '')}
        ${step(financeDone ? 'fa-circle-check' : 'fa-magnifying-glass-dollar', 'Finance', financeDone ? 'Checked & Forwarded' : 'Pending Review', financeDone ? 'done' : (stage === 'finance_review' ? 'active' : ''))}
        ${step(claim.status === 'rejected' ? 'fa-circle-xmark' : (managementDone ? 'fa-circle-check' : 'fa-user-tie'), 'Management', claim.status === 'rejected' ? 'Rejected' : (managementDone ? 'Approved' : 'Pending Approval'), claim.status === 'rejected' ? 'danger' : (managementDone ? 'done' : (stage === 'management_approval' ? 'active' : '')))}
        ${step(paymentDone ? 'fa-circle-check' : 'fa-money-bill-wave', 'Payment', paymentDone ? 'Paid' : (claim.status === 'approved' ? 'Awaiting Payment' : 'Pending'), paymentDone ? 'done' : (claim.status === 'approved' ? 'active' : ''))}
    </div>`;
}

async function viewExpenseDetail(id) {
    App.openModal('viewExpenseModal');
    const content = document.getElementById('expenseDetailContent');
    content.innerHTML = '<p style="text-align:center;"><i class="fa-solid fa-spinner fa-spin"></i> Loading claim...</p>';

    try {
        const [res, sigRes] = await Promise.all([
            fetch(`index.php?action=get_expense&id=${encodeURIComponent(id)}`, {headers:{'X-Requested-With':'XMLHttpRequest'}}),
            fetch(`index.php?action=get_signature_status&document_type=expense_claim&document_id=${encodeURIComponent(id)}`, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        ]);
        const data = await res.json();
        const sigData = await sigRes.json().catch(() => null);

        if (data.status !== 'success' || !data.data) {
            content.innerHTML = `<p style="color:#EF4444;">${htmlspecialchars(data.message || 'Failed to load claim details.')}</p>`;
            return;
        }

        const { claim, items, category_totals, workflow } = data.data;
        const categoryHtml = (category_totals || []).map(row => `
            <tr><td style="padding:8px 10px;border-bottom:1px solid var(--border-color);">${htmlspecialchars(row.category || 'Misc Expenses')}</td><td style="padding:8px 10px;border-bottom:1px solid var(--border-color);text-align:right;font-weight:800;">MYR ${Number(row.total || 0).toFixed(2)}</td></tr>
        `).join('');

        const itemsHtml = (items || []).map((item, idx) => `
            <tr style="border-bottom:1px solid var(--border-color);">
                <td style="padding:8px;">${idx + 1}</td>
                <td style="padding:8px;font-weight:600;">${htmlspecialchars(item.description)}</td>
                <td style="padding:8px;"><span style="background:var(--bg-primary);padding:2px 7px;border-radius:4px;font-size:10px;">${htmlspecialchars(item.category)}</span></td>
                <td style="padding:8px;text-align:right;font-weight:800;color:#10B981;">${Number(item.amount || 0).toFixed(2)}</td>
                <td style="padding:8px;text-align:center;">${item.receipt_path ? `<a href="index.php?action=download_expense_item_receipt&item_id=${encodeURIComponent(item.id)}" target="_blank" rel="noopener" style="color:#BE185D;font-weight:800;text-decoration:none;"><i class="fa-solid fa-paperclip"></i> View</a>` : '<span style="color:#94A3B8;">—</span>'}</td>
            </tr>`).join('');

        let sigHtml = '';
        if (sigData?.status === 'success') sigHtml = buildSignatureStatusHtml(sigData.data, 'expense_claim', id);

        content.innerHTML = `
            <div style="margin-bottom:14px;padding:12px;background:var(--bg-primary);border-radius:8px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div><strong style="font-size:15px;color:var(--text-dark);display:block;">${htmlspecialchars(claim.title)}</strong><span style="font-size:11px;color:var(--text-muted);">${htmlspecialchars(claim.claim_no)} · ${htmlspecialchars(claim.user_name || '')} · ${htmlspecialchars(claim.expense_date || '')}</span></div>
                <div style="text-align:right;"><span style="font-size:18px;font-weight:900;color:#10B981;display:block;">MYR ${Number(claim.total_amount || 0).toFixed(2)}</span><span style="font-size:10px;text-transform:uppercase;font-weight:800;color:var(--text-muted);">${htmlspecialchars(claim.workflow_label || claim.status)}</span></div>
            </div>

            ${buildExpenseWorkflow(claim, workflow)}

            <div style="margin-top:14px;border:1px solid var(--border-color);border-radius:8px;overflow:hidden;">
                <div style="padding:10px 12px;background:var(--bg-primary);font-size:12px;font-weight:900;"><i class="fa-solid fa-chart-pie" style="color:#2563EB;"></i> Total by Category</div>
                <table style="width:100%;border-collapse:collapse;font-size:12px;"><tbody>${categoryHtml || '<tr><td style="padding:10px;">No category data.</td></tr>'}</tbody><tfoot><tr><td style="padding:10px;font-weight:900;">GRAND TOTAL</td><td style="padding:10px;text-align:right;font-weight:900;color:#10B981;">MYR ${Number(claim.total_amount || 0).toFixed(2)}</td></tr></tfoot></table>
            </div>

            ${claim.receipt_path ? `<div style="margin-top:12px;padding:10px;background:#BE185D10;border:1px dashed #BE185D;border-radius:8px;"><strong style="color:#BE185D;">Legacy Claim Receipt</strong><a href="index.php?action=download_attachment&type=expense&id=${encodeURIComponent(id)}" target="_blank" rel="noopener" style="float:right;color:#BE185D;font-weight:800;">View</a></div>` : ''}

            <div style="margin-top:14px;overflow-x:auto;">
                <table style="width:100%;min-width:610px;border-collapse:collapse;font-size:11px;">
                    <thead><tr style="background:var(--bg-primary);border-bottom:2px solid var(--border-color);text-align:left;"><th style="padding:8px;width:30px;">#</th><th style="padding:8px;">Description</th><th style="padding:8px;width:190px;">Category</th><th style="padding:8px;width:95px;text-align:right;">Amount</th><th style="padding:8px;width:70px;text-align:center;">Receipt</th></tr></thead>
                    <tbody>${itemsHtml}</tbody>
                </table>
            </div>

            ${claim.notes ? `<div style="margin-top:14px;padding:10px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:6px;"><strong>Notes:</strong> ${htmlspecialchars(claim.notes)}</div>` : ''}
            ${sigHtml}
        `;
    } catch (e) {
        console.error(e);
        content.innerHTML = '<p style="color:#EF4444;">Error connecting to server.</p>';
    }
}

async function viewLeaveDetail(id) {
    App.openModal('viewLeaveModal');
    const content = document.getElementById('leaveDetailContent');
    content.innerHTML = '<p style="text-align:center;"><i class="fa-solid fa-spinner fa-spin"></i> Loading details...</p>';
    try {
        const res = await fetch(`index.php?action=get_leave&id=${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();

        if (data.status === 'success' && data.data) {
            const r = data.data;
            content.innerHTML = `
                <div style="display:grid; grid-template-columns: 120px 1fr; gap: 8px; margin-bottom: 12px;">
                    <strong style="color: var(--text-muted);">Staff Name:</strong> <span style="color: var(--text-dark); font-weight:600;">${htmlspecialchars(r.user_name || '-')}</span>
                    <strong style="color: var(--text-muted);">Leave Type:</strong> <span style="color: var(--text-dark); font-weight:600;">${htmlspecialchars(r.leave_type_name || '-')}</span>
                    <strong style="color: var(--text-muted);">Start Date:</strong> <span style="color: var(--text-dark);">${htmlspecialchars(r.start_date || '-')}</span>
                    <strong style="color: var(--text-muted);">End Date:</strong> <span style="color: var(--text-dark);">${htmlspecialchars(r.end_date || '-')}</span>
                    <strong style="color: var(--text-muted);">Total Days:</strong> <span style="color: var(--text-dark); font-weight:600;">${htmlspecialchars(r.total_days)} Day(s) ${r.half_day == 1 ? '(Half Day)' : ''}</span>
                    <strong style="color: var(--text-muted);">Status:</strong> <span style="color: var(--text-dark); text-transform:uppercase; font-weight:700;">${htmlspecialchars(r.status || '-')}</span>
                    ${r.gps_coordinates ? `<strong style="color: var(--text-muted);">GPS Stamped:</strong> <span><a href="https://maps.google.com/?q=${encodeURIComponent(r.gps_coordinates)}" target="_blank" rel="noopener" style="color:#10B981; font-weight:700; text-decoration:none;"><i class="fa-solid fa-location-dot"></i> ${htmlspecialchars(r.gps_coordinates)} (Open Map)</a></span>` : ''}
                    ${r.attachment_path ? `<strong style="color: var(--text-muted);">Attached Proof:</strong> <span><a href="index.php?action=download_attachment&type=leave&id=${encodeURIComponent(id)}" target="_blank" rel="noopener" style="color:#2563EB; font-weight:700; text-decoration:none;"><i class="fa-solid fa-paperclip"></i> View Document / Snapshot</a></span>` : ''}
                </div>
                <div style="background: var(--bg-primary); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); margin-top: 12px;">
                    <strong style="color: var(--text-muted); display:block; margin-bottom:4px;">Reason / Remarks:</strong>
                    <span style="color: var(--text-dark);">${htmlspecialchars(r.reason || 'No reason provided.')}</span>
                </div>
            `;
        } else {
            content.innerHTML = `<p style="color:#EF4444;">Failed to load request details.</p>`;
        }
    } catch (e) {
        console.error("Approval leave detail load error:", e);
        content.innerHTML = `<p style="color:#EF4444;">Error connecting to server.</p>`;
    }
}

function htmlspecialchars(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<!-- Modal: View Expense Detail -->
<div class="modal-overlay module-clean-modal" id="viewExpenseModal">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-list-check" style="color: #BE185D;"></i> Expense Claim Details</h3>
            <button class="modal-close" onclick="App.closeModal('viewExpenseModal')">&times;</button>
        </div>
        <div class="modal-body" id="expenseDetailContent" style="padding: 20px; font-size: 13px; line-height: 1.6;">
            <p>Loading...</p>
        </div>
        <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); text-align: right;">
            <button class="btn btn-secondary" onclick="App.closeModal('viewExpenseModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Close</button>
        </div>
    </div>
</div>

<!-- Modal: View Leave Detail -->
<div class="modal-overlay module-clean-modal" id="viewLeaveModal">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-file-lines" style="color: #2563EB;"></i> Leave Request Details</h3>
            <button class="modal-close" onclick="App.closeModal('viewLeaveModal')">&times;</button>
        </div>
        <div class="modal-body" id="leaveDetailContent" style="padding: 20px; font-size: 13px; line-height: 1.6;">
            <p>Loading...</p>
        </div>
        <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); text-align: right;">
            <button class="btn btn-secondary" onclick="App.closeModal('viewLeaveModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Close</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
