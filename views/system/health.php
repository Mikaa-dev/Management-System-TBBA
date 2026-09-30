<?php
/**
 * Paparan Modul Pemantauan Kesihatan Sistem & Auto-Backup
 * Syarikat: The Bridge Business Alliance (TBBA)
 * Akses: Khas untuk Admin sahaja
 */
$pageTitle = "System Health & Auto-Backup";
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
$lastBackup = $backups[0] ?? null;
?>

<style>
.health-page { --health-border:#E2E8F0; --health-muted:#64748B; --health-ink:#0F172A; }
.health-page .health-hero { margin-bottom:18px !important; padding:24px !important; border-radius:18px !important; box-shadow:0 10px 28px rgba(15,23,42,.14); }
.health-hero-actions { display:flex; align-items:center; gap:9px; flex-wrap:wrap; }
.health-page .health-hero-secondary,
.health-page .health-hero-primary { min-height:42px; border-radius:10px; padding:10px 15px !important; font-size:12px !important; white-space:nowrap; }
.health-page .health-overview-grid { display:grid !important; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px !important; margin-bottom:18px !important; }
.health-page .health-overview-card { display:block !important; min-width:0; margin:0 !important; padding:17px !important; border:1px solid var(--health-border); border-radius:14px; box-shadow:0 2px 7px rgba(15,23,42,.035); }
.health-page .health-overview-card .stat-info { width:100%; }
.health-overview-card .health-card-heading { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:12px; }
.health-overview-card .health-card-label { color:var(--health-muted); font-size:10px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
.health-overview-card .health-card-icon { width:32px; height:32px; flex:0 0 32px; display:grid; place-items:center; border-radius:9px; background:#EFF6FF; color:#2563EB; }
.health-overview-card .health-card-value { color:var(--health-ink); font-size:22px; font-weight:800; line-height:1.2; overflow-wrap:anywhere; }
.health-overview-card .health-card-caption { min-height:34px; margin:5px 0 10px; color:var(--health-muted); font-size:10px; line-height:1.45; }
.health-overview-card .health-mini-row { display:flex; align-items:center; justify-content:space-between; gap:8px; color:var(--health-muted); font-size:9px; }
.health-page .health-section { margin-bottom:18px !important; padding:20px !important; border:1px solid var(--health-border) !important; border-radius:16px !important; box-shadow:0 2px 8px rgba(15,23,42,.04) !important; }
.health-section-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding-bottom:14px; margin-bottom:16px; border-bottom:1px solid var(--health-border); }
.health-section-title { display:flex; align-items:center; gap:10px; margin:0; color:var(--health-ink); font-size:16px; font-weight:800; }
.health-section-title .health-title-icon { width:34px; height:34px; display:grid; place-items:center; border-radius:10px; background:#EFF6FF; color:#2563EB; }
.health-section-subtitle { margin:4px 0 0 44px; color:var(--health-muted); font-size:10px; line-height:1.5; }
.health-page .health-policy-grid { display:grid !important; grid-template-columns:minmax(300px,.8fr) minmax(0,1.2fr) !important; gap:16px !important; }
.health-page .health-policy-form { padding:14px; border:1px solid var(--health-border); border-radius:12px; background:#F8FAFC; }
.health-event-table { min-width:600px; }
.health-page .infrastructure-card { background:#FFF !important; color:var(--health-ink) !important; }
.health-page .infrastructure-card > .health-section-heading { margin-bottom:0; }
.health-page .infrastructure-card .infrastructure-actions { display:flex; gap:8px; flex-wrap:wrap; }
.health-page .infrastructure-card .infrastructure-action { min-height:36px; padding:8px 11px !important; border-radius:8px; font-size:10px !important; }
.infrastructure-summary { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:13px 2px 0; color:#334155; font-size:11px; font-weight:800; cursor:pointer; list-style:none; }
.infrastructure-summary::-webkit-details-marker { display:none; }
.infrastructure-summary .summary-state { display:inline-flex; align-items:center; gap:6px; color:#059669; }
.infrastructure-summary .summary-chevron { transition:transform .2s; color:#94A3B8; }
.infrastructure-details[open] .summary-chevron { transform:rotate(180deg); }
.infrastructure-content { padding-top:16px; }
.health-page .defence-grid { display:grid !important; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px !important; margin-bottom:12px !important; }
.health-page .defence-card { padding:13px !important; border:1px solid var(--health-border) !important; border-radius:10px !important; background:#F8FAFC !important; }
.health-page .defence-card strong { display:block; margin:5px 0; color:var(--health-ink); font-size:11px; }
.health-page .defence-card p { margin:0; color:var(--health-muted); font-size:9px; line-height:1.5; }
.health-page .threat-metrics { display:grid !important; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px !important; padding:10px !important; margin-bottom:12px !important; border:1px solid var(--health-border) !important; background:#F8FAFC !important; }
.health-page .threat-metric { min-width:0 !important; padding:3px 7px; }
.health-page .threat-metric span { color:var(--health-muted) !important; font-size:9px !important; }
.health-page .threat-metric strong { color:var(--health-ink) !important; font-size:18px !important; }
.health-page .threat-log-title { color:#334155 !important; font-size:11px !important; }
.health-page .threat-log-wrap { max-height:230px !important; border-color:var(--health-border) !important; background:#FFF !important; }
.health-page .threat-log-wrap thead { background:#F8FAFC !important; color:#64748B !important; }
.health-page .threat-log-wrap th { border-color:var(--health-border) !important; }
.health-page .health-bottom-grid { display:grid !important; grid-template-columns:minmax(0,1.2fr) minmax(300px,.8fr) !important; gap:16px !important; margin-bottom:24px !important; }
.health-page .health-bottom-grid > *,
.health-page .health-policy-grid > *,
.health-page .health-overview-grid > * { min-width:0; max-width:100%; }
.health-page .backup-card { order:1; }
.health-page .diagnostic-card { order:2; }
.health-page .backup-callout { padding:15px !important; margin-bottom:15px !important; border:1px solid #A7F3D0 !important; border-radius:11px !important; background:#ECFDF5 !important; }
.backup-callout-inner { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; }
.backup-callout-copy { display:flex; align-items:flex-start; gap:11px; min-width:0; }
.backup-callout-copy i { margin-top:2px; color:#059669; font-size:24px; }
.health-page .backup-callout .btn { flex:0 0 auto; width:auto !important; min-height:40px; padding:10px 14px !important; border-radius:9px; font-size:11px !important; white-space:nowrap; }
.backup-list-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:9px; }
.backup-location { color:#94A3B8; font-size:9px; }
.backup-table-wrap { overflow:auto; max-height:310px; border:1px solid var(--health-border); border-radius:10px; }
.backup-table { min-width:620px; margin:0 !important; font-size:10px !important; }
.backup-action-group { display:inline-flex; align-items:center; gap:5px; }
.backup-action-group .btn { width:31px; height:31px; display:inline-grid; place-items:center; padding:0 !important; border-radius:8px; }
.health-parameter-list { display:flex; flex-direction:column; gap:7px; margin-bottom:12px; }
.health-parameter-row { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:9px 11px; border:1px solid var(--health-border); border-radius:9px; background:#F8FAFC; color:#475569; font-size:10px; }
.health-parameter-row strong { color:var(--health-ink); text-align:right; overflow-wrap:anywhere; }
.health-page .benchmark-box { padding:14px !important; border:1px solid #BFDBFE !important; border-radius:10px !important; background:#EFF6FF !important; text-align:left !important; }
.benchmark-action-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
.health-page #btnBenchmark { width:auto !important; min-height:38px; padding:9px 12px !important; white-space:nowrap; }

@media (max-width:1200px) {
    .health-page .health-overview-grid { grid-template-columns:repeat(2,minmax(0,1fr)) !important; }
    .health-page .health-bottom-grid { grid-template-columns:minmax(0,1fr) !important; }
    .health-page .backup-card, .health-page .diagnostic-card { order:initial; }
}
@media (max-width:900px) {
    .health-page .health-policy-grid { grid-template-columns:1fr !important; }
    .health-page .defence-grid { grid-template-columns:1fr !important; }
    .health-page .threat-metrics { grid-template-columns:repeat(2,minmax(0,1fr)) !important; }
}
@media (max-width:640px) {
    .health-page { width:100%; max-width:100%; overflow:hidden; }
    .health-page .health-hero { padding:19px !important; }
    .health-hero-actions, .health-hero-actions .btn { width:100%; }
    .health-page .health-overview-grid { grid-template-columns:minmax(0,1fr) !important; }
    .health-page .health-bottom-grid { grid-template-columns:minmax(0,1fr) !important; }
    .health-section-heading, .backup-callout-inner, .benchmark-action-row { flex-direction:column; align-items:stretch; }
    .health-section-subtitle { margin-left:0; }
    .health-page .health-policy-form { grid-template-columns:1fr !important; }
    .health-page .health-policy-form > * { grid-column:1 !important; }
    .health-page .infrastructure-card .infrastructure-actions { width:100%; }
    .health-page .infrastructure-card .infrastructure-action { flex:1; }
    .health-page .threat-metrics { grid-template-columns:1fr 1fr !important; }
    .health-page .backup-callout .btn, .health-page #btnBenchmark { width:100% !important; }
    .backup-list-header { align-items:flex-start; flex-direction:column; }
    .health-page .health-section { width:100%; min-width:0; padding:16px !important; }
    .health-page .health-parameter-row { flex-direction:column; align-items:flex-start !important; }
    .health-page .health-parameter-row strong { width:100%; text-align:left; }
}
</style>

<div class="health-page">

<!-- Special Banner for System Health Module -->
<div class="card health-hero" style="margin-bottom: 24px; background: linear-gradient(135deg, #0F172A 0%, #065F46 100%); color: #FFFFFF; border: none; padding: 28px; position: relative; overflow: hidden;">
    <div style="position: absolute; right: -20px; top: -20px; font-size: 180px; opacity: 0.07; color: #FFFFFF; pointer-events: none;">
        <i class="fa-solid fa-server"></i>
    </div>
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; position: relative; z-index: 2;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34D399; font-size: 11px; border: 1px solid rgba(52, 211, 153, 0.3);"><i class="fa-solid fa-shield-halved"></i> ADMIN EXCLUSIVE MODULE</span>
                <span style="font-size: 12px; color: #A7F3D0;"><i class="fa-solid fa-circle-check" style="color: #10B981;"></i> System Status: Optimal</span>
            </div>
            <h2 style="font-size: 26px; font-weight: 800; color: #FFFFFF; margin-bottom: 6px;">
                System Health & Auto-Backup
            </h2>
            <p style="font-size: 13px; color: #D1FAE5; margin: 0; max-width: 680px; line-height: 1.5;">
                Monitor real-time server disk space, MySQL query speed, and server memory load. Compress corporate database and tender files into secure <code>.zip</code> archives for enterprise data safety.
            </p>
        </div>

        <div class="health-hero-actions">
            <button onclick="benchmarkDb()" class="btn health-hero-secondary" style="background: rgba(255,255,255,0.15); color: #FFFFFF; border: 1px solid rgba(255,255,255,0.25); padding: 12px 18px; font-size: 13px;">
                <i class="fa-solid fa-gauge-high"></i> Run Database Test
            </button>
            <button onclick="runAutoBackup()" id="btnHeaderBackup" class="btn btn-success health-hero-primary" style="background: #10B981; color: #FFFFFF; font-weight: 800; padding: 14px 22px; font-size: 14px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4); border: none; transition: transform 0.2s;">
                <i class="fa-solid fa-cloud-arrow-down"></i> Create Backup
            </button>
        </div>
    </div>
</div>

<!-- 3 Main Health Stat Cards (Disk Space, MySQL Performance, Server Load) -->
<div class="grid grid-cols-3 health-overview-grid" style="margin-bottom: 28px; gap: 20px;">
    <!-- 1. Disk Space Storage -->
    <div class="card stat-card health-overview-card <?= in_array($diskUsage['status'], ['danger', 'unavailable'], true) ? 'accent-warning' : 'accent-success' ?>" style="margin: 0; padding: 22px;">
        <div class="stat-info" style="width: 100%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span class="badge <?= in_array($diskUsage['status'], ['danger', 'unavailable'], true) ? 'badge-danger' : 'badge-success' ?>" style="font-size: 11px;">HARD DISK STORAGE</span>
                <span style="font-size: 12px; font-weight: 700; color: #64748B;"><?= $diskUsage['status'] === 'unavailable' ? 'Unavailable' : $diskUsage['percentage'] . '% Used' ?></span>
            </div>
            <h3 style="font-size: 28px; font-weight: 800; margin-bottom: 4px; color: #0F172A;">
                <?= $diskUsage['used'] ?> <span style="font-size: 16px; font-weight: 500; color: #64748B;">/ <?= $diskUsage['total'] ?></span>
            </h3>
            <p style="font-size: 13px; color: #475569; margin-bottom: 12px;">Free Storage: <strong style="color: #059669;"><?= $diskUsage['free'] ?></strong></p>
            
            <!-- Disk Usage Progress Bar -->
            <div class="progress-wrapper" style="margin: 0; height: 10px; background: #E2E8F0; border-radius: 6px; overflow: hidden;">
                <div class="progress-bar" style="width: <?= $diskUsage['percentage'] ?>%; background: <?= $diskUsage['percentage'] > 85 ? '#EF4444' : ($diskUsage['percentage'] > 70 ? '#F59E0B' : '#10B981') ?>; transition: width 0.5s ease;"></div>
            </div>
            <div style="margin-top: 10px; font-size: 11px; color: #64748B; display: flex; justify-content: space-between;">
                <span><i class="fa-solid fa-folder"></i> Uploads Directory: <strong><?= $diskUsage['uploads_size'] ?></strong></span>
                <span>Status: <strong style="color: <?= $diskUsage['status'] === 'unavailable' || $diskUsage['percentage'] > 85 ? '#EF4444' : '#10B981' ?>;"><?= $diskUsage['status'] === 'unavailable' ? 'Unavailable' : ($diskUsage['percentage'] > 85 ? 'Critical' : 'Normal') ?></strong></span>
            </div>
        </div>
    </div>

    <!-- 2. MySQL Query Performance -->
    <div class="card stat-card health-overview-card accent-blue" style="margin: 0; padding: 22px;">
        <div class="stat-info" style="width: 100%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span class="badge badge-info" style="font-size: 11px;">MYSQL PERFORMANCE</span>
                <span id="benchmarkBadge" class="badge" style="background: #DBEAFE; color: #1E40AF; font-size: 11px;">⚡ QPS: <?= $mysqlPerf['qps'] ?></span>
            </div>
            <h3 style="font-size: 28px; font-weight: 800; margin-bottom: 4px; color: #0F172A;">
                <?= $mysqlPerf['total_queries'] ?> <span style="font-size: 16px; font-weight: 500; color: #64748B;">Queries</span>
            </h3>
            <p style="font-size: 13px; color: #475569; margin-bottom: 12px;">Database Size: <strong style="color: #2563EB;"><?= $mysqlPerf['db_size_mb'] ?></strong></p>
            
            <div style="background: #F1F5F9; padding: 10px 12px; border-radius: 8px; border: 1px solid #E2E8F0; display: flex; justify-content: space-between; font-size: 12px; color: #334155;">
                <div><i class="fa-solid fa-table" style="color: #3B82F6;"></i> Tables: <strong><?= $mysqlPerf['total_tables'] ?></strong></div>
                <div><i class="fa-solid fa-bug" style="color: #EF4444;"></i> Slow Queries: <strong><?= $mysqlPerf['slow_queries'] ?></strong></div>
                <div><i class="fa-solid fa-link" style="color: #10B981;"></i> Threads: <strong><?= $mysqlPerf['threads_connected'] ?></strong></div>
            </div>
            <div style="margin-top: 8px; font-size: 11px; color: #64748B;">
                <i class="fa-solid fa-clock-rotate-left"></i> Server Uptime: <strong><?= $mysqlPerf['uptime'] ?></strong>
            </div>
        </div>
    </div>

    <!-- 3. Server Load & RAM Usage -->
    <div class="card stat-card health-overview-card accent-warning" style="margin: 0; padding: 22px;">
        <div class="stat-info" style="width: 100%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span class="badge badge-warning" style="font-size: 11px;">SERVER LOAD & RAM</span>
                <span class="badge badge-success" style="font-size: 11px;"><i class="fa-solid fa-heart-pulse"></i> Healthy</span>
            </div>
            <h3 style="font-size: 28px; font-weight: 800; margin-bottom: 4px; color: #0F172A;">
                <?= $serverLoad['memory_used'] ?> <span style="font-size: 16px; font-weight: 500; color: #64748B;">/ <?= $serverLoad['memory_limit'] ?></span>
            </h3>
            <p style="font-size: 13px; color: #475569; margin-bottom: 12px;">Peak RAM: <strong style="color: #D97706;"><?= $serverLoad['memory_peak'] ?></strong></p>
            
            <div style="background: #FFFBEB; padding: 10px 12px; border-radius: 8px; border: 1px solid #FEF3C7; font-size: 12px; color: #92400E; display: flex; flex-direction: column; gap: 4px;">
                <div style="display: flex; justify-content: space-between;">
                    <span><i class="fa-brands fa-php" style="color: #7C3AED;"></i> PHP Engine:</span>
                    <strong>v<?= $serverLoad['php_version'] ?> (Max: <?= $serverLoad['max_exec'] ?>)</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11px; color: #B45309;">
                    <span><i class="fa-solid fa-server"></i> Web Server:</span>
                    <strong><?= htmlspecialchars(substr($serverLoad['web_server'], 0, 22)) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card stat-card health-overview-card accent-success">
        <div class="health-card-heading">
            <span class="health-card-label">Backup Readiness</span>
            <span class="health-card-icon" style="background:#ECFDF5;color:#059669;"><i class="fa-solid fa-box-archive"></i></span>
        </div>
        <div class="health-card-value"><?= count($backups) ?> <span style="font-size:12px;color:#64748B;font-weight:700;">archive<?= count($backups) === 1 ? '' : 's' ?></span></div>
        <p class="health-card-caption"><?= $lastBackup ? 'Latest: ' . htmlspecialchars($lastBackup['created_at']) : 'No backup archive has been created yet.' ?></p>
        <div class="health-mini-row">
            <span><i class="fa-solid fa-calendar-days"></i> <?= (int)($securitySettings['backup_retention_days'] ?? 30) ?>-day retention</span>
            <strong style="color:<?= $lastBackup ? '#059669' : '#D97706' ?>;"><?= $lastBackup ? 'Ready' : 'Action needed' ?></strong>
        </div>
    </div>
</div> <!-- End 3-Column Stat Cards Grid -->

<!-- Account security policy and event visibility -->
<div class="card health-section" style="margin-bottom:24px;padding:22px;border-radius:14px;">
    <div class="card-header health-section-heading" style="padding-bottom:15px;margin-bottom:17px;border-bottom:1px solid #E2E8F0;">
        <div><div class="card-title"><i class="fa-solid fa-user-shield" style="color:#2563EB;"></i><span>Account Security Policy</span></div><p style="margin:5px 0 0;color:#64748B;font-size:11px;">Password expiry, two-factor adoption, login alerts and device-session controls.</p></div>
        <div style="display:flex;gap:7px;flex-wrap:wrap;"><span class="badge badge-info"><?= (int)($securityStats['active_sessions']??0) ?> active sessions</span><span class="badge badge-success"><?= (int)($securityStats['two_factor_users']??0) ?> using 2FA</span><span class="badge badge-warning"><?= (int)($securityStats['recent_alerts']??0) ?> alerts / 30d</span></div>
    </div>
    <div class="health-policy-grid" style="display:grid;grid-template-columns:minmax(320px,.8fr) minmax(420px,1.2fr);gap:18px;">
        <form id="securityPolicyForm" class="health-policy-form" onsubmit="saveSecurityPolicy(event)" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-content:start;">
            <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
            <label style="font-size:10px;font-weight:800;color:#334155;">Minimum Password Length<input type="number" min="6" max="64" name="password_min_length" value="<?= max(6, (int)($securitySettings['password_min_length'] ?? 6)) ?>" class="form-control" style="margin-top:5px;"></label>
            <label style="font-size:10px;font-weight:800;color:#334155;">Password Expiry (Days)<input type="number" min="1" max="365" name="password_expiry_days" value="<?= htmlspecialchars($securitySettings['password_expiry_days']??'90') ?>" class="form-control" style="margin-top:5px;"></label>
            <label style="font-size:10px;font-weight:800;color:#334155;">Session Lifetime (Days)<input type="number" min="1" max="90" name="session_lifetime_days" value="<?= htmlspecialchars($securitySettings['session_lifetime_days']??'30') ?>" class="form-control" style="margin-top:5px;"></label>
            <label style="font-size:10px;font-weight:800;color:#334155;">Backup Retention (Days)<input type="number" min="7" max="365" name="backup_retention_days" value="<?= htmlspecialchars($securitySettings['backup_retention_days']??'30') ?>" class="form-control" style="margin-top:5px;"></label>
            <label style="grid-column:1/-1;display:flex;align-items:center;gap:8px;font-size:11px;color:#334155;"><input type="checkbox" name="login_alerts" value="1" <?= ($securitySettings['login_alerts']??'1')==='1'?'checked':'' ?>> Send in-app and email alerts for new login contexts</label>
            <label style="grid-column:1/-1;display:flex;align-items:center;gap:8px;font-size:11px;color:#334155;"><input type="checkbox" name="require_2fa_admin" value="1" <?= ($securitySettings['require_2fa_admin']??'0')==='1'?'checked':'' ?>> Require administrators to enrol in two-factor authentication</label>
            <?php if(Auth::hasPermission('security','manage')): ?><button type="submit" class="btn btn-primary" style="grid-column:1/-1;"><i class="fa-solid fa-floppy-disk"></i> Save Security Policy</button><?php endif; ?>
        </form>
        <div style="overflow:auto;max-height:270px;border:1px solid #E2E8F0;border-radius:10px;"><table class="table health-event-table" style="font-size:10px;margin:0;"><thead><tr><th>Time</th><th>User</th><th>Event</th><th>Severity</th><th>IP</th></tr></thead><tbody><?php if(!$securityEvents): ?><tr><td colspan="5" style="padding:25px;text-align:center;color:#64748B;">No account security events recorded yet.</td></tr><?php endif; ?><?php foreach($securityEvents as $event): ?><tr><td style="white-space:nowrap;"><?= date('d M, H:i',strtotime($event['created_at'])) ?></td><td><?= htmlspecialchars($event['user_name']?:'Unknown') ?></td><td><?= htmlspecialchars(str_replace('_',' ',$event['event_type'])) ?></td><td><span class="badge <?= $event['severity']==='critical'?'badge-danger':($event['severity']==='warning'?'badge-warning':'badge-info') ?>"><?= htmlspecialchars($event['severity']) ?></span></td><td><?= htmlspecialchars($event['ip_address']?:'—') ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
</div>

<!-- A. NETWORK & INFRASTRUCTURE SECURITY -->
<div class="card health-section infrastructure-card" style="margin-bottom: 28px; border: 1px solid #1E293B; background: linear-gradient(145deg, #0F172A 0%, #1E293B 100%); color: #F8FAFC; padding: 26px; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
    <!-- Header Section -->
    <div class="health-section-heading" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; border-bottom: 1px solid #334155; padding-bottom: 18px; margin-bottom: 20px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <span class="badge badge-info" style="font-size: 10px;">
                    <i class="fa-solid fa-shield-halved"></i> INFRASTRUCTURE SECURITY
                </span>
            </div>
            <h3 style="font-size: 17px; font-weight: 800; color: #0F172A; margin: 0;">
                Web Protection & Threat Monitoring
            </h3>
            <p style="font-size: 10px; color: #64748B; margin: 4px 0 0 0;">
                Review active safeguards, interception totals and recent security events.
            </p>
        </div>
        <div class="infrastructure-actions">
            <button onclick="simulateWafTest()" class="btn infrastructure-action" style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;">
                <i class="fa-solid fa-bug-slash"></i> Test WAF
            </button>
            <button onclick="clearWafLogs()" class="btn infrastructure-action" style="background:#FFF;color:#475569;border:1px solid #CBD5E1;">
                <i class="fa-solid fa-broom"></i> Clear Logs
            </button>
        </div>
    </div>

    <details class="infrastructure-details">
        <summary class="infrastructure-summary">
            <span class="summary-state"><i class="fa-solid fa-circle-check"></i> WAF, rate limiting and HTTP hardening are active</span>
            <span>View details <i class="fa-solid fa-chevron-down summary-chevron"></i></span>
        </summary>
        <div class="infrastructure-content">

    <!-- Security Components Grid (3 Active Defenses) -->
    <div class="grid grid-cols-3 defence-grid" style="gap: 16px; margin-bottom: 24px;">
        <!-- WAF Status -->
        <div class="defence-card" style="background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; border-radius: 10px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 700; color: #38BDF8; text-transform: uppercase;">1. Web Application Firewall (WAF)</span>
                <span class="badge" style="background: #065F46; color: #6EE7B7; font-size: 10px;">ONLINE</span>
            </div>
            <strong>
                SQLi, XSS & LFI Filter
            </strong>
            <p>
                Automatically filters and sanitizes <code>$_GET</code>, <code>$_POST</code> & <code>$_COOKIE</code> inputs.
            </p>
        </div>

        <!-- Anti-DDoS Status -->
        <div class="defence-card" style="background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; border-radius: 10px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 700; color: #F59E0B; text-transform: uppercase;">2. Anti-DDoS Protection</span>
                <span class="badge" style="background: #065F46; color: #6EE7B7; font-size: 10px;">ONLINE</span>
            </div>
            <strong>
                Rate Limiting (120 req/min)
            </strong>
            <p>
                Absorbs and mitigates HTTP traffic flood attempts with a 60s sliding window.
            </p>
        </div>

        <!-- Edge HTTP Headers Status -->
        <div class="defence-card" style="background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; border-radius: 10px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 700; color: #A855F7; text-transform: uppercase;">3. Edge HTTP Hardening</span>
                <span class="badge" style="background: #065F46; color: #6EE7B7; font-size: 10px;">ONLINE</span>
            </div>
            <strong>
                X-Frame, XSS & CSP Shield
            </strong>
            <p>
                Enforces edge security headers to prevent Clickjacking & MIME Sniffing.
            </p>
        </div>
    </div>

    <!-- Interception Counter Badges -->
    <div class="threat-metrics" style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; background: rgba(15, 23, 42, 0.4); padding: 14px; border-radius: 10px; border: 1px solid #334155;">
        <div class="threat-metric" style="flex: 1; min-width: 140px;">
            <span style="font-size: 11px; color: #94A3B8; display: block;">Total Attacks Blocked:</span>
            <strong style="font-size: 22px; color: #F8FAFC;"><?= $wafStats['total_blocked'] ?></strong>
        </div>
        <div class="threat-metric" style="flex: 1; min-width: 140px;">
            <span style="font-size: 11px; color: #94A3B8; display: block;">SQL Injection (SQLi):</span>
            <strong style="font-size: 22px; color: #F87171;"><?= $wafStats['sqli_blocked'] ?></strong>
        </div>
        <div class="threat-metric" style="flex: 1; min-width: 140px;">
            <span style="font-size: 11px; color: #94A3B8; display: block;">Cross-Site Scripting (XSS):</span>
            <strong style="font-size: 22px; color: #FBBF24;"><?= $wafStats['xss_blocked'] ?></strong>
        </div>
        <div class="threat-metric" style="flex: 1; min-width: 140px;">
            <span style="font-size: 11px; color: #94A3B8; display: block;">DDoS Flood Mitigated:</span>
            <strong style="font-size: 22px; color: #38BDF8;"><?= $wafStats['ddos_mitigated'] ?></strong>
        </div>
    </div>

    <!-- Live Audit Logs Table -->
    <h4 class="threat-log-title" style="font-size: 14px; font-weight: 700; color: #E2E8F0; margin: 0 0 12px 0;">
        <i class="fa-solid fa-list-ul" style="color: #38BDF8;"></i> Recent Security Interception Audit Log (Security Threat Log)
    </h4>
    <div class="threat-log-wrap" style="max-height: 220px; overflow:auto; border: 1px solid #334155; border-radius: 8px; background: #0F172A;">
        <table class="table" style="margin: 0; font-size: 12px; width: 100%; border-collapse: collapse;">
            <thead style="background: #1E293B; color: #94A3B8; position: sticky; top: 0;">
                <tr>
                    <th style="padding: 10px; border-bottom: 1px solid #334155;">Incident Timestamp</th>
                    <th style="padding: 10px; border-bottom: 1px solid #334155;">Attack Type</th>
                    <th style="padding: 10px; border-bottom: 1px solid #334155;">IP Address</th>
                    <th style="padding: 10px; border-bottom: 1px solid #334155;">Blocked Details & Payload</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($wafStats['recent_logs'])): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 24px; color: #64748B;">
                        <i class="fa-solid fa-shield-cat" style="font-size: 24px; color: #10B981; display: block; margin-bottom: 6px;"></i>
                        No attack attempts detected. The system is secure and protected.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($wafStats['recent_logs'] as $log): ?>
                    <tr style="border-bottom: 1px solid #1E293B;">
                        <td style="padding: 10px; color: #94A3B8; white-space: nowrap;"><?= htmlspecialchars($log['timestamp']) ?></td>
                        <td style="padding: 10px;">
                            <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #F87171; border: 1px solid rgba(239,68,68,0.3);">
                                <?= htmlspecialchars($log['type']) ?>
                            </span>
                        </td>
                        <td style="padding: 10px; font-family: monospace; color: #38BDF8;"><?= htmlspecialchars($log['ip']) ?></td>
                        <td style="padding: 10px; color: #E2E8F0;"><?= htmlspecialchars($log['details']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
        </div>
    </details>
</div>

<!-- 2-Column Grid: Detailed Diagnostics & Auto-Backup Management -->
<div class="grid grid-cols-2 health-bottom-grid" style="gap: 24px; margin-bottom: 30px;">
    <!-- Left Column: Diagnostics & Query Speed (Live Benchmark) -->
    <div class="card health-section diagnostic-card" style="margin: 0; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div class="card-header" style="border-bottom: 2px solid #F1F5F9; padding-bottom: 16px; margin-bottom: 16px;">
                <div class="card-title" style="font-size: 16px; font-weight: 800; color: #0F172A;">
                    <i class="fa-solid fa-stethoscope" style="color: #3B82F6; font-size: 18px;"></i>
                    <span>Health Diagnostics & Database Parameters</span>
                </div>
                <span class="badge badge-navy" style="font-size: 11px;">MySQL v<?= $mysqlPerf['version'] ?></span>
            </div>

            <!-- Health Parameters List -->
            <div class="health-parameter-list" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                <div class="health-parameter-row" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0;">
                    <span style="font-size: 13px; color: #475569; font-weight: 600;"><i class="fa-solid fa-database" style="color: #64748B; width: 20px;"></i> MySQL Engine Status</span>
                    <span class="badge badge-success" style="font-size: 12px;"><i class="fa-solid fa-check"></i> Active & Stable</span>
                </div>
                <div class="health-parameter-row" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0;">
                    <span style="font-size: 13px; color: #475569; font-weight: 600;"><i class="fa-solid fa-table-list" style="color: #64748B; width: 20px;"></i> Total Tables & Schemas</span>
                    <strong style="color: #0F172A; font-size: 13px;"><?= $mysqlPerf['total_tables'] ?> Tables (<?= $mysqlPerf['db_size_mb'] ?>)</strong>
                </div>
                <div class="health-parameter-row" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0;">
                    <span style="font-size: 13px; color: #475569; font-weight: 600;"><i class="fa-solid fa-microchip" style="color: #64748B; width: 20px;"></i> Server Operating System</span>
                    <strong style="color: #0F172A; font-size: 13px;"><?= htmlspecialchars($serverLoad['os_info']) ?></strong>
                </div>
                <div class="health-parameter-row" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0;">
                    <span style="font-size: 13px; color: #475569; font-weight: 600;"><i class="fa-solid fa-clock" style="color: #64748B; width: 20px;"></i> Server System Time</span>
                    <strong style="color: #0F172A; font-size: 13px; font-family: monospace;"><?= date('h:i:s A | d M Y') ?></strong>
                </div>
            </div>
        </div>

        <!-- Real-time Speed Test (Live Benchmark Box) -->
        <div class="benchmark-box" style="background: #EFF6FF; border: 2px dashed #3B82F6; padding: 18px; border-radius: 10px; text-align: center;">
            <div id="benchmarkResultBox" style="display: none; background: #FFFFFF; border: 1px solid #BFDBFE; padding: 12px; border-radius: 8px; margin-bottom: 14px;">
                <div style="font-size: 11px; color: #64748B; text-transform: uppercase; font-weight: 700;">Response Speed Result:</div>
                <div id="benchmarkSpeedDisplay" style="font-size: 24px; font-weight: 800; color: #059669; margin: 4px 0;">-- ms</div>
                <div id="benchmarkRatingDisplay" style="font-size: 12px; font-weight: 700; color: #1E40AF;">--</div>
            </div>
            <div class="benchmark-action-row">
                <div>
                    <h5 style="font-size: 12px; font-weight: 800; color: #1E3A8A; margin: 0 0 3px;">
                        <i class="fa-solid fa-stopwatch" style="color: #2563EB;"></i> Database Response Test
                    </h5>
                    <p style="font-size: 9px; color: #3B82F6; margin:0;">Measure the average query response time.</p>
                </div>
                <button onclick="benchmarkDb()" id="btnBenchmark" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: 700;">
                    <i class="fa-solid fa-bolt"></i> Run Test
                </button>
            </div>
        </div>
    </div>

    <!-- Right Column: Auto-Backup Module & Backup Archive List -->
    <div class="card health-section backup-card" style="margin: 0; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div class="card-header" style="border-bottom: 2px solid #F1F5F9; padding-bottom: 16px; margin-bottom: 16px;">
                <div class="card-title" style="font-size: 16px; font-weight: 800; color: #0F172A;">
                    <i class="fa-solid fa-box-archive" style="color: #10B981; font-size: 18px;"></i>
                    <span>Backup Archives</span>
                </div>
                <span class="badge badge-success" style="font-size: 11px;">Auto-Compress Ready</span>
            </div>

            <!-- Main Button & Explanation -->
            <div class="backup-callout" style="background: #ECFDF5; border: 1px solid #10B981; padding: 18px; border-radius: 10px; margin-bottom: 20px;">
                <div class="backup-callout-inner">
                    <div class="backup-callout-copy">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                        <h5 style="font-size: 15px; font-weight: 800; color: #065F46; margin: 0 0 6px 0;">
                            Create a full system backup
                        </h5>
                        <p style="font-size: 12px; color: #047857; margin: 0 0 14px 0; line-height: 1.5;">
                            Packages the database and uploaded files into one protected ZIP archive.
                        </p>
                        </div>
                    </div>
                    <button onclick="runAutoBackup()" id="btnMainBackup" class="btn btn-success" style="width: 100%; padding: 14px; font-size: 14px; font-weight: 800; background: #059669; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); border: none;">
                        <i class="fa-solid fa-box-archive"></i> Create Backup
                    </button>
                </div>
            </div>

            <div class="backup-list-header">
                <h5 style="font-size: 12px; font-weight: 800; color: #0F172A; margin:0;">
                    <i class="fa-solid fa-clock-rotate-left" style="color:#64748B;"></i> Backup History <span style="color:#94A3B8;">(<?= count($backups) ?>)</span>
                </h5>
                <span class="backup-location"><i class="fa-solid fa-folder"></i> /storage/backups/</span>
            </div>

            <!-- Backup List Table -->
            <div class="backup-table-wrap" style="max-height: 280px; overflow-y: auto; border: 1px solid #E2E8F0; border-radius: 8px;">
                <table class="table backup-table" style="margin: 0; font-size: 12px;">
                    <thead style="background: #F8FAFC; position: sticky; top: 0; z-index: 5;">
                        <tr>
                            <th style="padding: 10px;">Archive Name (.zip)</th>
                            <th style="padding: 10px;">Date & Time</th>
                            <th style="padding: 10px;">Size</th>
                            <th style="padding: 10px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="backupsTableBody">
                        <?php if (empty($backups)): ?>
                        <tr id="emptyBackupRow">
                            <td colspan="4" style="text-align: center; color: #64748B; padding: 28px;">
                                <i class="fa-solid fa-folder-open" style="font-size: 24px; color: #CBD5E1; display: block; margin-bottom: 8px;"></i>
                                No backup archives found. Select <strong>Create Backup</strong> to generate the first archive.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($backups as $b): ?>
                            <tr id="backup-row-<?= md5($b['filename']) ?>">
                                <td style="font-family: monospace; font-weight: 700; color: #1E3A8A;">
                                    <i class="fa-solid fa-file-zipper" style="color: #F59E0B; margin-right: 6px;"></i>
                                    <?= htmlspecialchars($b['filename']) ?>
                                </td>
                                <td style="color: #475569;"><?= $b['created_at'] ?></td>
                                <td><span class="badge badge-navy" style="font-size: 10px;"><?= $b['size'] ?></span></td>
                                <td style="text-align: right; white-space: nowrap;"><span class="backup-action-group">
                                    <button onclick="testBackupRestore('<?= htmlspecialchars(addslashes($b['filename'])) ?>', this)" class="btn btn-sm" style="padding:5px 10px;font-size:11px;background:#DBEAFE;color:#1D4ED8;border:1px solid #BFDBFE;" title="Run non-destructive restore readiness test">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </button>
                                    <a href="<?= $b['url'] ?>" class="btn btn-success btn-sm" style="padding: 5px 10px; font-size: 11px; background: #10B981; color: #fff; text-decoration: none;" title="Download">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    <button onclick="deleteBackup('<?= htmlspecialchars(addslashes($b['filename'])) ?>', '<?= md5($b['filename']) ?>')" class="btn btn-danger btn-sm" style="padding: 5px 10px; font-size: 11px; background: #EF4444; color: #fff; border: none;" title="Delete Archive">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div style="margin-top: 16px; text-align: center;">
            <span style="font-size: 11px; color: #64748B;"><i class="fa-solid fa-lock"></i> Backup archives are protected by the Admin session authentication system.</span>
        </div>
    </div>
</div>

</div>

<script>
    async function saveSecurityPolicy(event) {
        event.preventDefault();
        const result = await App.post('index.php?action=save_security_settings', new FormData(event.target), event.submitter);
        if (result?.status === 'success') setTimeout(() => location.reload(), 700);
    }
    // 1. AJAX Auto-Backup Execution
    async function runAutoBackup() {
        const btnHeader = document.getElementById('btnHeaderBackup');
        const btnMain   = document.getElementById('btnMainBackup');

        // Set buttons to loading state
        const originalTextMain = btnMain.innerHTML;
        const originalTextHeader = btnHeader.innerHTML;

        btnMain.disabled = true;
        btnHeader.disabled = true;
        btnMain.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Compressing Database & Files...';
        btnHeader.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing Backup...';

        try {
            const formData = new FormData();
            formData.append('csrf_token', '<?= Helper::csrfToken() ?>');

            const res = await fetch('index.php?action=run_auto_backup', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data && data.status === 'success') {
                if (window.App && App.showToast) {
                    App.showToast('success', data.message);
                } else {
                    alert('✔ SUCCESS: ' + data.message);
                }
                setTimeout(() => location.reload(), 1500);
            } else {
                const errorMsg = data ? data.message : 'Failed to generate backup.';
                if (window.App && App.showToast) {
                    App.showToast('error', errorMsg);
                } else {
                    alert('❌ ERROR: ' + errorMsg);
                }
            }
        } catch (err) {
            console.error('Backup error:', err);
            if (window.App && App.showToast) {
                App.showToast('error', 'Network error while running the backup process.');
            } else {
                alert('❌ SYSTEM ERROR: Network error while running backup script.');
            }
        } finally {
            btnMain.disabled = false;
            btnHeader.disabled = false;
            btnMain.innerHTML = originalTextMain;
            btnHeader.innerHTML = originalTextHeader;
        }
    }

    // 2. Delete Backup Archive Function
    async function deleteBackup(filename, rowId) {
        if (!confirm(`Are you sure you want to delete backup archive "${filename}"?\n\nThis action cannot be undone.`)) {
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', '<?= Helper::csrfToken() ?>');
        formData.append('filename', filename);

        try {
            const res = await fetch('index.php?action=delete_backup', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data && data.status === 'success') {
                if (window.App && App.showToast) {
                    App.showToast('success', data.message);
                }
                const row = document.getElementById(`backup-row-${rowId}`);
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        const tbody = document.getElementById('backupsTableBody');
                        if (tbody && tbody.children.length === 0) {
                            tbody.innerHTML = `
                                <tr id="emptyBackupRow">
                                    <td colspan="4" style="text-align: center; color: #64748B; padding: 28px;">
                                        <i class="fa-solid fa-folder-open" style="font-size: 24px; color: #CBD5E1; display: block; margin-bottom: 8px;"></i>
                                        No backup archives found. Select <strong>Create Backup</strong> to generate the first archive.
                                    </td>
                                </tr>`;
                        }
                    }, 300);
                }
            } else {
                const errorMsg = data ? data.message : 'Failed to delete backup file.';
                if (window.App && App.showToast) {
                    App.showToast('error', errorMsg);
                } else {
                    alert('❌ ERROR: ' + errorMsg);
                }
            }
        } catch (err) {
            console.error('Delete error:', err);
            if (window.App && App.showToast) {
                App.showToast('error', 'Failed to connect to the server.');
            } else {
                alert('❌ ERROR: Failed to connect to server.');
            }
        }
    }

    async function testBackupRestore(filename, button) {
        const formData = new FormData();
        formData.append('csrf_token', '<?= Helper::csrfToken() ?>');
        formData.append('filename', filename);
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        try {
            const response = await fetch('index.php?action=test_backup_restore', {method:'POST', body:formData});
            const data = await response.json();
            if (window.App?.showToast) App.showToast(data.status === 'success' ? 'success' : 'error', data.message);
            else alert(data.message);
        } catch (error) {
            if (window.App?.showToast) App.showToast('error', 'Unable to test the backup archive.');
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    }

    // 3. MySQL Query Speed Benchmark Function
    async function benchmarkDb() {
        const btn = document.getElementById('btnBenchmark');
        const box = document.getElementById('benchmarkResultBox');
        const speedDisplay = document.getElementById('benchmarkSpeedDisplay');
        const ratingDisplay = document.getElementById('benchmarkRatingDisplay');
        const badge = document.getElementById('benchmarkBadge');

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing...';

        try {
            const res = await fetch('index.php?action=benchmark_db');
            const data = await res.json();

            if (data && data.status === 'success') {
                box.style.display = 'block';
                speedDisplay.textContent = data.data.speed_ms + ' ms';
                ratingDisplay.textContent = data.data.rating;

                if (badge) {
                    badge.innerHTML = `⚡ Response Speed: ${data.data.speed_ms} ms`;
                    badge.style.background = '#D1FAE5';
                    badge.style.color = '#065F46';
                }
                if (window.App && App.showToast) {
                    App.showToast('success', 'Database response test completed.');
                }
            } else {
                if (window.App && App.showToast) {
                    App.showToast('error', 'Failed to execute the database response test.');
                } else {
                    alert('❌ Failed to execute speed test.');
                }
            }
        } catch (err) {
            console.error('Benchmark error:', err);
            if (window.App && App.showToast) {
                App.showToast('error', 'An error occurred during the database response test.');
            } else {
                alert('❌ Error occurred during MySQL speed benchmark.');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    // Simulate SQL Injection attack to test WAF interception block screen
    function simulateWafTest() {
        if (confirm("Do you want to test the WAF system? A simulated SQL Injection request will be sent and intercepted by WAF in real-time.")) {
            window.open('index.php?page=home&test_waf=1%27%20UNION%20SELECT%20password%20FROM%20users', '_blank');
            setTimeout(() => {
                location.reload();
            }, 1500);
        }
    }

    // Clear WAF audit logs
    async function clearWafLogs() {
        if (!confirm("Are you sure you want to clear all WAF security audit logs?")) return;
        try {
            const res = await fetch('index.php?action=clear_waf_logs', { method: 'POST' });
            const data = await res.json();
            if (data && data.status === 'success') {
                if (window.App && App.showToast) {
                    App.showToast('success', 'Security audit logs cleared successfully.');
                }
                setTimeout(() => location.reload(), 600);
            }
        } catch(e) {
            console.error('Error clearing WAF logs:', e);
        }
    }
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
