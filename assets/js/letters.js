/**
 * Skrip Pengurusan Surat Masuk & Keluar - AJAX (Vanilla JS)
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

document.addEventListener('DOMContentLoaded', () => {
    const addLetterForm = document.getElementById('addLetterForm');
    const btnSubmit = document.getElementById('btnSubmitLetter');
    const tableBody = document.getElementById('lettersTableBody');
    const emptyRow = document.getElementById('emptyLetterRow');

    if (!addLetterForm) return;

    addLetterForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Gunakan FormData kerana terdapat muat naik fail lampiran PDF
        const formData = new FormData(addLetterForm);

        const result = await App.post('index.php?action=add_letter', formData, btnSubmit);

        if (result && result.status === 'success') {
            // Tutup Modal & Reset borang
            App.closeModal('addLetterModal');
            addLetterForm.reset();

            // Hapus baris kosong jika ada
            if (emptyRow) {
                emptyRow.remove();
            }

            // Kemas kini nombor badge jumlah surat
            const countBadge = document.getElementById('letterCountBadge');
            if (countBadge && tableBody) {
                const newTotal = tableBody.rows.length + (emptyRow && !document.getElementById('emptyLetterRow') ? 0 : 1);
                countBadge.textContent = `${newTotal} Records`;
            }

            // Bina baris HTML baru untuk dimasukkan ke dalam jadual
            const newRow = document.createElement('tr');
            newRow.style.animation = 'fadeIn 0.5s ease';
            newRow.style.backgroundColor = '#D1FAE5'; // Highlight kejayaan baru

            const typeBadge = result.data.type === 'IN'
                ? `<span class="badge badge-success"><i class="fa-solid fa-arrow-down"></i> INCOMING</span>`
                : `<span class="badge badge-info" style="background:#DBEAFE; color:#1E40AF;"><i class="fa-solid fa-arrow-up"></i> OUTGOING</span>`;

            const fileButton = result.data.file_path 
                ? `<a href="${result.data.file_path}" target="_blank" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px; color: #DC2626;"><i class="fa-solid fa-file-pdf"></i> View PDF</a>`
                : `<span style="color: var(--text-muted); font-size: 11px;">No PDF</span>`;

            const statusMap = {
                'pending': ['badge-danger', 'Pending Action'],
                'in_progress': ['badge-warning', 'In Progress'],
                'replied': ['badge-info', 'Replied'],
                'completed': ['badge-success', 'Completed / Closed']
            };
            const st = statusMap[result.data.status] || ['badge-navy', result.data.status];

            const adminSelect = IS_ADMIN
                ? `<td>
                    <select class="form-control" style="padding: 4px 8px; font-size: 11px; width: 150px;" onchange="updateLetterStatus(${result.data.id}, this.value)">
                        <option value="pending" ${result.data.status === 'pending' ? 'selected' : ''}>Pending Action</option>
                        <option value="in_progress" ${result.data.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                        <option value="replied" ${result.data.status === 'replied' ? 'selected' : ''}>Replied</option>
                        <option value="completed" ${result.data.status === 'completed' ? 'selected' : ''}>Completed</option>
                    </select>
                   </td>`
                : '';

            const remarksHtml = result.data.remarks 
                ? `<span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;"><i class="fa-solid fa-note-sticky" style="color:#94A3B8;"></i> ${result.data.remarks}</span>`
                : '';

            newRow.innerHTML = `
                <td style="white-space: nowrap;">
                    <strong style="color: var(--navy-dark); font-family: monospace; font-size: 13px; background: #F1F5F9; padding: 4px 8px; border-radius: 4px; border: 1px solid #CBD5E1;">
                        ${result.data.ref_no}
                    </strong>
                </td>
                <td>${typeBadge}</td>
                <td>
                    <strong style="color: var(--text-dark); display: block; font-size: 13px;">${result.data.title}</strong>
                    ${remarksHtml}
                </td>
                <td><span style="color: var(--text-body); font-weight: 500;">${result.data.sender_receiver}</span></td>
                <td><span style="font-size: 12px; color: var(--text-muted);">${result.data.letter_date}</span></td>
                <td>${fileButton}</td>
                <td><span class="badge ${st[0]}" id="status-badge-${result.data.id}">${st[1]}</span></td>
                ${adminSelect}
            `;

            if (tableBody) {
                tableBody.insertBefore(newRow, tableBody.firstChild);
            }

            // Hilangkan warna highlight selepas 3 saat
            setTimeout(() => {
                newRow.style.backgroundColor = '';
                newRow.style.transition = 'background-color 1s ease';
            }, 3000);
        }
    });
});
