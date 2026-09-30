<?php
/**
 * Controller Kehadiran GPS (Attendance Controller) - AJAX
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/Branch.php';

class AttendanceController {
    // Pengendali Clock-In GPS (AJAX)
    public static function clockIn() {
        Auth::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security. Please refresh the page.');
        }

        $user = Auth::user();
        $todayRecord = Attendance::getToday($user['id']);

        if ($todayRecord && !empty($todayRecord['clock_in'])) {
            Helper::json('error', 'You have already clocked in today at ' . date('h:i A', strtotime($todayRecord['clock_in'])));
        }

        $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
        $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;
        $photo = isset($_POST['photo']) && !empty($_POST['photo']) ? trim($_POST['photo']) : null;

        if ($lat === null || $lng === null) {
            Helper::json('error', 'System failed to detect your GPS coordinates. Please ensure Location Permission is enabled on your device/browser.');
        }

        // Semak geofence (maksimum 100 meter dari pejabat/cawangan). Jika > 100 dan tiada reason/photo, kembalikan JSON 'out_of_range'
        $distance = self::checkGeofence($user['id'], $lat, $lng, $reason, $photo, 'clock_in');
        $isOutOfRange = ($distance > 100);

        // Rekod masa dari Server Time
        Attendance::clockIn($user['id'], $lat, $lng, $reason, $photo, $isOutOfRange);
        $serverTime = date('h:i A');

        $rangeStatus = $isOutOfRange ? "Out-of-Range ({$distance}m)" : "In-Office ({$distance}m)";
        AuditLog::record('ATTENDANCE', "Clocked In via GPS geofencing [{$rangeStatus}] at {$serverTime}");

        $hour = (int) date('H');
        $minute = (int) date('i');
        $isLate = ($hour > 8 || ($hour == 8 && $minute > 45));

        $msg = $isOutOfRange 
            ? "Remote Clock-in (Outside Office: {$distance}m) recorded at {$serverTime} with reason & photo verification."
            : ($isLate 
                ? "Clock-in recorded at {$serverTime} (Status: LATE - After 8:45 AM). Distance: {$distance}m from office."
                : "Clock-in successfully recorded at {$serverTime} (Status: ON TIME). Distance: {$distance}m from office!");

        $dbUser = User::findById($user['id']);
        $realName = !empty($dbUser['name']) ? $dbUser['name'] : (!empty($user['name']) ? $user['name'] : 'Staff');
        $realPosition = !empty($dbUser['position']) ? $dbUser['position'] : (!empty($dbUser['role']) ? strtoupper($dbUser['role']) : 'Staff');
        $formattedDate = Helper::date(date('Y-m-d'), 'd M Y');

        Helper::json('success', $msg, [
            'time'            => $serverTime,
            'lat'             => $lat,
            'lng'             => $lng,
            'distance'        => $distance,
            'status'          => 'clocked_in',
            'is_late'         => $isLate,
            'is_out_of_range' => $isOutOfRange,
            'reason'          => $reason,
            'photo'           => $photo,
            'date_str'        => $formattedDate,
            'user_name'       => $realName,
            'user_position'   => $realPosition
        ]);
    }

    // Pengendali Clock-Out GPS (AJAX)
    public static function clockOut() {
        Auth::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $user = Auth::user();
        $todayRecord = Attendance::getToday($user['id']);

        if (!$todayRecord || empty($todayRecord['clock_in'])) {
            Helper::json('error', 'You have not clocked in today!');
        }

        if (!empty($todayRecord['clock_out'])) {
            Helper::json('error', 'You have already clocked out today at ' . date('h:i A', strtotime($todayRecord['clock_out'])));
        }

        $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
        $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;
        $photo = isset($_POST['photo']) && !empty($_POST['photo']) ? trim($_POST['photo']) : null;

        if ($lat === null || $lng === null) {
            Helper::json('error', 'System failed to detect your GPS coordinates. Please ensure Location Permission is enabled on your device/browser.');
        }

        // Semak geofence (maksimum 100 meter dari pejabat/cawangan)
        $distance = self::checkGeofence($user['id'], $lat, $lng, $reason, $photo, 'clock_out');
        $isOutOfRange = ($distance > 100);

        Attendance::clockOut($user['id'], $lat, $lng, $reason, $photo, $isOutOfRange);
        $serverTime = date('h:i A');

        $rangeStatus = $isOutOfRange ? "Out-of-Range ({$distance}m)" : "In-Office ({$distance}m)";
        AuditLog::record('ATTENDANCE', "Clocked Out via GPS geofencing [{$rangeStatus}] at {$serverTime}");

        $msg = $isOutOfRange
            ? "Remote Clock-out (Outside Office: {$distance}m) recorded at {$serverTime} with verification."
            : "Clock-out successfully recorded at {$serverTime}. (Distance: {$distance}m from office)";

        Helper::json('success', $msg, [
            'time'            => $serverTime,
            'lat'             => $lat,
            'lng'             => $lng,
            'distance'        => $distance,
            'status'          => 'clocked_out',
            'is_out_of_range' => $isOutOfRange,
            'reason'          => $reason,
            'photo'           => $photo
        ]);
    }

    // Helper Formula Haversine untuk Geofencing
    private static function checkGeofence($userId, $lat, $lng, $reason = null, $photo = null, $actionType = 'clock_in') {
        $dbUser = User::findById($userId);
        
        // Default HQ: 3.115915378512726, 101.73668952434485
        $officeLat = 3.115915378512726;
        $officeLng = 101.73668952434485;

        // Gunakan koordinat cawangan jika wujud
        if (!empty($dbUser['branch_id'])) {
            $branch = Branch::findById($dbUser['branch_id']);
            if ($branch && !empty($branch['latitude']) && !empty($branch['longitude'])) {
                $officeLat = (float)$branch['latitude'];
                $officeLng = (float)$branch['longitude'];
            }
        }

        $earthRadius = 6371000; // Radius bumi dalam meter
        $dLat = deg2rad($officeLat - $lat);
        $dLng = deg2rad($officeLng - $lng);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat)) * cos(deg2rad($officeLat)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = round($earthRadius * $c);

        // If > 100m and no reason/photo provided yet, return 'out_of_range' status to prompt UI for selfie & reason
        if ($distance > 100 && empty($reason) && empty($photo)) {
            Helper::json('out_of_range', "You are detected outside the office area ({$distance}m from HQ). Please state your reason and take a verification selfie.", [
                'distance'    => $distance,
                'lat'         => $lat,
                'lng'         => $lng,
                'action_type' => $actionType
            ]);
        }

        return $distance;
    }

    // Pengendali Simpan / Kemas Kini Rekod Kehadiran Manual (Admin Sahaja - Tanpa GPS API)
    public static function saveManual() {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security. Please refresh.');
        }

        $id       = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
        $userId   = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int)$_POST['user_id'] : null;
        $date     = $_POST['date'] ?? '';
        $clockIn  = $_POST['clock_in'] ?? null;
        $clockOut = $_POST['clock_out'] ?? null;
        $status   = $_POST['status'] ?? 'MC';

        if (!$userId) {
            Helper::json('error', 'Please select a staff member.');
        }
        if (empty($date)) {
            Helper::json('error', 'Please select the record date.');
        }

        $recordId = Attendance::saveManualRecord([
            'id'        => $id,
            'user_id'   => $userId,
            'date'      => $date,
            'clock_in'  => $clockIn,
            'clock_out' => $clockOut,
            'status'    => $status
        ]);

        $saved = Attendance::getById($recordId);

        Helper::json('success', "Attendance record saved successfully (Status: " . strtoupper($status) . ").", [
            'record_id' => $recordId,
            'status'    => $status,
            'record'    => $saved
        ]);
    }

    // Dapatkan butiran rekod kehadiran untuk diedit oleh Admin
    public static function getRecord() {
        Auth::requireAdmin();

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            Helper::json('error', 'Invalid record ID.');
        }

        $record = Attendance::getById($id);
        if (!$record) {
            Helper::json('error', 'Attendance record not found.');
        }

        Helper::json('success', 'Record found.', $record);
    }
}
?>
