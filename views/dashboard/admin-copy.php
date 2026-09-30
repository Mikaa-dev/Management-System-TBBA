<?php
/** Focused system overview for administrators. */
$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';

$pendingApprovalCount = count($pendingLeaves ?? []) + count($pendingExpenses ?? []) + count($pendingPurchases ?? []);
$hasAttention = $pendingApprovalCount > 0 || ($logisticsStats['due_soon'] ?? 0) > 0 || ($logisticsStats['overdue'] ?? 0) > 0;
$diskDanger = ($diskUsage['status'] ?? '') === 'danger';
$topStaffKpi = array_slice($tenderKpi['staff_kpi'] ?? [], 0, 5);
?>

<div class="dashboard-shell">
    <header class="dashboard-heading">
        <div>
            <div class="dashboard-eyebrow"><?= ($user['role'] ?? '') === 'super_admin' ? 'System-wide overview' : 'Administration overview' ?></div>
            <h1>Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars($user['name']) ?></h1>
            <p>Here is what needs your attention today.</p>
        </div>
        <div class="dashboard-heading-meta">
            <span class="dashboard-date"><i class="fa-regular fa-calendar"></i><?= date('l, d M Y') ?></span>
            <a href="index.php?page=system_health" class="dashboard-health <?= $diskDanger ? 'is-warning' : 'is-good' ?>">
                <i class="fa-solid <?= $diskDanger ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i>
                <?= $diskDanger ? 'System needs attention' : 'System healthy' ?>
            </a>
        </div>
    </header>

    <section class="dashboard-kpi-grid" aria-label="Key performance indicators">
        <article class="dashboard-kpi tone-success">
            <div class="dashboard-kpi-top"><span class="dashboard-kpi-label">Present today</span><span class="dashboard-kpi-icon"><i class="fa-solid fa-user-check"></i></span></div>
            <div class="dashboard-kpi-value"><?= (int)($attendanceStats['present'] ?? 0) ?> <small>/ <?= (int)($attendanceStats['total_staff'] ?? 0) ?> staff</small></div>
            <div class="dashboard-kpi-note"><?= (int)($attendanceStats['percentage'] ?? 0) ?>% attendance rate · <?= (int)($attendanceStats['late'] ?? 0) ?> late</div>
        </article>
        <article class="dashboard-kpi">
            <div class="dashboard-kpi-top"><span class="dashboard-kpi-label">Pending approvals</span><span class="dashboard-kpi-icon"><i class="fa-solid fa-check-double"></i></span></div>
            <div class="dashboard-kpi-value"><?= $pendingApprovalCount ?> <small>requests</small></div>
            <div class="dashboard-kpi-note">Leave, expense and purchase requests</div>
        </article>
        <article class="dashboard-kpi tone-warning">
            <div class="dashboard-kpi-top"><span class="dashboard-kpi-label">Due within 7 days</span><span class="dashboard-kpi-icon"><i class="fa-regular fa-clock"></i></span></div>
            <div class="dashboard-kpi-value"><?= (int)($logisticsStats['due_soon'] ?? 0) ?> <small>items</small></div>
            <div class="dashboard-kpi-note">Project deliveries scheduled for clients</div>
        </article>
        <article class="dashboard-kpi <?= ($logisticsStats['overdue'] ?? 0) > 0 ? 'tone-danger' : 'tone-purple' ?>">
            <div class="dashboard-kpi-top"><span class="dashboard-kpi-label">Overdue items</span><span class="dashboard-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></span></div>
            <div class="dashboard-kpi-value"><?= (int)($logisticsStats['overdue'] ?? 0) ?> <small>items</small></div>
            <div class="dashboard-kpi-note"><?= ($logisticsStats['overdue'] ?? 0) > 0 ? 'Immediate follow-up required' : 'No overdue operations' ?></div>
        </article>
    </section>

    <section class="dashboard-primary-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <div class="dashboard-card-title"><i class="fa-solid fa-bell"></i>Requires your attention</div>
                <?php if ($pendingApprovalCount > 0): ?><a class="dashboard-card-link" href="index.php?page=approvals">Open Approval Center &rarr;</a><?php endif; ?>
            </div>
            <div class="dashboard-card-body">
                <?php if (!$hasAttention): ?>
                    <div class="dashboard-empty"><i class="fa-regular fa-circle-check"></i>Everything is up to date. No urgent action is required.</div>
                <?php else: ?>
                    <ul class="dashboard-attention-list">
                        <?php if (count($pendingLeaves ?? []) > 0): ?>
                        <li class="dashboard-attention-item">
                            <span class="dashboard-list-icon"><i class="fa-solid fa-calendar-minus"></i></span>
                            <div class="dashboard-list-copy"><strong>Leave applications awaiting review</strong><span>Review the requested dates and supporting documents.</span></div>
                            <span class="dashboard-count"><?= count($pendingLeaves) ?></span>
                        </li>
                        <?php endif; ?>
                        <?php if (count($pendingExpenses ?? []) > 0): ?>
                        <li class="dashboard-attention-item">
                            <span class="dashboard-list-icon warning"><i class="fa-solid fa-receipt"></i></span>
                            <div class="dashboard-list-copy"><strong>Expense claims awaiting approval</strong><span>Verify claim details, receipts and payment information.</span></div>
                            <span class="dashboard-count warning"><?= count($pendingExpenses) ?></span>
                        </li>
                        <?php endif; ?>
                        <?php if (count($pendingPurchases ?? []) > 0): ?>
                        <li class="dashboard-attention-item">
                            <span class="dashboard-list-icon purple"><i class="fa-solid fa-cart-shopping"></i></span>
                            <div class="dashboard-list-copy"><strong>Purchase requests awaiting approval</strong><span>Confirm requirement, vendor and budget allocation.</span></div>
                            <span class="dashboard-count"><?= count($pendingPurchases) ?></span>
                        </li>
                        <?php endif; ?>
                        <?php if (($logisticsStats['overdue'] ?? 0) > 0): ?>
                        <li class="dashboard-attention-item">
                            <span class="dashboard-list-icon danger"><i class="fa-solid fa-truck-ramp-box"></i></span>
                            <div class="dashboard-list-copy"><strong>A project delivery date has passed</strong><span>Contact the assigned PIC and update the delivery status.</span></div>
                            <span class="dashboard-count danger"><?= (int)$logisticsStats['overdue'] ?></span>
                        </li>
                        <?php elseif (($logisticsStats['due_soon'] ?? 0) > 0): ?>
                        <li class="dashboard-attention-item">
                            <span class="dashboard-list-icon warning"><i class="fa-regular fa-clock"></i></span>
                            <div class="dashboard-list-copy"><strong>Project deliveries due within seven days</strong><span>Confirm the shipping and client contact arrangements.</span></div>
                            <span class="dashboard-count warning"><?= (int)$logisticsStats['due_soon'] ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header"><div class="dashboard-card-title"><i class="fa-solid fa-bolt"></i>Quick actions</div></div>
            <div class="dashboard-quick-grid">
                <?php if (Auth::hasPermission('staff', 'view')): ?><a class="dashboard-quick-action" href="index.php?page=staff"><i class="fa-solid fa-users"></i>Manage staff</a><?php endif; ?>
                <?php if (Auth::hasPermission('leave', 'approve') || Auth::hasPermission('expense', 'approve') || Auth::hasPermission('purchase', 'approve')): ?><a class="dashboard-quick-action" href="index.php?page=approvals"><i class="fa-solid fa-check-double"></i>Review approvals</a><?php endif; ?>
                <?php if (Auth::hasPermission('logistics', 'create')): ?><a class="dashboard-quick-action" href="index.php?page=logistics"><i class="fa-solid fa-truck-fast"></i>Add delivery</a><?php endif; ?>
                <?php if (Auth::hasPermission('tenders', 'create')): ?><a class="dashboard-quick-action" href="index.php?page=tender_board&amp;section=kpi"><i class="fa-solid fa-briefcase"></i>Add KPI record</a><?php endif; ?>
                <?php if (Auth::hasPermission('letters', 'view')): ?><a class="dashboard-quick-action" href="index.php?page=inquiries"><i class="fa-solid fa-inbox"></i>Open inbox</a><?php endif; ?>
                <?php if (Auth::hasPermission('attendance', 'view')): ?><a class="dashboard-quick-action" href="index.php?page=attendance"><i class="fa-solid fa-location-dot"></i>Attendance</a><?php endif; ?>
            </div>
        </article>
    </section>

    <section class="dashboard-secondary-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <div class="dashboard-card-title"><i class="fa-solid fa-clock-rotate-left"></i>Recent attendance</div>
                <a class="dashboard-card-link" href="index.php?page=attendance">View all &rarr;</a>
            </div>
            <div class="dashboard-card-body">
                <?php if (empty($recentAttendance)): ?>
                    <div class="dashboard-empty">No recent attendance records.</div>
                <?php else: ?>
                    <ul class="dashboard-activity-list">
                        <?php foreach (array_slice($recentAttendance, 0, 5) as $ra):
                            $status = strtoupper(trim($ra['status'] ?? 'PRESENT'));
                            $statusLabel = $status === 'LATE' ? 'Late' : ($status === 'MC' ? 'MC' : ($status === 'LEAVE' ? 'On Leave' : ($status === 'ABSENT' ? 'Absent' : 'On Time')));
                        ?>
                        <li class="dashboard-activity-item">
                            <span class="dashboard-list-icon <?= in_array($status, ['LATE','ABSENT'], true) ? 'warning' : 'success' ?>"><i class="fa-solid fa-user-clock"></i></span>
                            <div class="dashboard-list-copy"><strong><?= htmlspecialchars($ra['name'] ?? 'Unknown staff') ?></strong><span><?= !empty($ra['clock_in']) ? date('d M · h:i A', strtotime($ra['clock_in'])) : date('d M', strtotime($ra['date'])) ?><?= !empty($ra['clock_out']) ? ' — ' . date('h:i A', strtotime($ra['clock_out'])) : '' ?></span></div>
                            <span class="dashboard-count"><?= $statusLabel ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <div class="dashboard-card-title"><i class="fa-solid fa-chart-simple"></i>Tender KPI · <?= date('M Y') ?></div>
                <a class="dashboard-card-link" href="index.php?page=tender_board&amp;section=kpi">View tenders &rarr;</a>
            </div>
            <div class="dashboard-card-body">
                <?php if (empty($topStaffKpi)): ?>
                    <div class="dashboard-empty">No tender KPI data is available this month.</div>
                <?php else: ?>
                    <?php foreach ($topStaffKpi as $staffKpi): ?>
                    <div class="dashboard-progress-row">
                        <div class="dashboard-progress-meta"><strong><?= htmlspecialchars($staffKpi['name']) ?></strong><span><?= (int)$staffKpi['count'] ?> / 4 · <?= (int)$staffKpi['percentage'] ?>%</span></div>
                        <div class="dashboard-progress-track"><div class="dashboard-progress-fill <?= ($staffKpi['percentage'] ?? 0) >= 100 ? 'complete' : '' ?>" style="width: <?= min(100, max(0, (int)($staffKpi['percentage'] ?? 0))) ?>%"></div></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <div class="dashboard-system-strip">
        <span><span class="dashboard-status-pill <?= $diskDanger ? 'danger' : '' ?>"><i class="fa-solid <?= $diskDanger ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i><?= $diskDanger ? 'Storage warning' : 'System operational' ?></span> &nbsp; <strong><?= htmlspecialchars((string)($diskUsage['percentage'] ?? 0)) ?>% disk used</strong> · <?= htmlspecialchars((string)($diskUsage['free'] ?? '-')) ?> free</span>
        <a href="index.php?page=system_health">System Health & Backup &rarr;</a>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
