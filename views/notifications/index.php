<?php
/**
 * Notifications View — TBBA ERP Module 10
 */
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<div class="page-content" style="padding: 24px;">
    <!-- Action Bar -->
    <div class="card" style="padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; background: var(--bg-card);">
        <div>
            <h3 style="margin: 0; font-size: 18px; color: var(--text-dark);"><i class="fa-solid fa-bell" style="color: #2563EB;"></i> Notification Center</h3>
            <p style="margin: 4px 0 0; font-size: 13px; color: var(--text-muted);">View all system alerts, approval status updates, and activity logs.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-secondary" onclick="markAllNotificationsRead()" style="padding: 8px 16px; font-weight: 600; border-radius: 8px; border: 1px solid var(--border-color); background: transparent; color: var(--text-dark); cursor: pointer;">
                <i class="fa-solid fa-check-double"></i> Mark All as Read
            </button>
            <button class="btn btn-danger" onclick="clearAllNotifications()" style="padding: 8px 16px; font-weight: 600; border-radius: 8px; border: none; background: #EF4444; color: #fff; cursor: pointer;">
                <i class="fa-solid fa-trash-can"></i> Clear All History
            </button>
        </div>
    </div>

    <!-- Notifications List -->
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php if (empty($notifications)): ?>
        <div class="card" style="padding: 50px; text-align: center; border-radius: 12px; background: var(--bg-card); color: var(--text-muted);">
            <i class="fa-solid fa-bell-slash" style="font-size: 40px; color: #CBD5E1; margin-bottom: 12px; display: block;"></i>
            No notifications in your history. You're all caught up!
        </div>
        <?php else: ?>
        <?php foreach ($notifications as $n): ?>
        <div class="card notification-item <?= $n['is_read'] ? 'read' : 'unread' ?>" style="padding: 16px 20px; border-radius: 12px; background: <?= $n['is_read'] ? 'var(--bg-card)' : 'var(--bg-primary)' ?>; border-left: 5px solid <?= $n['color'] ?? '#2563EB' ?>; display: flex; justify-content: space-between; align-items: center; gap: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: <?= $n['color'] ?? '#2563EB' ?>22; color: <?= $n['color'] ?? '#2563EB' ?>; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                    <i class="fa-solid <?= $n['icon'] ?: 'fa-bell' ?>"></i>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <h5 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--text-dark);"><?= htmlspecialchars($n['title']) ?></h5>
                        <?php if (!$n['is_read']): ?>
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #2563EB; display: inline-block;"></span>
                        <?php endif; ?>
                    </div>
                    <p style="margin: 4px 0 6px; font-size: 13px; color: var(--text-muted); line-height: 1.4;">
                        <?= htmlspecialchars($n['body']) ?>
                    </p>
                    <span style="font-size: 11px; color: var(--text-muted);"><i class="fa-solid fa-clock"></i> <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></span>
                </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <?php if ($n['link']): ?>
                <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-secondary btn-sm" style="padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color); text-decoration: none; font-size: 12px; color: var(--text-dark); font-weight: 600;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View
                </a>
                <?php endif; ?>
                <?php if (!$n['is_read']): ?>
                <button onclick="markNotificationRead(<?= $n['id'] ?>)" class="btn btn-secondary btn-sm" style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer; color: var(--text-dark);" title="Mark as Read">
                    <i class="fa-solid fa-check"></i>
                </button>
                <?php endif; ?>
                <button onclick="deleteNotification(<?= $n['id'] ?>)" class="btn btn-danger btn-sm" style="padding: 6px 10px; border-radius: 6px; border: none; background: #EF4444; color: #fff; cursor: pointer;" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
async function markNotificationRead(id) {
    const formData = new FormData(); formData.append('id', id); formData.append('csrf_token', '<?= Helper::csrfToken() ?>');
    await App.post('index.php?action=mark_read_notification', formData);
    location.reload();
}

async function markAllNotificationsRead() {
    const fd = new FormData(); fd.append('csrf_token', '<?= Helper::csrfToken() ?>');
    await App.post('index.php?action=mark_all_read_notification', fd);
    location.reload();
}

async function deleteNotification(id) {
    const formData = new FormData(); formData.append('id', id); formData.append('csrf_token', '<?= Helper::csrfToken() ?>');
    await App.post('index.php?action=delete_notification', formData);
    location.reload();
}

async function clearAllNotifications() {
    if (!await App.confirm('Clear All Notifications', 'Are you sure you want to clear all notification history?')) return;
    const fd = new FormData(); fd.append('csrf_token', '<?= Helper::csrfToken() ?>');
    await App.post('index.php?action=clear_all_notifications', fd);
    location.reload();
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
