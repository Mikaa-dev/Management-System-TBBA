/**
 * E-Sign / Digital Signature Module — TBBA ERP
 * Memerlukan library: signature_pad.umd.min.js
 */

const ESign = (function() {
    let signaturePad = null;
    let currentConfig = {
        documentType: '',
        documentId: 0,
        signerRole: '',
        onSuccess: null
    };

    /**
     * Parse error response secara selamat
     */
    async function parseResponse(response) {
        try {
            const data = await response.json();
            return data;
        } catch (e) {
            console.error('[ESign] Failed to parse JSON:', e);
            throw new Error('Server error: Invalid response format.');
        }
    }

    /**
     * Tunjuk toast notification (guna App.showToast jika ada, kalau tak fallback ke alert)
     */
    function showNotification(type, message) {
        if (typeof App !== 'undefined' && App.showToast) {
            App.showToast(type, message);
        } else {
            alert(message);
        }
    }

    return {
        /**
         * Initialize Signature Pad dan attach pada canvas
         */
        init: function() {
            if (typeof SignaturePad === 'undefined') {
                console.error('[ESign] SignaturePad library was not found.');
                return;
            }

            const canvas = document.getElementById('esignCanvas');
            if (!canvas) return;

            // Initialize library
            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)', // Transparent
                penColor: 'rgb(0, 0, 128)', // Dark blue ink
                velocityFilterWeight: 0.7
            });

            // Handle window resize untuk adjust canvas (prevent signature jadi herot)
            window.addEventListener('resize', this.resizeCanvas.bind(this));
        },

        /**
         * Resize canvas mengikut saiz sebenar screen/container
         * Perlu dipanggil setiap kali modal dibuka supaya resolution canvas tajam
         */
        resizeCanvas: function() {
            const canvas = document.getElementById('esignCanvas');
            if (!canvas || !signaturePad) return;

            // Simpan data sedia ada
            const data = signaturePad.toData();

            // Set actual canvas size based on device pixel ratio (untuk screen retina/mobile)
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);

            // Letak balik data (kalau user resize masa tengah sign)
            signaturePad.clear();
            signaturePad.fromData(data);
        },

        /**
         * Buka modal signature
         * @param {string} documentType cth: 'leave_request'
         * @param {number} documentId   ID dokumen
         * @param {string} signerRole   'applicant' | 'approver'
         * @param {function} onSuccess  Callback bila berjaya submit
         */
        openModal: function(documentType, documentId, signerRole, onSuccess = null) {
            currentConfig = { documentType, documentId, signerRole, onSuccess };
            
            const modal = document.getElementById('esignModal');
            if (modal) {
                modal.classList.add('active');
                // Paksa resize lepas modal nampak (sebab display:none kacau offsetWidth)
                setTimeout(() => this.resizeCanvas(), 50);
                if (signaturePad) signaturePad.clear();
            }
        },

        /**
         * Tutup modal
         */
        closeModal: function() {
            const modal = document.getElementById('esignModal');
            if (modal) {
                modal.classList.remove('active');
            }
            if (signaturePad) signaturePad.clear();
        },

        /**
         * Clear canvas
         */
        clear: function() {
            if (signaturePad) {
                signaturePad.clear();
            }
        },

        /**
         * Submit signature ke server
         */
        save: async function() {
            if (!signaturePad) return;

            if (signaturePad.isEmpty()) {
                showNotification('error', 'Please provide your signature first.');
                return;
            }

            // Dapatkan Base64 PNG dari canvas
            const base64Data = signaturePad.toDataURL('image/png');
            
            // Tunjuk loading state pada button
            const btnSave = document.getElementById('btnESignSave');
            const originalText = btnSave.innerHTML;
            btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
            btnSave.disabled = true;

            try {
                const response = await fetch(`${window.APP_BASE_URL}?action=sign_document`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        csrf_token: window.CSRF_TOKEN,
                        signature_data: base64Data,
                        document_type: currentConfig.documentType,
                        document_id: currentConfig.documentId,
                        signer_role: currentConfig.signerRole
                    })
                });

                const data = await parseResponse(response);

                if (data.status === 'success') {
                    showNotification('success', data.message);
                    this.closeModal();
                    if (currentConfig.onSuccess) {
                        currentConfig.onSuccess(data.data);
                    } else {
                        // Default action: reload page untuk tunjuk status terkini
                        window.location.reload();
                    }
                } else {
                    showNotification('error', data.message || 'Failed to save the signature.');
                }
            } catch (err) {
                showNotification('error', err.message);
            } finally {
                btnSave.innerHTML = originalText;
                btnSave.disabled = false;
            }
        },

        /**
         * Verify integriti signature
         */
        verify: async function(documentType, documentId) {
            try {
                const response = await fetch(`${window.APP_BASE_URL}?action=verify_signature&document_type=${documentType}&document_id=${documentId}`);
                const data = await parseResponse(response);
                
                if (data.status === 'success') {
                    if (data.data.verified) {
                        showNotification('success', data.message);
                    } else {
                        showNotification('error', data.message);
                    }
                    console.table(data.data.results); // Tunjuk detail dalam console
                } else {
                    showNotification('error', data.message);
                }
            } catch (err) {
                showNotification('error', err.message);
            }
        },

        /**
         * Generate PDF lepas semua dah sign
         */
        generatePdf: async function(documentType, documentId, btnElement = null) {
            let originalText = '';
            if (btnElement) {
                originalText = btnElement.innerHTML;
                btnElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating PDF...';
                btnElement.disabled = true;
            }

            try {
                const response = await fetch(`${window.APP_BASE_URL}?action=generate_signed_pdf`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        csrf_token: window.CSRF_TOKEN,
                        document_type: documentType,
                        document_id: documentId
                    })
                });

                const data = await parseResponse(response);
                
                if (data.status === 'success') {
                    showNotification('success', 'The official PDF was generated successfully and is being downloaded.');
                    // Buka URL download dalam tab baru
                    if (data.data && data.data.download_url) {
                        window.open(data.data.download_url, '_blank');
                    }
                } else {
                    showNotification('error', data.message);
                }
            } catch (err) {
                showNotification('error', err.message);
            } finally {
                if (btnElement) {
                    btnElement.innerHTML = originalText;
                    btnElement.disabled = false;
                }
            }
        }
    };
})();

// Auto-init bila DOM loaded
document.addEventListener('DOMContentLoaded', function() {
    ESign.init();
});
