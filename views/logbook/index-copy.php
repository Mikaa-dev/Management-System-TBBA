<?php
$pageTitle = 'Weekly Logbook';
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
require __DIR__ . '/../layouts/navbar.php';

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$csrfToken = Helper::csrfToken();
$canViewStaffReports = $canViewStaffReports ?? Auth::hasPermission('logbook', 'view_staff_reports');
$view = $view ?? 'my';

$statusMeta = static function (?string $status): array {
    $status = strtolower((string) $status);
    return match ($status) {
        'draft' => ['Draft', '#92400E', '#FEF3C7', 'fa-pen'],
        'submitted' => ['Submitted', '#1D4ED8', '#DBEAFE', 'fa-paper-plane'],
        default => ['Not Started', '#475569', '#F1F5F9', 'fa-circle'],
    };
};
?>

<style>
/* Logbook v2.4 — Staff Reports are view-only */
.logbook-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
.logbook-tab { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid #CBD5E1; border-radius:8px; background:#fff; color:#475569; text-decoration:none; font-size:13px; font-weight:700; }
.logbook-tab.active { color:#fff; background:#2563EB; border-color:#2563EB; }
.logbook-container { display:flex; gap:24px; align-items:flex-start; flex-wrap:wrap; }
.logbook-left { flex:1; min-width:400px; }
.logbook-right { flex:1; min-width:400px; position:sticky; top:80px; }
.day-accordion { margin-bottom:12px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-card); overflow:hidden; }
.day-header { padding:15px 20px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; background:#F8FAFC; border-bottom:1px solid transparent; transition:.2s; }
.day-header:hover { background:#F1F5F9; }
.day-header.active { border-bottom-color:var(--border-color); }
.day-title { font-weight:700; color:var(--text-dark); font-size:15px; }
.day-subtitle { font-weight:normal; color:var(--text-muted); font-size:13px; margin-left:8px; }
.day-status { padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700; margin-right:12px; }
.status-Not { background:#F1F5F9; color:#475569; }
.status-Draft { background:#FEF3C7; color:#92400E; }
.status-Completed { background:#D1FAE5; color:#065F46; }
.day-body { padding:20px; display:none; }
.day-body.active { display:block; }
.form-row { display:flex; gap:16px; margin-bottom:16px; flex-wrap:wrap; }
.form-group { flex:1; min-width:150px; }
.form-group label { display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px; font-weight:600; }
.form-control { width:100%; padding:8px 12px; border:1px solid var(--border-color); border-radius:6px; font-size:13px; font-family:inherit; }
.activity-block { border:1px solid #E2E8F0; border-radius:8px; padding:16px; margin-bottom:16px; background:#FAFAFA; position:relative; }
.activity-header { display:flex; gap:16px; align-items:flex-start; margin-bottom:12px; }
.activity-title-wrap { flex:2; }
.activity-photos-wrap { flex:1; background:#fff; border:1px dashed #CBD5E1; border-radius:6px; padding:10px; text-align:center; }
.action-list { margin-top:10px; display:flex; flex-direction:column; gap:8px; }
.action-item { display:flex; gap:8px; align-items:center; }
.action-drag { color:#94A3B8; cursor:grab; }
.photo-grid { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
.photo-thumb { position:relative; width:60px; height:60px; border-radius:4px; border:1px solid #E2E8F0; overflow:hidden; }
.photo-thumb img { width:100%; height:100%; object-fit:cover; }
.photo-remove { position:absolute; top:2px; right:2px; background:rgba(0,0,0,.5); color:#fff; border-radius:50%; width:16px; height:16px; display:flex; align-items:center; justify-content:center; font-size:10px; cursor:pointer; }
.preview-paper { background:#fff; padding:30px; box-shadow:0 10px 25px rgba(0,0,0,.05); border:1px solid #E2E8F0; border-radius:4px; min-height:800px; font-family:Helvetica,Arial,sans-serif; font-size:12px; color:#000; }
.preview-header { border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:flex-start; }
.preview-header img { max-height:40px; }
.preview-title { text-align:right; font-weight:bold; }
.preview-info-table { width:100%; border-collapse:collapse; margin-bottom:20px; font-size:10px; }
.preview-info-table th { background:#555; color:#fff; text-align:left; padding:4px; border:1px solid #000; width:25%; }
.preview-info-table td { padding:4px; border:1px solid #000; text-transform:uppercase; }
.preview-day { margin-bottom:20px; }
.preview-day-header { background:#E2E8F0; padding:4px; font-weight:bold; border:1px solid #000; text-transform:uppercase; font-size:10px; }
.preview-act-table { width:100%; border-collapse:collapse; margin-top:-1px; }
.preview-act-table td { border:1px solid #000; padding:6px; vertical-align:top; }
.preview-act-num { width:5%; text-align:center; font-weight:bold; }
.preview-act-title { font-weight:bold; text-transform:uppercase; margin-bottom:4px; }
.preview-act-actions { margin:4px 0 10px 15px; padding:0; color:#D92B2B; font-weight:bold; font-size:10px; }
.preview-act-actions li span { color:#000; font-weight:normal; }
.preview-photos { display:flex; gap:4px; flex-wrap:wrap; margin-top:8px; }
.preview-photos img { height:60px; border:1px solid #ccc; }

.team-summary { display:grid; grid-template-columns:repeat(4,minmax(130px,1fr)); gap:12px; margin-bottom:18px; }
.team-stat { background:#fff; border:1px solid #E2E8F0; border-radius:10px; padding:14px 16px; }
.team-stat small { display:block; color:#64748B; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; }
.team-stat strong { font-size:24px; color:#0F172A; }
.team-filter { display:grid; grid-template-columns:minmax(220px,2fr) minmax(160px,1fr) minmax(180px,1fr) auto; gap:10px; align-items:end; }
.team-table-wrap { overflow:auto; border:1px solid #E2E8F0; border-radius:10px; background:#fff; }
.team-table { width:100%; border-collapse:collapse; min-width:900px; }
.team-table th { text-align:left; padding:12px 14px; background:#F8FAFC; color:#475569; font-size:11px; text-transform:uppercase; border-bottom:1px solid #E2E8F0; }
.team-table td { padding:13px 14px; border-bottom:1px solid #F1F5F9; vertical-align:middle; font-size:13px; }
.team-table tr:last-child td { border-bottom:none; }
.status-pill { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:5px 9px; font-size:11px; font-weight:800; white-space:nowrap; }
.staff-name { font-weight:800; color:#0F172A; }
.staff-sub { font-size:11px; color:#64748B; margin-top:2px; }
.team-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 10px; border-radius:6px; text-decoration:none; border:1px solid #CBD5E1; background:#fff; color:#334155; font-weight:700; font-size:11px; cursor:pointer; }
.team-btn.primary { background:#2563EB; color:#fff; border-color:#2563EB; }
.team-btn.success { background:#10B981; color:#fff; border-color:#10B981; }
.team-btn.danger { background:#EF4444; color:#fff; border-color:#EF4444; }
.team-detail { background:#fff; border:1px solid #E2E8F0; border-radius:12px; overflow:hidden; margin-top:20px; }
.team-detail-head { padding:18px 20px; background:#F8FAFC; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; gap:14px; align-items:flex-start; flex-wrap:wrap; }
.team-profile-grid { display:grid; grid-template-columns:repeat(4,minmax(140px,1fr)); gap:12px; padding:18px 20px; border-bottom:1px solid #E2E8F0; }
.team-profile-item small { display:block; font-size:10px; color:#64748B; text-transform:uppercase; font-weight:800; margin-bottom:4px; }
.team-profile-item strong { color:#0F172A; font-size:13px; }
.review-comment { margin:16px 20px; border-radius:8px; padding:12px 14px; background:#FFF7ED; border:1px solid #FED7AA; color:#9A3412; font-size:13px; }
.report-day { margin:0 20px 16px; border:1px solid #E2E8F0; border-radius:8px; overflow:hidden; }
.report-day-title { background:#F8FAFC; padding:10px 12px; font-size:12px; font-weight:800; display:flex; justify-content:space-between; gap:10px; }
.report-activity { padding:12px; border-top:1px solid #F1F5F9; }
.report-activity:first-of-type { border-top:none; }
.report-photo-grid { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
.report-photo-grid a { display:block; }
.report-photo-grid img { width:120px; height:90px; object-fit:cover; border-radius:6px; border:1px solid #CBD5E1; }

@media (max-width:1000px) {
    .team-summary { grid-template-columns:repeat(2,minmax(130px,1fr)); }
    .team-filter { grid-template-columns:1fr 1fr; }
    .team-profile-grid { grid-template-columns:repeat(2,minmax(140px,1fr)); }
}
@media (max-width:900px) {
    .logbook-left,.logbook-right { min-width:100%; }
    .logbook-right { position:static; }
    .activity-header { flex-direction:column; }
    .activity-title-wrap,.activity-photos-wrap { width:100%; }
}
@media (max-width:600px) {
    .team-summary,.team-filter,.team-profile-grid { grid-template-columns:1fr; }
}
</style>

<div class="page-content" style="padding:24px;">
    <div class="logbook-tabs">
        <a class="logbook-tab <?= $view === 'my' ? 'active' : '' ?>" href="index.php?page=logbook&view=my&week=<?= htmlspecialchars($weekStart, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-book-open"></i> My Logbook
        </a>
        <?php if ($canViewStaffReports): ?>
            <a class="logbook-tab <?= $view === 'team' ? 'active' : '' ?>" href="index.php?page=logbook&view=team&week=<?= htmlspecialchars($weekStart, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-users"></i> Staff Reports
            </a>
        <?php endif; ?>
    </div>

<?php if ($view === 'team' && $canViewStaffReports): ?>
    <?php
        $summary = $teamSummary ?? ['total_staff'=>0,'not_started'=>0,'draft'=>0,'submitted'=>0];
        $filters = $teamFilters ?? ['status'=>'all','department_id'=>0,'q'=>''];
        $baseFilterParams = [
            'page' => 'logbook', 'view' => 'team', 'week' => $weekStart,
            'status' => $filters['status'] ?? 'all',
            'department_id' => (int) ($filters['department_id'] ?? 0),
            'q' => $filters['q'] ?? '',
        ];
    ?>

    <div class="card" style="padding:20px;border-radius:12px;margin-bottom:18px;background:var(--bg-card);display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
        <div>
            <h3 style="margin:0;font-size:18px;color:var(--text-dark);"><i class="fa-solid fa-clipboard-list" style="color:#2563EB;"></i> Staff Weekly Reports</h3>
            <p style="margin:4px 0 0;font-size:13px;color:var(--text-muted);">View weekly activity reports submitted by staff. This dashboard is read-only.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <a class="team-btn" href="index.php?<?= htmlspecialchars(http_build_query(array_merge($baseFilterParams, ['week'=>date('Y-m-d', strtotime('-1 week', strtotime($weekStart))), 'report_id'=>null])), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-chevron-left"></i> Prev Week</a>
            <div style="text-align:center;min-width:150px;">
                <small style="display:block;color:#64748B;font-size:10px;font-weight:800;text-transform:uppercase;">Week Of</small>
                <strong><?= htmlspecialchars(date('d M', strtotime($weekStart)) . ' - ' . date('d M Y', strtotime($weekEnd)), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <a class="team-btn" href="index.php?<?= htmlspecialchars(http_build_query(array_merge($baseFilterParams, ['week'=>date('Y-m-d', strtotime('+1 week', strtotime($weekStart))), 'report_id'=>null])), ENT_QUOTES, 'UTF-8') ?>">Next Week <i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>

    <div class="team-summary">
        <div class="team-stat"><small>Total Staff</small><strong><?= (int) $summary['total_staff'] ?></strong></div>
        <div class="team-stat"><small>Not Started</small><strong><?= (int) $summary['not_started'] ?></strong></div>
        <div class="team-stat"><small>Draft</small><strong><?= (int) $summary['draft'] ?></strong></div>
        <div class="team-stat"><small>Submitted</small><strong><?= (int) $summary['submitted'] ?></strong></div>
    </div>

    <div class="card" style="padding:16px;border-radius:12px;margin-bottom:16px;background:var(--bg-card);">
        <form method="get" class="team-filter">
            <input type="hidden" name="page" value="logbook">
            <input type="hidden" name="view" value="team">
            <input type="hidden" name="week" value="<?= htmlspecialchars($weekStart, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group" style="margin:0;">
                <label>Search Staff</label>
                <input class="form-control" type="search" name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Name, email or employee ID">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Status</label>
                <select class="form-control" name="status">
                    <?php foreach (['all'=>'All Status','not_started'=>'Not Started','draft'=>'Draft','submitted'=>'Submitted'] as $value=>$label): ?>
                        <option value="<?= $value ?>" <?= (($filters['status'] ?? 'all') === $value) ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Department</label>
                <select class="form-control" name="department_id">
                    <?php if (($scopeDepartmentId ?? null) === null): ?><option value="0">All Departments</option><?php endif; ?>
                    <?php foreach (($departments ?? []) as $dept): ?>
                        <option value="<?= (int) $dept['id'] ?>" <?= ((int) ($filters['department_id'] ?? 0) === (int) $dept['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $dept['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="team-btn primary" type="submit" style="height:37px;justify-content:center;"><i class="fa-solid fa-filter"></i> Filter</button>
        </form>
    </div>

    <?php if (!empty($teamFlashError)): ?>
        <div style="padding:12px 14px;border:1px solid #FCA5A5;background:#FEF2F2;color:#991B1B;border-radius:8px;margin-bottom:14px;"><?= htmlspecialchars($teamFlashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="team-table-wrap">
        <table class="team-table">
            <thead><tr><th>Employee</th><th>Department</th><th>Position</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (empty($teamReports)): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:#64748B;">No staff matched the selected filters.</td></tr>
            <?php else: ?>
                <?php foreach ($teamReports as $row): ?>
                    <?php
                        $rowStatus = !empty($row['id']) ? (string) $row['status'] : 'not_started';
                        [$label,$color,$bg,$icon] = $statusMeta($rowStatus);
                        $viewUrl = '';
                        if (!empty($row['id'])) {
                            $viewUrl = 'index.php?' . http_build_query(array_merge($baseFilterParams, ['report_id'=>(int)$row['id']]));
                        }
                    ?>
                    <tr>
                        <td><div class="staff-name"><?= htmlspecialchars((string) $row['user_name'], ENT_QUOTES, 'UTF-8') ?></div><div class="staff-sub"><?= htmlspecialchars((string) ($row['employee_id'] ?: $row['email']), ENT_QUOTES, 'UTF-8') ?></div></td>
                        <td><?= htmlspecialchars((string) $row['department'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['position'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="status-pill" style="color:<?= $color ?>;background:<?= $bg ?>;"><i class="fa-solid <?= $icon ?>"></i><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= !empty($row['submitted_at']) ? htmlspecialchars(date('d M Y, h:i A', strtotime($row['submitted_at'])), ENT_QUOTES, 'UTF-8') : '-' ?></td>
                        <td><?php if ($viewUrl): ?><a class="team-btn primary" href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="fa-regular fa-eye"></i> View Report</a><?php else: ?><span style="color:#94A3B8;font-size:11px;">No report yet</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($selectedTeamReport)): ?>
        <?php [$detailLabel,$detailColor,$detailBg,$detailIcon] = $statusMeta($selectedTeamReport['status']); ?>
        <div class="team-detail" id="report-detail">
            <div class="team-detail-head">
                <div>
                    <div style="font-size:18px;font-weight:800;color:#0F172A;"><?= htmlspecialchars((string) $selectedTeamReport['user_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div style="font-size:12px;color:#64748B;margin-top:3px;">Weekly report <?= htmlspecialchars(date('d M', strtotime($selectedTeamReport['week_start'])) . ' - ' . date('d M Y', strtotime($selectedTeamReport['week_end'])), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <span class="status-pill" style="color:<?= $detailColor ?>;background:<?= $detailBg ?>;"><i class="fa-solid <?= $detailIcon ?>"></i><?= htmlspecialchars($detailLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <a class="team-btn" target="_blank" rel="noopener" href="index.php?action=logbook_pdf&report_id=<?= (int) $selectedTeamReport['id'] ?>"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                    <a class="team-btn" href="index.php?<?= htmlspecialchars(http_build_query($baseFilterParams), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-xmark"></i> Close</a>
                </div>
            </div>

            <div class="team-profile-grid">
                <div class="team-profile-item"><small>Department</small><strong><?= htmlspecialchars((string) $selectedTeamReport['department'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="team-profile-item"><small>Position</small><strong><?= htmlspecialchars((string) ($selectedTeamReport['position'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="team-profile-item"><small>Employee ID</small><strong><?= htmlspecialchars((string) ($selectedTeamReport['employee_id'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="team-profile-item"><small>Submitted</small><strong><?= !empty($selectedTeamReport['submitted_at']) ? htmlspecialchars(date('d M Y, h:i A', strtotime($selectedTeamReport['submitted_at'])), ENT_QUOTES, 'UTF-8') : '-' ?></strong></div>
            </div>

            <div style="padding-top:16px;">
                <?php $dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday']; $dayIndex = 0; ?>
                <?php foreach (($selectedTeamData ?? []) as $dateStr => $day): ?>
                    <div class="report-day">
                        <div class="report-day-title"><span><?= $dayNames[$dayIndex] ?? date('l', strtotime($dateStr)) ?> — <?= htmlspecialchars(date('d M Y', strtotime($dateStr)), ENT_QUOTES, 'UTF-8') ?></span><span style="color:#64748B;"><?= htmlspecialchars((string) ($day['status'] ?? 'Not Started'), ENT_QUOTES, 'UTF-8') ?></span></div>
                        <?php if (!empty($day['activities'])): ?>
                            <?php foreach ($day['activities'] as $aIndex => $activity): ?>
                                <div class="report-activity">
                                    <div style="font-weight:800;color:#0F172A;"><?= ($aIndex + 1) ?>. <?= htmlspecialchars((string) ($activity['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($activity['actions'])): ?>
                                        <ul style="margin:8px 0 0 18px;padding:0;">
                                            <?php foreach ($activity['actions'] as $action): ?><li style="margin-bottom:4px;"><?= htmlspecialchars((string) ($action['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if (!empty($activity['photos'])): ?>
                                        <div class="report-photo-grid">
                                            <?php foreach ($activity['photos'] as $photo): ?>
                                                <a href="<?= htmlspecialchars((string) ($photo['url'] ?? ('index.php?action=logbook_photo&id=' . (int)$photo['id'])), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars((string) ($photo['url'] ?? ('index.php?action=logbook_photo&id=' . (int)$photo['id'])), ENT_QUOTES, 'UTF-8') ?>" alt="Logbook photo"></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding:12px;color:#94A3B8;font-size:12px;">No activities recorded for this day.</div>
                        <?php endif; ?>
                    </div>
                    <?php $dayIndex++; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <script>window.csrfToken = <?= json_encode($csrfToken, $jsonFlags) ?>;</script>

<?php else: ?>
    <?php
        $reportStatus = (string) ($report['status'] ?? 'draft');
        $isEditable = ($reportStatus === 'draft');
    ?>
    <div class="card module-toolbar" style="padding:20px;border-radius:12px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;background:var(--bg-card);">
        <div>
            <h3 style="margin:0;font-size:18px;color:var(--text-dark);"><i class="fa-solid fa-book" style="color:#2563EB;"></i> Activity Logbook</h3>
            <p style="margin:4px 0 0;font-size:13px;color:var(--text-muted);">Record your daily activities and generate your weekly report.</p>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
            <a href="index.php?page=logbook&view=my&week=<?= htmlspecialchars(date('Y-m-d', strtotime('-1 week', strtotime($weekStart))), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="padding:8px 12px;border-radius:6px;background:#F1F5F9;color:#475569;text-decoration:none;font-size:13px;"><i class="fa-solid fa-chevron-left"></i> Prev Week</a>
            <div style="text-align:center;padding:0 10px;"><span style="display:block;font-size:10px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Week Of</span><strong style="font-size:14px;color:var(--text-dark);"><?= htmlspecialchars(date('d M', strtotime($weekStart)) . ' - ' . date('d M Y', strtotime($weekEnd)), ENT_QUOTES, 'UTF-8') ?></strong></div>
            <a href="index.php?page=logbook&view=my&week=<?= htmlspecialchars(date('Y-m-d', strtotime('+1 week', strtotime($weekStart))), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="padding:8px 12px;border-radius:6px;background:#F1F5F9;color:#475569;text-decoration:none;font-size:13px;">Next Week <i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>
    <script>
        window.logbookData = <?= json_encode($structuredData, $jsonFlags) ?>;
        window.userData = <?= json_encode($userProfile, $jsonFlags) ?>;
        window.reportId = <?= (int) $report['id'] ?>;
        window.weekStart = <?= json_encode($weekStart, $jsonFlags) ?>;
        window.weekEnd = <?= json_encode($weekEnd, $jsonFlags) ?>;
        window.isDraft = <?= $isEditable ? 'true' : 'false' ?>;
        window.csrfToken = <?= json_encode($csrfToken, $jsonFlags) ?>;
    </script>

    <div class="logbook-container">
        <div class="logbook-left" id="accordionContainer"></div>
        <div class="logbook-right">
            <div class="card" style="border-radius:12px;background:var(--bg-card);overflow:hidden;display:flex;flex-direction:column;">
                <div style="padding:15px 20px;border-bottom:1px solid var(--border-color);background:#F8FAFC;display:flex;align-items:center;gap:10px;"><i class="fa-regular fa-file-pdf" style="color:#3B82F6;font-size:20px;"></i><div><h3 style="margin:0;font-size:14px;color:var(--text-dark);text-transform:uppercase;">Weekly Report Preview</h3><p style="margin:0;font-size:11px;color:var(--text-muted);">Live preview of your generated PDF.</p></div></div>
                <div style="padding:20px;background:#E2E8F0;overflow-y:auto;max-height:calc(100vh - 250px);"><div class="preview-paper" id="livePreviewContainer"></div></div>

                <?php if ($isEditable): ?>
                    <div style="padding:15px 20px;border-top:1px solid var(--border-color);background:#F8FAFC;display:flex;justify-content:space-between;gap:10px;">
                        <button type="button" onclick="saveAllDrafts()" style="flex:1;padding:10px;border-radius:6px;background:#fff;color:#475569;border:1px solid #CBD5E1;cursor:pointer;font-weight:600;">Save All Drafts</button>
                        <button type="button" onclick="submitFinalReport()" style="flex:1;padding:10px;border-radius:6px;background:#10B981;color:white;border:none;cursor:pointer;font-weight:600;"><i class="fa-solid fa-paper-plane"></i> Submit Weekly Report</button>
                        <a href="index.php?action=logbook_pdf&report_id=<?= (int) $report['id'] ?>" target="_blank" rel="noopener" style="flex:1;text-align:center;padding:10px;border-radius:6px;background:#2563EB;color:white;text-decoration:none;font-weight:600;"><i class="fa-solid fa-download"></i> Generate PDF</a>
                    </div>
                <?php else: ?>
                    <div style="padding:15px 20px;border-top:1px solid var(--border-color);background:#DBEAFE;color:#1E40AF;text-align:center;font-weight:bold;"><i class="fa-solid fa-paper-plane"></i> Report Submitted <a href="index.php?action=logbook_pdf&report_id=<?= (int) $report['id'] ?>" target="_blank" rel="noopener" style="margin-left:15px;padding:6px 12px;border-radius:6px;background:#2563EB;color:white;text-decoration:none;font-size:12px;"><i class="fa-solid fa-download"></i> Download PDF</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <input type="file" id="globalPhotoInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
<?php endif; ?>
</div>

<script src="assets/js/logbook.js?v=2.4.0"></script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
