<?php
/**
 * Calendar & Events View — TBBA ERP Module 9
 */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<!-- FullCalendar CDN -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" integrity="sha384-B1OFx8Gy9GjPu8UbUyXbGQpzll9ubAUQ9agInFJ8NnD7nYG1u/CLR+Sqr5yifl4q" crossorigin="anonymous"></script>

<div class="page-content" style="padding: 24px;">
    <!-- Action Bar -->
    <div class="card" style="padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; background: var(--bg-card);">
        <div>
            <h3 style="margin: 0; font-size: 18px; color: var(--text-dark);"><i class="fa-solid fa-calendar-days" style="color: #2563EB;"></i> Company Schedule & Events</h3>
            <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">View company meetings, holidays, project deadlines, and public schedules.</p>
        </div>
        <div>
            <?php if ($canCreate): ?>
            <button class="btn btn-primary" onclick="openNewEventModal()" style="padding: 10px 18px; font-weight: 600; border-radius: 8px; background: #2563EB; color: #fff; border: none; cursor: pointer;">
                <i class="fa-solid fa-plus"></i> Add Event
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Calendar Layout Grid -->
    <div style="display: grid; grid-template-columns: 1fr 320px; gap: 24px; align-items: start;" id="calendarLayoutGrid">
        <!-- Main Calendar Container -->
        <div class="card" style="padding: 24px; border-radius: 12px; background: var(--bg-card); min-height: 680px;">
            <div id="fullcalendar" style="width: 100%; min-height: 640px;"></div>
        </div>

        <!-- Right Side Sidebar: Upcoming & Legend -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <div class="card" style="padding: 20px; border-radius: 12px; background: var(--bg-card);">
                <h4 style="margin: 0 0 16px; font-size: 15px; color: var(--text-dark);"><i class="fa-solid fa-clock" style="color: #2563EB;"></i> Upcoming Schedule</h4>
                <?php if (empty($upcoming)): ?>
                <p style="font-size: 13px; color: var(--text-muted); text-align: center; margin: 20px 0;">No upcoming events scheduled.</p>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($upcoming as $ue): ?>
                    <div style="padding: 12px; border-radius: 8px; border-left: 4px solid <?= $ue['color'] ?? '#2563EB' ?>; background: var(--bg-primary);">
                        <strong style="font-size: 13px; color: var(--text-dark); display: block;"><?= htmlspecialchars($ue['title']) ?></strong>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                            <i class="fa-solid fa-calendar"></i> <?= date('d M Y, h:i A', strtotime($ue['start_datetime'])) ?>
                        </div>
                        <?php if ($ue['location']): ?>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                            <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($ue['location']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Legend & Categories -->
            <div class="card" style="padding: 20px; border-radius: 12px; background: var(--bg-card);">
                <h4 style="margin: 0 0 12px; font-size: 15px; color: var(--text-dark);"><i class="fa-solid fa-palette" style="color: #10B981;"></i> Event Categories</h4>
                <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px; color: var(--text-dark);">
                    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 12px; height: 12px; border-radius: 50%; background: #2563EB;"></span> Meeting / Conference</div>
                    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 12px; height: 12px; border-radius: 50%; background: #10B981;"></span> Public Holiday</div>
                    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 12px; height: 12px; border-radius: 50%; background: #D97706;"></span> Company Event</div>
                    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 12px; height: 12px; border-radius: 50%; background: #9333EA;"></span> Training / Workshop</div>
                    <div style="display: flex; align-items: center; gap: 8px;"><span style="width: 12px; height: 12px; border-radius: 50%; background: #EF4444;"></span> Urgent / Deadline</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add/Edit Event -->
<div class="modal-overlay" id="newEventModal">
    <div class="modal-box" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title" id="eventModalTitle"><i class="fa-solid fa-calendar-plus" style="color: #2563EB;"></i> Create Calendar Event</h3>
            <button class="modal-close" onclick="App.closeModal('newEventModal')">&times;</button>
        </div>
        <form id="eventForm" onsubmit="submitEvent(event)">
            <input type="hidden" name="id" id="eventId" value="0">
            <div class="modal-body" style="padding: 20px;">
                <div class="profile-input-group" style="margin-bottom: 16px;">
                    <label class="profile-label"><input type="checkbox" name="is_public" id="evIsPublic" value="1" checked> Make this a Company-wide Public Event</label>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Event Title <span style="color: #EF4444;">*</span></label>
                    <input type="text" name="title" id="evTitle" required placeholder="e.g. Q3 Management Review Meeting" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Start Date & Time <span style="color: #EF4444;">*</span></label>
                        <input type="datetime-local" name="start_datetime" id="evStart" required value="<?= date('Y-m-d\T09:00') ?>" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">End Date & Time <span style="color: #EF4444;">*</span></label>
                        <input type="datetime-local" name="end_datetime" id="evEnd" required value="<?= date('Y-m-d\T17:00') ?>" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Type</label>
                        <select name="type" id="evType" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                            <option value="meeting">Meeting</option>
                            <option value="holiday">Public Holiday</option>
                            <option value="company">Company Event</option>
                            <option value="training">Training</option>
                            <option value="deadline">Deadline</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Color Tag</label>
                        <input type="color" name="color" id="evColor" value="#2563EB" style="width: 100%; height: 42px; border-radius: 8px; border: 1px solid var(--border-color); padding: 2px; background: var(--bg-primary); cursor: pointer;">
                    </div>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Location / Virtual Link</label>
                    <input type="text" name="location" id="evLocation" placeholder="e.g. Main Boardroom or Google Meet link" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark);">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-dark);">Description</label>
                    <textarea name="description" id="evDesc" rows="3" placeholder="Meeting agenda or event details..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-dark); resize: vertical;"></textarea>
                </div>
                
                <!-- Event Media Gallery (Google Drive) -->
                <div id="mediaGallerySection" style="display: none; margin-top: 20px; padding-top: 16px; border-top: 1px dashed var(--border-color);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <label style="font-size: 14px; font-weight: 700; color: #10B981; margin: 0;"><i class="fa-brands fa-google-drive"></i> Event Media Gallery</label>
                        <button type="button" class="btn btn-sm" id="btnCreateGallery" onclick="createMediaGallery()" style="background: #10B981; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">Create Gallery</button>
                    </div>
                    <div id="mediaGalleryContent" style="background: var(--bg-primary); padding: 16px; border-radius: 8px; text-align: center; font-size: 12px; color: var(--text-muted);">
                        Loading gallery...
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <?php if ($canDelete): ?>
                    <button type="button" class="btn btn-danger" id="btnDeleteEvent" onclick="deleteCurrentEvent()" style="display: none; padding: 8px 16px; border-radius: 6px; background: #EF4444; color: #fff; border: none; font-weight: 600; cursor: pointer;">Delete</button>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-secondary" onclick="App.closeModal('newEventModal')" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitEvent" style="padding: 8px 16px; border-radius: 6px; background: #2563EB; color: #fff; border: none; font-weight: 600; cursor: pointer;">Save Event</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let calendarInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('fullcalendar');
    calendarInstance = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        height: 'auto',
        events: async function(info, successCallback, failureCallback) {
            try {
                const res = await fetch(`index.php?action=get_events&start=${info.startStr}&end=${info.endStr}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.status === 'success') {
                    const evs = data.data.map(item => ({
                        id: item.id,
                        title: item.title,
                        start: item.start_datetime ? item.start_datetime.replace(' ', 'T') : null,
                        end: item.end_datetime ? item.end_datetime.replace(' ', 'T') : null,
                        backgroundColor: item.color || '#2563EB',
                        borderColor: item.color || '#2563EB',
                        extendedProps: item
                    }));
                    successCallback(evs);
                } else failureCallback();
            } catch (e) { failureCallback(); }
        },
        eventClick: function(info) {
            const ev = info.event.extendedProps;
            openEditEvent(ev);
        }
    });
    calendarInstance.render();
});

async function submitEvent(e) {
    e.preventDefault();
    const form = document.getElementById('eventForm');
    const formData = new FormData(form);
    const id = document.getElementById('eventId').value;
    const action = id > 0 ? 'update_event' : 'add_event';
    const btn = document.getElementById('btnSubmitEvent');
    const res = await App.post(`index.php?action=${action}`, formData, btn);
    if (res && res.status === 'success') {
        App.closeModal('newEventModal');
        if (calendarInstance) calendarInstance.refetchEvents();
        setTimeout(() => location.reload(), 800);
    }
}

function openNewEventModal() {
    const form = document.getElementById('eventForm');
    if (form) form.reset();
    document.getElementById('eventId').value = 0;
    document.getElementById('evStart').value = '<?= date('Y-m-d\T09:00') ?>';
    document.getElementById('evEnd').value = '<?= date('Y-m-d\T17:00') ?>';
    document.getElementById('eventModalTitle').innerHTML = '<i class="fa-solid fa-calendar-plus" style="color: #2563EB;"></i> Create Calendar Event';
    const delBtn = document.getElementById('btnDeleteEvent');
    if (delBtn) delBtn.style.display = 'none';
    
    const gallerySec = document.getElementById('mediaGallerySection');
    if (gallerySec) gallerySec.style.display = 'none';

    App.openModal('newEventModal');
}

function openEditEvent(ev) {
    document.getElementById('eventId').value = ev.id;
    document.getElementById('evTitle').value = ev.title;
    document.getElementById('evStart').value = ev.start_datetime ? ev.start_datetime.substring(0, 16) : '';
    document.getElementById('evEnd').value = ev.end_datetime ? ev.end_datetime.substring(0, 16) : '';
    document.getElementById('evType').value = ev.type || 'meeting';
    document.getElementById('evColor').value = ev.color || '#2563EB';
    document.getElementById('evLocation').value = ev.location || '';
    document.getElementById('evDesc').value = ev.description || '';
    document.getElementById('eventModalTitle').innerHTML = '<i class="fa-solid fa-edit" style="color:#2563EB;"></i> Edit Calendar Event';
    const delBtn = document.getElementById('btnDeleteEvent');
    if (delBtn) delBtn.style.display = 'inline-block';
    
    const gallerySec = document.getElementById('mediaGallerySection');
    if (gallerySec) {
        gallerySec.style.display = 'block';
        if (ev.gdrive_folder_id) {
            document.getElementById('btnCreateGallery').style.display = 'none';
            loadMediaGallery(ev.id);
        } else {
            document.getElementById('btnCreateGallery').style.display = 'inline-block';
            document.getElementById('btnCreateGallery').innerHTML = 'Create Gallery';
            document.getElementById('btnCreateGallery').disabled = false;
            document.getElementById('mediaGalleryContent').innerHTML = '<p>No media gallery created yet. Click "Create Gallery" to generate a Google Drive folder.</p>';
        }
    }

    App.openModal('newEventModal');
}

async function deleteCurrentEvent() {
    const id = document.getElementById('eventId').value;
    if (!id || !await App.confirm('Delete Event', 'Are you sure you want to delete this event?')) return;
    const formData = new FormData(); formData.append('id', id);
    const res = await App.post('index.php?action=delete_event', formData);
    if (res && res.status === 'success') {
        App.closeModal('newEventModal');
        setTimeout(() => location.reload(), 800);
    }
}

async function createMediaGallery() {
    const id = document.getElementById('eventId').value;
    if (!id || id == 0) return;
    
    document.getElementById('btnCreateGallery').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
    document.getElementById('btnCreateGallery').disabled = true;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', '<?= Helper::csrfToken() ?>');
    
    try {
        const res = await App.post('index.php?action=create_event_gallery', formData);
        if (res && res.status === 'success') {
            if (calendarInstance) calendarInstance.refetchEvents();
            loadMediaGallery(id);
            document.getElementById('btnCreateGallery').style.display = 'none';
        } else {
            document.getElementById('btnCreateGallery').innerHTML = 'Create Gallery';
            document.getElementById('btnCreateGallery').disabled = false;
        }
    } catch (e) {
        alert('Failed to create gallery.');
        document.getElementById('btnCreateGallery').innerHTML = 'Create Gallery';
        document.getElementById('btnCreateGallery').disabled = false;
    }
}

async function loadMediaGallery(id) {
    const content = document.getElementById('mediaGalleryContent');
    content.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Fetching images from Google Drive...';
    try {
        const res = await fetch(`index.php?action=get_event_gallery&id=${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        
        if (data.status === 'success') {
            const { folder_link, images } = data.data;
            let html = `
                <div style="display:flex; justify-content:space-between; margin-bottom: 12px; align-items:center;">
                    <span style="font-weight:600; color:var(--text-dark);">${images.length} Image(s) found</span>
                    <a href="${safeGalleryUrl(folder_link)}" target="_blank" rel="noopener" style="background:#2563EB; color:white; padding: 4px 10px; border-radius:4px; text-decoration:none; font-size:11px; font-weight:600;"><i class="fa-solid fa-cloud-arrow-up"></i> Upload to Drive</a>
                </div>
            `;
            
            if (images.length === 0) {
                html += `<div style="padding: 20px; border: 2px dashed var(--border-color); border-radius: 8px;">Folder is empty. Click Upload to add photos!</div>`;
            } else {
                html += `<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 8px;">`;
                images.forEach(img => {
                    html += `
                        <a href="${safeGalleryUrl(img.viewUrl)}" target="_blank" rel="noopener" title="${escapeCalendarHtml(img.name)}" style="display:block; border-radius:6px; overflow:hidden; border:1px solid var(--border-color);">
                            <img src="${safeGalleryUrl(img.thumbnail)}" alt="${escapeCalendarHtml(img.name)}" style="width:100%; height:80px; object-fit:cover; display:block;">
                        </a>
                    `;
                });
                html += `</div>`;
            }
            content.innerHTML = html;
        } else {
            content.innerHTML = `<span style="color:#EF4444;"><i class="fa-solid fa-triangle-exclamation"></i> ${escapeCalendarHtml(data.message)}</span>`;
            document.getElementById('btnCreateGallery').style.display = 'inline-block';
            document.getElementById('btnCreateGallery').innerHTML = 'Retry Create Gallery';
            document.getElementById('btnCreateGallery').disabled = false;
        }
    } catch (e) {
        content.innerHTML = '<span style="color:#EF4444;">Connection error fetching gallery.</span>';
    }
}

function escapeCalendarHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[character]);
}

function safeGalleryUrl(value) {
    try {
        const url = new URL(String(value ?? ''));
        return url.protocol === 'https:' ? escapeCalendarHtml(url.href) : '#';
    } catch (error) {
        return '#';
    }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
