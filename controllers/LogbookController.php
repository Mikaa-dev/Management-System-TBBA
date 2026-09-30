<?php
/**
 * Logbook Controller v2.4 — View-only Staff Reports
 * Staff can create/edit/submit their own logbook.
 * Users with logbook.view_staff_reports can only VIEW staff reports/PDF/photos.
 * No approve/reject/review workflow is used.
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/Logbook.php';

class LogbookController
{
    public static function index(): void
    {
        Auth::requireLogin();
        Auth::requirePermission('logbook', 'view');

        $userId = (int) Auth::id();
        $date = (string) ($_GET['week'] ?? date('Y-m-d'));
        $ts = strtotime($date);
        if ($ts === false) $ts = time();

        $weekStart = date('Y-m-d', strtotime('monday this week', $ts));
        $weekEnd = date('Y-m-d', strtotime('friday this week', $ts));

        $canViewStaffReports = Auth::hasPermission('logbook', 'view_staff_reports');
        $requestedView = (string) ($_GET['view'] ?? 'my');
        $view = ($canViewStaffReports && $requestedView === 'team') ? 'team' : 'my';

        if ($view === 'team') {
            $scopeDepartmentId = method_exists('Auth', 'getScopeDepartmentId')
                ? Auth::getScopeDepartmentId()
                : null;

            $statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
            if (!in_array($statusFilter, ['all', 'not_started', 'draft', 'submitted'], true)) {
                $statusFilter = 'all';
            }

            $departmentFilter = (int) ($_GET['department_id'] ?? 0);
            if ($scopeDepartmentId !== null) {
                $departmentFilter = (int) $scopeDepartmentId;
            }

            $searchFilter = trim((string) ($_GET['q'] ?? ''));
            if (strlen($searchFilter) > 100) {
                $searchFilter = substr($searchFilter, 0, 100);
            }

            $teamFilters = [
                'status' => $statusFilter,
                'department_id' => $departmentFilter,
                'q' => $searchFilter,
            ];

            $teamReports = Logbook::getTeamReports($weekStart, $scopeDepartmentId, $teamFilters);
            $teamSummary = Logbook::getTeamSummary($weekStart, $scopeDepartmentId);
            $departments = Logbook::getDepartmentsForFilter($scopeDepartmentId);

            $selectedTeamReport = null;
            $selectedTeamData = [];
            $selectedReportId = (int) ($_GET['report_id'] ?? 0);

            if ($selectedReportId > 0) {
                $candidate = Logbook::getReportWithUser($selectedReportId);
                if (!$candidate) {
                    http_response_code(404);
                    $teamFlashError = 'The selected report was not found.';
                } elseif (!self::canViewStaffOwner((int) $candidate['user_id'])) {
                    http_response_code(403);
                    $teamFlashError = 'You do not have permission to view this staff report.';
                } else {
                    $selectedTeamReport = $candidate;
                    $selectedTeamData = self::buildWeekData($candidate);
                }
            }

            require __DIR__ . '/../views/logbook/index.php';
            return;
        }

        $report = Logbook::getOrCreateReport($userId, $date);
        $structuredData = self::buildWeekData($report);
        $userProfile = Logbook::getUserProfile($userId);

        require __DIR__ . '/../views/logbook/index.php';
    }

    public static function saveEntry(): void
    {
        Auth::requireLogin();
        Auth::requirePermission('logbook', 'create');
        self::requireCsrf();

        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['report_id'], $data['activity_date'])) {
            http_response_code(422);
            Helper::json('error', 'Invalid logbook payload.');
        }

        $userId = (int) Auth::id();
        $reportId = (int) $data['report_id'];
        $date = (string) $data['activity_date'];
        $report = Logbook::getReportById($reportId);

        if (!$report || (int) $report['user_id'] !== $userId || (string) $report['status'] !== 'draft') {
            http_response_code(403);
            Helper::json('error', 'This logbook cannot be modified.');
        }

        $lastEditableDate = date('Y-m-d', strtotime('+4 days', strtotime($report['week_start'])));
        if ($date < $report['week_start'] || $date > $lastEditableDate) {
            http_response_code(422);
            Helper::json('error', 'The selected day is outside this working week.');
        }

        try {
            Logbook::saveDailyLog($reportId, $date, $data);
            Helper::json('success', 'Logbook day saved successfully.', [
                'structured_data' => self::buildWeekData(Logbook::getReportById($reportId) ?: $report)
            ]);
        } catch (Throwable $e) {
            error_log('[Logbook] saveEntry failed: ' . $e->getMessage());
            http_response_code(500);
            Helper::json('error', 'Unable to save this logbook day. ' . $e->getMessage());
        }
    }

    public static function uploadPhoto(): void
    {
        Auth::requireLogin();
        Auth::requirePermission('logbook', 'create');
        self::requireCsrf();

        $userId = (int) Auth::id();
        $activityId = (int) ($_POST['activity_id'] ?? 0);
        if ($activityId <= 0 || !isset($_FILES['photo'])) {
            http_response_code(422);
            Helper::json('error', 'Invalid photo upload request.');
        }

        $context = Logbook::getActivityContext($activityId);
        if (!$context || (int) $context['user_id'] !== $userId || (string) $context['report_status'] !== 'draft') {
            http_response_code(403);
            Helper::json('error', 'You cannot upload a photo to this activity.');
        }

        $file = $_FILES['photo'];
        $destination = null;

        try {
            $extension = Helper::validateUpload($file, [
                'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
                'png' => ['image/png'], 'webp' => ['image/webp']
            ], 5 * 1024 * 1024);

            $uploadDirRelative = 'storage/private/logbooks/' . date('Y/m');
            $uploadDirAbsolute = __DIR__ . '/../' . $uploadDirRelative;
            if (!is_dir($uploadDirAbsolute) && !mkdir($uploadDirAbsolute, 0750, true) && !is_dir($uploadDirAbsolute)) {
                throw new RuntimeException('The logbook upload directory could not be created.');
            }
            if (!is_writable($uploadDirAbsolute)) {
                throw new RuntimeException('The logbook upload directory is not writable.');
            }

            $uniqueFilename = 'log_' . bin2hex(random_bytes(12)) . '.' . $extension;
            $destination = $uploadDirAbsolute . '/' . $uniqueFilename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('The uploaded photo could not be moved to storage.');
            }
            @chmod($destination, 0640);

            $path = $uploadDirRelative . '/' . $uniqueFilename;
            $photoId = Logbook::addPhoto($activityId, $path);
            Helper::json('success', 'Photo uploaded successfully.', [
                'id' => $photoId,
                'path' => $path,
                'url' => 'index.php?action=logbook_photo&id=' . $photoId
            ]);
        } catch (Throwable $e) {
            if ($destination && is_file($destination)) @unlink($destination);
            error_log('[Logbook] uploadPhoto failed: ' . $e->getMessage());
            http_response_code(500);
            Helper::json('error', $e->getMessage());
        }
    }

    public static function removePhoto(): void
    {
        Auth::requireLogin();
        Auth::requirePermission('logbook', 'create');
        self::requireCsrf();

        $photoId = (int) ($_POST['photo_id'] ?? 0);
        if ($photoId <= 0) {
            http_response_code(422);
            Helper::json('error', 'Invalid photo.');
        }

        try {
            if (Logbook::removePhotoForUser($photoId, (int) Auth::id())) {
                Helper::json('success', 'Photo removed.');
            }
            http_response_code(403);
            Helper::json('error', 'The photo could not be removed.');
        } catch (Throwable $e) {
            error_log('[Logbook] removePhoto failed: ' . $e->getMessage());
            http_response_code(500);
            Helper::json('error', 'Unable to remove the photo.');
        }
    }

    public static function photo(): void
    {
        Auth::requireLogin();
        Auth::requirePermission('logbook', 'view');

        $photoId = (int) ($_GET['id'] ?? 0);
        $photo = $photoId > 0 ? Logbook::getPhotoContext($photoId) : null;
        if (!$photo) {
            http_response_code(404);
            exit('Photo not found.');
        }

        if (!self::canViewStaffOwner((int) $photo['user_id'])) {
            http_response_code(403);
            exit('Access denied.');
        }

        $baseDir = realpath(__DIR__ . '/../storage/private/logbooks');
        $filePath = realpath(__DIR__ . '/../' . $photo['photo_path']);
        if (!$baseDir || !$filePath || !is_file($filePath) || !str_starts_with($filePath, $baseDir . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit('Photo not found.');
        }

        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('Cache-Control: private, max-age=300');
        readfile($filePath);
        exit;
    }

    public static function submitReport(): void
    {
        Auth::requireLogin();
        Auth::requirePermission('logbook', 'create');
        self::requireCsrf();

        $userId = (int) Auth::id();
        $reportId = (int) ($_POST['report_id'] ?? 0);
        if ($reportId <= 0) {
            http_response_code(422);
            Helper::json('error', 'Invalid weekly report.');
        }

        try {
            $report = Logbook::getReportById($reportId);
            if (!$report || (int) $report['user_id'] !== $userId) {
                http_response_code(403);
                Helper::json('error', 'You cannot submit this report.');
            }
            if (!Logbook::submitReport($reportId, $userId)) {
                http_response_code(409);
                Helper::json('error', 'The report has already been submitted.');
            }
            Helper::json('success', 'Weekly report submitted successfully.');
        } catch (Throwable $e) {
            error_log('[Logbook] submitReport failed: ' . $e->getMessage());
            http_response_code(500);
            Helper::json('error', 'Unable to submit the weekly report.');
        }
    }

    public static function downloadPdf(): void
    {
        Auth::requireLogin();
        Auth::requirePermission('logbook', 'view');

        $reportId = (int) ($_GET['report_id'] ?? 0);
        $report = Logbook::getReportById($reportId);
        if (!$report) {
            http_response_code(404);
            exit('Report not found.');
        }
        if (!self::canViewStaffOwner((int) $report['user_id'])) {
            http_response_code(403);
            exit('Access denied.');
        }

        try {
            Logbook::generatePdf($reportId);
        } catch (Throwable $e) {
            error_log('[Logbook] PDF generation failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Unable to generate the PDF. Please contact the system administrator.');
        }
    }

    private static function canViewStaffOwner(int $ownerUserId): bool
    {
        $currentUserId = (int) Auth::id();
        if ($ownerUserId <= 0) return false;
        if ($ownerUserId === $currentUserId) return true;
        if (!Auth::hasPermission('logbook', 'view_staff_reports')) return false;

        $scopeDepartmentId = method_exists('Auth', 'getScopeDepartmentId')
            ? Auth::getScopeDepartmentId()
            : null;
        if ($scopeDepartmentId === null) return true;

        $ownerDepartmentId = Logbook::getUserDepartmentId($ownerUserId);
        return $ownerDepartmentId !== null && $ownerDepartmentId === (int) $scopeDepartmentId;
    }

    private static function buildWeekData(array $report): array
    {
        $existing = Logbook::getStructuredWeeklyLogs((int) $report['id']);
        $result = [];
        $weekStart = $report['week_start'];
        for ($i = 0; $i < 5; $i++) {
            $currentDate = date('Y-m-d', strtotime("+{$i} days", strtotime($weekStart)));
            $result[$currentDate] = $existing[$currentDate] ?? [
                'activity_date' => $currentDate,
                'status' => 'Not Started',
                'day_type' => 'Working Day',
                'location' => '',
                'start_time' => '08:30:00',
                'end_time' => '17:30:00',
                'activities' => []
            ];
        }
        return $result;
    }

    private static function requireCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf((string) $token)) {
            http_response_code(419);
            Helper::json('error', 'Your security token has expired. Refresh the page and try again.');
        }
    }
}
