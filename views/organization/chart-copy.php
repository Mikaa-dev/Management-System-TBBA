<?php
/**
 * Organization Chart View — TBBA ERP
 */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<!-- d3-org-chart dependencies -->
<script src="https://d3js.org/d3.v7.min.js" integrity="sha384-CjloA8y00+1SDAUkjs099PVfnY2KmDC2BZnws9kh8D/lX1s46w6EPhpXdqMfjK6i" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-org-chart@3.1.1" integrity="sha384-j2Vlh7leSFYx1AI8mtBLXkmfX0U/6ItQrDKqzclCbTV0jlMFb10HxJR8iALXfXFI" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-flextree@2.1.2/build/d3-flextree.js" integrity="sha384-6pTgblH+kfP7e8kLkJxI96n+G6MCr28XHUtlXyr3cSjSyT/co6eOBwwCPAX8pBb5" crossorigin="anonymous"></script>

<div class="page-content" style="padding: 24px;">
    <!-- Top Bar -->
    <div class="card" style="padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; background: var(--bg-card);">
        <div>
            <h3 style="margin: 0; font-size: 18px; color: var(--text-dark);"><i class="fa-solid fa-sitemap" style="color: #D97706;"></i> Organization Chart</h3>
            <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">View the interactive, auto-updating company hierarchy.</p>
        </div>
        <div>
            <button id="fitChartButton" class="btn btn-secondary" type="button" onclick="fitOrganizationChart()" disabled style="padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;"><i class="fa-solid fa-expand"></i> Fit to Screen</button>
            <button id="expandChartButton" class="btn btn-secondary" type="button" onclick="expandOrganizationChart()" disabled style="padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;"><i class="fa-solid fa-plus"></i> Expand All</button>
        </div>
    </div>

    <!-- Chart Container -->
    <div class="card" style="border-radius: 12px; background: var(--bg-card); overflow: hidden; padding: 0;">
        <div class="chart-container" style="height: 700px; width: 100%; background-color: #f8fafc;">
            <div id="chartStatus" style="height:100%;display:flex;align-items:center;justify-content:center;padding:40px;text-align:center;color:#64748b;">
                <div><i class="fa-solid fa-spinner fa-spin" style="font-size:24px;margin-bottom:10px;"></i><br>Loading organization chart...</div>
            </div>
        </div>
    </div>
</div>

<script>
    let chart = null;

    function setChartControlsEnabled(enabled) {
        document.getElementById('fitChartButton').disabled = !enabled;
        document.getElementById('expandChartButton').disabled = !enabled;
    }

    function showChartMessage(message, isError = false) {
        document.querySelector('.chart-container').innerHTML = `
            <div style="height:100%;display:flex;align-items:center;justify-content:center;padding:40px;text-align:center;color:${isError ? '#B91C1C' : '#64748b'};">
                <div><i class="fa-solid ${isError ? 'fa-triangle-exclamation' : 'fa-circle-info'}" style="font-size:24px;margin-bottom:10px;"></i><br>${escapeChartText(message)}</div>
            </div>`;
        setChartControlsEnabled(false);
    }

    function escapeChartText(value) {
        return String(value ?? '').replace(/[&<>'"]/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        }[character]));
    }

    function fitOrganizationChart() {
        if (chart) chart.fit();
    }

    function expandOrganizationChart() {
        if (chart) chart.expandAll().fit();
    }

    document.addEventListener('DOMContentLoaded', async function() {
        try {
            if (!window.d3 || typeof d3.OrgChart !== 'function') {
                throw new Error('The organization chart library could not be loaded. Please refresh the page.');
            }

            const response = await fetch('index.php?action=get_org_chart', {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            if (!response.ok) {
                throw new Error(`Unable to load organization data (HTTP ${response.status}).`);
            }
            const data = await response.json();
            if (!data || data.length === 0) {
                showChartMessage('No organization data found. Please add positions first.');
                return;
            }

            document.querySelector('.chart-container').innerHTML = '';
            chart = new d3.OrgChart()
                .container('.chart-container')
                .data(data)
                .nodeWidth(d => 260)
                .initialZoom(0.7)
                .nodeHeight(d => {
                    if (d.data.vacant) return 140;
                    // height depends on number of employees
                    const count = d.data.employees ? d.data.employees.length : 1;
                    return 145 + ((count - 1) * 50); 
                })
                .childrenMargin(d => 50)
                .compactMarginBetween(d => 25)
                .compactMarginPair(d => 50)
                .nodeContent(function(d, i, arr, state) {
                    const borderColor = d.data.vacant ? '#cbd5e1' : '#38bdf8';
                    const borderStyle = d.data.vacant ? 'dashed' : 'solid';
                    const bgColor = d.data.vacant ? '#f1f5f9' : '#ffffff';
                    
                    let html = `
                    <div style="padding-top:20px; background-color:transparent; margin-left:1px; height:${d.height}px; border-radius:8px; overflow:visible">
                        <div style="height:${d.height - 20}px; box-sizing:border-box; background-color:${bgColor}; border: 2px ${borderStyle} ${borderColor}; border-radius:10px; padding: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                            
                            <div style="font-family: 'Plus Jakarta Sans', sans-serif;">
                                <div style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 2px;">${escapeChartText(d.data.position)}</div>
                                <div style="font-size: 11px; font-weight: 600; color: #d97706; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">${escapeChartText(d.data.department || '')}</div>
                    `;

                    if (d.data.vacant) {
                        html += `
                                <div style="display:flex; align-items:center; gap: 10px; margin-top: 10px;">
                                    <div style="width:36px; height:36px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                        <i class="fa-solid fa-user-xmark"></i>
                                    </div>
                                    <div style="font-size: 13px; font-weight: 600; color: #64748b; font-style: italic;">Vacant</div>
                                </div>
                        `;
                    } else if (d.data.employees) {
                        d.data.employees.forEach(emp => {
                            const defaultAvatar = 'assets/images/default-avatar.svg';
                            const photo = escapeChartText(emp.photo || defaultAvatar);
                            html += `
                                <div style="display:flex; align-items:center; gap: 10px; margin-bottom: 10px;">
                                    <img src="${photo}" alt="${escapeChartText(emp.name)} profile picture" onerror="this.onerror=null;this.src='${defaultAvatar}';" style="width:40px; height:40px; flex:0 0 40px; border-radius:50%; border:1px solid #BFDBFE; background:#DBEAFE; object-fit:cover; object-position:center; display:block;">
                                    <div style="font-size: 14px; font-weight: 600; color: #1e293b;">${escapeChartText(emp.name)}</div>
                                </div>
                            `;
                        });
                    }

                    html += `
                            </div>
                        </div>
                    </div>
                    `;
                    return html;
                })
                .render();
            setChartControlsEnabled(true);
            window.setTimeout(() => chart.fit(), 100);
        } catch (error) {
            console.error('[Organization Chart]', error);
            showChartMessage(error.message || 'Unable to render the organization chart.', true);
        }
    });
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
