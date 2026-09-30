/** Tender KPI and post-mortem interactions. */

document.addEventListener('DOMContentLoaded', () => {
    const addTenderForm = document.getElementById('addTenderForm');
    const btnSubmit = document.getElementById('btnSubmitTender');
    if (!addTenderForm) return;

    addTenderForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(addTenderForm);

        const result = await App.post('index.php?action=add_tender', formData, btnSubmit);

        if (result && result.status === 'success') {
            App.closeModal('addTenderModal');
            addTenderForm.reset();
            if (result.data) {
                const badge = document.querySelector('.kpi-summary-pill.target');
                if (badge && result.data.kpi) {
                    badge.innerHTML = `KPI ${result.data.kpi.count} / 4 (${result.data.kpi.percentage}%)`;
                }
                const countBadge = document.getElementById('tenderCountBadge');
                if (countBadge) {
                    let count = parseInt(countBadge.textContent) || 0;
                    countBadge.textContent = `${count + 1} Record${(count+1) === 1 ? '' : 's'}`;
                }
                
                const tr = document.createElement('tr');
                tr.id = `tender-row-${result.data.id}`;
                
                // Hide staff column if not admin/manager (depends on UI, but we can just skip it or add a generic column)
                // For safety, we use the same number of columns visually available
                const isManagement = document.querySelector('th:first-child').textContent.includes('Staff');
                let staffCol = '';
                if (isManagement) {
                    staffCol = `<td data-label="Staff"><div class="kpi-staff-cell"><span class="kpi-staff-avatar">${result.data.staff_name.substring(0,2).toUpperCase()}</span><div class="kpi-staff-info"><strong>${result.data.staff_name}</strong><span>KPI: ${result.data.kpi.percentage}%</span></div></div></td>`;
                }
                
                tr.innerHTML = `
                    ${staffCol}
                    <td data-label="Project / Tender"><strong>${result.data.project_name}</strong></td>
                    <td data-label="Client / Department">${result.data.client_name}</td>
                    <td data-label="Value (RM)"><span class="badge badge-success" style="font-size:11px;padding:3px 6px;">RM ${result.data.project_value}</span></td>
                    <td data-label="Closing Date">${result.data.closing_date}</td>
                    <td data-label="Status / Review"><span class="badge-status" style="background:#EFF6FF;color:#2563EB;border:1px solid #BFDBFE;"><i class="fa-solid fa-paper-plane"></i>Submitted</span></td>
                `;
                
                const tbody = document.getElementById('tendersTableBody');
                const emptyRow = document.getElementById('emptyTenderRow');
                if (emptyRow) emptyRow.remove();
                if (tbody) tbody.prepend(tr);
                
                // Blink row to show it was added
                tr.style.background = '#ECFDF5';
                setTimeout(() => tr.style.background = '', 1500);
            } else {
                setTimeout(() => location.reload(), 500);
            }
        }
    });
});

let activeStatusSelectElem = null;

// Buka Modal Post Mortem (Apabila status bertukar ke Won / Lost atau untuk lihat/edit)
window.openPostMortemModal = function(id, status, projectName, factors = '', summary = '', canEdit = true, selectElem = null, swotS = '', swotW = '', swotO = '', swotT = '') {
    activeStatusSelectElem = selectElem;
    const modal = document.getElementById('postMortemModal');
    const header = document.getElementById('pmHeader');
    const title = document.getElementById('pmTitle');
    const subtitle = document.getElementById('pmSubtitle');
    const banner = document.getElementById('pmBanner');
    const bannerIcon = document.getElementById('pmBannerIcon');
    const bannerTitle = document.getElementById('pmBannerTitle');
    const bannerDesc = document.getElementById('pmBannerDesc');
    const swotSInput = document.getElementById('pmSwotS');
    const swotWInput = document.getElementById('pmSwotW');
    const swotOInput = document.getElementById('pmSwotO');
    const swotTInput = document.getElementById('pmSwotT');
    const btnSave = document.getElementById('btnSavePostMortem');

    if (!modal) return;

    document.getElementById('pmTenderId').value = id;
    document.getElementById('pmStatus').value = status;

    if (status === 'won') {
        header.style.background = 'linear-gradient(135deg, #065F46 0%, #059669 100%)';
        title.innerHTML = `<i class="fa-solid fa-trophy" style="color: #FDE68A;"></i> SWOT Post Mortem - WON TENDER`;
        subtitle.textContent = `Project: ${projectName}`;
        banner.style.background = '#ECFDF5';
        banner.style.borderColor = '#A7F3D0';
        bannerIcon.className = 'fa-solid fa-circle-check';
        bannerIcon.style.color = '#059669';
        bannerTitle.style.color = '#065F46';
        bannerTitle.textContent = '🎉 Congratulations on Winning this Tender!';
        bannerDesc.style.color = '#047857';
        bannerDesc.textContent = 'Please complete the SWOT analysis to document our winning strengths and future opportunities.';
    } else {
        header.style.background = 'linear-gradient(135deg, #7F1D1D 0%, #DC2626 100%)';
        title.innerHTML = `<i class="fa-solid fa-triangle-exclamation" style="color: #FECACA;"></i> SWOT Post Mortem - LOST TENDER`;
        subtitle.textContent = `Project: ${projectName}`;
        banner.style.background = '#FEF2F2';
        banner.style.borderColor = '#FECACA';
        bannerIcon.className = 'fa-solid fa-circle-xmark';
        bannerIcon.style.color = '#DC2626';
        bannerTitle.style.color = '#7F1D1D';
        bannerTitle.textContent = '💡 SWOT Analysis (Areas for Improvement & Competitor Review)';
        bannerDesc.style.color = '#B91C1C';
        bannerDesc.textContent = 'Please state why we did not win and analyze competitor strengths as lessons learned.';
    }

    // If swotS/W/O/T are empty but summary contains data (from pre-SWOT or JSON version), parse safely
    let sVal = swotS, wVal = swotW, oVal = swotO, tVal = swotT;
    if (!sVal && !wVal && !oVal && !tVal && summary) {
        try {
            const parsed = JSON.parse(summary);
            if (parsed && typeof parsed === 'object') {
                sVal = parsed.strengths || '';
                wVal = parsed.weaknesses || '';
                oVal = parsed.opportunities || '';
                tVal = parsed.threats || '';
            } else {
                sVal = summary;
            }
        } catch(e) {
            sVal = summary;
        }
    }

    swotSInput.value = sVal || '';
    swotWInput.value = wVal || '';
    swotOInput.value = oVal || '';
    swotTInput.value = tVal || '';

    if (!canEdit) {
        swotSInput.disabled = true;
        swotWInput.disabled = true;
        swotOInput.disabled = true;
        swotTInput.disabled = true;
        if (btnSave) btnSave.style.display = 'none';
    } else {
        swotSInput.disabled = false;
        swotWInput.disabled = false;
        swotOInput.disabled = false;
        swotTInput.disabled = false;
        if (btnSave) btnSave.style.display = 'inline-flex';
    }

    App.openModal('postMortemModal');
};

window.closePostMortemModal = function() {
    if (activeStatusSelectElem) {
        const oldVal = activeStatusSelectElem.getAttribute('data-old-val') || 'submitted';
        activeStatusSelectElem.value = oldVal;
    }
    activeStatusSelectElem = null;
    App.closeModal('postMortemModal');
};

window.submitPostMortem = async function() {
    const id = document.getElementById('pmTenderId').value;
    const status = document.getElementById('pmStatus').value;
    const sVal = document.getElementById('pmSwotS').value.trim();
    const wVal = document.getElementById('pmSwotW').value.trim();
    const oVal = document.getElementById('pmSwotO').value.trim();
    const tVal = document.getElementById('pmSwotT').value.trim();
    const btnSave = document.getElementById('btnSavePostMortem');

    if (!sVal && !wVal && !oVal && !tVal) {
        App.showToast('error', 'Please fill in at least one section of the SWOT Analysis.');
        document.getElementById('pmSwotS').focus();
        return;
    }

    if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Saving...`;
    }

    const summaryFormatted = JSON.stringify({
        strengths: sVal,
        weaknesses: wVal,
        opportunities: oVal,
        threats: tVal
    });

    await updateTenderStatusAjax(id, status, summaryFormatted, null, activeStatusSelectElem, btnSave, sVal, wVal, oVal, tVal);
    
    if (btnSave) {
        btnSave.disabled = false;
        btnSave.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> Save & Submit Post Mortem`;
    }
    activeStatusSelectElem = null;
    App.closeModal('postMortemModal');
};

// Pengesanan pertukaran status pada dropdown (Terus simpan status tanpa membuka modal serta-merta)
window.updateTenderStatus = function(id, newStatus, selectElem) {
    updateTenderStatusAjax(id, newStatus, null, null, selectElem);
};

// Kemas kini Status & Post Mortem via AJAX
async function updateTenderStatusAjax(id, newStatus, summary = null, factors = null, selectElem = null, btnSave = null, swotS = null, swotW = null, swotO = null, swotT = null) {
    if (selectElem) selectElem.disabled = true;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('status', newStatus);
    if (summary !== null) formData.append('post_mortem_summary', summary);
    if (factors !== null) formData.append('post_mortem_factors', factors);
    if (swotS !== null) formData.append('swot_s', swotS);
    if (swotW !== null) formData.append('swot_w', swotW);
    if (swotO !== null) formData.append('swot_o', swotO);
    if (swotT !== null) formData.append('swot_t', swotT);

    const csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput) {
        formData.append('csrf_token', csrfInput.value);
    }

    try {
        const response = await fetch('index.php?action=update_tender_status', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            App.showToast('success', result.message);
            
            // Seamless update
            const tr = document.getElementById(`tender-row-${id}`);
            if (tr) {
                if (selectElem) selectElem.setAttribute('data-old-val', newStatus);
                let badgeSpan = tr.querySelector('.badge-status');
                if (badgeSpan) {
                    const statusMap = {
                        'in_progress': ['#FEF3C7', '#B45309', '#FDE68A', 'In Progress', 'fa-spinner'],
                        'submitted': ['#EFF6FF', '#2563EB', '#BFDBFE', 'Submitted', 'fa-paper-plane'],
                        'won': ['#ECFDF5', '#059669', '#A7F3D0', 'Won / Successful', 'fa-trophy'],
                        'lost': ['#FEF2F2', '#DC2626', '#FECACA', 'Lost', 'fa-xmark']
                    };
                    const st = statusMap[newStatus] || ['#F1F5F9', '#475569', '#CBD5E1', newStatus, 'fa-circle'];
                    badgeSpan.style.background = st[0];
                    badgeSpan.style.color = st[1];
                    badgeSpan.style.borderColor = st[2];
                    badgeSpan.innerHTML = `<i class="fa-solid ${st[4]}"></i>${st[3]}`;
                } else {
                    setTimeout(() => location.reload(), 800);
                }
            } else {
                setTimeout(() => location.reload(), 800);
            }
        } else {
            App.showToast('error', result.message || 'Failed to update tender status');
            if (selectElem) {
                const oldVal = selectElem.getAttribute('data-old-val') || 'submitted';
                selectElem.value = oldVal;
            }
        }
    } catch (error) {
        App.showToast('error', 'Network error occurred while updating status.');
        if (selectElem) {
            const oldVal = selectElem.getAttribute('data-old-val') || 'submitted';
            selectElem.value = oldVal;
        }
    } finally {
        if (selectElem) selectElem.disabled = false;
    }
}

window.deleteTender = async function(id, name) {
    const confirmed = await App.confirm(
        'Delete Tender Record?',
        `Are you sure you want to permanently delete the tender "${name}"? This action cannot be undone and will be recorded in the Audit Trail.`,
        'Yes, Delete It',
        'Cancel'
    );

    if (!confirmed) return;

    const formData = new FormData();
    formData.append('id', id);

    try {
        const response = await fetch('index.php?action=delete_tender', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const result = await response.json();

        if (result.status === 'success') {
            App.showToast('success', result.message || 'Tender record deleted successfully!');
            
            // Seamless remove
            const tr = document.getElementById(`tender-row-${id}`);
            if (tr) {
                tr.style.transition = 'all 0.3s ease';
                tr.style.opacity = '0';
                setTimeout(() => {
                    tr.remove();
                    const countBadge = document.getElementById('tenderCountBadge');
                    if (countBadge) {
                        let count = parseInt(countBadge.textContent) || 0;
                        count = Math.max(0, count - 1);
                        countBadge.textContent = `${count} Record${count === 1 ? '' : 's'}`;
                    }
                }, 300);
            } else {
                setTimeout(() => location.reload(), 800);
            }
        } else {
            App.showToast('error', result.message || 'Failed to delete tender.');
        }
    } catch (error) {
        App.showToast('error', 'Network error occurred while deleting tender.');
    }
};
