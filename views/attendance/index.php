<?php
/**
 * GPS Attendance Management and Clock-In/Out
 * Company: The Bridge Business Alliance (TBBA)
 */
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helper.php';
require_once __DIR__ . '/../../models/Attendance.php';
require_once __DIR__ . '/../../models/User.php';

Auth::requireLogin();
$currentUser = Auth::user();
$todayRecord = Attendance::getToday($currentUser['id']);
$canViewTeam = Auth::isAdmin() || Auth::isManager() || Auth::isHR() || Auth::isDeptHead() || (Auth::hasPermission('attendance', 'view') && !Auth::isStaff());
$deptScope   = Auth::getScopeDepartmentId();
$staffList   = $canViewTeam ? User::getAll($deptScope) : [];

// Read the selected month and year filters.
$filterMonth = isset($_GET['month']) ? Helper::clean($_GET['month']) : date('m'); // Current month by default, or 'all'.
$filterYear  = isset($_GET['year']) ? Helper::clean($_GET['year']) : date('Y');   // Current year by default, or 'all'.
$filterStaff = isset($_GET['staff_id']) ? Helper::clean($_GET['staff_id']) : '';

// Load more records when a specific month or year is selected.
$historyLimit = ($filterMonth === 'all' && $filterYear === 'all') ? 100 : 500;
$history = Attendance::getHistory(
    $canViewTeam ? null : $currentUser['id'],
    $historyLimit,
    $filterMonth,
    $filterYear,
    $canViewTeam && $filterStaff !== '' ? $filterStaff : null,
    $deptScope
);

// Load summary statistics for the selected month.
$filterSummary = Attendance::getFilterSummary(
    $filterMonth,
    $filterYear,
    $canViewTeam ? null : $currentUser['id'],
    $canViewTeam && $filterStaff !== '' ? $filterStaff : null,
    $deptScope
);

// Month names used in the interface.
$monthNamesEnglish = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];
$selectedMonthLabel = ($filterMonth && $filterMonth !== 'all' && isset($monthNamesEnglish[sprintf('%02d', (int)$filterMonth)]))
    ? $monthNamesEnglish[sprintf('%02d', (int)$filterMonth)] . ' ' . ($filterYear !== 'all' ? $filterYear : '')
    : 'All Records';

// Calculate previous and next months for quick navigation.
$currM = ($filterMonth && $filterMonth !== 'all') ? (int)$filterMonth : (int)date('m');
$currY = ($filterYear && $filterYear !== 'all') ? (int)$filterYear : (int)date('Y');

$prevTime = mktime(0, 0, 0, $currM - 1, 1, $currY);
$prevMStr = date('m', $prevTime);
$prevYStr = date('Y', $prevTime);

$nextTime = mktime(0, 0, 0, $currM + 1, 1, $currY);
$nextMStr = date('m', $nextTime);
$nextYStr = date('Y', $nextTime);

$prevLink = "index.php?page=attendance&month={$prevMStr}&year={$prevYStr}" . ($filterStaff ? "&staff_id={$filterStaff}" : "");
$nextLink = "index.php?page=attendance&month={$nextMStr}&year={$nextYStr}" . ($filterStaff ? "&staff_id={$filterStaff}" : "");

$pageTitle = "GPS Attendance";
$pageJs = "attendance_v2.js";
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar.php';
include __DIR__ . '/../layouts/navbar.php';
?>

<!-- Mobile Add to Home Screen banner -->
<div id="pwaInstallBanner" style="display: none; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 1px solid #BFDBFE; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(37,99,235,0.08);">
    <div style="display: flex; align-items: center; gap: 14px;">
        <div style="background: #2563EB; color: #FFFFFF; width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; box-shadow: 0 4px 10px rgba(37,99,235,0.3);">
            <i class="fa-solid fa-mobile-screen-button"></i>
        </div>
        <div>
            <h4 style="color: #1E3A8A; font-size: 14px; font-weight: 700; margin-bottom: 3px;">Install e-Attendance App on Your Mobile Device</h4>
            <p style="color: #334155; font-size: 12px; margin: 0; line-height: 1.4;">Access the GPS Clock-In terminal faster directly from your mobile home screen (without opening the browser daily).</p>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <button onclick="App.installPWA()" class="btn btn-primary" style="background: #2563EB; border: none; padding: 8px 16px; font-size: 12px; border-radius: 8px; font-weight: 600; box-shadow: 0 2px 6px rgba(37,99,235,0.3); display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-download"></i> Install Now
        </button>
        <button onclick="App.dismissPWABanner()" style="background: transparent; border: none; color: #64748B; font-size: 18px; padding: 4px 8px; cursor: pointer; border-radius: 6px;" title="Dismiss">
            &times;
        </button>
    </div>
</div>

<!-- Panel Tindakan Clock-In / Clock-Out GPS -->
<div class="card module-clock-card" style="margin-bottom: 30px; background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%); border-top: 4px solid var(--navy-medium);">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-satellite-dish" style="color: var(--accent-blue); font-size: 20px;"></i>
            <span>Biometric / Phone GPS Attendance Terminal</span>
        </div>
        <span class="badge badge-navy"><?= date('d F Y') ?></span>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 30px; align-items: center; justify-content: space-between;">
        <!-- Current status -->
        <div style="flex: 1; min-width: 260px;">
            <h4 style="color: var(--text-dark); margin-bottom: 12px; font-size: 15px;">Today's Status (<?= htmlspecialchars($currentUser['name']) ?>):</h4>
            <div style="display: flex; gap: 20px;">
                <div style="background: var(--bg-primary); padding: 12px 18px; border-radius: 8px; border: 1px solid var(--border-color); flex: 1;">
                    <span style="font-size: 11px; color: var(--text-muted); display: block; text-transform: uppercase;">Clock In</span>
                    <strong id="displayClockIn" style="font-size: 18px; color: var(--success);">
                        <?= !empty($todayRecord['clock_in']) ? date('h:i A', strtotime($todayRecord['clock_in'])) : '--:-- --' ?>
                    </strong>
                </div>
                <div style="background: var(--bg-primary); padding: 12px 18px; border-radius: 8px; border: 1px solid var(--border-color); flex: 1;">
                    <span style="font-size: 11px; color: var(--text-muted); display: block; text-transform: uppercase;">Clock Out</span>
                    <strong id="displayClockOut" style="font-size: 18px; color: var(--warning);">
                        <?= !empty($todayRecord['clock_out']) ? date('h:i A', strtotime($todayRecord['clock_out'])) : '--:-- --' ?>
                    </strong>
                </div>
            </div>
            
            <div id="gpsCoordsText" style="margin-top: 12px; font-size: 12px; color: var(--text-muted);">
                <i class="fa-solid fa-location-crosshairs" style="color: var(--accent-blue);"></i> Detecting your device GPS coordinates...
            </div>
        </div>

        <!-- AJAX actions -->
        <div style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: flex-end;">
            <form id="clockInForm" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
                <input type="hidden" name="lat" id="inLat">
                <input type="hidden" name="lng" id="inLng">
                <button type="submit" id="btnClockIn" class="btn btn-success <?= !empty($todayRecord['clock_in']) ? 'disabled' : '' ?>" style="padding: 14px 28px; font-size: 15px;" <?= !empty($todayRecord['clock_in']) ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-sign-in-alt"></i>
                    <span><?= !empty($todayRecord['clock_in']) ? 'Clocked In' : 'CLOCK IN' ?></span>
                </button>
            </form>

            <form id="clockOutForm" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
                <input type="hidden" name="lat" id="outLat">
                <input type="hidden" name="lng" id="outLng">
                <button type="submit" id="btnClockOut" class="btn btn-primary <?= (empty($todayRecord['clock_in']) || !empty($todayRecord['clock_out'])) ? 'disabled' : '' ?>" style="padding: 14px 28px; font-size: 15px; background: #D97706; border-color: #D97706;" <?= (empty($todayRecord['clock_in']) || !empty($todayRecord['clock_out'])) ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-sign-out-alt"></i>
                    <span><?= !empty($todayRecord['clock_out']) ? 'Clocked Out' : 'CLOCK OUT' ?></span>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Attendance history -->
<div class="card module-data-card">
    <div class="card-header module-toolbar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div class="card-title">
            <i class="fa-solid fa-list-check" style="color: var(--navy-medium);"></i>
            <span>Attendance History - <?= $canViewTeam ? 'All Staff (Team/Admin View)' : 'Personal View' ?></span>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if (Auth::hasPermission('attendance', 'edit')): ?>
            <button type="button" onclick="openAddManualRecordModal()" class="btn btn-primary" style="background: var(--navy-medium); border-color: var(--navy-medium); font-size: 13px; font-weight: 600; padding: 8px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-plus-circle"></i> Add Manual Record
            </button>
            <?php endif; ?>
            <span class="badge badge-navy"><?= htmlspecialchars($selectedMonthLabel) ?></span>
        </div>
    </div>

    <!-- Monthly filters -->
    <div style="background: var(--bg-primary, #F8FAFC); border-bottom: 1px solid var(--border-color, #E2E8F0); padding: 16px 24px;">
        <form method="GET" action="index.php" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: flex-start;">
            <input type="hidden" name="page" value="attendance">
            
            <!-- Month and year navigation -->
            <a href="<?= $prevLink ?>" class="btn btn-secondary" style="padding: 7px 11px; font-size: 13px; text-decoration: none; border-radius: 8px;" title="Previous Month">
                <i class="fa-solid fa-chevron-left"></i>
            </a>

            <!-- Select month -->
            <select name="month" class="form-control" style="width: auto; padding: 7px 12px; font-size: 13px; font-weight: 600; border-radius: 8px;" onchange="this.form.submit()">
                <option value="all" <?= $filterMonth === 'all' ? 'selected' : '' ?>>All Months</option>
                <?php 
                $monthsList = [
                    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                ];
                foreach ($monthsList as $mNum => $mLabel): ?>
                    <option value="<?= $mNum ?>" <?= sprintf('%02d', (int)$filterMonth) === $mNum && $filterMonth !== 'all' ? 'selected' : '' ?>>
                        <?= $mLabel ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Select year -->
            <select name="year" class="form-control" style="width: auto; padding: 7px 12px; font-size: 13px; font-weight: 600; border-radius: 8px;" onchange="this.form.submit()">
                <option value="all" <?= $filterYear === 'all' ? 'selected' : '' ?>>All Years</option>
                <?php for ($y = date('Y') + 1; $y >= 2024; $y--): ?>
                    <option value="<?= $y ?>" <?= (int)$filterYear === $y && $filterYear !== 'all' ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <a href="<?= $nextLink ?>" class="btn btn-secondary" style="padding: 7px 11px; font-size: 13px; text-decoration: none; border-radius: 8px;" title="Next Month">
                <i class="fa-solid fa-chevron-right"></i>
            </a>

            <?php if ($canViewTeam): ?>
            <!-- Select staff (Admin) -->
            <select name="staff_id" class="form-control" style="width: auto; max-width: 200px; padding: 7px 12px; font-size: 13px; font-weight: 600; border-radius: 8px;" onchange="this.form.submit()">
                <option value="">All Staff Members</option>
                <?php foreach ($staffList as $st): ?>
                    <option value="<?= $st['id'] ?>" <?= (int)$filterStaff === (int)$st['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($st['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <!-- Filter reset -->
            <?php if ($filterMonth !== date('m') || $filterYear !== date('Y') || !empty($filterStaff)): ?>
            <a href="index.php?page=attendance" class="btn btn-secondary" style="padding: 7px 14px; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;" title="Reset Filter">
                <i class="fa-solid fa-rotate-left"></i> Reset
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Selected month summary -->
    <div style="background: #FFFFFF; border-bottom: 1px solid var(--border-color, #E2E8F0); padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 6px; margin-right: 4px;">
                <i class="fa-solid fa-chart-simple" style="color: var(--navy-medium);"></i> Monthly Summary:
            </span>
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: #334155; white-space: nowrap;">
                <i class="fa-solid fa-calendar-days" style="color: var(--accent-blue);"></i>
                <span><?= htmlspecialchars($selectedMonthLabel) ?>:</span>
                <strong style="color: var(--navy-medium); margin-left: 4px;"><?= (int)$filterSummary['total_records'] ?> Records</strong>
            </div>
            <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap;">
                <i class="fa-solid fa-check-circle"></i> On Time: <?= (int)$filterSummary['total_present'] ?>
            </div>
            <div style="background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap;">
                <i class="fa-solid fa-clock"></i> Late: <?= (int)$filterSummary['total_late'] ?>
            </div>
            <?php if ((int)$filterSummary['total_others'] > 0): ?>
            <div style="background: #EEF2FF; border: 1px solid #C7D2FE; color: #4F46E5; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap;">
                <i class="fa-solid fa-notes-medical"></i> MC/Leave: <?= (int)$filterSummary['total_others'] ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table datatable" id="attendanceTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Staff Member</th>
                    <th>Position</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>GPS Location</th>
                    <th>Status</th>
                    <?php if (Auth::hasPermission('attendance', 'edit')): ?>
                    <th style="text-align: right;">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="attendanceTableBody">
                <?php if (empty($history)): ?>
                <tr>
                    <td colspan="<?= Auth::hasPermission('attendance', 'edit') ? 8 : 7 ?>" style="text-align: center; color: var(--text-muted); padding: 40px;">
                        <i class="fa-solid fa-calendar-xmark" style="font-size: 28px; color: #94A3B8; margin-bottom: 10px; display: block;"></i>
                        No attendance records found for <strong><?= htmlspecialchars($selectedMonthLabel) ?></strong>.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($history as $row): ?>
                    <tr id="record-row-<?= (int)$row['id'] ?>">
                        <td style="font-weight: 600;"><?= Helper::date($row['date'], 'd M Y') ?></td>
                        <td>
                            <strong style="color: var(--text-dark);"><?= htmlspecialchars($row['name']) ?></strong>
                        </td>
                        <td><span style="color: var(--text-muted); font-size: 12px;"><?= htmlspecialchars($row['position'] ?? 'Staff') ?></span></td>
                        <td style="color: var(--success); font-weight: 600;">
                            <?= !empty($row['clock_in']) ? date('h:i A', strtotime($row['clock_in'])) : '-' ?>
                        </td>
                        <td style="color: var(--warning); font-weight: 600;">
                            <?= !empty($row['clock_out']) ? date('h:i A', strtotime($row['clock_out'])) : '-' ?>
                        </td>
                        <td>
                            <?php if (!empty($row['clock_in_lat']) && !empty($row['clock_in_lng'])): ?>
                            <button class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px;" onclick="showGpsMap(<?= $row['clock_in_lat'] ?>, <?= $row['clock_in_lng'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                <i class="fa-solid fa-map-marker-alt" style="color: #DC2626;"></i> GPS Map
                            </button>
                            <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 11px;">Manual / No GPS</span>
                            <?php endif; ?>

                            <?php if (!empty($row['reason']) || !empty($row['photo'])): ?>
                            <div style="margin-top: 4px;">
                                <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px; background: #EFF6FF; border-color: #BFDBFE; color: #2563EB; display: inline-flex; align-items: center; gap: 4px;" onclick="showReasonPhotoModal('<?= htmlspecialchars(addslashes($row['name']), ENT_QUOTES) ?>', '<?= Helper::date($row['date'], 'd M Y') ?> (Clock In)', '<?= htmlspecialchars(addslashes($row['reason']), ENT_QUOTES) ?>', '<?= htmlspecialchars($row['photo'], ENT_QUOTES) ?>', <?= (int)$row['is_out_of_range'] ?>)">
                                    <i class="fa-solid fa-camera" style="color: #2563EB;"></i> Clock In Verification
                                </button>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($row['clock_out_reason']) || !empty($row['clock_out_photo'])): ?>
                            <div style="margin-top: 4px;">
                                <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px; background: #FFF1F2; border-color: #FECDD3; color: #E11D48; display: inline-flex; align-items: center; gap: 4px;" onclick="showReasonPhotoModal('<?= htmlspecialchars(addslashes($row['name']), ENT_QUOTES) ?>', '<?= Helper::date($row['date'], 'd M Y') ?> (Clock Out)', '<?= htmlspecialchars(addslashes($row['clock_out_reason']), ENT_QUOTES) ?>', '<?= htmlspecialchars($row['clock_out_photo'], ENT_QUOTES) ?>', <?= (int)$row['is_out_of_range'] ?>)">
                                    <i class="fa-solid fa-camera" style="color: #E11D48;"></i> Clock Out Verification
                                </button>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['is_out_of_range'])): ?>
                                <div style="margin-bottom: 4px;"><span class="badge" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-weight: 700; font-size: 10px;"><i class="fa-solid fa-route"></i> Outside Area</span></div>
                            <?php endif; ?>
                            <?php 
                            $stUpper = strtoupper(trim($row['status'] ?? ''));
                            if ($stUpper === 'MC'): ?>
                                <span class="badge" style="background: #E0E7FF; color: #4F46E5; border: 1px solid #C7D2FE; font-weight: 700;"><i class="fa-solid fa-notes-medical"></i> MC</span>
                            <?php elseif ($stUpper === 'LATE'): ?>
                                <span class="badge badge-danger">Late</span>
                            <?php elseif ($stUpper === 'HALF_DAY'): ?>
                                <span class="badge badge-warning">Half Day</span>
                            <?php elseif ($stUpper === 'ABSENT'): ?>
                                <span class="badge badge-danger" style="background: #FEE2E2; color: #DC2626;">Absent</span>
                            <?php elseif ($stUpper === 'LEAVE'): ?>
                                <span class="badge badge-info" style="background: #E0F2FE; color: #0284C7;">On Leave</span>
                            <?php else: ?>
                                <span class="badge badge-success">Present / On Time</span>
                            <?php endif; ?>
                        </td>
                        <?php if (Auth::hasPermission('attendance', 'edit')): ?>
                        <td style="text-align: right;">
                            <button type="button" class="btn btn-secondary" style="padding: 5px 12px; font-size: 12px; border-radius: 6px;" onclick="openEditManualRecordModal(<?= (int)$row['id'] ?>, <?= (int)$row['user_id'] ?>, '<?= htmlspecialchars($row['date'], ENT_QUOTES) ?>', '<?= !empty($row['clock_in']) ? date('H:i', strtotime($row['clock_in'])) : '' ?>', '<?= !empty($row['clock_out']) ? date('H:i', strtotime($row['clock_out'])) : '' ?>', '<?= htmlspecialchars($row['status'] ?? 'MC', ENT_QUOTES) ?>')">
                                <i class="fa-solid fa-pen-to-square" style="color: var(--accent-blue);"></i> Edit
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (Auth::hasPermission('attendance', 'edit')): ?>
<!-- Modal Add / Edit Manual Attendance Record (Admin Only) -->
<div id="manualAttendanceModal" class="modal-overlay module-clean-modal">
    <div class="modal-box" style="max-width: 540px; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 100%); padding: 20px 24px; color: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 id="manualModalTitle" style="font-size: 18px; font-weight: 700; margin: 0; color: #FFFFFF;"><i class="fa-solid fa-user-clock"></i> Add Manual Record</h3>
                <span id="manualModalSubtitle" style="font-size: 12px; color: #94A3B8;">Direct record entry</span>
            </div>
            <button type="button" onclick="App.closeModal('manualAttendanceModal')" style="background: none; border: none; color: #94A3B8; font-size: 24px; cursor: pointer;">&times;</button>
        </div>

        <form id="manualAttendanceForm" onsubmit="return false;" style="padding: 24px;">
            <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
            <input type="hidden" name="id" id="manualRecordId" value="">

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Staff Member *</label>
                <select name="user_id" id="manualStaffId" class="form-control" required style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #CBD5E1;">
                    <option value="">-- Select Staff Member --</option>
                    <?php foreach ($staffList as $st): ?>
                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['position'] ?? $st['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Date *</label>
                <input type="date" name="date" id="manualDate" class="form-control" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #CBD5E1;">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Status *</label>
                <select name="status" id="manualStatus" class="form-control" required style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #CBD5E1; font-weight: 600;" onchange="handleManualStatusChange(this.value)">
                    <option value="MC" selected style="color: #4F46E5; font-weight: 700;">MC (Medical Certificate)</option>
                    <option value="present">Present / On Time</option>
                    <option value="late">Late</option>
                    <option value="half_day">Half Day</option>
                    <option value="absent">Absent</option>
                    <option value="leave">On Leave</option>
                </select>
                <small id="manualStatusHint" style="color: #64748B; font-size: 11px; display: block; margin-top: 4px;">
                    <i class="fa-solid fa-circle-info"></i>
                </small>
            </div>

            <div id="manualTimeGroup" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Clock In Time</label>
                    <input type="time" name="clock_in" id="manualClockIn" class="form-control" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #CBD5E1;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Clock Out Time</label>
                    <input type="time" name="clock_out" id="manualClockOut" class="form-control" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #CBD5E1;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn" onclick="App.closeModal('manualAttendanceModal')" style="background: #F1F5F9; color: #475569; padding: 10px 20px; font-weight: 600; border: none; border-radius: 8px;">Cancel</button>
                <button type="submit" id="btnSaveManual" class="btn btn-primary" style="background: var(--navy-medium); border-color: var(--navy-medium); padding: 10px 24px; font-weight: 600; border-radius: 8px;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Record
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Remote / out-of-area attendance modal (reason and selfie) -->
<div id="remoteAttendanceModal" class="modal-overlay module-clean-modal">
    <div class="modal-box" style="max-width: 580px; overflow: hidden; border-radius: 20px;">
        <div style="background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 100%); padding: 20px 24px; color: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="font-size: 18px; font-weight: 700; margin: 0; color: #FFFFFF;"><i class="fa-solid fa-camera-web" style="color: #38BDF8;"></i> Out-of-Office / Remote Attendance</h3>
                <span style="font-size: 12px; color: #94A3B8;">GPS Verification & Selfie Photo Capture</span>
            </div>
            <button type="button" onclick="closeRemoteAttendanceModal()" style="background: none; border: none; color: #94A3B8; font-size: 24px; cursor: pointer;">&times;</button>
        </div>

        <form id="remoteAttendanceForm" onsubmit="return false;" style="padding: 24px; max-height: 80vh; overflow-y: auto;">
            <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
            <input type="hidden" name="action_type" id="remoteActionType" value="clock_in">
            <input type="hidden" name="lat" id="remoteLat" value="">
            <input type="hidden" name="lng" id="remoteLng" value="">
            <input type="hidden" name="photo" id="remotePhotoData" value="">

            <!-- Banner Info GPS -->
            <div id="remoteGpsInfoBanner" style="background: #FEF3C7; border: 1px solid #FDE68A; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; color: #92400E; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-location-exclamation" style="font-size: 20px; color: #D97706;"></i>
                <div>
                    <strong id="remoteGpsTitle">📍 You are detected outside the HQ area (> 150 meters)</strong>
                    <p id="remoteGpsDesc" style="margin: 2px 0 0 0; font-size: 12px; color: #78350F;">Please state your reason and take a verification selfie from your camera before submitting.</p>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 18px;">
                <label style="display: block; font-size: 13px; font-weight: 700; color: #1E293B; margin-bottom: 6px;">Reason for Out-of-Office Attendance *</label>
                <textarea id="remoteReasonInput" name="reason" rows="3" placeholder="Please state why you are clocking in/out outside the office area" class="form-control" style="width: 100%; padding: 12px; border-radius: 10px; border: 1.5px solid #CBD5E1; font-weight: 500; font-size: 14px; resize: vertical;"></textarea>
            </div>

            <!-- Web Camera Capture Area -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 700; color: #1E293B; margin-bottom: 8px;">
                    <span>📷 Verification Selfie Photo *</span>
                </label>
                <div style="background: #0F172A; border-radius: 14px; padding: 14px; text-align: center; position: relative; border: 2px solid #334155;">
                    <video id="webcamVideo" autoplay playsinline style="width: 100%; max-height: 260px; border-radius: 10px; background: #000; object-fit: cover;"></video>
                    <img id="webcamPreview" style="display: none; width: 100%; max-height: 260px; border-radius: 10px; object-fit: cover; border: 2px solid #10B981;">
                    
                    <div style="margin-top: 14px; display: flex; justify-content: center; gap: 12px;">
                        <button type="button" id="btnSnapPhoto" onclick="snapWebcamPhoto()" class="btn btn-primary" style="background: #2563EB; border: none; padding: 10px 20px; font-size: 13px; font-weight: 700; border-radius: 50px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(37,99,235,0.4);">
                            <i class="fa-solid fa-camera"></i> Snap Photo
                        </button>
                        <button type="button" id="btnRetakePhoto" onclick="retakeWebcamPhoto()" class="btn btn-secondary" style="display: none; background: #475569; color: #FFF; border: none; padding: 10px 20px; font-size: 13px; font-weight: 700; border-radius: 50px; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-rotate-right"></i> Retake Photo
                        </button>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #E2E8F0; padding-top: 16px;">
                <button type="button" class="btn" onclick="closeRemoteAttendanceModal()" style="background: #F1F5F9; color: #475569; padding: 12px 20px; font-weight: 600; border: none; border-radius: 10px;">Cancel</button>
                <button type="button" id="btnSubmitRemote" onclick="submitRemoteAttendance()" class="btn btn-success" style="background: #059669; border: none; padding: 12px 26px; font-weight: 700; border-radius: 10px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(5,150,105,0.3);">
                    <i class="fa-solid fa-check-double"></i> Submit Attendance
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reason and selfie viewer modal -->
<div id="viewReasonPhotoModal" class="modal-overlay module-clean-modal">
    <div class="modal-box" style="max-width: 520px; overflow: hidden; border-radius: 20px;">
        <div style="background: linear-gradient(135deg, #1E293B 0%, #334155 100%); padding: 18px 24px; color: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="font-size: 17px; font-weight: 700; margin: 0; color: #FFFFFF;"><i class="fa-solid fa-file-shield" style="color: #38BDF8;"></i> Attendance Verification Details</h3>
                <span id="viewReasonPhotoSubtitle" style="font-size: 12px; color: #94A3B8;">Reason & Selfie Photo</span>
            </div>
            <button type="button" onclick="App.closeModal('viewReasonPhotoModal')" style="background: none; border: none; color: #94A3B8; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div style="padding: 24px;">
            <div style="margin-bottom: 16px;">
                <span style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Attendance Reason</span>
                <div id="viewReasonText" style="background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 10px; padding: 12px 16px; font-size: 14px; font-weight: 600; color: #0F172A; margin-top: 4px;"></div>
            </div>
            <div>
                <span style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Selfie Verification Photo</span>
                <div style="margin-top: 6px; text-align: center; background: #F1F5F9; border-radius: 12px; padding: 10px; border: 1px solid #E2E8F0;">
                    <img id="viewPhotoImage" src="" alt="No Photo Attached" style="max-width: 100%; max-height: 380px; border-radius: 10px; object-fit: contain; display: none;">
                    <div id="viewPhotoEmpty" style="padding: 30px; color: #94A3B8; font-size: 13px; display: none;">
                        <i class="fa-solid fa-camera-slash" style="font-size: 28px; margin-bottom: 8px; display: block;"></i> No selfie photo attached for this record.
                    </div>
                </div>
            </div>
            <div style="margin-top: 20px; text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('viewReasonPhotoModal')" style="padding: 10px 24px; border-radius: 10px; font-weight: 600;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Fungsi papar modal Peta GPS
    function showGpsMap(lat, lng, name) {
        const content = document.getElementById('gpsMapContent');
        const mapUrl = `https://www.openstreetmap.org/export/embed.html?bbox=${lng-0.005},${lat-0.005},${lng+0.005},${lat+0.005}&layer=mapnik&marker=${lat},${lng}`;
        
        content.innerHTML = `
            <div style="margin-bottom: 10px;"><strong> Staff Member: ${name}</strong><br><span style="font-size:12px; color:#64748B;">Latitude: ${lat}, Longitude: ${lng}</span></div>
            <iframe width="100%" height="280" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="${mapUrl}" style="border: 1px solid #e2e8f0; border-radius: 8px;"></iframe>
            <div style="margin-top: 10px;">
                <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" class="btn btn-primary" style="font-size: 12px;">
                    <i class="fa-solid fa-up-right-from-square"></i> Open in Google Maps
                </a>
            </div>
        `;
        App.openModal('gpsMapModal');
    }

    // Fungsi papar modal Lihat Sebab & Swafoto
    function showReasonPhotoModal(name, dateStr, reason, photo, isOut) {
        const subEl = document.getElementById('viewReasonPhotoSubtitle');
        const reasonEl = document.getElementById('viewReasonText');
        const photoImg = document.getElementById('viewPhotoImage');
        const photoEmpty = document.getElementById('viewPhotoEmpty');

        if (subEl) subEl.textContent = `${name} (${dateStr})`;
        if (reasonEl) reasonEl.textContent = reason || (isOut ? 'Remote / Out-of-Office Record' : 'No specific notes provided.');
        
        if (photo && photo.length > 50) {
            if (photoImg) {
                photoImg.src = photo;
                photoImg.style.display = 'inline-block';
            }
            if (photoEmpty) photoEmpty.style.display = 'none';
        } else {
            if (photoImg) photoImg.style.display = 'none';
            if (photoEmpty) photoEmpty.style.display = 'block';
        }

        App.openModal('viewReasonPhotoModal');
    }
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
