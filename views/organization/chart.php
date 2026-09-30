<?php
/**
 * Organization Chart View — TBBA ERP
 * Upgraded: Organization Explorer
 *
 * Features:
 * - Live summary cards (Departments / Positions / Filled / Vacant)
 * - Search staff or position
 * - Department focus filter
 * - Hierarchy depth filter
 * - Fit / Collapse All / Expand All
 * - Fullscreen chart
 * - Click node -> detail drawer
 * - Print / Save PDF
 *
 * Backend endpoint remains:
 *   index.php?action=get_org_chart
 */

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<!-- d3-org-chart dependencies -->
<script src="https://d3js.org/d3.v7.min.js"
        integrity="sha384-CjloA8y00+1SDAUkjs099PVfnY2KmDC2BZnws9kh8D/lX1s46w6EPhpXdqMfjK6i"
        crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/d3-org-chart@3.1.1"
        integrity="sha384-j2Vlh7leSFYx1AI8mtBLXkmfX0U/6ItQrDKqzclCbTV0jlMFb10HxJR8iALXfXFI"
        crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/d3-flextree@2.1.2/build/d3-flextree.js"
        integrity="sha384-6pTgblH+kfP7e8kLkJxI96n+G6MCr28XHUtlXyr3cSjSyT/co6eOBwwCPAX8pBb5"
        crossorigin="anonymous"></script>

<style>
/* ============================================================
   ORGANIZATION EXPLORER — PAGE STYLES
============================================================ */

.org-page {
    padding: 24px;
}

.org-toolbar-card,
.org-chart-card,
.org-stat-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .035);
}

.org-toolbar-card {
    padding: 18px;
    margin-bottom: 16px;
    border-radius: 14px;
}

.org-toolbar-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
}

.org-title-wrap {
    min-width: 0;
}

.org-title-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 7px;
    padding: 4px 8px;
    border-radius: 999px;
    background: #EFF6FF;
    color: #2563EB;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.org-title-wrap h3 {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    color: #0F172A;
    font-size: 18px;
    font-weight: 800;
}

.org-title-wrap h3 i {
    color: #D97706;
}

.org-title-wrap p {
    margin: 5px 0 0;
    color: #64748B;
    font-size: 12px;
    line-height: 1.5;
}

.org-toolbar-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 7px;
    flex-wrap: wrap;
}

.org-control-btn {
    min-height: 35px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 8px 11px;
    border: 1px solid #CBD5E1;
    border-radius: 9px;
    background: #FFFFFF;
    color: #475569;
    font-size: 10px;
    font-weight: 750;
    cursor: pointer;
    transition: all .18s ease;
    text-decoration: none;
    white-space: nowrap;
}

.org-control-btn:hover:not(:disabled) {
    border-color: #93C5FD;
    background: #EFF6FF;
    color: #1D4ED8;
}

.org-control-btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.org-control-btn.primary {
    border-color: #BFDBFE;
    background: #EFF6FF;
    color: #1D4ED8;
}

.org-filter-row {
    display: grid;
    grid-template-columns: minmax(220px, 1.3fr) minmax(180px, .8fr) minmax(150px, .55fr);
    gap: 10px;
    margin-top: 16px;
    padding-top: 15px;
    border-top: 1px solid #F1F5F9;
}

.org-field {
    position: relative;
    min-width: 0;
}

.org-field i.field-icon {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    color: #94A3B8;
    font-size: 11px;
    pointer-events: none;
}

.org-field input,
.org-field select {
    width: 100%;
    min-height: 38px;
    box-sizing: border-box;
    padding: 8px 11px 8px 34px;
    border: 1px solid #CBD5E1;
    border-radius: 9px;
    background: #FFFFFF;
    color: #0F172A;
    font-family: inherit;
    font-size: 11px;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease;
}

.org-field select {
    cursor: pointer;
}

.org-field input:focus,
.org-field select:focus {
    border-color: #60A5FA;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .09);
}

.org-search-results {
    position: absolute;
    top: calc(100% + 5px);
    left: 0;
    right: 0;
    z-index: 50;
    display: none;
    max-height: 260px;
    overflow-y: auto;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    background: #FFFFFF;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .14);
}

.org-search-results.open {
    display: block;
}

.org-search-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px 10px;
    border: 0;
    border-bottom: 1px solid #F1F5F9;
    background: #FFFFFF;
    color: #334155;
    text-align: left;
    cursor: pointer;
}

.org-search-item:last-child {
    border-bottom: 0;
}

.org-search-item:hover {
    background: #F8FAFC;
}

.org-search-icon {
    width: 28px;
    height: 28px;
    flex: 0 0 28px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    background: #EFF6FF;
    color: #2563EB;
    font-size: 10px;
}

.org-search-copy {
    min-width: 0;
    flex: 1;
}

.org-search-main {
    overflow: hidden;
    color: #0F172A;
    font-size: 10.5px;
    font-weight: 750;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.org-search-sub {
    margin-top: 2px;
    overflow: hidden;
    color: #94A3B8;
    font-size: 9px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.org-search-empty {
    padding: 14px;
    color: #94A3B8;
    font-size: 10px;
    text-align: center;
}

.org-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 16px;
}

.org-stat-card {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 13px 14px;
    border-radius: 12px;
}

.org-stat-icon {
    width: 35px;
    height: 35px;
    flex: 0 0 35px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    font-size: 13px;
}

.org-stat-icon.blue   { background: #EFF6FF; color: #2563EB; }
.org-stat-icon.purple { background: #F5F3FF; color: #7C3AED; }
.org-stat-icon.green  { background: #ECFDF5; color: #059669; }
.org-stat-icon.gray   { background: #F1F5F9; color: #64748B; }

.org-stat-copy {
    min-width: 0;
}

.org-stat-value {
    color: #0F172A;
    font-size: 20px;
    font-weight: 850;
    line-height: 1;
}

.org-stat-label {
    margin-top: 4px;
    color: #94A3B8;
    font-size: 8.5px;
    font-weight: 800;
    letter-spacing: .055em;
    text-transform: uppercase;
    white-space: nowrap;
}

.org-chart-card {
    position: relative;
    overflow: hidden;
    border-radius: 14px;
}

.org-chart-header {
    min-height: 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid #E2E8F0;
    background: #FFFFFF;
}

.org-chart-context {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748B;
    font-size: 9.5px;
    font-weight: 700;
}

.org-chart-context strong {
    color: #0F172A;
    font-weight: 800;
}

.org-context-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    max-width: 240px;
    padding: 4px 7px;
    border-radius: 999px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    color: #475569;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.org-chart-hint {
    color: #94A3B8;
    font-size: 9px;
    white-space: nowrap;
}

.org-chart-hint i {
    color: #60A5FA;
}

.chart-container {
    height: 690px;
    width: 100%;
    background:
        radial-gradient(circle at 1px 1px, rgba(148,163,184,.18) 1px, transparent 0);
    background-size: 22px 22px;
    background-color: #F8FAFC;
}

.org-chart-status {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: #64748B;
    text-align: center;
}

.org-chart-status i {
    margin-bottom: 10px;
    font-size: 24px;
}

.org-chart-status.error {
    color: #B91C1C;
}

/* Drawer */
.org-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9990;
    background: rgba(15, 23, 42, .28);
    backdrop-filter: blur(1px);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: .22s ease;
}

.org-drawer-backdrop.open {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.org-detail-drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 9991;
    width: min(390px, 92vw);
    display: flex;
    flex-direction: column;
    background: #FFFFFF;
    border-left: 1px solid #E2E8F0;
    box-shadow: -14px 0 38px rgba(15, 23, 42, .16);
    transform: translateX(104%);
    transition: transform .26s cubic-bezier(.2,.8,.2,1);
}

.org-detail-drawer.open {
    transform: translateX(0);
}

.org-drawer-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 18px;
    border-bottom: 1px solid #E2E8F0;
    background: linear-gradient(135deg, #0F172A, #1E3A8A);
    color: #FFFFFF;
}

.org-drawer-eyebrow {
    color: #93C5FD;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.org-drawer-title {
    margin-top: 4px;
    font-size: 17px;
    font-weight: 800;
    line-height: 1.25;
}

.org-drawer-close {
    width: 31px;
    height: 31px;
    flex: 0 0 31px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(255,255,255,.14);
    border-radius: 9px;
    background: rgba(255,255,255,.08);
    color: #FFFFFF;
    cursor: pointer;
}

.org-drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 17px;
}

.org-drawer-section {
    margin-bottom: 16px;
}

.org-drawer-label {
    margin-bottom: 6px;
    color: #94A3B8;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.org-drawer-info {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.org-info-box {
    padding: 10px;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    background: #F8FAFC;
}

.org-info-box span {
    display: block;
    color: #94A3B8;
    font-size: 8px;
    font-weight: 750;
    text-transform: uppercase;
}

.org-info-box strong {
    display: block;
    margin-top: 4px;
    color: #0F172A;
    font-size: 10.5px;
    font-weight: 800;
    line-height: 1.35;
}

.org-employee-list {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.org-employee-card {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    background: #FFFFFF;
}

.org-employee-card img {
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    border-radius: 9px;
    background: #DBEAFE;
    border: 1px solid #BFDBFE;
    object-fit: cover;
}

.org-employee-name {
    color: #0F172A;
    font-size: 10.5px;
    font-weight: 800;
}

.org-empty-person {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 10px;
    border: 1px dashed #CBD5E1;
    border-radius: 10px;
    background: #F8FAFC;
    color: #64748B;
    font-size: 10px;
    font-style: italic;
}

.org-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 7px;
    border-radius: 999px;
    font-size: 8.5px;
    font-weight: 800;
    text-transform: uppercase;
}

.org-status-chip.filled {
    background: #ECFDF5;
    color: #047857;
}

.org-status-chip.vacant {
    background: #F1F5F9;
    color: #64748B;
}

.org-drawer-actions {
    display: flex;
    gap: 7px;
    padding: 12px 17px;
    border-top: 1px solid #E2E8F0;
    background: #F8FAFC;
}

/* Fullscreen */
.org-chart-card:fullscreen {
    width: 100vw;
    height: 100vh;
    border-radius: 0;
    background: #FFFFFF;
}

.org-chart-card:fullscreen .chart-container {
    height: calc(100vh - 49px);
}

/* Print */
@media print {
    .sidebar,
    .navbar,
    .org-toolbar-card,
    .org-stats,
    .org-chart-header,
    .org-drawer-backdrop,
    .org-detail-drawer {
        display: none !important;
    }

    .main-wrapper,
    .main-content,
    .page-content,
    .org-page {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    .org-chart-card {
        border: 0 !important;
        box-shadow: none !important;
    }

    .chart-container {
        height: 95vh !important;
        background: #FFFFFF !important;
    }
}

/* Responsive */
@media (max-width: 992px) {
    .org-filter-row {
        grid-template-columns: 1fr 1fr;
    }

    .org-field:first-child {
        grid-column: 1 / -1;
    }

    .org-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .chart-container {
        height: 620px;
    }
}

@media (max-width: 640px) {
    .org-page {
        padding: 14px 12px;
    }

    .org-toolbar-card {
        padding: 14px;
    }

    .org-toolbar-top {
        flex-direction: column;
        align-items: stretch;
    }

    .org-toolbar-actions {
        justify-content: flex-start;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 2px;
    }

    .org-control-btn {
        flex: 0 0 auto;
    }

    .org-filter-row {
        grid-template-columns: 1fr;
    }

    .org-field:first-child {
        grid-column: auto;
    }

    .org-stats {
        gap: 7px;
    }

    .org-stat-card {
        padding: 10px;
    }

    .org-stat-value {
        font-size: 17px;
    }

    .org-chart-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .org-chart-hint {
        white-space: normal;
    }

    .chart-container {
        height: 560px;
    }
}
</style>


<div class="page-content org-page">

    <!-- ========================================================
         TOOLBAR
    ========================================================= -->
    <div class="org-toolbar-card">

        <div class="org-toolbar-top">

            <div class="org-title-wrap">
                <div class="org-title-eyebrow">
                    <i class="fa-solid fa-network-wired"></i>
                    Organization Explorer
                </div>

                <h3>
                    <i class="fa-solid fa-sitemap"></i>
                    Organization Chart
                </h3>

                <p>
                    Explore the company hierarchy, reporting lines, positions, employees, and vacancies.
                </p>
            </div>

            <div class="org-toolbar-actions">

                <button id="fitChartButton"
                        class="org-control-btn primary"
                        type="button"
                        onclick="fitOrganizationChart()"
                        disabled>
                    <i class="fa-solid fa-expand"></i>
                    Fit
                </button>

                <button id="collapseChartButton"
                        class="org-control-btn"
                        type="button"
                        onclick="collapseOrganizationChart()"
                        disabled>
                    <i class="fa-solid fa-compress"></i>
                    Collapse
                </button>

                <button id="expandChartButton"
                        class="org-control-btn"
                        type="button"
                        onclick="expandOrganizationChart()"
                        disabled>
                    <i class="fa-solid fa-plus"></i>
                    Expand All
                </button>

                <button id="fullscreenChartButton"
                        class="org-control-btn"
                        type="button"
                        onclick="toggleOrganizationFullscreen()"
                        disabled>
                    <i class="fa-solid fa-maximize"></i>
                    Fullscreen
                </button>

                <button id="printChartButton"
                        class="org-control-btn"
                        type="button"
                        onclick="printOrganizationChart()"
                        disabled>
                    <i class="fa-solid fa-print"></i>
                    Print / PDF
                </button>

            </div>
        </div>


        <!-- FILTERS -->
        <div class="org-filter-row">

            <div class="org-field">
                <i class="fa-solid fa-magnifying-glass field-icon"></i>

                <input type="search"
                       id="orgSearchInput"
                       placeholder="Search staff or position..."
                       autocomplete="off"
                       aria-label="Search staff or position">

                <div id="orgSearchResults"
                     class="org-search-results"
                     role="listbox"
                     aria-label="Organization search results"></div>
            </div>

            <div class="org-field">
                <i class="fa-solid fa-building-user field-icon"></i>

                <select id="orgDepartmentFilter"
                        aria-label="Filter by department"
                        disabled>
                    <option value="">All Departments</option>
                </select>
            </div>

            <div class="org-field">
                <i class="fa-solid fa-layer-group field-icon"></i>

                <select id="orgDepthFilter"
                        aria-label="Hierarchy depth"
                        disabled>
                    <option value="">All Levels</option>
                    <option value="2">Up to Level 2</option>
                    <option value="3">Up to Level 3</option>
                    <option value="4">Up to Level 4</option>
                    <option value="5">Up to Level 5</option>
                </select>
            </div>

        </div>

    </div>


    <!-- ========================================================
         SUMMARY
    ========================================================= -->
    <div class="org-stats">

        <div class="org-stat-card">
            <div class="org-stat-icon blue">
                <i class="fa-solid fa-building-user"></i>
            </div>
            <div class="org-stat-copy">
                <div class="org-stat-value" id="orgStatDepartments">—</div>
                <div class="org-stat-label">Departments</div>
            </div>
        </div>

        <div class="org-stat-card">
            <div class="org-stat-icon purple">
                <i class="fa-solid fa-id-badge"></i>
            </div>
            <div class="org-stat-copy">
                <div class="org-stat-value" id="orgStatPositions">—</div>
                <div class="org-stat-label">Positions</div>
            </div>
        </div>

        <div class="org-stat-card">
            <div class="org-stat-icon green">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="org-stat-copy">
                <div class="org-stat-value" id="orgStatFilled">—</div>
                <div class="org-stat-label">Filled</div>
            </div>
        </div>

        <div class="org-stat-card">
            <div class="org-stat-icon gray">
                <i class="fa-solid fa-user-xmark"></i>
            </div>
            <div class="org-stat-copy">
                <div class="org-stat-value" id="orgStatVacant">—</div>
                <div class="org-stat-label">Vacant</div>
            </div>
        </div>

    </div>


    <!-- ========================================================
         CHART
    ========================================================= -->
    <div class="org-chart-card" id="organizationChartCard">

        <div class="org-chart-header">

            <div class="org-chart-context">
                <span class="org-context-pill">
                    <i class="fa-solid fa-building"></i>
                    <strong id="orgContextDepartment">All Company</strong>
                </span>

                <span class="org-context-pill">
                    <i class="fa-solid fa-layer-group"></i>
                    <strong id="orgContextDepth">All Levels</strong>
                </span>
            </div>

            <div class="org-chart-hint">
                <i class="fa-solid fa-computer-mouse"></i>
                Drag to move • Scroll to zoom • Click a position for details
            </div>

        </div>

        <div class="chart-container">
            <div id="chartStatus" class="org-chart-status">
                <div>
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <br>
                    Loading organization chart...
                </div>
            </div>
        </div>

    </div>

</div>


<!-- ============================================================
     NODE DETAIL DRAWER
============================================================= -->
<div class="org-drawer-backdrop"
     id="orgDrawerBackdrop"
     onclick="closeOrgDetailDrawer()"
     aria-hidden="true"></div>

<aside class="org-detail-drawer"
       id="orgDetailDrawer"
       aria-hidden="true"
       aria-label="Organization position details">

    <div class="org-drawer-header">

        <div>
            <div class="org-drawer-eyebrow">Position Details</div>
            <div class="org-drawer-title" id="orgDrawerTitle">Position</div>
        </div>

        <button type="button"
                class="org-drawer-close"
                onclick="closeOrgDetailDrawer()"
                aria-label="Close details">
            <i class="fa-solid fa-xmark"></i>
        </button>

    </div>

    <div class="org-drawer-body" id="orgDrawerBody"></div>

    <div class="org-drawer-actions">
        <button type="button"
                class="org-control-btn"
                style="flex:1;"
                onclick="closeOrgDetailDrawer()">
            Close
        </button>

        <a href="index.php?page=organization"
           class="org-control-btn primary"
           style="flex:1;">
            <i class="fa-solid fa-pen-to-square"></i>
            Structure
        </a>
    </div>

</aside>


<script>
/* ==============================================================
   ORGANIZATION EXPLORER
============================================================== */

let chart = null;
let organizationData = [];
let renderedOrganizationData = [];

const DEFAULT_AVATAR = 'assets/images/default-avatar.svg';


/* --------------------------------------------------------------
   SAFE TEXT
-------------------------------------------------------------- */
function escapeChartText(value) {
    return String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
    }[character]));
}


/* --------------------------------------------------------------
   CONTROLS
-------------------------------------------------------------- */
function setChartControlsEnabled(enabled) {
    [
        'fitChartButton',
        'collapseChartButton',
        'expandChartButton',
        'fullscreenChartButton',
        'printChartButton'
    ].forEach(id => {
        const element = document.getElementById(id);
        if (element) element.disabled = !enabled;
    });

    const dept = document.getElementById('orgDepartmentFilter');
    const depth = document.getElementById('orgDepthFilter');

    if (dept) dept.disabled = !enabled;
    if (depth) depth.disabled = !enabled;
}


function showChartMessage(message, isError = false) {
    const container = document.querySelector('.chart-container');

    if (!container) return;

    container.innerHTML = `
        <div class="org-chart-status ${isError ? 'error' : ''}">
            <div>
                <i class="fa-solid ${isError ? 'fa-triangle-exclamation' : 'fa-circle-info'}"></i>
                <br>
                ${escapeChartText(message)}
            </div>
        </div>
    `;

    setChartControlsEnabled(false);
}


function fitOrganizationChart() {
    if (!chart) return;

    try {
        chart.fit();
    } catch (error) {
        console.warn('[Organization Chart] Fit failed:', error);
    }
}


function expandOrganizationChart() {
    if (!chart) return;

    try {
        chart.expandAll().fit();
    } catch (error) {
        console.warn('[Organization Chart] Expand all failed:', error);
    }
}


function collapseOrganizationChart() {
    if (!chart) return;

    try {
        chart.collapseAll().fit();
    } catch (error) {
        console.warn('[Organization Chart] Collapse all failed:', error);
    }
}


async function toggleOrganizationFullscreen() {
    const card = document.getElementById('organizationChartCard');

    if (!card) return;

    try {
        if (!document.fullscreenElement) {
            await card.requestFullscreen();
        } else {
            await document.exitFullscreen();
        }
    } catch (error) {
        console.warn('[Organization Chart] Fullscreen unavailable:', error);
    }
}


function printOrganizationChart() {
    if (!chart) return;

    fitOrganizationChart();

    window.setTimeout(() => {
        window.print();
    }, 180);
}


/* --------------------------------------------------------------
   DATA HELPERS
-------------------------------------------------------------- */
function nodeId(node) {
    return String(node?.id ?? '');
}


function nodeParentId(node) {
    const value = node?.parentId ?? node?.parent_id ?? node?.parent;

    if (value === null || value === undefined || value === '') {
        return '';
    }

    return String(value);
}


function isVacantNode(node) {
    if (node?.vacant === true || node?.vacant === 1 || node?.vacant === '1') {
        return true;
    }

    const employees = Array.isArray(node?.employees) ? node.employees : [];

    return employees.length === 0;
}


function departmentName(node) {
    return String(node?.department ?? '').trim();
}


function buildDepthMap(data) {
    const map = new Map();
    const byId = new Map(data.map(item => [nodeId(item), item]));

    function resolveDepth(item, visited = new Set()) {
        const id = nodeId(item);

        if (map.has(id)) {
            return map.get(id);
        }

        if (visited.has(id)) {
            map.set(id, 1);
            return 1;
        }

        visited.add(id);

        const parentId = nodeParentId(item);

        if (!parentId || !byId.has(parentId)) {
            map.set(id, 1);
            return 1;
        }

        const depth = resolveDepth(byId.get(parentId), visited) + 1;
        map.set(id, depth);

        return depth;
    }

    data.forEach(item => resolveDepth(item));

    return map;
}


function getDirectReportCount(targetNode, data = organizationData) {
    const targetId = nodeId(targetNode);

    return data.filter(item => nodeParentId(item) === targetId).length;
}


function getParentNode(targetNode) {
    const parentId = nodeParentId(targetNode);

    if (!parentId) return null;

    return organizationData.find(item => nodeId(item) === parentId) || null;
}


/*
 * Department focus keeps:
 * - matching department positions
 * - every ancestor needed to preserve a valid tree path
 *
 * Hierarchy-depth filter is then applied.
 */
function buildFilteredOrganizationData() {
    const department = document.getElementById('orgDepartmentFilter')?.value ?? '';
    const depthValue = document.getElementById('orgDepthFilter')?.value ?? '';
    const maxDepth = depthValue ? Number(depthValue) : null;

    let allowedIds = new Set(organizationData.map(nodeId));

    if (department) {
        const byId = new Map(
            organizationData.map(item => [nodeId(item), item])
        );

        allowedIds = new Set();

        organizationData.forEach(item => {
            if (departmentName(item) !== department) return;

            let current = item;
            const safety = new Set();

            while (current) {
                const currentId = nodeId(current);

                if (!currentId || safety.has(currentId)) break;

                safety.add(currentId);
                allowedIds.add(currentId);

                const parentId = nodeParentId(current);

                if (!parentId || !byId.has(parentId)) break;

                current = byId.get(parentId);
            }
        });
    }

    let filtered = organizationData.filter(item => allowedIds.has(nodeId(item)));

    if (maxDepth) {
        const depthMap = buildDepthMap(organizationData);

        filtered = filtered.filter(item => {
            return (depthMap.get(nodeId(item)) || 1) <= maxDepth;
        });
    }

    return filtered;
}


/* --------------------------------------------------------------
   STATS / FILTER UI
-------------------------------------------------------------- */
function updateOrganizationStats() {
    const departments = new Set(
        organizationData
            .map(departmentName)
            .filter(Boolean)
    );

    const positions = organizationData.length;
    const vacant = organizationData.filter(isVacantNode).length;
    const filled = Math.max(0, positions - vacant);

    document.getElementById('orgStatDepartments').textContent = departments.size;
    document.getElementById('orgStatPositions').textContent = positions;
    document.getElementById('orgStatFilled').textContent = filled;
    document.getElementById('orgStatVacant').textContent = vacant;
}


function populateDepartmentFilter() {
    const select = document.getElementById('orgDepartmentFilter');

    if (!select) return;

    const departments = Array.from(
        new Set(
            organizationData
                .map(departmentName)
                .filter(Boolean)
        )
    ).sort((a, b) => a.localeCompare(b));

    select.innerHTML = '<option value="">All Departments</option>';

    departments.forEach(department => {
        const option = document.createElement('option');
        option.value = department;
        option.textContent = department;
        select.appendChild(option);
    });
}


function updateChartContext() {
    const departmentSelect = document.getElementById('orgDepartmentFilter');
    const depthSelect = document.getElementById('orgDepthFilter');

    const departmentText =
        departmentSelect?.selectedOptions?.[0]?.textContent || 'All Departments';

    const depthText =
        depthSelect?.selectedOptions?.[0]?.textContent || 'All Levels';

    const departmentOutput = document.getElementById('orgContextDepartment');
    const depthOutput = document.getElementById('orgContextDepth');

    if (departmentOutput) {
        departmentOutput.textContent =
            departmentSelect?.value ? departmentText : 'All Company';
    }

    if (depthOutput) {
        depthOutput.textContent =
            depthSelect?.value ? depthText : 'All Levels';
    }
}


/* --------------------------------------------------------------
   SEARCH
-------------------------------------------------------------- */
function buildSearchCatalog() {
    const results = [];

    organizationData.forEach(node => {
        const id = nodeId(node);
        const position = String(node.position ?? 'Position');
        const department = departmentName(node);

        results.push({
            nodeId: id,
            type: 'position',
            title: position,
            subtitle: department || 'Position',
            search: `${position} ${department}`.toLowerCase()
        });

        const employees = Array.isArray(node.employees) ? node.employees : [];

        employees.forEach(employee => {
            const employeeName = String(employee?.name ?? '').trim();

            if (!employeeName) return;

            results.push({
                nodeId: id,
                type: 'employee',
                title: employeeName,
                subtitle: `${position}${department ? ' • ' + department : ''}`,
                search: `${employeeName} ${position} ${department}`.toLowerCase()
            });
        });
    });

    return results;
}


function renderSearchResults(query) {
    const container = document.getElementById('orgSearchResults');

    if (!container) return;

    const cleanQuery = String(query ?? '').trim().toLowerCase();

    if (cleanQuery.length < 2) {
        container.classList.remove('open');
        container.innerHTML = '';
        return;
    }

    const matches = buildSearchCatalog()
        .filter(item => item.search.includes(cleanQuery))
        .slice(0, 10);

    if (matches.length === 0) {
        container.innerHTML = `
            <div class="org-search-empty">
                No staff or position found.
            </div>
        `;
        container.classList.add('open');
        return;
    }

    container.innerHTML = matches.map(item => `
        <button type="button"
                class="org-search-item"
                onclick='focusOrganizationNode(${JSON.stringify(item.nodeId)})'>
            <span class="org-search-icon">
                <i class="fa-solid ${item.type === 'employee' ? 'fa-user' : 'fa-id-badge'}"></i>
            </span>

            <span class="org-search-copy">
                <span class="org-search-main">${escapeChartText(item.title)}</span>
                <span class="org-search-sub">${escapeChartText(item.subtitle)}</span>
            </span>
        </button>
    `).join('');

    container.classList.add('open');
}


function focusOrganizationNode(id) {
    const searchResults = document.getElementById('orgSearchResults');
    const departmentFilter = document.getElementById('orgDepartmentFilter');
    const depthFilter = document.getElementById('orgDepthFilter');

    if (searchResults) searchResults.classList.remove('open');

    /*
     * A search should always be able to find the user even if a filter
     * was previously hiding that node.
     */
    if (departmentFilter) departmentFilter.value = '';
    if (depthFilter) depthFilter.value = '';

    renderOrganizationChart();

    window.setTimeout(() => {
        if (!chart) return;

        try {
            if (typeof chart.clearHighlighting === 'function') {
                chart.clearHighlighting();
            }

            if (typeof chart.setCentered === 'function') {
                chart.setCentered(id);
            }

            if (typeof chart.setHighlighted === 'function') {
                chart.setHighlighted(id);
            }

            if (typeof chart.setUpToTheRootHighlighted === 'function') {
                chart.setUpToTheRootHighlighted(id);
            }

            chart.render();

            window.setTimeout(() => {
                if (typeof chart.fit === 'function') {
                    chart.fit();
                }
            }, 80);
        } catch (error) {
            console.warn('[Organization Chart] Search focus failed:', error);
            fitOrganizationChart();
        }
    }, 50);
}


/* --------------------------------------------------------------
   DETAIL DRAWER
-------------------------------------------------------------- */
function openOrgDetailDrawer(node) {
    if (!node) return;

    const drawer = document.getElementById('orgDetailDrawer');
    const backdrop = document.getElementById('orgDrawerBackdrop');
    const title = document.getElementById('orgDrawerTitle');
    const body = document.getElementById('orgDrawerBody');

    if (!drawer || !backdrop || !body || !title) return;

    const parent = getParentNode(node);
    const employees = Array.isArray(node.employees) ? node.employees : [];
    const vacant = isVacantNode(node);
    const directReports = getDirectReportCount(node);

    title.textContent = node.position || 'Position';

    let employeeHtml = '';

    if (vacant || employees.length === 0) {
        employeeHtml = `
            <div class="org-empty-person">
                <i class="fa-solid fa-user-xmark"></i>
                Vacant position — no employee assigned.
            </div>
        `;
    } else {
        employeeHtml = employees.map(employee => {
            const photo = escapeChartText(employee?.photo || DEFAULT_AVATAR);
            const name = escapeChartText(employee?.name || 'Employee');

            return `
                <div class="org-employee-card">
                    <img src="${photo}"
                         alt="${name}"
                         onerror="this.onerror=null;this.src='${DEFAULT_AVATAR}';">

                    <div>
                        <div class="org-employee-name">${name}</div>
                        <div style="margin-top:3px;color:#94A3B8;font-size:8.5px;">
                            Assigned to this position
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    body.innerHTML = `
        <div class="org-drawer-section">
            <div class="org-drawer-label">Status</div>

            <span class="org-status-chip ${vacant ? 'vacant' : 'filled'}">
                <i class="fa-solid ${vacant ? 'fa-circle-minus' : 'fa-circle-check'}"></i>
                ${vacant ? 'Vacant' : 'Filled'}
            </span>
        </div>

        <div class="org-drawer-section">
            <div class="org-drawer-label">Hierarchy Information</div>

            <div class="org-drawer-info">
                <div class="org-info-box">
                    <span>Department</span>
                    <strong>${escapeChartText(node.department || 'Not assigned')}</strong>
                </div>

                <div class="org-info-box">
                    <span>Reports To</span>
                    <strong>${escapeChartText(parent?.position || 'Top Level')}</strong>
                </div>

                <div class="org-info-box">
                    <span>Direct Reports</span>
                    <strong>${directReports} position${directReports === 1 ? '' : 's'}</strong>
                </div>

                <div class="org-info-box">
                    <span>Employees</span>
                    <strong>${employees.length}</strong>
                </div>
            </div>
        </div>

        <div class="org-drawer-section">
            <div class="org-drawer-label">Assigned Employee${employees.length === 1 ? '' : 's'}</div>
            <div class="org-employee-list">
                ${employeeHtml}
            </div>
        </div>
    `;

    drawer.classList.add('open');
    backdrop.classList.add('open');

    drawer.setAttribute('aria-hidden', 'false');
    backdrop.setAttribute('aria-hidden', 'false');
}


function closeOrgDetailDrawer() {
    const drawer = document.getElementById('orgDetailDrawer');
    const backdrop = document.getElementById('orgDrawerBackdrop');

    if (drawer) {
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
    }

    if (backdrop) {
        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
    }
}


/* --------------------------------------------------------------
   NODE DESIGN
-------------------------------------------------------------- */
function organizationNodeHtml(d) {
    const node = d.data;

    const vacant = isVacantNode(node);
    const borderColor = vacant ? '#CBD5E1' : '#60A5FA';
    const borderStyle = vacant ? 'dashed' : 'solid';
    const background = vacant ? '#F8FAFC' : '#FFFFFF';

    const employees = Array.isArray(node.employees) ? node.employees : [];

    let peopleHtml = '';

    if (vacant) {
        peopleHtml = `
            <div style="
                display:flex;
                align-items:center;
                gap:9px;
                margin-top:10px;
                padding:8px;
                border-radius:9px;
                background:#F1F5F9;
            ">
                <div style="
                    width:34px;
                    height:34px;
                    flex:0 0 34px;
                    display:grid;
                    place-items:center;
                    border-radius:9px;
                    background:#E2E8F0;
                    color:#94A3B8;
                ">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>

                <div>
                    <div style="
                        color:#64748B;
                        font-size:11px;
                        font-weight:800;
                    ">
                        Vacant
                    </div>

                    <div style="
                        margin-top:2px;
                        color:#94A3B8;
                        font-size:8.5px;
                    ">
                        No employee assigned
                    </div>
                </div>
            </div>
        `;
    } else {
        peopleHtml = employees.map(employee => {
            const photo = escapeChartText(employee?.photo || DEFAULT_AVATAR);
            const name = escapeChartText(employee?.name || 'Employee');

            return `
                <div style="
                    display:flex;
                    align-items:center;
                    gap:9px;
                    margin-top:8px;
                ">
                    <img src="${photo}"
                         alt="${name}"
                         onerror="this.onerror=null;this.src='${DEFAULT_AVATAR}';"
                         style="
                            width:36px;
                            height:36px;
                            flex:0 0 36px;
                            display:block;
                            border:1px solid #BFDBFE;
                            border-radius:9px;
                            background:#DBEAFE;
                            object-fit:cover;
                            object-position:center;
                         ">

                    <div style="
                        min-width:0;
                        overflow:hidden;
                        color:#1E293B;
                        font-size:11px;
                        font-weight:750;
                        text-overflow:ellipsis;
                        white-space:nowrap;
                    ">
                        ${name}
                    </div>
                </div>
            `;
        }).join('');
    }

    return `
        <div style="
            box-sizing:border-box;
            height:${d.height}px;
            padding:10px 2px 2px;
            font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
        ">
            <div style="
                box-sizing:border-box;
                height:${d.height - 12}px;
                overflow:hidden;
                padding:13px;
                border:1.5px ${borderStyle} ${borderColor};
                border-radius:12px;
                background:${background};
                box-shadow:0 5px 14px rgba(15,23,42,.065);
                cursor:pointer;
            ">
                <div style="
                    display:flex;
                    align-items:flex-start;
                    justify-content:space-between;
                    gap:8px;
                ">
                    <div style="min-width:0;flex:1;">
                        <div style="
                            overflow:hidden;
                            color:#0F172A;
                            font-size:12px;
                            font-weight:850;
                            line-height:1.3;
                            text-overflow:ellipsis;
                            white-space:nowrap;
                        ">
                            ${escapeChartText(node.position || 'Position')}
                        </div>

                        <div style="
                            margin-top:4px;
                            overflow:hidden;
                            color:#D97706;
                            font-size:8px;
                            font-weight:800;
                            letter-spacing:.055em;
                            text-overflow:ellipsis;
                            text-transform:uppercase;
                            white-space:nowrap;
                        ">
                            ${escapeChartText(node.department || 'No Department')}
                        </div>
                    </div>

                    <span style="
                        display:inline-flex;
                        align-items:center;
                        gap:4px;
                        flex:0 0 auto;
                        padding:3px 6px;
                        border-radius:999px;
                        background:${vacant ? '#F1F5F9' : '#ECFDF5'};
                        color:${vacant ? '#64748B' : '#047857'};
                        font-size:7.5px;
                        font-weight:850;
                        text-transform:uppercase;
                    ">
                        <i class="fa-solid ${vacant ? 'fa-circle-minus' : 'fa-circle-check'}"></i>
                        ${vacant ? 'Vacant' : 'Filled'}
                    </span>
                </div>

                ${peopleHtml}
            </div>
        </div>
    `;
}


/* --------------------------------------------------------------
   CHART RENDER
-------------------------------------------------------------- */
function renderOrganizationChart() {
    if (!organizationData.length) return;

    renderedOrganizationData = buildFilteredOrganizationData();

    updateChartContext();

    if (!renderedOrganizationData.length) {
        showChartMessage('No positions match the selected filters.');
        return;
    }

    const container = document.querySelector('.chart-container');

    if (!container) return;

    container.innerHTML = '';

    try {
        chart = new d3.OrgChart()
            .container('.chart-container')
            .data(renderedOrganizationData)
            .nodeWidth(() => 250)
            .initialZoom(0.72)
            .nodeHeight(d => {
                const employees = Array.isArray(d.data.employees)
                    ? d.data.employees.length
                    : 0;

                if (isVacantNode(d.data)) {
                    return 132;
                }

                return 125 + Math.max(0, employees - 1) * 44;
            })
            .childrenMargin(() => 46)
            .compactMarginBetween(() => 23)
            .compactMarginPair(() => 42)
            .nodeContent(organizationNodeHtml)
            .onNodeClick(d => {
                openOrgDetailDrawer(d.data);
            })
            .render();

        setChartControlsEnabled(true);

        window.setTimeout(() => {
            if (chart) chart.fit();
        }, 100);

    } catch (error) {
        console.error('[Organization Chart Render]', error);

        showChartMessage(
            error.message || 'Unable to render the organization chart.',
            true
        );
    }
}


/* --------------------------------------------------------------
   INITIAL LOAD
-------------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', async function () {
    try {
        if (!window.d3 || typeof d3.OrgChart !== 'function') {
            throw new Error(
                'The organization chart library could not be loaded. Please refresh the page.'
            );
        }

        const response = await fetch(
            'index.php?action=get_org_chart',
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            }
        );

        if (!response.ok) {
            throw new Error(
                `Unable to load organization data (HTTP ${response.status}).`
            );
        }

        const data = await response.json();

        if (!Array.isArray(data) || data.length === 0) {
            showChartMessage(
                'No organization data found. Please add positions first.'
            );
            return;
        }

        organizationData = data;

        updateOrganizationStats();
        populateDepartmentFilter();
        renderOrganizationChart();

    } catch (error) {
        console.error('[Organization Chart]', error);

        showChartMessage(
            error.message || 'Unable to render the organization chart.',
            true
        );
    }
});


/* --------------------------------------------------------------
   EVENTS
-------------------------------------------------------------- */
document.getElementById('orgSearchInput')?.addEventListener('input', function () {
    renderSearchResults(this.value);
});


document.getElementById('orgDepartmentFilter')?.addEventListener('change', function () {
    renderOrganizationChart();
});


document.getElementById('orgDepthFilter')?.addEventListener('change', function () {
    renderOrganizationChart();
});


document.addEventListener('click', function (event) {
    const field = event.target.closest('.org-field');
    const results = document.getElementById('orgSearchResults');

    if (!field && results) {
        results.classList.remove('open');
    }
});


document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeOrgDetailDrawer();

        const results = document.getElementById('orgSearchResults');
        if (results) results.classList.remove('open');
    }
});


document.addEventListener('fullscreenchange', function () {
    const button = document.getElementById('fullscreenChartButton');

    if (!button) return;

    if (document.fullscreenElement) {
        button.innerHTML =
            '<i class="fa-solid fa-minimize"></i> Exit Fullscreen';
    } else {
        button.innerHTML =
            '<i class="fa-solid fa-maximize"></i> Fullscreen';
    }

    window.setTimeout(() => {
        fitOrganizationChart();
    }, 120);
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
