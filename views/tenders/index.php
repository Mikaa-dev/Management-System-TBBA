<?php
/**
 * Tender KPI Tracker and Tender Management
 * Company: The Bridge Business Alliance (TBBA)
 */
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helper.php';
require_once __DIR__ . '/../../models/Tender.php';

Auth::requireLogin();
$currentUser = Auth::user();
$curMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');

require_once __DIR__ . '/../../models/User.php';

// Load tender records and KPI data.
if (Auth::hasPermission('tenders', 'edit')) {
    $tenders = Tender::getAll($curMonth);
    
    $allUsers = User::getAll();
    $staffGroups = [];
    foreach ($allUsers as $u) {
        // Include all users in the KPI list.
        $uTenders = Tender::getByUser($u['id'], $curMonth);
        $uKpi = Tender::getKpi($u['id'], $curMonth);
        $staffGroups[] = [
            'user'    => $u,
            'tenders' => $uTenders,
            'kpi'     => $uKpi
        ];
    }
    // Sort alphabetically by user name.
    usort($staffGroups, function($a, $b) {
        return strcmp($a['user']['name'], $b['user']['name']);
    });
} else {
    $tenders = Tender::getByUser($currentUser['id'], $curMonth);
    $myKpi   = Tender::getKpi($currentUser['id'], $curMonth);
    $staffGroups = [
        [
            'user'    => $currentUser,
            'tenders' => $tenders,
            'kpi'     => $myKpi
        ]
    ];
}

$selectedStaffId = Auth::hasPermission('tenders', 'edit') ? max(0, (int)($_GET['staff'] ?? 0)) : (int)$currentUser['id'];
$visibleStaffGroups = $staffGroups;
if ($selectedStaffId > 0) {
    $visibleStaffGroups = array_values(array_filter(
        $staffGroups,
        fn(array $group): bool => (int)$group['user']['id'] === $selectedStaffId
    ));
}
$visibleTenderRecords = [];
$visibleStaffWithRecords = [];
foreach ($visibleStaffGroups as $group) {
    foreach ($group['tenders'] as $record) {
        $visibleStaffWithRecords[(int)$group['user']['id']] = true;
        $visibleTenderRecords[] = [
            'record' => $record,
            'user' => $group['user'],
            'kpi' => $group['kpi'],
        ];
    }
}
$selectedStaffGroup = $selectedStaffId > 0
    ? ($visibleStaffGroups[0] ?? null)
    : null;

$tenderManagementSection = 'kpi';
$pageTitle = "Tender Management";
$pageJs = "tenders.js";
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<style>
    .table th {
        background-color: #FFFFFF !important;
        color: #475569 !important;
        font-weight: 700 !important;
        font-size: 11px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        padding: 16px 20px !important;
        border-bottom: 2px solid #E2E8F0 !important;
        border-top: none !important;
    }
    .table td {
        padding: 16px 20px !important;
        border-bottom: 1px solid #F1F5F9 !important;
        font-size: 13px !important;
        color: #334155 !important;
        vertical-align: middle !important;
    }
    .table tbody tr:hover td {
        background-color: #F8FAFC !important;
    }
    .badge-status {
        border-radius: 50px !important;
        padding: 5px 14px !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
    }
    .btn-pill {
        border-radius: 50px !important;
        padding: 6px 14px !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        transition: all 0.2s ease !important;
        text-decoration: none !important;
    }
    .btn-pill:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1) !important;
    }
    .kpi-control-panel { margin-bottom: 20px; overflow: hidden; border: 1px solid #E2E8F0; border-radius: 16px; background: #FFFFFF; box-shadow: 0 5px 16px rgba(15,23,42,.05); }
    .kpi-control-main { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 18px 20px; }
    .kpi-control-title h3 { margin: 0 0 4px; color: #0F172A; font-size: 18px; font-weight: 800; }
    .kpi-control-title p { margin: 0; color: #64748B; font-size: 11px; }
    .kpi-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
    .kpi-action { min-height: 38px; padding: 8px 13px; border-radius: 9px; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; gap: 7px; cursor: pointer; }
    .kpi-action.secondary { border: 1px solid #CBD5E1; background: #FFFFFF; color: #1E3A8A; }
    .kpi-action.primary { border: 1px solid #2563EB; background: #2563EB; color: #FFFFFF; }
    .kpi-filter-bar { display: flex; align-items: end; justify-content: space-between; gap: 14px; padding: 13px 20px; border-top: 1px solid #E2E8F0; background: #F8FAFC; }
    .kpi-filter-group { display: flex; align-items: end; gap: 8px; flex-wrap: wrap; }
    .kpi-filter-field { display: grid; gap: 5px; }
    .kpi-filter-label { color: #64748B; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .55px; }
    .kpi-filter-select { min-height: 38px; padding: 8px 32px 8px 11px; border: 1px solid #CBD5E1; border-radius: 9px; background: #FFFFFF; color: #1E293B; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
    .kpi-month-nav { width: 38px; height: 38px; padding: 0; border: 1px solid #CBD5E1; border-radius: 9px; background: #FFFFFF; color: #334155; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
    .kpi-summary { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .kpi-summary-pill { padding: 6px 10px; border-radius: 999px; background: #E2E8F0; color: #334155; font-size: 10px; font-weight: 800; }
    .kpi-summary-pill.target { background: #EFF6FF; color: #1D4ED8; }
    .kpi-records-card { overflow: hidden; border: 1px solid #E2E8F0; border-radius: 16px; background: #FFFFFF; box-shadow: 0 5px 16px rgba(15,23,42,.05); }
    .kpi-records-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 16px 20px; border-bottom: 1px solid #E2E8F0; }
    .kpi-records-head h3 { margin: 0; color: #0F172A; font-size: 14px; font-weight: 800; }
    .kpi-staff-cell { display: flex; align-items: center; gap: 9px; min-width: 170px; }
    .kpi-staff-avatar { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; flex: 0 0 32px; border-radius: 50%; background: #0F172A; color: #FFFFFF; font-size: 10px; font-weight: 800; }
    .kpi-staff-name { display: block; color: #0F172A; font-size: 11px; font-weight: 800; line-height: 1.3; }
    .kpi-staff-target { display: block; margin-top: 2px; color: #64748B; font-size: 9px; font-weight: 700; }
    @media (max-width: 760px) {
        .kpi-control-main, .kpi-filter-bar { align-items: stretch; flex-direction: column; padding: 15px; }
        .kpi-actions { justify-content: flex-start; }
        .kpi-action { flex: 1; }
        .kpi-filter-group { width: 100%; }
        .kpi-filter-field { flex: 1; min-width: 150px; }
        .kpi-filter-select { width: 100%; }
        .kpi-summary { width: 100%; }
        .kpi-records-head { align-items: flex-start; flex-direction: column; padding: 14px 15px; }
        .module-data-card .card-header { padding: 16px !important; align-items: flex-start !important; }
        .module-data-card .card-header > div:first-child { min-width: 0; align-items: flex-start !important; }
        .module-data-card .card-header > div:first-child > div:last-child { min-width: 0; }
        .module-data-card .card-header > div:first-child > div:last-child > div { flex-wrap: wrap; }
        .module-data-card .card-header > div:first-child strong { overflow-wrap: anywhere; }
        .module-data-card .card-header > div:last-child { width: 100%; text-align: left !important; padding-left: 62px; }
        .module-data-card .table-responsive { overflow: visible !important; }
        .module-data-card table, .module-data-card tbody { display: block; width: 100%; }
        .module-data-card thead { display: none; }
        .module-data-card tbody { padding: 12px; background: #F8FAFC; }
        .module-data-card tbody tr { display: block; margin-bottom: 12px; overflow: hidden; border: 1px solid #E2E8F0; border-radius: 12px; background: #FFFFFF; }
        .module-data-card tbody tr:last-child { margin-bottom: 0; }
        .module-data-card .table td { display: grid; grid-template-columns: 105px minmax(0,1fr); gap: 10px; align-items: start; width: auto !important; padding: 10px 12px !important; }
        .module-data-card .table td::before { content: attr(data-label); color: #64748B; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .45px; }
        .module-data-card .table td.kpi-primary-cell { display: block; padding: 14px 12px !important; }
        .module-data-card .table td.kpi-primary-cell::before, .module-data-card .table td.kpi-empty-cell::before { display: none; }
        .module-data-card .table td.kpi-empty-cell { display: block; }
        .module-data-card .badge-status { white-space: normal; }
        .kpi-staff-cell { min-width: 0; }
        .module-clean-modal .modal-box { width: calc(100% - 20px); max-height: calc(100dvh - 20px); }
        .module-clean-modal .modal-body > div[style*="display: flex"] { flex-direction: column; gap: 0 !important; }
    }
</style>

<?php include __DIR__ . '/../tender_board/workspace_nav.php'; ?>

<!-- Section Title & Month Filter -->
<?php
$prevMonth = date('Y-m', strtotime($curMonth . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($curMonth . '-01 +1 month'));
$monthsList = [];
$startMonthDt = strtotime('-12 months', strtotime(date('Y-m-01')));
$endMonthDt = strtotime('+12 months', strtotime(date('Y-m-01')));
for ($m = $startMonthDt; $m <= $endMonthDt; $m = strtotime('+1 month', $m)) {
    $mVal = date('Y-m', $m);
    $mLabel = date('F Y', $m);
    if ($mVal === date('Y-m')) {
        $mLabel .= ' (Current)';
    }
    $monthsList[$mVal] = $mLabel;
}
if (!isset($monthsList[$curMonth])) {
    $monthsList[$curMonth] = date('F Y', strtotime($curMonth . '-01'));
}
?>
<section class="kpi-control-panel" aria-label="KPI controls">
    <div class="kpi-control-main">
        <div class="kpi-control-title">
            <h3><i class="fa-solid fa-chart-column" style="color:#2563EB;margin-right:7px;"></i>Monthly Tender Records</h3>
            <p><?= $selectedStaffGroup ? htmlspecialchars($selectedStaffGroup['user']['name']) . ' · ' : '' ?><?= date('F Y', strtotime($curMonth . '-01')) ?></p>
        </div>
        <div class="kpi-actions">
            <?php if (!empty($canReport)): ?>
            <button class="kpi-action secondary" type="button" onclick="openTenderReportModal()"><i class="fa-solid fa-file-lines"></i> My Monthly Report</button>
            <?php endif; ?>
            <button class="kpi-action primary" type="button" onclick="App.openModal('addTenderModal')"><i class="fa-solid fa-plus"></i> Add Manual Record</button>
        </div>
    </div>
    <div class="kpi-filter-bar">
        <div class="kpi-filter-group">
            <a class="kpi-month-nav" href="index.php?page=tender_board&amp;section=kpi&amp;month=<?= $prevMonth ?>&amp;staff=<?= $selectedStaffId ?>" title="Previous month"><i class="fa-solid fa-chevron-left"></i></a>
            <label class="kpi-filter-field">
                <span class="kpi-filter-label">KPI Month</span>
                <select class="kpi-filter-select" onchange="location.href='index.php?page=tender_board&section=kpi&staff=<?= $selectedStaffId ?>&month=' + this.value;">
                    <?php foreach ($monthsList as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $val === $curMonth ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <a class="kpi-month-nav" href="index.php?page=tender_board&amp;section=kpi&amp;month=<?= $nextMonth ?>&amp;staff=<?= $selectedStaffId ?>" title="Next month"><i class="fa-solid fa-chevron-right"></i></a>
            <?php if (Auth::hasPermission('tenders', 'edit')): ?>
            <label class="kpi-filter-field">
                <span class="kpi-filter-label">Staff Member</span>
                <select class="kpi-filter-select" onchange="location.href='index.php?page=tender_board&section=kpi&month=<?= $curMonth ?>&staff=' + this.value;">
                    <option value="0" <?= $selectedStaffId === 0 ? 'selected' : '' ?>>All Staff</option>
                    <?php foreach ($staffGroups as $group): ?>
                    <option value="<?= (int)$group['user']['id'] ?>" <?= $selectedStaffId === (int)$group['user']['id'] ? 'selected' : '' ?>><?= htmlspecialchars($group['user']['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
        </div>
        <div class="kpi-summary">
            <span class="kpi-summary-pill" id="tenderCountBadge"><?= count($visibleTenderRecords) ?> Record<?= count($visibleTenderRecords) === 1 ? '' : 's' ?></span>
            <?php if ($selectedStaffGroup): ?>
            <span class="kpi-summary-pill target">KPI <?= (int)$selectedStaffGroup['kpi']['count'] ?> / 4 (<?= (int)$selectedStaffGroup['kpi']['percentage'] ?>%)</span>
            <?php elseif (Auth::hasPermission('tenders', 'edit')): ?>
            <span class="kpi-summary-pill"><?= count($visibleStaffWithRecords) ?> Staff with Records</span>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="kpi-records-card module-data-card">
    <div class="kpi-records-head">
        <div>
            <h3><?= $selectedStaffGroup ? htmlspecialchars($selectedStaffGroup['user']['name']) . ' — Tender Records' : 'All Staff Tender Records' ?></h3>
            <span style="display:block;margin-top:3px;color:#64748B;font-size:10px;">Status changes are managed from My Tenders. Won and Lost records support post-mortem analysis here.</span>
        </div>
        <?php if ($selectedStaffId !== (int)$currentUser['id']): ?>
        <a href="index.php?page=tender_board&amp;section=kpi&amp;month=<?= $curMonth ?>&amp;staff=<?= (int)$currentUser['id'] ?>" style="color:#2563EB;font-size:10px;font-weight:800;text-decoration:none;"><i class="fa-solid fa-user"></i> Show My Records</a>
        <?php endif; ?>
    </div>
    <div class="table-responsive" style="border:0;border-radius:0;">
        <table class="table datatable" id="tendersTable" style="margin:0;">
            <thead>
                <tr>
                    <?php if (Auth::hasPermission('tenders', 'edit')): ?><th>Staff</th><?php endif; ?>
                    <th>Project / Tender</th>
                    <th>Client / Department</th>
                    <th>Value (RM)</th>
                    <th>Closing Date</th>
                    <th>Status / Review</th>
                </tr>
            </thead>
            <tbody id="tendersTableBody">
                <?php if (!$visibleTenderRecords): ?>
                <tr id="emptyTenderRow">
                    <td class="kpi-empty-cell" colspan="<?= Auth::hasPermission('tenders', 'edit') ? 6 : 5 ?>" style="text-align:center;color:#64748B;padding:44px 20px;">
                        <i class="fa-regular fa-folder-open" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4;"></i>
                        No tender records found for the selected month<?= $selectedStaffGroup ? ' and staff member' : '' ?>.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($visibleTenderRecords as $idx => $item): ?>
                <?php
                    $t = $item['record'];
                    $u = $item['user'];
                    $uKpi = $item['kpi'];
                    $statusMap = [
                        'in_progress' => ['#FEF3C7', '#B45309', '#FDE68A', 'In Progress', 'fa-spinner'],
                        'submitted' => ['#EFF6FF', '#2563EB', '#BFDBFE', 'Submitted', 'fa-paper-plane'],
                        'won' => ['#ECFDF5', '#059669', '#A7F3D0', 'Won / Successful', 'fa-trophy'],
                        'lost' => ['#FEF2F2', '#DC2626', '#FECACA', 'Lost', 'fa-xmark'],
                    ];
                    $st = $statusMap[$t['status']] ?? ['#F1F5F9', '#475569', '#CBD5E1', ucfirst($t['status']), 'fa-circle'];
                ?>
                <tr id="tender-row-<?= $t['id'] ?>">
                    <?php if (Auth::hasPermission('tenders', 'edit')): ?>
                    <td data-label="Staff">
                        <div class="kpi-staff-cell">
                            <span class="kpi-staff-avatar"><?= htmlspecialchars(strtoupper(substr($u['name'], 0, 2))) ?></span>
                            <span><span class="kpi-staff-name"><?= htmlspecialchars($u['name']) ?></span><span class="kpi-staff-target">KPI <?= (int)$uKpi['count'] ?>/4 · <?= (int)$uKpi['percentage'] ?>%</span></span>
                        </div>
                    </td>
                    <?php endif; ?>
                    <td class="kpi-primary-cell" data-label="Tender">
                        <strong style="display:block;color:#0F172A;font-weight:800;"><?= htmlspecialchars($t['project_name']) ?></strong>
                        <?php if (!empty($t['attachment'])): ?><a href="<?= Helper::url('index.php?action=download_attachment&type=tender&id=' . (int)$t['id']) ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;margin-top:5px;color:#2563EB;font-size:10px;font-weight:700;text-decoration:none;"><i class="fa-solid fa-paperclip"></i> View File</a><?php endif; ?>
                    </td>
                    <td data-label="Client / Department"><?= htmlspecialchars($t['client_name']) ?></td>
                    <td data-label="Value (RM)" style="font-weight:800;color:#1E3A8A;">
                        <?php if (!empty($t['tender_opportunity_id']) && (float)$t['project_value'] <= 0): ?>
                        <span style="color:#B45309;">Value Pending</span>
                        <?php else: ?><?= Helper::rm($t['project_value']) ?><?php endif; ?>
                    </td>
                    <td data-label="Closing Date" style="font-weight:700;white-space:nowrap;"><?= Helper::date($t['closing_date'], 'd M Y') ?></td>
                    <td data-label="Status / Review">
                        <span class="badge-status" style="background:<?= $st[0] ?>;color:<?= $st[1] ?>;border:1px solid <?= $st[2] ?>;"><i class="fa-solid <?= $st[4] ?>"></i><?= $st[3] ?></span>
                        <?php if (in_array($t['status'], ['won', 'lost'], true)): ?>
                        <button type="button" style="display:flex;margin-top:6px;padding:5px 9px;border-radius:8px;background:#FFFFFF;border:1px solid <?= $st[2] ?>;color:<?= $st[1] ?>;font-size:10px;font-weight:800;cursor:pointer;" onclick="openPostMortemModal(<?= $t['id'] ?>, '<?= $t['status'] ?>', '<?= htmlspecialchars(addslashes($t['project_name'])) ?>', '<?= htmlspecialchars(addslashes($t['post_mortem_factors'] ?? '')) ?>', '<?= htmlspecialchars(addslashes(str_replace(["\r\n", "\r", "\n"], "\\n", $t['post_mortem_summary'] ?? ''))) ?>', <?= (Auth::hasPermission('tenders', 'edit') || (int)$t['user_id'] === (int)Auth::id()) ? 'true' : 'false' ?>, null, '<?= htmlspecialchars(addslashes(str_replace(["\r\n", "\r", "\n"], "\\n", $t['swot_s'] ?? ''))) ?>', '<?= htmlspecialchars(addslashes(str_replace(["\r\n", "\r", "\n"], "\\n", $t['swot_w'] ?? ''))) ?>', '<?= htmlspecialchars(addslashes(str_replace(["\r\n", "\r", "\n"], "\\n", $t['swot_o'] ?? ''))) ?>', '<?= htmlspecialchars(addslashes(str_replace(["\r\n", "\r", "\n"], "\\n", $t['swot_t'] ?? ''))) ?>')"><i class="fa-solid fa-clipboard-list" style="margin-right:5px;"></i>Post Mortem</button>
                        <?php elseif ((int)$t['user_id'] === (int)Auth::id()): ?>
                        <a href="index.php?page=tender_board" style="display:inline-flex;margin-top:6px;color:#2563EB;font-size:10px;font-weight:700;text-decoration:none;">Update in My Tenders <i class="fa-solid fa-arrow-right" style="margin-left:4px;"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- New tender registration modal (AJAX form) -->
<div class="modal-overlay module-clean-modal" id="addTenderModal">
    <div class="modal-box" style="max-width: 620px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-briefcase" style="color: var(--accent-blue);"></i> Submit New Tender / Project
            </h3>
            <button class="modal-close" onclick="App.closeModal('addTenderModal')">&times;</button>
        </div>

        <form id="addTenderForm" enctype="multipart/form-data" onsubmit="return false;">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
                
                <div class="form-group">
                    <label class="form-label">Project / Tender Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="project_name" class="form-control" placeholder="e.g.: Core Database Upgrade" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Client / Ministry / Company <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="client_name" class="form-control" placeholder="e.g.: Ministry of Finance" required>
                </div>

                <div style="display: flex; gap: 16px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Project Value (RM) <span style="color: var(--danger);">*</span></label>
                        <input type="number" step="0.01" name="project_value" class="form-control" placeholder="e.g.: 500000.00" required>
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Tender Closing Date <span style="color: var(--danger);">*</span></label>
                        <input type="date" name="closing_date" class="form-control" required>
                    </div>
                </div>

                <div class="form-group" style="background: #F8FAFC; padding: 12px 16px; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 15px;">
                    <label class="form-label" style="color: var(--navy-dark); font-weight: 700;">
                        <i class="fa-regular fa-calendar-check" style="color: var(--accent-blue);"></i> KPI Target Month (Tender Month) <span style="color: var(--danger);">*</span>
                    </label>
                    <select name="month_year" class="form-control" required style="font-weight: 600; padding: 10px 14px; border-radius: 8px; border: 1.5px solid #CBD5E1; cursor: pointer;">
                        <?php foreach ($monthsList as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $val === $curMonth ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: 4px;">
                        <i class="fa-solid fa-circle-info"></i> This tender will be saved and classified under the selected month's KPI target.
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">Supporting Document <span style="color:var(--text-muted);font-weight:500;">(Optional)</span></label>
                    <div style="border: 2px dashed var(--border-color); padding: 20px; text-align: center; border-radius: 8px; background: var(--bg-secondary);">
                        <i class="fa-solid fa-cloud-arrow-up" style="font-size: 28px; color: var(--accent-blue); margin-bottom: 8px;"></i>
                        <span style="display: block; font-size: 13px; color: var(--text-dark); font-weight: 600;">Click or select a file to upload</span>
                        <span style="font-size: 11px; color: var(--text-muted); display: block; margin-bottom: 12px;">Allowed formats: PDF, DOCX, ZIP (Max 10MB)</span>
                        <input type="file" name="attachment" id="tenderAttachmentInput" class="form-control" style="border: none; background: transparent; padding: 0;">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('addTenderModal')">Cancel</button>
                <button type="submit" id="btnSubmitTender" class="btn btn-primary" style="padding: 10px 24px;">
                    <i class="fa-solid fa-paper-plane"></i> Save & Submit Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Post Mortem Tender (Won / Lost Analysis) -->
<div class="modal-overlay module-clean-modal" id="postMortemModal">
    <div class="modal-box" style="max-width: 760px; overflow: hidden; border-radius: 20px;">
        <div id="pmHeader" style="background: linear-gradient(135deg, #1E293B 0%, #334155 100%); padding: 20px 24px; color: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 id="pmTitle" style="font-size: 18px; font-weight: 700; margin: 0; color: #FFFFFF;"><i class="fa-solid fa-clipboard-check" style="color: #38BDF8;"></i> Tender Post Mortem Analysis</h3>
                <span id="pmSubtitle" style="font-size: 12px; color: #E2E8F0;">Project Performance Review</span>
            </div>
            <button type="button" onclick="closePostMortemModal()" style="background: none; border: none; color: #94A3B8; font-size: 24px; cursor: pointer;">&times;</button>
        </div>

        <form id="postMortemForm" onsubmit="return false;" style="padding: 24px; max-height: 82vh; overflow-y: auto;">
            <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
            <input type="hidden" id="pmTenderId" name="id" value="">
            <input type="hidden" id="pmStatus" name="status" value="">

            <div id="pmBanner" style="background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; display: flex; align-items: center; gap: 12px;">
                <i id="pmBannerIcon" class="fa-solid fa-circle-info" style="font-size: 24px; color: #2563EB;"></i>
                <div>
                    <strong id="pmBannerTitle" style="display: block; font-size: 14px; color: #0F172A;">Post Mortem Review</strong>
                    <span id="pmBannerDesc" style="font-size: 12px; color: #475569;">Document key factors and lessons learned for future tenders.</span>
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 14px; font-weight: 700; color: #0F172A; margin-bottom: 12px;"><i class="fa-solid fa-chart-pie" style="color: #2563EB;"></i> SWOT Analysis (Strengths, Weaknesses, Opportunities, Threats)</label>
                
                <div id="pmSwotContainer" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <!-- STRENGTHS -->
                    <div style="background: #ECFDF5; border: 1.5px solid #A7F3D0; border-radius: 12px; padding: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #065F46; margin-bottom: 8px;">
                            <span style="background: #059669; color: #FFF; width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px;">S</span>
                            Strengths (Our Key Advantages)
                        </label>
                        <textarea id="pmSwotS" rows="3" placeholder="..." class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #6EE7B7; font-size: 13px; resize: vertical; background: #FFFFFF; color: #064E3B; font-weight: 500;"></textarea>
                    </div>

                    <!-- WEAKNESSES -->
                    <div style="background: #FFFBEB; border: 1.5px solid #FDE68A; border-radius: 12px; padding: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #92400E; margin-bottom: 8px;">
                            <span style="background: #D97706; color: #FFF; width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px;">W</span>
                            Weaknesses (Internal Limitations)
                        </label>
                        <textarea id="pmSwotW" rows="3" placeholder="..." class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #FCD34D; font-size: 13px; resize: vertical; background: #FFFFFF; color: #78350F; font-weight: 500;"></textarea>
                    </div>

                    <!-- OPPORTUNITIES -->
                    <div style="background: #EFF6FF; border: 1.5px solid #BFDBFE; border-radius: 12px; padding: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #1E40AF; margin-bottom: 8px;">
                            <span style="background: #2563EB; color: #FFF; width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px;">O</span>
                            Opportunities (Future Prospects)
                        </label>
                        <textarea id="pmSwotO" rows="3" placeholder="..." class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #93C5FD; font-size: 13px; resize: vertical; background: #FFFFFF; color: #1E3A8A; font-weight: 500;"></textarea>
                    </div>

                    <!-- THREATS -->
                    <div style="background: #FEF2F2; border: 1.5px solid #FECACA; border-radius: 12px; padding: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #991B1B; margin-bottom: 8px;">
                            <span style="background: #DC2626; color: #FFF; width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px;">T</span>
                            Threats (Competitor & Market Risks)
                        </label>
                        <textarea id="pmSwotT" rows="3" placeholder="..." class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #FCA5A5; font-size: 13px; resize: vertical; background: #FFFFFF; color: #7F1D1D; font-weight: 500;"></textarea>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #E2E8F0; padding-top: 16px;">
                <button type="button" class="btn" onclick="closePostMortemModal()" style="background: #F1F5F9; color: #475569; padding: 12px 20px; font-weight: 600; border: none; border-radius: 10px;">Cancel</button>
                <button type="button" id="btnSavePostMortem" onclick="submitPostMortem()" class="btn btn-primary" style="background: #2563EB; border: none; padding: 12px 26px; font-weight: 700; border-radius: 10px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(37,99,235,0.3);">
                    <i class="fa-solid fa-floppy-disk"></i> Save & Submit Post Mortem
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($canReport)) include __DIR__ . '/../tender_board/report_modal.php'; ?>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
