<?php
$reportDefaultMonth = isset($curMonth) && preg_match('/^\d{4}-\d{2}$/', (string)$curMonth)
    && $curMonth <= date('Y-m') ? $curMonth : date('Y-m');
[$reportDefaultYear, $reportDefaultMonthNumber] = array_map('intval', explode('-', $reportDefaultMonth));
?>
<style>
.tm-report-field { margin-bottom:17px; }
.tm-report-label { display:block;margin-bottom:8px;color:var(--text-dark);font-size:12px;font-weight:800; }
.tm-report-control { display:block;width:100%;min-height:44px;padding:10px 12px;border:1px solid #CBD5E1;border-radius:9px;background:var(--bg-card);color:var(--text-dark);font:inherit;font-size:14px; }
.tm-month-picker { border:1px solid #CBD5E1;border-radius:13px;overflow:hidden;background:var(--bg-card); }
.tm-month-year { display:flex;align-items:center;justify-content:space-between;padding:11px 12px;background:var(--bg-primary);border-bottom:1px solid var(--border-color); }
.tm-month-year button { width:36px;height:34px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-card);color:var(--text-dark);cursor:pointer; }
.tm-month-year button:disabled { opacity:.35;cursor:not-allowed; }
.tm-month-year strong { color:var(--text-dark);font-size:15px; }
.tm-month-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:12px; }
.tm-month-option { padding:10px 6px;border:1px solid transparent;border-radius:9px;background:transparent;color:var(--text-muted);font:inherit;font-size:12px;font-weight:800;cursor:pointer; }
.tm-month-option:hover:not(:disabled) { border-color:#93C5FD;background:#EFF6FF;color:#1D4ED8; }
.tm-month-option.selected { background:#2563EB;color:#FFF;box-shadow:0 4px 10px rgba(37,99,235,.22); }
.tm-month-option.current:not(.selected) { border-color:#BFDBFE;color:#1D4ED8; }
.tm-month-option:disabled { opacity:.3;cursor:not-allowed; }
.tm-report-selection { margin-top:9px;color:#2563EB;font-size:12px;font-weight:800;text-align:center; }
@media (max-width:480px) { .tm-month-grid{grid-template-columns:repeat(3,1fr)} }
</style>

<div class="modal-overlay" id="tenderReportModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-calendar-days" style="color:#2563EB;"></i> Generate Monthly Report</h3>
            <button class="modal-close" type="button" onclick="App.closeModal('tenderReportModal')">&times;</button>
        </div>
        <form action="index.php" method="get" target="_blank" onsubmit="return validateTenderReportMonth()">
            <input type="hidden" name="page" value="tender_board_report">
            <input type="hidden" name="month" id="tenderReportMonth" value="<?= htmlspecialchars($reportDefaultMonth) ?>">
            <div class="modal-body" style="padding:20px;">
                <div class="tm-report-field">
                    <label class="tm-report-label">Report Month *</label>
                    <div class="tm-month-picker" role="group" aria-label="Choose report month">
                        <div class="tm-month-year">
                            <button type="button" id="tmReportPreviousYear" onclick="changeTenderReportYear(-1)" aria-label="Previous year"><i class="fa-solid fa-chevron-left"></i></button>
                            <strong id="tmReportYear"><?= $reportDefaultYear ?></strong>
                            <button type="button" id="tmReportNextYear" onclick="changeTenderReportYear(1)" aria-label="Next year"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                        <div class="tm-month-grid" id="tmReportMonthGrid"></div>
                    </div>
                    <div class="tm-report-selection" id="tmReportSelection"></div>
                </div>
                <div class="tm-report-field">
                    <label class="tm-report-label" for="tenderReportFormat">Report Format *</label>
                    <select class="tm-report-control" name="format" id="tenderReportFormat" required>
                        <option value="print">Preview / Print</option>
                        <option value="pdf">Download PDF</option>
                        <option value="csv">Download CSV</option>
                    </select>
                </div>
                <p style="margin:0;padding:11px 12px;border-radius:10px;background:#EFF6FF;color:#475569;font-size:12px;line-height:1.55;">
                    <i class="fa-solid fa-lock" style="color:#2563EB;"></i>
                    This personal report only includes tenders you joined. Completed post-mortem and SWOT details are included automatically.
                </p>
            </div>
            <div class="modal-footer" style="padding:16px 20px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('tenderReportModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-export"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<script>
const tenderReportMonthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const tenderReportFullMonthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const tenderReportCurrentYear = <?= (int)date('Y') ?>;
const tenderReportCurrentMonth = <?= (int)date('m') ?>;
let tenderReportPickerYear = <?= $reportDefaultYear ?>;
let tenderReportSelectedYear = <?= $reportDefaultYear ?>;
let tenderReportSelectedMonth = <?= $reportDefaultMonthNumber ?>;

function renderTenderReportCalendar() {
    document.getElementById('tmReportYear').textContent = tenderReportPickerYear;
    document.getElementById('tmReportPreviousYear').disabled = tenderReportPickerYear <= 2020;
    document.getElementById('tmReportNextYear').disabled = tenderReportPickerYear >= tenderReportCurrentYear;
    const grid = document.getElementById('tmReportMonthGrid');
    grid.innerHTML = tenderReportMonthNames.map((name, index) => {
        const month = index + 1;
        const isFuture = tenderReportPickerYear > tenderReportCurrentYear
            || (tenderReportPickerYear === tenderReportCurrentYear && month > tenderReportCurrentMonth);
        const isSelected = tenderReportPickerYear === tenderReportSelectedYear && month === tenderReportSelectedMonth;
        const isCurrent = tenderReportPickerYear === tenderReportCurrentYear && month === tenderReportCurrentMonth;
        return `<button type="button" class="tm-month-option${isSelected ? ' selected' : ''}${isCurrent ? ' current' : ''}"
                    onclick="selectTenderReportMonth(${month})" ${isFuture ? 'disabled' : ''}>${name}</button>`;
    }).join('');
    document.getElementById('tmReportSelection').textContent = `${tenderReportFullMonthNames[tenderReportSelectedMonth - 1]} ${tenderReportSelectedYear}`;
}

function changeTenderReportYear(change) {
    tenderReportPickerYear = Math.max(2020, Math.min(tenderReportCurrentYear, tenderReportPickerYear + change));
    renderTenderReportCalendar();
}

function selectTenderReportMonth(month) {
    tenderReportSelectedYear = tenderReportPickerYear;
    tenderReportSelectedMonth = month;
    document.getElementById('tenderReportMonth').value = `${tenderReportSelectedYear}-${String(month).padStart(2, '0')}`;
    renderTenderReportCalendar();
}

function openTenderReportModal() {
    tenderReportPickerYear = tenderReportSelectedYear;
    renderTenderReportCalendar();
    App.openModal('tenderReportModal');
}

function validateTenderReportMonth() {
    return /^\d{4}-(0[1-9]|1[0-2])$/.test(document.getElementById('tenderReportMonth').value);
}

renderTenderReportCalendar();
</script>
