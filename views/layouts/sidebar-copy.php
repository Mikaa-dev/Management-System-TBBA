<?php
/**
 * Sidebar Navigation — TBBA ERP
 * Company: The Bridge Business Alliance (TBBA)
 * Updated: Module 1 — Role-aware navigation + new module links
 */
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
$currentUser = Auth::user();
$activePage  = $_GET['page'] ?? 'dashboard';
?>
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-header">
        <img src="assets/images/logo.png" alt="TBBA Logo" class="sidebar-logo">
        <div class="sidebar-brand">
            TBBA
            <span>The Bridge Business Alliance</span>
        </div>
    </div>

    <ul class="sidebar-menu">
        <!-- ─── MAIN ─────────────────────────────────────── -->
        <li class="sidebar-heading">Main</li>

        <li class="sidebar-item">
            <a href="index.php?page=dashboard" class="sidebar-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <?php if (Auth::hasPermission('attendance', 'view')): ?>
        <li class="sidebar-item">
            <a href="index.php?page=attendance" class="sidebar-link <?= $activePage === 'attendance' ? 'active' : '' ?>">
                <i class="fa-solid fa-location-dot"></i>
                <span>GPS Attendance</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (Auth::hasPermission('announcements', 'view')): ?>
        <li class="sidebar-item">
            <a href="index.php?page=announcements" class="sidebar-link <?= $activePage === 'announcements' ? 'active' : '' ?>">
                <i class="fa-solid fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (Auth::hasPermission('calendar', 'view')): ?>
        <li class="sidebar-item">
            <a href="index.php?page=calendar" class="sidebar-link <?= $activePage === 'calendar' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Calendar & Events</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- ─── HR & PEOPLE DROPDOWN ──────────────────────── -->
        <?php
        $showHR = Auth::hasPermission('leave','view') || Auth::hasPermission('staff','view');
        $isHrActive = in_array($activePage, ['staff', 'leave']);
        if ($showHR):
        ?>
        <li class="sidebar-heading">HR Management</li>
        <li class="sidebar-item">
            <a onclick="toggleSidebarGroup('hrSubmenu', this, event)" class="sidebar-link sidebar-dropdown-toggle <?= $isHrActive ? 'active expanded' : '' ?>">
                <div style="display:flex;align-items:center;gap:12px;">
                    <i class="fa-solid fa-users-viewfinder"></i>
                    <span>HR Management</span>
                </div>
                <i class="fa-solid fa-chevron-down toggle-icon"></i>
            </a>
            <ul class="sidebar-submenu" id="hrSubmenu" style="display:<?= $isHrActive ? 'block' : 'none' ?>;">
                <?php if (Auth::hasPermission('staff','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=staff" class="sidebar-link <?= $activePage === 'staff' ? 'active' : '' ?>">
                        <i class="fa-solid fa-users-gear"></i>
                        <span>Staff List</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (Auth::hasPermission('leave','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=leave" class="sidebar-link <?= $activePage === 'leave' ? 'active' : '' ?>">
                        <i class="fa-solid fa-calendar-minus"></i>
                        <span>Leave & Permission</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- ─── FINANCE DROPDOWN ───────────────────────────── -->
        <?php
        $showFinance = Auth::hasPermission('expense','view') || Auth::hasPermission('purchase','view') || Auth::hasPermission('sales','view') || Auth::hasPermission('purchases','view') || Auth::hasPermission('finance_control','view');
        $isFinanceHubActive = in_array($activePage, ['expense', 'purchase', 'finance_control', 'finance_receipt']);
        $isSalesActive = ($activePage === 'sales');
        $isPurchasesActive = ($activePage === 'purchases');
        $activeTab = $_GET['tab'] ?? '';
        if ($showFinance):
        ?>
        <li class="sidebar-heading">Finance</li>
        <?php if (Auth::hasPermission('expense','view') || Auth::hasPermission('purchase','view') || Auth::hasPermission('finance_control','view')): ?>
        <li class="sidebar-item">
            <a onclick="toggleSidebarGroup('financeSubmenu', this, event)" class="sidebar-link sidebar-dropdown-toggle <?= $isFinanceHubActive ? 'active expanded' : '' ?>">
                <div style="display:flex;align-items:center;gap:12px;">
                    <i class="fa-solid fa-coins"></i>
                    <span>Finance Hub</span>
                </div>
                <i class="fa-solid fa-chevron-down toggle-icon"></i>
            </a>
            <ul class="sidebar-submenu" id="financeSubmenu" style="display:<?= $isFinanceHubActive ? 'block' : 'none' ?>;">
                <?php if (Auth::hasPermission('expense','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=expense" class="sidebar-link <?= $activePage === 'expense' ? 'active' : '' ?>">
                        <i class="fa-solid fa-receipt"></i>
                        <span>Expense Claims</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (Auth::hasPermission('purchase','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=purchase" class="sidebar-link <?= $activePage === 'purchase' ? 'active' : '' ?>">
                        <i class="fa-solid fa-cart-plus"></i>
                        <span>Purchase Requests</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Sales Link -->
        <?php if (Auth::hasPermission('sales', 'view')): ?>
        <li class="sidebar-item">
            <a href="index.php?page=sales" class="sidebar-link <?= $activePage === 'sales' ? 'active' : '' ?>">
                <i class="fa-regular fa-square-plus" style="font-size: 16px;"></i>
                <span>Sales</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Purchases Link -->
        <?php if (Auth::hasPermission('purchases', 'view')): ?>
        <li class="sidebar-item">
            <a href="index.php?page=purchases" class="sidebar-link <?= $activePage === 'purchases' ? 'active' : '' ?>">
                <i class="fa-regular fa-square-minus" style="font-size: 16px;"></i>
                <span>Purchases</span>
            </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <!-- ─── OPERATIONS ─────────────────────────────────── -->
        <li class="sidebar-heading">Operations</li>
        <?php if (Auth::hasPermission('logistics', 'view')): ?>
        <li class="sidebar-item">
            <a href="index.php?page=logistics" class="sidebar-link <?= $activePage === 'logistics' ? 'active' : '' ?>">
                <i class="fa-solid fa-truck-ramp-box"></i>
                <span>Logistics & Inventory</span>
            </a>
        </li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('logbook', 'view')): ?>
        <li class="sidebar-item">
            <a href="index.php?page=logbook" class="sidebar-link <?= $activePage === 'logbook' ? 'active' : '' ?>">
                <i class="fa-solid fa-book"></i>
                <span>Activity Logbook</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- ─── APPROVALS ──────────────────────────────────── -->
        <?php if (Auth::hasPermission('leave','approve') || Auth::hasPermission('expense','approve') || Auth::hasPermission('purchase','approve')): ?>
        <li class="sidebar-heading">Approvals</li>
        <li class="sidebar-item">
            <a href="index.php?page=approvals" class="sidebar-link <?= $activePage === 'approvals' ? 'active' : '' ?>">
                <i class="fa-solid fa-check-double"></i>
                <span>Approval Center</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- ─── CORPORATE DROPDOWN ─────────────────────────── -->
        <?php
        $showCorp = Auth::hasPermission('tenders','view') || Auth::hasPermission('tender_board','view') || Auth::hasPermission('documents','view')
                 || Auth::hasPermission('letters','view');
        $isCorpActive = in_array($activePage, ['tenders', 'tender_board', 'documents', 'inquiries', 'letters']);
        if ($showCorp):
        ?>
        <li class="sidebar-heading">Corporate Management</li>
        <li class="sidebar-item">
            <a onclick="toggleSidebarGroup('corpSubmenu', this, event)" class="sidebar-link sidebar-dropdown-toggle <?= $isCorpActive ? 'active expanded' : '' ?>">
                <div style="display:flex;align-items:center;gap:12px;">
                    <i class="fa-solid fa-building-shield"></i>
                    <span>Corporate Operations</span>
                </div>
                <i class="fa-solid fa-chevron-down toggle-icon"></i>
            </a>
            <ul class="sidebar-submenu" id="corpSubmenu" style="display:<?= $isCorpActive ? 'block' : 'none' ?>;">
                <?php if (Auth::hasPermission('tender_board','view') || Auth::hasPermission('tenders','view')): ?>
                <li class="sidebar-item">
                    <a href="<?= Auth::hasPermission('tender_board','view') ? 'index.php?page=tender_board' : 'index.php?page=tender_board&amp;section=kpi' ?>" class="sidebar-link <?= in_array($activePage, ['tenders', 'tender_board'], true) ? 'active' : '' ?>">
                        <i class="fa-solid fa-briefcase"></i>
                        <span>Tender Management</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (Auth::hasPermission('documents','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=documents" class="sidebar-link <?= $activePage === 'documents' ? 'active' : '' ?>">
                        <i class="fa-solid fa-folder-open"></i>
                        <span>Document Center</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (Auth::hasPermission('letters','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=inquiries" class="sidebar-link <?= ($activePage === 'inquiries' || $activePage === 'letters') ? 'active' : '' ?>">
                        <i class="fa-solid fa-inbox"></i>
                        <span>Inquiries & Letters</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- ─── ORGANIZATION DROPDOWN ──────────────────────── -->
        <?php
        $showOrg = Auth::hasPermission('departments','view')
            || Auth::hasPermission('branches','view')
            || Auth::hasPermission('positions','view')
            || Auth::hasPermission('org_chart','view');
        $isOrgActive = in_array($activePage, ['organization', 'org_chart'], true);
        if ($showOrg):
        ?>
        <li class="sidebar-heading">Organization</li>
        <li class="sidebar-item">
            <a onclick="toggleSidebarGroup('orgSubmenu', this, event)" class="sidebar-link sidebar-dropdown-toggle <?= $isOrgActive ? 'active expanded' : '' ?>">
                <div style="display:flex;align-items:center;gap:12px;">
                    <i class="fa-solid fa-sitemap"></i>
                    <span>Organization Hub</span>
                </div>
                <i class="fa-solid fa-chevron-down toggle-icon"></i>
            </a>
            <ul class="sidebar-submenu" id="orgSubmenu" style="display:<?= $isOrgActive ? 'block' : 'none' ?>;">
                <?php if (Auth::hasPermission('departments','view') || Auth::hasPermission('branches','view') || Auth::hasPermission('positions','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=organization" class="sidebar-link <?= $activePage === 'organization' ? 'active' : '' ?>">
                        <i class="fa-solid fa-network-wired"></i>
                        <span>Departments & Hierarchy</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (Auth::hasPermission('org_chart','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=org_chart" class="sidebar-link <?= $activePage === 'org_chart' ? 'active' : '' ?>">
                        <i class="fa-solid fa-sitemap"></i>
                        <span>Organization Chart</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- ─── SYSTEM DROPDOWN ────────────────────────────── -->
        <?php
        $isSystemActive = in_array($activePage, ['audit_logs', 'system_health', 'roles', 'reports']);
        $showSystem = Auth::hasPermission('roles','view') || Auth::hasPermission('audit_logs','view') || Auth::hasPermission('system','view') || Auth::isSuperAdmin();
        if ($showSystem):
        ?>
        <li class="sidebar-heading">System</li>

        <?php if (Auth::isSuperAdmin() || Auth::hasPermission('system', 'view')): ?>
        <li class="sidebar-item">
            <a href="index.php?page=reports" class="sidebar-link <?= $activePage === 'reports' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Executive Reports</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="sidebar-item">
            <a onclick="toggleSidebarGroup('systemSubmenu', this, event)" class="sidebar-link sidebar-dropdown-toggle <?= $isSystemActive && $activePage !== 'reports' ? 'active expanded' : '' ?>">
                <div style="display:flex;align-items:center;gap:12px;">
                    <i class="fa-solid fa-gears"></i>
                    <span>System Administration</span>
                </div>
                <i class="fa-solid fa-chevron-down toggle-icon"></i>
            </a>
            <ul class="sidebar-submenu" id="systemSubmenu" style="display:<?= ($isSystemActive && $activePage !== 'reports') ? 'block' : 'none' ?>;">
                <?php if (Auth::hasPermission('roles','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=roles" class="sidebar-link <?= $activePage === 'roles' ? 'active' : '' ?>">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Roles & Permissions</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (Auth::hasPermission('audit_logs','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=audit_logs" class="sidebar-link <?= $activePage === 'audit_logs' ? 'active' : '' ?>">
                        <i class="fa-solid fa-clipboard-list"></i>
                        <span>Audit Log</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (Auth::hasPermission('system','view')): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=system_health" class="sidebar-link <?= $activePage === 'system_health' ? 'active' : '' ?>">
                        <i class="fa-solid fa-server"></i>
                        <span>System Health & Backup</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- ─── PERSONAL & SETTINGS ─────────────────────────── -->
        <li class="sidebar-heading">Personal & Settings</li>
        <li class="sidebar-item">
            <a href="index.php?page=profile" class="sidebar-link <?= $activePage === 'profile' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-shield"></i>
                <span>My Profile</span>
            </a>
        </li>

        <!-- ─── HELP ────────────────────────────────────────── -->
        <li class="sidebar-heading">Help & Guide</li>
        <li class="sidebar-item">
            <a href="index.php?page=manual" class="sidebar-link <?= $activePage === 'manual' ? 'active' : '' ?>">
                <i class="fa-solid fa-book-open-reader"></i>
                <span>User Manual & Guide</span>
            </a>
        </li>
    </ul>

    <!-- ─── User Footer Card ──────────────────────────────── -->
    <?php if ($currentUser): ?>
    <div class="sidebar-footer">
        <a href="index.php?page=profile" class="user-card" style="text-decoration:none; cursor:pointer;" title="Click to open Profile & Push Settings">
            <div class="user-avatar">
                <?= strtoupper(substr($currentUser['name'], 0, 2)) ?>
            </div>
            <div class="user-info">
                <div class="user-name" title="<?= htmlspecialchars($currentUser['name']) ?>">
                    <?= htmlspecialchars($currentUser['name']) ?>
                </div>
                <div class="user-role">
                    <?php
                    $roleColors = [
                        'super_admin' => '#7C3AED', 'admin' => '#1D4ED8', 'hr' => '#0891B2',
                        'manager' => '#059669', 'dept_head' => '#D97706', 'finance' => '#BE185D',
                        'auditor' => '#475569', 'staff' => '#64748B',
                    ];
                    $rc = $roleColors[$currentUser['role']] ?? '#64748B';
                    ?>
                    <span style="background:<?= $rc ?>22;color:<?= $rc ?>;padding:2px 8px;border-radius:50px;font-size:9px;font-weight:700;text-transform:uppercase;">
                        <?= htmlspecialchars(Auth::roleLabel($currentUser['role'])) ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</aside>

<script>
function toggleSidebarGroup(submenuId, toggleElem, e) {
    if (e) e.preventDefault();
    const submenu = document.getElementById(submenuId);
    if (!submenu) return;
    if (submenu.style.display === 'none' || !submenu.style.display) {
        submenu.style.display = 'block';
        if (toggleElem) toggleElem.classList.add('expanded');
    } else {
        submenu.style.display = 'none';
        if (toggleElem) toggleElem.classList.remove('expanded');
    }
}
</script>
