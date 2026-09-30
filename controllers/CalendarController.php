<?php
/**
 * Calendar & Events Controller — TBBA ERP Module 9
 *
 * Features:
 * - Calendar view
 * - Create / update / delete events
 * - Public event Bell notification
 * - Firebase Web Push notification
 * - Google Drive event media gallery
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/AuditLog.php';


class CalendarController
{
    /**
     * Calendar main page
     */
    public static function index(): void
    {
        Auth::requirePermission(
            'calendar',
            'view'
        );

        $pageTitle =
            'Calendar & Events';

        $currentUser =
            Auth::user();

        $canCreate =
            Auth::hasPermission(
                'calendar',
                'create'
            );

        $canEdit =
            Auth::hasPermission(
                'calendar',
                'edit'
            );

        $canDelete =
            Auth::hasPermission(
                'calendar',
                'delete'
            );

        $departments =
            Department::getAllActive();

        $upcoming =
            Event::getUpcoming(
                6,
                Auth::userDepartmentId(),
                (int) Auth::id(),
                $canEdit || $canDelete
            );

        include __DIR__
            . '/../views/calendar/index.php';
    }


    /**
     * Fetch calendar events
     */
    public static function getEvents(): void
    {
        Auth::requirePermission(
            'calendar',
            'view'
        );

        $canManage =
            Auth::hasPermission(
                'calendar',
                'edit'
            )
            ||
            Auth::hasPermission(
                'calendar',
                'delete'
            );

        $departmentId =
            Auth::userDepartmentId();

        $userId =
            (int) Auth::id();

        $from =
            isset($_GET['start'])
                ? substr(
                    (string) $_GET['start'],
                    0,
                    10
                )
                : null;

        $to =
            isset($_GET['end'])
                ? substr(
                    (string) $_GET['end'],
                    0,
                    10
                )
                : null;


        if (!$from && !$to) {

            $year =
                (string) (
                    $_GET['year']
                    ??
                    date('Y')
                );

            $month =
                (string) (
                    $_GET['month']
                    ??
                    date('m')
                );

            $events =
                Event::getForCalendar(
                    $year,
                    $month,
                    $departmentId,
                    $userId,
                    $canManage
                );

        } else {

            $events =
                Event::getAll(
                    $from,
                    $to,
                    $departmentId,
                    $userId,
                    $canManage
                );
        }


        Helper::json(
            'success',
            'OK',
            $events
        );
    }


    /**
     * Fetch one event
     */
    public static function getEvent(): void
    {
        Auth::requirePermission(
            'calendar',
            'view'
        );

        $id =
            (int) (
                $_GET['id']
                ??
                0
            );

        $ev =
            Event::findById(
                $id
            );


        if (!$ev) {
            Helper::json(
                'error',
                'Event not found.'
            );
        }


        if (
            !self::canViewEvent(
                $ev
            )
        ) {
            Helper::json(
                'error',
                'Event not found.'
            );
        }


        Helper::json(
            'success',
            'OK',
            $ev
        );
    }


    /**
     * Create Event
     */
    public static function store(): void
    {
        Auth::requirePermission(
            'calendar',
            'create'
        );


        if (
            $_SERVER['REQUEST_METHOD']
            !==
            'POST'
        ) {
            Helper::json(
                'error',
                'Invalid method.'
            );
        }


        /* ============================
           CSRF
        ============================ */

        $csrf =
            (string) (
                $_POST['csrf_token']
                ??
                ''
            );


        if (
            !Helper::verifyCsrf(
                $csrf
            )
        ) {
            Helper::json(
                'error',
                'Invalid CSRF token security.'
            );
        }


        /* ============================
           INPUT
        ============================ */

        $title =
            trim(
                (string) (
                    $_POST['title']
                    ??
                    ''
                )
            );


        $start =
            self::normaliseDateTime(
                trim(
                    (string) (
                        $_POST['start_datetime']
                        ??
                        ''
                    )
                )
            );


        $end =
            self::normaliseDateTime(
                trim(
                    (string) (
                        $_POST['end_datetime']
                        ??
                        ''
                    )
                )
            );


        if (
            !$title
            ||
            !$start
            ||
            !$end
        ) {
            Helper::json(
                'error',
                'Title, start time, and end time are required.'
            );
        }


        if (
            $start >= $end
        ) {
            Helper::json(
                'error',
                'End time must be after start time.'
            );
        }


        /* ============================
           EVENT TYPE
        ============================ */

        $type =
            (string) (
                $_POST['type']
                ??
                'meeting'
            );


        if (
            !in_array(
                $type,
                [
                    'meeting',
                    'holiday',
                    'company',
                    'training',
                    'deadline'
                ],
                true
            )
        ) {
            Helper::json(
                'error',
                'Invalid event type.'
            );
        }


        /* ============================
           COLOUR
        ============================ */

        $color =
            strtoupper(
                (string) (
                    $_POST['color']
                    ??
                    '#2563EB'
                )
            );


        if (
            !preg_match(
                '/^#[0-9A-F]{6}$/',
                $color
            )
        ) {
            Helper::json(
                'error',
                'Invalid event colour.'
            );
        }


        /* ============================
           OTHER DATA
        ============================ */

        $description =
            trim(
                (string) (
                    $_POST['description']
                    ??
                    ''
                )
            );


        $location =
            trim(
                (string) (
                    $_POST['location']
                    ??
                    ''
                )
            );


        $allDay =
            (int) (
                $_POST['all_day']
                ??
                0
            );


        $isPublic =
            !empty(
                $_POST['is_public']
            )
                ? 1
                : 0;


        $departmentId =
            (int) (
                $_POST['department_id']
                ??
                0
            );


        $departmentId =
            $departmentId > 0
                ? $departmentId
                : null;


        /* ============================
           CREATE EVENT
        ============================ */

        $id =
            Event::create([
                'title' =>
                    $title,

                'description' =>
                    $description !== ''
                        ? $description
                        : null,

                'type' =>
                    $type,

                'start_datetime' =>
                    $start,

                'end_datetime' =>
                    $end,

                'all_day' =>
                    $allDay,

                'location' =>
                    $location !== ''
                        ? $location
                        : null,

                'color' =>
                    $color,

                'is_public' =>
                    $isPublic,

                'department_id' =>
                    $departmentId,

                'created_by' =>
                    (int) Auth::id()
            ]);


        /* ============================
           AUDIT LOG
        ============================ */

        AuditLog::record(
            'CREATE',
            "Created event: '{$title}' (#{$id})"
        );


        /* ============================
           NOTIFICATION
           PUBLIC EVENT ONLY

           Bell + Firebase Push
        ============================ */

        if (
            $isPublic === 1
        ) {

            self::notifyPublicEventCreated(
                $id,
                $title,
                $start,
                $location
            );
        }


        Helper::json(
            'success',
            'Event created!',
            [
                'id' => $id
            ]
        );
    }


    /**
     * Update Event
     */
    public static function update(): void
    {
        Auth::requirePermission(
            'calendar',
            'edit'
        );


        if (
            $_SERVER['REQUEST_METHOD']
            !==
            'POST'
        ) {
            Helper::json(
                'error',
                'Invalid method.'
            );
        }


        /* ============================
           CSRF
        ============================ */

        $csrf =
            (string) (
                $_POST['csrf_token']
                ??
                ''
            );


        if (
            !Helper::verifyCsrf(
                $csrf
            )
        ) {
            Helper::json(
                'error',
                'Invalid CSRF token security.'
            );
        }


        /* ============================
           EVENT
        ============================ */

        $id =
            (int) (
                $_POST['id']
                ??
                0
            );


        $oldEvent =
            Event::findById(
                $id
            );


        if (!$oldEvent) {
            Helper::json(
                'error',
                'Not found.'
            );
        }


        /* ============================
           INPUT
        ============================ */

        $title =
            trim(
                (string) (
                    $_POST['title']
                    ??
                    ''
                )
            );


        $start =
            self::normaliseDateTime(
                trim(
                    (string) (
                        $_POST['start_datetime']
                        ??
                        ''
                    )
                )
            );


        $end =
            self::normaliseDateTime(
                trim(
                    (string) (
                        $_POST['end_datetime']
                        ??
                        ''
                    )
                )
            );


        if (
            !$title
            ||
            !$start
            ||
            !$end
        ) {
            Helper::json(
                'error',
                'Title, start time, and end time required.'
            );
        }


        if (
            $start >= $end
        ) {
            Helper::json(
                'error',
                'End time must be after start time.'
            );
        }


        /* ============================
           TYPE
        ============================ */

        $type =
            (string) (
                $_POST['type']
                ??
                'meeting'
            );


        if (
            !in_array(
                $type,
                [
                    'meeting',
                    'holiday',
                    'company',
                    'training',
                    'deadline'
                ],
                true
            )
        ) {
            Helper::json(
                'error',
                'Invalid event type.'
            );
        }


        /* ============================
           COLOUR
        ============================ */

        $color =
            strtoupper(
                (string) (
                    $_POST['color']
                    ??
                    '#2563EB'
                )
            );


        if (
            !preg_match(
                '/^#[0-9A-F]{6}$/',
                $color
            )
        ) {
            Helper::json(
                'error',
                'Invalid event colour.'
            );
        }


        /* ============================
           OTHER VALUES
        ============================ */

        $description =
            trim(
                (string) (
                    $_POST['description']
                    ??
                    ''
                )
            );


        $location =
            trim(
                (string) (
                    $_POST['location']
                    ??
                    ''
                )
            );


        $allDay =
            (int) (
                $_POST['all_day']
                ??
                0
            );


        $isPublic =
            !empty(
                $_POST['is_public']
            )
                ? 1
                : 0;


        $departmentId =
            (int) (
                $_POST['department_id']
                ??
                0
            );


        $departmentId =
            $departmentId > 0
                ? $departmentId
                : null;


        /* ============================
           UPDATE DB
        ============================ */

        Event::update(
            $id,
            [
                'title' =>
                    $title,

                'description' =>
                    $description !== ''
                        ? $description
                        : null,

                'type' =>
                    $type,

                'start_datetime' =>
                    $start,

                'end_datetime' =>
                    $end,

                'all_day' =>
                    $allDay,

                'location' =>
                    $location !== ''
                        ? $location
                        : null,

                'color' =>
                    $color,

                'is_public' =>
                    $isPublic,

                'department_id' =>
                    $departmentId
            ]
        );


        AuditLog::record(
            'UPDATE',
            "Updated event: '{$title}' (#{$id})"
        );


        /*
         * Notify users only for public event.
         *
         * Ini juga berguna kalau event time/location
         * berubah selepas staff dah tahu event tersebut.
         */

        if (
            $isPublic === 1
        ) {

            self::notifyPublicEventUpdated(
                $id,
                $title,
                $start,
                $location
            );
        }


        Helper::json(
            'success',
            'Event updated!'
        );
    }


    /**
     * Delete Event
     */
    public static function delete(): void
    {
        Auth::requirePermission(
            'calendar',
            'delete'
        );


        if (
            $_SERVER['REQUEST_METHOD']
            !==
            'POST'
        ) {
            Helper::json(
                'error',
                'Invalid method.'
            );
        }


        $csrf =
            (string) (
                $_POST['csrf_token']
                ??
                ''
            );


        if (
            !Helper::verifyCsrf(
                $csrf
            )
        ) {
            Helper::json(
                'error',
                'Invalid CSRF token security.'
            );
        }


        $id =
            (int) (
                $_POST['id']
                ??
                0
            );


        $ev =
            Event::findById(
                $id
            );


        if (!$ev) {
            Helper::json(
                'error',
                'Not found.'
            );
        }


        /*
         * Simpan info sebelum delete
         * untuk notification.
         */

        $wasPublic =
            (int) (
                $ev['is_public']
                ??
                0
            ) === 1;


        $title =
            (string) (
                $ev['title']
                ??
                'Event'
            );


        $start =
            (string) (
                $ev['start_datetime']
                ??
                ''
            );


        Event::delete(
            $id
        );


        AuditLog::record(
            'DELETE',
            "Deleted event: '{$title}' (#{$id})"
        );


        /*
         * Kalau event public dibatalkan/deleted,
         * staff akan dapat Bell + Push notification.
         */

        if ($wasPublic) {

            self::notifyPublicEventDeleted(
                $title,
                $start
            );
        }


        Helper::json(
            'success',
            'Event deleted.'
        );
    }


    /**
     * Create Event Media Gallery
     */
    public static function createMediaGallery(): void
    {
        Auth::requirePermission(
            'calendar',
            'edit'
        );


        if (
            $_SERVER['REQUEST_METHOD']
            !==
            'POST'
        ) {
            Helper::json(
                'error',
                'Invalid method.'
            );
        }


        $csrf =
            (string) (
                $_POST['csrf_token']
                ??
                ''
            );


        if (
            !Helper::verifyCsrf(
                $csrf
            )
        ) {
            Helper::json(
                'error',
                'Invalid CSRF token security.'
            );
        }


        $id =
            (int) (
                $_POST['id']
                ??
                0
            );


        $ev =
            Event::findById(
                $id
            );


        if (!$ev) {
            Helper::json(
                'error',
                'Event not found.'
            );
        }


        require_once __DIR__
            . '/../core/GoogleDriveService.php';


        $gdrive =
            new GoogleDriveService();


        if (
            !$gdrive->isReady()
        ) {
            Helper::json(
                'error',
                'Google Drive Service is not configured properly. Missing credentials or master folder ID.'
            );
        }


        $safeTitle =
            preg_replace(
                '/[^a-zA-Z0-9_\-]/',
                '_',
                (string) $ev['title']
            );


        $folderName =
            "Event_{$ev['id']}_{$safeTitle}";


        $result =
            $gdrive->createEventFolder(
                $folderName
            );


        if ($result) {

            Database::query(
                "
                    UPDATE `events`
                    SET
                        `gdrive_folder_id` = ?,
                        `gdrive_folder_link` = ?
                    WHERE `id` = ?
                ",
                [
                    $result['id'],
                    $result['link'],
                    $ev['id']
                ]
            );


            AuditLog::record(
                'UPDATE',
                "Created Media Gallery for event: '{$ev['title']}' (#{$ev['id']})"
            );


            Helper::json(
                'success',
                'Media gallery created successfully!',
                $result
            );

        } else {

            Helper::json(
                'error',
                'Failed to create Google Drive folder. Check logs.'
            );
        }
    }


    /**
     * Get Event Media Gallery
     */
    public static function getMediaGallery(): void
    {
        Auth::requirePermission(
            'calendar',
            'view'
        );


        $id =
            (int) (
                $_GET['id']
                ??
                0
            );


        $ev =
            Event::findById(
                $id
            );


        if (!$ev) {
            Helper::json(
                'error',
                'Event not found.'
            );
        }


        if (
            !self::canViewEvent(
                $ev
            )
        ) {
            Helper::json(
                'error',
                'Event not found.'
            );
        }


        if (
            empty(
                $ev['gdrive_folder_id']
            )
        ) {
            Helper::json(
                'error',
                'No media gallery exists for this event.'
            );
        }


        require_once __DIR__
            . '/../core/GoogleDriveService.php';


        $gdrive =
            new GoogleDriveService();


        if (
            !$gdrive->isReady()
        ) {
            Helper::json(
                'error',
                'Google Drive Service is not configured properly.'
            );
        }


        $images =
            $gdrive->getImages(
                $ev['gdrive_folder_id']
            );


        Helper::json(
            'success',
            'OK',
            [
                'folder_link' =>
                    $ev['gdrive_folder_link'],

                'images' =>
                    $images
            ]
        );
    }


    /* =========================================================
       CALENDAR NOTIFICATIONS
       ========================================================= */


    /**
     * New public event
     *
     * Bell + FCM Push.
     */
    private static function notifyPublicEventCreated(
        int $eventId,
        string $title,
        string $start,
        string $location = ''
    ): void {

        require_once __DIR__
            . '/../core/NotificationService.php';


        $dateFormatted =
            date(
                'd M Y, h:i A',
                strtotime(
                    $start
                )
            );


        $body =
            "Date: {$dateFormatted}";


        if (
            trim(
                $location
            ) !== ''
        ) {
            $body .=
                ' • '
                . trim(
                    $location
                );
        }


        self::sendCalendarNotificationToAll(
            'announcement_event',

            '📅 New Event: '
            . $title,

            $body,

            '#3B82F6',

            'calendar_reminder'
        );
    }


    /**
     * Updated public event
     */
    private static function notifyPublicEventUpdated(
        int $eventId,
        string $title,
        string $start,
        string $location = ''
    ): void {

        require_once __DIR__
            . '/../core/NotificationService.php';


        $dateFormatted =
            date(
                'd M Y, h:i A',
                strtotime(
                    $start
                )
            );


        $body =
            "Updated schedule: {$dateFormatted}";


        if (
            trim(
                $location
            ) !== ''
        ) {
            $body .=
                ' • '
                . trim(
                    $location
                );
        }


        self::sendCalendarNotificationToAll(
            'calendar_event_updated',

            '📅 Event Updated: '
            . $title,

            $body,

            '#F59E0B',

            'calendar_reminder'
        );
    }


    /**
     * Deleted / cancelled public event
     */
    private static function notifyPublicEventDeleted(
        string $title,
        string $start = ''
    ): void {

        require_once __DIR__
            . '/../core/NotificationService.php';


        $body =
            'This event has been cancelled or removed.';


        if (
            $start !== ''
            &&
            strtotime(
                $start
            ) !== false
        ) {

            $body .=
                ' Original schedule: '
                . date(
                    'd M Y, h:i A',
                    strtotime(
                        $start
                    )
                );
        }


        self::sendCalendarNotificationToAll(
            'calendar_event_cancelled',

            '❌ Event Cancelled: '
            . $title,

            $body,

            '#EF4444',

            'calendar_reminder'
        );
    }


    /**
     * Send Bell + Firebase Push
     * kepada semua active users.
     */
    private static function sendCalendarNotificationToAll(
        string $inAppType,
        string $title,
        string $body,
        string $color,
        string $pushType = 'calendar_reminder'
    ): void {

        try {

            $users =
                Database::query(
                    "
                        SELECT `id`
                        FROM `users`
                        WHERE `status` = 'active'
                    "
                )->fetchAll();


            foreach (
                $users
                as
                $recipient
            ) {

                $userId =
                    (int) (
                        $recipient['id']
                        ??
                        0
                    );


                if (
                    $userId <= 0
                ) {
                    continue;
                }


                /*
                 * notifyAndPush:
                 *
                 * 1. notifications table
                 *    → navbar Bell
                 *
                 * 2. Firebase Cloud Messaging
                 *    → native push notification
                 */

                NotificationService::notifyAndPush(
                    $userId,

                    $inAppType,

                    $title,

                    $body,

                    'fa-calendar-days',

                    $color,

                    'index.php?page=calendar',

                    $pushType
                );
            }

        }

        catch (Throwable $e) {

            error_log(
                '[Calendar Notification] '
                . $e->getMessage()
            );
        }
    }


    /* =========================================================
       SECURITY / VISIBILITY
       ========================================================= */

    private static function canViewEvent(
        array $event
    ): bool {

        if (
            Auth::hasPermission(
                'calendar',
                'edit'
            )
            ||
            Auth::hasPermission(
                'calendar',
                'delete'
            )
        ) {
            return true;
        }


        if (
            (int) (
                $event['is_public']
                ??
                0
            ) === 1
        ) {
            return true;
        }


        if (
            (int) (
                $event['created_by']
                ??
                0
            )
            ===
            (int) Auth::id()
        ) {
            return true;
        }


        $departmentId =
            Auth::userDepartmentId();


        return (
            $departmentId !== null
            &&
            (int) (
                $event['department_id']
                ??
                0
            )
            ===
            $departmentId
        );
    }


    /**
     * Normalise datetime
     */
    private static function normaliseDateTime(
        string $value
    ): ?string {

        if (
            $value === ''
        ) {
            return null;
        }


        $formats = [
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d H:i:s'
        ];


        foreach (
            $formats
            as
            $format
        ) {

            $date =
                DateTimeImmutable::createFromFormat(
                    '!' . $format,
                    $value
                );


            $errors =
                DateTimeImmutable::getLastErrors();


            if (
                $date
                &&
                (
                    $errors === false
                    ||
                    (
                        $errors['warning_count'] === 0
                        &&
                        $errors['error_count'] === 0
                    )
                )
                &&
                $date->format(
                    $format
                ) === $value
            ) {

                return $date->format(
                    'Y-m-d H:i:s'
                );
            }
        }


        return null;
    }
}
?>