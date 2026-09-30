<?php
/**
 * Templat Footer & Skrip JavaScript
 * Syarikat: The Bridge Business Alliance (TBBA)
 */
?>
    </main> <!-- End .content-area -->

    <footer style="padding: 20px 30px; text-align: center; border-top: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-muted); font-size: 12px;">
        &copy; <?= date('Y') ?> <strong>The Bridge Business Alliance (TBBA)</strong>. All Rights Reserved.
    </footer>
</div> <!-- End .main-wrapper -->
</div> <!-- End .app-wrapper -->

<!-- Toast notification container (AJAX feedback without reload) -->
<div id="toast-container"></div>

<!-- GPS map / information modal -->
<div class="modal-overlay" id="gpsMapModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-map-location-dot" style="color: var(--accent-blue);"></i> GPS Coordinate Location</h3>
            <button class="modal-close" onclick="App.closeModal('gpsMapModal')">&times;</button>
        </div>
        <div class="modal-body" id="gpsMapContent" style="min-height: 250px; text-align: center;">
            <p>Loading coordinates...</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="App.closeModal('gpsMapModal')">Close</button>
        </div>
    </div>
</div>

<!-- PWA Installation Guide Modal (Add to Home Screen) -->
<div class="modal-overlay" id="pwaInstallModal">
    <div class="modal-box" style="max-width: 460px;">
        <div class="modal-header">
            <h3 class="modal-title" style="font-size: 15px;"><i class="fa-solid fa-mobile-screen-button" style="color: var(--accent-blue);"></i> Install TBBA ERP App</h3>
            <button class="modal-close" onclick="App.closeModal('pwaInstallModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px; text-align: left; font-size: 13px; color: var(--text-dark);">
            <div id="pwaModalContent">
                <!-- Dynamically populated by JavaScript based on Android/iOS/Desktop -->
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
            <button class="btn btn-secondary" onclick="App.closeModal('pwaInstallModal')">Close</button>
        </div>
    </div>
</div>
<!-- E-Sign Modal -->
<div id="esignModal" class="esign-modal-overlay">
    <div class="esign-modal-box">
        <div class="esign-modal-header">
            <h3 class="esign-modal-title"><i class="fa-solid fa-pen-nib"></i> Digital Signature</h3>
            <button class="esign-modal-close" onclick="ESign.closeModal()">&times;</button>
        </div>
        <div class="esign-modal-body">
            <div class="esign-wrapper">
                <canvas id="esignCanvas" class="esign-canvas"></canvas>
                <div class="esign-instruction">Please provide your signature inside this box.</div>
            </div>
        </div>
        <div class="esign-modal-footer">
            <div class="esign-actions-left">
                <button type="button" class="btn btn-secondary" onclick="ESign.clear()"><i class="fa-solid fa-eraser"></i> Clear</button>
            </div>
            <div class="esign-actions-right">
                <button type="button" class="btn btn-secondary" onclick="ESign.closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnESignSave" onclick="ESign.save()">
                    <i class="fa-solid fa-check"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- DataTables Plugin JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js" integrity="sha384-k5vbMeKHbxEZ0AEBTSdR7UjAgWCcUfrS8c0c5b2AfIh7olfhNkyCZYwOfzOQhauK" crossorigin="anonymous"></script>

<?php if (!empty($pageJs)): ?>
    <script src="assets/js/<?= htmlspecialchars($pageJs) ?>?v=<?= tbba_asset_version('assets/js/' . $pageJs) ?>"></script>
<?php endif; ?>

<!-- E-Sign Dependencies -->
<script src="assets/js/signature_pad.umd.min.js"></script>
<script src="assets/js/esign.js?v=<?= tbba_asset_version('assets/js/esign.js') ?>"></script>

<script>
// Auto-assign data-label for Idea 1 (Mobile Card Transformation)
function applyMobileTableLabels() {
    $('table.table, table.datatable, table.audit-table').each(function() {
        const headers = [];
        $(this).find('thead th').each(function() {
            headers.push($(this).text().trim());
        });
        $(this).find('tbody tr').each(function() {
            const rowTds = $(this).find('td');
            if (rowTds.length === 1 && rowTds.attr('colspan')) return; // skip empty/loading rows
            rowTds.each(function(index) {
                if (headers[index] && !$(this).attr('data-label')) {
                    $(this).attr('data-label', headers[index]);
                }
            });
        });
    });
}

$(document).ready(function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.datatable').each(function() {
            if ($.fn.DataTable.isDataTable(this)) return;
            
            let emptyMsg = "No records found.";
            const firstRowTd = $(this).find('tbody tr:first td');
            if (firstRowTd.length === 1 && firstRowTd.attr('colspan')) {
                emptyMsg = firstRowTd.text().trim() || emptyMsg;
                $(this).find('tbody').empty();
            }

            $(this).DataTable({
                dom: '<"dt-top-bar"lf><"dt-table-container"rt><"dt-bottom-bar"ip>',
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [10, 20, 50, 100],
                order: [],
                language: {
                    search: "",
                    searchPlaceholder: "Search all columns...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 entries",
                    emptyTable: emptyMsg,
                    zeroRecords: "No matching records found."
                }
            });
        });
    }

    applyMobileTableLabels();

    $(document).on('draw.dt init.dt', '.datatable, .table, .audit-table', function() {
        applyMobileTableLabels();
    });
});
</script>

<!-- Firebase Push Notification Module — TBBA ERP -->
<!-- Loaded last so the Firebase SDK in the header is already available -->
<script src="assets/js/firebase-push.js?v=<?= tbba_asset_version('assets/js/firebase-push.js') ?>"></script>
<script>
    // Auto-initialize push notifications after the page finishes loading.
    // Restore a previously allowed subscription; new permission requires a user tap.
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize only when Firebase configuration is set (not a placeholder).
        if (window.FIREBASE_CONFIG && window.FIREBASE_CONFIG.projectId &&
            window.FIREBASE_CONFIG.projectId !== 'REPLACE_WITH_YOUR_PROJECT_ID' &&
            window.FIREBASE_CONFIG.projectId !== '') {
            // Defer the background registration until the page has settled.
            setTimeout(function() {
                if (typeof FirebasePush !== 'undefined') {
                    FirebasePush.init();
                }
            }, 2000);
        }
    });
</script>
</body>
</html>
