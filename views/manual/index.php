<?php
/** Practical, role-aware end-user guide for the TBBA portal. */
require_once __DIR__ . '/../../core/Auth.php';
Auth::requireLogin();

$currentUser = Auth::user();
$pageTitle = 'User Manual & Guide';
$roleLabel = Auth::roleLabel($currentUser['role'] ?? 'staff');

$canAttendance = Auth::hasPermission('attendance', 'view');
$canTender = Auth::hasPermission('tenders', 'view') || Auth::hasPermission('tender_board', 'view');
$canRequests = Auth::hasPermission('leave', 'view') || Auth::hasPermission('expense', 'view') || Auth::hasPermission('purchase', 'view');
$canCompanyTools = Auth::hasPermission('announcements', 'view') || Auth::hasPermission('calendar', 'view')
    || Auth::hasPermission('documents', 'view') || Auth::hasPermission('letters', 'view')
    || Auth::hasPermission('sales', 'view') || Auth::hasPermission('purchases', 'view')
    || Auth::hasPermission('departments', 'view') || Auth::hasPermission('org_chart', 'view');
$canAdminGuide = Auth::isAdmin() || Auth::hasPermission('roles', 'view') || Auth::hasPermission('staff', 'create')
    || Auth::hasPermission('leave', 'approve') || Auth::hasPermission('expense', 'approve')
    || Auth::hasPermission('purchase', 'approve') || Auth::hasPermission('system', 'view');

$quickLinks = [
    ['Dashboard', 'dashboard', 'fa-chart-line', true],
    ['GPS Attendance', 'attendance', 'fa-location-dot', $canAttendance],
    ['Tender Management', Auth::hasPermission('tender_board', 'view') ? 'tender_board' : 'tenders', 'fa-briefcase', $canTender],
    ['Leave', 'leave', 'fa-calendar-minus', Auth::hasPermission('leave', 'view')],
    ['Expense Claims', 'expense', 'fa-receipt', Auth::hasPermission('expense', 'view')],
    ['My Profile', 'profile', 'fa-user-shield', true],
];

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<style>
.manual-page{padding:24px;max-width:1500px;margin:0 auto}.manual-hero{display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap;padding:30px;border-radius:20px;background:linear-gradient(135deg,#0F172A,#1E3A8A);color:#fff;box-shadow:0 12px 28px rgba(15,23,42,.2)}
.manual-eyebrow{display:inline-flex;align-items:center;gap:7px;padding:5px 11px;border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(255,255,255,.1);color:#7DD3FC;font-size:10px;font-weight:800;letter-spacing:.6px;text-transform:uppercase}.manual-hero h1{margin:12px 0 7px;font-size:27px;color:#fff}.manual-hero p{margin:0;max-width:740px;color:#CBD5E1;font-size:13px;line-height:1.6}.manual-role{min-width:190px;padding:16px 18px;border:1px solid rgba(255,255,255,.2);border-radius:14px;background:rgba(255,255,255,.09)}.manual-role small{display:block;color:#93C5FD;font-size:10px;font-weight:800;text-transform:uppercase}.manual-role strong{display:block;margin-top:5px;color:#fff;font-size:14px}
.manual-quick{display:flex;gap:9px;flex-wrap:wrap;margin:18px 0}.manual-quick a{display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-card);color:var(--text-dark);font-size:12px;font-weight:700;text-decoration:none}.manual-quick a:hover{border-color:#93C5FD;color:#1D4ED8}.manual-tabs{display:flex;gap:8px;overflow-x:auto;padding-bottom:12px;margin-bottom:20px;border-bottom:1px solid var(--border-color)}.manual-tab{display:inline-flex;align-items:center;gap:7px;white-space:nowrap;padding:9px 13px;border:1px solid var(--border-color);border-radius:999px;background:var(--bg-card);color:var(--text-muted);font:inherit;font-size:12px;font-weight:800;cursor:pointer}.manual-tab.active{border-color:#2563EB;background:#2563EB;color:#fff;box-shadow:0 4px 10px rgba(37,99,235,.2)}
.manual-section{display:none}.manual-section.active{display:block}.manual-section-head{display:flex;justify-content:space-between;align-items:end;gap:16px;flex-wrap:wrap;margin-bottom:14px}.manual-section-head h2{margin:0;color:var(--text-dark);font-size:21px}.manual-section-head p{margin:4px 0 0;color:var(--text-muted);font-size:12px}.manual-search{width:min(300px,100%);padding:9px 12px;border:1px solid var(--border-color);border-radius:9px;background:var(--bg-card);color:var(--text-dark);font:inherit;font-size:12px}
.manual-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.manual-card{padding:20px;border:1px solid var(--border-color);border-radius:15px;background:var(--bg-card);box-shadow:0 3px 10px rgba(15,23,42,.04)}.manual-card.full{grid-column:1/-1}.manual-card h3{display:flex;align-items:center;gap:9px;margin:0 0 12px;color:var(--text-dark);font-size:15px}.manual-card h3 i{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:#EFF6FF;color:#2563EB}.manual-card p,.manual-card li{color:var(--text-muted);font-size:13px;line-height:1.65}.manual-card ol,.manual-card ul{margin:0;padding-left:20px}.manual-card li+li{margin-top:6px}.manual-card strong{color:var(--text-dark)}.manual-card code{padding:2px 6px;border-radius:5px;background:var(--bg-primary);color:#1D4ED8;font-family:inherit;font-weight:700}
.manual-callout{grid-column:1/-1;display:flex;gap:11px;padding:14px 16px;border:1px solid #BFDBFE;border-radius:12px;background:#EFF6FF;color:#1E3A8A;font-size:12px;line-height:1.6}.manual-callout.warning{border-color:#FDE68A;background:#FFFBEB;color:#92400E}.manual-statuses{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.manual-status{padding:5px 9px;border-radius:999px;background:var(--bg-primary);border:1px solid var(--border-color);color:var(--text-muted);font-size:11px;font-weight:700}.manual-action{display:inline-flex;align-items:center;gap:6px;margin-top:12px;color:#2563EB;font-size:12px;font-weight:800;text-decoration:none}.manual-no-results{display:none;grid-column:1/-1;padding:35px;text-align:center;color:var(--text-muted)}
@media(max-width:850px){.manual-grid{grid-template-columns:1fr}.manual-card.full{grid-column:auto}}@media(max-width:640px){.manual-page{padding:14px}.manual-hero{padding:22px}.manual-hero h1{font-size:23px}.manual-role{width:100%}}
</style>

<div class="page-content manual-page">
    <section class="manual-hero">
        <div>
            <span class="manual-eyebrow"><i class="fa-solid fa-book-open"></i> Practical User Guide</span>
            <h1>How to Use the TBBA System</h1>
            <p>Step-by-step instructions for everyday work. This guide follows the actual menus, buttons, statuses and access controls available in the system.</p>
        </div>
        <div class="manual-role">
            <small>Your current access</small>
            <strong><i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($roleLabel) ?></strong>
            <span style="display:block;margin-top:5px;color:#CBD5E1;font-size:10px;">Guide viewed <?= date('d M Y') ?></span>
        </div>
    </section>

    <nav class="manual-quick" aria-label="Quick links">
        <?php foreach ($quickLinks as [$label, $page, $icon, $visible]): if (!$visible) continue; ?>
        <a href="index.php?page=<?= $page ?>"><i class="fa-solid <?= $icon ?>"></i><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <nav class="manual-tabs" aria-label="Manual topics">
        <button class="manual-tab active" data-target="manual-start" onclick="openManualSection(this)"><i class="fa-solid fa-play"></i> Start Here</button>
        <?php if ($canAttendance): ?><button class="manual-tab" data-target="manual-attendance" onclick="openManualSection(this)"><i class="fa-solid fa-location-dot"></i> Attendance</button><?php endif; ?>
        <?php if ($canTender): ?><button class="manual-tab" data-target="manual-tender" onclick="openManualSection(this)"><i class="fa-solid fa-briefcase"></i> Tenders</button><?php endif; ?>
        <?php if ($canRequests): ?><button class="manual-tab" data-target="manual-requests" onclick="openManualSection(this)"><i class="fa-solid fa-file-circle-check"></i> Requests & Claims</button><?php endif; ?>
        <?php if ($canCompanyTools): ?><button class="manual-tab" data-target="manual-tools" onclick="openManualSection(this)"><i class="fa-solid fa-building"></i> Company Tools</button><?php endif; ?>
        <?php if ($canAdminGuide): ?><button class="manual-tab" data-target="manual-admin" onclick="openManualSection(this)"><i class="fa-solid fa-user-gear"></i> Admin Guide</button><?php endif; ?>
        <button class="manual-tab" data-target="manual-help" onclick="openManualSection(this)"><i class="fa-solid fa-circle-question"></i> Profile & Help</button>
    </nav>

    <section class="manual-section active" id="manual-start">
        <div class="manual-section-head"><div><h2>Start Here</h2><p>The basics every user should know before using the portal.</p></div><input class="manual-search" type="search" placeholder="Search this topic..." oninput="searchManualCards(this)"></div>
        <div class="manual-grid">
            <article class="manual-card"><h3><i class="fa-solid fa-right-to-bracket"></i>1. Sign in safely</h3><ol><li>Open the official TBBA system address.</li><li>Enter your registered company email and password, or use Google sign-in if enabled.</li><li>Never share your password or leave the system open on a shared device.</li><li>Use <strong>Log Out</strong> when you finish.</li></ol></article>
            <article class="manual-card"><h3><i class="fa-solid fa-bars"></i>2. Use the main layout</h3><ul><li>The <strong>sidebar</strong> contains the modules you can access.</li><li>The <strong>top bar</strong> shows GPS status, current time, notifications and profile controls.</li><li>On a phone, open the menu button to access the sidebar.</li><li>Use the Dashboard for a quick overview before starting work.</li></ul></article>
            <article class="manual-card"><h3><i class="fa-solid fa-list-check"></i>Suggested daily routine</h3><ol><li>Check <strong>Announcements</strong> and notifications.</li><li>Record <strong>Clock In</strong> when starting work.</li><li>Review deadlines, requests and assigned tender work.</li><li>Record <strong>Clock Out</strong> at the end of the day.</li></ol></article>
            <article class="manual-card"><h3><i class="fa-solid fa-shield-halved"></i>Menus and permissions</h3><p>Menus are displayed according to your role and individual access. If a colleague sees a menu that you do not see, it does not mean your account is broken.</p><p>Ask your supervisor or system administrator if your job requires additional access.</p></article>
            <div class="manual-callout"><i class="fa-solid fa-circle-info"></i><div><strong>Save confirmation:</strong> After submitting a form, wait for the success message before closing the page. Avoid clicking a save button repeatedly.</div></div>
            <div class="manual-no-results">No matching instructions in this topic.</div>
        </div>
    </section>

    <?php if ($canAttendance): ?>
    <section class="manual-section" id="manual-attendance">
        <div class="manual-section-head"><div><h2>GPS Attendance</h2><p>Correct procedure for Clock In, Clock Out and out-of-office verification.</p></div><input class="manual-search" type="search" placeholder="Search this topic..." oninput="searchManualCards(this)"></div>
        <div class="manual-grid">
            <article class="manual-card"><h3><i class="fa-solid fa-location-crosshairs"></i>Before recording attendance</h3><ul><li>Enable <strong>Location</strong> on your device.</li><li>Allow the browser to use precise location when prompted.</li><li>Wait until the GPS indicator is active.</li><li>Use your own device and account.</li></ul></article>
            <article class="manual-card"><h3><i class="fa-solid fa-clock"></i>Clock In and Clock Out</h3><ol><li>Open <strong>GPS Attendance</strong>.</li><li>Tap <strong>CLOCK IN</strong> when work starts.</li><li>Confirm the recorded time shown on the page.</li><li>At the end of work, return and tap <strong>CLOCK OUT</strong>.</li></ol><a class="manual-action" href="index.php?page=attendance">Open GPS Attendance <i class="fa-solid fa-arrow-right"></i></a></article>
            <article class="manual-card"><h3><i class="fa-solid fa-person-walking-arrow-right"></i>Outside the office area</h3><p>The assigned office or branch geofence is <strong>100 metres</strong>. If you are outside it, the system requests a work reason and verification photo.</p><ol><li>Enter a specific reason, such as a client meeting or site visit.</li><li>Take or attach the requested verification image.</li><li>Submit and check that the remote attendance is recorded.</li></ol></article>
            <article class="manual-card"><h3><i class="fa-solid fa-screwdriver-wrench"></i>If GPS does not work</h3><ul><li>Turn Location off and on, then refresh the page.</li><li>Check browser site settings and allow location access.</li><li>Move near a window or outside for a stronger GPS signal.</li><li>Do not enter a manual location. Contact an administrator if the issue continues.</li></ul></article>
            <div class="manual-callout warning"><i class="fa-solid fa-triangle-exclamation"></i><div>Clock Out is available only after Clock In. The system also prevents duplicate Clock In or Clock Out records for the same day.</div></div>
            <div class="manual-no-results">No matching attendance instructions.</div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($canTender): ?>
    <section class="manual-section" id="manual-tender">
        <div class="manual-section-head"><div><h2>Tender Management</h2><p>Use Tender Board and KPI & Performance from one connected workspace.</p></div><input class="manual-search" type="search" placeholder="Search this topic..." oninput="searchManualCards(this)"></div>
        <div class="manual-grid">
            <?php if (Auth::hasPermission('tender_board', 'view')): ?>
            <article class="manual-card"><h3><i class="fa-solid fa-table-list"></i>Browse tender opportunities</h3><ul><li><strong>Open Tenders</strong> only shows available tenders you have not joined.</li><li>After joining, the tender moves to <strong>My Tenders</strong>.</li><li><strong>Closed</strong> stores past opportunities.</li><li>Search using the QT number or tender title.</li></ul><a class="manual-action" href="index.php?page=tender_board">Open Tender Management <i class="fa-solid fa-arrow-right"></i></a></article>
            <article class="manual-card"><h3><i class="fa-solid fa-user-plus"></i>Join, price and submit a tender</h3><ol><li>Find an unassigned tender and check its closing date.</li><li>Click <strong>Join</strong>. Each tender can be assigned to one staff member only.</li><li>The tender moves to <strong>My Tenders</strong> with an <strong>In Progress</strong> status, and a KPI record is created under your name.</li><li>Use <strong>Add Pricing</strong> or <strong>Edit Pricing</strong> to maintain the indicative price, cost, selling price and supporting file.</li><li>Click <strong>Submit</strong> when the pricing is complete. Pricing is locked after submission.</li><li>Open <strong>KPI &amp; Performance</strong> and select <strong>Monthly Report</strong> to generate your personal report with post-mortem and SWOT details.</li></ol></article>
            <article class="manual-card"><h3><i class="fa-solid fa-user-minus"></i>Leave a tender</h3><p>Click <strong>Leave</strong> and confirm only when you no longer plan to participate.</p><p>The linked Tender KPI record will also be removed. This prevents a cancelled participation from remaining in your monthly KPI.</p></article>
            <?php if (Auth::hasPermission('tender_board', 'create')): ?><article class="manual-card"><h3><i class="fa-solid fa-plus"></i>Post a new opportunity</h3><ol><li>Click <strong>Add Opportunity</strong>.</li><li>Enter the unique QT number and full title.</li><li>Select the closing date.</li><li>Save and verify it appears under Open Tenders.</li></ol><p>Only assigned users will see the add/edit controls.</p></article><?php endif; ?>
            <?php endif; ?>
            <?php if (Auth::hasPermission('tenders', 'view')): ?>
            <article class="manual-card"><h3><i class="fa-solid fa-chart-line"></i>Manage your Tender KPI</h3><ul><li>Open the <strong>KPI &amp; Performance</strong> tab.</li><li>The monthly target is <strong>4 tenders</strong>.</li><li>Use the month dropdown to review different KPI periods.</li><li>A <strong>Value Pending</strong> item must be completed from Tender Board.</li></ul><a class="manual-action" href="index.php?page=tender_board&amp;section=kpi">Open KPI &amp; Performance <i class="fa-solid fa-arrow-right"></i></a></article>
            <article class="manual-card"><h3><i class="fa-solid fa-arrows-rotate"></i>Tender status and post mortem</h3><p>Tender status follows a fixed workflow and cannot be selected manually:</p><div class="manual-statuses"><span class="manual-status">In Progress</span><span class="manual-status">Submitted</span><span class="manual-status">Won or Lost</span></div><p>After submission, use <strong>Won</strong> or <strong>Lost</strong> to record the final result. Final results cannot be changed. Then open <strong>KPI &amp; Performance</strong> and use <strong>Post Mortem</strong> to record the summary, key factors and SWOT analysis.</p></article>
            <?php endif; ?>
            <div class="manual-callout"><i class="fa-solid fa-link"></i><div>Tender Board and KPI &amp; Performance are two sections of Tender Management. Board title, QT number and closing-date changes remain synchronized with linked KPI records.</div></div>
            <div class="manual-no-results">No matching tender instructions.</div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($canRequests): ?>
    <section class="manual-section" id="manual-requests">
        <div class="manual-section-head"><div><h2>Requests, Claims & Approvals</h2><p>Submit complete information so approvers can make a decision without delays.</p></div><input class="manual-search" type="search" placeholder="Search this topic..." oninput="searchManualCards(this)"></div>
        <div class="manual-grid">
            <?php if (Auth::hasPermission('leave', 'view')): ?><article class="manual-card"><h3><i class="fa-solid fa-calendar-minus"></i>Leave & Permission</h3><ol><li>Open <strong>Leave & Permission</strong> and click the application button.</li><li>Select the leave type and dates.</li><li>Enter a clear reason.</li><li>Attach proof or GPS details when requested for that leave type.</li><li>Submit and monitor the status. A pending request can be cancelled if plans change.</li></ol><a class="manual-action" href="index.php?page=leave">Open Leave & Permission <i class="fa-solid fa-arrow-right"></i></a></article><?php endif; ?>
            <?php if (Auth::hasPermission('expense', 'view')): ?><article class="manual-card"><h3><i class="fa-solid fa-receipt"></i>Expense Claims</h3><ol><li>Create a claim title and select the expense date.</li><li>Add each item, category and exact amount.</li><li>Attach a receipt or supporting file when one is available; the attachment is optional.</li><li>Submit for approval and monitor <strong>Pending → Approved/Rejected → Paid</strong>.</li></ol><a class="manual-action" href="index.php?page=expense">Open Expense Claims <i class="fa-solid fa-arrow-right"></i></a></article><?php endif; ?>
            <?php if (Auth::hasPermission('purchase', 'view')): ?><article class="manual-card"><h3><i class="fa-solid fa-cart-plus"></i>Purchase Requests</h3><ol><li>Create a request title, required date and department.</li><li>Add every requested item with quantity and price.</li><li>Review the calculated total before submitting.</li><li>Monitor <strong>Pending, Approved, Rejected, Ordered</strong> and <strong>Received</strong>.</li></ol><a class="manual-action" href="index.php?page=purchase">Open Purchase Requests <i class="fa-solid fa-arrow-right"></i></a></article><?php endif; ?>
            <?php if (Auth::hasPermission('leave', 'approve') || Auth::hasPermission('expense', 'approve') || Auth::hasPermission('purchase', 'approve')): ?><article class="manual-card"><h3><i class="fa-solid fa-check-double"></i>For approvers</h3><ol><li>Open <strong>Approval Center</strong>.</li><li>Review the requester, dates, amount, items and evidence.</li><li>Approve or reject only after checking the full details.</li><li>Finance users can mark an approved expense as Paid; purchase requests continue to Ordered and Received.</li></ol><a class="manual-action" href="index.php?page=approvals">Open Approval Center <i class="fa-solid fa-arrow-right"></i></a></article><?php endif; ?>
            <div class="manual-callout warning"><i class="fa-solid fa-paperclip"></i><div>Upload readable evidence. A blurred receipt, incomplete item list or unclear reason may delay approval.</div></div>
            <div class="manual-no-results">No matching request or claim instructions.</div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($canCompanyTools): ?>
    <section class="manual-section" id="manual-tools">
        <div class="manual-section-head"><div><h2>Company Tools</h2><p>Shared information, operational records and organization references.</p></div><input class="manual-search" type="search" placeholder="Search this topic..." oninput="searchManualCards(this)"></div>
        <div class="manual-grid">
            <?php if (Auth::hasPermission('announcements', 'view') || Auth::hasPermission('calendar', 'view')): ?><article class="manual-card"><h3><i class="fa-solid fa-bullhorn"></i>Announcements & Calendar</h3><ul><li>Read pinned announcements first and open an item for full details.</li><li>Use Calendar & Events to review company activities by date.</li><li>Users with manage access can create, edit or remove announcements and events.</li></ul></article><?php endif; ?>
            <?php if (Auth::hasPermission('documents', 'view')): ?><article class="manual-card"><h3><i class="fa-solid fa-folder-open"></i>Document Center</h3><ul><li>Filter by category or search for the document title.</li><li>Use <strong>View</strong> to open a document and <strong>Download</strong> to save a copy.</li><li>Administrators can upload supported official files and remove obsolete copies.</li></ul><a class="manual-action" href="index.php?page=documents">Open Document Center <i class="fa-solid fa-arrow-right"></i></a></article><?php endif; ?>
            <?php if (Auth::hasPermission('letters', 'view')): ?><article class="manual-card"><h3><i class="fa-solid fa-inbox"></i>Inquiries & Letters</h3><ul><li>Review incoming inquiries and reference details.</li><li>Authorized users can add letters, record minutes and update the workflow status.</li><li>Use the system-generated reference number to avoid duplicate filing.</li></ul><a class="manual-action" href="index.php?page=inquiries">Open Inquiries & Letters <i class="fa-solid fa-arrow-right"></i></a></article><?php endif; ?>
            <?php if (Auth::hasPermission('sales', 'view') || Auth::hasPermission('purchases', 'view')): ?><article class="manual-card"><h3><i class="fa-solid fa-coins"></i>Finance Workflow</h3><ul><li>Use Sales or Purchases to enter the source record with its project reference and cost centre.</li><li>Use <strong>Finance Control Center</strong> to convert documents through their approved stages.</li><li>Record partial or full payments against invoices and bills; never overwrite the original total.</li><li>Print the generated receipt or payment voucher and reconcile it after verifying the bank transaction.</li><li>Review AR/AP aging, budgets, cash flow and project profitability each reporting period.</li></ul><?php if(Auth::hasPermission('finance_control','view')): ?><a class="manual-action" href="index.php?page=finance_control">Open Finance Control Center <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?></article><?php endif; ?>
            <?php if (Auth::hasPermission('departments', 'view') || Auth::hasPermission('org_chart', 'view')): ?><article class="manual-card"><h3><i class="fa-solid fa-sitemap"></i>Organization Hub</h3><ul><li>Departments & Hierarchy shows branches, departments, positions and reporting lines.</li><li>Organization Chart provides a visual reporting structure.</li><li>Hierarchy levels are calculated from the selected reporting position.</li></ul></article><?php endif; ?>
            <div class="manual-no-results">No matching company tool instructions.</div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($canAdminGuide): ?>
    <section class="manual-section" id="manual-admin">
        <div class="manual-section-head"><div><h2>Administrator Guide</h2><p>Controls shown here depend on the administrator's specific permissions.</p></div><input class="manual-search" type="search" placeholder="Search this topic..." oninput="searchManualCards(this)"></div>
        <div class="manual-grid">
            <article class="manual-card"><h3><i class="fa-solid fa-users-gear"></i>Staff accounts</h3><ul><li>Create an account using the correct employee ID, email, role, branch and position.</li><li>Selecting a position determines the staff member's department and designation.</li><li>Disable an account when access should stop temporarily; delete only when the record should be permanently removed.</li></ul></article>
            <article class="manual-card"><h3><i class="fa-solid fa-network-wired"></i>Positions and hierarchy</h3><ol><li>Create branches and departments before positions.</li><li>For each position, select <strong>Reports To Position</strong>.</li><li>The system derives its hierarchy level automatically.</li><li>Avoid circular reporting lines. Position changes also synchronize assigned staff details.</li></ol></article>
            <?php if (Auth::hasPermission('roles', 'view')): ?><article class="manual-card"><h3><i class="fa-solid fa-shield-halved"></i>Permissions</h3><ul><li><strong>Permission Matrix</strong> sets the default access for a role.</li><li><strong>User Overrides</strong> add or remove access for one specific user.</li><li>Grant only the actions needed for the person's job.</li><li>Confirm the sidebar and action buttons using the affected user's access after changing permissions.</li></ul><a class="manual-action" href="index.php?page=roles">Open Roles & Permissions <i class="fa-solid fa-arrow-right"></i></a></article><?php endif; ?>
            <article class="manual-card"><h3><i class="fa-solid fa-shield"></i>Audit, reports and system health</h3><ul><li>Use Audit Log to identify who performed a recorded action and when.</li><li>Executive Reports are for authorized management users.</li><li>After creating a backup, run its restore-readiness test to validate the manifest and database checksum.</li><li>Schedule <code>scripts/run_scheduled_backup.php</code> from the server scheduler and monitor the retention policy.</li><li>Do not share exported reports or backups with unauthorized users.</li></ul></article>
            <div class="manual-callout warning"><i class="fa-solid fa-triangle-exclamation"></i><div>Before deleting staff, departments, positions or business records, confirm the exact target and understand the related data that may be affected.</div></div>
            <div class="manual-no-results">No matching administrator instructions.</div>
        </div>
    </section>
    <?php endif; ?>

    <section class="manual-section" id="manual-help">
        <div class="manual-section-head"><div><h2>Profile, Mobile Setup & Help</h2><p>Account maintenance and quick solutions for common issues.</p></div><input class="manual-search" type="search" placeholder="Search this topic..." oninput="searchManualCards(this)"></div>
        <div class="manual-grid">
            <article class="manual-card"><h3><i class="fa-solid fa-user-pen"></i>Update your profile</h3><ol><li>Open <strong>My Profile</strong>.</li><li>Update your full name or profile picture if needed.</li><li>To change your password, provide the current password and use at least 6 characters with uppercase, lowercase, a number and a symbol.</li><li>Use the Security tab to enable two-factor authentication and review active devices.</li><li>Your position is controlled by the organization setup. Company email changes are restricted to Super Admin.</li></ol><a class="manual-action" href="index.php?page=profile">Open My Profile <i class="fa-solid fa-arrow-right"></i></a></article>
            <article class="manual-card"><h3><i class="fa-solid fa-mobile-screen-button"></i>Install on a phone</h3><ul><li><strong>Android/Chrome:</strong> open the browser menu and select Install App or Add to Home screen.</li><li><strong>iPhone/Safari:</strong> tap Share, select Add to Home Screen, then Add.</li><li>Always allow location when using GPS Attendance.</li></ul></article>
            <article class="manual-card"><h3><i class="fa-solid fa-bell"></i>Notifications</h3><ul><li>Use the bell icon to review new activity.</li><li>Open a notification to view its related item.</li><li>Mark items as read or clear old notifications when no longer needed.</li><li>Browser push notifications can be managed from My Profile when supported.</li></ul></article>
            <article class="manual-card"><h3><i class="fa-solid fa-life-ring"></i>When something goes wrong</h3><ol><li>Read the error message and check all required fields.</li><li>Refresh once and try the action again.</li><li>Check your connection, browser permissions and file type/size.</li><li>Take a screenshot showing the page, error and time.</li><li>Send it to the system administrator with the steps that caused the issue.</li></ol></article>
            <div class="manual-callout"><i class="fa-solid fa-lock"></i><div>Never send passwords, login cookies, backup files or confidential financial documents in a support screenshot.</div></div>
            <div class="manual-no-results">No matching help instructions.</div>
        </div>
    </section>
</div>

<script>
function openManualSection(button) {
    const target = button.dataset.target;
    document.querySelectorAll('.manual-tab').forEach(tab => tab.classList.toggle('active', tab === button));
    document.querySelectorAll('.manual-section').forEach(section => section.classList.toggle('active', section.id === target));
    const search = document.querySelector(`#${target} .manual-search`);
    if (search) { search.value = ''; searchManualCards(search); }
    history.replaceState(null, '', `#${target}`);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function searchManualCards(input) {
    const section = input.closest('.manual-section');
    const query = input.value.trim().toLowerCase();
    let visible = 0;
    section.querySelectorAll('.manual-card, .manual-callout').forEach(card => {
        const match = !query || card.textContent.toLowerCase().includes(query);
        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const empty = section.querySelector('.manual-no-results');
    if (empty) empty.style.display = visible ? 'none' : 'block';
}

document.addEventListener('DOMContentLoaded', () => {
    const target = location.hash.replace('#', '');
    const tab = target ? document.querySelector(`.manual-tab[data-target="${CSS.escape(target)}"]`) : null;
    if (tab) openManualSection(tab);
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
