<?php
/**
 * Announcements View — TBBA ERP Module 8
 */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<div class="page-content" style="padding: 24px;">
    <!-- Action Bar -->
    <div class="card" style="padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; background: var(--bg-card);">
        <div>
            <h3 style="margin: 0; font-size: 18px; color: var(--text-dark);"><i class="fa-solid fa-bullhorn" style="color: #BE185D;"></i> Company Announcements & News</h3>
            <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">Stay informed with official broadcasts, policy updates, and team notices.</p>
        </div>
        <div>
            <?php if ($canCreate): ?>
            <button class="btn btn-primary" onclick="App.openModal('newAnnouncementModal')" style="padding: 10px 18px; font-weight: 600; border-radius: 8px; background: #BE185D; color: #fff; border: none; cursor: pointer;">
                <i class="fa-solid fa-plus"></i> Publish Announcement
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Announcements List -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php if (empty($announcements)): ?>
        <div class="card" style="padding: 40px; text-align: center; border-radius: 12px; background: var(--bg-card); color: var(--text-muted);">
            <i class="fa-solid fa-newspaper" style="font-size: 40px; color: #CBD5E1; margin-bottom: 12px; display: block;"></i>
            No active announcements published right now.
        </div>
        <?php else: ?>
        <?php foreach ($announcements as $a): ?>
        <div class="card" style="padding: 24px; border-radius: 12px; background: var(--bg-card); border-left: 5px solid <?= $a['is_pinned'] ? '#D97706' : '#BE185D' ?>; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); position: relative;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php if ($a['is_pinned']): ?>
                    <span style="background: #FEF3C7; color: #D97706; padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700;">
                        <i class="fa-solid fa-thumbtack"></i> Pinned Notice
                    </span>
                    <?php endif; ?>
                    <?php
                    $typeColors = ['general' => '#2563EB', 'policy' => '#DC2626', 'event' => '#10B981', 'urgent' => '#EF4444'];
                    $tc = $typeColors[$a['type']] ?? '#64748B';
                    ?>
                    <span style="color: <?= $tc ?>; background: <?= $tc ?>1A; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                        <?= htmlspecialchars($a['type']) ?>
                    </span>
                    <h4 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--text-dark);"><?= htmlspecialchars($a['title']) ?></h4>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <?php if ($canEdit): ?>
                    <button class="btn btn-secondary btn-sm" onclick="togglePinAnnouncement(<?= $a['id'] ?>)" style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer; color: <?= $a['is_pinned'] ? '#D97706' : 'var(--text-muted)' ?>;" title="<?= $a['is_pinned'] ? 'Unpin' : 'Pin' ?>">
                        <i class="fa-solid fa-thumbtack"></i>
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick='editAnnouncement(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer; color: var(--text-dark);" title="Edit">
                        <i class="fa-solid fa-edit"></i>
                    </button>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                    <button class="btn btn-danger btn-sm" onclick="deleteAnnouncement(<?= $a['id'] ?>)" style="padding: 6px 10px; border-radius: 6px; border: none; background: #EF4444; color: #fff; cursor: pointer;" title="Delete">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Content -->
            <div style="font-size: 14px; color: var(--text-dark); line-height: 1.6; white-space: pre-line; margin-bottom: 16px;">
                <?= htmlspecialchars($a['content']) ?>
            </div>

            <!-- Meta Footer -->
            <div style="border-top: 1px solid var(--border-color); padding-top: 12px; display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-muted); flex-wrap: wrap; gap: 8px;">
                <div>
                    <i class="fa-solid fa-user-pen"></i> Posted by <strong style="color: var(--text-dark);"><?= htmlspecialchars($a['author_name']) ?></strong> on <?= date('d M Y, h:i A', strtotime($a['created_at'])) ?>
                </div>
                <div>
                    <?php if ($a['audience'] !== 'all'): ?>
                    <span style="background: var(--bg-primary); padding: 2px 8px; border-radius: 4px; font-weight: 600;">
                        Audience: <?= ucfirst($a['audience']) ?> <?= $a['audience_target'] ? '('.htmlspecialchars($a['audience_target']).')' : '' ?>
                    </span>
                    <?php else: ?>
                    <span><i class="fa-solid fa-globe"></i> Broadcast to All Staff</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: New/Edit Announcement -->
<div class="modal-overlay" id="newAnnouncementModal">
    <div class="modal-box" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" id="annModalTitle"><i class="fa-solid fa-bullhorn" style="color: #BE185D;"></i> Publish Announcement</h3>
            <button class="modal-close" onclick="App.closeModal('newAnnouncementModal')">&times;</button>
        </div>
        <form id="announcementForm" onsubmit="submitAnnouncement(event)">
            <input type="hidden" name="id" id="annId" value="0">
            <div class="modal-body" style="padding: 20px;">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Title / Headline <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="title" id="annTitle" required placeholder="e.g. Annual Townhall Meeting Schedule" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Category</label>
                        <select name="type" id="annType" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                            <option value="general">General Notice</option>
                            <option value="policy">Policy Update</option>
                            <option value="event">Company Event</option>
                            <option value="urgent">Urgent Alert</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Target Audience</label>
                        <select name="audience" id="annAudience" onchange="toggleAudienceTarget()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                            <option value="all">All Staff</option>
                            <option value="department">Specific Department</option>
                            <option value="role">Specific Role</option>
                        </select>
                    </div>
                    <div id="targetDeptDiv" style="display: none;">
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Department</label>
                        <select name="audience_target_dept" id="annTargetDept" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="targetRoleDiv" style="display: none;">
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Role</label>
                        <select name="audience_target_role" id="annTargetRole" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= htmlspecialchars($r['name'] ?? '') ?>"><?= htmlspecialchars($r['display_name'] ?? ucfirst($r['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="audience_target" id="finalAudienceTarget" value="">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-dark); cursor: pointer;">
                        <input type="checkbox" name="is_pinned" id="annPinned" value="1" style="width: 16px; height: 16px;">
                        <span>Pin to Top (Keep at the top of the announcements feed)</span>
                    </label>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Announcement Content <span style="color: #EF4444;">*</span></label>
                    <textarea name="content" id="annContent" rows="6" required placeholder="Type the full message..." style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark); resize: vertical; line-height: 1.5; font-size: 14px;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('newAnnouncementModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitAnn" style="padding: 8px 16px; border-radius: 6px; background: #BE185D; color: #fff; border: none; font-weight: 600; cursor: pointer;">Publish Now</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAudienceTarget() {
    const aud = document.getElementById('annAudience').value;
    document.getElementById('targetDeptDiv').style.display = aud === 'department' ? 'block' : 'none';
    document.getElementById('targetRoleDiv').style.display = aud === 'role' ? 'block' : 'none';
}

async function submitAnnouncement(e) {
    e.preventDefault();
    const aud = document.getElementById('annAudience').value;
    if (aud === 'department') {
        document.getElementById('finalAudienceTarget').value = document.getElementById('annTargetDept').value;
    } else if (aud === 'role') {
        document.getElementById('finalAudienceTarget').value = document.getElementById('annTargetRole').value;
    } else {
        document.getElementById('finalAudienceTarget').value = '';
    }

    const form = document.getElementById('announcementForm');
    const formData = new FormData(form);
    const id = document.getElementById('annId').value;
    const action = id > 0 ? 'update_announcement' : 'add_announcement';
    const btn = document.getElementById('btnSubmitAnn');
    const res = await App.post(`index.php?action=${action}`, formData, btn);
    if (res && res.status === 'success') {
        App.closeModal('newAnnouncementModal');
        setTimeout(() => location.reload(), 800);
    }
}

function editAnnouncement(a) {
    document.getElementById('annId').value = a.id;
    document.getElementById('annTitle').value = a.title;
    document.getElementById('annType').value = a.type;
    document.getElementById('annAudience').value = a.audience || 'all';
    document.getElementById('annPinned').checked = a.is_pinned == 1;
    document.getElementById('annContent').value = a.content;
    document.getElementById('annModalTitle').innerHTML = '<i class="fa-solid fa-edit" style="color:#BE185D;"></i> Edit Announcement';
    toggleAudienceTarget();
    if (a.audience === 'department') document.getElementById('annTargetDept').value = a.audience_target;
    if (a.audience === 'role') document.getElementById('annTargetRole').value = a.audience_target;
    App.openModal('newAnnouncementModal');
}

async function togglePinAnnouncement(id) {
    const formData = new FormData(); formData.append('id', id);
    const res = await App.post('index.php?action=toggle_pin_announcement', formData);
    if (res && res.status === 'success') location.reload();
}

async function deleteAnnouncement(id) {
    if (!await App.confirm('Delete Announcement', 'Are you sure you want to permanently delete this announcement?')) return;
    const formData = new FormData(); formData.append('id', id);
    const res = await App.post('index.php?action=delete_announcement', formData);
    if (res && res.status === 'success') setTimeout(() => location.reload(), 800);
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
