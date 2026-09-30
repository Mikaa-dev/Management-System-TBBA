<?php
/**
 * Leave & Permission Controller — TBBA ERP Module 2
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/LeaveRequest.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../core/NotificationService.php'; // FCM Push Notification

class LeaveController {
    public static function index(): void {
        Auth::requirePermission('leave', 'view');
        $pageTitle = 'Leave & Permission';
        $currentUser = Auth::user();
        $canApprove = Auth::hasPermission('leave', 'approve');
        $leaveTypes = LeaveRequest::getLeaveTypes();
        $deptScope = Auth::getScopeDepartmentId();

        if ($canApprove) {
            $allRequests = LeaveRequest::getAll(null, $deptScope);
            $pending     = LeaveRequest::getAll('pending', $deptScope);
        } else {
            $allRequests = LeaveRequest::getByUser((int)$currentUser['id']);
            $pending     = LeaveRequest::getByUser((int)$currentUser['id'], 'pending');
        }
        include __DIR__ . '/../views/leave/index.php';
    }

    public static function store(): void {
        Auth::requirePermission('leave', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $userId      = (int)Auth::id();
        $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
        $startDate   = trim($_POST['start_date'] ?? '');
        $endDate     = trim($_POST['end_date'] ?? '');
        $halfDay     = (int)($_POST['half_day'] ?? 0);
        $reason      = trim($_POST['reason'] ?? '');

        if (!$leaveTypeId || !$startDate || !$endDate) Helper::json('error', 'Leave type, start date, and end date are required.');
        if ($startDate > $endDate) Helper::json('error', 'End date must be on or after start date.');

        // Calculate working days
        $start = new DateTime($startDate); $end = new DateTime($endDate);
        $days = 0;
        $cur = clone $start;
        while ($cur <= $end) {
            if (!in_array($cur->format('N'), [6,7])) $days++;
            $cur->modify('+1 day');
        }
        if ($halfDay) $days = max(0.5, $days * 0.5);
        if ($days <= 0) Helper::json('error', 'Selected dates have no working days.');

        $proofType = trim($_POST['proof_type'] ?? 'none');
        $gpsCoords = trim($_POST['gps_coordinates'] ?? '') ?: null;
        $attachmentPath = null;

        if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
            try {
                Helper::validateUpload($_FILES['proof_file'], [
                    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
                    'pdf' => ['application/pdf'],
                    'doc' => ['application/msword', 'application/octet-stream'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                ], 10 * 1024 * 1024);
            } catch (InvalidArgumentException $e) {
                Helper::json('error', $e->getMessage());
            }
            $uploadDirRel = 'storage/private/leaves';
            $uploadDirAbs = __DIR__ . '/../' . $uploadDirRel;
            if (!is_dir($uploadDirAbs)) {
                mkdir($uploadDirAbs, 0750, true);
            }
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['proof_file']['name']));
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $uploadDirAbs . '/' . $fileName)) {
                $attachmentPath = $uploadDirRel . '/' . $fileName;
            }
        }

        if ($proofType === 'gps_or_photo') {
            if ($attachmentPath && $gpsCoords) $proofType = 'photo_and_gps';
            elseif ($attachmentPath) $proofType = 'photo';
            elseif ($gpsCoords) $proofType = 'gps';
            else $proofType = 'none';
        } elseif ($proofType === 'document' && $attachmentPath) {
            $proofType = 'document';
        }

        if (isset($_POST['proof_required']) && $_POST['proof_required'] == '1' && !$attachmentPath && !$gpsCoords) {
            Helper::json('error', 'Please attach required medical certificate / document or stamp GPS coordinates.');
        }

        $id = LeaveRequest::create([
            'user_id' => $userId, 'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate, 'end_date' => $endDate,
            'total_days' => $days, 'half_day' => $halfDay, 'reason' => $reason,
            'proof_type' => $proofType, 'attachment_path' => $attachmentPath, 'gps_coordinates' => $gpsCoords,
        ]);

        $user = Auth::user();
        Notification::sendToApprovers(
            'leave',
            $userId,
            'approval_leave_pending',
            'New Leave Request',
            "{$user['name']} submitted a {$days}-day leave request from {$startDate} to {$endDate}.",
            'fa-calendar-check',
            '#D97706'
        );
        AuditLog::record('LEAVE_APPLY', "Leave request submitted: {$days} day(s) from {$startDate} to {$endDate}");
        Helper::json('success', 'Leave request submitted! Awaiting approval.', ['id' => $id, 'days' => $days]);
    }

    public static function approve(): void {
        Auth::requirePermission('leave', 'approve');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id       = (int)($_POST['id'] ?? 0);
        $decision = trim($_POST['decision'] ?? '');
        $reason   = trim($_POST['reason'] ?? '');

        if (!$id || !in_array($decision, ['approved','rejected'])) Helper::json('error', 'Invalid data.');
        $req = LeaveRequest::findById($id);
        if (!$req) Helper::json('error', 'Leave request not found.');
        if ($req['status'] !== 'pending') Helper::json('error', 'This request is no longer pending.');

        if (Auth::getScopeDepartmentId() !== null) {
            $targetUser = User::findById((int)$req['user_id']);
            if (!$targetUser || !Auth::canAccessUser($targetUser)) {
                Helper::json('error', 'Access Denied: You can only approve leave requests for staff within your own department.');
            }
        }

        $approverId = (int)Auth::id();
        if (!LeaveRequest::updateStatus($id, $decision, $approverId, $reason ?: null)) {
            Helper::json('error', 'This request was already processed by another user.');
        }

        // Notify the requester — in-app notification + FCM push notification
        $icon  = $decision === 'approved' ? 'fa-check-circle' : 'fa-times-circle';
        $color = $decision === 'approved' ? '#10B981' : '#EF4444';
        $notifTitle = "Leave Request " . ucfirst($decision);
        $notifBody  = "Your leave request ({$req['total_days']} day(s): {$req['start_date']} to {$req['end_date']}) has been {$decision}." . ($reason ? " Reason: {$reason}" : '');

        // Dual notification: simpan in-app DB + hantar FCM push ke device user
        NotificationService::notifyAndPush(
            (int)$req['user_id'],
            'leave_' . $decision,       // in-app type
            $notifTitle,
            $notifBody,
            $icon, $color,
            'index.php?page=leave',
            'leave_' . $decision        // FCM push type
        );

        AuditLog::record('LEAVE_' . strtoupper($decision), "Leave #{$id} for User #{$req['user_id']}: {$decision}");
        Helper::json('success', "Leave request " . ucfirst($decision) . " successfully.");
    }

    public static function cancel(): void {
        Auth::requirePermission('leave', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        $ok = LeaveRequest::cancel($id, (int)Auth::id());
        if (!$ok) Helper::json('error', 'Cannot cancel this request (not pending or not yours).');
        AuditLog::record('LEAVE_CANCEL', "Leave request #{$id} cancelled.");
        Helper::json('success', 'Leave request cancelled.');
    }

    public static function getRequest(): void {
        Auth::requirePermission('leave', 'view');
        $id = (int)($_GET['id'] ?? 0);
        $req = LeaveRequest::findById($id);
        if (!$req) Helper::json('error', 'Not found.');

        if (!Auth::canAccessOwnedResource((int)$req['user_id'], 'leave')) {
            Helper::json('error', 'Access Denied: You cannot view this leave request.');
        }
        
        Helper::json('success', 'OK', $req);
    }

    public static function getBalance(): void {
        Auth::requirePermission('leave', 'view');
        $userId = (int)($_GET['user_id'] ?? Auth::id());
        if (!Auth::canAccessOwnedResource($userId, 'leave')) {
            Helper::json('error', 'Access Denied: You cannot view this leave balance.');
        }
        $year   = (int)($_GET['year'] ?? date('Y'));
        $types  = LeaveRequest::getLeaveTypes();
        $balance = [];
        foreach ($types as $t) {
            $used = LeaveRequest::getTotalDaysUsed($userId, $t['id'], $year);
            $balance[] = [
                'type'    => $t,
                'allowed' => (float)$t['days_allowed'],
                'used'    => $used,
                'remaining' => max(0, (float)$t['days_allowed'] - $used),
            ];
        }
        Helper::json('success', 'Balance retrieved.', $balance);
    }
}
?>
