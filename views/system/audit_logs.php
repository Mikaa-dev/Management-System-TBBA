<?php
/**
 * System Audit Trail & Activity Log View
 * Company: The Bridge Business Alliance (TBBA)
 */
require_once __DIR__ . '/../../core/Auth.php';
Auth::requireLogin();
$currentUser = Auth::user();
$pageTitle = "System Audit Trail & Activity Log";
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';

if (!isset($selectedAction)) $selectedAction = 'ALL';
if (!isset($auditLogs)) $auditLogs = [];
?>

<!-- Banner Header Korporat -->
<div class="card" style="margin-bottom: 28px; background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 100%); color: #FFFFFF; border: none; border-radius: 20px; padding: 32px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
    <div style="flex: 1; min-width: 280px;">
        <span style="background: rgba(239, 68, 68, 0.2); color: #FCA5A5; border: 1px solid rgba(239, 68, 68, 0.4); padding: 5px 14px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px;">
            <i class="fa-solid fa-shield-halved"></i> Enterprise Security & Compliance
        </span>
        <h1 style="font-size: 26px; font-weight: 800; margin: 0 0 8px; color: #FFFFFF; display: flex; align-items: center; gap: 10px;">
            <span>System Audit Trail & Activity Log</span>
        </h1>
        <p style="font-size: 14px; color: #CBD5E1; margin: 0; max-width: 650px; line-height: 1.6;">
            Silent, tamper-evident security tracker recording critical corporate actions, record deletions, status modifications, and login events across TBBA Mini ERP.
        </p>
    </div>

    <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); padding: 16px 24px; border-radius: 16px; text-align: center; backdrop-filter: blur(10px);">
        <div style="font-size: 26px; font-weight: 800; color: #38BDF8;"><?= count($auditLogs) ?></div>
        <div style="font-size: 12px; font-weight: 600; color: #E2E8F0;">Total Recorded Events</div>
    </div>
</div>

        <!-- Filter Bar & Search -->
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 18px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php
                $actions = [
                    'ALL' => ['All Actions', 'fa-layer-group', '#475569'],
                    'DELETE' => ['Deletions', 'fa-trash-can', '#DC2626'],
                    'CREATE' => ['Submissions', 'fa-plus', '#059669'],
                    'UPDATE' => ['Modifications', 'fa-pen-to-square', '#D97706'],
                    'LOGIN' => ['Authentication', 'fa-key', '#2563EB'],
                    'DOCUMENT' => ['Documents / SOPs', 'fa-file-shield', '#7C3AED'],
                    'ATTENDANCE' => ['GPS Geofencing', 'fa-location-dot', '#0891B2']
                ];
                foreach ($actions as $key => $actInfo):
                    $isActive = ($selectedAction === $key);
                ?>
                    <a href="index.php?page=audit_logs&action_type=<?= urlencode($key) ?>" 
                       style="padding: 8px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease; <?= $isActive ? 'background: #0F172A; color: #FFF; box-shadow: 0 4px 10px rgba(15,23,42,0.25); border: 1px solid #0F172A;' : 'background: #F8FAFC; color: #475569; border: 1px solid #CBD5E1;' ?>">
                        <i class="fa-solid <?= $actInfo[1] ?>" style="color: <?= $isActive ? '#FFF' : $actInfo[2] ?>;"></i> <?= $actInfo[0] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Audit Table -->
        <div class="card" style="border: 1px solid #E2E8F0; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #FFFFFF; padding: 24px; width: 100%; box-sizing: border-box;">
            <?php if (empty($auditLogs)): ?>
                <div style="padding: 60px 20px; text-align: center;">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: #F1F5F9; color: #64748B; display: inline-flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 14px;">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 6px;">No Security Logs Recorded</h3>
                    <p style="font-size: 14px; color: #64748B; margin: 0;">
                        <?= $selectedAction === 'ALL' ? "No activity logs have been captured in the system yet." : "No logs available matching the filter '{$selectedAction}'." ?>
                    </p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="audit-table datatable" id="auditLogsTable" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                                <th style="padding: 16px 20px; width: 180px;">Date & Timestamp</th>
                                <th style="padding: 16px 20px; width: 220px;">User / Actor</th>
                                <th style="padding: 16px 20px; width: 140px;">Action Type</th>
                                <th style="padding: 16px 20px;">Security Event Description</th>
                                <th style="padding: 16px 20px; width: 130px; text-align: right;">IP Address</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <?php foreach ($auditLogs as $log): 
                                // Badge styles
                                $badgeStyle = 'background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1;';
                                $badgeIcon  = 'fa-circle-info';
                                
                                if ($log['action_type'] === 'DELETE') {
                                    $badgeStyle = 'background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; font-weight: 700;';
                                    $badgeIcon  = 'fa-trash-can';
                                } elseif ($log['action_type'] === 'CREATE') {
                                    $badgeStyle = 'background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; font-weight: 700;';
                                    $badgeIcon  = 'fa-plus';
                                } elseif ($log['action_type'] === 'UPDATE') {
                                    $badgeStyle = 'background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; font-weight: 700;';
                                    $badgeIcon  = 'fa-pen-to-square';
                                } elseif ($log['action_type'] === 'LOGIN') {
                                    $badgeStyle = 'background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; font-weight: 700;';
                                    $badgeIcon  = 'fa-key';
                                } elseif ($log['action_type'] === 'DOCUMENT') {
                                    $badgeStyle = 'background: #F5F3FF; color: #7C3AED; border: 1px solid #DDD6FE; font-weight: 700;';
                                    $badgeIcon  = 'fa-file-shield';
                                } elseif ($log['action_type'] === 'ATTENDANCE') {
                                    $badgeStyle = 'background: #ECFEFF; color: #0891B2; border: 1px solid #A5F3FC; font-weight: 700;';
                                    $badgeIcon  = 'fa-location-dot';
                                }
                            ?>
                            <tr class="audit-row" data-user="<?= htmlspecialchars(strtolower($log['user_name'] ?? '')) ?>" data-desc="<?= htmlspecialchars(strtolower($log['description'])) ?>" 
                                style="border-bottom: 1px solid #F1F5F9; transition: background 0.15s ease;">
                                
                                <td style="padding: 16px 20px; font-size: 13px; color: #334155; white-space: nowrap;">
                                    <div style="font-weight: 700; color: #0F172A;"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                                    <div style="font-size: 11px; color: #64748B;"><?= date('g:i:s A', strtotime($log['created_at'])) ?></div>
                                </td>

                                <td style="padding: 16px 20px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: #E2E8F0; color: #334155; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0;">
                                            <?= strtoupper(substr($log['user_name'] ?? 'U', 0, 2)) ?>
                                        </div>
                                        <div>
                                            <div style="font-size: 13px; font-weight: 700; color: #0F172A;"><?= htmlspecialchars($log['user_name'] ?? 'System / Guest') ?></div>
                                            <div style="font-size: 11px; color: #64748B;">
                                                <?= ($log['user_role'] === 'admin') ? '<span style="color: #D97706; font-weight: 700;"><i class="fa-solid fa-crown"></i> Admin</span>' : 'Staff Member' ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td style="padding: 16px 20px;">
                                    <span style="padding: 5px 10px; border-radius: 6px; font-size: 11px; display: inline-flex; align-items: center; gap: 6px; <?= $badgeStyle ?>">
                                        <i class="fa-solid <?= $badgeIcon ?>"></i> <?= htmlspecialchars($log['action_type']) ?>
                                    </span>
                                </td>

                                <td style="padding: 16px 20px; font-size: 13px; color: #1E293B; line-height: 1.5;">
                                    <?= htmlspecialchars($log['description']) ?>
                                </td>

                                <td style="padding: 16px 20px; font-size: 12px; color: #64748B; text-align: right; font-family: monospace;">
                                    <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

<style>
.audit-table tr:hover {
    background: #F8FAFC !important;
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
