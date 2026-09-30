<?php
/**
 * Roles & Permissions Management — TBBA ERP Module 1
 * Company: The Bridge Business Alliance (TBBA)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';

$currentUser = Auth::user();
$canEdit     = Auth::hasPermission('roles', 'edit');

// Module display labels
$moduleLabels = [
    'dashboard'     => 'Dashboard',
    'staff'         => 'Staff Management',
    'attendance'    => 'GPS Attendance',
    'tenders'       => 'Tender Management — KPI & Performance',
    'tender_board'  => 'Tender Management — Board & Pricing',
    'documents'     => 'Document Center',
    'letters'       => 'Inquiries & Letters',
    'leave'         => 'Leave & Permission',
    'expense'       => 'Expense Claims',
    'purchase'      => 'Purchase Requests',
    'departments'   => 'Departments Directory',
    'branches'      => 'Branch Locations',
    'positions'     => 'Positions & Hierarchy',
    'org_chart'     => 'Organization Chart',
    'announcements' => 'Announcements',
    'calendar'      => 'Calendar & Events',
    'notifications' => 'Notifications',
    'roles'         => 'Roles & Permissions',
    'audit_logs'    => 'Audit Logs',
    'system'        => 'System Health',
    'sales'         => 'Sales Operations & Records',
    'purchases'     => 'Purchases Operations & Records',
    'logistics'     => 'Logistics & Inventory Tracker',
    'projects'      => 'Projects & Tasks',
    'logbook'       => 'Activity Logbook',
    'finance_control' => 'Finance Control Center',
    'security'      => 'Account Security',
];
$actionLabels = [
    'view'    => 'View',
    'create'  => 'Create',
    'edit'    => 'Edit',
    'delete'  => 'Delete',
    'approve' => 'Approve',
    'join'    => 'Join / Withdraw',
    'view_staff_reports' => 'View Staff Reports',
];
$actionIcons = [
    'view'    => 'fa-eye',
    'create'  => 'fa-plus',
    'edit'    => 'fa-pen',
    'delete'  => 'fa-trash',
    'approve' => 'fa-check-double',
    'join'    => 'fa-user-plus',
    'view_staff_reports' => 'fa-users-viewfinder',
];
$actionColors = [
    'view'    => '#3B82F6',
    'create'  => '#10B981',
    'edit'    => '#F59E0B',
    'delete'  => '#EF4444',
    'approve' => '#8B5CF6',
    'join'    => '#0891B2',
    'view_staff_reports' => '#0F766E',
];

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>
<style>
/* ─── Page Styles ─────────────────────────────────────────── */
.tab-bar { display:flex; gap:0; border-bottom:2px solid #E2E8F0; margin-bottom:28px; }
.tab-btn { padding:12px 22px; font-size:13px; font-weight:700; color:#64748B; background:none; border:none; border-bottom:3px solid transparent; cursor:pointer; transition:all .2s; display:flex; align-items:center; gap:8px; margin-bottom:-2px; }
.tab-btn.active { color:#1D4ED8; border-bottom-color:#1D4ED8; }
.tab-btn:hover:not(.active) { color:#334155; background:#F8FAFC; }
.tab-panel { display:none; }
.tab-panel.active { display:block; }

/* Role Badge */
.role-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:50px; font-size:11px; font-weight:700; text-transform:uppercase; }

/* Permission Matrix Table */
.perm-table { width:100%; border-collapse:collapse; font-size:13px; }
.perm-table th { padding:10px 14px; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748B; background:#F8FAFC; white-space:nowrap; }
.perm-table td { padding:10px 14px; border-bottom:1px solid #F1F5F9; vertical-align:middle; }
.perm-table tr:hover td { background:#F8FAFC; }
.perm-table .module-header td { background:linear-gradient(135deg,#0F172A 0%,#1E3A8A 100%); color:#FFF; font-weight:700; font-size:12px; padding:8px 14px; }
.perm-table .module-header td span { opacity:.7; font-size:11px; }

/* Toggle Switch */
.perm-toggle { position:relative; display:inline-block; width:44px; height:24px; }
.perm-toggle input { opacity:0; width:0; height:0; }
.perm-slider { position:absolute; cursor:pointer; inset:0; background:#CBD5E1; border-radius:50px; transition:.25s; }
.perm-slider:before { content:''; position:absolute; height:18px; width:18px; left:3px; bottom:3px; background:#FFF; border-radius:50%; transition:.25s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
input:checked + .perm-slider { background:#10B981; }
input:checked + .perm-slider:before { transform:translateX(20px); }
.perm-toggle.locked .perm-slider { background:#10B981; cursor:not-allowed; opacity:.7; }

/* Role columns */
.role-col-header { text-align:center; padding:10px 8px !important; min-width:90px; }
.perm-cell { text-align:center; }

/* Staff override panel */
.override-workspace { display:grid; grid-template-columns:260px minmax(0,1fr); gap:18px; align-items:start; }
.override-user-panel { position:sticky; top:82px; border:1px solid #E2E8F0; border-radius:16px; background:#FFF; overflow:hidden; box-shadow:0 2px 8px rgba(15,23,42,.04); }
.override-user-list { max-height:calc(100vh - 250px); overflow-y:auto; padding:8px; }
.user-card-select { background:#FFF; border:1px solid transparent; border-radius:10px; padding:11px 12px; margin-bottom:4px; cursor:pointer; transition:all .18s; }
.user-card-select:hover { border-color:#BFDBFE; background:#F8FAFC; }
.user-card-select.selected { border-color:#93C5FD; background:#EFF6FF; box-shadow:inset 3px 0 0 #2563EB; }
.override-empty-state { min-height:300px; display:grid; place-items:center; padding:42px 24px; text-align:center; border:1px dashed #CBD5E1; border-radius:16px; background:#F8FAFC; }
.override-panel-card { border:1px solid #E2E8F0; border-radius:16px; background:#FFF; overflow:hidden; box-shadow:0 2px 8px rgba(15,23,42,.04); }
.override-panel-header { display:flex; justify-content:space-between; align-items:center; gap:16px; padding:16px 18px; border-bottom:1px solid #E2E8F0; }
.override-summary { display:flex; flex-wrap:wrap; gap:7px; margin-top:8px; }
.override-summary-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 8px; border-radius:999px; background:#F1F5F9; color:#475569; font-size:10px; font-weight:700; }
.override-tools { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.override-search { width:230px; min-height:36px; padding:8px 11px 8px 34px; border:1px solid #CBD5E1; border-radius:9px; background:#FFF; color:#0F172A; font-size:11px; outline:none; }
.override-search:focus { border-color:#60A5FA; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.override-permission-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; padding:14px; background:#F8FAFC; }
.override-module-card { min-width:0; border:1px solid #E2E8F0; border-radius:12px; background:#FFF; overflow:hidden; }
.override-module-header { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:11px 13px; border-bottom:1px solid #E2E8F0; background:#F8FAFC; }
.override-module-title { display:flex; align-items:center; gap:8px; min-width:0; color:#1E293B; font-size:12px; font-weight:800; }
.override-module-title i { color:#2563EB; }
.override-module-count { flex-shrink:0; color:#94A3B8; font-size:9px; font-weight:800; text-transform:uppercase; }
.override-permission-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:12px; align-items:center; padding:11px 13px; border-bottom:1px solid #F1F5F9; }
.override-permission-row:last-child { border-bottom:0; }
.override-permission-name { color:#334155; font-size:11px; font-weight:700; line-height:1.35; }
.override-permission-meta { display:flex; align-items:center; flex-wrap:wrap; gap:6px; margin-top:4px; color:#94A3B8; font-size:9px; }
.override-effective { display:inline-flex; align-items:center; gap:4px; padding:2px 6px; border-radius:999px; font-weight:800; }
.override-effective.allowed { background:#ECFDF5; color:#059669; }
.override-effective.denied { background:#FEF2F2; color:#DC2626; }
.override-choice { display:inline-flex; padding:3px; border:1px solid #E2E8F0; border-radius:9px; background:#F8FAFC; }
.override-choice button { min-width:48px; padding:6px 8px; border:0; border-radius:6px; background:transparent; color:#64748B; font-size:9px; font-weight:800; cursor:pointer; }
.override-choice button:hover { background:#FFF; color:#1E293B; }
.override-choice button.active-default { background:#FFF; color:#2563EB; box-shadow:0 1px 3px rgba(15,23,42,.12); }
.override-choice button.active-allow { background:#DCFCE7; color:#15803D; }
.override-choice button.active-deny { background:#FEE2E2; color:#DC2626; }
.override-no-results { display:none; grid-column:1/-1; padding:36px; text-align:center; color:#94A3B8; font-size:12px; }

@media (max-width:1400px) {
    .override-permission-grid { grid-template-columns:1fr; }
}

@media (max-width:980px) {
    .override-workspace { grid-template-columns:1fr; }
    .override-user-panel { position:static; }
    .override-user-list { display:flex; gap:8px; max-height:none; overflow-x:auto; }
    .user-card-select { flex:0 0 220px; margin:0; }
}

@media (max-width:640px) {
    .override-panel-header { align-items:stretch; flex-direction:column; }
    .override-tools, .override-search { width:100%; }
    .override-tools > div { width:100%; }
    .override-permission-grid { padding:8px; gap:8px; }
    .override-permission-row { grid-template-columns:1fr; }
    .override-choice { width:100%; }
    .override-choice button { flex:1; min-height:36px; }
}

/* Role info cards */
.role-info-card { background:#FFF; border:1px solid #E2E8F0; border-radius:16px; padding:20px; transition:all .3s; }
.role-info-card:hover { box-shadow:0 10px 25px rgba(0,0,0,.06); transform:translateY(-2px); }

/* Sticky header for matrix */
.matrix-wrapper { overflow-x:auto; border-radius:12px; border:1px solid #E2E8F0; }
.perm-table thead { position:sticky; top:0; z-index:10; }

/* Login History */
.lh-badge-success { background:#DCFCE7; color:#15803D; padding:3px 10px; border-radius:50px; font-size:11px; font-weight:700; }
.lh-badge-failed  { background:#FEF2F2; color:#DC2626; padding:3px 10px; border-radius:50px; font-size:11px; font-weight:700; }
</style>

<!-- ─── Header Banner ─────────────────────────────────────── -->
<div class="module-page-header" style="background:linear-gradient(135deg,#0F172A 0%,#4C1D95 100%);color:#FFF;border-radius:20px;padding:32px;margin-bottom:28px;position:relative;overflow:hidden;box-shadow:0 10px 25px -5px rgba(15,23,42,.3);">
    <div style="position:absolute;top:-20%;right:-5%;width:300px;height:300px;background:radial-gradient(circle,rgba(139,92,246,.3) 0%,transparent 70%);border-radius:50%;"></div>
    <div style="position:relative;z-index:2;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
        <div>
            <div class="module-page-eyebrow" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);color:#C4B5FD;font-size:12px;font-weight:700;padding:6px 14px;border-radius:50px;margin-bottom:12px;text-transform:uppercase;">
                <i class="fa-solid fa-shield-halved"></i> Security & Access Control
            </div>
            <h1 style="font-size:28px;font-weight:800;margin-bottom:8px;">Roles & Permissions</h1>
            <p style="color:#CBD5E1;font-size:14px;max-width:600px;margin:0;line-height:1.6;">
                Configure the permission matrix for each role, manage user-level overrides, and review login activity.
            </p>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;align-items:flex-end;">
            <div class="module-header-meta" style="background:rgba(255,255,255,.1);border-radius:12px;padding:14px 18px;text-align:right;">
                <div style="font-size:11px;color:#CBD5E1;text-transform:uppercase;font-weight:700;">Your Access Level</div>
                <?php $r = Role::findByName($currentUser['role'] ?? 'staff'); ?>
                <div style="font-size:18px;font-weight:800;margin-top:4px;display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid <?= htmlspecialchars($r['icon'] ?? 'fa-user') ?>" style="color:<?= htmlspecialchars($r['color'] ?? '#64748B') ?>;"></i>
                    <?= htmlspecialchars($r['display_name'] ?? Auth::roleLabel()) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── Stats Row ─────────────────────────────────────────── -->
<div class="module-stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
    <?php
    $totalUsers = count($allUsers ?? []);
    $totalRoles = count($roles ?? []);
    $roleCounts = [];
    foreach (($allUsers ?? []) as $u) { $roleCounts[$u['role']] = ($roleCounts[$u['role']] ?? 0) + 1; }
    ?>
    <div class="module-stat-card" style="background:#FFF;border:1px solid #E2E8F0;border-radius:16px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,.04);border-left:5px solid #7C3AED;">
        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Total Users</div>
        <div style="font-size:30px;font-weight:800;color:#0F172A;margin:6px 0;"><?= $totalUsers ?></div>
        <div style="font-size:12px;color:#7C3AED;"><i class="fa-solid fa-users"></i> All registered accounts</div>
    </div>
    <div class="module-stat-card" style="background:#FFF;border:1px solid #E2E8F0;border-radius:16px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,.04);border-left:5px solid #1D4ED8;">
        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">System Roles</div>
        <div style="font-size:30px;font-weight:800;color:#0F172A;margin:6px 0;"><?= $totalRoles ?></div>
        <div style="font-size:12px;color:#1D4ED8;"><i class="fa-solid fa-user-gear"></i> Configured roles</div>
    </div>
    <div class="module-stat-card" style="background:#FFF;border:1px solid #E2E8F0;border-radius:16px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,.04);border-left:5px solid #059669;">
        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Active Staff</div>
        <div style="font-size:30px;font-weight:800;color:#0F172A;margin:6px 0;"><?= $roleCounts['staff'] ?? 0 ?></div>
        <div style="font-size:12px;color:#059669;"><i class="fa-solid fa-user-check"></i> Standard staff accounts</div>
    </div>
    <div class="module-stat-card" style="background:#FFF;border:1px solid #E2E8F0;border-radius:16px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,.04);border-left:5px solid #D97706;">
        <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Departments</div>
        <div style="font-size:30px;font-weight:800;color:#0F172A;margin:6px 0;"><?= count($departments ?? []) ?></div>
        <div style="font-size:12px;color:#D97706;"><i class="fa-solid fa-sitemap"></i> Organizational units</div>
    </div>
</div>

<!-- ─── Tab Navigation ────────────────────────────────────── -->
<div class="tab-bar module-tabs">
    <button class="tab-btn active" onclick="switchTab('matrix', this)">
        <i class="fa-solid fa-table"></i> Permission Matrix
    </button>
    <button class="tab-btn" onclick="switchTab('overrides', this)">
        <i class="fa-solid fa-user-cog"></i> User Overrides
    </button>
    <button class="tab-btn" onclick="switchTab('role-guide', this)">
        <i class="fa-solid fa-book-open"></i> Role Guide
    </button>
    <button class="tab-btn" onclick="switchTab('login-history', this)">
        <i class="fa-solid fa-clock-rotate-left"></i> Login History
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 1: PERMISSION MATRIX
═══════════════════════════════════════════════════════════ -->
<div id="tab-matrix" class="tab-panel active">
    <div class="card module-data-card" style="border:1px solid #E2E8F0;border-radius:20px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,.04);background:#FFF;">
        <div style="padding:20px 24px;border-bottom:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="font-size:18px;font-weight:800;color:#0F172A;margin:0;">Permission Matrix</h3>
                <p style="font-size:13px;color:#64748B;margin:4px 0 0;">Toggle allows/denies per role.</p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                <input class="module-search-input" type="search" placeholder="Search module or permission..." oninput="filterPermissionMatrix(this.value)" aria-label="Search permission matrix">
                <?php if ($canEdit): ?>
                <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:8px;padding:8px 11px;font-size:10px;color:#92400E;display:flex;align-items:center;gap:6px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>Changes apply immediately
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="matrix-wrapper" style="max-height:72vh;overflow-y:auto;">
            <table class="perm-table" id="permMatrix">
                <thead>
                    <tr style="border-bottom:2px solid #E2E8F0;">
                        <th style="text-align:left;min-width:200px;background:#F8FAFC;">Module / Action</th>
                        <?php foreach (($roles ?? []) as $role): ?>
                        <th class="role-col-header" style="background:#F8FAFC;">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                                <i class="fa-solid <?= htmlspecialchars($role['icon'] ?? 'fa-user') ?>" style="font-size:16px;color:<?= htmlspecialchars($role['color'] ?? '#64748B') ?>;"></i>
                                <span style="font-size:10px;font-weight:800;color:#334155;text-transform:uppercase;line-height:1.2;"><?= htmlspecialchars($role['display_name']) ?></span>
                                <?php if ($role['name'] === 'super_admin'): ?>
                                <span style="font-size:9px;color:#7C3AED;font-style:italic;">Full Access</span>
                                <?php endif; ?>
                            </div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($modules ?? []) as $modKey => $modActions): ?>
                    <tr class="module-header">
                        <td colspan="<?= count($roles ?? []) + 1 ?>">
                            <i class="fa-solid fa-layer-group" style="margin-right:8px;opacity:.7;"></i>
                            <?= htmlspecialchars($moduleLabels[$modKey] ?? ucfirst($modKey)) ?>
                            <span>— <?= count($modActions) ?> permission(s)</span>
                        </td>
                    </tr>
                    <?php foreach ($modActions as $perm): ?>
                    <?php $action = $perm['action']; ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="width:24px;height:24px;border-radius:6px;background:<?= $actionColors[$action] ?? '#64748B' ?>22;display:inline-flex;align-items:center;justify-content:center;font-size:11px;color:<?= $actionColors[$action] ?? '#64748B' ?>;">
                                    <i class="fa-solid <?= $actionIcons[$action] ?? 'fa-circle' ?>"></i>
                                </span>
                                <span style="font-weight:600;color:#334155;"><?= htmlspecialchars($perm['label'] ?? ucfirst($action)) ?></span>
                            </div>
                        </td>
                        <?php foreach (($roles ?? []) as $role): ?>
                        <?php
                            $roleName    = $role['name'];
                            $isAllowed   = $matrix[$roleName][$modKey][$action] ?? false;
                            $isSuperAdmin = $roleName === 'super_admin';
                            $toggleId    = "perm_{$roleName}_{$modKey}_{$action}";
                        ?>
                        <td class="perm-cell">
                            <?php if ($isSuperAdmin): ?>
                                <label class="perm-toggle locked" title="Super Admin always has full access">
                                    <input type="checkbox" checked disabled>
                                    <span class="perm-slider"></span>
                                </label>
                            <?php elseif ($canEdit): ?>
                                <label class="perm-toggle" title="<?= $isAllowed ? 'Click to deny' : 'Click to allow' ?>">
                                    <input type="checkbox"
                                           id="<?= $toggleId ?>"
                                           <?= $isAllowed ? 'checked' : '' ?>
                                           onchange="togglePermission('<?= $roleName ?>','<?= $modKey ?>','<?= $action ?>',this)">
                                    <span class="perm-slider"></span>
                                </label>
                            <?php else: ?>
                                <span style="color:<?= $isAllowed ? '#10B981' : '#CBD5E1' ?>;font-size:16px;">
                                    <i class="fa-solid <?= $isAllowed ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 2: USER OVERRIDES
═══════════════════════════════════════════════════════════ -->
<div id="tab-overrides" class="tab-panel">
    <div class="override-workspace">

        <!-- Left: User picker -->
        <div class="override-user-panel">
            <div style="padding:14px 16px;border-bottom:1px solid #E2E8F0;">
                <h4 style="font-size:13px;font-weight:800;color:#0F172A;margin:0;">Select User</h4>
                <p style="margin:3px 0 0;color:#94A3B8;font-size:10px;">Choose an account to manage exceptions.</p>
                <input type="text" id="userSearchInput" placeholder="Search by name..." oninput="filterUsers(this.value)"
                    style="width:100%;margin-top:10px;padding:8px 10px;border:1px solid #CBD5E1;border-radius:8px;font-size:11px;outline:none;box-sizing:border-box;">
            </div>
            <div id="userPickerList" class="override-user-list">
                <?php foreach (($allUsers ?? []) as $u): ?>
                <div class="user-card-select" onclick="loadUserOverrides(<?= $u['id'] ?>, this)"
                     data-name="<?= strtolower(htmlspecialchars($u['name'])) ?>"
                     data-id="<?= $u['id'] ?>">
                    <div style="display:flex;align-items:center;gap:9px;">
                        <div style="width:32px;height:32px;border-radius:9px;background:#DBEAFE;color:#1D4ED8;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:11px;flex-shrink:0;">
                            <?= strtoupper(substr($u['name'], 0, 2)) ?>
                        </div>
                        <div style="overflow:hidden;">
                            <div style="font-weight:700;font-size:11px;color:#0F172A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($u['name']) ?></div>
                            <div style="font-size:9px;color:#64748B;margin-top:2px;"><?= htmlspecialchars(Auth::roleLabel($u['role'])) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Override panel -->
        <div id="overridePanel">
            <div class="override-empty-state">
                <div>
                    <i class="fa-solid fa-user-shield" style="font-size:34px;color:#CBD5E1;margin-bottom:12px;display:block;"></i>
                    <p style="color:#64748B;font-size:13px;font-weight:700;margin:0;">Select a user to manage access exceptions</p>
                    <p style="color:#94A3B8;font-size:10px;margin:5px 0 0;">Role defaults remain unchanged unless an override is applied.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 3: ROLE GUIDE
═══════════════════════════════════════════════════════════ -->
<div id="tab-role-guide" class="tab-panel">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
        <?php foreach (($roles ?? []) as $role): ?>
        <div class="role-info-card">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                <div style="width:48px;height:48px;border-radius:14px;background:<?= htmlspecialchars($role['color']) ?>22;display:flex;align-items:center;justify-content:center;font-size:22px;color:<?= htmlspecialchars($role['color']) ?>;">
                    <i class="fa-solid <?= htmlspecialchars($role['icon'] ?? 'fa-user') ?>"></i>
                </div>
                <div>
                    <div style="font-size:16px;font-weight:800;color:#0F172A;"><?= htmlspecialchars($role['display_name']) ?></div>
                    <span class="role-badge" style="background:<?= htmlspecialchars($role['color']) ?>22;color:<?= htmlspecialchars($role['color']) ?>;">
                        <?= htmlspecialchars($role['name']) ?>
                    </span>
                </div>
            </div>
            <p style="font-size:13px;color:#475569;line-height:1.6;margin:0 0 14px;">
                <?= htmlspecialchars($role['description'] ?? '') ?>
            </p>
            <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid #F1F5F9;">
                <span style="font-size:12px;color:#64748B;">
                    <i class="fa-solid fa-users" style="margin-right:4px;"></i>
                    <?= $roleCounts[$role['name']] ?? 0 ?> user(s) assigned
                </span>
                <span style="font-size:11px;color:#94A3B8;font-weight:700;">Authority Level <?= $role['level'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 4: LOGIN HISTORY
═══════════════════════════════════════════════════════════ -->
<div id="tab-login-history" class="tab-panel">
    <div class="card" style="border:1px solid #E2E8F0;border-radius:20px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,.04);background:#FFF;">
        <div style="padding:20px 24px;border-bottom:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="font-size:18px;font-weight:800;color:#0F172A;margin:0;">Login History</h3>
                <p style="font-size:13px;color:#64748B;margin:4px 0 0;">Track system access by selecting a user below.</p>
            </div>
            <select id="loginHistoryUserSelect" onchange="loadLoginHistory(this.value)"
                style="padding:10px 16px;border:1px solid #CBD5E1;border-radius:10px;font-size:13px;color:#0F172A;background:#FFF;font-weight:600;outline:none;">
                <option value="">— Select user —</option>
                <?php foreach (($allUsers ?? []) as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars(Auth::roleLabel($u['role'])) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="loginHistoryContent" style="padding:40px;text-align:center;color:#94A3B8;">
            <i class="fa-solid fa-clock-rotate-left" style="font-size:36px;margin-bottom:12px;display:block;"></i>
            Select a user above to view their login history.
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODALS
═══════════════════════════════════════════════════════════ -->
<!-- Override detail modal -->
<div class="modal-overlay module-clean-modal" id="overrideDetailModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);z-index:99999;align-items:center;justify-content:center;">
    <div class="modal-box" style="background:#FFF;border-radius:24px;width:100%;max-width:560px;overflow:hidden;box-shadow:0 25px 50px rgba(0,0,0,.25);animation:modalFadeIn .3s ease;">
        <div style="background:linear-gradient(135deg,#0F172A,#4C1D95);color:#FFF;padding:22px 28px;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:17px;font-weight:800;"><i class="fa-solid fa-user-cog" style="margin-right:8px;color:#C4B5FD;"></i>User Permission Override</h3>
            <button onclick="App.closeModal('overrideDetailModal')" style="background:rgba(255,255,255,.1);border:none;color:#FFF;width:32px;height:32px;border-radius:50%;font-size:16px;cursor:pointer;">&times;</button>
        </div>
        <div id="overrideDetailContent" style="padding:24px;"></div>
    </div>
</div>
<style>@keyframes modalFadeIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}</style>

<script>
// ─── Tab Switcher ─────────────────────────────────────────────────────────────
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// ─── Permission Matrix Toggle ──────────────────────────────────────────────────
async function togglePermission(roleName, module, action, checkbox) {
    const allowed = checkbox.checked ? 1 : 0;
    const fd = new FormData();
    fd.append('role_name', roleName);
    fd.append('module', module);
    fd.append('action', action);
    fd.append('allowed', allowed);
    fd.append('csrf_token', window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '');

    checkbox.disabled = true;
    try {
        const res = await fetch('index.php?action=update_role_permission', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const result = await res.json();
        if (result.status === 'success') {
            App.showToast('success', `Permission updated: ${roleName} → ${module}.${action} = ${allowed ? 'ALLOWED' : 'DENIED'}`);
        } else {
            App.showToast('error', result.message);
            checkbox.checked = !checkbox.checked; // rollback
        }
    } catch(e) {
        App.showToast('error', 'Network error. Change was not saved.');
        checkbox.checked = !checkbox.checked;
    } finally {
        checkbox.disabled = false;
    }
}

// ─── User Override Panel ───────────────────────────────────────────────────────
let currentOverrideUserId = null;

async function loadUserOverrides(userId, cardEl) {
    document.querySelectorAll('.user-card-select').forEach(c => c.classList.remove('selected'));
    const targetEl = cardEl || document.querySelector(`.user-card-select[data-id="${userId}"]`);
    if (targetEl) targetEl.classList.add('selected');
    currentOverrideUserId = userId;

    const panel = document.getElementById('overridePanel');
    panel.innerHTML = '<div style="text-align:center;padding:60px;color:#94A3B8;"><i class="fa-solid fa-spinner fa-spin" style="font-size:28px;"></i><p style="margin-top:12px;">Loading overrides...</p></div>';

    try {
        const res = await fetch(`index.php?action=get_user_overrides&user_id=${userId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await res.json();
        if (result.status !== 'success') {
            App.showToast('error', result.message);
            return;
        }
        renderOverridePanel(result.data);
    } catch(e) {
        App.showToast('error', 'Failed to load overrides.');
    }
}

function renderOverridePanel(data) {
    const { user, overrides, rolePerms } = data;
    const panel = document.getElementById('overridePanel');

    const moduleLabels = <?= json_encode($moduleLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const permissionCatalog = <?= json_encode($modules ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
    const escapeOverrideText = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const overrideCount = Object.values(overrides).reduce((total, actions) => total + Object.keys(actions || {}).length, 0);
    const effectiveAllowedCount = Object.entries(permissionCatalog).reduce((total, [module, permissions]) => {
        return total + permissions.filter((permission) => {
            const action = permission.action;
            const custom = overrides[module]?.[action];
            return custom !== undefined ? custom : (rolePerms[module]?.[action] ?? false);
        }).length;
    }, 0);
    const totalPermissionCount = Object.values(permissionCatalog).reduce((total, permissions) => total + permissions.length, 0);

    let html = `
        <div class="override-panel-card">
            <div class="override-panel-header">
                <div>
                    <h4 style="font-size:14px;font-weight:800;color:#0F172A;margin:0;">${escapeOverrideText(user.name)}</h4>
                    <div class="override-summary">
                        <span class="override-summary-pill"><i class="fa-solid fa-user-tag"></i>${escapeOverrideText(user.role)}</span>
                        <span class="override-summary-pill"><i class="fa-solid fa-sliders"></i>${overrideCount} override${overrideCount === 1 ? '' : 's'}</span>
                        <span class="override-summary-pill"><i class="fa-solid fa-shield-halved"></i>${effectiveAllowedCount} of ${totalPermissionCount} allowed</span>
                    </div>
                </div>
                <div class="override-tools">
                    <div style="position:relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:11px;"></i>
                        <input class="override-search" type="search" placeholder="Search permissions..." oninput="filterOverridePermissions(this.value)" aria-label="Search user permissions">
                    </div>
                    ${canEdit && overrideCount > 0 ? `<button onclick="clearAllOverrides(${user.id})" class="btn" style="min-height:36px;background:#FFF;color:#DC2626;border:1px solid #FECACA;padding:7px 11px;border-radius:8px;font-size:10px;font-weight:800;cursor:pointer;white-space:nowrap;">
                        <i class="fa-solid fa-rotate-left"></i> Reset All
                    </button>` : ''}
                </div>
            </div>
            <div class="override-permission-grid" id="overridePermissionGrid">`;

    for (const [modKey, permissions] of Object.entries(permissionCatalog)) {
        const modLabel = moduleLabels[modKey] || modKey.replace(/_/g, ' ');
        html += `<section class="override-module-card" data-override-module="${escapeOverrideText(modLabel.toLowerCase())}">
            <header class="override-module-header">
                <div class="override-module-title"><i class="fa-solid fa-layer-group"></i><span>${escapeOverrideText(modLabel)}</span></div>
                <span class="override-module-count">${permissions.length} permission${permissions.length === 1 ? '' : 's'}</span>
            </header>
            <div>`;

        for (const permission of permissions) {
            const act = permission.action;
            const permissionLabel = permission.label || act.replace(/_/g, ' ');
            const roleVal = rolePerms[modKey]?.[act] ?? false;
            const overrideVal = overrides[modKey]?.[act];
            const hasOverride = overrideVal !== undefined;
            const effective = hasOverride ? overrideVal : roleVal;

            html += `<div class="override-permission-row" data-override-permission="${escapeOverrideText(`${permissionLabel} ${modKey}.${act}`.toLowerCase())}">
                <div>
                    <div class="override-permission-name">${escapeOverrideText(permissionLabel)}</div>
                    <div class="override-permission-meta">
                        <span>Role default: <strong>${roleVal ? 'Allow' : 'Deny'}</strong></span>
                        <span class="override-effective ${effective ? 'allowed' : 'denied'}"><i class="fa-solid ${effective ? 'fa-check' : 'fa-xmark'}"></i>${effective ? 'Allowed' : 'Denied'}</span>
                    </div>
                </div>
                ${canEdit ? `<div class="override-choice" role="group" aria-label="Override for ${escapeOverrideText(permissionLabel)}">
                    <button type="button" class="${!hasOverride ? 'active-default' : ''}" onclick="setOverride(${user.id},'${modKey}','${act}','reset')" title="Use role default">Default</button>
                    <button type="button" class="${hasOverride && overrideVal ? 'active-allow' : ''}" onclick="setOverride(${user.id},'${modKey}','${act}',1)">Allow</button>
                    <button type="button" class="${hasOverride && !overrideVal ? 'active-deny' : ''}" onclick="setOverride(${user.id},'${modKey}','${act}',0)">Deny</button>
                </div>` : ''}
            </div>`;
        }
        html += '</div></section>';
    }

    html += `<div class="override-no-results" id="overrideNoResults"><i class="fa-solid fa-magnifying-glass" style="display:block;font-size:22px;margin-bottom:8px;"></i>No matching permissions found.</div></div></div>`;
    panel.innerHTML = html;
}

function filterOverridePermissions(query) {
    const normalized = query.trim().toLowerCase();
    let visibleModules = 0;
    document.querySelectorAll('.override-module-card').forEach(card => {
        const moduleMatches = card.dataset.overrideModule.includes(normalized);
        let visibleRows = 0;
        card.querySelectorAll('.override-permission-row').forEach(row => {
            const matches = !normalized || moduleMatches || row.dataset.overridePermission.includes(normalized);
            row.style.display = matches ? '' : 'none';
            if (matches) visibleRows++;
        });
        card.style.display = visibleRows > 0 ? '' : 'none';
        if (visibleRows > 0) visibleModules++;
    });
    const empty = document.getElementById('overrideNoResults');
    if (empty) empty.style.display = visibleModules === 0 ? 'block' : 'none';
}

async function setOverride(userId, module, action, allowed) {
    const fd = new FormData();
    fd.append('user_id', userId);
    fd.append('module', module);
    fd.append('action', action);
    fd.append('allowed', allowed);
    fd.append('csrf_token', window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '');

    try {
        const res = await fetch('index.php?action=set_user_override', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const result = await res.json();
        if (result.status === 'success') {
            App.showToast('success', result.message);
            // Reload the override panel
            loadUserOverrides(userId, null);
        } else {
            App.showToast('error', result.message);
        }
    } catch(e) {
        App.showToast('error', 'Network error.');
    }
}

async function clearAllOverrides(userId) {
    const confirmed = await App.confirm(
        'Reset All Overrides',
        'This will remove all custom permission overrides for this user. They will revert to their role defaults. Continue?',
        'Yes, Reset All'
    );
    if (!confirmed) return;

    const fd = new FormData();
    fd.append('user_id', userId);
    fd.append('csrf_token', window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '');
    try {
        const res = await fetch('index.php?action=clear_user_overrides', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const result = await res.json();
        if (result.status === 'success') {
            App.showToast('success', result.message);
            loadUserOverrides(userId, null);
        } else {
            App.showToast('error', result.message);
        }
    } catch(e) {
        App.showToast('error', 'Network error.');
    }
}

// ─── Permission Matrix Search ───────────────────────────────────────────────
function filterPermissionMatrix(query) {
    const tbody = document.querySelector('#permMatrix tbody');
    if (!tbody) return;
    const normalized = query.trim().toLowerCase();
    let header = null;
    let actionRows = [];

    const applyGroup = () => {
        if (!header) return;
        const headerMatches = header.textContent.toLowerCase().includes(normalized);
        let visibleActions = 0;
        actionRows.forEach(row => {
            const matches = !normalized || headerMatches || row.textContent.toLowerCase().includes(normalized);
            row.style.display = matches ? '' : 'none';
            if (matches) visibleActions++;
        });
        header.style.display = (!normalized || headerMatches || visibleActions > 0) ? '' : 'none';
    };

    Array.from(tbody.children).forEach(row => {
        if (row.classList.contains('module-header')) {
            applyGroup();
            header = row;
            actionRows = [];
        } else {
            actionRows.push(row);
        }
    });
    applyGroup();
}

// ─── User Search Filter ─────────────────────────────────────────────────────
function filterUsers(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.user-card-select').forEach(card => {
        card.style.display = card.dataset.name.includes(q) ? '' : 'none';
    });
}

// ─── Login History ──────────────────────────────────────────────────────────
async function loadLoginHistory(userId) {
    if (!userId) return;
    const content = document.getElementById('loginHistoryContent');
    content.innerHTML = '<div style="padding:40px;text-align:center;color:#94A3B8;"><i class="fa-solid fa-spinner fa-spin" style="font-size:28px;"></i><p style="margin-top:12px;">Loading...</p></div>';

    try {
        const res = await fetch(`index.php?action=get_login_history&user_id=${userId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await res.json();
        if (result.status !== 'success') { App.showToast('error', result.message); return; }

        const { user, history } = result.data;
        if (!history.length) {
            content.innerHTML = '<div style="padding:40px;text-align:center;color:#94A3B8;"><i class="fa-solid fa-circle-info" style="font-size:32px;margin-bottom:12px;display:block;"></i>No login history found for this user.</div>';
            return;
        }

        let html = `<div style="overflow-x:auto;"><table class="table datatable" style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="background:#F8FAFC;border-bottom:2px solid #E2E8F0;">
                <th style="padding:12px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">#</th>
                <th style="padding:12px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Date & Time</th>
                <th style="padding:12px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Method</th>
                <th style="padding:12px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">IP Address</th>
                <th style="padding:12px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Status</th>
                <th style="padding:12px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Browser / Agent</th>
            </tr></thead>
            <tbody>`;

        history.forEach((row, i) => {
            const statusBadge = row.status === 'success'
                ? '<span class="lh-badge-success"><i class="fa-solid fa-check"></i> Success</span>'
                : '<span class="lh-badge-failed"><i class="fa-solid fa-times"></i> Failed</span>';
            const methodBadge = row.method === 'google'
                ? '<span style="background:#EFF6FF;color:#2563EB;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700;"><i class="fa-brands fa-google"></i> Google</span>'
                : '<span style="background:#F0FDF4;color:#15803D;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700;"><i class="fa-solid fa-envelope"></i> Email</span>';

            html += `<tr style="border-bottom:1px solid #F1F5F9;">
                <td style="padding:12px 20px;color:#94A3B8;">${i + 1}</td>
                <td style="padding:12px 20px;font-weight:600;color:#0F172A;">${row.created_at}</td>
                <td style="padding:12px 20px;">${methodBadge}</td>
                <td style="padding:12px 20px;font-family:monospace;color:#334155;">${row.ip_address || '—'}</td>
                <td style="padding:12px 20px;">${statusBadge}</td>
                <td style="padding:12px 20px;color:#64748B;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${row.user_agent || ''}">${(row.user_agent || '').substring(0, 60)}${(row.user_agent || '').length > 60 ? '...' : ''}</td>
            </tr>`;
        });

        html += '</tbody></table></div>';
        content.innerHTML = html;

        // Init DataTables if available
        if (typeof $.fn.DataTable !== 'undefined') {
            $(content).find('table.datatable').DataTable({ pageLength: 15, order: [] });
        }
    } catch(e) {
        App.showToast('error', 'Failed to load login history.');
    }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
