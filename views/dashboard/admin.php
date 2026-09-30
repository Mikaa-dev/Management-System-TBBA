<?php
/**
 * Admin Dashboard — TBBA ERP
 * Upgraded management-focused dashboard
 *
 * View-only upgrade:
 * - Uses the same variables already provided by the existing dashboard controller
 * - No database schema changes
 * - No controller changes required
 * - Staff Dashboard remains untouched
 */

$pageTitle = 'Admin Dashboard';

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';

/* ============================================================
   EXISTING DATA — NORMALISED FOR THE DASHBOARD
============================================================ */
$pendingLeaveCount    = count($pendingLeaves ?? []);
$pendingExpenseCount  = count($pendingExpenses ?? []);
$pendingPurchaseCount = count($pendingPurchases ?? []);

$pendingApprovalCount =
    $pendingLeaveCount
    + $pendingExpenseCount
    + $pendingPurchaseCount;

$presentToday = (int)($attendanceStats['present'] ?? 0);
$totalStaff   = (int)($attendanceStats['total_staff'] ?? 0);
$lateToday    = (int)($attendanceStats['late'] ?? 0);
$attendancePercentage = (int)($attendanceStats['percentage'] ?? 0);

/*
 * This is deliberately labelled "Not marked present" rather than "Absent"
 * because staff may be on leave, outstation, or otherwise not expected onsite.
 */
$notMarkedPresent = max(0, $totalStaff - $presentToday);

$dueSoonCount = (int)($logisticsStats['due_soon'] ?? 0);
$overdueCount = (int)($logisticsStats['overdue'] ?? 0);

$diskDanger = ($diskUsage['status'] ?? '') === 'danger';

$hasAttention =
    $pendingApprovalCount > 0
    || $dueSoonCount > 0
    || $overdueCount > 0;

/* ============================================================
   TENDER KPI
============================================================ */
$tenderTarget = (int)(
    $tenderKpi['target']
    ?? $tenderKpi['monthly_target']
    ?? 4
);

if ($tenderTarget <= 0) {
    $tenderTarget = 4;
}

$allStaffKpi = $tenderKpi['staff_kpi'] ?? [];

/*
 * Management view: show lowest completion first so the admin immediately
 * sees who may need attention. Keep only the first 5 rows.
 */
usort($allStaffKpi, static function ($a, $b) {
    $aPct = (int)($a['percentage'] ?? 0);
    $bPct = (int)($b['percentage'] ?? 0);

    if ($aPct === $bPct) {
        return strcasecmp(
            (string)($a['name'] ?? ''),
            (string)($b['name'] ?? '')
        );
    }

    return $aPct <=> $bPct;
});

$managementTenderKpi = array_slice($allStaffKpi, 0, 5);

/* ============================================================
   ATTENDANCE EXCEPTIONS FROM THE EXISTING RECENT DATA
============================================================ */
$attendanceExceptions = [];

foreach (($recentAttendance ?? []) as $record) {
    $status = strtoupper(trim((string)($record['status'] ?? 'PRESENT')));

    if (in_array($status, ['LATE', 'ABSENT', 'MC', 'LEAVE'], true)) {
        $attendanceExceptions[] = $record;
    }

    if (count($attendanceExceptions) >= 4) {
        break;
    }
}

/* ============================================================
   QUICK ACTIONS
============================================================ */
$quickActions = [];

if (Auth::hasPermission('staff', 'view')) {
    $quickActions[] = [
        'href'  => 'index.php?page=staff',
        'icon'  => 'fa-users',
        'label' => 'Manage Staff'
    ];
}

if (
    Auth::hasPermission('leave', 'approve')
    || Auth::hasPermission('expense', 'approve')
    || Auth::hasPermission('purchase', 'approve')
) {
    $quickActions[] = [
        'href'  => 'index.php?page=approvals',
        'icon'  => 'fa-check-double',
        'label' => 'Review Approvals'
    ];
}

if (Auth::hasPermission('logistics', 'create')) {
    $quickActions[] = [
        'href'  => 'index.php?page=logistics',
        'icon'  => 'fa-truck-fast',
        'label' => 'Add Delivery'
    ];
}

if (Auth::hasPermission('tenders', 'create')) {
    $quickActions[] = [
        'href'  => 'index.php?page=tender_board&amp;section=kpi',
        'icon'  => 'fa-briefcase',
        'label' => 'Add Tender'
    ];
}

if (Auth::hasPermission('announcements', 'create')) {
    $quickActions[] = [
        'href'  => 'index.php?page=announcements',
        'icon'  => 'fa-bullhorn',
        'label' => 'Publish Announcement'
    ];
}

if (Auth::hasPermission('calendar', 'view')) {
    $quickActions[] = [
        'href'  => 'index.php?page=calendar',
        'icon'  => 'fa-calendar-days',
        'label' => 'Open Calendar'
    ];
}

$quickActions = array_slice($quickActions, 0, 6);
?>

<style>
/* ============================================================
   ADMIN DASHBOARD — MANAGEMENT UI
============================================================ */

.admin-dashboard {
    padding: 24px;
}

.admin-hero {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:20px;
    margin-bottom:16px;
}

.admin-eyebrow {
    margin-bottom:5px;
    color:#2563EB;
    font-size:8.5px;
    font-weight:850;
    letter-spacing:.08em;
    text-transform:uppercase;
}

.admin-hero h1 {
    margin:0;
    color:#0F172A;
    font-size:22px;
    font-weight:850;
    letter-spacing:-.025em;
}

.admin-hero p {
    margin:5px 0 0;
    color:#64748B;
    font-size:11px;
}

.admin-hero-meta {
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:7px;
    flex-wrap:wrap;
}

.admin-meta-pill {
    min-height:31px;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 9px;
    border:1px solid #E2E8F0;
    border-radius:8px;
    background:#FFFFFF;
    color:#64748B;
    font-size:8.5px;
    font-weight:800;
    text-decoration:none;
}

.admin-meta-pill.health-good {
    border-color:#D1FAE5;
    background:#F0FDF4;
    color:#047857;
}

.admin-meta-pill.health-warning {
    border-color:#FDE68A;
    background:#FFFBEB;
    color:#B45309;
}

/* KPI */
.admin-kpi-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    margin-bottom:14px;
}

.admin-kpi {
    position:relative;
    min-width:0;
    display:block;
    padding:14px;
    border:1px solid #E2E8F0;
    border-radius:12px;
    background:#FFFFFF;
    box-shadow:0 1px 2px rgba(15,23,42,.035);
    color:inherit;
    text-decoration:none;
    overflow:hidden;
    transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.admin-kpi[href]:hover {
    border-color:#BFDBFE;
    box-shadow:0 7px 18px rgba(15,23,42,.07);
    transform:translateY(-1px);
}

.admin-kpi-top {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}

.admin-kpi-label {
    color:#64748B;
    font-size:8.5px;
    font-weight:850;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.admin-kpi-icon {
    width:27px;
    height:27px;
    flex:0 0 27px;
    display:grid;
    place-items:center;
    border-radius:8px;
    background:#EFF6FF;
    color:#2563EB;
    font-size:10px;
}

.admin-kpi.success .admin-kpi-icon {
    background:#ECFDF5;
    color:#059669;
}

.admin-kpi.warning .admin-kpi-icon {
    background:#FFFBEB;
    color:#D97706;
}

.admin-kpi.danger .admin-kpi-icon {
    background:#FEF2F2;
    color:#DC2626;
}

.admin-kpi.purple .admin-kpi-icon {
    background:#F5F3FF;
    color:#7C3AED;
}

.admin-kpi-value {
    margin-top:10px;
    color:#0F172A;
    font-size:24px;
    font-weight:850;
    line-height:1;
}

.admin-kpi-value small {
    color:#64748B;
    font-size:9px;
    font-weight:750;
}

.admin-kpi-note {
    margin-top:6px;
    color:#64748B;
    font-size:8.5px;
    line-height:1.4;
}

.admin-kpi-linkhint {
    position:absolute;
    right:12px;
    bottom:10px;
    color:#93C5FD;
    font-size:8px;
}

/* Cards */
.admin-grid-primary {
    display:grid;
    grid-template-columns:minmax(0,2fr) minmax(320px,1fr);
    gap:10px;
    margin-bottom:10px;
}

.admin-grid-secondary {
    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(0,1fr);
    gap:10px;
    margin-bottom:10px;
}

.admin-card {
    min-width:0;
    overflow:hidden;
    border:1px solid #E2E8F0;
    border-radius:12px;
    background:#FFFFFF;
    box-shadow:0 1px 2px rgba(15,23,42,.035);
}

.admin-card-head {
    min-height:44px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:10px 13px;
    border-bottom:1px solid #E2E8F0;
}

.admin-card-title {
    display:flex;
    align-items:center;
    gap:7px;
    color:#0F172A;
    font-size:10px;
    font-weight:850;
}

.admin-card-title i {
    color:#2563EB;
}

.admin-card-link {
    color:#2563EB;
    font-size:8.5px;
    font-weight:800;
    text-decoration:none;
}

.admin-card-body {
    padding:12px 13px;
}

.admin-card-body.compact-empty {
    padding:16px 13px;
}

/* Attention */
.admin-attention-list,
.admin-list {
    margin:0;
    padding:0;
    list-style:none;
}

.admin-attention-item,
.admin-list-item {
    display:grid;
    grid-template-columns:auto minmax(0,1fr) auto;
    align-items:center;
    gap:10px;
    padding:9px 0;
    border-bottom:1px solid #F1F5F9;
}

.admin-attention-item:first-child,
.admin-list-item:first-child {
    padding-top:0;
}

.admin-attention-item:last-child,
.admin-list-item:last-child {
    padding-bottom:0;
    border-bottom:0;
}

.admin-list-icon {
    width:31px;
    height:31px;
    display:grid;
    place-items:center;
    border-radius:8px;
    background:#EFF6FF;
    color:#2563EB;
    font-size:10px;
}

.admin-list-icon.success {
    background:#ECFDF5;
    color:#059669;
}

.admin-list-icon.warning {
    background:#FFFBEB;
    color:#D97706;
}

.admin-list-icon.danger {
    background:#FEF2F2;
    color:#DC2626;
}

.admin-list-icon.purple {
    background:#F5F3FF;
    color:#7C3AED;
}

.admin-list-copy {
    min-width:0;
}

.admin-list-copy strong {
    display:block;
    overflow:hidden;
    color:#1E293B;
    font-size:9.5px;
    font-weight:800;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.admin-list-copy span {
    display:block;
    margin-top:2px;
    overflow:hidden;
    color:#94A3B8;
    font-size:8px;
    line-height:1.35;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.admin-count {
    min-width:25px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:4px 7px;
    border-radius:999px;
    background:#EFF6FF;
    color:#2563EB;
    font-size:8px;
    font-weight:850;
    white-space:nowrap;
}

.admin-count.warning {
    background:#FFFBEB;
    color:#B45309;
}

.admin-count.danger {
    background:#FEF2F2;
    color:#DC2626;
}

.admin-empty {
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-height:42px;
    color:#64748B;
    font-size:9px;
    text-align:center;
}

.admin-empty i {
    color:#10B981;
    font-size:13px;
}

/* Quick actions */
.admin-quick-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:7px;
    padding:11px 13px 13px;
}

.admin-quick {
    min-height:37px;
    display:flex;
    align-items:center;
    gap:8px;
    padding:8px 9px;
    border:1px solid #E2E8F0;
    border-radius:8px;
    background:#FFFFFF;
    color:#334155;
    font-size:8.5px;
    font-weight:800;
    text-decoration:none;
    transition:.18s ease;
}

.admin-quick:hover {
    border-color:#BFDBFE;
    background:#F8FBFF;
    color:#1D4ED8;
}

.admin-quick i {
    width:16px;
    color:#2563EB;
    text-align:center;
}

/* Attendance */
.admin-attendance-summary {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:7px;
    margin-bottom:12px;
}

.admin-attendance-box {
    padding:9px;
    border:1px solid #E2E8F0;
    border-radius:9px;
    background:#F8FAFC;
}

.admin-attendance-box strong {
    display:block;
    color:#0F172A;
    font-size:15px;
    font-weight:850;
}

.admin-attendance-box span {
    display:block;
    margin-top:3px;
    color:#94A3B8;
    font-size:7.5px;
    font-weight:800;
    text-transform:uppercase;
}

/* Tender */
.admin-progress-row {
    padding:8px 0;
    border-bottom:1px solid #F1F5F9;
}

.admin-progress-row:first-child {
    padding-top:0;
}

.admin-progress-row:last-child {
    padding-bottom:0;
    border-bottom:0;
}

.admin-progress-meta {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:5px;
}

.admin-progress-meta strong {
    overflow:hidden;
    color:#1E293B;
    font-size:9px;
    font-weight:800;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.admin-progress-meta span {
    color:#64748B;
    font-size:8px;
    font-weight:800;
    white-space:nowrap;
}

.admin-progress-track {
    height:4px;
    overflow:hidden;
    border-radius:999px;
    background:#E2E8F0;
}

.admin-progress-fill {
    height:100%;
    border-radius:999px;
    background:#2563EB;
}

.admin-progress-fill.low {
    background:#F59E0B;
}

.admin-progress-fill.complete {
    background:#10B981;
}

/* System strip */
.admin-system-strip {
    min-height:38px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:8px 12px;
    border:1px solid #E2E8F0;
    border-radius:10px;
    background:#FFFFFF;
    color:#64748B;
    font-size:8px;
}

.admin-system-strip strong {
    color:#334155;
}

.admin-system-status {
    display:inline-flex;
    align-items:center;
    gap:5px;
    color:#059669;
    font-weight:850;
}

.admin-system-status.danger {
    color:#DC2626;
}

.admin-system-strip a {
    color:#2563EB;
    font-weight:800;
    text-decoration:none;
}

/* Responsive */
@media (max-width:1150px) {
    .admin-kpi-grid {
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .admin-grid-primary {
        grid-template-columns:1fr;
    }
}

@media (max-width:760px) {
    .admin-dashboard {
        padding:16px 12px;
    }

    .admin-hero {
        flex-direction:column;
    }

    .admin-hero-meta {
        justify-content:flex-start;
    }

    .admin-grid-secondary {
        grid-template-columns:1fr;
    }

    .admin-attendance-summary {
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media (max-width:480px) {
    .admin-kpi-grid {
        grid-template-columns:1fr;
    }

    .admin-quick-grid {
        grid-template-columns:1fr;
    }

    .admin-system-strip {
        align-items:flex-start;
        flex-direction:column;
    }
}
</style>


<div class="admin-dashboard">

    <!-- ========================================================
         HERO
    ========================================================= -->
    <header class="admin-hero">

        <div>
            <div class="admin-eyebrow">
                <?= ($user['role'] ?? '') === 'super_admin'
                    ? 'System-wide overview'
                    : 'Administration overview' ?>
            </div>

            <h1>
                Good <?= date('H') < 12
                    ? 'morning'
                    : (date('H') < 18 ? 'afternoon' : 'evening') ?>,
                <?= htmlspecialchars($user['name']) ?>
            </h1>

            <p>
                Management priorities, workforce status and operational exceptions in one view.
            </p>
        </div>

        <div class="admin-hero-meta">

            <span class="admin-meta-pill">
                <i class="fa-regular fa-calendar"></i>
                <?= date('l, d M Y') ?>
            </span>

            <a href="index.php?page=system_health"
               class="admin-meta-pill <?= $diskDanger ? 'health-warning' : 'health-good' ?>">
                <i class="fa-solid <?= $diskDanger
                    ? 'fa-triangle-exclamation'
                    : 'fa-circle-check' ?>"></i>

                <?= $diskDanger
                    ? 'System needs attention'
                    : 'System healthy' ?>
            </a>

        </div>

    </header>


    <!-- ========================================================
         KPI
    ========================================================= -->
    <section class="admin-kpi-grid"
             aria-label="Management overview">

        <a href="index.php?page=attendance"
           class="admin-kpi success">

            <div class="admin-kpi-top">
                <span class="admin-kpi-label">Staff Clocked In</span>
                <span class="admin-kpi-icon">
                    <i class="fa-solid fa-user-check"></i>
                </span>
            </div>

            <div class="admin-kpi-value">
                <?= $presentToday ?>
                <small>/ <?= $totalStaff ?> staff</small>
            </div>

            <div class="admin-kpi-note">
                <?= $attendancePercentage ?>% marked present ·
                <?= $lateToday ?> late
            </div>

            <i class="fa-solid fa-arrow-right admin-kpi-linkhint"></i>
        </a>


        <a href="index.php?page=approvals"
           class="admin-kpi">

            <div class="admin-kpi-top">
                <span class="admin-kpi-label">Pending Approvals</span>
                <span class="admin-kpi-icon">
                    <i class="fa-solid fa-check-double"></i>
                </span>
            </div>

            <div class="admin-kpi-value">
                <?= $pendingApprovalCount ?>
                <small>requests</small>
            </div>

            <div class="admin-kpi-note">
                <?= $pendingLeaveCount ?> leave ·
                <?= $pendingExpenseCount ?> expense ·
                <?= $pendingPurchaseCount ?> purchase
            </div>

            <i class="fa-solid fa-arrow-right admin-kpi-linkhint"></i>
        </a>


        <a href="index.php?page=logistics"
           class="admin-kpi warning">

            <div class="admin-kpi-top">
                <span class="admin-kpi-label">Due Within 7 Days</span>
                <span class="admin-kpi-icon">
                    <i class="fa-regular fa-clock"></i>
                </span>
            </div>

            <div class="admin-kpi-value">
                <?= $dueSoonCount ?>
                <small>items</small>
            </div>

            <div class="admin-kpi-note">
                Upcoming client delivery / operational deadlines
            </div>

            <i class="fa-solid fa-arrow-right admin-kpi-linkhint"></i>
        </a>


        <a href="index.php?page=logistics"
           class="admin-kpi <?= $overdueCount > 0 ? 'danger' : 'purple' ?>">

            <div class="admin-kpi-top">
                <span class="admin-kpi-label">Overdue Items</span>
                <span class="admin-kpi-icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </span>
            </div>

            <div class="admin-kpi-value">
                <?= $overdueCount ?>
                <small>items</small>
            </div>

            <div class="admin-kpi-note">
                <?= $overdueCount > 0
                    ? 'Immediate follow-up required'
                    : 'No overdue operations' ?>
            </div>

            <i class="fa-solid fa-arrow-right admin-kpi-linkhint"></i>
        </a>

    </section>


    <!-- ========================================================
         PRIORITY + QUICK ACTIONS
    ========================================================= -->
    <section class="admin-grid-primary">

        <article class="admin-card">

            <div class="admin-card-head">

                <div class="admin-card-title">
                    <i class="fa-solid fa-bell"></i>
                    Requires Your Attention
                </div>

                <?php if ($pendingApprovalCount > 0): ?>
                <a class="admin-card-link"
                   href="index.php?page=approvals">
                    Approval Center &rarr;
                </a>
                <?php endif; ?>

            </div>

            <div class="admin-card-body <?= !$hasAttention ? 'compact-empty' : '' ?>">

                <?php if (!$hasAttention): ?>

                    <div class="admin-empty">
                        <i class="fa-regular fa-circle-check"></i>
                        Everything is up to date. No urgent action is required.
                    </div>

                <?php else: ?>

                    <ul class="admin-attention-list">

                        <?php if ($pendingLeaveCount > 0): ?>
                        <li class="admin-attention-item">

                            <span class="admin-list-icon">
                                <i class="fa-solid fa-calendar-minus"></i>
                            </span>

                            <div class="admin-list-copy">
                                <strong>Leave applications awaiting review</strong>
                                <span>Review dates and supporting documents.</span>
                            </div>

                            <span class="admin-count">
                                <?= $pendingLeaveCount ?>
                            </span>

                        </li>
                        <?php endif; ?>


                        <?php if ($pendingExpenseCount > 0): ?>
                        <li class="admin-attention-item">

                            <span class="admin-list-icon warning">
                                <i class="fa-solid fa-receipt"></i>
                            </span>

                            <div class="admin-list-copy">
                                <strong>Expense claims awaiting approval</strong>
                                <span>Verify claim details, receipts and payment information.</span>
                            </div>

                            <span class="admin-count warning">
                                <?= $pendingExpenseCount ?>
                            </span>

                        </li>
                        <?php endif; ?>


                        <?php if ($pendingPurchaseCount > 0): ?>
                        <li class="admin-attention-item">

                            <span class="admin-list-icon purple">
                                <i class="fa-solid fa-cart-shopping"></i>
                            </span>

                            <div class="admin-list-copy">
                                <strong>Purchase requests awaiting action</strong>
                                <span>Confirm requirement, vendor and budget allocation.</span>
                            </div>

                            <span class="admin-count">
                                <?= $pendingPurchaseCount ?>
                            </span>

                        </li>
                        <?php endif; ?>


                        <?php if ($overdueCount > 0): ?>
                        <li class="admin-attention-item">

                            <span class="admin-list-icon danger">
                                <i class="fa-solid fa-truck-ramp-box"></i>
                            </span>

                            <div class="admin-list-copy">
                                <strong>Operational delivery date has passed</strong>
                                <span>Contact the assigned PIC and update the delivery status.</span>
                            </div>

                            <span class="admin-count danger">
                                <?= $overdueCount ?>
                            </span>

                        </li>

                        <?php elseif ($dueSoonCount > 0): ?>

                        <li class="admin-attention-item">

                            <span class="admin-list-icon warning">
                                <i class="fa-regular fa-clock"></i>
                            </span>

                            <div class="admin-list-copy">
                                <strong>Deliveries due within seven days</strong>
                                <span>Confirm shipping and client contact arrangements.</span>
                            </div>

                            <span class="admin-count warning">
                                <?= $dueSoonCount ?>
                            </span>

                        </li>
                        <?php endif; ?>

                    </ul>

                <?php endif; ?>

            </div>

        </article>


        <article class="admin-card">

            <div class="admin-card-head">
                <div class="admin-card-title">
                    <i class="fa-solid fa-bolt"></i>
                    Quick Actions
                </div>
            </div>

            <div class="admin-quick-grid">

                <?php foreach ($quickActions as $action): ?>

                <a class="admin-quick"
                   href="<?= $action['href'] ?>">
                    <i class="fa-solid <?= htmlspecialchars($action['icon']) ?>"></i>
                    <?= htmlspecialchars($action['label']) ?>
                </a>

                <?php endforeach; ?>

            </div>

        </article>

    </section>


    <!-- ========================================================
         ATTENDANCE + TENDER KPI
    ========================================================= -->
    <section class="admin-grid-secondary">

        <!-- ATTENDANCE -->
        <article class="admin-card">

            <div class="admin-card-head">

                <div class="admin-card-title">
                    <i class="fa-solid fa-users-viewfinder"></i>
                    Today's Attendance
                </div>

                <a class="admin-card-link"
                   href="index.php?page=attendance">
                    View Attendance &rarr;
                </a>

            </div>

            <div class="admin-card-body">

                <div class="admin-attendance-summary">

                    <div class="admin-attendance-box">
                        <strong><?= $presentToday ?></strong>
                        <span>Present</span>
                    </div>

                    <div class="admin-attendance-box">
                        <strong><?= $lateToday ?></strong>
                        <span>Late</span>
                    </div>

                    <div class="admin-attendance-box">
                        <strong><?= $notMarkedPresent ?></strong>
                        <span>Not Marked Present</span>
                    </div>

                    <div class="admin-attendance-box">
                        <strong><?= $attendancePercentage ?>%</strong>
                        <span>Present Rate</span>
                    </div>

                </div>


                <?php if (empty($attendanceExceptions)): ?>

                    <div class="admin-empty">
                        <i class="fa-regular fa-circle-check"></i>
                        No recent attendance exceptions are shown.
                    </div>

                <?php else: ?>

                    <ul class="admin-list">

                        <?php foreach ($attendanceExceptions as $record): ?>
                        <?php
                            $status = strtoupper(
                                trim((string)($record['status'] ?? 'PRESENT'))
                            );

                            $statusLabel =
                                $status === 'LATE'
                                ? 'Late'
                                : (
                                    $status === 'ABSENT'
                                    ? 'Absent'
                                    : (
                                        $status === 'MC'
                                        ? 'MC'
                                        : (
                                            $status === 'LEAVE'
                                            ? 'On Leave'
                                            : 'Present'
                                        )
                                    )
                                );

                            $statusTone =
                                in_array($status, ['LATE', 'ABSENT'], true)
                                ? 'warning'
                                : 'success';
                        ?>

                        <li class="admin-list-item">

                            <span class="admin-list-icon <?= $statusTone ?>">
                                <i class="fa-solid fa-user-clock"></i>
                            </span>

                            <div class="admin-list-copy">
                                <strong>
                                    <?= htmlspecialchars(
                                        $record['name'] ?? 'Unknown staff'
                                    ) ?>
                                </strong>

                                <span>
                                    <?php if (!empty($record['clock_in'])): ?>
                                        <?= date(
                                            'd M · h:i A',
                                            strtotime($record['clock_in'])
                                        ) ?>
                                    <?php elseif (!empty($record['date'])): ?>
                                        <?= date(
                                            'd M',
                                            strtotime($record['date'])
                                        ) ?>
                                    <?php else: ?>
                                        Attendance record
                                    <?php endif; ?>
                                </span>
                            </div>

                            <span class="admin-count <?= $statusTone === 'warning' ? 'warning' : '' ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>

                        </li>

                        <?php endforeach; ?>

                    </ul>

                <?php endif; ?>

            </div>

        </article>


        <!-- TENDER KPI -->
        <article class="admin-card">

            <div class="admin-card-head">

                <div class="admin-card-title">
                    <i class="fa-solid fa-chart-simple"></i>
                    Tender KPI · <?= date('M Y') ?>
                </div>

                <a class="admin-card-link"
                   href="index.php?page=tender_board&amp;section=kpi">
                    View Tenders &rarr;
                </a>

            </div>

            <div class="admin-card-body">

                <?php if (empty($managementTenderKpi)): ?>

                    <div class="admin-empty">
                        <i class="fa-regular fa-circle-check"></i>
                        No tender KPI data is available this month.
                    </div>

                <?php else: ?>

                    <?php foreach ($managementTenderKpi as $staffKpi): ?>
                    <?php
                        $percentage = min(
                            100,
                            max(0, (int)($staffKpi['percentage'] ?? 0))
                        );

                        $count = (int)($staffKpi['count'] ?? 0);

                        $progressClass =
                            $percentage >= 100
                            ? 'complete'
                            : ($percentage < 50 ? 'low' : '');
                    ?>

                    <div class="admin-progress-row">

                        <div class="admin-progress-meta">

                            <strong>
                                <?= htmlspecialchars(
                                    $staffKpi['name'] ?? 'Unknown staff'
                                ) ?>
                            </strong>

                            <span>
                                <?= $count ?>
                                / <?= $tenderTarget ?>
                                · <?= $percentage ?>%
                            </span>

                        </div>

                        <div class="admin-progress-track">
                            <div class="admin-progress-fill <?= $progressClass ?>"
                                 style="width:<?= $percentage ?>%;"></div>
                        </div>

                    </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </article>

    </section>


    <!-- ========================================================
         SYSTEM STATUS
    ========================================================= -->
    <div class="admin-system-strip">

        <span>

            <span class="admin-system-status <?= $diskDanger ? 'danger' : '' ?>">

                <i class="fa-solid <?= $diskDanger
                    ? 'fa-triangle-exclamation'
                    : 'fa-circle-check' ?>"></i>

                <?= $diskDanger
                    ? 'Storage warning'
                    : 'System operational' ?>

            </span>

            &nbsp;·&nbsp;

            <strong>
                <?= htmlspecialchars(
                    (string)($diskUsage['percentage'] ?? 0)
                ) ?>% disk used
            </strong>

            &nbsp;·&nbsp;

            <?= htmlspecialchars(
                (string)($diskUsage['free'] ?? '-')
            ) ?> free

        </span>

        <a href="index.php?page=system_health">
            System Health &amp; Backup &rarr;
        </a>

    </div>

</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
