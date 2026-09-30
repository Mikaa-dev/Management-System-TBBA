/**
 * Skrip Pengurusan Pusat Dokumen & SOP - AJAX (Vanilla JS + SweetAlert)
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

document.addEventListener('DOMContentLoaded', () => {
    const uploadDocForm = document.getElementById('uploadDocForm');
    const btnSubmit = document.getElementById('btnSubmitDoc');

    if (!uploadDocForm) return;

    uploadDocForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(uploadDocForm);

        const result = await App.post('index.php?action=upload_document', formData, btnSubmit);

        if (result && result.status === 'success') {
            if (typeof closeUploadModal === 'function') closeUploadModal();
            uploadDocForm.reset();
            App.showToast('success', result.message || 'Document uploaded successfully!');
            
            // Seamless DOM Update (No Reload)
            if (result.data) {
                const doc = result.data;
                const ext = doc.file_name.split('.').pop().toLowerCase();
                let iconClass = 'fa-file-lines', iconColor = '#64748B', iconBg = '#F1F5F9';
                if (ext === 'pdf') { iconClass = 'fa-file-pdf'; iconColor = '#DC2626'; iconBg = '#FEF2F2'; }
                else if (['doc', 'docx'].includes(ext)) { iconClass = 'fa-file-word'; iconColor = '#2563EB'; iconBg = '#EFF6FF'; }
                else if (['xls', 'xlsx'].includes(ext)) { iconClass = 'fa-file-excel'; iconColor = '#059669'; iconBg = '#ECFDF5'; }
                else if (ext === 'zip') { iconClass = 'fa-file-zipper'; iconColor = '#D97706'; iconBg = '#FFFBEB'; }

                let formattedSize = doc.file_size + ' Bytes';
                if (doc.file_size >= 1048576) formattedSize = (doc.file_size / 1048576).toFixed(1) + ' MB';
                else if (doc.file_size >= 1024) formattedSize = Math.round(doc.file_size / 1024) + ' KB';

                const catStyles = {
                    'HR & Policies': {bg: '#FEF3C7', text: '#92400E', border: '#FDE68A'},
                    'Operational SOPs': {bg: '#E0E7FF', text: '#3730A3', border: '#C7D2FE'},
                    'Forms & Templates': {bg: '#ECFDF5', text: '#065F46', border: '#A7F3D0'},
                    'General Corporate': {bg: '#F1F5F9', text: '#334155', border: '#E2E8F0'}
                };
                const cat = catStyles[doc.category] || catStyles['General Corporate'];

                const dateStr = new Date(doc.created_at).toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});

                const html = `
                <div class="doc-card" data-title="${doc.title.toLowerCase()}" data-desc="${(doc.description||'').toLowerCase()}" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 22px; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(0,0,0,0.01); animation: modalFadeIn 0.3s ease;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                            <div style="width: 48px; height: 48px; border-radius: 12px; background: ${iconBg}; color: ${iconColor}; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;"><i class="fa-solid ${iconClass}"></i></div>
                            <span style="background: ${cat.bg}; color: ${cat.text}; border: 1px solid ${cat.border}; padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; letter-spacing: 0.3px;">${doc.category}</span>
                        </div>
                        <h4 style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0 0 8px; line-height: 1.4;">${doc.title}</h4>
                        <p style="font-size: 13px; color: #64748B; line-height: 1.6; margin: 0 0 16px; min-height: 40px;">${doc.description ? doc.description : '<em>No additional description provided for this corporate document.</em>'}</p>
                    </div>
                    <div>
                        <div class="doc-meta-bar" style="border-top: 1px solid #F1F5F9; padding-top: 14px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #94A3B8;">
                            <span style="display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-hard-drive"></i> ${formattedSize}</span>
                            <span style="display: inline-flex; align-items: center; gap: 4px;"><i class="fa-regular fa-calendar"></i> ${dateStr}</span>
                            <span style="display: inline-flex; align-items: center; gap: 4px;"><i class="fa-solid fa-user-check"></i> ${doc.uploader_name}</span>
                        </div>
                        <div class="doc-actions-bar" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; width: 100%; box-sizing: border-box;">
                            <a href="index.php?action=download_attachment&type=document&id=${doc.id}" target="_blank" class="btn btn-view-doc" style="flex: 1 1 auto; min-width: 80px; background: #2563EB; color: #FFFFFF; border: 1px solid #2563EB; padding: 10px 12px; border-radius: 10px; font-size: 13px; font-weight: 700; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;"><i class="fa-solid fa-eye"></i> <span>View</span></a>
                            <a href="index.php?action=download_attachment&type=document&id=${doc.id}&download=1" target="_blank" class="btn btn-dl-doc" style="flex: 1 1 auto; min-width: 80px; background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; padding: 10px 12px; border-radius: 10px; font-size: 13px; font-weight: 700; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;"><i class="fa-solid fa-download"></i> <span>Download</span></a>
                            <button type="button" onclick="deleteDocument(${doc.id}, '${doc.title.replace(/'/g, "\\'")}')" class="btn-del-doc" style="background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; padding: 10px 14px; border-radius: 10px; font-size: 13px; cursor: pointer; flex: 0 0 auto;"><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                    </div>
                </div>`;
                
                let container = document.getElementById('docCardsContainer');
                if (!container) {
                    // Replace empty state
                    const mainArea = document.querySelector('.main-content > div:nth-child(2)');
                    if (mainArea) {
                        mainArea.innerHTML = `<div class="doc-grid" id="docCardsContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; width: 100%; box-sizing: border-box;">${html}</div>`;
                    } else {
                        location.reload(); // Fallback
                    }
                } else {
                    container.insertAdjacentHTML('afterbegin', html);
                }
            } else {
                setTimeout(() => location.reload(), 800);
            }
        }
    });
});

window.deleteDocument = async function(id, title) {
    const confirmed = await App.confirm(
        'Delete Document?',
        `Are you sure you want to permanently delete "${title}"? This action cannot be undone and will be recorded in the Audit Trail.`,
        'Yes, Delete It',
        'Cancel'
    );

    if (!confirmed) return;

    const formData = new FormData();
    formData.append('document_id', id);
    const csrfTokenEl = document.getElementById('csrf_token_doc');
    if (csrfTokenEl) formData.append('csrf_token', csrfTokenEl.value);

    try {
        const response = await fetch('index.php?action=delete_document', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const result = await response.json();

        if (result.status === 'success') {
            App.showToast('success', result.message || 'Document deleted successfully!');
            
            // Seamless DOM Remove (No Reload)
            const delBtn = document.querySelector(`button[onclick*="deleteDocument(${id},"]`);
            if (delBtn) {
                const card = delBtn.closest('.doc-card');
                if (card) {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    setTimeout(() => card.remove(), 200);
                }
            } else {
                setTimeout(() => location.reload(), 800);
            }
        } else {
            App.showToast('error', result.message || 'Failed to delete document.');
        }
    } catch (err) {
        App.showToast('error', 'Network error occurred while deleting document.');
    }
};
