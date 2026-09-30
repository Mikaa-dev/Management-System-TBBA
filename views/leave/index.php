<?php
/**
 * Leave & Permission View — TBBA ERP Module 2
 */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';

$currentYear = (int)date('Y');

/*
 * History filter catalog.
 * This uses the requests already provided by the controller, so no controller
 * or database change is required for the monthly / yearly / staff filters.
 */
$historyYears = [$currentYear => $currentYear];
$historyUsers = [];
$historyDepartments = [];

foreach (($allRequests ?? []) as $request) {
    if (!empty($request['start_date'])) {
        $year = (int)date('Y', strtotime($request['start_date']));
        if ($year > 0) $historyYears[$year] = $year;
    }

    if (!empty($request['end_date'])) {
        $year = (int)date('Y', strtotime($request['end_date']));
        if ($year > 0) $historyYears[$year] = $year;
    }

    $uid = (int)($request['user_id'] ?? 0);
    if ($uid > 0) {
        $historyUsers[$uid] = $request['user_name'] ?? ('User #' . $uid);
    }

    $department = trim((string)($request['department_name'] ?? ''));
    if ($department !== '') {
        $historyDepartments[$department] = $department;
    }
}

krsort($historyYears);
natcasesort($historyUsers);
natcasesort($historyDepartments);
?>

<style>
/* ============================================================
   LEAVE HISTORY — YEAR / MONTH / STAFF FILTERS
============================================================ */
.leave-page-note {
    display:flex;
    align-items:center;
    gap:7px;
    margin-top:6px;
    color:#64748B;
    font-size:10px;
}
.leave-page-note i { color:#2563EB; }

.leave-history-tabs {
    display:inline-flex;
    gap:4px;
    padding:4px;
    border:1px solid #E2E8F0;
    border-radius:9px;
    background:#F8FAFC;
}
.leave-history-tab {
    min-height:31px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:6px 11px;
    border:0;
    border-radius:7px;
    background:transparent;
    color:#64748B;
    font-size:9.5px;
    font-weight:800;
    cursor:pointer;
}
.leave-history-tab.active {
    background:#FFFFFF;
    color:#2563EB;
    box-shadow:0 1px 3px rgba(15,23,42,.10);
}

.leave-filter-panel {
    margin-bottom:16px;
    padding:14px;
    border:1px solid #E2E8F0;
    border-radius:12px;
    background:#FFFFFF;
}
.leave-filter-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:11px;
}
.leave-filter-title {
    display:flex;
    align-items:center;
    gap:7px;
    color:#334155;
    font-size:10px;
    font-weight:800;
}
.leave-filter-grid {
    display:grid;
    grid-template-columns:repeat(6,minmax(130px,1fr));
    gap:8px;
}
.leave-filter-field label {
    display:block;
    margin-bottom:4px;
    color:#94A3B8;
    font-size:8px;
    font-weight:800;
    letter-spacing:.055em;
    text-transform:uppercase;
}
.leave-filter-field select {
    width:100%;
    min-height:34px;
    padding:7px 9px;
    border:1px solid #CBD5E1;
    border-radius:8px;
    background:#FFFFFF;
    color:#0F172A;
    font-family:inherit;
    font-size:10px;
    outline:none;
}
.leave-filter-field select:focus {
    border-color:#60A5FA;
    box-shadow:0 0 0 3px rgba(37,99,235,.08);
}
.leave-filter-actions {
    display:flex;
    align-items:flex-end;
}
.leave-reset-btn {
    width:100%;
    min-height:34px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:7px 9px;
    border:1px solid #CBD5E1;
    border-radius:8px;
    background:#F8FAFC;
    color:#475569;
    font-size:9px;
    font-weight:800;
    cursor:pointer;
}

.leave-filter-summary {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:8px;
    margin-top:11px;
}
.leave-mini-stat {
    padding:9px 10px;
    border:1px solid #E2E8F0;
    border-radius:9px;
    background:#F8FAFC;
}
.leave-mini-stat strong {
    display:block;
    color:#0F172A;
    font-size:15px;
    font-weight:850;
    line-height:1.1;
}
.leave-mini-stat span {
    display:block;
    margin-top:3px;
    color:#94A3B8;
    font-size:8px;
    font-weight:800;
    text-transform:uppercase;
}

.leave-balance-period {
    display:inline-flex;
    align-items:center;
    gap:5px;
    margin-top:7px;
    padding:3px 7px;
    border-radius:999px;
    background:#F8FAFC;
    color:#94A3B8;
    font-size:8px;
    font-weight:800;
    text-transform:uppercase;
}

.leave-mode-staff-only.is-hidden {
    display:none !important;
}

@media (max-width:1200px) {
    .leave-filter-grid { grid-template-columns:repeat(3,minmax(140px,1fr)); }
}
@media (max-width:700px) {
    .leave-filter-head {
        align-items:flex-start;
        flex-direction:column;
    }
    .leave-filter-grid { grid-template-columns:1fr 1fr; }
    .leave-filter-summary { grid-template-columns:1fr 1fr; }
}
@media (max-width:430px) {
    .leave-filter-grid { grid-template-columns:1fr; }
}
</style>

<div class="page-content" style="padding: 24px;">

    <!-- ========================================================
         MY CURRENT-YEAR LEAVE BALANCE
         Balance does NOT reset monthly. Monthly selection below
         is only for leave-history reporting.
    ========================================================= -->
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
        <div>
            <div style="font-size:11px;font-weight:800;color:#334155;">My Leave Entitlement</div>
            <div class="leave-page-note">
                <i class="fa-solid fa-circle-info"></i>
                Balance shown for <?= $currentYear ?>. Month filters below affect history only.
            </div>
        </div>
        <span class="leave-balance-period">
            <i class="fa-solid fa-calendar"></i>
            <?= $currentYear ?> Entitlement / Usage
        </span>
    </div>

    <div class="module-stat-grid"
         style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;"
         id="leaveBalanceCards">

        <?php foreach ($leaveTypes as $lt): ?>
        <?php
            $leaveCode = strtoupper(trim((string)($lt['code'] ?? '')));
            $leaveNameLower = strtolower((string)($lt['name'] ?? ''));

            // These are operational / usage records rather than a normal annual balance.
            $isUsageBased =
                in_array($leaveCode, ['OD', 'PL', 'UL'], true)
                || str_contains($leaveNameLower, 'official duty')
                || str_contains($leaveNameLower, 'outstation')
                || str_contains($leaveNameLower, 'permission to leave')
                || str_contains($leaveNameLower, 'unpaid');

            // Event / eligibility leave still uses the configured entitlement,
            // but is labelled separately from ordinary annual balance.
            $isEventBased =
                in_array($leaveCode, ['MAT', 'PAT', 'CL', 'COMP'], true)
                || str_contains($leaveNameLower, 'maternity')
                || str_contains($leaveNameLower, 'paternity')
                || str_contains($leaveNameLower, 'compassionate')
                || str_contains($leaveNameLower, 'bereavement');

            $balanceMode = $isUsageBased ? 'usage' : ($isEventBased ? 'event' : 'balance');
        ?>

        <div class="card module-stat-card"
             data-balance-mode="<?= htmlspecialchars($balanceMode) ?>"
             data-leave-type-id="<?= (int)$lt['id'] ?>"
             style="padding:20px;border-radius:12px;border-left:5px solid <?= htmlspecialchars($lt['color']) ?>;background:var(--bg-card);box-shadow:0 4px 6px -1px rgba(0,0,0,.1);">

            <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">
                <?= htmlspecialchars($lt['name']) ?>
            </div>

            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:8px;margin-top:10px;">
                <span style="font-size:26px;font-weight:800;color:var(--text-dark);"
                      id="bal_rem_<?= (int)$lt['id'] ?>">--</span>

                <span style="font-size:12px;color:var(--text-muted);text-align:right;"
                      id="bal_suffix_<?= (int)$lt['id'] ?>">
                    <?php if ($balanceMode === 'usage'): ?>
                        Days Used YTD
                    <?php else: ?>
                        / <?= htmlspecialchars($lt['days_allowed']) ?> Days
                    <?php endif; ?>
                </span>
            </div>

            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;"
                 id="bal_sub_<?= (int)$lt['id'] ?>">
                <?php if ($balanceMode === 'usage'): ?>
                    <?= $currentYear ?> usage
                <?php elseif ($balanceMode === 'event'): ?>
                    Used YTD: <span id="bal_used_<?= (int)$lt['id'] ?>">0</span> Days
                <?php else: ?>
                    Used: <span id="bal_used_<?= (int)$lt['id'] ?>">0</span> Days
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>

    </div>


    <!-- ========================================================
         HISTORY HEADER
    ========================================================= -->
    <div class="card module-toolbar"
         style="padding:20px;border-radius:12px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;background:var(--bg-card);">

        <div>
            <h3 style="margin:0;font-size:18px;color:var(--text-dark);">
                <i class="fa-solid fa-calendar-check" style="color:#2563EB;"></i>
                Leave Requests History
            </h3>

            <p style="margin:4px 0 0;font-size:13px;color:var(--text-muted);">
                Filter requests by year, month<?= $canApprove ? ', staff and department' : '' ?>.
                Leave balances above remain annual / policy-based.
            </p>
        </div>

        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">

            <?php if ($canApprove): ?>
            <div class="leave-history-tabs" aria-label="Leave history mode">
                <button type="button"
                        id="leaveTabMy"
                        class="leave-history-tab"
                        onclick="setLeaveHistoryMode('my')">
                    <i class="fa-solid fa-user"></i>
                    My Leave
                </button>

                <button type="button"
                        id="leaveTabStaff"
                        class="leave-history-tab active"
                        onclick="setLeaveHistoryMode('staff')">
                    <i class="fa-solid fa-users"></i>
                    Staff Leave
                </button>
            </div>
            <?php endif; ?>

            <?php if (Auth::hasPermission('leave', 'create')): ?>
            <button type="button"
                    class="btn btn-primary"
                    onclick="openApplyLeaveModal()"
                    style="padding:10px 18px;font-weight:600;border-radius:8px;background:#2563EB;color:#fff;border:none;cursor:pointer;">
                <i class="fa-solid fa-plus"></i>
                Apply New Leave
            </button>
            <?php endif; ?>

        </div>
    </div>


    <!-- ========================================================
         HISTORY FILTERS
    ========================================================= -->
    <div class="leave-filter-panel">

        <div class="leave-filter-head">
            <div class="leave-filter-title">
                <i class="fa-solid fa-filter" style="color:#2563EB;"></i>
                History Filters
            </div>

            <div id="leaveFilterContext"
                 style="color:#94A3B8;font-size:9px;font-weight:700;">
                Showing <?= $canApprove ? 'staff requests' : 'your requests' ?>
            </div>
        </div>

        <div class="leave-filter-grid">

            <div class="leave-filter-field">
                <label for="leaveFilterYear">Year</label>
                <select id="leaveFilterYear">
                    <option value="">All Years</option>
                    <?php foreach ($historyYears as $year): ?>
                    <option value="<?= (int)$year ?>" <?= (int)$year === $currentYear ? 'selected' : '' ?>>
                        <?= (int)$year ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="leave-filter-field">
                <label for="leaveFilterMonth">Month</label>
                <select id="leaveFilterMonth">
                    <option value="">All Months</option>
                    <?php
                    $months = [
                        1=>'January',2=>'February',3=>'March',4=>'April',
                        5=>'May',6=>'June',7=>'July',8=>'August',
                        9=>'September',10=>'October',11=>'November',12=>'December'
                    ];
                    ?>
                    <?php foreach ($months as $monthNumber => $monthName): ?>
                    <option value="<?= $monthNumber ?>"><?= $monthName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($canApprove): ?>
            <div class="leave-filter-field leave-mode-staff-only">
                <label for="leaveFilterStaff">Staff</label>
                <select id="leaveFilterStaff">
                    <option value="">All Staff</option>
                    <?php foreach ($historyUsers as $uid => $uname): ?>
                    <option value="<?= (int)$uid ?>"><?= htmlspecialchars($uname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="leave-filter-field leave-mode-staff-only">
                <label for="leaveFilterDepartment">Department</label>
                <select id="leaveFilterDepartment">
                    <option value="">All Departments</option>
                    <?php foreach ($historyDepartments as $department): ?>
                    <option value="<?= htmlspecialchars(strtolower($department)) ?>">
                        <?= htmlspecialchars($department) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="leave-filter-field">
                <label for="leaveFilterType">Leave Type</label>
                <select id="leaveFilterType">
                    <option value="">All Leave Types</option>
                    <?php foreach ($leaveTypes as $lt): ?>
                    <option value="<?= (int)$lt['id'] ?>"><?= htmlspecialchars($lt['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="leave-filter-field">
                <label for="leaveFilterStatus">Status</label>
                <select id="leaveFilterStatus">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="leave-filter-actions">
                <button type="button"
                        class="leave-reset-btn"
                        onclick="resetLeaveHistoryFilters()">
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset Filters
                </button>
            </div>

        </div>

        <div class="leave-filter-summary">

            <div class="leave-mini-stat">
                <strong id="leaveSummaryRequests">0</strong>
                <span>Requests</span>
            </div>

            <div class="leave-mini-stat">
                <strong id="leaveSummaryApproved">0</strong>
                <span>Approved</span>
            </div>

            <div class="leave-mini-stat">
                <strong id="leaveSummaryPending">0</strong>
                <span>Pending</span>
            </div>

            <div class="leave-mini-stat">
                <strong id="leaveSummaryDays">0</strong>
                <span>Approved Days</span>
            </div>

        </div>

    </div>


    <!-- ========================================================
         REQUESTS TABLE
    ========================================================= -->
    <div class="card module-data-card"
         style="padding:20px;border-radius:12px;background:var(--bg-card);overflow-x:auto;">

        <table class="datatable table"
               id="leaveHistoryTable"
               style="width:100%;border-collapse:collapse;font-size:13px;">

            <thead>
                <tr style="border-bottom:2px solid var(--border-color);text-align:left;">
                    <th style="padding:12px;">ID</th>
                    <?php if ($canApprove): ?><th style="padding:12px;">Staff Name</th><?php endif; ?>
                    <th style="padding:12px;">Leave Type</th>
                    <th style="padding:12px;">Duration (Dates)</th>
                    <th style="padding:12px;">Days</th>
                    <th style="padding:12px;">Reason</th>
                    <th style="padding:12px;">Status</th>
                    <th style="padding:12px;text-align:center;">Actions</th>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($allRequests)): ?>
                <tr id="leaveNoRequestsRow">
                    <td colspan="<?= $canApprove ? 8 : 7 ?>"
                        style="text-align:center;padding:24px;color:var(--text-muted);">
                        No leave requests found.
                    </td>
                </tr>

                <?php else: ?>

                <?php foreach ($allRequests as $r): ?>
                <?php
                    $rowDept = strtolower(trim((string)($r['department_name'] ?? '')));
                    $rowLeaveTypeId = (int)($r['leave_type_id'] ?? $r['type_id'] ?? 0);
                ?>

                <tr class="leave-history-row"
                    data-user-id="<?= (int)($r['user_id'] ?? 0) ?>"
                    data-department="<?= htmlspecialchars($rowDept) ?>"
                    data-leave-type-id="<?= $rowLeaveTypeId ?>"
                    data-status="<?= htmlspecialchars(strtolower((string)($r['status'] ?? ''))) ?>"
                    data-start-date="<?= htmlspecialchars((string)($r['start_date'] ?? '')) ?>"
                    data-end-date="<?= htmlspecialchars((string)($r['end_date'] ?? '')) ?>"
                    data-days="<?= (float)($r['total_days'] ?? 0) ?>"
                    style="border-bottom:1px solid var(--border-color);">

                    <td data-label="ID" style="padding:12px;font-weight:600;">
                        #LR-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?>
                    </td>

                    <?php if ($canApprove): ?>
                    <td data-label="Staff Name" style="padding:12px;">
                        <div style="font-weight:600;color:var(--text-dark);">
                            <?= htmlspecialchars($r['user_name'] ?? 'Unknown') ?>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);">
                            <?= htmlspecialchars($r['department_name'] ?? 'General') ?>
                        </div>
                    </td>
                    <?php endif; ?>

                    <td data-label="Leave Type" style="padding:12px;">
                        <span style="background:<?= $r['leave_color'] ?? '#2563EB' ?>22;color:<?= $r['leave_color'] ?? '#2563EB' ?>;padding:4px 10px;border-radius:50px;font-size:11px;font-weight:700;">
                            <?= htmlspecialchars($r['leave_type_name'] ?? 'Leave') ?>
                        </span>

                        <?php if (!empty($r['attachment_path'])): ?>
                        <div style="margin-top:4px;">
                            <a href="index.php?action=download_attachment&amp;type=leave&amp;id=<?= (int)$r['id'] ?>"
                               target="_blank"
                               rel="noopener"
                               style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#2563EB;background:#2563EB15;padding:3px 8px;border-radius:4px;text-decoration:none;">
                                <i class="fa-solid fa-paperclip"></i>
                                View Proof
                            </a>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($r['gps_coordinates'])): ?>
                        <div style="margin-top:4px;">
                            <a href="https://maps.google.com/?q=<?= htmlspecialchars($r['gps_coordinates']) ?>"
                               target="_blank"
                               rel="noopener"
                               style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#10B981;background:#10B98115;padding:3px 8px;border-radius:4px;text-decoration:none;">
                                <i class="fa-solid fa-location-dot"></i>
                                GPS Map
                            </a>
                        </div>
                        <?php endif; ?>
                    </td>

                    <td data-label="Duration" style="padding:12px;">
                        <div>
                            <?= date('d M Y', strtotime($r['start_date'])) ?>
                            &rarr;
                            <?= date('d M Y', strtotime($r['end_date'])) ?>
                        </div>

                        <?php if ($r['half_day']): ?>
                        <span style="font-size:10px;color:#D97706;font-weight:700;">
                            (Half Day)
                        </span>
                        <?php endif; ?>
                    </td>

                    <td data-label="Days" style="padding:12px;font-weight:700;">
                        <?= $r['total_days'] ?> d
                    </td>

                    <td data-label="Reason"
                        style="padding:12px;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                        title="<?= htmlspecialchars($r['reason'] ?? '') ?>">
                        <?= htmlspecialchars($r['reason'] ?? '-') ?>
                    </td>

                    <td data-label="Status" style="padding:12px;">
                        <?php
                        $statusColors = [
                            'pending'   => ['#FEF3C7', '#D97706', 'Pending'],
                            'approved'  => ['#D1FAE5', '#059669', 'Approved'],
                            'rejected'  => ['#FEE2E2', '#DC2626', 'Rejected'],
                            'cancelled' => ['#F1F5F9', '#64748B', 'Cancelled']
                        ];

                        $sc = $statusColors[$r['status']]
                            ?? ['#F1F5F9', '#64748B', ucfirst($r['status'])];
                        ?>

                        <span style="background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;">
                            <?= $sc[2] ?>
                        </span>
                    </td>

                    <td data-label="Actions" style="padding:12px;text-align:center;">

                        <button class="btn btn-secondary btn-sm"
                                onclick="viewLeaveDetail(<?= $r['id'] ?>)"
                                style="padding:4px 8px;border-radius:4px;border:1px solid var(--border-color);background:transparent;cursor:pointer;color:var(--text-dark);"
                                title="View Details">
                            <i class="fa-solid fa-eye"></i>
                        </button>

                        <?php if ($canApprove && $r['status'] === 'pending'): ?>
                        <button class="btn btn-success btn-sm"
                                onclick="approveLeave(<?= $r['id'] ?>, 'approved')"
                                style="padding:4px 8px;border-radius:4px;border:none;background:#10B981;color:#fff;cursor:pointer;"
                                title="Sign & Approve">
                            <i class="fa-solid fa-pen-nib"></i>
                            Sign &amp; Approve
                        </button>

                        <button class="btn btn-danger btn-sm"
                                onclick="approveLeave(<?= $r['id'] ?>, 'rejected')"
                                style="padding:4px 8px;border-radius:4px;border:none;background:#EF4444;color:#fff;cursor:pointer;"
                                title="Reject">
                            <i class="fa-solid fa-times"></i>
                        </button>
                        <?php endif; ?>

                        <?php if ($r['user_id'] == Auth::id() && $r['status'] === 'pending' && Auth::hasPermission('leave','delete')): ?>
                        <button class="btn btn-warning btn-sm"
                                onclick="cancelLeave(<?= $r['id'] ?>)"
                                style="padding:4px 8px;border-radius:4px;border:none;background:#D97706;color:#fff;cursor:pointer;"
                                title="Cancel Submission">
                            <i class="fa-solid fa-ban"></i>
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

<!-- Modal: Apply Leave -->
<div class="modal-overlay module-clean-modal" id="applyLeaveModal">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-calendar-plus" style="color: #2563EB;"></i> Apply For Leave</h3>
            <button class="modal-close" onclick="App.closeModal('applyLeaveModal')">&times;</button>
        </div>
        <form id="applyLeaveForm" onsubmit="submitLeave(event)">
            <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
            <div class="modal-body" style="padding: 20px;">
                <!-- Category Tabs (Annual / Medical vs Outstation) -->
                <div style="display: flex; background: var(--bg-card); padding: 4px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 16px;">
                    <button type="button" id="tabCatLeave" onclick="switchLeaveCategory('leave')" style="flex: 1; padding: 8px; border-radius: 6px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; background: #2563EB; color: #fff; display: flex; align-items: center; justify-content: center; gap: 6px;">
                        <i class="fa-solid fa-umbrella-beach"></i> Annual / Medical Leave
                    </button>
                    <button type="button" id="tabCatOutstation" onclick="switchLeaveCategory('outstation')" style="flex: 1; padding: 8px; border-radius: 6px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; background: transparent; color: var(--text-dark); display: flex; align-items: center; justify-content: center; gap: 6px;">
                        <i class="fa-solid fa-briefcase"></i> Outstation / Official Duty
                    </button>
                </div>

                <div style="margin-bottom: 16px;">
                    <label id="leaveTypeLabel" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Leave Type <span style="color: #EF4444;">*</span></label>
                    <select name="leave_type_id" id="leaveTypeSelect" required onchange="handleLeaveTypeChange(this)" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                        <option value="" data-code="" data-category="both">-- Select Leave Type --</option>
                        <?php foreach ($leaveTypes as $lt): 
                            $isOutstation = ($lt['code'] == 'OD' || $lt['code'] == 'PL');
                        ?>
                        <option value="<?= $lt['id'] ?>" data-code="<?= htmlspecialchars($lt['code'] ?? '') ?>" data-name="<?= htmlspecialchars($lt['name'] ?? '') ?>" data-category="<?= $isOutstation ? 'outstation' : 'leave' ?>" style="<?= $isOutstation ? 'display: none;' : '' ?>">
                            <?= htmlspecialchars($lt['name']) ?> (Max: <?= $lt['days_allowed'] ?> days)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Start Date <span style="color: #EF4444;">*</span></label>
                        <input type="date" name="start_date" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">End Date <span style="color: #EF4444;">*</span></label>
                        <input type="date" name="end_date" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                </div>
                <div id="halfDayDiv" style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-dark); cursor: pointer;">
                        <input type="checkbox" name="half_day" value="1" style="width: 16px; height: 16px;">
                        <span>Half Day Application (0.5 day)</span>
                    </label>
                </div>

                <!-- Minimalist Proof & Location Containers -->
                <input type="hidden" name="proof_type" id="proofTypeInput" value="none">
                <input type="hidden" name="proof_required" id="proofRequiredInput" value="0">
                <input type="hidden" name="gps_coordinates" id="gpsCoordsInput" value="">
                
                <!-- 1. Supporting Document Box (Minimalist) -->
                <div id="proofDocBox" style="display: none; margin-bottom: 16px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px;">
                    <label id="proofDocLabel" style="font-size: 12px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                        <i class="fa-solid fa-paperclip"></i> Supporting Document <span id="proofDocAsterisk" style="color:#EF4444;">*</span>
                    </label>
                    <input type="file" name="proof_file" id="proofFileInput" accept="image/*,.pdf,.doc,.docx" style="width: 100%; font-size: 12px; background: var(--bg-primary); padding: 6px; border-radius: 6px; border: 1px solid var(--border-color); color: var(--text-dark);">
                    <div id="photoPreviewDoc" style="display:none; margin-top: 6px;"></div>
                </div>

                <!-- 2. Location Verification Box (Minimalist) -->
                <div id="proofLocationBox" style="display: none; margin-bottom: 16px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                        <span style="font-size: 12px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-location-dot" style="color: #10B981;"></i> Location Verification
                        </span>
                        <div>
                            <button type="button" id="btnToggleGPS" onclick="stampGPSLocation()" style="padding: 4px 10px; font-size: 11px; border-radius: 6px; border: 1px solid #10B981; background: #10B981; color: #fff; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fa-solid fa-location-crosshairs"></i> Tag GPS
                            </button>
                        </div>
                    </div>
                    <div id="gpsStatusDisplay" style="font-size: 11px; color: var(--text-muted); margin-bottom: 6px;">
                        Click Tag GPS or attach document/photo below.
                    </div>
                    <input type="file" id="proofFileLocInput" onchange="syncFileInput(this)" accept="image/*,.pdf,.doc,.docx" style="width: 100%; font-size: 12px; background: var(--bg-primary); padding: 6px; border-radius: 6px; border: 1px solid var(--border-color); color: var(--text-dark);">
                    <div id="photoPreviewLoc" style="display:none; margin-top: 6px;"></div>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Reason / Remarks <span style="color: #EF4444;">*</span></label>
                    <textarea name="reason" rows="3" required placeholder="Provide your reason for leave..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark); resize: vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('applyLeaveModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitLeave" style="padding: 8px 16px; border-radius: 6px; background: #2563EB; color: #fff; border: none; font-weight: 600; cursor: pointer;">Submit Request</button>
            </div>
        </form>
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



<script>
const LEAVE_CURRENT_USER_ID = <?= (int)Auth::id() ?>;
const LEAVE_CAN_APPROVE = <?= $canApprove ? 'true' : 'false' ?>;
const LEAVE_CURRENT_YEAR = <?= $currentYear ?>;

let leaveHistoryMode = LEAVE_CAN_APPROVE ? 'staff' : 'my';
let leaveDataTableFilterRegistered = false;


/* ============================================================
   INITIALISE
============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    loadLeaveBalance();
    initLeaveHistoryFilters();

    /*
     * DataTables may initialise from app.js on the same DOMContentLoaded
     * event. Retry briefly so our custom filter can attach after it exists.
     */
    let tries = 0;
    const timer = window.setInterval(() => {
        tries++;

        if (registerLeaveDataTableFilter() || tries >= 12) {
            window.clearInterval(timer);
            applyLeaveHistoryFilters();
        }
    }, 180);
});


/* ============================================================
   HISTORY FILTER MODE
============================================================ */
function setLeaveHistoryMode(mode) {
    if (!LEAVE_CAN_APPROVE) {
        leaveHistoryMode = 'my';
        applyLeaveHistoryFilters();
        return;
    }

    leaveHistoryMode = mode === 'my' ? 'my' : 'staff';

    document.getElementById('leaveTabMy')?.classList.toggle(
        'active',
        leaveHistoryMode === 'my'
    );

    document.getElementById('leaveTabStaff')?.classList.toggle(
        'active',
        leaveHistoryMode === 'staff'
    );

    document.querySelectorAll('.leave-mode-staff-only').forEach(element => {
        element.classList.toggle(
            'is-hidden',
            leaveHistoryMode === 'my'
        );
    });

    const staffSelect = document.getElementById('leaveFilterStaff');
    const departmentSelect = document.getElementById('leaveFilterDepartment');
    const context = document.getElementById('leaveFilterContext');

    if (leaveHistoryMode === 'my') {
        if (staffSelect) staffSelect.value = '';
        if (departmentSelect) departmentSelect.value = '';
        if (context) context.textContent = 'Showing your leave requests';
    } else {
        if (context) context.textContent = 'Showing staff leave requests';
    }

    applyLeaveHistoryFilters();
}


/* ============================================================
   FILTER SETUP
============================================================ */
function initLeaveHistoryFilters() {
    [
        'leaveFilterYear',
        'leaveFilterMonth',
        'leaveFilterStaff',
        'leaveFilterDepartment',
        'leaveFilterType',
        'leaveFilterStatus'
    ].forEach(id => {
        document.getElementById(id)?.addEventListener(
            'change',
            applyLeaveHistoryFilters
        );
    });

    setLeaveHistoryMode(leaveHistoryMode);
}


function resetLeaveHistoryFilters() {
    const year = document.getElementById('leaveFilterYear');
    const month = document.getElementById('leaveFilterMonth');
    const staff = document.getElementById('leaveFilterStaff');
    const department = document.getElementById('leaveFilterDepartment');
    const type = document.getElementById('leaveFilterType');
    const status = document.getElementById('leaveFilterStatus');

    if (year) year.value = String(LEAVE_CURRENT_YEAR);
    if (month) month.value = '';
    if (staff) staff.value = '';
    if (department) department.value = '';
    if (type) type.value = '';
    if (status) status.value = '';

    applyLeaveHistoryFilters();
}


/* ============================================================
   DATE OVERLAP
   A request from 31 Jul -> 09 Aug appears in BOTH July and August.
============================================================ */
function leaveRequestOverlapsPeriod(row, yearValue, monthValue) {
    if (!yearValue && !monthValue) return true;

    const startRaw = row.dataset.startDate || '';
    const endRaw = row.dataset.endDate || startRaw;

    if (!startRaw) return false;

    const start = new Date(startRaw + 'T00:00:00');
    const end = new Date(endRaw + 'T23:59:59');

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return false;
    }

    const year = yearValue
        ? Number(yearValue)
        : start.getFullYear();

    let periodStart;
    let periodEnd;

    if (monthValue) {
        const month = Number(monthValue);

        periodStart = new Date(year, month - 1, 1, 0, 0, 0);
        periodEnd = new Date(year, month, 0, 23, 59, 59);
    } else {
        periodStart = new Date(year, 0, 1, 0, 0, 0);
        periodEnd = new Date(year, 11, 31, 23, 59, 59);
    }

    return start <= periodEnd && end >= periodStart;
}


/* ============================================================
   ROW MATCH
============================================================ */
function leaveRowMatchesFilters(row) {
    if (!row?.classList?.contains('leave-history-row')) {
        return true;
    }

    const year = document.getElementById('leaveFilterYear')?.value || '';
    const month = document.getElementById('leaveFilterMonth')?.value || '';
    const staff = document.getElementById('leaveFilterStaff')?.value || '';
    const department = (
        document.getElementById('leaveFilterDepartment')?.value || ''
    ).toLowerCase();
    const leaveType = document.getElementById('leaveFilterType')?.value || '';
    const status = (
        document.getElementById('leaveFilterStatus')?.value || ''
    ).toLowerCase();

    if (
        leaveHistoryMode === 'my'
        && Number(row.dataset.userId || 0) !== Number(LEAVE_CURRENT_USER_ID)
    ) {
        return false;
    }

    if (
        leaveHistoryMode === 'staff'
        && staff
        && String(row.dataset.userId || '') !== String(staff)
    ) {
        return false;
    }

    if (
        leaveHistoryMode === 'staff'
        && department
        && String(row.dataset.department || '').toLowerCase() !== department
    ) {
        return false;
    }

    if (
        leaveType
        && String(row.dataset.leaveTypeId || '') !== String(leaveType)
    ) {
        return false;
    }

    if (
        status
        && String(row.dataset.status || '').toLowerCase() !== status
    ) {
        return false;
    }

    if (!leaveRequestOverlapsPeriod(row, year, month)) {
        return false;
    }

    return true;
}


/* ============================================================
   DATATABLES INTEGRATION
============================================================ */
function getLeaveDataTable() {
    if (!window.jQuery || !jQuery.fn?.DataTable) return null;

    const table = document.getElementById('leaveHistoryTable');

    if (!table || !jQuery.fn.DataTable.isDataTable(table)) {
        return null;
    }

    return jQuery(table).DataTable();
}


function registerLeaveDataTableFilter() {
    if (leaveDataTableFilterRegistered) return true;

    if (!window.jQuery || !jQuery.fn?.dataTable?.ext?.search) {
        return false;
    }

    const dt = getLeaveDataTable();
    if (!dt) return false;

    jQuery.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            if (settings.nTable?.id !== 'leaveHistoryTable') {
                return true;
            }

            const row = settings.aoData?.[dataIndex]?.nTr;

            return leaveRowMatchesFilters(row);
        }
    );

    leaveDataTableFilterRegistered = true;

    return true;
}


/* ============================================================
   APPLY FILTERS + SUMMARY
============================================================ */
function applyLeaveHistoryFilters() {
    const rows = Array.from(
        document.querySelectorAll(
            '#leaveHistoryTable tbody tr.leave-history-row'
        )
    );

    let requestCount = 0;
    let approvedCount = 0;
    let pendingCount = 0;
    let approvedDays = 0;

    rows.forEach(row => {
        const match = leaveRowMatchesFilters(row);

        if (match) {
            requestCount++;

            const rowStatus = String(
                row.dataset.status || ''
            ).toLowerCase();

            if (rowStatus === 'approved') {
                approvedCount++;
                approvedDays += Number(row.dataset.days || 0);
            }

            if (rowStatus === 'pending') {
                pendingCount++;
            }
        }
    });

    document.getElementById('leaveSummaryRequests').textContent = requestCount;
    document.getElementById('leaveSummaryApproved').textContent = approvedCount;
    document.getElementById('leaveSummaryPending').textContent = pendingCount;
    document.getElementById('leaveSummaryDays').textContent =
        Number.isInteger(approvedDays)
            ? approvedDays
            : approvedDays.toFixed(1);

    const dt = getLeaveDataTable();

    if (dt) {
        registerLeaveDataTableFilter();
        dt.draw(false);
        return;
    }

    /*
     * Fallback when DataTables is not available / not initialised.
     */
    rows.forEach(row => {
        row.style.display = leaveRowMatchesFilters(row) ? '' : 'none';
    });
}


/* ============================================================
   APPLY LEAVE MODAL
============================================================ */
function openApplyLeaveModal() {
    App.openModal('applyLeaveModal');
    switchLeaveCategory('leave');
}

async function loadLeaveBalance() {
    try {
        const res = await fetch(
            'index.php?action=get_leave_balance',
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            }
        );

        const data = await res.json();

        if (data.status === 'success' && data.data) {
            data.data.forEach(item => {
                const typeId = item.type.id;
                const card = document.querySelector(
                    `[data-leave-type-id="${typeId}"]`
                );

                const mode = card?.dataset.balanceMode || 'balance';

                const mainEl = document.getElementById(
                    `bal_rem_${typeId}`
                );

                const usedEl = document.getElementById(
                    `bal_used_${typeId}`
                );

                const suffixEl = document.getElementById(
                    `bal_suffix_${typeId}`
                );

                const subEl = document.getElementById(
                    `bal_sub_${typeId}`
                );

                if (mode === 'usage') {
                    if (mainEl) mainEl.textContent = item.used ?? 0;

                    if (suffixEl) {
                        suffixEl.textContent = 'Days Used YTD';
                    }

                    if (subEl) {
                        subEl.textContent =
                            `${LEAVE_CURRENT_YEAR} usage`;
                    }

                    return;
                }

                if (mainEl) {
                    mainEl.textContent = item.remaining;
                }

                if (usedEl) {
                    usedEl.textContent = item.used;
                }

                if (mode === 'event' && subEl) {
                    subEl.innerHTML =
                        `Used YTD: <span id="bal_used_${typeId}">${item.used}</span> Days`;
                }
            });
        }

    } catch (e) {
        console.error('Failed to load balance', e);
    }
}

function switchLeaveCategory(cat) {
    const tabLeave = document.getElementById('tabCatLeave');
    const tabOutstation = document.getElementById('tabCatOutstation');
    const label = document.getElementById('leaveTypeLabel');
    const select = document.getElementById('leaveTypeSelect');
    const halfDayDiv = document.getElementById('halfDayDiv');

    if (cat === 'leave') {
        tabLeave.style.background = '#2563EB'; tabLeave.style.color = '#fff';
        tabOutstation.style.background = 'transparent'; tabOutstation.style.color = 'var(--text-dark)';
        label.innerHTML = 'Leave Type (Annual / Medical) <span style="color: #EF4444;">*</span>';
        if (halfDayDiv) halfDayDiv.style.display = 'block';
    } else {
        tabOutstation.style.background = '#2563EB'; tabOutstation.style.color = '#fff';
        tabLeave.style.background = 'transparent'; tabLeave.style.color = 'var(--text-dark)';
        label.innerHTML = 'Outstation / Official Duty Option <span style="color: #EF4444;">*</span>';
        if (halfDayDiv) halfDayDiv.style.display = 'none';
    }

    // Filter select options
    let firstValid = '';
    Array.from(select.options).forEach(opt => {
        const optCat = opt.getAttribute('data-category');
        if (optCat === 'both' || optCat === cat) {
            opt.style.display = '';
            if (!firstValid && optCat === cat) firstValid = opt.value;
        } else {
            opt.style.display = 'none';
        }
    });

    select.value = '';
    handleLeaveTypeChange(select);
}

function handleLeaveTypeChange(select) {
    const opt = select.options[select.selectedIndex];
    const code = opt ? (opt.getAttribute('data-code') || '').toUpperCase() : '';
    const name = opt ? (opt.getAttribute('data-name') || '').toLowerCase() : '';
    
    const docBox = document.getElementById('proofDocBox');
    const locBox = document.getElementById('proofLocationBox');
    const docLabel = document.getElementById('proofDocLabel');
    const fileInput = document.getElementById('proofFileInput');
    const fileLocInput = document.getElementById('proofFileLocInput');
    const proofTypeInput = document.getElementById('proofTypeInput');
    const proofRequiredInput = document.getElementById('proofRequiredInput');

    fileInput.required = false;
    fileInput.value = '';
    if (fileLocInput) fileLocInput.value = '';
    proofRequiredInput.value = '0';
    docBox.style.display = 'none';
    locBox.style.display = 'none';

    if (code === 'ML' || name.includes('medical') || code === 'MAT' || name.includes('maternity') || code === 'PAT' || name.includes('paternity') || code === 'EL' || name.includes('emergency')) {
        docBox.style.display = 'block';
        docLabel.innerHTML = '<i class="fa-solid fa-file-medical" style="color:#2563EB;"></i> Supporting Document (MC/Letter) <span style="color:#EF4444;">*</span>';
        proofTypeInput.value = 'document';
        proofRequiredInput.value = '1';
        fileInput.required = true;
    } else if (code === 'OD' || name.includes('outstation') || name.includes('official duty') || code === 'PL' || name.includes('permission') || name.includes('time off')) {
        locBox.style.display = 'block';
        proofTypeInput.value = 'gps_or_photo';
        proofRequiredInput.value = '1';
    } else if (
        code === 'AL'
        || code === 'UL'
        || code === 'RL'
        || code === 'CL'
        || code === 'COMP'
        || name.includes('compassionate')
        || name.includes('bereavement')
    ) {
        docBox.style.display = 'block';
        docLabel.innerHTML = '<i class="fa-solid fa-paperclip"></i> Optional Attachment / Supporting Note';
        proofTypeInput.value = 'optional';
    } else {
        proofTypeInput.value = 'none';
    }
}

function syncFileInput(locInput) {
    const mainFileInput = document.getElementById('proofFileInput');
    if (locInput.files && locInput.files.length > 0) {
        mainFileInput.files = locInput.files;
    } else {
        mainFileInput.value = '';
    }
}

function stampGPSLocation() {
    const span = document.getElementById('gpsStatusDisplay');
    const input = document.getElementById('gpsCoordsInput');
    const btn = document.getElementById('btnToggleGPS');
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
    }
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Tagging...';
    span.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="color:#2563EB;"></i> Getting high-accuracy GPS coordinates...';
    
    navigator.geolocation.getCurrentPosition((pos) => {
        const lat = pos.coords.latitude.toFixed(6);
        const lng = pos.coords.longitude.toFixed(6);
        input.value = `${lat}, ${lng}`;
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> GPS Stamped';
        btn.style.background = '#059669';
        span.innerHTML = `<span style="color:#10B981; font-weight:700;"><i class="fa-solid fa-circle-check"></i> Stamped: ${lat}, ${lng}</span>`;
    }, (err) => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Try Again';
        span.innerHTML = `<span style="color:#EF4444;"><i class="fa-solid fa-triangle-exclamation"></i> GPS permission denied or unavailable</span>`;
    }, { enableHighAccuracy: true, timeout: 10000 });
}

async function submitLeave(e) {
    e.preventDefault();
    const form = document.getElementById('applyLeaveForm');
    const formData = new FormData(form);
    const btn = document.getElementById('btnSubmitLeave');
    const res = await App.post('index.php?action=add_leave', formData, btn);
    if (res && res.status === 'success') {
        App.closeModal('applyLeaveModal');
        form.reset();
        setTimeout(() => location.reload(), 1000);
    }
}

function escapeLeaveDetail(value) {
    return String(value ?? '').replace(/[&<>'"]/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[char]));
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
            const gps = escapeLeaveDetail(r.gps_coordinates || '');
            const attachment = escapeLeaveDetail(r.attachment_path || '');
            content.innerHTML = `
                <div style="display:grid; grid-template-columns: 120px 1fr; gap: 8px; margin-bottom: 12px;">
                    <strong style="color: var(--text-muted);">Staff Name:</strong> <span style="color: var(--text-dark); font-weight:600;">${escapeLeaveDetail(r.user_name || '-')}</span>
                    <strong style="color: var(--text-muted);">Leave Type:</strong> <span style="color: var(--text-dark); font-weight:600;">${escapeLeaveDetail(r.leave_type_name || '-')}</span>
                    <strong style="color: var(--text-muted);">Start Date:</strong> <span style="color: var(--text-dark);">${escapeLeaveDetail(r.start_date || '-')}</span>
                    <strong style="color: var(--text-muted);">End Date:</strong> <span style="color: var(--text-dark);">${escapeLeaveDetail(r.end_date || '-')}</span>
                    <strong style="color: var(--text-muted);">Total Days:</strong> <span style="color: var(--text-dark); font-weight:600;">${escapeLeaveDetail(r.total_days)} Day(s) ${r.half_day == 1 ? '(Half Day)' : ''}</span>
                    <strong style="color: var(--text-muted);">Status:</strong> <span style="color: var(--text-dark); text-transform:uppercase; font-weight:700;">${escapeLeaveDetail(r.status || '-')}</span>
                    ${gps ? `<strong style="color: var(--text-muted);">GPS Stamped:</strong> <span><a href="https://maps.google.com/?q=${encodeURIComponent(r.gps_coordinates)}" target="_blank" rel="noopener" style="color:#10B981; font-weight:700; text-decoration:none;"><i class="fa-solid fa-location-dot"></i> ${gps} (Open Map)</a></span>` : ''}
                    ${attachment ? `<strong style="color: var(--text-muted);">Attached Proof:</strong> <span><a href="index.php?action=download_attachment&type=leave&id=${encodeURIComponent(id)}" target="_blank" rel="noopener" style="color:#2563EB; font-weight:700; text-decoration:none;"><i class="fa-solid fa-paperclip"></i> View Document / Snapshot</a></span>` : ''}
                </div>
                <div style="background: var(--bg-primary); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); margin-top: 12px;">
                    <strong style="color: var(--text-muted); display:block; margin-bottom:4px;">Reason / Remarks:</strong>
                    <span style="color: var(--text-dark);">${escapeLeaveDetail(r.reason || 'No reason provided.')}</span>
                </div>
                ${r.rejection_reason ? `
                    <div style="background: #FEE2E2; color: #DC2626; padding: 12px; border-radius: 8px; margin-top: 12px;">
                        <strong>Approver Remarks:</strong> ${escapeLeaveDetail(r.rejection_reason)}
                    </div>
                ` : ''}
            `;
        } else {
            content.innerHTML = `<p style="color:#EF4444;">Failed to load request details.</p>`;
        }
    } catch (e) {
        console.error("Leave detail load error:", e);
        content.innerHTML = `<p style="color:#EF4444;">Error connecting to server.</p>`;
    }
}

async function approveLeave(id, decision) {
    let reason = '';
    if (decision === 'rejected') {
        reason = prompt('Please enter the reason for rejection:');
        if (reason === null) return;
    } else {
        const ok = await App.confirm('Approve Leave', 'Are you sure you want to approve this leave request?');
        if (!ok) return;
    }
    const formData = new FormData();
    formData.append('id', id);
    formData.append('decision', decision);
    if (reason) formData.append('reason', reason);

    const res = await App.post('index.php?action=update_leave_status', formData);
    if (res && res.status === 'success') {
        setTimeout(() => location.reload(), 1000);
    }
}

async function cancelLeave(id) {
    const ok = await App.confirm('Cancel Leave', 'Are you sure you want to cancel this leave application?');
    if (!ok) return;
    const formData = new FormData();
    formData.append('id', id);
    const res = await App.post('index.php?action=cancel_leave', formData);
    if (res && res.status === 'success') {
        setTimeout(() => location.reload(), 1000);
    }
}


</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
