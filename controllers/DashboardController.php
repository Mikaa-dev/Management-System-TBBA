<?php
/**
 * Controller Dashboard Berpusat (Admin & Staff)
 * Company: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Tender.php';
require_once __DIR__ . '/../models/Letter.php';
require_once __DIR__ . '/../models/SystemHealth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/LeaveRequest.php';
require_once __DIR__ . '/../models/ExpenseClaim.php';
require_once __DIR__ . '/../models/PurchaseRequest.php';
require_once __DIR__ . '/../models/FinanceRecord.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/LogisticsRecord.php';

class DashboardController {
    public static function index() {
        Auth::requireLogin();
        $user = Auth::user();
        $role = $user['role'] ?? 'staff';

        // Universal & Role-specific KPI metrics
        $attendanceStats  = Attendance::getTodayStats();
        $pendingLeaves    = LeaveRequest::getPending();
        $totalHeadcount   = count(User::getAll());
        $pendingExpenses  = ExpenseClaim::getPending();
        $pendingPurchases = PurchaseRequest::getPending();
        $purchasesStats   = FinanceRecord::getStats('purchases');
        $budgetUsed       = ($purchasesStats['paid_sum'] ?? 0) + ($purchasesStats['confirmed_sum'] ?? 0);
        $canViewLogistics = Auth::hasPermission('logistics', 'view');
        $logisticsStats   = $canViewLogistics
            ? LogisticsRecord::getStats()
            : ['total' => 0, 'active_deliveries' => 0, 'demo_items' => 0, 'overdue' => 0, 'due_soon' => 0];

        if (Auth::hasPermission('system', 'view')) {
            // Analitik Dashboard Admin / CEO
            $tenderKpi       = Tender::getCompanyKpi();
            $pendingLetters  = Letter::getPendingCount();
            $diskUsage       = SystemHealth::getDiskUsage();
            
            // Senarai aktiviti terkini untuk Widget
            $recentAttendance = Attendance::getHistory(null, 6);
            $recentLetters    = array_slice(Letter::getAll(), 0, 5);

            include __DIR__ . '/../views/dashboard/admin.php';
        } else {
            // Analitik Dashboard Staf Eksekutif / HR / Finance / etc.
            $todayAttendance   = Attendance::getToday($user['id']);
            $myKpi             = Tender::getKpi($user['id']);
            $myAttendanceMonth = Attendance::getFilterSummary(date('m'), date('Y'), $user['id']);
            $myPendingLeaves   = LeaveRequest::countByStatus((int)$user['id'], 'pending');
            $myPendingExpenses = ExpenseClaim::countByStatus((int)$user['id'], 'pending');
            $myPendingPurchases = PurchaseRequest::countByStatus((int)$user['id'], 'pending');
            $myLogisticsStats  = $canViewLogistics
                ? LogisticsRecord::getStatsForResponsible((int)$user['id'])
                : ['active' => 0, 'overdue' => 0, 'due_soon' => 0];
            $myLogisticsRecords = $canViewLogistics
                ? LogisticsRecord::getForResponsible((int)$user['id'], 5)
                : [];
            
            $recentTenders   = array_slice(Tender::getByUser($user['id']), 0, 5);
            $recentLetters   = array_slice(Letter::getAll(null, $user['id']), 0, 5);

            // Calculate personal leave balance for Staff view
            $leaveTypes = LeaveRequest::getLeaveTypes();
            $myLeaveBalances = [];
            foreach ($leaveTypes as $t) {
                $used = LeaveRequest::getTotalDaysUsed($user['id'], $t['id'], date('Y'));
                $myLeaveBalances[] = [
                    'name'      => $t['name'],
                    'code'      => $t['code'] ?? '',
                    'allowed'   => (float)$t['days_allowed'],
                    'used'      => $used,
                    'remaining' => max(0, (float)$t['days_allowed'] - $used)
                ];
            }

            include __DIR__ . '/../views/dashboard/staff.php';
        }
    }
}
?>
