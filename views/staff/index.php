<?php
/**
 * Staff Management View — TBBA ERP
 * Company: The Bridge Business Alliance (TBBA)
 * Updated: Module 1 — Expanded fields, 8 roles, department/branch, status toggle
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../core/Auth.php';
$currentUser = Auth::user();
$pageTitle   = 'Staff Management';

// Stats
$totalUsers  = count($staffList ?? []);
$roleCounts  = [];
$activeCount = 0;
$suspCount   = 0;
foreach (($staffList ?? []) as $u) {
    $roleCounts[$u['role']] = ($roleCounts[$u['role']] ?? 0) + 1;
    if (($u['status'] ?? 'active') === 'active') $activeCount++;
    else $suspCount++;
}

// Role badge config
$staffRoleBadges = [
    'super_admin' => ['bg' => '#EDE9FE', 'text' => '#7C3AED', 'icon' => 'fa-crown'],
    'admin'       => ['bg' => '#EFF6FF', 'text' => '#1D4ED8', 'icon' => 'fa-user-shield'],
    'hr'          => ['bg' => '#E0F2FE', 'text' => '#0891B2', 'icon' => 'fa-people-roof'],
    'manager'     => ['bg' => '#DCFCE7', 'text' => '#059669', 'icon' => 'fa-user-tie'],
    'dept_head'   => ['bg' => '#FEF3C7', 'text' => '#D97706', 'icon' => 'fa-sitemap'],
    'finance'     => ['bg' => '#FCE7F3', 'text' => '#BE185D', 'icon' => 'fa-money-bill-trend-up'],
    'auditor'     => ['bg' => '#F1F5F9', 'text' => '#475569', 'icon' => 'fa-magnifying-glass-chart'],
    'staff'       => ['bg' => '#F8FAFC', 'text' => '#64748B', 'icon' => 'fa-user'],
];

$canCreate = Auth::hasPermission('staff', 'create');
$canEdit   = Auth::hasPermission('staff', 'edit');
$canDelete = Auth::hasPermission('staff', 'delete');

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>
<style>
.staff-card-stat{background:#FFF;border:1px solid #E2E8F0;border-radius:16px;padding:24px;box-shadow:0 4px 6px -1px rgba(0,0,0,.05);transition:all .3s;}
.staff-card-stat:hover{box-shadow:0 10px 15px -3px rgba(0,0,0,.1);transform:translateY(-2px);}
.avatar-circle{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#1D4ED8);color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;text-transform:uppercase;flex-shrink:0;box-shadow:0 2px 6px rgba(37,99,235,.25);}
.role-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:50px;font-size:11px;font-weight:700;text-transform:uppercase;}
.status-active{background:#DCFCE7;color:#15803D;border:1px solid #BBF7D0;padding:4px 10px;border-radius:50px;font-size:11px;font-weight:700;}
.status-suspended{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:4px 10px;border-radius:50px;font-size:11px;font-weight:700;}
.modal{display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
.modal.active{display:flex;}
.modal-content{background:#FFF;border-radius:24px;width:100%;max-width:600px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);overflow:hidden;animation:modalFadeIn .3s cubic-bezier(.16,1,.3,1);max-height:90vh;overflow-y:auto;}
@keyframes modalFadeIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.form-label{font-size:12px;font-weight:700;color:#64748B;text-transform:uppercase;display:block;margin-bottom:6px;}
.form-input{width:100%;padding:11px 14px;border:1px solid #CBD5E1;border-radius:10px;font-size:14px;color:#0F172A;outline:none;transition:border-color .2s;box-sizing:border-box;}
.form-input:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  /* Login History close button — force visible */
#loginHistoryModal .login-history-close {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: absolute !important;
    top: 14px !important;
    right: 14px !important;
    z-index: 100002 !important;

    width: 34px !important;
    height: 34px !important;

    align-items: center !important;
    justify-content: center !important;

    background: #F1F5F9 !important;
    color: #475569 !important;

    border: 1px solid #E2E8F0 !important;
    border-radius: 50% !important;

    font-size: 22px !important;
    cursor: pointer !important;

    transition: all .2s ease !important;
}

#loginHistoryModal .login-history-close:hover {
    background: #FEE2E2 !important;
    color: #DC2626 !important;
    border-color: #FECACA !important;
    transform: rotate(90deg);
}
</style>

<!-- ─── Header Banner ─────────────────────────────────────── -->
<div class="module-page-header" style="background:linear-gradient(135deg,#0F172A 0%,#1E3A8A 100%);color:#FFF;border-radius:20px;padding:32px;margin-bottom:28px;position:relative;overflow:hidden;box-shadow:0 10px 25px -5px rgba(15,23,42,.3);">
    <div style="position:absolute;top:-20%;right:-5%;width:300px;height:300px;background:radial-gradient(circle,rgba(56,189,248,.25) 0%,transparent 70%);border-radius:50%;"></div>
    <div style="position:relative;z-index:2;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
        <div>
            <div class="module-page-eyebrow" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);color:#38BDF8;font-size:12px;font-weight:700;padding:6px 14px;border-radius:50px;margin-bottom:12px;text-transform:uppercase;">
                <i class="fa-solid fa-users-gear"></i> Corporate Human Resources & Access Control
            </div>
            <h1 style="font-size:28px;font-weight:800;margin-bottom:8px;">Staff Management</h1>
            <p style="color:#CBD5E1;font-size:14px;max-width:650px;margin:0;line-height:1.6;">
                Manage corporate accounts, roles, departments, branches, and access status for all personnel.
            </p>
        </div>
        <?php if ($canCreate): ?>
        <div>
            <button type="button" onclick="openAddModal()"
                style="background:linear-gradient(135deg,#10B981,#059669);color:#FFF;font-weight:700;font-size:13px;padding:12px 22px;border-radius:12px;border:none;box-shadow:0 4px 12px rgba(16,185,129,.35);display:inline-flex;align-items:center;gap:8px;cursor:pointer;transition:all .2s;">
                <i class="fa-solid fa-user-plus"></i> Add New Staff
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── Stats Row ─────────────────────────────────────────── -->
<div class="module-stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:28px;">
    <div class="staff-card-stat module-stat-card">
        <span style="font-size:12px;color:#64748B;font-weight:700;text-transform:uppercase;">Total Users</span>
        <div style="font-size:32px;font-weight:800;color:#0F172A;margin-top:6px;"><?= $totalUsers ?></div>
        <span style="font-size:12px;color:#64748B;"><i class="fa-solid fa-users" style="color:#2563EB;"></i> All portal accounts</span>
    </div>
    <div class="staff-card-stat module-stat-card" style="border-left:5px solid #10B981;">
        <span style="font-size:12px;color:#64748B;font-weight:700;text-transform:uppercase;">Active Accounts</span>
        <div style="font-size:32px;font-weight:800;color:#10B981;margin-top:6px;"><?= $activeCount ?></div>
        <span style="font-size:12px;color:#10B981;"><i class="fa-solid fa-user-check"></i> Can log in</span>
    </div>
    <div class="staff-card-stat module-stat-card" style="border-left:5px solid #DC2626;">
        <span style="font-size:12px;color:#64748B;font-weight:700;text-transform:uppercase;">Suspended</span>
        <div style="font-size:32px;font-weight:800;color:#DC2626;margin-top:6px;"><?= $suspCount ?></div>
        <span style="font-size:12px;color:#DC2626;"><i class="fa-solid fa-user-lock"></i> Access blocked</span>
    </div>
    <div class="staff-card-stat module-stat-card" style="border-left:5px solid #7C3AED;">
        <span style="font-size:12px;color:#64748B;font-weight:700;text-transform:uppercase;">Admins / Managers</span>
        <div style="font-size:32px;font-weight:800;color:#7C3AED;margin-top:6px;"><?= ($roleCounts['super_admin'] ?? 0) + ($roleCounts['admin'] ?? 0) + ($roleCounts['manager'] ?? 0) ?></div>
        <span style="font-size:12px;color:#7C3AED;"><i class="fa-solid fa-user-shield"></i> Elevated access</span>
    </div>
</div>

<!-- ─── Table Card ────────────────────────────────────────── -->
<div class="card module-data-card" style="border:1px solid #E2E8F0;border-radius:20px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,.05);background:#FFF;">
    <div class="module-toolbar" style="padding:20px 24px;border-bottom:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h3 style="font-size:18px;font-weight:800;color:#0F172A;margin:0;">Staff & Administrators</h3>
            <p style="font-size:13px;color:#64748B;margin:4px 0 0;">View, update, suspend, or remove personnel portal access</p>
        </div>
        <!-- Filter -->
        <select onchange="filterByRole(this.value)" style="padding:9px 14px;border:1px solid #CBD5E1;border-radius:10px;font-size:13px;font-weight:600;color:#0F172A;background:#FFF;outline:none;">
            <option value="">All Roles</option>
            <?php foreach (($roles ?? []) as $r): ?>
            <option value="<?= htmlspecialchars($r['name']) ?>"><?= htmlspecialchars($r['display_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="table-responsive" style="padding:0;">
        <table class="table datatable" id="staffTable" style="margin:0;width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;text-align:left;">
                    <th style="padding:14px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Employee</th>
                    <th style="padding:14px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Position</th>
                    <th style="padding:14px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Role</th>
                    <th style="padding:14px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Department</th>
                    <th style="padding:14px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Status</th>
                    <th style="padding:14px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;">Joined</th>
                    <th style="padding:14px 20px;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staffList)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:40px;color:#64748B;">No staff found in system database.</td></tr>
                <?php else: ?>
                    <?php foreach ($staffList as $st):
                        $rc   = $staffRoleBadges[$st['role']] ?? $staffRoleBadges['staff'];
                        $isMe = (int)$st['id'] === (int)$currentUser['id'];
                    ?>
                    <tr class="staff-row" data-role="<?= htmlspecialchars($st['role']) ?>" style="border-bottom:1px solid #F1F5F9;transition:background .15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
                        <td style="padding:14px 20px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div class="avatar-circle"><?= strtoupper(substr($st['name'] ?? 'U', 0, 2)) ?></div>
                                <div>
                                    <div style="font-weight:700;color:#0F172A;font-size:14px;">
                                        <?= htmlspecialchars($st['name'] ?? '') ?>
                                        <?php if ($isMe): ?><span style="font-size:11px;color:#2563EB;font-style:italic;margin-left:4px;">(you)</span><?php endif; ?>
                                    </div>
                                    <div style="font-size:12px;color:#64748B;font-family:monospace;"><?= htmlspecialchars($st['email'] ?? '') ?></div>
                                    <?php if (!empty($st['employee_id'])): ?>
                                    <div style="font-size:11px;color:#94A3B8;">ID: <?= htmlspecialchars($st['employee_id']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td style="padding:14px 20px;font-size:13px;color:#334155;font-weight:600;">
                            <?= htmlspecialchars($st['position_title'] ?? $st['position'] ?? 'Executive Staff') ?>
                        </td>
                        <td style="padding:14px 20px;">
                            <span class="role-pill" style="background:<?= $rc['bg'] ?>;color:<?= $rc['text'] ?>;">
                                <i class="fa-solid <?= $rc['icon'] ?>"></i>
                                <?= htmlspecialchars(Auth::roleLabel($st['role'])) ?>
                            </span>
                        </td>
                        <td style="padding:14px 20px;font-size:13px;color:#475569;">
                            <?= htmlspecialchars($st['department_name'] ?? '—') ?>
                        </td>
                        <td style="padding:14px 20px;">
                            <?php if (($st['status'] ?? 'active') === 'active'): ?>
                                <span class="status-active"><i class="fa-solid fa-circle" style="font-size:7px;"></i> Active</span>
                            <?php else: ?>
                                <span class="status-suspended"><i class="fa-solid fa-lock" style="font-size:9px;"></i> Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 20px;font-size:13px;color:#64748B;">
                            <?= !empty($st['created_at']) ? date('d M Y', strtotime($st['created_at'])) : '—' ?>
                        </td>
                        <td style="padding:14px 20px;text-align:right;">
                            <div style="display:inline-flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                                <?php if ($canEdit): ?>
                                <button onclick="openEditModal(<?= (int)$st['id'] ?>)"
                                    style="background:#EFF6FF;color:#2563EB;border:1px solid #BFDBFE;padding:7px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;transition:all .2s;"
                                    title="Edit Profile">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </button>
                                <?php endif; ?>

                                <!-- Login History -->
                                <button onclick="viewLoginHistory(<?= (int)$st['id'] ?>, '<?= htmlspecialchars(addslashes($st['name'])) ?>')"
                                    style="background:#F0FDF4;color:#15803D;border:1px solid #BBF7D0;padding:7px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;"
                                    title="Login History">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </button>

                                <?php if (!$isMe && $canEdit): ?>
                                    <?php if (($st['status'] ?? 'active') === 'active'): ?>
                                    <button onclick="toggleStatus(<?= (int)$st['id'] ?>, 'suspended', '<?= htmlspecialchars(addslashes($st['name'])) ?>')"
                                        style="background:#FEF3C7;color:#D97706;border:1px solid #FDE68A;padding:7px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;"
                                        title="Suspend Account">
                                        <i class="fa-solid fa-user-lock"></i>
                                    </button>
                                    <?php else: ?>
                                    <button onclick="toggleStatus(<?= (int)$st['id'] ?>, 'active', '<?= htmlspecialchars(addslashes($st['name'])) ?>')"
                                        style="background:#DCFCE7;color:#15803D;border:1px solid #BBF7D0;padding:7px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;"
                                        title="Activate Account">
                                        <i class="fa-solid fa-user-check"></i>
                                    </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!$isMe && $canDelete): ?>
                                <button onclick="openDeleteModal(<?= (int)$st['id'] ?>, '<?= htmlspecialchars(addslashes($st['name'])) ?>')"
                                    style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:7px 12px;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;"
                                    title="Delete Account">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ADD / EDIT STAFF MODAL
═══════════════════════════════════════════════════════════ -->
<div id="staffModal" class="modal-overlay module-clean-modal">
    <div class="modal-box" style="max-width:600px;">
        <div style="background:linear-gradient(135deg,#0F172A,#1E3A8A);color:#FFF;padding:22px 28px;display:flex;justify-content:space-between;align-items:center;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:18px;color:#38BDF8;">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div>
                    <h3 id="modalTitle" style="margin:0;font-size:17px;font-weight:800;">Add New Staff Member</h3>
                    <p id="modalSubtitle" style="margin:2px 0 0;font-size:12px;color:#CBD5E1;">Create portal account credentials</p>
                </div>
            </div>
            <button onclick="closeStaffModal()" style="background:rgba(255,255,255,.1);border:none;color:#FFF;width:32px;height:32px;border-radius:50%;font-size:16px;cursor:pointer;">&times;</button>
        </div>

        <form id="staffForm" onsubmit="submitStaffForm(event)" style="padding:24px;">
            <input type="hidden" id="staffId" name="id" value="0">

            <div class="form-grid-2" style="margin-bottom:16px;">
                <div>
                    <label class="form-label">Full Name *</label>
                    <input type="text" id="staffName" name="name" class="form-input" required placeholder="e.g. Ahmad Razak">
                </div>
                <div>
                    <label class="form-label">Employee ID</label>
                    <input type="text" id="staffEmployeeId" name="employee_id" class="form-input" placeholder="e.g. TBBA-001">
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label class="form-label">Email Address (Login ID) *</label>
                <input type="email" id="staffEmail" name="email" class="form-input" required placeholder="ahmad@tbba.com">
            </div>

            <div class="form-grid-2" style="margin-bottom:16px;">
                <div>
                    <label class="form-label">Portal Role *</label>
                    <select id="staffRole" name="role" class="form-input">
                        <?php foreach (($assignableRoles ?? ($roles ?? [])) as $r):
                            // Strict check: Only Super Administrator can see or assign the super_admin role
                            if ($r['name'] === 'super_admin' && !Auth::isSuperAdmin()) continue;
                        ?>
                        <option value="<?= htmlspecialchars($r['name']) ?>"><?= htmlspecialchars($r['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Account Status</label>
                    <select id="staffStatus" name="status" class="form-input">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label class="form-label">Job Position *</label>
                <select id="staffPosition" name="position_id" class="form-input" required>
                    <option value="">— Select Position —</option>
                    <?php foreach (($positions ?? []) as $p): ?>
                    <option value="<?= $p['id'] ?>"
                            data-department-id="<?= htmlspecialchars((string)($p['department_id'] ?? '')) ?>"
                            data-position-title="<?= htmlspecialchars($p['title']) ?>">
                        <?= htmlspecialchars($p['title']) ?> (<?= htmlspecialchars($p['department_name'] ?: 'No Dept') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <!-- Keep hidden position name for backwards compatibility if needed, though we updated controller -->
                <input type="hidden" name="position" id="staffPositionName" value="">
            </div>

            <div class="form-grid-2" style="margin-bottom:16px;">
                <div>
                    <label class="form-label">Department</label>
                    <select id="staffDept" name="department_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach (($departments ?? []) as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Branch / Office</label>
                    <select id="staffBranch" name="branch_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach (($branches ?? []) as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label class="form-label">Phone Number</label>
                <input type="text" id="staffPhone" name="phone" class="form-input" placeholder="e.g. +60123456789">
            </div>

            <div style="margin-bottom:22px;">
                <label class="form-label" id="passwordLabel">Password *</label>
                <input type="password" id="staffPassword" name="password" class="form-input" minlength="6" placeholder="6+ characters" autocomplete="new-password">
                <small style="display:block;margin-top:5px;color:var(--text-muted);font-size:10px;">Must include uppercase, lowercase, a number and a symbol.</small>
                <span style="font-size:11px;color:#94A3B8;display:none;margin-top:4px;" id="passwordHint">Leave blank to keep current password.</span>
            </div>

            <div style="border-top:1px solid #E2E8F0;padding-top:18px;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" onclick="closeStaffModal()" style="background:#F1F5F9;color:#475569;padding:11px 20px;border-radius:10px;font-weight:700;border:none;cursor:pointer;">Cancel</button>
                <button type="submit" id="staffSubmitBtn" style="background:#2563EB;color:#FFF;padding:11px 22px;border-radius:10px;font-weight:700;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(37,99,235,.3);display:inline-flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-check" id="btnIcon"></i>
                    <span id="btnText">Save Staff Account</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ─── Delete Confirmation Modal ─────────────────────────── -->
<div id="deleteModal" class="modal-overlay module-clean-modal">
    <div class="modal-box" style="max-width:420px;text-align:center;">
        <div style="background:linear-gradient(135deg,#DC2626,#991B1B);color:#FFF;padding:22px 28px;display:flex;justify-content:space-between;align-items:center;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:17px;font-weight:800;">Confirm Deletion</h3>
                    <p style="margin:2px 0 0;font-size:12px;color:#FECACA;">This action cannot be undone</p>
                </div>
            </div>
            <button onclick="closeDeleteModal()" style="background:rgba(255,255,255,.1);border:none;color:#FFF;width:32px;height:32px;border-radius:50%;font-size:16px;cursor:pointer;">&times;</button>
        </div>
        <div style="padding:28px;text-align:center;">
            <p style="font-size:14px;color:#334155;line-height:1.6;margin:0 0 24px;">
                Are you sure you want to permanently delete the account for <strong id="deleteTargetName" style="color:#0F172A;"></strong>?<br>
                <span style="font-size:12px;color:#DC2626;font-weight:600;display:block;margin-top:8px;">All associated data will be removed immediately.</span>
            </p>
            <input type="hidden" id="deleteTargetId" value="">
            <div style="display:flex;justify-content:center;gap:12px;">
                <button onclick="closeDeleteModal()" style="background:#F1F5F9;color:#475569;padding:11px 22px;border-radius:10px;font-weight:700;border:none;cursor:pointer;">Cancel</button>
                <button onclick="confirmDeleteAction()" id="confirmDeleteBtn"
                    style="background:#DC2626;color:#FFF;padding:11px 22px;border-radius:10px;font-weight:700;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(220,38,38,.3);display:inline-flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-trash" id="deleteBtnIcon"></i>
                    <span id="deleteBtnText">Yes, Delete Account</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ─── Login History Modal ───────────────────────────────── -->
<!-- ─── Login History Modal ───────────────────────────────── -->
<div id="loginHistoryModal"
     class="modal-overlay module-clean-modal"
     onclick="if(event.target === this) closeLoginHistoryModal()">

    <div class="modal-box login-history-box"
         style="
            max-width:580px;
            width:calc(100% - 32px);
            max-height:85vh;
            position:relative;
            background:#FFFFFF;
            border-radius:16px;
            overflow:hidden;
            box-shadow:0 25px 50px rgba(15,23,42,.25);
         ">

        <!-- CLOSE BUTTON -->
        <button
            type="button"
            onclick="closeLoginHistoryModal()"
            aria-label="Close Login History"
            title="Close"
            class="login-history-close"
            style="
                position:absolute;
                top:14px;
                right:14px;
                z-index:100002;
                width:34px;
                height:34px;
                display:flex;
                align-items:center;
                justify-content:center;
                border:none;
                border-radius:50%;
                background:#F1F5F9;
                color:#475569;
                font-size:22px;
                font-weight:400;
                line-height:1;
                cursor:pointer;
                box-shadow:0 1px 3px rgba(0,0,0,.08);
            ">
            &times;
        </button>

        <!-- HEADER -->
        <div style="
            padding:22px 60px 18px 28px;
            border-bottom:1px solid #E2E8F0;
            background:#FFFFFF;
        ">
            <h3 id="loginHistoryTitle"
                style="
                    margin:0;
                    font-size:17px;
                    font-weight:800;
                    color:#0F172A;
                    display:flex;
                    align-items:center;
                    gap:8px;
                ">
                <i class="fa-solid fa-clock-rotate-left"
                   style="color:#0EA5E9;"></i>
                Login History
            </h3>
        </div>

        <!-- CONTENT -->
        <div id="loginHistoryModalContent"
             style="
                padding:24px;
                max-height:65vh;
                overflow-y:auto;
                background:#FFFFFF;
             ">
            <div style="
                text-align:center;
                color:#94A3B8;
                padding:30px;
            ">
                Loading...
            </div>
        </div>

    </div>
</div>
<script>
// ─── Modal helpers ────────────────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('staffId').value = '0';
    document.getElementById('staffName').value = '';
    document.getElementById('staffEmail').value = '';
    document.getElementById('staffPassword').value = '';
    document.getElementById('staffRole').value = 'staff';
    document.getElementById('staffStatus').value = 'active';
    document.getElementById('staffPosition').value = '';
    document.getElementById('staffDept').value = '';
    syncStaffDepartmentFromPosition();
    document.getElementById('staffBranch').value = '';
    document.getElementById('staffPhone').value = '';
    document.getElementById('staffEmployeeId').value = '';
    document.getElementById('modalTitle').textContent = 'Add New Staff Member';
    document.getElementById('modalSubtitle').textContent = 'Create portal account credentials';
    document.getElementById('passwordLabel').textContent = 'Password *';
    document.getElementById('staffPassword').required = true;
    document.getElementById('passwordHint').style.display = 'none';
    document.getElementById('btnText').textContent = 'Add Staff Member';
    App.openModal('staffModal');
}

async function openEditModal(id) {
    try {
        const res = await fetch(`index.php?action=get_staff&id=${id}`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const result = await res.json();
        if (result.status !== 'success') { App.showToast('error', result.message); return; }
        const u = result.data;
        document.getElementById('staffId').value = u.id;
        document.getElementById('staffName').value = u.name || '';
        document.getElementById('staffEmail').value = u.email || '';
        document.getElementById('staffRole').value = u.role || 'staff';
        document.getElementById('staffStatus').value = u.status || 'active';
        document.getElementById('staffPosition').value = u.position_id || '';
        document.getElementById('staffDept').value = u.department_id || '';
        syncStaffDepartmentFromPosition();
        document.getElementById('staffBranch').value = u.branch_id || '';
        document.getElementById('staffPhone').value = u.phone || '';
        document.getElementById('staffEmployeeId').value = u.employee_id || '';
        document.getElementById('staffPassword').value = '';
        document.getElementById('modalTitle').textContent = 'Edit Staff Profile';
        document.getElementById('modalSubtitle').textContent = `Updating credentials for ${u.name}`;
        document.getElementById('passwordLabel').textContent = 'New Password (Optional)';
        document.getElementById('staffPassword').required = false;
        document.getElementById('passwordHint').style.display = 'block';
        document.getElementById('btnText').textContent = 'Update Staff Profile';
        App.openModal('staffModal');
    } catch(e) { App.showToast('error', 'Failed to load staff details.'); }
}

function closeStaffModal() { App.closeModal('staffModal'); }

function syncStaffDepartmentFromPosition() {
    const positionSelect = document.getElementById('staffPosition');
    const departmentSelect = document.getElementById('staffDept');
    const positionName = document.getElementById('staffPositionName');
    const selected = positionSelect.options[positionSelect.selectedIndex];
    const hasPosition = Boolean(positionSelect.value);

    if (hasPosition && selected) {
        departmentSelect.value = selected.dataset.departmentId || '';
        positionName.value = selected.dataset.positionTitle || '';
    } else {
        positionName.value = '';
    }
    departmentSelect.disabled = hasPosition;
    departmentSelect.title = hasPosition
        ? 'Department is inherited from the selected position.'
        : 'Select a department when no position is assigned.';
}

document.getElementById('staffPosition')?.addEventListener('change', syncStaffDepartmentFromPosition);

async function submitStaffForm(e) {
    e.preventDefault();
    const btn  = document.getElementById('staffSubmitBtn');
    const icon = document.getElementById('btnIcon');
    const text = document.getElementById('btnText');
    const id   = document.getElementById('staffId').value;
    const isEdit = (id !== '0' && id !== '');
    const url  = isEdit ? 'index.php?action=update_staff' : 'index.php?action=add_staff';

    btn.disabled = true;
    icon.className = 'fa-solid fa-spinner fa-spin';
    text.textContent = isEdit ? 'Updating...' : 'Saving...';

    const fd = new FormData(document.getElementById('staffForm'));
    try {
        const res = await fetch(url, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const result = await res.json();
        if (result.status === 'success') {
            App.showToast('success', result.message);
            closeStaffModal();
            setTimeout(() => location.reload(), 800);
        } else {
            App.showToast('error', result.message);
            btn.disabled = false;
            icon.className = 'fa-solid fa-check';
            text.textContent = isEdit ? 'Update Staff Profile' : 'Add Staff Member';
        }
    } catch(e) {
        App.showToast('error', 'Network error during submission.');
        btn.disabled = false;
        icon.className = 'fa-solid fa-check';
        text.textContent = isEdit ? 'Update Staff Profile' : 'Add Staff Member';
    }
}

function openDeleteModal(id, name) {
    document.getElementById('deleteTargetId').value = id;
    document.getElementById('deleteTargetName').textContent = name;
    App.openModal('deleteModal');
}
function closeDeleteModal() { App.closeModal('deleteModal'); }

async function confirmDeleteAction() {
    const id  = document.getElementById('deleteTargetId').value;
    const btn = document.getElementById('confirmDeleteBtn');
    const ico = document.getElementById('deleteBtnIcon');
    const txt = document.getElementById('deleteBtnText');
    btn.disabled = true; ico.className = 'fa-solid fa-spinner fa-spin'; txt.textContent = 'Deleting...';
    const fd = new FormData(); fd.append('id', id); fd.append('csrf_token', '<?= Helper::csrfToken() ?>');
    try {
        const res = await fetch('index.php?action=delete_staff', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const result = await res.json();
        if (result.status === 'success') {
            App.showToast('success', result.message);
            closeDeleteModal();
            setTimeout(() => location.reload(), 800);
        } else {
            App.showToast('error', result.message);
            btn.disabled = false; ico.className = 'fa-solid fa-trash'; txt.textContent = 'Yes, Delete Account';
        }
    } catch(e) {
        App.showToast('error', 'Network error.');
        btn.disabled = false; ico.className = 'fa-solid fa-trash'; txt.textContent = 'Yes, Delete Account';
    }
}

async function toggleStatus(id, status, name) {
    const action = status === 'suspended' ? 'suspend' : 'activate';
    const confirmed = await App.confirm(
        `${status === 'suspended' ? 'Suspend' : 'Activate'} Account`,
        `Are you sure you want to ${action} the account for "${name}"?`,
        `Yes, ${status === 'suspended' ? 'Suspend' : 'Activate'}`
    );
    if (!confirmed) return;
    const fd = new FormData(); fd.append('id', id); fd.append('status', status); fd.append('csrf_token', '<?= Helper::csrfToken() ?>');
    try {
        const res = await fetch('index.php?action=toggle_staff_status', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const result = await res.json();
        if (result.status === 'success') {
            App.showToast('success', result.message);
            setTimeout(() => location.reload(), 600);
        } else { App.showToast('error', result.message); }
    } catch(e) { App.showToast('error', 'Network error.'); }
}

async function viewLoginHistory(id, name) {
    document.getElementById('loginHistoryTitle').innerHTML = `<i class="fa-solid fa-clock-rotate-left" style="margin-right:8px;color:#38BDF8;"></i>Login History — ${name}`;
    document.getElementById('loginHistoryModalContent').innerHTML = '<div style="text-align:center;color:#94A3B8;padding:30px;"><i class="fa-solid fa-spinner fa-spin" style="font-size:24px;"></i></div>';
    App.openModal('loginHistoryModal');

    try {
        const res = await fetch(`index.php?action=get_login_history&user_id=${id}`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
        const result = await res.json();
        if (result.status !== 'success') { App.showToast('error', result.message); return; }
        const history = result.data.history;
        if (!history.length) {
            document.getElementById('loginHistoryModalContent').innerHTML = '<p style="text-align:center;color:#94A3B8;padding:30px;">No login history found.</p>';
            return;
        }
        let html = '<table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:#F8FAFC;">';
        html += '<th style="padding:10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#64748B;font-weight:700;">Date & Time</th>';
        html += '<th style="padding:10px;text-align:center;border-bottom:1px solid #E2E8F0;color:#64748B;font-weight:700;">Method</th>';
        html += '<th style="padding:10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#64748B;font-weight:700;">IP Address</th>';
        html += '<th style="padding:10px;text-align:center;border-bottom:1px solid #E2E8F0;color:#64748B;font-weight:700;">Status</th>';
        html += '</tr></thead><tbody>';
        history.forEach(row => {
            const statusBadge = row.status === 'success'
                ? '<span style="background:#DCFCE7;color:#15803D;padding:2px 8px;border-radius:50px;font-size:10px;font-weight:700;">Success</span>'
                : '<span style="background:#FEF2F2;color:#DC2626;padding:2px 8px;border-radius:50px;font-size:10px;font-weight:700;">Failed</span>';
            html += `<tr style="border-bottom:1px solid #F1F5F9;">
                <td style="padding:10px;font-weight:600;color:#0F172A;">${row.created_at}</td>
                <td style="padding:10px;text-align:center;color:#475569;">${row.method}</td>
                <td style="padding:10px;font-family:monospace;color:#334155;">${row.ip_address||'—'}</td>
                <td style="padding:10px;text-align:center;">${statusBadge}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        document.getElementById('loginHistoryModalContent').innerHTML = html;
    } catch(e) { App.showToast('error', 'Failed to load login history.'); }
}

function closeLoginHistoryModal() { App.closeModal('loginHistoryModal'); }

// ─── Role filter ──────────────────────────────────────────────────────────────
function filterByRole(role) {
    document.querySelectorAll('.staff-row').forEach(row => {
        row.style.display = (!role || row.dataset.role === role) ? '' : 'none';
    });
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
