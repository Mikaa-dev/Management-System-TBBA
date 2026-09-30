<?php
/**
 * Announcements Controller — TBBA ERP Module 8
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/Announcement.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../models/AuditLog.php';

class AnnouncementController {
    public static function index(): void {
        Auth::requirePermission('announcements', 'view');
        $pageTitle = 'Announcements';
        $currentUser = Auth::user();
        $canCreate   = Auth::hasPermission('announcements', 'create');
        $canEdit     = Auth::hasPermission('announcements', 'edit');
        $canDelete   = Auth::hasPermission('announcements', 'delete');

        $deptId = $currentUser['department_id'] ?? null;
        $role   = $currentUser['role'] ?? null;

        if ($canEdit || $canDelete) {
            $announcements = Announcement::getAll(false);
        } else {
            $announcements = Announcement::getAll(true, $deptId, $role);
        }

        $departments = Department::getAllActive();
        $roles       = Role::getAll();
        include __DIR__ . '/../views/announcements/index.php';
    }

    public static function getAnnouncement(): void {
        Auth::requirePermission('announcements', 'view');
        $id = (int)($_GET['id'] ?? 0);
        $ann = Announcement::findById($id);
        if (!$ann) Helper::json('error', 'Announcement not found.');
        $canManage = Auth::hasPermission('announcements', 'edit') || Auth::hasPermission('announcements', 'delete');
        if (!$canManage && !Announcement::isVisibleTo($ann, Auth::userDepartmentId(), Auth::role())) {
            Helper::json('error', 'Announcement not found.');
        }
        Helper::json('success', 'OK', $ann);
    }

    public static function store(): void {
        Auth::requirePermission('announcements', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (!$title || !$content) Helper::json('error', 'Title and content are required.');
        $input = self::validatedInput();

        $id = Announcement::create([
            'title'           => $title,
            'content'         => $content,
            'type'            => $input['type'],
            'audience'        => $input['audience'],
            'audience_target' => $input['target'],
            'is_pinned'       => (int)($_POST['is_pinned'] ?? 0),
            'author_id'       => (int)Auth::id(),
            'published_at'    => $input['published_at'],
            'expires_at'      => $input['expires_at'],
        ]);

        AuditLog::record('CREATE', "Created announcement: '{$title}' (#{$id})");

        require_once __DIR__ . '/../core/NotificationService.php';
        $audience = $input['audience'];
        $target   = $input['target'];
        $pushTitle = '📢 ' . $title;
        $pushBody  = substr(strip_tags($content), 0, 100);
        $pushLink  = 'index.php?page=announcements';

        if ($audience === 'role' && $target) {
            // In-app notification kepada role tertentu
            NotificationService::notifyRole($target, 'announcement_created', $pushTitle, $pushBody, 'fa-bullhorn', '#10B981', $pushLink);
            // FCM push kepada role yang sama
            NotificationService::sendPushToRole($target, $pushTitle, $pushBody, $pushLink, 'announcement');
        } elseif ($audience === 'department' && $target) {
            $users = Database::query(
                "SELECT id FROM users WHERE department_id=? AND status='active'",
                [(int)$target]
            )->fetchAll();
            $userIds = array_column($users, 'id');
            NotificationService::notifyUsers($userIds, 'announcement_created', $pushTitle, $pushBody, 'fa-bullhorn', '#10B981', $pushLink);
            NotificationService::sendPushToUsers($userIds, $pushTitle, $pushBody, $pushLink, 'announcement');
        } else {
            // In-app notification kepada semua user
            NotificationService::notifyAll('announcement_created', $pushTitle, $pushBody, 'fa-bullhorn', '#10B981', $pushLink);
            // FCM push kepada semua user yang subscribe
            $allUsers = Database::query("SELECT `id` FROM `users` WHERE `status` = 'active'")->fetchAll();
            NotificationService::sendPushToUsers(array_column($allUsers, 'id'), $pushTitle, $pushBody, $pushLink, 'announcement');
        }

        Helper::json('success', 'Announcement published!', ['id' => $id]);
    }

    public static function update(): void {
        Auth::requirePermission('announcements', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        $ann = Announcement::findById($id);
        if (!$ann) Helper::json('error', 'Not found.');

        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (!$title || !$content) Helper::json('error', 'Title and content are required.');
        $input = self::validatedInput();

        Announcement::update($id, [
            'title'           => $title,
            'content'         => $content,
            'type'            => $input['type'],
            'audience'        => $input['audience'],
            'audience_target' => $input['target'],
            'is_pinned'       => (int)($_POST['is_pinned'] ?? 0),
            'published_at'    => $input['published_at'],
            'expires_at'      => $input['expires_at'],
        ]);

        AuditLog::record('UPDATE', "Updated announcement: '{$title}' (#{$id})");
        Helper::json('success', 'Announcement updated!');
    }

    public static function delete(): void {
        Auth::requirePermission('announcements', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        $ann = Announcement::findById($id);
        if (!$ann) Helper::json('error', 'Not found.');
        Announcement::delete($id);
        AuditLog::record('DELETE', "Deleted announcement: '{$ann['title']}' (#{$id})");
        Helper::json('success', 'Announcement deleted.');
    }

    public static function togglePin(): void {
        Auth::requirePermission('announcements', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        Announcement::togglePin($id);
        Helper::json('success', 'Pin status toggled.');
    }

    private static function validatedInput(): array {
        $type = (string)($_POST['type'] ?? 'general');
        if (!in_array($type, ['general', 'policy', 'event', 'urgent'], true)) {
            Helper::json('error', 'Invalid announcement category.');
        }

        $audience = (string)($_POST['audience'] ?? 'all');
        if (!in_array($audience, ['all', 'department', 'role'], true)) {
            Helper::json('error', 'Invalid target audience.');
        }
        $target = trim((string)($_POST['audience_target'] ?? '')) ?: null;
        if ($audience === 'all') {
            $target = null;
        } elseif ($target === null) {
            Helper::json('error', 'Please select a target audience.');
        } elseif ($audience === 'department') {
            $exists = Database::query("SELECT 1 FROM departments WHERE id=? AND status='active'", [(int)$target])->fetchColumn();
            if (!$exists || !ctype_digit($target)) Helper::json('error', 'Invalid department target.');
            $target = (string)(int)$target;
        } elseif (!in_array($target, Role::getAllNames(), true)) {
            Helper::json('error', 'Invalid role target.');
        }

        $publishedAt = self::normaliseDateTime(trim((string)($_POST['published_at'] ?? '')));
        $expiresAt = self::normaliseDateTime(trim((string)($_POST['expires_at'] ?? '')));
        if (!empty($_POST['published_at']) && $publishedAt === null) Helper::json('error', 'Invalid publish date.');
        if (!empty($_POST['expires_at']) && $expiresAt === null) Helper::json('error', 'Invalid expiry date.');
        if ($publishedAt && $expiresAt && $expiresAt <= $publishedAt) {
            Helper::json('error', 'Expiry date must be after publish date.');
        }

        return [
            'type' => $type,
            'audience' => $audience,
            'target' => $target,
            'published_at' => $publishedAt,
            'expires_at' => $expiresAt,
        ];
    }

    private static function normaliseDateTime(string $value): ?string {
        if ($value === '') return null;
        foreach (['Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'Y-m-d H:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        return null;
    }
}
?>
