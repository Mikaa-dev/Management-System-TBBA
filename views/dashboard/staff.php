<?php
/** Focused role-based workspace for staff, HR, finance and managers. */
$pageTitle = 'Staff Dashboard';
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';

$role = $user['role'] ?? 'staff';
$isPeopleRole = in_array($role, ['hr', 'manager'], true);
$isFinanceRole = in_array($role, ['finance', 'auditor'], true);
$annualBalance = '--';
$medicalBalance = '--';
foreach ($myLeaveBalances ?? [] as $balance) {
    if (($balance['code'] ?? '') === 'AL' || stripos($balance['name'] ?? '', 'Annual') !== false) $annualBalance = $balance['remaining'];
    if (in_array(($balance['code'] ?? ''), ['ML', 'MC'], true) || stripos($balance['name'] ?? '', 'Medical') !== false) $medicalBalance = $balance['remaining'];
}

$myOpenRequests = (int)($myPendingLeaves ?? 0) + (int)($myPendingExpenses ?? 0) + (int)($myPendingPurchases ?? 0);
$roleStats = [];
if ($isPeopleRole) {
    $roleStats = [
        ['label' => 'Present today', 'value' => (int)($attendanceStats['present'] ?? 0), 'suffix' => '/ ' . (int)($attendanceStats['total_staff'] ?? 0) . ' staff', 'note' => (int)($attendanceStats['percentage'] ?? 0) . '% attendance rate', 'icon' => 'fa-user-check', 'tone' => 'tone-success'],
        ['label' => 'Pending leave', 'value' => count($pendingLeaves ?? []), 'suffix' => 'requests', 'note' => 'Awaiting management action', 'icon' => 'fa-calendar-minus', 'tone' => ''],
        ['label' => 'Late today', 'value' => (int)($attendanceStats['late'] ?? 0), 'suffix' => 'staff', 'note' => 'Attendance exceptions today', 'icon' => 'fa-clock', 'tone' => 'tone-warning'],
        ['label' => 'Total headcount', 'value' => (int)($totalHeadcount ?? 0), 'suffix' => 'employees', 'note' => 'Active company personnel', 'icon' => 'fa-users', 'tone' => 'tone-purple'],
    ];
} elseif ($isFinanceRole) {
    $roleStats = [
        ['label' => 'Expense claims', 'value' => count($pendingExpenses ?? []), 'suffix' => 'pending', 'note' => 'Awaiting verification or payout', 'icon' => 'fa-receipt', 'tone' => 'tone-warning'],
        ['label' => 'Purchase requests', 'value' => count($pendingPurchases ?? []), 'suffix' => 'pending', 'note' => 'Awaiting procurement action', 'icon' => 'fa-cart-shopping', 'tone' => ''],
        ['label' => 'Budget utilized', 'value' => 'RM ' . number_format((float)($budgetUsed ?? 0), 0), 'suffix' => '', 'note' => 'Confirmed and paid purchases', 'icon' => 'fa-coins', 'tone' => 'tone-success'],
        ['label' => 'My open requests', 'value' => $myOpenRequests, 'suffix' => 'requests', 'note' => 'Your own pending submissions', 'icon' => 'fa-folder-open', 'tone' => 'tone-purple'],
    ];
} else {
    $roleStats = [
        ['label' => 'Attendance this month', 'value' => (int)($myAttendanceMonth['total_present'] ?? 0), 'suffix' => 'days', 'note' => !empty($todayAttendance['clock_in']) ? 'Clocked in at ' . date('h:i A', strtotime($todayAttendance['clock_in'])) : 'Not clocked in today', 'icon' => 'fa-user-clock', 'tone' => 'tone-success'],
        ['label' => 'Annual leave balance', 'value' => $annualBalance, 'suffix' => 'days', 'note' => $medicalBalance . ' medical leave days available', 'icon' => 'fa-umbrella-beach', 'tone' => ''],
        ['label' => 'Monthly tender KPI', 'value' => (int)($myKpi['count'] ?? 0), 'suffix' => '/ 4 tenders', 'note' => (int)($myKpi['percentage'] ?? 0) . '% of monthly target', 'icon' => 'fa-briefcase', 'tone' => 'tone-purple'],
        ['label' => 'My open requests', 'value' => $myOpenRequests, 'suffix' => 'requests', 'note' => 'Leave, expense and purchase', 'icon' => 'fa-folder-open', 'tone' => 'tone-warning'],
    ];
}

$attentionItems = [];
if ($isPeopleRole && Auth::hasPermission('leave', 'approve') && count($pendingLeaves ?? []) > 0) {
    $attentionItems[] = ['icon' => 'fa-calendar-minus', 'tone' => '', 'title' => 'Leave applications awaiting review', 'note' => 'Review dates and supporting evidence in Approval Center.', 'count' => count($pendingLeaves), 'count_tone' => ''];
}
if ($isFinanceRole && Auth::hasPermission('expense', 'approve') && count($pendingExpenses ?? []) > 0) {
    $attentionItems[] = ['icon' => 'fa-receipt', 'tone' => 'warning', 'title' => 'Expense claims awaiting verification', 'note' => 'Check receipts, claim details and payment information.', 'count' => count($pendingExpenses), 'count_tone' => 'warning'];
}
if ($isFinanceRole && Auth::hasPermission('purchase', 'approve') && count($pendingPurchases ?? []) > 0) {
    $attentionItems[] = ['icon' => 'fa-cart-shopping', 'tone' => 'purple', 'title' => 'Purchase requests awaiting action', 'note' => 'Review the requirement, vendor and available budget.', 'count' => count($pendingPurchases), 'count_tone' => ''];
}
if (empty($todayAttendance['clock_in']) && Auth::hasPermission('attendance', 'view')) {
    $attentionItems[] = ['icon' => 'fa-location-crosshairs', 'tone' => 'warning', 'title' => 'You have not clocked in today', 'note' => 'Open GPS Attendance when you arrive at the workplace.', 'count' => 'Today', 'count_tone' => 'warning'];
}
if (($myLogisticsStats['overdue'] ?? 0) > 0) {
    $attentionItems[] = ['icon' => 'fa-truck-ramp-box', 'tone' => 'danger', 'title' => 'An assigned delivery is overdue', 'note' => 'Confirm the client delivery and update its status.', 'count' => (int)$myLogisticsStats['overdue'], 'count_tone' => 'danger'];
} elseif (($myLogisticsStats['due_soon'] ?? 0) > 0) {
    $attentionItems[] = ['icon' => 'fa-clock', 'tone' => 'warning', 'title' => 'Assigned deliveries are due soon', 'note' => 'Confirm shipping and client contact arrangements within seven days.', 'count' => (int)$myLogisticsStats['due_soon'], 'count_tone' => 'warning'];
}
if ($myOpenRequests > 0) {
    $attentionItems[] = ['icon' => 'fa-hourglass-half', 'tone' => '', 'title' => 'Your submissions are still pending', 'note' => 'You can review their current status from each module.', 'count' => $myOpenRequests, 'count_tone' => ''];
}

$quickActions = [];
if (Auth::hasPermission('attendance', 'view')) $quickActions[] = ['attendance', 'fa-location-crosshairs', 'Clock in / out'];
if (Auth::hasPermission('leave', 'create')) $quickActions[] = ['leave', 'fa-calendar-plus', 'Apply for leave'];
if (Auth::hasPermission('expense', 'create')) $quickActions[] = ['expense', 'fa-receipt', 'Submit expense'];
if (Auth::hasPermission('purchase', 'create')) $quickActions[] = ['purchase', 'fa-cart-plus', 'Request purchase'];
if (Auth::hasPermission('logistics', 'view')) $quickActions[] = ['logistics', 'fa-truck-fast', 'Delivery & demo'];
if (Auth::hasPermission('tenders', 'create')) $quickActions[] = ['tenders', 'fa-briefcase', 'Submit tender'];
?>

<div class="dashboard-shell">
    <header class="dashboard-heading">
        <div>
            <div class="dashboard-eyebrow"><?= htmlspecialchars(Auth::roleLabel($role)) ?> workspace</div>
            <h1>Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars($user['name']) ?></h1>
            <p><?= $isPeopleRole ? 'Your workforce priorities and attendance overview.' : ($isFinanceRole ? 'Your finance and procurement priorities.' : 'Your workday, requests and assignments in one place.') ?></p>
        </div>
        <div class="dashboard-heading-meta"><span class="dashboard-date"><i class="fa-regular fa-calendar"></i><?= date('l, d M Y') ?></span></div>
    </header>

    <section class="dashboard-kpi-grid" aria-label="Key performance indicators">
        <?php foreach ($roleStats as $stat): ?>
        <article class="dashboard-kpi <?= $stat['tone'] ?>">
            <div class="dashboard-kpi-top"><span class="dashboard-kpi-label"><?= htmlspecialchars($stat['label']) ?></span><span class="dashboard-kpi-icon"><i class="fa-solid <?= $stat['icon'] ?>"></i></span></div>
            <div class="dashboard-kpi-value"><?= htmlspecialchars((string)$stat['value']) ?> <?php if ($stat['suffix'] !== ''): ?><small><?= htmlspecialchars($stat['suffix']) ?></small><?php endif; ?></div>
            <div class="dashboard-kpi-note"><?= htmlspecialchars($stat['note']) ?></div>
        </article>
        <?php endforeach; ?>
    </section>

    <section class="dashboard-primary-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <div class="dashboard-card-title"><i class="fa-solid fa-bell"></i>Requires your attention</div>
                <?php if (($isPeopleRole || $isFinanceRole) && (Auth::hasPermission('leave', 'approve') || Auth::hasPermission('expense', 'approve') || Auth::hasPermission('purchase', 'approve'))): ?><a class="dashboard-card-link" href="index.php?page=approvals">Approval Center &rarr;</a><?php endif; ?>
            </div>
            <div class="dashboard-card-body">
                <?php if (empty($attentionItems)): ?>
                    <div class="dashboard-empty"><i class="fa-regular fa-circle-check"></i>You are all caught up. No urgent action is required.</div>
                <?php else: ?>
                    <ul class="dashboard-attention-list">
                        <?php foreach ($attentionItems as $item): ?>
                        <li class="dashboard-attention-item">
                            <span class="dashboard-list-icon <?= $item['tone'] ?>"><i class="fa-solid <?= $item['icon'] ?>"></i></span>
                            <div class="dashboard-list-copy"><strong><?= htmlspecialchars($item['title']) ?></strong><span><?= htmlspecialchars($item['note']) ?></span></div>
                            <span class="dashboard-count <?= $item['count_tone'] ?>"><?= htmlspecialchars((string)$item['count']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header"><div class="dashboard-card-title"><i class="fa-solid fa-bolt"></i>Quick actions</div></div>
            <div class="dashboard-quick-grid">
                <?php foreach ($quickActions as [$page, $icon, $label]): ?>
                    <a class="dashboard-quick-action" href="index.php?page=<?= urlencode($page) ?>"><i class="fa-solid <?= $icon ?>"></i><?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <section class="dashboard-secondary-grid">
        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <div class="dashboard-card-title"><i class="fa-solid fa-briefcase"></i>Recent tenders</div>
                <?php if (Auth::hasPermission('tenders', 'view')): ?><a class="dashboard-card-link" href="index.php?page=tender_board&amp;section=kpi">View all &rarr;</a><?php endif; ?>
            </div>
            <div class="dashboard-card-body">
                <?php if (empty($recentTenders)): ?>
                    <div class="dashboard-empty">No recent tender submissions.</div>
                <?php else: ?>
                    <ul class="dashboard-activity-list">
                        <?php foreach (array_slice($recentTenders, 0, 5) as $tender):
                            $tenderStatus = str_replace('_', ' ', $tender['status'] ?? 'unknown');
                        ?>
                        <li class="dashboard-activity-item">
                            <span class="dashboard-list-icon purple"><i class="fa-solid fa-folder-open"></i></span>
                            <div class="dashboard-list-copy"><strong><?= htmlspecialchars($tender['project_name'] ?? 'Untitled tender') ?></strong><span><?= htmlspecialchars($tender['client_name'] ?? 'No client') ?> · <?= Helper::rm($tender['project_value'] ?? 0) ?></span></div>
                            <span class="dashboard-count"><?= htmlspecialchars(ucwords($tenderStatus)) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="dashboard-card-header">
                <div class="dashboard-card-title"><i class="fa-solid fa-list-check"></i>My current work</div>
                <?php if (!empty($myLogisticsRecords)): ?><a class="dashboard-card-link" href="index.php?page=logistics">Open tracker &rarr;</a><?php endif; ?>
            </div>
            <div class="dashboard-card-body">
                <ul class="dashboard-request-list">
                    <li class="dashboard-request-item"><span class="dashboard-list-icon"><i class="fa-solid fa-calendar-minus"></i></span><div class="dashboard-list-copy"><strong>Leave applications</strong><span>Pending personal requests</span></div><span class="dashboard-count"><?= (int)($myPendingLeaves ?? 0) ?></span></li>
                    <li class="dashboard-request-item"><span class="dashboard-list-icon warning"><i class="fa-solid fa-receipt"></i></span><div class="dashboard-list-copy"><strong>Expense claims</strong><span>Pending personal claims</span></div><span class="dashboard-count"><?= (int)($myPendingExpenses ?? 0) ?></span></li>
                    <li class="dashboard-request-item"><span class="dashboard-list-icon purple"><i class="fa-solid fa-cart-shopping"></i></span><div class="dashboard-list-copy"><strong>Purchase requests</strong><span>Pending personal requests</span></div><span class="dashboard-count"><?= (int)($myPendingPurchases ?? 0) ?></span></li>
                    <?php foreach (array_slice($myLogisticsRecords ?? [], 0, 2) as $record): ?>
                    <li class="dashboard-request-item">
                        <?php $isDemoItem = ($record['record_type'] ?? '') === 'demo_item'; ?>
                        <span class="dashboard-list-icon <?= !empty($record['is_overdue']) ? 'danger' : 'success' ?>"><i class="fa-solid <?= $isDemoItem ? 'fa-box-open' : 'fa-truck-fast' ?>"></i></span>
                        <div class="dashboard-list-copy"><strong><?= htmlspecialchars($record['item_name'] ?? 'Assigned item') ?></strong><span><?php if ($isDemoItem): ?><?= htmlspecialchars($record['supplier_name'] ?? '') ?> · Received <?= !empty($record['received_date']) ? date('d M Y', strtotime($record['received_date'])) : '-' ?><?php else: ?><?= htmlspecialchars($record['client_name'] ?? '') ?> · Delivery <?= !empty($record['delivery_date']) ? date('d M Y', strtotime($record['delivery_date'])) : '-' ?><?php endif; ?></span></div>
                        <span class="dashboard-count <?= !empty($record['is_overdue']) ? 'danger' : '' ?>"><?= !empty($record['is_overdue']) ? 'Overdue' : ($isDemoItem ? 'Received' : 'Active') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </article>
    </section>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
