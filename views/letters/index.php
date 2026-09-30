<?php
/**
 * Halaman Pengurusan Surat Masuk & Keluar
 * Syarikat: The Bridge Business Alliance (TBBA)
 */
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helper.php';
require_once __DIR__ . '/../../models/Letter.php';

Auth::requireLogin();
$currentUser = Auth::user();

// Penapisan jenis surat dari URL
$filterType = isset($_GET['type']) ? Helper::clean($_GET['type']) : '';
$letters = Letter::getAll($filterType ?: null, Auth::hasPermission('letters', 'edit') ? null : $currentUser['id']);

$pageTitle = "Corporate Correspondence Management";
$pageJs = "letters.js";
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<!-- Banner Atas -->
<div class="card" style="margin-bottom: 30px; background: linear-gradient(135deg, #1E3A8A 0%, #0F172A 100%); color: #FFFFFF; border: none;">
    <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center; justify-content: space-between;">
        <div>
            <span class="badge" style="background: rgba(255,255,255,0.15); color: #FFFFFF; font-size: 11px; margin-bottom: 8px;">OFFICIAL RECORDS MODULE</span>
            <h2 style="font-size: 24px; font-weight: 800; color: #FFFFFF; margin-bottom: 6px;">
                Corporate Correspondence Registration & Tracking
            </h2>
            <p style="font-size: 13px; color: #CBD5E1; margin: 0;">
                Automated generation of standardized corporate Reference Numbers (e.g.: <code>TBBA/IN/2026/07/001</code>) with PDF document uploads.
            </p>
        </div>

        <div>
            <button class="btn btn-success" onclick="openAddLetterModal('IN')" style="padding: 12px 20px; font-size: 13px;">
                <i class="fa-solid fa-inbox"></i> + Register Incoming Letter
            </button>
            <button class="btn btn-primary" onclick="openAddLetterModal('OUT')" style="padding: 12px 20px; font-size: 13px; background: #3B82F6;">
                <i class="fa-solid fa-paper-plane"></i> + Register Outgoing Letter
            </button>
        </div>
    </div>
</div>

<!-- Navigasi Tap Penapisan (Filter Tabs) -->
<div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
    <a href="index.php?page=letters" class="btn <?= empty($filterType) ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 18px;">
        <i class="fa-solid fa-list"></i> All Letters (<?= count(Letter::getAll(null, Auth::hasPermission('letters', 'edit') ? null : $currentUser['id'])) ?>)
    </a>
    <a href="index.php?page=letters&type=IN" class="btn <?= $filterType === 'IN' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 18px;">
        <i class="fa-solid fa-arrow-down-to-bracket" style="color: #059669;"></i> Incoming (IN)
    </a>
    <a href="index.php?page=letters&type=OUT" class="btn <?= $filterType === 'OUT' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 8px 18px;">
        <i class="fa-solid fa-arrow-up-from-bracket" style="color: #3B82F6;"></i> Outgoing (OUT)
    </a>
</div>

<!-- Jadual Senarai Surat -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-envelope-open-text" style="color: var(--navy-medium);"></i>
            <span>Letter Records List <?= $filterType ? "($filterType)" : '' ?></span>
        </div>
        <span class="badge badge-navy" id="letterCountBadge"><?= count($letters) ?> Records</span>
    </div>

    <div class="table-responsive">
        <table class="table datatable" id="lettersTable">
            <thead>
                <tr>
                    <th>Ref No.</th>
                    <th>Type</th>
                    <th>Letter Title & Details</th>
                    <th>Sender / Recipient</th>
                    <th>Letter Date</th>
                    <th>PDF Copy</th>
                    <th>Action Status</th>
                    <?php if (Auth::hasPermission('letters', 'edit')): ?><th>Status Action</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="lettersTableBody">
                <?php if (empty($letters)): ?>
                <tr id="emptyLetterRow">
                    <td colspan="<?= Auth::hasPermission('letters', 'edit') ? '8' : '7' ?>" style="text-align: center; color: var(--text-muted); padding: 35px;">
                        <i class="fa-regular fa-envelope" style="font-size: 32px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                        No letter records found.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($letters as $l): ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <strong style="color: var(--navy-dark); font-family: monospace; font-size: 13px; background: #F1F5F9; padding: 4px 8px; border-radius: 4px; border: 1px solid #CBD5E1;">
                                <?= htmlspecialchars($l['ref_no']) ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($l['type'] === 'IN'): ?>
                                <span class="badge badge-success"><i class="fa-solid fa-arrow-down"></i> INCOMING</span>
                            <?php else: ?>
                                <span class="badge badge-info" style="background:#DBEAFE; color:#1E40AF;"><i class="fa-solid fa-arrow-up"></i> OUTGOING</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color: var(--text-dark); display: block; font-size: 13px;"><?= htmlspecialchars($l['title']) ?></strong>
                            <?php if (!empty($l['remarks'])): ?>
                            <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">
                                <i class="fa-solid fa-note-sticky" style="color:#94A3B8;"></i> <?= htmlspecialchars($l['remarks']) ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td><span style="color: var(--text-body); font-weight: 500;"><?= htmlspecialchars($l['sender_receiver']) ?></span></td>
                        <td><span style="font-size: 12px; color: var(--text-muted);"><?= Helper::date($l['letter_date'], 'd M Y') ?></span></td>
                        <td>
                            <?php if (!empty($l['file_path'])): ?>
                            <a href="<?= Helper::url('index.php?action=download_attachment&type=letter&id=' . (int)$l['id']) ?>" target="_blank" rel="noopener" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px; color: #DC2626;">
                                <i class="fa-solid fa-file-pdf"></i> View PDF
                            </a>
                            <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 11px;">No PDF</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $statusMap = [
                                'pending' => ['badge-danger', 'Pending Action'],
                                'in_progress' => ['badge-warning', 'In Progress'],
                                'replied' => ['badge-info', 'Replied'],
                                'completed' => ['badge-success', 'Completed / Closed']
                            ];
                            $st = $statusMap[$l['status']] ?? ['badge-navy', $l['status']];
                            ?>
                            <span class="badge <?= $st[0] ?>" id="status-badge-<?= $l['id'] ?>"><?= $st[1] ?></span>
                        </td>
                        <?php if (Auth::hasPermission('letters', 'edit')): ?>
                        <td>
                            <select class="form-control" style="padding: 4px 8px; font-size: 11px; width: 150px;" onchange="updateLetterStatus(<?= $l['id'] ?>, this.value)">
                                <option value="pending" <?= $l['status'] === 'pending' ? 'selected' : '' ?>>Pending Action</option>
                                <option value="in_progress" <?= $l['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="replied" <?= $l['status'] === 'replied' ? 'selected' : '' ?>>Replied</option>
                                <option value="completed" <?= $l['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New letter registration modal (AJAX form) -->
<div class="modal-overlay" id="addLetterModal">
    <div class="modal-box" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-envelope-open-text" style="color: var(--navy-medium);"></i> Official Letter Registration
            </h3>
            <button class="modal-close" onclick="App.closeModal('addLetterModal')">&times;</button>
        </div>

        <form id="addLetterForm" enctype="multipart/form-data" onsubmit="return false;">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
                
                <!-- Pilihan Jenis Surat (IN / OUT) -->
                <div class="form-group" style="background: var(--bg-secondary); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <label class="form-label" style="margin-bottom: 8px;">Letter Category <span style="color: var(--danger);">*</span></label>
                    <div style="display: flex; gap: 20px;">
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 600; color: #059669;">
                            <input type="radio" name="type" value="IN" id="radioTypeIn" checked onchange="fetchNextRefNo('IN')">
                            Incoming Letter (IN)
                        </label>
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 600; color: #2563EB;">
                            <input type="radio" name="type" value="OUT" id="radioTypeOut" onchange="fetchNextRefNo('OUT')">
                            Outgoing Letter (OUT)
                        </label>
                    </div>
                </div>

                <!-- Preview Nombor Rujukan -->
                <div class="form-group">
                    <label class="form-label">Corporate Reference Number (Auto-Generated):</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="text" id="previewRefNo" class="form-control" value="TBBA/IN/2026/..." readonly style="background: #F1F5F9; font-weight: 700; font-family: monospace; color: var(--navy-dark); cursor: not-allowed;">
                        <span class="badge badge-success" style="padding: 8px 12px;"><i class="fa-solid fa-lock"></i> Auto-ID</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Letter Title / Subject <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g.: Invitation to Corporate Dialogue Session 2026" required>
                </div>

                <div style="display: flex; gap: 16px;">
                    <div class="form-group" style="flex: 1.2;">
                        <label class="form-label" id="senderReceiverLabel">Sender Name / Department <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="sender_receiver" id="senderReceiverInput" class="form-control" placeholder="e.g.: Prime Minister's Department" required>
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Letter Date <span style="color: var(--danger);">*</span></label>
                        <input type="date" name="letter_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div style="display: flex; gap: 16px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Initial Action Status</label>
                        <select name="status" class="form-control">
                            <option value="pending">Pending Action</option>
                            <option value="in_progress">In Progress</option>
                            <option value="replied">Replied</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1.2;">
                        <label class="form-label">Upload PDF Copy / Document</label>
                        <input type="file" name="file_path" class="form-control" style="padding: 8px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Remarks / Additional Minutes (Optional)</label>
                    <textarea name="remarks" class="form-control" rows="2" placeholder="e.g.: Please forward to Finance Department for review..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('addLetterModal')">Cancel</button>
                <button type="submit" id="btnSubmitLetter" class="btn btn-primary" style="padding: 10px 24px;">
                    <i class="fa-solid fa-save"></i> Register Official Letter
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const IS_ADMIN = <?= Auth::hasPermission('letters', 'edit') ? 'true' : 'false' ?>;

    // Buka modal dan auto trigger fetch ref no
    function openAddLetterModal(defaultType = 'IN') {
        if (defaultType === 'IN') {
            document.getElementById('radioTypeIn').checked = true;
        } else {
            document.getElementById('radioTypeOut').checked = true;
        }
        fetchNextRefNo(defaultType);
        App.openModal('addLetterModal');
    }

    // Ambil No. Rujukan dari server menerusi AJAX
    async function fetchNextRefNo(type) {
        const label = document.getElementById('senderReceiverLabel');
        const input = document.getElementById('senderReceiverInput');
        if (type === 'IN') {
            label.innerHTML = 'Sender Name / Department <span style="color: var(--danger);">*</span>';
            input.placeholder = 'e.g.: Ministry of Finance';
        } else {
            label.innerHTML = 'Recipient Name / Department <span style="color: var(--danger);">*</span>';
            input.placeholder = 'e.g.: City Council / Business Partner';
        }

        try {
            const res = await fetch(`index.php?action=get_next_ref&type=${type}`);
            const data = await res.json();
            if (data && data.status === 'success') {
                document.getElementById('previewRefNo').value = data.data.ref_no;
            }
        } catch (e) {
            console.error('Failed to fetch ref no:', e);
        }
    }

    // Kemas kini status surat (Admin sahaja)
    async function updateLetterStatus(id, newStatus) {
        const formData = new FormData();
        formData.append('csrf_token', '<?= Helper::csrfToken() ?>');
        formData.append('id', id);
        formData.append('status', newStatus);

        const result = await App.post('index.php?action=update_letter_status', formData);
        if (result && result.status === 'success') {
            const badge = document.getElementById(`status-badge-${id}`);
            if (badge) {
                const statusMap = {
                    'pending': ['badge-danger', 'Pending Action'],
                    'in_progress': ['badge-warning', 'In Progress'],
                    'replied': ['badge-info', 'Replied'],
                    'completed': ['badge-success', 'Completed / Closed']
                };
                const st = statusMap[newStatus] || ['badge-navy', newStatus];
                badge.className = `badge ${st[0]}`;
                badge.textContent = st[1];
            }
        }
    }
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
