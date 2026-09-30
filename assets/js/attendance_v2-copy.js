/**
 * Skrip Kehadiran GPS & AJAX Clock-In/Out (Vanilla JS)
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

document.addEventListener('DOMContentLoaded', () => {
    const coordsText = document.getElementById('gpsCoordsText');
    const inLat = document.getElementById('inLat');
    const inLng = document.getElementById('inLng');
    const outLat = document.getElementById('outLat');
    const outLng = document.getElementById('outLng');

    const clockInForm = document.getElementById('clockInForm');
    const clockOutForm = document.getElementById('clockOutForm');
    const btnClockIn = document.getElementById('btnClockIn');
    const btnClockOut = document.getElementById('btnClockOut');

    // 1. Kesan Koordinat GPS melalui HTML5 Geolocation API
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                if (inLat) inLat.value = lat;
                if (inLng) inLng.value = lng;
                if (outLat) outLat.value = lat;
                if (outLng) outLng.value = lng;

                if (coordsText) {
                    coordsText.innerHTML = `<i class="fa-solid fa-circle-check" style="color: #059669;"></i> GPS Coordinates Successfully Detected: <strong>Lat ${lat.toFixed(5)}, Lng ${lng.toFixed(5)}</strong> (Accuracy: ${Math.round(position.coords.accuracy)} meters)`;
                }
            },
            (error) => {
                console.warn('Geolocation Error:', error);
                if (inLat) inLat.value = '';
                if (inLng) inLng.value = '';
                if (outLat) outLat.value = '';
                if (outLng) outLng.value = '';

                if (coordsText) {
                    coordsText.innerHTML = `<i class="fa-solid fa-triangle-exclamation" style="color: #DC2626;"></i> Browser GPS access was blocked or did not respond. Please allow Location Permission to record attendance.`;
                }
            },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
        );
    } else {
        if (coordsText) {
            coordsText.innerHTML = `<i class="fa-solid fa-circle-xmark" style="color: #DC2626;"></i> Your browser does not support GPS Geolocation.`;
        }
    }

    // 2. Pengendali Hantar Clock-In AJAX dengan GPS Geolocation
    if (clockInForm && btnClockIn) {
        clockInForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const originalBtnText = btnClockIn.innerHTML;
            btnClockIn.disabled = true;
            btnClockIn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Capturing GPS Location...';

            let lat = inLat ? inLat.value : '';
            let lng = inLng ? inLng.value : '';

            // Dapatkan koordinat GPS terkini sebelum menghantar borang (HTML5 Geolocation API)
            if (navigator.geolocation) {
                try {
                    const pos = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        });
                    });
                    lat = pos.coords.latitude;
                    lng = pos.coords.longitude;
                    if (inLat) inLat.value = lat;
                    if (inLng) inLng.value = lng;
                } catch (err) {
                    console.warn('Geolocation fetch error on Clock-In button click:', err);
                }
            }

            if (!lat || !lng || lat === '' || lng === '') {
                App.showToast('error', 'Please enable and allow location access (GPS) in your browser to record attendance.');
                btnClockIn.disabled = false;
                btnClockIn.innerHTML = originalBtnText;
                return;
            }

            const formData = new FormData(clockInForm);
            // Append data GPS terkini ke dalam AJAX POST payload
            formData.set('lat', lat);
            formData.set('lng', lng);

            const result = await App.post('index.php?action=clock_in', formData, btnClockIn);

            if (result && result.status === 'success') {
                // Kemas kini masa masuk tanpa reload
                const displayIn = document.getElementById('displayClockIn');
                if (displayIn) displayIn.textContent = result.data.time;

                // Kemas kini status butang Clock-In
                btnClockIn.disabled = true;
                btnClockIn.classList.add('disabled');
                btnClockIn.innerHTML = '<i class="fa-solid fa-check-double"></i> Clocked In';
                btnClockIn.style.background = '#059669';

                // Aktifkan butang Clock-Out
                if (btnClockOut) {
                    btnClockOut.disabled = false;
                    btnClockOut.classList.remove('disabled');
                    btnClockOut.innerHTML = '<i class="fa-solid fa-sign-out-alt"></i> CLOCK OUT';
                }

                // Tambah baris baru ke jadual kehadiran secara dinamik
                const tbody = document.getElementById('attendanceTableBody');
                if (tbody) {
                    if (tbody.rows.length === 1 && tbody.rows[0].cells.length === 1) {
                        tbody.innerHTML = '';
                    }
                    const newRow = document.createElement('tr');
                    newRow.style.animation = 'fadeIn 0.5s ease';
                    let statusBadge = result.data.is_late 
                        ? '<span class="badge badge-danger">Late</span>'
                        : '<span class="badge badge-success">Present / On Time</span>';
                    if (result.data.is_out_of_range) {
                        statusBadge = '<div style="margin-bottom: 4px;"><span class="badge" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-weight: 700; font-size: 10px;"><i class="fa-solid fa-route"></i> Outside Area</span></div>' + statusBadge;
                    }

                    const displayDate = result.data.date_str || 'Today';
                    const displayName = result.data.user_name || 'You';
                    const displayPosition = result.data.user_position || 'Staff';

                    let gpsButtons = `
                        <button class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px;" onclick="showGpsMap(${result.data.lat}, ${result.data.lng}, '${String(displayName).replace(/'/g, "\\'")}')">
                            <i class="fa-solid fa-map-marker-alt" style="color: #DC2626;"></i> GPS Map
                        </button>
                    `;
                    if (result.data.reason || result.data.photo) {
                        gpsButtons += `
                            <div style="margin-top: 4px;">
                                <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px; background: #EFF6FF; border-color: #BFDBFE; color: #2563EB; display: inline-flex; align-items: center; gap: 4px;" onclick="showReasonPhotoModal('${String(displayName).replace(/'/g, "\\'")}', '${displayDate} (Clock In)', '${String(result.data.reason || '').replace(/'/g, "\\'")}', '${result.data.photo || ''}', ${result.data.is_out_of_range ? 1 : 0})">
                                    <i class="fa-solid fa-camera" style="color: #2563EB;"></i> Clock In Verification
                                </button>
                            </div>
                        `;
                    }

                    newRow.innerHTML = `
                        <td style="font-weight: 600;">${displayDate}</td>
                        <td><strong style="color: var(--text-dark);">${displayName}</strong></td>
                        <td><span style="color: var(--text-muted); font-size: 12px;">${displayPosition}</span></td>
                        <td style="color: var(--success); font-weight: 600;">${result.data.time}</td>
                        <td style="color: var(--warning); font-weight: 600;">-</td>
                        <td>${gpsButtons}</td>
                        <td>${statusBadge}</td>
                    `;
                    tbody.insertBefore(newRow, tbody.firstChild);
                }
            } else if (result && result.status === 'out_of_range') {
                btnClockIn.disabled = false;
                btnClockIn.innerHTML = originalBtnText;
                openRemoteAttendanceModal('clock_in', result.data ? result.data.distance : null, result.data ? result.data.lat : null, result.data ? result.data.lng : null);
            } else {
                btnClockIn.disabled = false;
                btnClockIn.innerHTML = originalBtnText;
            }
        });
    }

    // 3. Pengendali Hantar Clock-Out AJAX dengan GPS Geolocation
    if (clockOutForm && btnClockOut) {
        clockOutForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const originalBtnText = btnClockOut.innerHTML;
            btnClockOut.disabled = true;
            btnClockOut.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Capturing GPS Location...';

            let lat = outLat ? outLat.value : '';
            let lng = outLng ? outLng.value : '';

            if (navigator.geolocation) {
                try {
                    const pos = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        });
                    });
                    lat = pos.coords.latitude;
                    lng = pos.coords.longitude;
                    if (outLat) outLat.value = lat;
                    if (outLng) outLng.value = lng;
                } catch (err) {
                    console.warn('Geolocation fetch error on Clock-Out button click:', err);
                }
            }

            if (!lat || !lng || lat === '' || lng === '') {
                App.showToast('error', 'Please enable and allow location access (GPS) in your browser to record attendance.');
                btnClockOut.disabled = false;
                btnClockOut.innerHTML = originalBtnText;
                return;
            }

            const formData = new FormData(clockOutForm);
            formData.set('lat', lat);
            formData.set('lng', lng);

            const result = await App.post('index.php?action=clock_out', formData, btnClockOut);

            if (result && result.status === 'success') {
                // Kemas kini masa keluar tanpa reload
                const displayOut = document.getElementById('displayClockOut');
                if (displayOut) displayOut.textContent = result.data.time;

                // Kemas kini status butang Clock-Out
                btnClockOut.disabled = true;
                btnClockOut.classList.add('disabled');
                btnClockOut.innerHTML = '<i class="fa-solid fa-check-double"></i> Clocked Out';
                btnClockOut.style.background = '#64748B';

                // Update jadual baris atas
                const tbody = document.getElementById('attendanceTableBody');
                if (tbody && tbody.rows.length > 0) {
                    tbody.rows[0].cells[4].textContent = result.data.time;
                    if (result.data.reason || result.data.photo) {
                        const cell5 = tbody.rows[0].cells[5];
                        if (cell5 && !cell5.innerHTML.includes('Clock Out Verification')) {
                            cell5.innerHTML += `
                                <div style="margin-top: 4px;">
                                    <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px; background: #FFF1F2; border-color: #FECDD3; color: #E11D48; display: inline-flex; align-items: center; gap: 4px;" onclick="showReasonPhotoModal('You', 'Today (Clock Out)', '${String(result.data.reason || '').replace(/'/g, "\\'")}', '${result.data.photo || ''}', ${result.data.is_out_of_range ? 1 : 0})">
                                        <i class="fa-solid fa-camera" style="color: #E11D48;"></i> Clock Out Verification
                                    </button>
                                </div>
                            `;
                        }
                    }
                }
            } else if (result && result.status === 'out_of_range') {
                btnClockOut.disabled = false;
                btnClockOut.innerHTML = originalBtnText;
                openRemoteAttendanceModal('clock_out', result.data ? result.data.distance : null, result.data ? result.data.lat : null, result.data ? result.data.lng : null);
            } else {
                btnClockOut.disabled = false;
                btnClockOut.innerHTML = originalBtnText;
            }
        });
    }

    // 4. Pengendali Borang Simpan Kehadiran Manual oleh Admin (Tanpa GPS API)
    const manualForm = document.getElementById('manualAttendanceForm');
    const btnSaveManual = document.getElementById('btnSaveManual');
    if (manualForm && btnSaveManual) {
        manualForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const elStaff = document.getElementById('manualStaffId');
            const staffId = elStaff ? elStaff.value : null;
            const elDate = document.getElementById('manualDate');
            const dateVal = elDate ? elDate.value : null;
            const elStatus = document.getElementById('manualStatus');
            const statusVal = elStatus ? elStatus.value : null;

            if (!staffId) {
                App.showToast('error', 'Please select a staff member.');
                return;
            }
            if (!dateVal) {
                App.showToast('error', 'Please select the record date.');
                return;
            }

            // Hantar terus ke database TANPA mencetuskan API koordinat GPS
            const formData = new FormData(manualForm);
            const result = await App.post('index.php?action=save_manual_attendance', formData, btnSaveManual);

            if (result && result.status === 'success') {
                App.closeModal('manualAttendanceModal');

                const rec = result.data ? result.data.record : null;
                if (rec) {
                    // Format Tarikh (e.g., 08 Jul 2026)
                    const dParts = new Date(rec.date + 'T00:00:00');
                    const formattedDate = !isNaN(dParts) 
                        ? dParts.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                        : rec.date;

                    // Format Masa Masuk & Keluar
                    const formatTime = (timeStr) => {
                        if (!timeStr) return '-';
                        const t = new Date(timeStr);
                        return !isNaN(t) ? t.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '-';
                    };

                    const clockInFormatted = formatTime(rec.clock_in);
                    const clockOutFormatted = formatTime(rec.clock_out);

                    // Badge Status
                    const stUpper = String(rec.status || '').trim().toUpperCase();
                    let badgeHtml = '<span class="badge badge-success">Present / On Time</span>';
                    if (stUpper === 'MC') {
                        badgeHtml = '<span class="badge" style="background: #E0E7FF; color: #4F46E5; border: 1px solid #C7D2FE; font-weight: 700;"><i class="fa-solid fa-notes-medical"></i> MC</span>';
                    } else if (stUpper === 'LATE') {
                        badgeHtml = '<span class="badge badge-danger">Late</span>';
                    } else if (stUpper === 'HALF_DAY') {
                        badgeHtml = '<span class="badge badge-warning">Half Day</span>';
                    } else if (stUpper === 'ABSENT') {
                        badgeHtml = '<span class="badge badge-danger" style="background: #FEE2E2; color: #DC2626;">Absent</span>';
                    } else if (stUpper === 'LEAVE') {
                        badgeHtml = '<span class="badge badge-info" style="background: #E0F2FE; color: #0284C7;">On Leave</span>';
                    }

                    // Kolum GPS
                    const gpsHtml = (rec.clock_in_lat && rec.clock_in_lng)
                        ? `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px;" onclick="showGpsMap(${rec.clock_in_lat}, ${rec.clock_in_lng}, '${String(rec.name).replace(/'/g, "\\'")}')">
                               <i class="fa-solid fa-map-marker-alt" style="color: #DC2626;"></i> GPS Map
                           </button>`
                        : `<span style="color: var(--text-muted); font-size: 11px;">Manual / No GPS</span>`;

                    // Parameter Butang Edit
                    const cInShort = rec.clock_in ? rec.clock_in.substring(11, 16) : '';
                    const cOutShort = rec.clock_out ? rec.clock_out.substring(11, 16) : '';

                    const newRowHtml = `
                        <td style="font-weight: 600;">${formattedDate}</td>
                        <td><strong style="color: var(--text-dark);">${rec.name || 'Staff'}</strong></td>
                        <td><span style="color: var(--text-muted); font-size: 12px;">${rec.position || 'Staff'}</span></td>
                        <td style="color: var(--success); font-weight: 600;">${clockInFormatted}</td>
                        <td style="color: var(--warning); font-weight: 600;">${clockOutFormatted}</td>
                        <td>${gpsHtml}</td>
                        <td>${badgeHtml}</td>
                        <td style="text-align: right;">
                            <button type="button" class="btn btn-secondary" style="padding: 5px 12px; font-size: 12px; border-radius: 6px;" onclick="openEditManualRecordModal(${rec.id}, ${rec.user_id}, '${rec.date}', '${cInShort}', '${cOutShort}', '${rec.status}')">
                                <i class="fa-solid fa-pen-to-square" style="color: var(--accent-blue);"></i> Edit
                            </button>
                        </td>
                    `;

                    const tbody = document.getElementById('attendanceTableBody');
                    if (tbody) {
                        if (tbody.rows.length === 1 && tbody.rows[0].cells.length === 1) {
                            tbody.innerHTML = '';
                        }

                        const existingRow = document.getElementById('record-row-' + rec.id);
                        if (existingRow) {
                            existingRow.innerHTML = newRowHtml;
                            existingRow.style.animation = 'fadeIn 0.5s ease';
                        } else {
                            const tr = document.createElement('tr');
                            tr.id = 'record-row-' + rec.id;
                            tr.innerHTML = newRowHtml;
                            tr.style.animation = 'fadeIn 0.5s ease';
                            tbody.insertBefore(tr, tbody.firstChild);
                        }
                    }
                }
            }
        });
    }
});

/// Pengendali Perubahan Status di dalam Modal Manual
window.handleManualStatusChange = function(status) {
    try {
        const hintEl = document.getElementById('manualStatusHint');
        if (!hintEl) return;

        const st = String(status || '').toUpperCase();
        if (st === 'MC') {
            hintEl.innerHTML = '<i class="fa-solid fa-circle-info"></i> <strong>Medical Leave:</strong> The record is saved directly without requesting GPS coordinates.';
        } else if (st === 'LEAVE' || st === 'ABSENT') {
            hintEl.innerHTML = `<i class="fa-solid fa-circle-info"></i> <strong>${st}:</strong> This status is recorded directly without GPS verification or mandatory times.`;
        } else {
            hintEl.innerHTML = '<i class="fa-solid fa-circle-info"></i> Clock-in and clock-out times are saved manually without reading GPS coordinates.';
        }
    } catch (e) {
        console.error('handleManualStatusChange error:', e);
    }
};

// Fungsi Global untuk Admin: Buka Modal Tambah Rekod Manual
window.openAddManualRecordModal = function() {
    try {
        const form = document.getElementById('manualAttendanceForm');
        if (form) form.reset();

        const idEl = document.getElementById('manualRecordId');
        const staffEl = document.getElementById('manualStaffId');
        const dateEl = document.getElementById('manualDate');
        const statusEl = document.getElementById('manualStatus');
        const titleEl = document.getElementById('manualModalTitle');
        const subtitleEl = document.getElementById('manualModalSubtitle');
        const clockInEl = document.getElementById('manualClockIn');
        const clockOutEl = document.getElementById('manualClockOut');

        if (idEl) idEl.value = '';
        if (staffEl) staffEl.value = '';
        if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];
        if (clockInEl) clockInEl.value = '';
        if (clockOutEl) clockOutEl.value = '';
        if (statusEl) statusEl.value = 'MC';

        if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-user-clock"></i> Add Manual Record';
        if (subtitleEl) subtitleEl.textContent = 'Direct record entry (MC / Leave / Manual Clock In) without GPS';

        if (typeof window.handleManualStatusChange === 'function') {
            window.handleManualStatusChange('MC');
        }
    } catch (e) {
        console.error('Error in openAddManualRecordModal field setup:', e);
    }

    if (typeof App !== 'undefined' && App.openModal) {
        App.openModal('manualAttendanceModal');
    } else {
        const modal = document.getElementById('manualAttendanceModal');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
};

// Fungsi Global untuk Admin: Buka Modal Edit Rekod Sedia Ada
window.openEditManualRecordModal = function(id, userId, date, clockIn, clockOut, status) {
    try {
        const idEl = document.getElementById('manualRecordId');
        const staffEl = document.getElementById('manualStaffId');
        const dateEl = document.getElementById('manualDate');
        const statusEl = document.getElementById('manualStatus');
        const titleEl = document.getElementById('manualModalTitle');
        const subtitleEl = document.getElementById('manualModalSubtitle');
        const clockInEl = document.getElementById('manualClockIn');
        const clockOutEl = document.getElementById('manualClockOut');

        if (idEl) idEl.value = id || '';
        if (staffEl) staffEl.value = userId || '';
        if (dateEl) dateEl.value = date || '';
        if (clockInEl) clockInEl.value = clockIn || '';
        if (clockOutEl) clockOutEl.value = clockOut || '';
        if (statusEl) statusEl.value = status || 'MC';

        if (titleEl) titleEl.innerHTML = `<i class="fa-solid fa-pen-to-square"></i> Edit Manual Record (#${id})`;
        if (subtitleEl) subtitleEl.textContent = 'Update a staff attendance record manually without GPS verification';

        if (typeof window.handleManualStatusChange === 'function') {
            window.handleManualStatusChange(status || 'MC');
        }
    } catch (e) {
        console.error('Error in openEditManualRecordModal field setup:', e);
    }

    if (typeof App !== 'undefined' && App.openModal) {
        App.openModal('manualAttendanceModal');
    } else {
        const modal = document.getElementById('manualAttendanceModal');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
};

// ==========================================
// PENGENDALIAN KAMERA & KEHADIRAN LUAR KAWASAN (REMOTE / OUT OF RANGE)
// ==========================================
let activeWebcamStream = null;

window.openRemoteAttendanceModal = async function(actionType = 'clock_in', distance = null, lat = null, lng = null) {
    try {
        const actionEl = document.getElementById('remoteActionType');
        const latEl = document.getElementById('remoteLat');
        const lngEl = document.getElementById('remoteLng');
        const photoEl = document.getElementById('remotePhotoData');
        const reasonInput = document.getElementById('remoteReasonInput');
        const bannerTitle = document.getElementById('remoteGpsTitle');
        const bannerDesc = document.getElementById('remoteGpsDesc');

        if (actionEl) actionEl.value = actionType;
        if (photoEl) photoEl.value = '';
        if (reasonInput) reasonInput.value = ''; // Leave blank so staff type their own reason

        // If coordinates/distance unknown, get from inLat/inLng if available
        if (!lat || !lng) {
            const inLat = document.getElementById('inLat');
            const inLng = document.getElementById('inLng');
            lat = (inLat ? inLat.value : '') || '';
            lng = (inLng ? inLng.value : '') || '';
        }
        if (latEl) latEl.value = lat;
        if (lngEl) lngEl.value = lng;

        if (distance && distance > 100) {
            if (bannerTitle) bannerTitle.innerHTML = `📍 You are detected <strong>${distance}m</strong> away from TBBA HQ (> 100m)`;
            if (bannerDesc) bannerDesc.textContent = 'Please state your reason and take a verification selfie before submitting.';
        } else {
            if (bannerTitle) bannerTitle.innerHTML = `📍 Photo Attendance / Remote Clock (${actionType === 'clock_in' ? 'Clock In' : 'Clock Out'})`;
            if (bannerDesc) bannerDesc.textContent = 'Please state your reason and take a verification selfie.';
        }

        // Open modal
        App.openModal('remoteAttendanceModal');

        // Start webcam stream
        await startWebcamStream();
    } catch (e) {
        console.error('Error opening remote attendance modal:', e);
    }
};

window.startWebcamStream = async function() {
    const video = document.getElementById('webcamVideo');
    const preview = document.getElementById('webcamPreview');
    const photoData = document.getElementById('remotePhotoData');
    const btnSnap = document.getElementById('btnSnapPhoto');
    const btnRetake = document.getElementById('btnRetakePhoto');

    if (preview) {
        preview.src = '';
        preview.style.display = 'none';
    }
    if (video) {
        video.style.display = 'block';
        video.style.border = "none";
    }
    if (photoData) photoData.value = '';
    
    if (btnSnap) btnSnap.style.display = 'inline-flex';
    if (btnRetake) btnRetake.style.display = 'none';

    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        try {
            if (activeWebcamStream) {
                activeWebcamStream.getTracks().forEach(t => t.stop());
            }
            activeWebcamStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false
            });
            if (video) {
                video.srcObject = activeWebcamStream;
            }
        } catch (err) {
            console.warn('Webcam permission error or not available:', err);
            App.showToast('warning', 'Camera is inaccessible or blocked by your browser. Please allow camera access to take a photo.');
        }
    } else {
        App.showToast('warning', 'Your browser does not support live camera capture.');
    }
};

window.snapWebcamPhoto = function() {
    const video = document.getElementById('webcamVideo');
    const preview = document.getElementById('webcamPreview');
    const photoData = document.getElementById('remotePhotoData');
    const btnSnap = document.getElementById('btnSnapPhoto');
    const btnRetake = document.getElementById('btnRetakePhoto');

    if (!video || !activeWebcamStream) {
        App.showToast('error', 'Camera is not active. Please allow camera access.');
        return;
    }

    // Guna resolution sebenar video tetapi elakkan gambar terlampau besar (hadkan 800px)
    let vWidth = video.videoWidth;
    let vHeight = video.videoHeight;
    const maxDim = 800;
    
    if (vWidth > maxDim || vHeight > maxDim) {
        if (vWidth > vHeight) {
            vHeight = Math.round((vHeight / vWidth) * maxDim);
            vWidth = maxDim;
        } else {
            vWidth = Math.round((vWidth / vHeight) * maxDim);
            vHeight = maxDim;
        }
    }

    const canvas = document.createElement('canvas');
    canvas.width = vWidth;
    canvas.height = vHeight;
    const ctx = canvas.getContext('2d');

    // Draw terus tanpa manual crop. CSS object-fit: cover pada <img> akan uruskan paparan yang sama seperti video.
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
    if (photoData) photoData.value = dataUrl;

    if (preview) {
        preview.src = dataUrl;
        preview.style.display = 'block';
    }
    video.style.display = 'none';

    if (btnSnap) btnSnap.style.display = 'none';
    if (btnRetake) btnRetake.style.display = 'inline-flex';
};

window.retakeWebcamPhoto = function() {
    const photoData = document.getElementById('remotePhotoData');
    if (photoData) photoData.value = '';
    startWebcamStream();
};

window.closeRemoteAttendanceModal = function() {
    if (activeWebcamStream) {
        activeWebcamStream.getTracks().forEach(t => t.stop());
        activeWebcamStream = null;
    }
    App.closeModal('remoteAttendanceModal');
};

window.submitRemoteAttendance = async function() {
    const form = document.getElementById('remoteAttendanceForm');
    const btnSubmit = document.getElementById('btnSubmitRemote');
    const photoDataEl = document.getElementById('remotePhotoData');
    const photoData = photoDataEl ? photoDataEl.value : null;
    const reasonInputEl = document.getElementById('remoteReasonInput');
    const reasonInput = reasonInputEl ? reasonInputEl.value : null;
    const actionTypeEl = document.getElementById('remoteActionType');
    const actionType = (actionTypeEl ? actionTypeEl.value : '') || 'clock_in';

    if (!photoData || photoData.length < 50) {
        App.showToast('error', "Please click 'Snap Photo' first to capture your verification selfie.");
        return;
    }

    const reason = reasonInput ? reasonInput.trim() : '';
    if (!reason) {
        App.showToast('error', 'Please enter your reason for attendance outside the office area.');
        const reasonField = document.getElementById('remoteReasonInput');
        if (reasonField) reasonField.focus();
        return;
    }

    const formData = new FormData(form);
    formData.set('reason', reason);
    formData.set('photo', photoData);

    const url = actionType === 'clock_in' ? 'index.php?action=clock_in' : 'index.php?action=clock_out';
    const result = await App.post(url, formData, btnSubmit);

    if (result && result.status === 'success') {
        closeRemoteAttendanceModal();

        // Seamless update without reloading
        App.showToast('success', result.message || 'Remote attendance recorded successfully!');
        
        if (actionType === 'clock_in') {
            const displayIn = document.getElementById('displayClockIn');
            if (displayIn) displayIn.textContent = result.data.time;
            
            const btnClockIn = document.getElementById('btnClockIn');
            if (btnClockIn) {
                btnClockIn.disabled = true;
                btnClockIn.classList.add('disabled');
                btnClockIn.innerHTML = '<i class="fa-solid fa-check-double"></i> Clocked In';
                btnClockIn.style.background = '#64748B';
            }
            
            const tbody = document.getElementById('attendanceTableBody');
            if (tbody) {
                if (tbody.rows.length === 1 && tbody.rows[0].cells.length === 1) {
                    tbody.innerHTML = '';
                }
                const newRow = document.createElement('tr');
                const displayName = result.data.name || 'You';
                const displayPosition = result.data.position || 'Staff';
                const displayDate = new Date().toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
                
                const gpsButtons = `
                    <button class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px;" onclick="showGpsMap(${result.data.lat}, ${result.data.lng}, '${displayName.replace(/'/g, "\\'")}')"><i class="fa-solid fa-map-marker-alt" style="color: #DC2626;"></i> GPS Map</button>
                    <div style="margin-top: 4px;">
                        <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px; background: #EFF6FF; border-color: #BFDBFE; color: #2563EB; display: inline-flex; align-items: center; gap: 4px;" onclick="showReasonPhotoModal('You', 'Today (Clock In)', '${String(result.data.reason || '').replace(/'/g, "\\'")}', '${result.data.photo || ''}', 1)">
                            <i class="fa-solid fa-camera" style="color: #2563EB;"></i> Clock In Verification
                        </button>
                    </div>`;
                
                let statusBadge = '<span class="badge badge-success">Present / On Time</span>';
                if (result.data.status === 'LATE') statusBadge = '<span class="badge badge-danger">Late</span>';
                
                newRow.innerHTML = `
                    <td style="font-weight: 600;">${displayDate}</td>
                    <td><strong style="color: var(--text-dark);">${displayName}</strong></td>
                    <td><span style="color: var(--text-muted); font-size: 12px;">${displayPosition}</span></td>
                    <td style="color: var(--success); font-weight: 600;">${result.data.time}</td>
                    <td style="color: var(--warning); font-weight: 600;">-</td>
                    <td><div style="margin-bottom: 4px;"><span class="badge" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-weight: 700; font-size: 10px;"><i class="fa-solid fa-route"></i> Outside Area</span></div>${gpsButtons}</td>
                    <td>${statusBadge}</td>
                    ${document.querySelector('th:last-child').textContent.includes('Actions') ? '<td></td>' : ''}
                `;
                tbody.insertBefore(newRow, tbody.firstChild);
            }
        } else {
            const displayOut = document.getElementById('displayClockOut');
            if (displayOut) displayOut.textContent = result.data.time;

            const btnClockOut = document.getElementById('btnClockOut');
            if (btnClockOut) {
                btnClockOut.disabled = true;
                btnClockOut.classList.add('disabled');
                btnClockOut.innerHTML = '<i class="fa-solid fa-check-double"></i> Clocked Out';
                btnClockOut.style.background = '#64748B';
            }

            const tbody = document.getElementById('attendanceTableBody');
            if (tbody && tbody.rows.length > 0) {
                tbody.rows[0].cells[4].textContent = result.data.time;
                const cell5 = tbody.rows[0].cells[5];
                if (cell5 && !cell5.innerHTML.includes('Clock Out Verification')) {
                    cell5.innerHTML += `
                        <div style="margin-top: 4px;">
                            <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px; background: #FFF1F2; border-color: #FECDD3; color: #E11D48; display: inline-flex; align-items: center; gap: 4px;" onclick="showReasonPhotoModal('You', 'Today (Clock Out)', '${String(result.data.reason || '').replace(/'/g, "\\'")}', '${result.data.photo || ''}', 1)">
                                <i class="fa-solid fa-camera" style="color: #E11D48;"></i> Clock Out Verification
                            </button>
                        </div>
                    `;
                }
            }
        }
    }
};
