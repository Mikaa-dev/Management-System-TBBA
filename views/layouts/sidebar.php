<?php
/**
 * Sidebar Navigation — TBBA ERP
 * Company: The Bridge Business Alliance (TBBA)
 * Updated: Cleaner ERP navigation structure
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';

$currentUser = Auth::user();
$activePage  = $_GET['page'] ?? 'dashboard';

/* Permission helpers */
$canAttendance    = Auth::hasPermission('attendance', 'view');
$canAnnouncements = Auth::hasPermission('announcements', 'view');
$canCalendar      = Auth::hasPermission('calendar', 'view');
$canLogbook       = Auth::hasPermission('logbook', 'view');

$canStaff         = Auth::hasPermission('staff', 'view');
$canLeave         = Auth::hasPermission('leave', 'view');

$canExpense       = Auth::hasPermission('expense', 'view');
$canPurchaseReq   = Auth::hasPermission('purchase', 'view');
$canSales         = Auth::hasPermission('sales', 'view');
$canPurchases     = Auth::hasPermission('purchases', 'view');
$canFinanceCtrl   = Auth::hasPermission('finance_control', 'view');

$canLogistics     = Auth::hasPermission('logistics', 'view');

$canTenders       = Auth::hasPermission('tenders', 'view');
$canTenderBoard   = Auth::hasPermission('tender_board', 'view');
$canDocuments     = Auth::hasPermission('documents', 'view');
$canLetters       = Auth::hasPermission('letters', 'view');

$canDepartments   = Auth::hasPermission('departments', 'view');
$canBranches      = Auth::hasPermission('branches', 'view');
$canPositions     = Auth::hasPermission('positions', 'view');
$canOrgChart      = Auth::hasPermission('org_chart', 'view');

$canRoles         = Auth::hasPermission('roles', 'view');
$canAuditLogs     = Auth::hasPermission('audit_logs', 'view');
$canSystem        = Auth::hasPermission('system', 'view');

$canApproveLeave    = Auth::hasPermission('leave', 'approve');
$canApproveExpense  = Auth::hasPermission('expense', 'approve');
$canApprovePurchase = Auth::hasPermission('purchase', 'approve');

$isSuperAdmin = Auth::isSuperAdmin();

/* Group visibility */
$showHR = $canStaff || $canLeave;

$showFinance = $canExpense
    || $canPurchaseReq
    || $canSales
    || $canPurchases
    || $canFinanceCtrl;

$showCorporate = $canTenders
    || $canTenderBoard
    || $canDocuments
    || $canLetters;

$showWorkspace = $showHR || $showFinance || $canLogistics || $showCorporate;

$showApprovals = $canApproveLeave || $canApproveExpense || $canApprovePurchase;

$showOrganization = $canDepartments || $canBranches || $canPositions || $canOrgChart;

$showExecutiveReports = $isSuperAdmin || $canSystem;

$showManagement = $showApprovals || $showExecutiveReports || $showOrganization;

$showAdministration = $isSuperAdmin || $canRoles || $canAuditLogs || $canSystem;

/* Active groups */
$isHrActive = in_array($activePage, ['staff', 'leave'], true);

$isFinanceActive = in_array(
    $activePage,
    ['expense', 'purchase', 'finance_control', 'finance_receipt', 'sales', 'purchases'],
    true
);

$isCorporateActive = in_array(
    $activePage,
    ['tenders', 'tender_board', 'documents', 'inquiries', 'letters'],
    true
);

$isOrganizationActive = in_array(
    $activePage,
    ['organization', 'org_chart'],
    true
);
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

        <!-- MAIN -->
        <li class="sidebar-heading">Main</li>

        <li class="sidebar-item">
            <a href="index.php?page=dashboard"
               class="sidebar-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <?php if ($canAttendance): ?>
        <li class="sidebar-item">
            <a href="index.php?page=attendance"
               class="sidebar-link <?= $activePage === 'attendance' ? 'active' : '' ?>">
                <i class="fa-solid fa-location-dot"></i>
                <span>GPS Attendance</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($canLogbook): ?>
        <li class="sidebar-item">
            <a href="index.php?page=logbook"
               class="sidebar-link <?= $activePage === 'logbook' ? 'active' : '' ?>">
                <i class="fa-solid fa-book"></i>
                <span>Activity Logbook</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($canAnnouncements): ?>
        <li class="sidebar-item">
            <a href="index.php?page=announcements"
               class="sidebar-link <?= $activePage === 'announcements' ? 'active' : '' ?>">
                <i class="fa-solid fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($canCalendar): ?>
        <li class="sidebar-item">
            <a href="index.php?page=calendar"
               class="sidebar-link <?= $activePage === 'calendar' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Calendar &amp; Events</span>
            </a>
        </li>
        <?php endif; ?>


        <!-- WORKSPACE -->
        <?php if ($showWorkspace): ?>
        <li class="sidebar-heading">Workspace</li>

        <?php if ($showHR): ?>
        <li class="sidebar-item">
            <a href="#"
               onclick="toggleSidebarGroup('hrSubmenu', this, event)"
               class="sidebar-link sidebar-dropdown-toggle <?= $isHrActive ? 'active expanded' : '' ?>"
               aria-expanded="<?= $isHrActive ? 'true' : 'false' ?>"
               aria-controls="hrSubmenu">
                <div style="display:flex;align-items:center;gap:12px;">
                    <i class="fa-solid fa-users-viewfinder"></i>
                    <span>HR &amp; People</span>
                </div>
                <i class="fa-solid fa-chevron-down toggle-icon"></i>
            </a>

            <ul class="sidebar-submenu"
                id="hrSubmenu"
                style="display:<?= $isHrActive ? 'block' : 'none' ?>;">

                <?php if ($canStaff): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=staff"
                       class="sidebar-link <?= $activePage === 'staff' ? 'active' : '' ?>">
                        <i class="fa-solid fa-users-gear"></i>
                        <span>Staff List</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($canLeave): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=leave"
                       class="sidebar-link <?= $activePage === 'leave' ? 'active' : '' ?>">
                        <i class="fa-solid fa-calendar-minus"></i>
                        <span>Leave &amp; Permission</span>
                    </a>
                </li>
                <?php endif; ?>

            </ul>
        </li>
        <?php endif; ?>


        <?php if ($showFinance): ?>
        <li class="sidebar-item">
            <a href="#"
               onclick="toggleSidebarGroup('financeSubmenu', this, event)"
               class="sidebar-link sidebar-dropdown-toggle <?= $isFinanceActive ? 'active expanded' : '' ?>"
               aria-expanded="<?= $isFinanceActive ? 'true' : 'false' ?>"
               aria-controls="financeSubmenu">
                <div style="display:flex;align-items:center;gap:12px;">
                    <i class="fa-solid fa-coins"></i>
                    <span>Finance</span>
                </div>
                <i class="fa-solid fa-chevron-down toggle-icon"></i>
            </a>

            <ul class="sidebar-submenu"
                id="financeSubmenu"
                style="display:<?= $isFinanceActive ? 'block' : 'none' ?>;">

                <?php if ($canExpense): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=expense"
                       class="sidebar-link <?= $activePage === 'expense' ? 'active' : '' ?>">
                        <i class="fa-solid fa-receipt"></i>
                        <span>Expense Claims</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($canPurchaseReq): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=purchase"
                       class="sidebar-link <?= $activePage === 'purchase' ? 'active' : '' ?>">
                        <i class="fa-solid fa-cart-plus"></i>
                        <span>Purchase Requests</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($canSales): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=sales"
                       class="sidebar-link <?= $activePage === 'sales' ? 'active' : '' ?>">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                        <span>Sales</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($canPurchases): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=purchases"
                       class="sidebar-link <?= $activePage === 'purchases' ? 'active' : '' ?>">
                        <i class="fa-solid fa-basket-shopping"></i>
                        <span>Purchases</span>
                    </a>
                </li>
                <?php endif; ?>

            </ul>
        </li>
        <?php endif; ?>


        <?php if ($canLogistics): ?>
        <li class="sidebar-item">
            <a href="index.php?page=logistics"
               class="sidebar-link <?= $activePage === 'logistics' ? 'active' : '' ?>">
                <i class="fa-solid fa-truck-ramp-box"></i>
                <span>Logistics &amp; Inventory</span>
            </a>
        </li>
        <?php endif; ?>


        <?php if ($showCorporate): ?>
        <li class="sidebar-item">
            <a href="#"
               onclick="toggleSidebarGroup('corpSubmenu', this, event)"
               class="sidebar-link sidebar-dropdown-toggle <?= $isCorporateActive ? 'active expanded' : '' ?>"
               aria-expanded="<?= $isCorporateActive ? 'true' : 'false' ?>"
               aria-controls="corpSubmenu">
                <div style="display:flex;align-items:center;gap:12px;">
                    <i class="fa-solid fa-building-shield"></i>
                    <span>Corporate</span>
                </div>
                <i class="fa-solid fa-chevron-down toggle-icon"></i>
            </a>

            <ul class="sidebar-submenu"
                id="corpSubmenu"
                style="display:<?= $isCorporateActive ? 'block' : 'none' ?>;">

                <?php if ($canTenderBoard || $canTenders): ?>
                <li class="sidebar-item">
                    <a href="<?= $canTenderBoard ? 'index.php?page=tender_board' : 'index.php?page=tender_board&amp;section=kpi' ?>"
                       class="sidebar-link <?= in_array($activePage, ['tenders', 'tender_board'], true) ? 'active' : '' ?>">
                        <i class="fa-solid fa-briefcase"></i>
                        <span>Tender Management</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($canDocuments): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=documents"
                       class="sidebar-link <?= $activePage === 'documents' ? 'active' : '' ?>">
                        <i class="fa-solid fa-folder-open"></i>
                        <span>Document Center</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($canLetters): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=inquiries"
                       class="sidebar-link <?= in_array($activePage, ['inquiries', 'letters'], true) ? 'active' : '' ?>">
                        <i class="fa-solid fa-inbox"></i>
                        <span>Inquiries &amp; Letters</span>
                    </a>
                </li>
                <?php endif; ?>

            </ul>
        </li>
        <?php endif; ?>

        <?php endif; ?>


        <!-- MANAGEMENT -->
        <?php if ($showManagement): ?>
        <li class="sidebar-heading">Management</li>

        <?php if ($showApprovals): ?>
        <li class="sidebar-item">
            <a href="index.php?page=approvals"
               class="sidebar-link <?= $activePage === 'approvals' ? 'active' : '' ?>">
                <i class="fa-solid fa-check-double"></i>
                <span>Approval Center</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($showExecutiveReports): ?>
        <li class="sidebar-item">
            <a href="index.php?page=reports"
               class="sidebar-link <?= $activePage === 'reports' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Executive Reports</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($showOrganization): ?>
        <li class="sidebar-item">
            <a href="#"
               onclick="toggleSidebarGroup('orgSubmenu', this, event)"
               class="sidebar-link sidebar-dropdown-toggle <?= $isOrganizationActive ? 'active expanded' : '' ?>"
               aria-expanded="<?= $isOrganizationActive ? 'true' : 'false' ?>"
               aria-controls="orgSubmenu">
                <div style="display:flex;align-items:center;gap:12px;">
                    <i class="fa-solid fa-sitemap"></i>
                    <span>Organization</span>
                </div>
                <i class="fa-solid fa-chevron-down toggle-icon"></i>
            </a>

            <ul class="sidebar-submenu"
                id="orgSubmenu"
                style="display:<?= $isOrganizationActive ? 'block' : 'none' ?>;">

                <?php if ($canDepartments || $canBranches || $canPositions): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=organization"
                       class="sidebar-link <?= $activePage === 'organization' ? 'active' : '' ?>">
                        <i class="fa-solid fa-network-wired"></i>
                        <span>Departments &amp; Hierarchy</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($canOrgChart): ?>
                <li class="sidebar-item">
                    <a href="index.php?page=org_chart"
                       class="sidebar-link <?= $activePage === 'org_chart' ? 'active' : '' ?>">
                        <i class="fa-solid fa-sitemap"></i>
                        <span>Organization Chart</span>
                    </a>
                </li>
                <?php endif; ?>

            </ul>
        </li>
        <?php endif; ?>

        <?php endif; ?>


        <!-- ADMINISTRATION -->
        <?php if ($showAdministration): ?>
        <li class="sidebar-heading">Administration</li>

        <?php if ($canRoles): ?>
        <li class="sidebar-item">
            <a href="index.php?page=roles"
               class="sidebar-link <?= $activePage === 'roles' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Roles &amp; Permissions</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($canAuditLogs): ?>
        <li class="sidebar-item">
            <a href="index.php?page=audit_logs"
               class="sidebar-link <?= $activePage === 'audit_logs' ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Audit Log</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($canSystem): ?>
        <li class="sidebar-item">
            <a href="index.php?page=system_health"
               class="sidebar-link <?= $activePage === 'system_health' ? 'active' : '' ?>">
                <i class="fa-solid fa-server"></i>
                <span>System Health &amp; Backup</span>
            </a>
        </li>
        <?php endif; ?>

        <?php endif; ?>


        <!-- ACCOUNT -->
        <li class="sidebar-heading">Account</li>

        <li class="sidebar-item">
            <a href="index.php?page=profile"
               class="sidebar-link <?= $activePage === 'profile' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-shield"></i>
                <span>My Profile</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="index.php?page=manual"
               class="sidebar-link <?= $activePage === 'manual' ? 'active' : '' ?>">
                <i class="fa-solid fa-book-open-reader"></i>
                <span>User Manual &amp; Guide</span>
            </a>
        </li>

    </ul>


    <!-- USER FOOTER CARD -->
    <?php if ($currentUser): ?>
    <div class="sidebar-footer">
        <a href="index.php?page=profile"
           class="user-card"
           style="text-decoration:none;cursor:pointer;"
           title="Open Profile & Push Settings">

            <div class="user-avatar">
                <?= strtoupper(substr((string)($currentUser['name'] ?? 'U'), 0, 2)) ?>
            </div>

            <div class="user-info">
                <div class="user-name"
                     title="<?= htmlspecialchars((string)($currentUser['name'] ?? '')) ?>">
                    <?= htmlspecialchars((string)($currentUser['name'] ?? 'User')) ?>
                </div>

                <div class="user-role">
                    <?php
                    $roleColors = [
                        'super_admin' => '#7C3AED',
                        'admin'       => '#1D4ED8',
                        'hr'          => '#0891B2',
                        'manager'     => '#059669',
                        'dept_head'   => '#D97706',
                        'finance'     => '#BE185D',
                        'auditor'     => '#475569',
                        'staff'       => '#64748B',
                    ];

                    $role = (string)($currentUser['role'] ?? 'staff');
                    $rc   = $roleColors[$role] ?? '#64748B';
                    ?>

                    <span style="
                        background:<?= $rc ?>22;
                        color:<?= $rc ?>;
                        padding:2px 8px;
                        border-radius:50px;
                        font-size:9px;
                        font-weight:700;
                        text-transform:uppercase;
                    ">
                        <?= htmlspecialchars(Auth::roleLabel($role)) ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

</aside>


<script>
function toggleSidebarGroup(submenuId, toggleElem, event) {
    if (event) {
        event.preventDefault();
    }

    const submenu = document.getElementById(submenuId);

    if (!submenu) {
        return;
    }

    const isOpen = submenu.style.display === 'block';

    submenu.style.display = isOpen ? 'none' : 'block';

    if (toggleElem) {
        toggleElem.classList.toggle('expanded', !isOpen);
        toggleElem.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
    }
}
</script>
