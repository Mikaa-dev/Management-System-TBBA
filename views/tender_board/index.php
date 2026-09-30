<?php
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
$today = date('Y-m-d');
$openTenderCount = count(array_filter(
    $tenders,
    fn($tender) => $tender['closing_date'] >= $today && (int)$tender['participant_count'] === 0
));
$myTenderCount = count(array_filter($tenders, fn($tender) => !empty($tender['joined_by_me'])));
$closedTenderCount = count(array_filter($tenders, fn($tender) => $tender['closing_date'] < $today));
?>

<style>
.tb-board { padding:24px; }
.tb-hero { background:linear-gradient(135deg,#0F172A,#1D4ED8); color:#fff; border-radius:20px; padding:28px; margin-bottom:22px; display:flex; justify-content:space-between; align-items:center; gap:18px; flex-wrap:wrap; box-shadow:0 12px 28px rgba(15,23,42,.2); }
.tb-table-wrap { overflow-x:auto;background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;box-shadow:0 4px 14px rgba(15,23,42,.05); }
.tb-table { width:100%;min-width:960px;border-collapse:collapse; }
.tb-table th { padding:13px 14px;background:var(--bg-primary);border-bottom:1px solid var(--border-color);color:var(--text-muted);font-size:10px;font-weight:800;text-align:left;text-transform:uppercase;letter-spacing:.55px;white-space:nowrap; }
.tb-table td { padding:14px;border-bottom:1px solid var(--border-color);color:var(--text-dark);font-size:12px;vertical-align:middle; }
.tb-table tbody tr:last-child td { border-bottom:0; }
.tb-table tbody tr:hover td { background:rgba(248,250,252,.75); }
.tb-tender-title { display:block;max-width:460px;margin-top:7px;color:var(--text-dark);font-size:13px;font-weight:800;line-height:1.4;overflow-wrap:anywhere; }
.tb-meta { display:block;margin-top:4px;color:var(--text-muted);font-size:10px;line-height:1.4; }
.tb-assignee { display:flex;align-items:center;gap:8px;min-width:170px; }
.tb-assignee-avatar { display:flex;align-items:center;justify-content:center;width:30px;height:30px;flex:0 0 30px;border-radius:50%;background:#DCFCE7;color:#047857;font-size:10px;font-weight:800; }
.tb-assignee-name { display:block;max-width:165px;font-size:11px;font-weight:700;line-height:1.35; }
.tb-available { color:#64748B;font-size:11px;font-style:italic; }
.tb-status-badge { display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap;text-transform:uppercase;letter-spacing:.25px; }
.tb-status-badge.in-progress { border-color:#FDE68A;background:#FFFBEB;color:#B45309; }
.tb-status-badge.submitted { border-color:#BFDBFE;background:#EFF6FF;color:#1D4ED8; }
.tb-status-badge.won { border-color:#A7F3D0;background:#ECFDF5;color:#047857; }
.tb-status-badge.lost { border-color:#FECACA;background:#FEF2F2;color:#B91C1C; }
.tb-lock-note { display:inline-flex;align-items:center;gap:5px;margin-top:6px;color:#64748B;font-size:10px;font-weight:700; }
.tb-pricing-summary { min-width:145px;line-height:1.5; }
.tb-pricing-summary strong { color:#047857; }
.tb-qt { display:inline-flex; align-items:center; gap:7px; background:#DBEAFE; color:#1D4ED8; padding:5px 11px; border-radius:999px; font-size:12px; font-weight:800; }
.tb-today { background:#DCFCE7; color:#15803D; padding:4px 9px; border-radius:999px; font-size:10px; font-weight:800; text-transform:uppercase; }
.tb-deadline { display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:10px;font-weight:800;text-transform:uppercase; }
.tb-deadline.open { background:#FEF3C7;color:#B45309; }
.tb-deadline.closed { background:#FEE2E2;color:#B91C1C; }
.tb-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.tb-actions .btn { min-height:34px;padding:7px 10px;border-radius:8px;font-size:11px;white-space:nowrap; }
.tb-search { width:min(360px,100%); padding:10px 13px 10px 38px; border:1px solid var(--border-color); border-radius:10px; background:var(--bg-card); color:var(--text-dark); }
.tb-tabs { display:flex;gap:8px;overflow-x:auto;margin-bottom:18px;padding-bottom:2px;scrollbar-width:thin; }
.tb-tab { display:inline-flex;align-items:center;gap:8px;white-space:nowrap;padding:9px 13px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-card);color:var(--text-muted);font:inherit;font-size:12px;font-weight:800;cursor:pointer; }
.tb-tab:hover { border-color:#93C5FD;color:#1D4ED8; }
.tb-tab.active { border-color:#2563EB;background:#EFF6FF;color:#1D4ED8;box-shadow:0 0 0 2px rgba(37,99,235,.08); }
.tb-count { display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;padding:0 6px;border-radius:999px;background:#E2E8F0;color:#475569;font-size:10px; }
.tb-tab.active .tb-count { background:#2563EB;color:#fff; }
.tb-pagination { display:flex;justify-content:center;align-items:center;gap:10px;margin-top:20px; }
.tb-page-button { min-width:38px;height:38px;padding:0 12px;border:1px solid var(--border-color);border-radius:9px;background:var(--bg-card);color:var(--text-dark);cursor:pointer;font-weight:700; }
.tb-page-button:disabled { opacity:.45;cursor:not-allowed; }
.tb-page-label { color:var(--text-muted);font-size:12px;font-weight:700; }
.tb-field { margin-bottom:16px; }
.tb-field:last-child { margin-bottom:0; }
.tb-label { display:block;margin-bottom:7px;color:var(--text-dark);font-size:12px;font-weight:800; }
.tb-control { display:block;width:100%;box-sizing:border-box;min-height:43px;padding:10px 12px;border:1px solid #CBD5E1;border-radius:8px;background:var(--bg-card);color:var(--text-dark);font:inherit;font-size:14px;outline:none; }
.tb-control:focus { border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.tb-control[readonly] { border-color:#E2E8F0;background:#F8FAFC;color:#334155;cursor:default;box-shadow:none; }
.tb-pricing-readonly { display:none;margin:0 0 14px;padding:9px 11px;border:1px solid #BFDBFE;border-radius:9px;background:#EFF6FF;color:#1E40AF;font-size:10px;font-weight:700;line-height:1.5; }
textarea.tb-control { min-height:96px;resize:vertical; }
.tb-pricing-grid { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px; }
.tb-pricing-note { margin-top:12px;padding:11px 12px;border-radius:10px;background:#F0FDF4;color:#166534;font-size:12px;line-height:1.5; }
@media (max-width:760px) {
    .tb-board{padding:12px}.tb-hero{padding:20px;border-radius:15px}.tb-hero h2{font-size:23px!important}.tb-pricing-grid{grid-template-columns:1fr}
    .tb-table-wrap{overflow:visible;background:transparent;border:0;box-shadow:none}.tb-table{display:block;min-width:0}.tb-table thead{display:none}.tb-table tbody{display:grid;gap:12px}.tb-table tr{display:block}.tb-table tr.tender-board-item{overflow:hidden;background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;box-shadow:0 3px 10px rgba(15,23,42,.05)}
    .tb-table td{display:grid;grid-template-columns:104px minmax(0,1fr);align-items:start;gap:10px;padding:10px 13px;border-bottom:1px solid var(--border-color);font-size:12px}.tb-table td::before{content:attr(data-label);color:var(--text-muted);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.5px}.tb-table td.tb-primary-cell{display:block;padding:14px}.tb-table td.tb-primary-cell::before,.tb-table td.tb-empty-cell::before{display:none}.tb-table td.tb-empty-cell{display:block;text-align:center;padding:36px 18px}.tb-table tbody tr:hover td{background:transparent}
    .tb-tender-title{max-width:none;font-size:14px}.tb-assignee{min-width:0}.tb-assignee-name{max-width:none}.tb-pricing-summary{min-width:0}.tb-actions{width:100%}.tb-actions .btn{flex:1;justify-content:center}.tb-pagination{margin-top:14px}
}
</style>

<div class="page-content tb-board">
    <?php include __DIR__ . '/workspace_nav.php'; ?>
    <section class="tb-hero">
        <div>
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.9px;color:#BFDBFE;margin-bottom:8px;">
                <i class="fa-solid fa-building-shield"></i> Corporate Operations
            </div>
            <h2 style="margin:0 0 7px;font-size:27px;">Tender Opportunities</h2>
            <p style="margin:0;color:#DBEAFE;max-width:650px;line-height:1.55;">Review daily opportunities, take ownership of an available tender, manage its pricing and update its progress.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <?php if ($canCreate): ?>
            <button class="btn btn-primary" onclick="openTenderBoardModal()" style="background:#fff;color:#1D4ED8;border:none;padding:11px 17px;border-radius:10px;font-weight:800;">
                <i class="fa-solid fa-plus"></i> Add Opportunity
            </button>
            <?php endif; ?>
        </div>
    </section>

    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:18px;">
        <div>
            <strong style="color:var(--text-dark);font-size:15px;"><?= count($tenders) ?> tender<?= count($tenders) === 1 ? '' : 's' ?></strong>
            <span style="color:var(--text-muted);font-size:12px;margin-left:6px;">— each tender can only be taken by <strong>1 staff</strong></span>
        </div>
        <div style="position:relative;width:min(360px,100%);">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94A3B8;"></i>
            <input id="tenderBoardSearch" class="tb-search" type="search" placeholder="Search QT number or title…" oninput="filterTenderBoard()">
        </div>
    </div>

    <div class="tb-tabs" role="tablist" aria-label="Tender filters">
        <button type="button" class="tb-tab active" data-filter="open" aria-selected="true" onclick="setTenderBoardFilter('open', this)">
            <i class="fa-solid fa-folder-open"></i> Open Tenders <span class="tb-count"><?= $openTenderCount ?></span>
        </button>
        <button type="button" class="tb-tab" data-filter="mine" aria-selected="false" onclick="setTenderBoardFilter('mine', this)">
            <i class="fa-solid fa-user-check"></i> My Tenders <span class="tb-count"><?= $myTenderCount ?></span>
        </button>
        <button type="button" class="tb-tab" data-filter="closed" aria-selected="false" onclick="setTenderBoardFilter('closed', this)">
            <i class="fa-solid fa-box-archive"></i> Closed <span class="tb-count"><?= $closedTenderCount ?></span>
        </button>
    </div>

    <div class="tb-table-wrap">
        <table class="tb-table" aria-label="Tender opportunities">
            <thead>
                <tr>
                    <th>Tender</th>
                    <th>Closing Date</th>
                    <th>Assigned Staff</th>
                    <th>Project Pricing</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tenderBoardGrid">
                <?php foreach ($tenders as $tender): ?>
                <?php
                    $isClosed = $tender['closing_date'] < $today;
                    $isAssignedToAnotherStaff = !$tender['joined_by_me'] && (int)$tender['participant_count'] > 0;
                    $daysLeft = (int)floor((strtotime($tender['closing_date']) - strtotime($today)) / 86400);
                    $myStatus = $tender['joined_by_me'] ? ($tender['user_tender_status'] ?? 'in_progress') : null;
                    $assignee = $tender['participants'][0] ?? null;
                    $statusLabels = [
                        'in_progress' => 'In Progress',
                        'submitted' => 'Submitted',
                        'won' => 'Won',
                        'lost' => 'Lost',
                    ];
                    $statusIcons = [
                        'in_progress' => 'fa-spinner',
                        'submitted' => 'fa-paper-plane',
                        'won' => 'fa-trophy',
                        'lost' => 'fa-circle-xmark',
                    ];
                ?>
                <tr class="tender-board-item" data-search="<?= htmlspecialchars(strtolower($tender['qt_number'] . ' ' . $tender['title'] . ' ' . ($assignee['name'] ?? ''))) ?>" data-status="<?= $isClosed ? 'closed' : 'open' ?>" data-joined="<?= $tender['joined_by_me'] ? '1' : '0' ?>" data-assigned="<?= (int)$tender['participant_count'] > 0 ? '1' : '0' ?>">
                    <td class="tb-primary-cell" data-label="Tender">
                        <span class="tb-qt"><i class="fa-solid fa-hashtag"></i><?= htmlspecialchars($tender['qt_number']) ?></span>
                        <?php if ($tender['tender_date'] === $today): ?><span class="tb-today">Today</span><?php endif; ?>
                        <span class="tb-tender-title"><?= htmlspecialchars($tender['title']) ?></span>
                    </td>
                    <td data-label="Closing Date">
                        <strong><?= date('d M Y', strtotime($tender['closing_date'])) ?></strong>
                        <span class="tb-deadline <?= $isClosed ? 'closed' : 'open' ?>" style="margin-top:6px;">
                            <i class="fa-regular fa-clock"></i>
                            <?= $isClosed ? 'Closed' : ($daysLeft === 0 ? 'Closes Today' : $daysLeft . ' Days Left') ?>
                        </span>
                    </td>
                    <td data-label="Assigned Staff">
                        <?php if ($assignee): ?>
                        <div class="tb-assignee">
                            <span class="tb-assignee-avatar"><?= htmlspecialchars(strtoupper(substr($assignee['name'], 0, 2))) ?></span>
                            <span>
                                <span class="tb-assignee-name"><?= htmlspecialchars($assignee['name']) ?></span>
                                <span class="tb-meta"><?= htmlspecialchars($assignee['position_title'] ?: 'Staff') ?></span>
                            </span>
                        </div>
                        <?php else: ?>
                        <span class="tb-available"><i class="fa-regular fa-circle-check"></i> Available</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Project Pricing">
                        <?php if ($tender['joined_by_me']): ?>
                        <div class="tb-pricing-summary">
                            <?php if ($tender['user_pricing_complete']): ?>
                            <span class="tb-meta">Selling Price</span>
                            <strong>RM <?= number_format((float)$tender['user_selling_price'], 2) ?></strong>
                            <?php else: ?>
                            <span style="color:#B45309;font-weight:700;">Pricing Pending</span>
                            <?php endif; ?>
                            <?php $pricingReadOnly = $myStatus !== 'in_progress'; ?>
                            <button class="btn" type="button" onclick='openProjectPricingModal(<?= json_encode([
                                'id' => (int)$tender['id'],
                                'title' => $tender['title'],
                                'indicative_price' => $tender['user_indicative_price'],
                                'cost' => $tender['user_cost'],
                                'selling_price' => $tender['user_selling_price'],
                                'attachment_name' => $tender['user_pricing_attachment_name'],
                                'readonly' => $pricingReadOnly,
                            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)' style="display:flex;margin-top:6px;border:0;background:#ECFDF5;color:#047857;">
                                <i class="fa-solid <?= $pricingReadOnly ? 'fa-eye' : 'fa-calculator' ?>"></i>
                                <?= $pricingReadOnly ? 'View Pricing' : ($tender['user_pricing_complete'] ? 'Edit Pricing' : 'Add Pricing') ?>
                            </button>
                            <?php if ($pricingReadOnly): ?>
                            <span class="tb-lock-note"><i class="fa-solid fa-lock"></i> Read Only</span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?><span class="tb-meta">—</span><?php endif; ?>
                    </td>
                    <td data-label="Status">
                        <?php if ($tender['joined_by_me']): ?>
                        <span class="tb-status-badge <?= str_replace('_', '-', htmlspecialchars($myStatus)) ?>">
                            <i class="fa-solid <?= htmlspecialchars($statusIcons[$myStatus] ?? 'fa-circle') ?>"></i>
                            <?= htmlspecialchars($statusLabels[$myStatus] ?? ucfirst(str_replace('_', ' ', $myStatus))) ?>
                        </span>
                        <?php elseif ($isAssignedToAnotherStaff): ?>
                        <span style="color:#047857;font-weight:800;"><i class="fa-solid fa-user-check"></i> Assigned</span>
                        <?php elseif ($isClosed): ?>
                        <span style="color:#B91C1C;font-weight:800;">Closed</span>
                        <?php else: ?>
                        <span style="color:#2563EB;font-weight:800;">Open</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Actions">
                        <div class="tb-actions">
                            <?php if ($canJoin && !$tender['joined_by_me'] && !$isClosed && !$isAssignedToAnotherStaff): ?>
                            <button class="btn btn-primary" type="button" onclick="toggleTenderInterest(<?= (int)$tender['id'] ?>, this, false)" style="border:0;background:#2563EB;color:#fff;">
                                <i class="fa-solid fa-user-plus"></i> Join
                            </button>
                            <?php endif; ?>
                            <?php if ($canJoin && $tender['joined_by_me'] && $myStatus === 'in_progress'): ?>
                            <button class="btn btn-primary" type="button" onclick="submitAssignedTender(<?= (int)$tender['id'] ?>, this)" <?= !$tender['user_pricing_complete'] ? 'disabled title="Complete Project Pricing before submitting"' : '' ?> style="border:0;background:#2563EB;color:#fff;">
                                <i class="fa-solid fa-paper-plane"></i> Submit
                            </button>
                            <button class="btn btn-secondary" type="button" onclick="toggleTenderInterest(<?= (int)$tender['id'] ?>, this, true)" style="border:1px solid #FECACA;background:#fff;color:#DC2626;">
                                <i class="fa-solid fa-user-minus"></i> Leave
                            </button>
                            <?php endif; ?>
                            <?php if ($canJoin && $tender['joined_by_me'] && $myStatus === 'submitted'): ?>
                            <button class="btn" type="button" onclick="setTenderResult(<?= (int)$tender['id'] ?>, 'won', this)" style="border:0;background:#059669;color:#fff;">
                                <i class="fa-solid fa-trophy"></i> Won
                            </button>
                            <button class="btn" type="button" onclick="setTenderResult(<?= (int)$tender['id'] ?>, 'lost', this)" style="border:1px solid #FECACA;background:#fff;color:#DC2626;">
                                <i class="fa-solid fa-circle-xmark"></i> Lost
                            </button>
                            <?php endif; ?>
                            <?php if ($tender['joined_by_me'] && in_array($myStatus, ['won', 'lost'], true)): ?>
                            <span class="tb-lock-note" style="margin-top:0;"><i class="fa-solid fa-lock"></i> Final</span>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                            <button class="btn btn-secondary" type="button" onclick='openTenderBoardModal(<?= json_encode(['id'=>(int)$tender['id'],'qt_number'=>$tender['qt_number'],'title'=>$tender['title'],'closing_date'=>$tender['closing_date']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit" style="border:1px solid var(--border-color);background:transparent;color:var(--text-dark);"><i class="fa-solid fa-pen"></i></button>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button class="btn btn-danger" type="button" onclick="deleteTenderBoardItem(<?= (int)$tender['id'] ?>)" title="Delete" style="border:0;background:#EF4444;color:#fff;"><i class="fa-solid fa-trash"></i></button>
                            <?php endif; ?>
                            <?php if ((!$canEdit && !$canDelete) && ($isClosed || $isAssignedToAnotherStaff) && !$tender['joined_by_me']): ?>
                            <span class="tb-meta">No action available</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <tr id="tenderBoardNoResults" style="<?= $tenders ? 'display:none;' : '' ?>">
                    <td class="tb-empty-cell" colspan="6" style="text-align:center;padding:44px 20px;color:var(--text-muted);">
                        <i class="fa-regular fa-folder-open" style="display:block;font-size:32px;color:#CBD5E1;margin-bottom:10px;"></i>
                        <span id="tenderBoardNoResultsText"><?= $tenders ? 'No tenders found in this section.' : 'No new tenders have been posted yet.' ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php if ($tenders): ?><div class="tb-pagination" id="tenderBoardPagination"></div><?php endif; ?>
</div>

<?php if ($canCreate || $canEdit): ?>
<div class="modal-overlay" id="tenderBoardModal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title" id="tenderBoardModalTitle"><i class="fa-solid fa-briefcase" style="color:#2563EB;"></i> Add Tender Opportunity</h3>
            <button class="modal-close" onclick="App.closeModal('tenderBoardModal')">&times;</button>
        </div>
        <form id="tenderBoardForm" onsubmit="saveTenderBoardRecord(event)">
            <input type="hidden" name="id" id="tenderBoardId" value="0">
            <div class="modal-body" style="padding:20px;">
                <div class="tb-field">
                    <label class="tb-label" for="tenderBoardQt">QT Number *</label>
                    <input class="tb-control" type="text" name="qt_number" id="tenderBoardQt" maxlength="100" required placeholder="e.g. QT-2026-001">
                </div>
                <div class="tb-field">
                    <label class="tb-label" for="tenderBoardTitle">Tender Title *</label>
                    <textarea class="tb-control" name="title" id="tenderBoardTitle" maxlength="255" rows="4" required placeholder="Enter the full tender title"></textarea>
                </div>
                <div class="tb-field">
                    <label class="tb-label" for="tenderBoardClosingDate">Closing Date *</label>
                    <input class="tb-control" type="date" name="closing_date" id="tenderBoardClosingDate" required>
                </div>
            </div>
            <div class="modal-footer" style="padding:16px 20px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('tenderBoardModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="tenderBoardSubmit"><i class="fa-solid fa-check"></i> Save Tender</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canJoin): ?>
<div class="modal-overlay" id="projectPricingModal">
    <div class="modal-box" style="max-width:720px;">
        <div class="modal-header">
            <h3 class="modal-title" id="projectPricingModalTitle"><i class="fa-solid fa-calculator" style="color:#059669;"></i> Project Pricing</h3>
            <button class="modal-close" onclick="closeProjectPricingModal()">&times;</button>
        </div>
        <form id="projectPricingForm" enctype="multipart/form-data" onsubmit="submitProjectPricing(event)">
            <input type="hidden" name="id" id="projectPricingOpportunityId">
            <div class="modal-body" style="padding:20px;">
                <p id="projectPricingTitle" style="margin:0 0 18px;color:var(--text-muted);font-size:13px;line-height:1.5;"></p>
                <div class="tb-pricing-readonly" id="projectPricingReadOnlyNotice">
                    <i class="fa-solid fa-lock"></i> This pricing was locked when the tender was submitted. You can review the details and download its attachment when available, but no changes can be made.
                </div>
                <div class="tb-pricing-grid">
                    <div class="tb-field">
                        <label class="tb-label" for="projectIndicativePrice">Indicative Price (RM) *</label>
                        <input class="tb-control" type="number" name="indicative_price" id="projectIndicativePrice" min="0" step="0.01" required placeholder="0.00">
                    </div>
                    <div class="tb-field">
                        <label class="tb-label" for="projectCost">Cost (RM) *</label>
                        <input class="tb-control" type="number" name="cost" id="projectCost" min="0" step="0.01" required placeholder="0.00">
                    </div>
                    <div class="tb-field">
                        <label class="tb-label" for="projectSellingPrice">Selling Price (RM) *</label>
                        <input class="tb-control" type="number" name="selling_price" id="projectSellingPrice" min="0" step="0.01" required placeholder="0.00">
                    </div>
                </div>
                <div class="tb-pricing-note" id="projectMarginPreview">
                    Gross margin: <strong>RM 0.00</strong>
                </div>
                <div class="tb-field" id="projectPricingAttachmentField" style="margin-top:18px;">
                    <label class="tb-label" for="projectPricingAttachment">Pricing File (Optional)</label>
                    <input class="tb-control" type="file" name="pricing_attachment" id="projectPricingAttachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.zip,.jpg,.jpeg,.png">
                    <small style="display:block;margin-top:7px;color:var(--text-muted);">PDF, Word, Excel, CSV, ZIP, JPG or PNG. Maximum 10 MB.</small>
                </div>
                <div id="projectPricingExistingFile" style="display:none;margin-top:10px;padding:11px 12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-primary);font-size:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                        <a id="projectPricingDownload" href="#" style="color:#2563EB;font-weight:700;text-decoration:none;"><i class="fa-solid fa-paperclip"></i> <span></span></a>
                        <label id="projectPricingRemoveLabel" style="display:flex;align-items:center;gap:7px;color:#B91C1C;cursor:pointer;">
                            <input type="checkbox" name="remove_attachment" value="1" id="projectPricingRemoveAttachment"> Remove file
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding:16px 20px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" id="projectPricingCloseButton" onclick="closeProjectPricingModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="projectPricingSubmit" style="background:#059669;border-color:#059669;"><i class="fa-solid fa-floppy-disk"></i> Save Pricing</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openTenderBoardModal(data = null) {
    const form = document.getElementById('tenderBoardForm');
    form.reset();
    document.getElementById('tenderBoardId').value = data?.id || 0;
    document.getElementById('tenderBoardQt').value = data?.qt_number || '';
    document.getElementById('tenderBoardTitle').value = data?.title || '';
    document.getElementById('tenderBoardClosingDate').value = data?.closing_date || '';
    document.getElementById('tenderBoardModalTitle').innerHTML = data
        ? '<i class="fa-solid fa-pen" style="color:#2563EB;"></i> Edit Tender'
        : '<i class="fa-solid fa-briefcase" style="color:#2563EB;"></i> Add Tender Opportunity';
    App.openModal('tenderBoardModal');
}

async function saveTenderBoardRecord(event) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const id = Number(data.get('id') || 0);
    const action = id ? 'update_tender_board' : 'add_tender_board';
    const result = await App.post(`index.php?action=${action}`, data, document.getElementById('tenderBoardSubmit'));
    if (result?.status === 'success') setTimeout(() => location.reload(), 500);
}

async function toggleTenderInterest(id, button, isJoined) {
    if (isJoined && !await App.confirm('Leave Tender', 'Leave this tender? Your linked Tender KPI record and pricing data will also be removed.')) return;
    const data = new FormData(); data.append('id', id);
    const result = await App.post('index.php?action=toggle_tender_board_interest', data, button);
    if (result?.status !== 'success') return;
    if (result.data?.joined && result.data?.needs_pricing) {
        const card = button.closest('.tender-board-item');
        const title = card?.querySelector('.tb-tender-title')?.textContent?.trim() || 'Selected tender';
        sessionStorage.setItem('tenderBoardActiveFilter', 'mine');
        projectPricingRequiresReload = true;
        openProjectPricingModal({ id, title, indicative_price: '', cost: '', selling_price: '', attachment_name: '' });
        return;
    }
    setTimeout(() => location.reload(), 350);
}

async function submitAssignedTender(id, button) {
    if (!await App.confirm('Submit Tender', 'Submit this tender? Project Pricing will be locked after submission.')) return;
    const data = new FormData();
    data.append('id', id);
    const result = await App.post('index.php?action=submit_tender_board', data, button);
    if (result?.status === 'success') setTimeout(() => location.reload(), 350);
}

async function setTenderResult(id, resultStatus, button) {
    const resultLabel = resultStatus === 'won' ? 'Won' : 'Lost';
    if (!await App.confirm(`Mark Tender as ${resultLabel}`, `Confirm this tender is ${resultLabel}? This final status cannot be changed.`)) return;
    const data = new FormData();
    data.append('id', id);
    data.append('result', resultStatus);
    const result = await App.post('index.php?action=set_tender_board_result', data, button);
    if (result?.status === 'success') setTimeout(() => location.reload(), 350);
}

let projectPricingRequiresReload = false;

function openProjectPricingModal(data) {
    const form = document.getElementById('projectPricingForm');
    if (!form) return;
    const readOnly = Boolean(data.readonly);
    form.reset();
    form.dataset.readonly = readOnly ? '1' : '0';
    document.getElementById('projectPricingOpportunityId').value = data.id;
    document.getElementById('projectPricingTitle').textContent = data.title || 'Selected tender';
    document.getElementById('projectPricingModalTitle').innerHTML = readOnly
        ? '<i class="fa-solid fa-eye" style="color:#2563EB;"></i> View Project Pricing'
        : '<i class="fa-solid fa-calculator" style="color:#059669;"></i> Project Pricing';

    const pricingFields = [
        document.getElementById('projectIndicativePrice'),
        document.getElementById('projectCost'),
        document.getElementById('projectSellingPrice'),
    ];
    pricingFields[0].value = data.indicative_price ?? '';
    pricingFields[1].value = data.cost ?? '';
    pricingFields[2].value = data.selling_price ?? '';
    pricingFields.forEach(field => field.readOnly = readOnly);

    document.getElementById('projectPricingReadOnlyNotice').style.display = readOnly ? 'block' : 'none';
    document.getElementById('projectPricingAttachmentField').style.display = readOnly ? 'none' : '';
    document.getElementById('projectPricingAttachment').disabled = readOnly;
    document.getElementById('projectPricingSubmit').style.display = readOnly ? 'none' : '';
    document.getElementById('projectPricingCloseButton').textContent = readOnly ? 'Close' : 'Cancel';

    const existingFile = document.getElementById('projectPricingExistingFile');
    const download = document.getElementById('projectPricingDownload');
    const remove = document.getElementById('projectPricingRemoveAttachment');
    const removeLabel = document.getElementById('projectPricingRemoveLabel');
    remove.checked = false;
    remove.disabled = readOnly;
    removeLabel.style.display = readOnly ? 'none' : 'flex';
    if (data.attachment_name) {
        existingFile.style.display = '';
        download.href = `index.php?action=download_tender_pricing_file&id=${encodeURIComponent(data.id)}`;
        download.querySelector('span').textContent = data.attachment_name;
    } else {
        existingFile.style.display = 'none';
        download.href = '#';
        download.querySelector('span').textContent = '';
    }
    updateProjectMarginPreview();
    App.openModal('projectPricingModal');
    if (!readOnly) setTimeout(() => document.getElementById('projectIndicativePrice').focus(), 100);
}

function closeProjectPricingModal() {
    App.closeModal('projectPricingModal');
    if (projectPricingRequiresReload) location.reload();
}

function updateProjectMarginPreview() {
    const cost = Number(document.getElementById('projectCost')?.value || 0);
    const selling = Number(document.getElementById('projectSellingPrice')?.value || 0);
    const margin = selling - cost;
    const preview = document.getElementById('projectMarginPreview');
    if (!preview) return;
    preview.innerHTML = `Gross margin: <strong>RM ${margin.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>`;
    preview.style.background = margin < 0 ? '#FEF2F2' : '#F0FDF4';
    preview.style.color = margin < 0 ? '#B91C1C' : '#166534';
}

async function submitProjectPricing(event) {
    event.preventDefault();
    if (event.currentTarget.dataset.readonly === '1') return;
    const result = await App.post('index.php?action=update_tender_board_pricing', new FormData(event.currentTarget), document.getElementById('projectPricingSubmit'));
    if (result?.status === 'success') {
        sessionStorage.setItem('tenderBoardActiveFilter', 'mine');
        setTimeout(() => location.reload(), 400);
    }
}

document.getElementById('projectCost')?.addEventListener('input', updateProjectMarginPreview);
document.getElementById('projectSellingPrice')?.addEventListener('input', updateProjectMarginPreview);

async function deleteTenderBoardItem(id) {
    if (!await App.confirm('Delete Tender', 'Delete this tender and its assigned staff record?')) return;
    const data = new FormData(); data.append('id', id);
    const result = await App.post('index.php?action=delete_tender_board', data);
    if (result?.status === 'success') setTimeout(() => location.reload(), 400);
}

const TENDER_BOARD_PAGE_SIZE = 10;
const savedTenderBoardFilter = sessionStorage.getItem('tenderBoardActiveFilter');
let activeTenderBoardFilter = ['open', 'mine', 'closed'].includes(savedTenderBoardFilter)
    ? savedTenderBoardFilter
    : 'open';
let tenderBoardPage = 1;

function setTenderBoardFilter(filter, button) {
    activeTenderBoardFilter = filter;
    sessionStorage.setItem('tenderBoardActiveFilter', filter);
    tenderBoardPage = 1;
    document.querySelectorAll('.tb-tab').forEach(tab => {
        const isActive = tab === button;
        tab.classList.toggle('active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
    filterTenderBoard(false);
}

function changeTenderBoardPage(page) {
    tenderBoardPage = page;
    filterTenderBoard(false);
    document.querySelector('.tb-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderTenderBoardPagination(totalItems) {
    const pagination = document.getElementById('tenderBoardPagination');
    if (!pagination) return;
    const totalPages = Math.ceil(totalItems / TENDER_BOARD_PAGE_SIZE);
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        pagination.style.display = 'none';
        return;
    }
    pagination.style.display = 'flex';
    pagination.innerHTML = `
        <button type="button" class="tb-page-button" ${tenderBoardPage === 1 ? 'disabled' : ''} onclick="changeTenderBoardPage(${tenderBoardPage - 1})" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></button>
        <span class="tb-page-label">Page ${tenderBoardPage} of ${totalPages}</span>
        <button type="button" class="tb-page-button" ${tenderBoardPage === totalPages ? 'disabled' : ''} onclick="changeTenderBoardPage(${tenderBoardPage + 1})" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></button>`;
}

function filterTenderBoard(resetPage = true) {
    if (resetPage) tenderBoardPage = 1;
    const query = document.getElementById('tenderBoardSearch').value.trim().toLowerCase();
    const allItems = Array.from(document.querySelectorAll('.tender-board-item'));
    const matches = allItems.filter(item => {
        const matchesTab = activeTenderBoardFilter === 'mine'
            ? item.dataset.joined === '1'
            : activeTenderBoardFilter === 'open'
                ? item.dataset.status === 'open' && item.dataset.assigned === '0'
                : item.dataset.status === 'closed';
        return matchesTab && item.dataset.search.includes(query);
    });
    const totalPages = Math.max(1, Math.ceil(matches.length / TENDER_BOARD_PAGE_SIZE));
    tenderBoardPage = Math.min(tenderBoardPage, totalPages);
    const start = (tenderBoardPage - 1) * TENDER_BOARD_PAGE_SIZE;
    const visibleItems = new Set(matches.slice(start, start + TENDER_BOARD_PAGE_SIZE));
    allItems.forEach(item => { item.style.display = visibleItems.has(item) ? '' : 'none'; });

    const emptyState = document.getElementById('tenderBoardNoResults');
    if (emptyState) {
        emptyState.style.display = matches.length ? 'none' : '';
        const labels = { open: 'No open tenders found.', mine: 'You have not joined any tenders yet.', closed: 'No closed tenders found.' };
        document.getElementById('tenderBoardNoResultsText').textContent = query ? 'No tenders match your search.' : labels[activeTenderBoardFilter];
    }
    renderTenderBoardPagination(matches.length);
}

const initialTenderBoardTab = document.querySelector(`.tb-tab[data-filter="${activeTenderBoardFilter}"]`);
if (initialTenderBoardTab) setTenderBoardFilter(activeTenderBoardFilter, initialTenderBoardTab);
else filterTenderBoard(false);
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
