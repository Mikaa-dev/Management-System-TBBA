<?php
$tenderManagementSection = $tenderManagementSection ?? 'board';
$canViewTenderBoard = Auth::hasPermission('tender_board', 'view');
$canViewTenderKpi = Auth::hasPermission('tenders', 'view');
?>
<style>
.tm-workspace { margin-bottom:22px;padding:18px 20px;border:1px solid var(--border-color);border-radius:16px;background:var(--bg-card);box-shadow:0 5px 16px rgba(15,23,42,.05);display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap; }
.tm-workspace-title { display:flex;align-items:center;gap:12px;min-width:240px; }
.tm-workspace-icon { width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#DBEAFE,#E0E7FF);color:#2563EB;display:inline-flex;align-items:center;justify-content:center;font-size:18px;flex:0 0 auto; }
.tm-workspace-title h2 { margin:0 0 3px;color:var(--text-dark);font-size:18px;line-height:1.2; }
.tm-workspace-title p { margin:0;color:var(--text-muted);font-size:11px;line-height:1.4; }
.tm-workspace-tabs { display:flex;gap:7px;padding:5px;border-radius:12px;background:var(--bg-primary);border:1px solid var(--border-color);overflow-x:auto;max-width:100%; }
.tm-workspace-tab { display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:9px 14px;border-radius:9px;color:var(--text-muted);font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap;transition:.18s ease; }
.tm-workspace-tab:hover { color:#2563EB;background:var(--bg-card); }
.tm-workspace-tab.active { color:#FFF;background:#2563EB;box-shadow:0 4px 10px rgba(37,99,235,.24); }
@media (max-width:640px) { .tm-workspace{padding:15px}.tm-workspace-tabs{width:100%}.tm-workspace-tab{flex:1}.tm-workspace-title p{max-width:260px} }
</style>

<section class="tm-workspace" aria-label="Tender Management sections">
    <div class="tm-workspace-title">
        <span class="tm-workspace-icon"><i class="fa-solid fa-briefcase"></i></span>
        <div>
            <h2>Tender Management</h2>
            <p>Manage opportunities, pricing and monthly KPI performance in one workspace.</p>
        </div>
    </div>
    <nav class="tm-workspace-tabs" aria-label="Tender Management navigation">
        <?php if ($canViewTenderBoard): ?>
        <a class="tm-workspace-tab <?= $tenderManagementSection === 'board' ? 'active' : '' ?>" href="index.php?page=tender_board">
            <i class="fa-solid fa-table-list"></i> Tender Board
        </a>
        <?php endif; ?>
        <?php if ($canViewTenderKpi): ?>
        <a class="tm-workspace-tab <?= $tenderManagementSection === 'kpi' ? 'active' : '' ?>" href="index.php?page=tender_board&amp;section=kpi">
            <i class="fa-solid fa-chart-line"></i> KPI &amp; Performance
        </a>
        <?php endif; ?>
    </nav>
</section>
