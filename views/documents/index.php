<?php
/**
 * Document Center & SOP Repository View
 * Company: The Bridge Business Alliance (TBBA)
 */
require_once __DIR__ . '/../../core/Auth.php';
Auth::requireLogin();
$currentUser = Auth::user();
$pageTitle = "Document Center & SOP Repository";
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';

if (!isset($selectedCategory)) $selectedCategory = 'ALL';
if (!isset($documents)) $documents = [];
?>

<!-- Banner Header Korporat -->
<div class="card" style="margin-bottom: 28px; background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 100%); color: #FFFFFF; border: none; border-radius: 20px; padding: 32px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
    <div style="flex: 1; min-width: 280px;">
        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); color: #38BDF8; font-size: 11px; font-weight: 700; padding: 5px 14px; border-radius: 50px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
            <i class="fa-solid fa-folder-open"></i> CORPORATE REPOSITORY
        </div>
        <h1 style="font-size: 26px; font-weight: 800; color: #FFFFFF; margin: 0 0 8px; display: flex; align-items: center; gap: 10px;">
            <span>Document Center & SOP Repository</span>
        </h1>
        <p style="font-size: 14px; color: #CBD5E1; margin: 0; max-width: 650px; line-height: 1.6;">
            Official corporate repository for Company Policies, Standard Operating Procedures (SOPs), HR forms, and templates.
        </p>
    </div>

    <?php if (Auth::hasPermission('documents', 'create')): ?>
    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
        <button onclick="openUploadModal()" class="btn btn-primary" style="background: #2563EB; color: #FFF; padding: 12px 22px; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(37,99,235,0.3); white-space: nowrap;">
            <i class="fa-solid fa-cloud-arrow-up"></i> <span>Upload Official Document</span>
        </button>
    </div>
    <?php endif; ?>
</div>

        <!-- Alert Notifications -->
        <?php if (isset($_GET['success'])): ?>
            <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check" style="font-size: 18px;"></i>
                    <span style="font-size: 14px; font-weight: 600;">
                        <?= $_GET['success'] === 'uploaded' ? "Official document uploaded successfully and logged into Audit Trail." : "Document deleted successfully." ?>
                    </span>
                </div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #065F46; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px;"></i>
                    <span style="font-size: 14px; font-weight: 600;">
                        <?php
                            $errs = [
                                'missing_fields' => "Please provide both document title and file upload.",
                                'invalid_type'   => "Invalid file format. Only PDF, DOCX, XLSX, and ZIP files are allowed.",
                                'upload_failed'  => "File upload failed due to server permissions or size limit.",
                                'unauthorized'   => "Only administrators are authorized to upload or delete documents."
                            ];
                            echo $errs[$_GET['error']] ?? "An unexpected error occurred.";
                        ?>
                    </span>
                </div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #991B1B; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>

        <!-- Category Filter Pills & Search Bar -->
        <div class="doc-filter-box" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 18px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
            <div class="doc-categories-list" style="display: flex; gap: 8px; flex-wrap: wrap; flex: 1 1 auto; max-width: 100%;">
                <?php
                $cats = [
                    'ALL' => ['All Documents', 'fa-layer-group'],
                    'HR & Policies' => ['HR & Policies', 'fa-user-shield'],
                    'Operational SOPs' => ['Operational SOPs', 'fa-gears'],
                    'Forms & Templates' => ['Forms & Templates', 'fa-file-invoice'],
                    'General Corporate' => ['General Corporate', 'fa-building']
                ];
                foreach ($cats as $key => $catInfo):
                    $isActive = ($selectedCategory === $key);
                ?>
                    <a href="index.php?page=documents&category=<?= urlencode($key) ?>" 
                       class="doc-category-pill" style="padding: 8px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease; white-space: nowrap; max-width: 100%; <?= $isActive ? 'background: #2563EB; color: #FFF; box-shadow: 0 4px 10px rgba(37,99,235,0.25); border: 1px solid #2563EB;' : 'background: #F8FAFC; color: #475569; border: 1px solid #CBD5E1;' ?>">
                        <i class="fa-solid <?= $catInfo[1] ?>"></i> <span><?= $catInfo[0] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Search Bar -->
            <div class="doc-search-wrapper" style="position: relative; min-width: 240px; flex: 1 1 280px; max-width: 100%;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94A3B8;"></i>
                <input type="text" id="docSearchInput" onkeyup="filterDocuments()" placeholder="Search title or description..." 
                       class="form-control" style="width: 100%; padding: 10px 14px 10px 40px; border-radius: 10px; border: 1px solid #CBD5E1; font-size: 13px; outline: none; background: #F8FAFC; color: #0F172A; box-sizing: border-box;">
            </div>
        </div>

        <!-- Document Cards Grid -->
        <?php if (empty($documents)): ?>
            <div style="background: #FFFFFF; border: 1px dashed #CBD5E1; border-radius: 16px; padding: 60px 20px; text-align: center;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: #EFF6FF; color: #3B82F6; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 16px;">
                    <i class="fa-solid fa-folder-closed"></i>
                </div>
                <h3 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 6px;">No Corporate Documents Found</h3>
                <p style="font-size: 14px; color: #64748B; margin: 0 0 20px; max-width: 420px; margin-left: auto; margin-right: auto;">
                    <?= $selectedCategory === 'ALL' ? "No documents have been uploaded yet. Administrators can upload official guidelines and SOPs here." : "No documents available under the '{$selectedCategory}' category." ?>
                </p>
                <?php if (Auth::hasPermission('documents', 'create')): ?>
                <button onclick="openUploadModal()" class="btn btn-primary" style="background: #2563EB; color: #FFF; padding: 10px 20px; border-radius: 10px; font-weight: 600; border: none; cursor: pointer;">
                    <i class="fa-solid fa-plus"></i> Upload First Document
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="doc-grid" id="docCardsContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; width: 100%; box-sizing: border-box;">
                <?php foreach ($documents as $doc): 
                    // Format file icon & size
                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    $iconClass = 'fa-file-lines';
                    $iconColor = '#64748B';
                    $iconBg = '#F1F5F9';

                    if ($ext === 'pdf') {
                        $iconClass = 'fa-file-pdf';
                        $iconColor = '#DC2626';
                        $iconBg = '#FEF2F2';
                    } elseif (in_array($ext, ['doc', 'docx'])) {
                        $iconClass = 'fa-file-word';
                        $iconColor = '#2563EB';
                        $iconBg = '#EFF6FF';
                    } elseif (in_array($ext, ['xls', 'xlsx'])) {
                        $iconClass = 'fa-file-excel';
                        $iconColor = '#059669';
                        $iconBg = '#ECFDF5';
                    } elseif ($ext === 'zip') {
                        $iconClass = 'fa-file-zipper';
                        $iconColor = '#D97706';
                        $iconBg = '#FFFBEB';
                    }

                    // Format size
                    $sizeBytes = intval($doc['file_size']);
                    if ($sizeBytes >= 1048576) {
                        $formattedSize = round($sizeBytes / 1048576, 1) . ' MB';
                    } elseif ($sizeBytes >= 1024) {
                        $formattedSize = round($sizeBytes / 1024) . ' KB';
                    } else {
                        $formattedSize = $sizeBytes . ' Bytes';
                    }

                    // Badge styles
                    $catColors = [
                        'HR & Policies' => ['bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#FDE68A'],
                        'Operational SOPs' => ['bg' => '#E0E7FF', 'text' => '#3730A3', 'border' => '#C7D2FE'],
                        'Forms & Templates' => ['bg' => '#ECFDF5', 'text' => '#065F46', 'border' => '#A7F3D0'],
                        'General Corporate' => ['bg' => '#F1F5F9', 'text' => '#334155', 'border' => '#E2E8F0']
                    ];
                    $catStyle = $catColors[$doc['category']] ?? $catColors['General Corporate'];
                ?>
                <div class="doc-card" data-title="<?= htmlspecialchars(strtolower($doc['title'])) ?>" data-desc="<?= htmlspecialchars(strtolower($doc['description'])) ?>" 
                     style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 22px; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(0,0,0,0.01);">
                    
                    <div>
                        <!-- Top Header -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                            <div style="width: 48px; height: 48px; border-radius: 12px; background: <?= $iconBg ?>; color: <?= $iconColor ?>; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">
                                <i class="fa-solid <?= $iconClass ?>"></i>
                            </div>
                            <span style="background: <?= $catStyle['bg'] ?>; color: <?= $catStyle['text'] ?>; border: 1px solid <?= $catStyle['border'] ?>; padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; letter-spacing: 0.3px;">
                                <?= htmlspecialchars($doc['category']) ?>
                            </span>
                        </div>

                        <!-- Title & Description -->
                        <h4 style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0 0 8px; line-height: 1.4;">
                            <?= htmlspecialchars($doc['title']) ?>
                        </h4>
                        <p style="font-size: 13px; color: #64748B; line-height: 1.6; margin: 0 0 16px; min-height: 40px;">
                            <?= !empty($doc['description']) ? htmlspecialchars($doc['description']) : '<em>No additional description provided for this corporate document.</em>' ?>
                        </p>
                    </div>

                    <!-- Footer & Actions -->
                    <div>
                        <div class="doc-meta-bar" style="border-top: 1px solid #F1F5F9; padding-top: 14px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #94A3B8;">
                            <span style="display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-hard-drive"></i> <?= $formattedSize ?></span>
                            <span style="display: inline-flex; align-items: center; gap: 4px;"><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($doc['created_at'])) ?></span>
                            <span style="display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-user-check"></i> <?= htmlspecialchars($doc['uploader_name'] ?? 'Admin') ?></span>
                        </div>

                        <div class="doc-actions-bar" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; width: 100%; box-sizing: border-box;">
                            <a href="index.php?action=download_attachment&amp;type=document&amp;id=<?= (int)$doc['id'] ?>" target="_blank" rel="noopener" title="View Document" 
                               class="btn btn-view-doc" style="flex: 1 1 auto; min-width: 80px; background: #2563EB; color: #FFFFFF; border: 1px solid #2563EB; padding: 10px 12px; border-radius: 10px; font-size: 13px; font-weight: 700; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease; box-sizing: border-box; white-space: nowrap;">
                                <i class="fa-solid fa-eye"></i> <span>View</span>
                            </a>

                            <a href="index.php?action=download_attachment&amp;type=document&amp;id=<?= (int)$doc['id'] ?>&amp;download=1" target="_blank" rel="noopener" title="Download Document" 
                               class="btn btn-dl-doc" style="flex: 1 1 auto; min-width: 80px; background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; padding: 10px 12px; border-radius: 10px; font-size: 13px; font-weight: 700; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease; box-sizing: border-box; white-space: nowrap;">
                                <i class="fa-solid fa-download"></i> <span>Download</span>
                            </a>

                            <?php if (Auth::hasPermission('documents', 'create')): ?>
                            <button type="button" title="Delete Document" onclick="deleteDocument(<?= $doc['id'] ?>, '<?= htmlspecialchars(addslashes($doc['title'])) ?>')" class="btn-del-doc" style="background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; padding: 10px 14px; border-radius: 10px; font-size: 13px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; box-sizing: border-box;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

<!-- Upload Modal (Admin Only) -->
<?php if (Auth::hasPermission('documents', 'create')): ?>
<div id="uploadDocModal" class="modal-overlay" style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box;">
    <div class="modal-box" style="background: #FFFFFF; border-radius: 20px; width: 100%; max-width: 520px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: modalFadeIn 0.25s ease; margin: auto; box-sizing: border-box;">
        <!-- Modal Header -->
        <div class="modal-header" style="background: #1E3A8A; color: #FFFFFF; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; box-sizing: border-box;">
            <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <div style="min-width: 0;">
                    <h3 style="font-size: 16px; font-weight: 700; margin: 0; color: #FFFFFF; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Upload Official Document</h3>
                    <span style="font-size: 12px; color: #93C5FD; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Add SOP, Policy, or HR form to repository</span>
                </div>
            </div>
            <button type="button" onclick="closeUploadModal()" style="background: none; border: none; color: #93C5FD; font-size: 20px; cursor: pointer; padding: 4px; flex-shrink: 0;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Modal Form -->
        <form action="index.php?page=documents_upload" method="POST" enctype="multipart/form-data" style="padding: 24px; overflow-y: auto; flex: 1 1 auto; box-sizing: border-box;" id="uploadDocForm">
            <input type="hidden" name="csrf_token" id="csrf_token_doc" value="<?= Helper::csrfToken() ?>">
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 13px; font-weight: 700; color: #0F172A; margin-bottom: 6px;">Document Title <span style="color: #DC2626;">*</span></label>
                <input type="text" name="title" required placeholder="e.g. Employee HR Handbook & Guidelines 2026" 
                       class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid #CBD5E1; font-size: 13px; outline: none; background: #F8FAFC; color: #0F172A; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 13px; font-weight: 700; color: #0F172A; margin-bottom: 6px;">Category <span style="color: #DC2626;">*</span></label>
                <select name="category" required class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid #CBD5E1; font-size: 13px; outline: none; background: #F8FAFC; color: #0F172A; cursor: pointer; box-sizing: border-box;">
                    <option value="HR & Policies">HR & Policies</option>
                    <option value="Operational SOPs">Operational SOPs</option>
                    <option value="Forms & Templates">Forms & Templates</option>
                    <option value="General Corporate" selected>General Corporate</option>
                </select>
            </div>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 13px; font-weight: 700; color: #0F172A; margin-bottom: 6px;">Brief Description (Optional)</label>
                <textarea name="description" rows="3" placeholder="Provide a short summary of what this document covers..." 
                          class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid #CBD5E1; font-size: 13px; outline: none; background: #F8FAFC; color: #0F172A; resize: vertical; box-sizing: border-box;"></textarea>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 13px; font-weight: 700; color: #0F172A; margin-bottom: 6px;">File Attachment <span style="color: #DC2626;">*</span></label>
                <div style="border: 2px dashed #94A3B8; border-radius: 12px; padding: 20px; text-align: center; background: #F8FAFC; position: relative; box-sizing: border-box;">
                    <i class="fa-solid fa-file-arrow-up" style="font-size: 28px; color: #2563EB; margin-bottom: 8px;"></i>
                    <div style="font-size: 13px; font-weight: 600; color: #334155;">Select official document file to upload</div>
                    <div style="font-size: 11px; color: #64748B; margin-top: 4px;">Supported: PDF, DOCX, XLSX, ZIP (Max size: 25 MB)</div>
                    <input type="file" name="document_file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.ppt,.pptx" 
                           style="margin-top: 12px; font-size: 12px; width: 100%; box-sizing: border-box;">
                </div>
            </div>

            <!-- Buttons -->
            <div style="display: flex; gap: 12px; justify-content: flex-end; flex-wrap: wrap; border-top: 1px solid #E2E8F0; padding-top: 18px; box-sizing: border-box;">
                <button type="button" onclick="closeUploadModal()" class="btn btn-secondary" style="background: #F1F5F9; color: #475569; border: none; padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; flex: 1 1 auto; text-align: center;">
                    Cancel
                </button>
                <button type="submit" id="btnSubmitDoc" class="btn btn-primary" style="background: #2563EB; color: #FFFFFF; border: none; padding: 10px 22px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(37,99,235,0.3); flex: 1 1 auto; white-space: nowrap;">
                    <i class="fa-solid fa-cloud-arrow-up"></i> <span>Upload Document</span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function filterDocuments() {
    const query = document.getElementById('docSearchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.doc-card');
    let hasVisible = false;

    cards.forEach(card => {
        const title = card.getAttribute('data-title') || '';
        const desc = card.getAttribute('data-desc') || '';
        if (title.includes(query) || desc.includes(query)) {
            card.style.display = 'flex';
            hasVisible = true;
        } else {
            card.style.display = 'none';
        }
    });
}

function openUploadModal() {
    App.openModal('uploadDocModal');
}

function closeUploadModal() {
    App.closeModal('uploadDocModal');
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('uploadDocModal');
    if (e.target === modal) {
        closeUploadModal();
    }
});
</script>

<style>
@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.doc-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px -5px rgba(0,0,0,0.08) !important;
    border-color: #CBD5E1 !important;
}
</style>

<script src="assets/js/documents.js"></script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
