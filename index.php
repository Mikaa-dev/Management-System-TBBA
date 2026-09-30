<?php
/**
 * Front Controller & Routing Engine
 * Company: The Bridge Business Alliance (TBBA)
 * Updated: Module 1 — Roles & Permissions, expanded AJAX actions
 */

require_once __DIR__ . '/config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = tbba_is_https();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}


// Security headers — prevent browser back/forward cache exposure
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

header(
    'Permissions-Policy: camera=(self), geolocation=(self), microphone=()'
);

header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' "
    . "https://code.jquery.com "
    . "https://cdn.datatables.net "
    . "https://www.gstatic.com "
    . "https://d3js.org "
    . "https://cdn.jsdelivr.net; "
    . "style-src 'self' 'unsafe-inline' "
    . "https://cdnjs.cloudflare.com "
    . "https://cdn.datatables.net "
    . "https://fonts.googleapis.com; "
    . "font-src 'self' "
    . "https://cdnjs.cloudflare.com "
    . "https://fonts.gstatic.com "
    . "data:; "
    . "img-src 'self' "
    . "https://images.unsplash.com "
    . "data: blob:; "
    . "connect-src 'self' "
    . "https://fcm.googleapis.com "
    . "https://*.googleapis.com "
    . "https://*.firebaseio.com; "
    . "frame-src 'self' "
    . "https://www.openstreetmap.org "
    . "https://www.google.com "
    . "https://maps.google.com; "
    . "frame-ancestors 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'"
);


if (tbba_is_https()) {

    header(
        'Strict-Transport-Security: '
        . 'max-age=31536000; includeSubDomains'
    );

}


// ─────────────────────────────────────────────────────────────
// CORE
// ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/config/database.php';

require_once __DIR__ . '/core/Helper.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Permission.php';
require_once __DIR__ . '/core/SecurityFirewall.php';


// Web Application Firewall & Anti-DDoS
SecurityFirewall::init();


// ─────────────────────────────────────────────────────────────
// PERSISTENT LOGIN
// ─────────────────────────────────────────────────────────────

if (
    !Auth::check()
    && isset($_COOKIE['tbba_remember_token'])
) {

    Auth::loginWithToken(
        $_COOKIE['tbba_remember_token']
    );

}


// ─────────────────────────────────────────────────────────────
// CONTROLLERS
// ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/controllers/AuthController.php';

require_once __DIR__ . '/controllers/SecurityController.php';

require_once __DIR__ . '/controllers/DashboardController.php';

require_once __DIR__ . '/controllers/AttendanceController.php';

require_once __DIR__ . '/controllers/TenderController.php';

require_once __DIR__ . '/controllers/TenderBoardController.php';

require_once __DIR__ . '/controllers/SystemHealthController.php';

require_once __DIR__ . '/controllers/PublicController.php';

require_once __DIR__ . '/controllers/InquiryController.php';

require_once __DIR__ . '/controllers/LetterController.php';

require_once __DIR__ . '/controllers/StaffController.php';

require_once __DIR__ . '/controllers/DocumentController.php';

require_once __DIR__ . '/controllers/AuditLogController.php';

require_once __DIR__ . '/controllers/RoleController.php';

require_once __DIR__ . '/controllers/LeaveController.php';

require_once __DIR__ . '/controllers/ExpenseController.php';

require_once __DIR__ . '/controllers/PurchaseController.php';

require_once __DIR__ . '/controllers/ApprovalController.php';

require_once __DIR__ . '/controllers/OrganizationController.php';

require_once __DIR__ . '/controllers/AnnouncementController.php';

require_once __DIR__ . '/controllers/CalendarController.php';

require_once __DIR__ . '/controllers/NotificationController.php';

require_once __DIR__ . '/controllers/FinanceRecordController.php';

require_once __DIR__ . '/controllers/FinanceWorkflowController.php';

require_once __DIR__ . '/controllers/ProfileController.php';

require_once __DIR__ . '/controllers/ReportController.php';

// E-Sign
require_once __DIR__ . '/controllers/SignatureController.php';

require_once __DIR__ . '/controllers/LogisticsController.php';

require_once __DIR__ . '/controllers/AttachmentController.php';

require_once __DIR__ . '/controllers/LogbookController.php';


// ─────────────────────────────────────────────────────────────
// MODELS
// ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/models/User.php';

require_once __DIR__ . '/models/AccountSecurity.php';

require_once __DIR__ . '/models/FinanceRecord.php';

require_once __DIR__ . '/models/FinanceWorkflow.php';

require_once __DIR__ . '/models/Role.php';

require_once __DIR__ . '/models/Department.php';

require_once __DIR__ . '/models/Branch.php';

require_once __DIR__ . '/models/AuditLog.php';

require_once __DIR__ . '/models/NotificationToken.php';

require_once __DIR__ . '/models/NotificationPushLog.php';


// ============================================================
// 1. AJAX / ACTION ROUTES
// ============================================================

$action = $_GET['action'] ?? null;


if ($action) {

    /*
     * Public actions yang dibenarkan tanpa login.
     */
    $publicActions = [

        'login',

        'google_login',

        'google_callback',

        'verify_two_factor',

        'request_password_reset',

        'reset_password',

        'submit_inquiry'

    ];


    if (
        !in_array(
            $action,
            $publicActions,
            true
        )
    ) {

        Auth::requireLogin();

    }


    switch ($action) {


        // ====================================================
        // AUTH
        // ====================================================

        case 'login':

            AuthController::login();

            break;


        case 'google_login':

            AuthController::googleLogin();

            break;


        case 'google_callback':

            AuthController::googleCallback();

            break;


        case 'verify_two_factor':

            SecurityController::verifyTwoFactor();

            break;


        case 'request_password_reset':

            SecurityController::requestReset();

            break;


        case 'reset_password':

            SecurityController::resetPassword();

            break;


        case 'begin_two_factor':

            SecurityController::beginTwoFactor();

            break;


        case 'enable_two_factor':

            SecurityController::enableTwoFactor();

            break;


        case 'disable_two_factor':

            SecurityController::disableTwoFactor();

            break;


        case 'revoke_session':

            SecurityController::revokeSession();

            break;


        case 'revoke_other_sessions':

            SecurityController::revokeOtherSessions();

            break;


        case 'revoke_trusted_devices':

            SecurityController::revokeTrustedDevices();

            break;


        case 'save_security_settings':

            SecurityController::saveSettings();

            break;



        // ====================================================
        // ATTENDANCE
        // ====================================================

        case 'clock_in':

            AttendanceController::clockIn();

            break;


        case 'clock_out':

            AttendanceController::clockOut();

            break;


        case 'save_manual_attendance':

            AttendanceController::saveManual();

            break;


        case 'get_attendance_record':

            AttendanceController::getRecord();

            break;



        // ====================================================
        // TENDERS
        // ====================================================

        case 'add_tender':

            TenderController::store();

            break;


        case 'update_tender_status':

            TenderController::updateStatus();

            break;


        case 'delete_tender':

            TenderController::delete();

            break;



        // ====================================================
        // LOGBOOK
        // ====================================================

        case 'logbook_save_entry':

            LogbookController::saveEntry();

            break;


        case 'logbook_submit':

            LogbookController::submitReport();

            break;


        case 'logbook_upload_photo':

            LogbookController::uploadPhoto();

            break;


        case 'logbook_remove_photo':

            LogbookController::removePhoto();

            break;


        case 'logbook_photo':

            LogbookController::photo();

            break;


        case 'logbook_pdf':

            LogbookController::downloadPdf();

            break;



        // ====================================================
        // TENDER BOARD
        // ====================================================

        case 'add_tender_board':

            TenderBoardController::store();

            break;


        case 'update_tender_board':

            TenderBoardController::update();

            break;


        case 'delete_tender_board':

            TenderBoardController::delete();

            break;


        case 'toggle_tender_board_interest':

            TenderBoardController::toggleInterest();

            break;


        case 'update_tender_board_pricing':

            TenderBoardController::updateProjectPricing();

            break;


        case 'set_tender_board_result':

            TenderBoardController::setResult();

            break;


        case 'submit_tender_board':

            TenderBoardController::submitTender();

            break;


        case 'download_tender_pricing_file':

            TenderBoardController::downloadPricingFile();

            break;



        // ====================================================
        // LETTERS / INQUIRIES
        // ====================================================

        case 'add_letter':

            LetterController::store();

            break;


        case 'get_next_ref':

            LetterController::getNextRef();

            break;


        case 'update_letter_status':

            LetterController::updateStatus();

            break;


        case 'get_inquiry':

            LetterController::getLetter();

            break;


        case 'update_inquiry_minute':

            LetterController::updateMinit();

            break;


        case 'delete_inquiry':

            LetterController::delete();

            break;



        // ====================================================
        // DOCUMENTS
        // ====================================================

        case 'upload_document':

            DocumentController::upload();

            break;


        case 'delete_document':

            DocumentController::delete();

            break;


        case 'download_attachment':

            AttachmentController::download();

            break;



        // ====================================================
        // SYSTEM HEALTH
        // ====================================================

        case 'run_auto_backup':

            SystemHealthController::runBackup();

            break;


        case 'delete_backup':

            SystemHealthController::deleteBackup();

            break;


        case 'download_backup':

            SystemHealthController::downloadBackup();

            break;


        case 'test_backup_restore':

            SystemHealthController::testBackup();

            break;


        case 'benchmark_db':

            SystemHealthController::benchmarkDb();

            break;


        case 'get_waf_stats':

            SystemHealthController::getWafStats();

            break;


        case 'clear_waf_logs':

            SystemHealthController::clearWafLogs();

            break;



        // ====================================================
        // PUBLIC
        // ====================================================

        case 'submit_inquiry':

            PublicController::submitInquiry();

            break;



        // ====================================================
        // STAFF MANAGEMENT
        // ====================================================

        case 'get_staff':

            StaffController::getStaff();

            break;


        case 'add_staff':

            StaffController::store();

            break;


        case 'update_staff':

            StaffController::update();

            break;


        case 'delete_staff':

            StaffController::delete();

            break;


        case 'toggle_staff_status':

            StaffController::toggleStatus();

            break;



        // ====================================================
        // ROLES & PERMISSIONS
        // ====================================================

        case 'get_permission_matrix':

            RoleController::getMatrix();

            break;


        case 'update_role_permission':

            RoleController::updatePermission();

            break;


        case 'get_user_overrides':

            RoleController::getUserOverrides();

            break;


        case 'set_user_override':

            RoleController::setUserOverride();

            break;


        case 'clear_user_overrides':

            RoleController::clearUserOverrides();

            break;


        case 'get_roles':

            RoleController::getRoles();

            break;


        case 'get_departments':

            RoleController::getDepartments();

            break;


        case 'get_branches':

            RoleController::getBranches();

            break;


        case 'get_login_history':

            RoleController::getLoginHistory();

            break;



        // ====================================================
        // DEPARTMENT CRUD
        // ====================================================

        case 'add_department':

            RoleController::storeDepartment();

            break;


        case 'update_department':

            RoleController::updateDepartment();

            break;


        case 'delete_department':

            RoleController::deleteDepartment();

            break;



        // ====================================================
        // BRANCH CRUD
        // ====================================================

        case 'add_branch':

            RoleController::storeBranch();

            break;


        case 'update_branch':

            RoleController::updateBranch();

            break;


        case 'delete_branch':

            RoleController::deleteBranch();

            break;



        // ====================================================
        // LEAVE & PERMISSION
        // ====================================================

        case 'add_leave':

            LeaveController::store();

            break;


        case 'update_leave_status':

            LeaveController::approve();

            break;


        case 'cancel_leave':

            LeaveController::cancel();

            break;


        case 'get_leave':

            LeaveController::getRequest();

            break;


        case 'get_leave_balance':

            LeaveController::getBalance();

            break;



        // ====================================================
        // EXPENSE CLAIMS — NEW FINANCE WORKFLOW
        // ====================================================

        /*
         * Staff:
         * Submit Expense Claim
         */
        case 'add_expense':

            ExpenseController::store();

            break;


        /*
         * Finance Officer:
         *
         * Verify expense claim
         * and send to Approval Center.
         */
        case 'finance_verify_expense':

            ExpenseController::financeVerify();

            break;


        /*
         * Final Approval / Rejection
         */
        case 'update_expense_status':

            ExpenseController::approve();

            break;


        /*
         * Check whether approver
         * already signed claim.
         */
        case 'check_expense_approver_signature':

            ExpenseController::checkApproverSignature();

            break;


        /*
         * Finance Officer:
         * Mark approved expense as paid.
         */
        case 'mark_paid_expense':

            ExpenseController::markPaid();

            break;


        /*
         * Expense detail
         */
        case 'get_expense':

            ExpenseController::getClaim();

            break;


        /*
         * Secure item receipt download.
         */
        case 'download_expense_item_receipt':

            ExpenseController::downloadItemReceipt();

            break;


        /*
         * Delete / Cancel Expense
         */
        case 'delete_expense':

            ExpenseController::delete();

            break;



        // ====================================================
        // PURCHASE REQUESTS
        // ====================================================

        case 'add_purchase':

            PurchaseController::store();

            break;


        case 'update_purchase_status':

            PurchaseController::approve();

            break;


        case 'set_purchase_status':

            PurchaseController::updateStatus();

            break;


        case 'get_purchase':

            PurchaseController::getPR();

            break;


        case 'delete_purchase':

            PurchaseController::delete();

            break;



        // ====================================================
        // APPROVAL CENTER
        // ====================================================

        case 'get_approval_summary':

            ApprovalController::getSummary();

            break;


        case 'universal_approval_decide':

            ApprovalController::decide();

            break;



        // ====================================================
        // ORGANIZATION
        // ====================================================

        case 'get_org_stats':

            OrganizationController::getStats();

            break;


        case 'add_position':

            OrganizationController::addPosition();

            break;


        case 'update_position':

            OrganizationController::updatePosition();

            break;


        case 'delete_position':

            OrganizationController::deletePosition();

            break;


        case 'get_org_chart':

            OrganizationController::getChartData();

            break;



        // ====================================================
        // ANNOUNCEMENTS
        // ====================================================

        case 'get_announcement':

            AnnouncementController::getAnnouncement();

            break;


        case 'add_announcement':

            AnnouncementController::store();

            break;


        case 'update_announcement':

            AnnouncementController::update();

            break;


        case 'delete_announcement':

            AnnouncementController::delete();

            break;


        case 'toggle_pin_announcement':

            AnnouncementController::togglePin();

            break;



        // ====================================================
        // CALENDAR
        // ====================================================

        case 'get_events':

            CalendarController::getEvents();

            break;


        case 'get_event':

            CalendarController::getEvent();

            break;


        case 'add_event':

            CalendarController::store();

            break;


        case 'update_event':

            CalendarController::update();

            break;


        case 'delete_event':

            CalendarController::delete();

            break;


        case 'create_event_gallery':

            CalendarController::createMediaGallery();

            break;


        case 'get_event_gallery':

            CalendarController::getMediaGallery();

            break;



        // ====================================================
        // NOTIFICATIONS
        // ====================================================

        case 'get_notification_dropdown':

            NotificationController::getDropdown();

            break;


        case 'mark_read_notification':

            NotificationController::markRead();

            break;


        case 'mark_all_read_notification':

            NotificationController::markAllRead();

            break;


        case 'delete_notification':

            NotificationController::delete();

            break;


        case 'clear_all_notifications':

            NotificationController::clearAll();

            break;


        case 'mark_receipt_read':

            NotificationController::markReceiptRead();

            break;



        // ====================================================
        // E-SIGN
        // ====================================================

        case 'sign_document':

            SignatureController::sign();

            break;


        case 'verify_signature':

            SignatureController::verify();

            break;


        case 'generate_signed_pdf':

            SignatureController::generatePdf();

            break;


        case 'download_signed_pdf':

            SignatureController::downloadPdf();

            break;


        case 'get_signature_status':

            SignatureController::getStatus();

            break;


        case 'download_signature_image':

            SignatureController::downloadImage();

            break;



        // ====================================================
        // FCM PUSH NOTIFICATIONS
        // ====================================================

        case 'save_fcm_token':

            NotificationController::saveFcmToken();

            break;


        case 'remove_fcm_token':

            NotificationController::removeFcmToken();

            break;


        case 'check_push_status':

            NotificationController::checkPushStatus();

            break;


        case 'send_test_push':

            NotificationController::sendTestPush();

            break;



        // ====================================================
        // PROFILE
        // ====================================================

        case 'update_profile':

            ProfileController::update();

            break;



        // ====================================================
        // FINANCE OFFICER
        // SALES & PURCHASES
        // ====================================================

        case 'save_finance_record':

            FinanceRecordController::save_record();

            break;


        case 'delete_finance_record':

            FinanceRecordController::delete_record();

            break;


        case 'get_finance_record':

            FinanceRecordController::get_record();

            break;


        case 'download_finance_attachment':

            FinanceRecordController::downloadAttachment();

            break;


        case 'convert_finance_record':

            FinanceWorkflowController::convertRecord();

            break;


        case 'convert_purchase_request':

            FinanceWorkflowController::convertPurchaseRequest();

            break;


        case 'record_finance_payment':

            FinanceWorkflowController::recordPayment();

            break;


        case 'reconcile_finance_payment':

            FinanceWorkflowController::reconcilePayment();

            break;


        case 'save_bank_transaction':

            FinanceWorkflowController::saveBankTransaction();

            break;


        case 'match_bank_transaction':

            FinanceWorkflowController::matchBankTransaction();

            break;


        case 'save_cost_center':

            FinanceWorkflowController::saveCostCenter();

            break;


        case 'save_finance_budget':

            FinanceWorkflowController::saveBudget();

            break;


        case 'save_recurring_bill':

            FinanceWorkflowController::saveRecurring();

            break;


        case 'generate_recurring_bills':

            FinanceWorkflowController::generateRecurring();

            break;


        case 'save_finance_settings':

            FinanceWorkflowController::saveSettings();

            break;



        // ====================================================
        // PROJECT DELIVERY & DEMO ITEM TRACKER
        // ====================================================

        case 'get_logistics_record':

            LogisticsController::getRecord();

            break;


        case 'add_logistics_record':

            LogisticsController::store();

            break;


        case 'update_logistics_record':

            LogisticsController::update();

            break;


        case 'update_logistics_status':

            LogisticsController::updateStatus();

            break;


        case 'delete_logistics_record':

            LogisticsController::delete();

            break;



        // ====================================================
        // INVALID ACTION
        // ====================================================

        default:

            Helper::json(
                'error',
                'Invalid or non-existent action.'
            );

            break;

    }


    exit;

}


// ============================================================
// 2. PAGE ROUTES
// ============================================================

$page = $_GET['page'] ?? null;


/*
 * Apache akan handle clean URL melalui .htaccess.
 *
 * PHP built-in server tak baca .htaccess,
 * jadi kita detect REQUEST_URI juga.
 */
if (
    $page === null
    || $page === ''
) {

    $requestPath =
        parse_url(
            $_SERVER['REQUEST_URI'] ?? '/',
            PHP_URL_PATH
        );


    $cleanPath =
        trim(
            is_string($requestPath)
                ? rawurldecode($requestPath)
                : '',
            '/'
        );


    if (
        in_array(
            $cleanPath,
            [
                'portal',
                'system',
                'login'
            ],
            true
        )
    ) {

        $page = 'login';


    } elseif (
        $cleanPath !== ''
        && $cleanPath !== 'index.php'
        && preg_match(
            '/^[a-zA-Z0-9_-]+$/',
            $cleanPath
        )
    ) {

        $page = $cleanPath;


    } else {

        $page = 'home';

    }

}


// ============================================================
// PUBLIC PAGES
// ============================================================

$publicPages = [

    'home',

    'about',

    'services',

    'contact',

    'forgot_password',

    'reset_password'

];


/*
 * Elakkan internal ERP pages
 * daripada diindex Google.
 */
if (
    !in_array(
        $page,
        [
            'home',
            'about',
            'services',
            'contact'
        ],
        true
    )
) {

    header(
        'X-Robots-Tag: '
        . 'noindex, nofollow, noarchive',
        true
    );

}


/*
 * Semua internal page memerlukan login.
 */
if (
    $page !== 'login'
    && !in_array(
        $page,
        $publicPages,
        true
    )
) {

    Auth::requireLogin();

}


// ============================================================
// PAGE ROUTER
// ============================================================

switch ($page) {


    // ========================================================
    // PUBLIC WEBSITE
    // ========================================================

    case 'home':

    case 'about':

    case 'services':

    case 'contact':

        include __DIR__
            . '/views/public/home.php';

        break;



    // ========================================================
    // LOGIN
    // ========================================================

    case 'login':

        if (Auth::check()) {

            Helper::redirect(
                'index.php?page=dashboard'
            );

        }


        include __DIR__
            . '/views/auth/login.php';

        break;



    // ========================================================
    // FORGOT PASSWORD
    // ========================================================

    case 'forgot_password':

        if (Auth::check()) {

            Helper::redirect(
                'index.php?page=dashboard'
            );

        }


        include __DIR__
            . '/views/auth/forgot_password.php';

        break;



    // ========================================================
    // RESET PASSWORD
    // ========================================================

    case 'reset_password':

        if (Auth::check()) {

            Helper::redirect(
                'index.php?page=dashboard'
            );

        }


        $resetToken =
            trim(
                $_GET['token'] ?? ''
            );


        $resetUser =
            AccountSecurity::findResetUser(
                $resetToken
            );


        include __DIR__
            . '/views/auth/reset_password.php';

        break;



    // ========================================================
    // LOGOUT
    // ========================================================

    case 'logout':

        AuthController::logout();

        break;



    // ========================================================
    // DASHBOARD
    // ========================================================

    case 'dashboard':

        DashboardController::index();

        break;



    // ========================================================
    // ATTENDANCE
    // ========================================================

    case 'attendance':

        Auth::requireLogin();


        include __DIR__
            . '/views/attendance/index.php';

        break;



    // ========================================================
    // LEGACY TENDER REDIRECT
    // ========================================================

    case 'tenders':

        Auth::requirePermission(
            'tenders',
            'view'
        );


        $legacyTenderMonth =

            isset($_GET['month'])

            && preg_match(
                '/^\d{4}-\d{2}$/',
                (string)$_GET['month']
            )

                ? '&month='
                    . rawurlencode(
                        (string)$_GET['month']
                    )

                : '';


        Helper::redirect(

            'index.php?page=tender_board'
            . '&section=kpi'
            . $legacyTenderMonth

        );

        break;



    // ========================================================
    // TENDER BOARD
    // ========================================================

    case 'tender_board':

        TenderBoardController::index();

        break;


    case 'tender_board_report':

        TenderBoardController::report();

        break;



    // ========================================================
    // DOCUMENT CENTER
    // ========================================================

    case 'documents':

    case 'documents_upload':

    case 'documents_delete':

        DocumentController::index();

        break;



    // ========================================================
    // INQUIRIES & LETTERS
    // ========================================================

    case 'inquiries':

    case 'letters':

        Auth::requirePermission(
            'letters',
            'view'
        );


        LetterController::index();

        break;



    // ========================================================
    // STAFF
    // ========================================================

    case 'staff':

        StaffController::index();

        break;



    // ========================================================
    // ROLES & PERMISSIONS
    // ========================================================

    case 'roles':

        RoleController::index();

        break;



    // ========================================================
    // APPROVAL CENTER
    // ========================================================

    case 'approvals':

        ApprovalController::index();

        break;



    // ========================================================
    // LEAVE
    // ========================================================

    case 'leave':

        LeaveController::index();

        break;



    // ========================================================
    // EXPENSE CLAIMS
    // ========================================================

    case 'expense':

        ExpenseController::index();

        break;



    // ========================================================
    // PURCHASE REQUESTS
    // ========================================================

    case 'purchase':

        PurchaseController::index();

        break;



    // ========================================================
    // SALES
    // ========================================================

    case 'sales':

        FinanceRecordController::sales();

        break;



    // ========================================================
    // PURCHASES
    // ========================================================

    case 'purchases':

        FinanceRecordController::purchases();

        break;



    // ========================================================
    // FINANCE CONTROL
    // ========================================================

    case 'finance_control':

        FinanceWorkflowController::index();

        break;



    case 'finance_receipt':

        FinanceWorkflowController::receipt();

        break;



    // ========================================================
    // ORGANIZATION
    // ========================================================

    case 'organization':

        OrganizationController::index();

        break;



    case 'org_chart':

        OrganizationController::chart();

        break;



    // ========================================================
    // ANNOUNCEMENTS
    // ========================================================

    case 'announcements':

        AnnouncementController::index();

        break;



    // ========================================================
    // CALENDAR
    // ========================================================

    case 'calendar':

        CalendarController::index();

        break;



    // ========================================================
    // LOGBOOK
    // ========================================================

    case 'logbook':

        LogbookController::index();

        break;



    // ========================================================
    // NOTIFICATIONS
    // ========================================================

    case 'notifications':

        NotificationController::index();

        break;



    // ========================================================
    // LOGISTICS
    // ========================================================

    case 'logistics':

        LogisticsController::index();

        break;



    // ========================================================
    // AUDIT LOG
    // ========================================================

    case 'audit_logs':

        Auth::requirePermission(
            'audit_logs',
            'view'
        );


        AuditLogController::index();

        break;



    // ========================================================
    // SYSTEM HEALTH
    // ========================================================

    case 'system_health':

        Auth::requirePermission(
            'system',
            'view'
        );


        SystemHealthController::index();

        break;



    // ========================================================
    // REPORTS
    // ========================================================

    case 'reports':

        ReportController::index();

        break;



    // ========================================================
    // USER MANUAL
    // ========================================================

    case 'manual':

        Auth::requireLogin();


        include __DIR__
            . '/views/manual/index.php';

        break;



    // ========================================================
    // PROFILE
    // ========================================================

    case 'profile':

        Auth::requireLogin();


        if (
            class_exists(
                'ProfileController'
            )
        ) {

            ProfileController::index();


        } else {

            Helper::redirect(
                'index.php?page=dashboard'
            );

        }

        break;



    // ========================================================
    // 404 / UNKNOWN ROUTE
    // ========================================================

    default:

        if (Auth::check()) {

            Helper::redirect(
                'index.php?page=dashboard'
            );


        } else {

            Helper::redirect(
                'index.php?page=home'
            );

        }

        break;

}

?>