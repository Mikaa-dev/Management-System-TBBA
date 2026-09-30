<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../core/Auth.php';
$currentUser = Auth::user();
$pageTitle = "Inquiries & Letters Inbox";
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>
<style>
        .inq-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color, #E2E8F0);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .inq-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-new { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }
        .status-minit { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
        .status-proses { background: #F3E8FF; color: #7E22CE; border: 1px solid #E9D5FF; }
        .status-complete { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }

        .btn-action-open {
            background: #2563EB !important;
            color: #FFFFFF !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 10px 18px !important;
            border-radius: 10px !important;
            border: none !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            cursor: pointer !important;
            text-decoration: none !important;
            transition: all 0.2s ease !important;
        }
        .btn-action-open:hover {
            background: #1D4ED8 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.45) !important;
        }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: #FFFFFF;
            border-radius: 20px;
            max-width: 720px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalSlide 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .stamp-badge {
            background: linear-gradient(135deg, #059669 0%, #10B981 100%);
            color: #FFFFFF;
            padding: 14px 20px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
            border: 2px solid #A7F3D0;
            margin-top: 14px;
        }
    </style>
            <div class="module-page-header" style="background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 100%); color: #FFFFFF; border-radius: 20px; padding: 32px; margin-bottom: 28px; position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3);">
                <div style="position: absolute; top: -20%; right: -5%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(56,189,248,0.25) 0%, rgba(0,0,0,0) 70%); border-radius: 50%;"></div>
                
                <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <div class="module-page-eyebrow" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); color: #38BDF8; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 50px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-inbox"></i> Executive Mail & Inquiry Management
                        </div>
                        <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 8px; color: #FFFFFF;">Inquiries & Letters Inbox</h1>
                        <p style="color: #CBD5E1; font-size: 14px; max-width: 650px; margin: 0; line-height: 1.6;">
                            All public inquiries and executive letters are centralized in this inbox. Management / Assistant CEO can review, track, and manage official communications.
                        </p>
                    </div>

                    <div class="module-header-meta" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); padding: 16px 24px; border-radius: 16px; backdrop-filter: blur(10px); text-align: center;">
                        <span style="font-size: 11px; color: #94A3B8; text-transform: uppercase; font-weight: 700; display: block;">Executive Role</span>
                        <strong style="font-size: 16px; color: #FFFFFF; display: block; margin-top: 4px;"><?= htmlspecialchars($currentUser['name'] ?? 'Admin') ?></strong>
                        <span style="font-size: 12px; color: #F59E0B; font-weight: 600;">Admin</span>
                    </div>
                </div>
            </div>

            <div class="module-stat-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 28px;">
                <div class="inq-card module-stat-card">
                    <span style="font-size: 12px; color: #64748B; font-weight: 700; text-transform: uppercase;">Total Letters / Inquiries</span>
                    <div style="font-size: 32px; font-weight: 800; color: #0F172A; margin-top: 6px;" id="statTotal"><?= $total ?></div>
                    <span style="font-size: 12px; color: #64748B;"><i class="fa-solid fa-envelope" style="color: #2563EB;"></i> In database</span>
                </div>
                <div class="inq-card module-stat-card" style="border-left: 5px solid #D97706;">
                    <span style="font-size: 12px; color: #64748B; font-weight: 700; text-transform: uppercase;">Unread</span>
                    <div style="font-size: 32px; font-weight: 800; color: #D97706; margin-top: 6px;" id="statUnread"><?= $unreadCount ?></div>
                    <span style="font-size: 12px; color: #D97706;"><i class="fa-solid fa-circle-exclamation"></i> Needs review</span>
                </div>
                <div class="inq-card module-stat-card" style="border-left: 5px solid #059669;">
                    <span style="font-size: 12px; color: #64748B; font-weight: 700; text-transform: uppercase;">Read</span>
                    <div style="font-size: 32px; font-weight: 800; color: #059669; margin-top: 6px;" id="statRead"><?= $readCount ?></div>
                    <span style="font-size: 12px; color: #059669;"><i class="fa-solid fa-check-double"></i> Reviewed</span>
                </div>
            </div>

            <div class="card module-data-card" style="border: 1px solid #E2E8F0; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #FFFFFF;">
                <div class="module-toolbar" style="padding: 20px 24px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 style="font-size: 18px; font-weight: 800; color: #0F172A; margin: 0;">Public Inquiries & Letters List</h3>
                        <span style="font-size: 13px; color: #64748B;">Click 'Read Content' on any letter. Opened letters will be automatically marked as 'Read'.</span>
                    </div>
                    <button type="button" onclick="location.reload()" class="btn" style="background: #F1F5F9; color: #334155; padding: 10px 18px; font-size: 13px; font-weight: 600; border-radius: 10px; border: 1px solid #CBD5E1;">
                        <i class="fa-solid fa-rotate-right"></i> Refresh
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table datatable" id="inquiriesTable" style="margin: 0; width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; text-align: left; font-size: 12px; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">
                                <th style="padding: 18px 24px;">Ref No & Date</th>
                                <th style="padding: 18px 20px;">Sender (Public / Client)</th>
                                <th style="padding: 18px 20px;">Title / Subject</th>
                                <th style="padding: 18px 20px;">Read Status</th>
                                <th style="padding: 18px 24px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inquiries)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 50px; color: #94A3B8;">
                                    <i class="fa-solid fa-folder-open" style="font-size: 42px; margin-bottom: 12px; display: block; color: #CBD5E1;"></i>
                                    No letters or inquiries found in the database at this time.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($inquiries as $inq): ?>
                                    <?php
                                        $statusVal = $inq['status'] ?? 'pending';
                                        $isRead = ($statusVal === 'read' || $statusVal === 'completed' || $statusVal === 'replied');
                                        
                                        $statusClass = $isRead ? 'status-complete' : 'status-new';
                                        $statusLabel = $isRead ? 'Read' : 'Unread';
                                        $statusIcon  = $isRead ? '<i class="fa-solid fa-check-double"></i>' : '<i class="fa-solid fa-circle" style="font-size: 6px;"></i>';

                                        $refDisplay = $inq['ref_no'] ?? ('#INQ-' . str_pad($inq['id'], 4, '0', STR_PAD_LEFT));
                                        $titleDisplay = $inq['title'] ?? ($inq['subject'] ?? 'General Inquiry');
                                        $senderDisplay = $inq['sender_receiver'] ?? ($inq['name'] ?? 'Public Sender');
                                        $emailDisplay = $inq['email_source'] ?? ($inq['email'] ?? '');
                                        $dateDisplay = !empty($inq['created_at']) ? date('d/m/Y h:i A', strtotime($inq['created_at'])) : ($inq['letter_date'] ?? '');
                                    ?>
                                    <tr id="inq-row-<?= $inq['id'] ?>" style="border-bottom: 1px solid #F1F5F9; transition: background 0.2s;" onmouseover="this.style.background='#F8FAFC';" onmouseout="this.style.background='#FFFFFF';">
                                        <td style="padding: 18px 24px;">
                                            <strong style="color: #0F172A; font-size: 14px; font-weight: 800;"><?= htmlspecialchars($refDisplay) ?></strong>
                                            <div style="font-size: 12px; color: #64748B; margin-top: 4px;">
                                                <?= htmlspecialchars($dateDisplay) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 18px 20px;">
                                            <strong style="color: #1E293B; font-size: 14px; display: block;"><?= htmlspecialchars($senderDisplay) ?></strong>
                                            <?php if (!empty($emailDisplay)): ?>
                                                <span style="font-size: 12px; color: #2563EB; font-weight: 500;"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($emailDisplay) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 18px 20px;">
                                            <span style="background: #EFF6FF; color: #1E3A8A; font-weight: 700; font-size: 12px; padding: 6px 12px; border-radius: 8px; display: inline-block;">
                                                <?= htmlspecialchars($titleDisplay) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 18px 20px;" id="status-cell-<?= $inq['id'] ?>">
                                            <span class="status-badge <?= $statusClass ?>" id="badge-<?= $inq['id'] ?>">
                                                <?= $statusIcon ?> <?= htmlspecialchars($statusLabel) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 18px 24px; text-align: right;">
                                            <button type="button" onclick="openInquiryModal(<?= $inq['id'] ?>, <?= $isRead ? 'true' : 'false' ?>)" class="btn-action-open">
                                                <i class="fa-solid fa-book-open"></i> Read Content
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>


<div id="inquiryModal" class="modal-overlay module-clean-modal">
    <div class="modal-content">
        <div style="padding: 24px 28px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; background: #F8FAFC;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: #EFF6FF; color: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="fa-solid fa-envelope-open"></i>
                </div>
                <div>
                    <h3 style="font-size: 18px; font-weight: 800; color: #0F172A; margin: 0;" id="modTitle">Letter & Public Inquiry Details</h3>
                    <span style="font-size: 12px; color: #64748B;" id="modIdDate">#REF-0000 &bull; Date</span>
                </div>
            </div>
            <button type="button" onclick="closeInquiryModal()" style="background: none; border: none; font-size: 22px; color: #94A3B8; cursor: pointer; padding: 4px;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div style="padding: 28px;">
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 16px; padding: 20px; margin-bottom: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <span style="font-size: 11px; color: #64748B; text-transform: uppercase; font-weight: 700; display: block;">Sender / Client</span>
                    <strong style="font-size: 15px; color: #0F172A;" id="modName">-</strong>
                </div>
                <div>
                    <span style="font-size: 11px; color: #64748B; text-transform: uppercase; font-weight: 700; display: block;">Public Email</span>
                    <a href="#" style="font-size: 14px; color: #2563EB; font-weight: 600; text-decoration: none;" id="modEmail">-</a>
                </div>
                <div>
                    <span style="font-size: 11px; color: #64748B; text-transform: uppercase; font-weight: 700; display: block;">Title / Category</span>
                    <span style="background: #E0E7FF; color: #3730A3; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-block; margin-top: 4px;" id="modSubject">-</span>
                </div>
            </div>

            <div style="margin-bottom: 28px;">
                <span style="font-size: 13px; font-weight: 700; color: #0F172A; display: block; margin-bottom: 8px;">Letter Content / Inquiry Message:</span>
                <div style="background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 12px; padding: 20px; font-size: 14px; color: #1E293B; line-height: 1.7; white-space: pre-wrap; max-height: 350px; overflow-y: auto;" id="modMessage">-</div>
            </div>

            <div style="border-top: 1px solid #E2E8F0; padding-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <input type="hidden" id="inqModId">
                <div>
                    <button type="button" id="btnDeleteRecord" onclick="deleteInquiryRecord()" class="btn" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; padding: 12px 20px; border-radius: 50px; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s;">
                        <i class="fa-solid fa-trash"></i> Delete Record
                    </button>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" onclick="openReplyModal()" class="btn" style="background: #3B82F6; color: #FFFFFF; padding: 12px 24px; border-radius: 50px; font-weight: 700; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(59,130,246,0.3);">
                        <i class="fa-solid fa-reply"></i> Reply Letter
                    </button>
                    <button type="button" onclick="closeInquiryModal()" class="btn" style="background: #0F172A; color: #FFFFFF; padding: 12px 28px; border-radius: 50px; font-weight: 700; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(15,23,42,0.3);">
                        <i class="fa-solid fa-check"></i> Close & Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Official In-Portal Letter Reply & Response Modal -->
<div id="replyModal" class="modal-overlay module-clean-modal" style="z-index: 10000;">
    <div class="modal-content" style="max-width: 650px;">
        <div style="padding: 24px 28px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; background: #F8FAFC;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: #EFF6FF; color: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="fa-solid fa-reply-all"></i>
                </div>
                <div>
                    <h3 style="font-size: 18px; font-weight: 800; color: #0F172A; margin: 0;">Official Letter Response / Reply</h3>
                    <span style="font-size: 12px; color: #64748B;">Composing as active database user</span>
                </div>
            </div>
            <button type="button" onclick="closeReplyModal()" style="background: none; border: none; font-size: 22px; color: #94A3B8; cursor: pointer; padding: 4px;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div style="padding: 28px;">
            <div style="margin-bottom: 18px;">
                <label style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; display: block; margin-bottom: 6px;">From (Active Database Account):</label>
                <div style="background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E3A8A; padding: 12px 16px; border-radius: 10px; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-user-shield" style="color: #2563EB;"></i>
                    <span><?= htmlspecialchars($currentUser['name'] ?? 'User') ?> (<?= htmlspecialchars($currentUser['email'] ?? 'No email') ?>)</span>
                    <span style="background: #2563EB; color: white; font-size: 11px; padding: 2px 8px; border-radius: 12px; margin-left: auto; text-transform: uppercase;"><?= htmlspecialchars($currentUser['role'] ?? 'ADMIN') ?></span>
                </div>
            </div>

            <div style="margin-bottom: 18px;">
                <label style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; display: block; margin-bottom: 6px;">To (Recipient Public Email):</label>
                <input type="text" id="replyToEmail" readonly style="width: 100%; padding: 12px 16px; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 14px; color: #334155; font-weight: 600;">
            </div>

            <div style="margin-bottom: 18px;">
                <label style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; display: block; margin-bottom: 6px;">Subject:</label>
                <input type="text" id="replySubject" style="width: 100%; padding: 12px 16px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 14px; color: #0F172A; font-weight: 600;">
            </div>

            <div style="margin-bottom: 24px;">
                <label style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; display: block; margin-bottom: 6px;">Response Message / Official Minute:</label>
                <textarea id="replyMessageBody" rows="6" placeholder="Type your official response here..." style="width: 100%; padding: 14px 16px; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 14px; color: #0F172A; line-height: 1.6; font-family: inherit; resize: vertical;"></textarea>
            </div>

            <div style="border-top: 1px solid #E2E8F0; padding-top: 20px; display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap;">
                <button type="button" onclick="sendViaGmailWeb()" class="btn" style="background: #FFFFFF; color: #EA4335; border: 1px solid #EA4335; padding: 12px 20px; border-radius: 50px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa-brands fa-google"></i> Send via Gmail Web (authuser)
                </button>
                <button type="button" onclick="submitOfficialReply()" class="btn" style="background: #2563EB; color: #FFFFFF; padding: 12px 24px; border-radius: 50px; font-weight: 700; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,0.3); display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-paper-plane"></i> Record Official Reply in Portal
                </button>
            </div>
        </div>
    </div>
</div>

<script>
async function openInquiryModal(id, wasRead) {
    App.openModal('inquiryModal');
    document.getElementById('inqModId').value = id;

    // Reset delete button state
    const btnDel = document.getElementById('btnDeleteRecord');
    if (btnDel) {
        delete btnDel.dataset.confirming;
        btnDel.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Record';
        btnDel.style.background = '#FEE2E2';
        btnDel.style.color = '#DC2626';
        btnDel.disabled = false;
    }

    // 1. Set loading state while waiting for data from backend
    document.getElementById('modName').innerText = 'Loading...';
    document.getElementById('modEmail').innerText = 'Loading...';
    document.getElementById('modSubject').innerText = 'Loading...';
    document.getElementById('modMessage').innerText = 'Fetching data from server...';

    try {
        const res = await fetch(`index.php?action=get_inquiry&id=${id}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        // 2. Check if backend returned HTML instead of JSON
        const contentType = res.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            const textError = await res.text();
            console.error("BACKEND ERROR: Server did not return JSON. It returned:\n", textError);
            throw new Error("System received HTML/Text format instead of JSON. Please check Developer Tools (F12) Console.");
        }

        const data = await res.json();

        if (data.status === 'success') {
            const inq = data.data?.inquiry || data.inquiry || data.data;
            if (!inq) {
                throw new Error("Inquiry object not found in server response.");
            }
            document.getElementById('inqModId').value = inq.id;
            
            const refDisplay = inq.ref_no || (`#INQ-${String(inq.id).padStart(4, '0')}`);
            const dateStr = inq.created_at || inq.letter_date || '';
            document.getElementById('modIdDate').innerText = `${refDisplay} • ${dateStr ? new Date(dateStr).toLocaleString('en-US') : ''}`;
            
            document.getElementById('modName').innerText = inq.sender_receiver || inq.name || 'Public Sender';
            const emailVal = inq.email_source || inq.email || '';
            document.getElementById('modEmail').innerText = emailVal || 'No email';
            document.getElementById('modEmail').href = "#";
            document.getElementById('modEmail').onclick = function(e) {
                e.preventDefault();
                if (emailVal) openReplyModal();
            };
            document.getElementById('modEmail').title = emailVal ? "Click to reply to this letter via portal" : "";
            
            document.getElementById('modSubject').innerText = inq.title || inq.subject || 'General Inquiry';
            document.getElementById('modMessage').innerText = inq.remarks || inq.message || 'No message provided.';

            // Update UI read status in table immediately when letter is opened
            const badge = document.getElementById('badge-' + id);
            if (badge && badge.innerText.includes('Unread')) {
                badge.className = 'status-badge status-complete';
                badge.innerHTML = '<i class="fa-solid fa-check-double"></i> Read';
                
                const unreadEl = document.getElementById('statUnread');
                const readEl   = document.getElementById('statRead');
                if (unreadEl && readEl) {
                    let uVal = parseInt(unreadEl.innerText, 10) || 0;
                    let rVal = parseInt(readEl.innerText, 10) || 0;
                    if (uVal > 0) unreadEl.innerText = uVal - 1;
                    readEl.innerText = rVal + 1;
                }
            }
        } else {
            // Display error in modal without closing
            App.showToast('error', data.message || 'Failed to fetch letter details.');
            document.getElementById('modMessage').innerText = 'Error: ' + (data.message || 'Record not found in database.');
        }
    } catch (err) {
        console.error("Javascript Fetch Error:", err);
        App.showToast('error', err.message || 'Server connection error.');
        document.getElementById('modMessage').innerText = 'ERROR: ' + err.message + '\n\nPlease check Developer Tools (F12) -> Console for full error details.';
    }
}

function closeInquiryModal() {
    App.closeModal('inquiryModal');
}

async function saveInquiryMinute(e) {
    e.preventDefault();
    const form = document.getElementById('inquiryMinuteForm');
    const formData = new FormData(form);

    try {
        const res = await fetch('index.php?action=update_inquiry_minute', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (data.status === 'success') {
            App.showToast('success', data.message);
            setTimeout(() => location.reload(), 1200);
        } else {
            App.showToast('error', data.message || 'Failed to save minutes.');
        }
    } catch (err) {
        console.error(err);
        App.showToast('error', 'Error saving data.');
    }
}

let deleteConfirmTimer = null;
async function deleteInquiryRecord() {
    const id = document.getElementById('inqModId').value;
    if (!id) {
        App.showToast('error', 'Invalid Record ID');
        return;
    }

    const btn = document.getElementById('btnDeleteRecord');
    if (!btn.dataset.confirming) {
        btn.dataset.confirming = "true";
        btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Click again to confirm delete';
        btn.style.background = '#DC2626';
        btn.style.color = '#FFFFFF';
        
        clearTimeout(deleteConfirmTimer);
        deleteConfirmTimer = setTimeout(() => {
            if (btn) {
                delete btn.dataset.confirming;
                btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Record';
                btn.style.background = '#FEE2E2';
                btn.style.color = '#DC2626';
            }
        }, 4000);
        return;
    }

    clearTimeout(deleteConfirmTimer);
    delete btn.dataset.confirming;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('id', id);
        const res = await fetch('index.php?action=delete_inquiry', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.status === 'success') {
            App.showToast('success', data.message);
            closeInquiryModal();
            setTimeout(() => location.reload(), 800);
        } else {
            App.showToast('error', data.message || 'Failed to delete.');
            btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Record';
            btn.style.background = '#FEE2E2';
            btn.style.color = '#DC2626';
            btn.disabled = false;
        }
    } catch (err) {
        console.error(err);
        App.showToast('error', 'Error deleting data.');
        btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Record';
        btn.style.background = '#FEE2E2';
        btn.style.color = '#DC2626';
        btn.disabled = false;
    }
}

function openReplyModal() {
    const toEmail = document.getElementById('modEmail').innerText;
    if (!toEmail || toEmail === 'No email' || toEmail === '-' || toEmail === 'No Email') {
        App.showToast('warning', 'This inquiry does not have a valid sender email.');
        return;
    }
    const subject = document.getElementById('modSubject').innerText;
    const refNo   = document.getElementById('modIdDate').innerText.split('•')[0].trim();
    
    document.getElementById('replyToEmail').value = toEmail;
    document.getElementById('replySubject').value = `Re: ${refNo} - ${subject}`;
    document.getElementById('replyMessageBody').value = '';
    
    App.openModal('replyModal');
}

function closeReplyModal() {
    App.closeModal('replyModal');
}

function sendViaGmailWeb() {
    const toEmail = document.getElementById('replyToEmail').value;
    const subject = document.getElementById('replySubject').value;
    const body    = document.getElementById('replyMessageBody').value;
    const dbUserEmail = "<?= htmlspecialchars(addslashes($currentUser['email'] ?? '')) ?>";
    
    // Use Gmail's authuser parameter to attempt composing from the database user's Google account
    const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(toEmail)}&su=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}&authuser=${encodeURIComponent(dbUserEmail)}`;
    
    window.open(gmailUrl, '_blank');
}

async function submitOfficialReply() {
    const id = document.getElementById('inqModId').value;
    const replyText = document.getElementById('replyMessageBody').value.trim();
    
    if (!replyText) {
        App.showToast('warning', 'Please type your response message before recording.');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', 'replied');
        formData.append('minute_notes', `Official Reply sent by <?= htmlspecialchars(addslashes($currentUser['name'] ?? 'Admin')) ?> (<?= htmlspecialchars(addslashes($currentUser['email'] ?? '')) ?>):\n\n${replyText}`);
        formData.append('apply_sign', '1');
        
        const res = await fetch('index.php?action=update_inquiry_minute', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        
        if (data.status === 'success') {
            App.showToast('success', 'Official reply successfully recorded in portal database!');
            closeReplyModal();
            closeInquiryModal();
            setTimeout(() => location.reload(), 1200);
        } else {
            App.showToast('error', data.message || 'Failed to record reply.');
        }
    } catch (err) {
        console.error(err);
        App.showToast('error', 'Error recording reply.');
    }
}
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
