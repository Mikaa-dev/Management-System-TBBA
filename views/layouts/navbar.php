<?php
/**
 * Templat Top Navbar & Status Masa Nyata
 * Syarikat: The Bridge Business Alliance (TBBA)
 */
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../models/Announcement.php';
require_once __DIR__ . '/../../models/Notification.php';

// Check upcoming events (today or tomorrow)
$navEvents = [];
try {
    $nowStart = date('Y-m-d 00:00:00');
    $tomorrowEnd = date('Y-m-d 23:59:59', strtotime('+1 day'));
    $navEvents = Database::query(
        "SELECT * FROM `events` WHERE `start_datetime` BETWEEN ? AND ? ORDER BY `start_datetime` ASC",
        [$nowStart, $tomorrowEnd]
    )->fetchAll();
} catch (Exception $e) {}

// Check recent announcements
$navAnnouncements = [];
try {
    $navAnnouncements = Announcement::getRecent(3);
} catch (Exception $e) {}

// Fetch user's read receipts
$readEvents = [];
$readAnns = [];
$navStoredNotifications = [];
try {
    $userId = (int)(Auth::id() ?? 0);
    $readReceipts = Database::query("SELECT item_type, item_id FROM `user_read_receipts` WHERE `user_id`=?", [$userId])->fetchAll();
    foreach ($readReceipts as $rr) {
        if ($rr['item_type'] === 'event') $readEvents[] = $rr['item_id'];
        if ($rr['item_type'] === 'announcement') $readAnns[] = $rr['item_id'];
    }
    $navStoredNotifications = Notification::getForUser($userId, 10, true);
} catch (Exception $e) {}

// Filter out read items
$navEvents = array_filter($navEvents, fn($e) => !in_array($e['id'], $readEvents));
$navAnnouncements = array_filter($navAnnouncements, fn($a) => !in_array($a['id'], $readAnns));

$navNotifCount = count($navEvents) + count($navAnnouncements) + count($navStoredNotifications);
?>
<div class="main-wrapper">
    <!-- Topbar Navigation -->
    <header class="navbar">
        <div class="navbar-left">
            <button class="btn btn-secondary" id="sidebarToggle" style="padding: 8px 12px; display: none;" title="Toggle Navigation">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
        </div>

        <div class="navbar-right">
            <!-- Sensor GPS & Jam Masa Nyata -->
            <div class="live-clock-badge">
                <span class="gps-badge-item" style="display: flex; align-items: center; gap: 6px; color: #059669; font-weight: 700;">
                    <i class="fa-solid fa-satellite-dish" id="gpsStatusIcon" title="GPS Status"></i>
                    <span id="gpsStatusText">GPS ACTIVE</span>
                </span>
                <span class="clock-divider" style="color: #CBD5E1;">|</span>
                <span class="clock-badge-item" style="display: flex; align-items: center; gap: 6px; color: #475569;">
                    <i class="fa-regular fa-clock" style="color: #2563EB;"></i>
                    <span id="liveClockDisplay"><?= date('d M Y, h:i A') ?></span>
                </span>
            </div>

            <!-- Mobile App PWA Install Button -->
            <button class="btn btn-primary pwa-install-trigger" onclick="App.installPWA()" style="display: none; padding: 6px 14px; font-size: 12px; background: linear-gradient(135deg, #2563EB 0%, #1E3A8A 100%); color: #ffffff; border-radius: 50px; border: none; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(37,99,235,0.3); font-weight: 600; cursor: pointer;" title="Install to Mobile Home Screen">
                <i class="fa-solid fa-mobile-screen-button"></i>
                <span>Install App</span>
            </button>

            <!-- Notifications Bell -->
            <div class="nav-notifications" style="position: relative; margin-right: 15px;">
                <button class="btn btn-secondary" id="notifToggle" style="padding: 8px 12px; border-radius: 50%; border: none; background: #F1F5F9; color: #475569; position: relative; cursor: pointer;">
                    <i class="fa-solid fa-bell"></i>
                    <span id="navNotifBadge" style="display: <?= $navNotifCount > 0 ? 'inline-block' : 'none' ?>; position: absolute; top: -5px; right: -5px; background: #EF4444; color: white; font-size: 10px; font-weight: bold; padding: 2px 5px; border-radius: 10px; min-width: 18px; text-align: center; border: 2px solid white;">
                        <?= $navNotifCount ?>
                    </span>
                </button>
                
                <!-- Dropdown -->
                <div id="notifDropdown" style="display: none; position: absolute; right: 0; top: 120%; width: 340px; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid #E2E8F0; z-index: 1000; overflow: hidden;">
                    <div style="padding: 12px 16px; background: #F8FAFC; border-bottom: 1px solid #E2E8F0; font-weight: 600; color: #1E293B; display: flex; justify-content: space-between; align-items: center;">
                        <span>Notifications</span>
                        <span id="navNotifBadgeText" style="display: <?= $navNotifCount > 0 ? 'inline-block' : 'none' ?>; background: #E0E7FF; color: #4F46E5; font-size: 11px; padding: 2px 8px; border-radius: 10px;"><?= $navNotifCount ?> New</span>
                    </div>
                    <div id="notifListContainer" style="max-height: 300px; overflow-y: auto;">
                        <div id="noNotifMessage" style="padding: 20px; text-align: center; color: #94A3B8; font-size: 13px; display: <?= $navNotifCount === 0 ? 'block' : 'none' ?>;">
                            <i class="fa-regular fa-bell-slash" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
                            No new notifications
                        </div>

                        <?php foreach ($navStoredNotifications as $notification):
                            $notificationColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $notification['color'] ?? '') ? $notification['color'] : '#2563EB';
                            $notificationIcon = preg_replace('/[^a-zA-Z0-9_-]/', '', $notification['icon'] ?? 'fa-bell');
                            $notificationLink = $notification['link'] ?: 'index.php?page=notifications';
                        ?>
                            <div class="nav-notif-item" data-type="stored" data-id="<?= (int)$notification['id'] ?>" style="display:flex;align-items:flex-start;padding:12px 16px;border-bottom:1px solid #F1F5F9;transition:background .2s;position:relative;">
                                <a href="<?= htmlspecialchars($notificationLink) ?>" onclick="markStoredNotifAsRead(<?= (int)$notification['id'] ?>, null)" style="display:flex;gap:12px;text-decoration:none;flex-grow:1;">
                                    <div style="width:36px;height:36px;border-radius:8px;background:<?= $notificationColor ?>18;color:<?= $notificationColor ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="fa-solid <?= htmlspecialchars($notificationIcon) ?>"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:13px;font-weight:600;color:#334155;margin-bottom:2px;line-height:1.3;padding-right:20px;"><?= htmlspecialchars($notification['title']) ?></div>
                                        <div style="font-size:11px;color:#64748B;line-height:1.4;"><?= htmlspecialchars($notification['body']) ?></div>
                                        <div style="font-size:10px;color:#94A3B8;margin-top:4px;"><?= date('d M, h:i A', strtotime($notification['created_at'])) ?></div>
                                    </div>
                                </a>
                                <button onclick="markStoredNotifAsRead(<?= (int)$notification['id'] ?>, this)" title="Mark as read" style="position:absolute;right:10px;top:12px;background:none;border:none;color:#94A3B8;cursor:pointer;padding:4px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                    <i class="fa-solid fa-check" style="font-size:12px;"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php foreach ($navEvents as $ev): ?>
                            <div class="nav-notif-item" data-type="event" data-id="<?= $ev['id'] ?>" style="display: flex; align-items: flex-start; padding: 12px 16px; border-bottom: 1px solid #F1F5F9; transition: background 0.2s; position: relative;">
                                <a href="index.php?page=calendar" style="display: flex; gap: 12px; text-decoration: none; flex-grow: 1;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #FFF7ED; color: #F97316; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="fa-regular fa-calendar-check"></i>
                                    </div>
                                    <div>
                                        <div style="font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 2px; line-height: 1.3; padding-right: 20px;"><?= htmlspecialchars($ev['title']) ?></div>
                                        <div style="font-size: 11px; color: #64748B;">Upcoming Event &bull; <?= date('d M', strtotime($ev['start_datetime'])) ?></div>
                                    </div>
                                </a>
                                <button onclick="markNotifAsRead('event', <?= $ev['id'] ?>, this)" title="Mark as read" style="position: absolute; right: 10px; top: 12px; background: none; border: none; color: #94A3B8; cursor: pointer; padding: 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-check" style="font-size: 12px;"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php foreach ($navAnnouncements as $an): ?>
                            <div class="nav-notif-item" data-type="announcement" data-id="<?= $an['id'] ?>" style="display: flex; align-items: flex-start; padding: 12px 16px; border-bottom: 1px solid #F1F5F9; transition: background 0.2s; position: relative;">
                                <a href="index.php?page=announcements" style="display: flex; gap: 12px; text-decoration: none; flex-grow: 1;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #EFF6FF; color: #3B82F6; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="fa-solid fa-bullhorn"></i>
                                    </div>
                                    <div>
                                        <div style="font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 2px; line-height: 1.3; padding-right: 20px;"><?= htmlspecialchars($an['title']) ?></div>
                                        <div style="font-size: 11px; color: #64748B;">New Announcement &bull; <?= date('d M', strtotime($an['created_at'])) ?></div>
                                    </div>
                                </a>
                                <button onclick="markNotifAsRead('announcement', <?= $an['id'] ?>, this)" title="Mark as read" style="position: absolute; right: 10px; top: 12px; background: none; border: none; color: #94A3B8; cursor: pointer; padding: 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-check" style="font-size: 12px;"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <script>
                document.getElementById('notifToggle')?.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dropdown = document.getElementById('notifDropdown');
                    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
                    if (dropdown.style.display === 'block') refreshStoredNotifications();
                });
                document.addEventListener('click', function(e) {
                    const dropdown = document.getElementById('notifDropdown');
                    const toggle = document.getElementById('notifToggle');
                    if (dropdown && !dropdown.contains(e.target) && !toggle.contains(e.target)) {
                        dropdown.style.display = 'none';
                    }
                });

                // Server-side Mark as Read Logic via AJAX
                function updateNotifBadgeCount() {
                    const items = document.querySelectorAll('.nav-notif-item');
                    let visibleCount = 0;
                    items.forEach(item => {
                        if (item.style.display !== 'none') visibleCount++;
                    });
                    
                    const badge = document.getElementById('navNotifBadge');
                    const badgeText = document.getElementById('navNotifBadgeText');
                    const emptyMsg = document.getElementById('noNotifMessage');
                    
                    if (visibleCount > 0) {
                        if(badge) { badge.style.display = 'inline-block'; badge.innerText = visibleCount; }
                        if(badgeText) { badgeText.style.display = 'inline-block'; badgeText.innerText = visibleCount + ' New'; }
                        if(emptyMsg) emptyMsg.style.display = 'none';
                    } else {
                        if(badge) badge.style.display = 'none';
                        if(badgeText) badgeText.style.display = 'none';
                        if(emptyMsg) emptyMsg.style.display = 'block';
                    }
                }

                function markNotifAsRead(type, id, btnElement) {
                    // Hide the element and update badge immediately for better UX
                    if (btnElement) {
                        const item = btnElement.closest('.nav-notif-item');
                        if (item) {
                            item.style.display = 'none';
                            updateNotifBadgeCount();
                        }
                    }

                    // Send AJAX request to save receipt in database
                    const formData = new FormData();
                    formData.append('type', type);
                    formData.append('id', id);

                    fetch('index.php?action=mark_receipt_read', {
                        method: 'POST',
                        body: formData
                    }).catch(err => console.error('Error marking as read:', err));
                }

                function markStoredNotifAsRead(id, btnElement) {
                    if (btnElement) {
                        const item = btnElement.closest('.nav-notif-item');
                        if (item) {
                            item.style.display = 'none';
                            updateNotifBadgeCount();
                        }
                    }

                    const formData = new FormData();
                    formData.append('id', id);
                    fetch('index.php?action=mark_read_notification', {
                        method: 'POST',
                        body: formData,
                        keepalive: true
                    }).catch(err => console.error('Error marking notification as read:', err));
                }

                function buildStoredNotificationItem(notification) {
                    const item = document.createElement('div');
                    item.className = 'nav-notif-item';
                    item.dataset.type = 'stored';
                    item.dataset.id = notification.id;
                    item.style.cssText = 'display:flex;align-items:flex-start;padding:12px 16px;border-bottom:1px solid #F1F5F9;transition:background .2s;position:relative;';

                    const color = /^#[0-9A-Fa-f]{6}$/.test(notification.color || '') ? notification.color : '#2563EB';
                    const iconClass = /^[a-zA-Z0-9_-]+$/.test(notification.icon || '') ? notification.icon : 'fa-bell';
                    const link = document.createElement('a');
                    link.href = notification.link || 'index.php?page=notifications';
                    link.style.cssText = 'display:flex;gap:12px;text-decoration:none;flex-grow:1;';
                    link.addEventListener('click', () => markStoredNotifAsRead(notification.id, null));

                    const icon = document.createElement('div');
                    icon.style.cssText = `width:36px;height:36px;border-radius:8px;background:${color}18;color:${color};display:flex;align-items:center;justify-content:center;flex-shrink:0;`;
                    icon.innerHTML = `<i class="fa-solid ${iconClass}"></i>`;

                    const content = document.createElement('div');
                    const title = document.createElement('div');
                    title.style.cssText = 'font-size:13px;font-weight:600;color:#334155;margin-bottom:2px;line-height:1.3;padding-right:20px;';
                    title.textContent = notification.title || 'Notification';
                    const body = document.createElement('div');
                    body.style.cssText = 'font-size:11px;color:#64748B;line-height:1.4;';
                    body.textContent = notification.body || '';
                    const time = document.createElement('div');
                    time.style.cssText = 'font-size:10px;color:#94A3B8;margin-top:4px;';
                    const parsedDate = new Date(String(notification.created_at || '').replace(' ', 'T'));
                    time.textContent = Number.isNaN(parsedDate.getTime()) ? '' : parsedDate.toLocaleString('en-GB', {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});
                    content.append(title, body, time);
                    link.append(icon, content);

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.title = 'Mark as read';
                    button.style.cssText = 'position:absolute;right:10px;top:12px;background:none;border:none;color:#94A3B8;cursor:pointer;padding:4px;border-radius:50%;display:flex;align-items:center;justify-content:center;';
                    button.innerHTML = '<i class="fa-solid fa-check" style="font-size:12px;"></i>';
                    button.addEventListener('click', () => markStoredNotifAsRead(notification.id, button));
                    item.append(link, button);
                    return item;
                }

                async function refreshStoredNotifications() {
                    try {
                        const response = await fetch('index.php?action=get_notification_dropdown', {
                            headers: {'X-Requested-With': 'XMLHttpRequest'},
                            cache: 'no-store'
                        });
                        const result = await response.json();
                        if (result.status !== 'success') return;

                        const container = document.getElementById('notifListContainer');
                        const emptyMessage = document.getElementById('noNotifMessage');
                        if (!container || !emptyMessage) return;
                        container.querySelectorAll('.nav-notif-item[data-type="stored"]').forEach(node => node.remove());

                        const fragment = document.createDocumentFragment();
                        (result.data.items || []).forEach(notification => fragment.appendChild(buildStoredNotificationItem(notification)));
                        container.insertBefore(fragment, emptyMessage.nextSibling);
                        updateNotifBadgeCount();
                    } catch (error) {
                        console.error('Error refreshing notifications:', error);
                    }
                }

                // Keep the bell current while a staff member leaves the ERP open.
                window.setInterval(refreshStoredNotifications, 60000);
            </script>

            <!-- Butang Profil -->
            <a href="index.php?page=profile" class="btn-logout" style="background:#EEF2FF; color:#3730A3; border-color:#C7D2FE;" title="My Corporate Profile">
                <i class="fa-solid fa-user"></i>
                <span>Profile</span>
            </a>

            <!-- Log Out button -->
            <a href="index.php?page=logout" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Log Out</span>
            </a>
        </div>
    </header>

    <!-- Kandungan Utama Page -->
    <main class="content-area">
