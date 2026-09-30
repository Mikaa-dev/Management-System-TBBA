<?php
/**
 * Organization & Departments View — TBBA ERP Module 7
 */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<div class="page-content" style="padding: 24px;">
    <!-- Top Bar -->
    <div class="card" style="padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; background: var(--bg-card);">
        <div>
            <h3 style="margin: 0; font-size: 18px; color: var(--text-dark);"><i class="fa-solid fa-sitemap" style="color: #D97706;"></i> Organization Structure & Departments</h3>
            <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">Manage company departments, branch locations, and organizational leadership.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <?php if ($canCreatePosition): ?>
            <button class="btn btn-primary" onclick="openNewPositionModal()" style="padding: 10px 16px; font-weight: 600; border-radius: 8px; background: #059669; color: #fff; border: none; cursor: pointer;">
                <i class="fa-solid fa-plus"></i> Add Position
            </button>
            <?php endif; ?>
            <?php if ($canCreateDepartment): ?>
            <button class="btn btn-primary" onclick="openNewDeptModal()" style="padding: 10px 16px; font-weight: 600; border-radius: 8px; background: #D97706; color: #fff; border: none; cursor: pointer;">
                <i class="fa-solid fa-plus"></i> Add Department
            </button>
            <?php endif; ?>
            <?php if ($canCreateBranch): ?>
            <button class="btn btn-secondary" onclick="openNewBranchModal()" style="padding: 10px 16px; font-weight: 600; border-radius: 8px; border: 1px solid var(--border-color); background: transparent; color: var(--text-dark); cursor: pointer;">
                <i class="fa-solid fa-building"></i> Add Branch
            </button>
            <?php endif; ?>
            <?php if ($canViewOrgChart): ?>
            <a href="index.php?page=org_chart" class="btn btn-secondary" style="padding: 10px 16px; font-weight: 600; border-radius: 8px; border: 1px solid var(--border-color); background: #38BDF8; color: #fff; text-decoration: none;">
                <i class="fa-solid fa-sitemap"></i> View Org Chart
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Departments & Branches Grid -->
    <?php if ($canViewDepartments || $canViewBranches): ?>
    <div style="display: grid; grid-template-columns: <?= $canViewDepartments && $canViewBranches ? '2fr 1fr' : '1fr' ?>; gap: 24px; align-items: start;">
        <!-- Departments List -->
        <?php if ($canViewDepartments): ?>
        <div class="card" style="padding: 20px; border-radius: 12px; background: var(--bg-card); overflow-x: auto;">
            <h4 style="margin: 0 0 16px; font-size: 16px; color: var(--text-dark);"><i class="fa-solid fa-users-rectangle" style="color: #D97706;"></i> Departments Directory</h4>
            <table class="datatable table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                        <th style="padding: 10px;">Dept Name</th>
                        <th style="padding: 10px;">Code</th>
                        <th style="padding: 10px;">Head of Dept</th>
                        <th style="padding: 10px;">Status</th>
                        <th style="padding: 10px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $d): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($d['name']) ?></td>
                        <td style="padding: 10px;"><span style="background:var(--bg-primary); padding:2px 8px; border-radius:4px; font-weight:700; color:#D97706;"><?= htmlspecialchars($d['code'] ?? 'N/A') ?></span></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($d['head_name'] ?: 'Not Assigned') ?></td>
                        <td style="padding: 10px;"><span style="background: <?= $d['status'] === 'active' ? '#DCFCE7' : '#F1F5F9' ?>; color: <?= $d['status'] === 'active' ? '#15803D' : '#64748B' ?>; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; text-transform: uppercase;"><?= $d['status'] ?></span></td>
                        <td style="padding: 10px; text-align: center;">
                            <?php if ($canEditDepartment): ?>
                            <button class="btn btn-secondary btn-sm" onclick='editDept(<?= json_encode($d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;" title="Edit"><i class="fa-solid fa-edit"></i></button>
                            <?php endif; ?>
                            <?php if ($canDeleteDepartment): ?>
                            <button class="btn btn-danger btn-sm" onclick="deleteDept(<?= $d['id'] ?>)" style="padding: 4px 8px; border-radius: 4px; border: none; background: #EF4444; color: #fff; cursor: pointer;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Branches List -->
        <?php if ($canViewBranches): ?>
        <div class="card" style="padding: 20px; border-radius: 12px; background: var(--bg-card); overflow-x: auto;">
            <h4 style="margin: 0 0 16px; font-size: 16px; color: var(--text-dark);"><i class="fa-solid fa-building-flag" style="color: #2563EB;"></i> Branch Locations</h4>
            <table class="datatable table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                        <th style="padding: 10px;">Branch Name</th>
                        <th style="padding: 10px;">Code</th>
                        <th style="padding: 10px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $b): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($b['name']) ?></td>
                        <td style="padding: 10px;"><span style="background:var(--bg-primary); padding:2px 8px; border-radius:4px; font-weight:700; color:#2563EB;"><?= htmlspecialchars($b['code']) ?></span></td>
                        <td style="padding: 10px; text-align: center;">
                            <?php if ($canEditBranch): ?>
                            <button class="btn btn-primary btn-sm" onclick='editBranch(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="padding: 4px 8px; border-radius: 4px; border: none; background: #2563EB; color: #fff; cursor: pointer; margin-right: 4px;" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                            <?php endif; ?>
                            <?php if ($canDeleteBranch && $b['id'] > 1): ?>
                            <button class="btn btn-danger btn-sm" onclick="deleteBranch(<?= $b['id'] ?>)" style="padding: 4px 8px; border-radius: 4px; border: none; background: #EF4444; color: #fff; cursor: pointer;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Positions List (Full width below) -->
    <?php if ($canViewPositions): ?>
    <div class="card" style="padding: 20px; border-radius: 12px; background: var(--bg-card); overflow-x: auto; margin-top: 24px;">
        <h4 style="margin: 0 0 16px; font-size: 16px; color: var(--text-dark);"><i class="fa-solid fa-id-badge" style="color: #059669;"></i> Positions & Hierarchy</h4>
        <table class="datatable table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                    <th style="padding: 10px;">Position Title</th>
                    <th style="padding: 10px;">Department</th>
                    <th style="padding: 10px;">Reports To</th>
                    <th style="padding: 10px;">Level</th>
                    <th style="padding: 10px;">Status</th>
                    <th style="padding: 10px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($positions as $p): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 10px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($p['title']) ?></td>
                    <td style="padding: 10px;"><?= htmlspecialchars($p['department_name'] ?: '-') ?></td>
                    <td style="padding: 10px;"><span style="color:#64748B;"><i class="fa-solid fa-arrow-turn-up fa-rotate-90"></i></span> <?= htmlspecialchars($p['reports_to_title'] ?: 'CEO / Top Level') ?></td>
                    <td style="padding: 10px;"><?= $p['level'] ?></td>
                    <td style="padding: 10px;"><span style="background: <?= $p['status'] === 'active' ? '#DCFCE7' : '#F1F5F9' ?>; color: <?= $p['status'] === 'active' ? '#15803D' : '#64748B' ?>; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; text-transform: uppercase;"><?= $p['status'] ?></span></td>
                    <td style="padding: 10px; text-align: center;">
                        <?php if ($canEditPosition): ?>
                        <button class="btn btn-secondary btn-sm" onclick='editPosition(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;" title="Edit"><i class="fa-solid fa-edit"></i></button>
                        <?php endif; ?>
                        <?php if ($canDeletePosition): ?>
                        <button class="btn btn-danger btn-sm" onclick="deletePosition(<?= $p['id'] ?>)" style="padding: 4px 8px; border-radius: 4px; border: none; background: #EF4444; color: #fff; cursor: pointer;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: New Department -->
<div class="modal-overlay" id="newDeptModal">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="deptModalTitle"><i class="fa-solid fa-sitemap" style="color: #D97706;"></i> Add New Department</h3>
            <button class="modal-close" onclick="App.closeModal('newDeptModal')">&times;</button>
        </div>
        <form id="deptForm" onsubmit="submitDepartment(event)">
            <input type="hidden" name="id" id="deptId" value="0">
            <div class="modal-body" style="padding: 20px;">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Department Name <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="name" id="deptName" required placeholder="e.g. Marketing & Communications" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Code <span style="color: #EF4444;">*</span></label>
                        <input type="text" name="code" id="deptCode" required placeholder="e.g. MKT" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Head of Department</label>
                        <select name="head_user_id" id="deptHead" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                            <option value="">-- None --</option>
                            <?php foreach ($allUsers as $k => $u): $uid = is_array($u) ? $u['id'] : $k; $uname = is_array($u) ? $u['name'] : $u; ?>
                            <option value="<?= $uid ?>"><?= htmlspecialchars($uname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Description</label>
                    <textarea name="description" id="deptDesc" rows="2" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark); resize: vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('newDeptModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitDept" style="padding: 8px 16px; border-radius: 6px; background: #D97706; color: #fff; border: none; font-weight: 600; cursor: pointer;">Save Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: New Branch -->
<div class="modal-overlay" id="newBranchModal">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title" id="branchModalTitle"><i class="fa-solid fa-building" style="color: #2563EB;"></i> Add Branch Location</h3>
            <button class="modal-close" onclick="App.closeModal('newBranchModal')">&times;</button>
        </div>
        <form id="branchForm" onsubmit="submitBranch(event)">
            <input type="hidden" name="id" id="branchId" value="0">
            <div class="modal-body" style="padding: 20px;">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Branch Name <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="name" id="branchName" required placeholder="e.g. Johor Bahru Regional Office" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Branch Code <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="code" id="branchCode" required placeholder="e.g. BR-JB" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">City / Address</label>
                    <input type="text" name="city" id="branchCity" placeholder="e.g. Johor Bahru" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">GPS Latitude</label>
                        <input type="text" name="latitude" id="branchLat" placeholder="e.g. 3.115915" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">GPS Longitude</label>
                        <input type="text" name="longitude" id="branchLng" placeholder="e.g. 101.736689" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('newBranchModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitBranch" style="padding: 8px 16px; border-radius: 6px; background: #2563EB; color: #fff; border: none; font-weight: 600; cursor: pointer;">Save Branch</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: New Position -->
<div class="modal-overlay" id="newPositionModal">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="positionModalTitle"><i class="fa-solid fa-id-badge" style="color: #059669;"></i> Add New Position</h3>
            <button class="modal-close" onclick="App.closeModal('newPositionModal')">&times;</button>
        </div>
        <form id="positionForm" onsubmit="submitPosition(event)">
            <input type="hidden" name="id" id="positionId" value="0">
            <div class="modal-body" style="padding: 20px;">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Position Title <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="title" id="positionTitle" required placeholder="e.g. Sales Executive" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Department</label>
                        <select name="department_id" id="positionDept" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                            <option value="">-- None --</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Reports To (Superior)</label>
                        <select name="reports_to_position_id" id="positionReportsTo" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                            <option value="">-- CEO / Top Level --</option>
                            <?php foreach ($positions as $p): ?>
                            <option value="<?= $p['id'] ?>" data-level="<?= (int)$p['level'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Hierarchy Level</label>
                        <input type="number" id="positionLevel" value="1" min="1" readonly style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: #F1F5F9; color: var(--text-muted); cursor: not-allowed;">
                        <small style="display:block;margin-top:5px;color:var(--text-muted);">Calculated automatically from “Reports To”.</small>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Status</label>
                        <select name="status" id="positionStatus" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('newPositionModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitPosition" style="padding: 8px 16px; border-radius: 6px; background: #059669; color: #fff; border: none; font-weight: 600; cursor: pointer;">Save Position</button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewDeptModal() {
    document.getElementById('deptId').value = '0';
    document.getElementById('deptForm').reset();
    document.getElementById('deptModalTitle').innerHTML = '<i class="fa-solid fa-sitemap" style="color: #D97706; margin-right: 8px;"></i>Add New Department';
    document.getElementById('btnSubmitDept').textContent = 'Save Department';
    App.openModal('newDeptModal');
}

function openNewBranchModal() {
    document.getElementById('branchId').value = '0';
    document.getElementById('branchForm').reset();
    document.getElementById('branchModalTitle').innerHTML = '<i class="fa-solid fa-building" style="color: #2563EB; margin-right: 8px;"></i>Add Branch Location';
    document.getElementById('btnSubmitBranch').textContent = 'Save Branch';
    App.openModal('newBranchModal');
}

async function submitDepartment(e) {
    e.preventDefault();
    const form = document.getElementById('deptForm');
    const formData = new FormData(form);
    const id = document.getElementById('deptId').value;
    const action = (id && id !== '0' && id !== 0) ? 'update_department' : 'add_department';
    const btn = document.getElementById('btnSubmitDept');
    const res = await App.post(`index.php?action=${action}`, formData, btn);
    if (res && res.status === 'success') {
        App.closeModal('newDeptModal');
        setTimeout(() => location.reload(), 800);
    }
}

function editDept(d) {
    document.getElementById('deptId').value = d.id;
    document.getElementById('deptName').value = d.name || '';
    document.getElementById('deptCode').value = d.code || '';
    document.getElementById('deptHead').value = d.head_user_id || '';
    document.getElementById('deptDesc').value = d.description || '';
    document.getElementById('deptModalTitle').innerHTML = '<i class="fa-solid fa-sitemap" style="color: #D97706; margin-right: 8px;"></i>Edit Department';
    document.getElementById('btnSubmitDept').textContent = 'Update Department';
    App.openModal('newDeptModal');
}

async function deleteDept(id) {
    if (!await App.confirm('Delete Department', 'Are you sure you want to delete this department? Staff under it will be unassigned.')) return;
    const formData = new FormData(); formData.append('id', id);
    const res = await App.post('index.php?action=delete_department', formData);
    if (res && res.status === 'success') setTimeout(() => location.reload(), 800);
}

function editBranch(b) {
    document.getElementById('branchId').value = b.id;
    document.getElementById('branchName').value = b.name || '';
    document.getElementById('branchCode').value = b.code || '';
    document.getElementById('branchCity').value = (b.city || b.address || '');
    document.getElementById('branchLat').value = b.latitude || '';
    document.getElementById('branchLng').value = b.longitude || '';
    document.getElementById('branchModalTitle').innerHTML = '<i class="fa-solid fa-building" style="color: #2563EB; margin-right: 8px;"></i>Edit Branch Location';
    document.getElementById('btnSubmitBranch').textContent = 'Update Branch';
    App.openModal('newBranchModal');
}

async function submitBranch(e) {
    e.preventDefault();
    const form = document.getElementById('branchForm');
    const formData = new FormData(form);
    const id = document.getElementById('branchId').value;
    const action = (id && id !== '0' && id !== 0) ? 'update_branch' : 'add_branch';
    const btn = document.getElementById('btnSubmitBranch');
    const res = await App.post(`index.php?action=${action}`, formData, btn);
    if (res && res.status === 'success') {
        App.closeModal('newBranchModal');
        setTimeout(() => location.reload(), 800);
    }
}

async function deleteBranch(id) {
    if (!await App.confirm('Delete Branch', 'Are you sure you want to delete this branch?')) return;
    const formData = new FormData(); formData.append('id', id);
    const res = await App.post('index.php?action=delete_branch', formData);
    if (res && res.status === 'success') setTimeout(() => location.reload(), 800);
}

function openNewPositionModal() {
    document.getElementById('positionId').value = '0';
    document.getElementById('positionForm').reset();
    updateDerivedPositionLevel();
    document.getElementById('positionModalTitle').innerHTML = '<i class="fa-solid fa-id-badge" style="color: #059669; margin-right: 8px;"></i>Add New Position';
    document.getElementById('btnSubmitPosition').textContent = 'Save Position';
    App.openModal('newPositionModal');
}

async function submitPosition(e) {
    e.preventDefault();
    const form = document.getElementById('positionForm');
    const formData = new FormData(form);
    const id = document.getElementById('positionId').value;
    const action = (id && id !== '0' && id !== 0) ? 'update_position' : 'add_position';
    const btn = document.getElementById('btnSubmitPosition');
    const res = await App.post(`index.php?action=${action}`, formData, btn);
    if (res && res.status === 'success') {
        App.closeModal('newPositionModal');
        setTimeout(() => location.reload(), 800);
    }
}

function editPosition(p) {
    document.getElementById('positionId').value = p.id;
    document.getElementById('positionTitle').value = p.title || '';
    document.getElementById('positionDept').value = p.department_id || '';
    document.getElementById('positionReportsTo').value = p.reports_to_position_id || '';
    document.getElementById('positionLevel').value = p.level || 1;
    document.getElementById('positionStatus').value = p.status || 'active';
    document.getElementById('positionModalTitle').innerHTML = '<i class="fa-solid fa-id-badge" style="color: #059669; margin-right: 8px;"></i>Edit Position';
    document.getElementById('btnSubmitPosition').textContent = 'Update Position';
    App.openModal('newPositionModal');
}

function updateDerivedPositionLevel() {
    const reportsTo = document.getElementById('positionReportsTo');
    const selected = reportsTo.options[reportsTo.selectedIndex];
    const parentLevel = selected?.dataset.level ? Number(selected.dataset.level) : 0;
    document.getElementById('positionLevel').value = parentLevel + 1;
}

document.getElementById('positionReportsTo')?.addEventListener('change', updateDerivedPositionLevel);

async function deletePosition(id) {
    if (!await App.confirm('Delete Position', 'Are you sure you want to delete this position?')) return;
    const formData = new FormData(); formData.append('id', id);
    const res = await App.post('index.php?action=delete_position', formData);
    if (res && res.status === 'success') setTimeout(() => location.reload(), 800);
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
