<?php
/**
 * Organization Controller — TBBA ERP Module 7
 * Department + Branch management with org chart view.
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/Branch.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Position.php';
require_once __DIR__ . '/../models/AuditLog.php';

class OrganizationController {
    public static function index(): void {
        Auth::requireLogin();
        $canViewDepartments = Auth::hasPermission('departments', 'view');
        $canViewBranches    = Auth::hasPermission('branches', 'view');
        $canViewPositions   = Auth::hasPermission('positions', 'view');
        $canViewOrgChart    = Auth::hasPermission('org_chart', 'view');
        if (!$canViewDepartments && !$canViewBranches && !$canViewPositions && !$canViewOrgChart) {
            Helper::redirect('index.php?page=dashboard');
        }

        $pageTitle   = 'Organization & Departments';
        $canCreateDepartment = Auth::hasPermission('departments', 'create');
        $canEditDepartment   = Auth::hasPermission('departments', 'edit');
        $canDeleteDepartment = Auth::hasPermission('departments', 'delete');
        $canCreateBranch     = Auth::hasPermission('branches', 'create');
        $canEditBranch       = Auth::hasPermission('branches', 'edit');
        $canDeleteBranch     = Auth::hasPermission('branches', 'delete');
        $canCreatePosition   = Auth::hasPermission('positions', 'create');
        $canEditPosition     = Auth::hasPermission('positions', 'edit');
        $canDeletePosition   = Auth::hasPermission('positions', 'delete');

        $needsDepartmentData = $canViewDepartments || $canCreateDepartment || $canEditDepartment
            || $canViewPositions || $canCreatePosition || $canEditPosition;
        $needsPositionData = $canViewPositions || $canCreatePosition || $canEditPosition;
        $departments = $needsDepartmentData ? Department::getAll() : [];
        $branches    = $canViewBranches ? Branch::getAll() : [];
        $positions   = $needsPositionData ? Position::getAll() : [];
        $allUsers    = ($canCreateDepartment || $canEditDepartment) ? User::getAll() : [];
        include __DIR__ . '/../views/organization/index.php';
    }

    // Department CRUD is already in RoleController; this is the page + additional stats
    public static function getStats(): void {
        Auth::requirePermission('departments', 'view');
        $depts = Department::getAll();
        $stats = [];
        foreach ($depts as $d) {
            $stats[] = [
                'id'         => $d['id'],
                'name'       => $d['name'],
                'code'       => $d['code'],
                'head_name'  => $d['head_name'],
                'status'     => $d['status'],
                'staff_count'=> Department::countStaff($d['id']),
            ];
        }
        Helper::json('success', 'OK', $stats);
    }

    // --- Positions CRUD ---

    public static function addPosition(): void {
        Auth::requirePermission('positions', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        if (!Helper::verifyCsrf($_POST['csrf_token'] ?? '')) Helper::json('error', 'Invalid CSRF token security.');
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'department_id' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
            'reports_to_position_id' => !empty($_POST['reports_to_position_id']) ? (int)$_POST['reports_to_position_id'] : null,
            'max_headcount' => (int)($_POST['max_headcount'] ?? 1),
            'status' => $_POST['status'] ?? 'active'
        ];
        try {
            Position::create($data);
        } catch (InvalidArgumentException $e) {
            Helper::json('error', $e->getMessage());
        }
        AuditLog::record('CREATE', 'Created Position: ' . $data['title']);
        Helper::json('success', 'Position created successfully');
    }

    public static function updatePosition(): void {
        Auth::requirePermission('positions', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        if (!Helper::verifyCsrf($_POST['csrf_token'] ?? '')) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) Helper::json('error', 'Invalid position ID.');
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'department_id' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
            'reports_to_position_id' => !empty($_POST['reports_to_position_id']) ? (int)$_POST['reports_to_position_id'] : null,
            'max_headcount' => (int)($_POST['max_headcount'] ?? 1),
            'status' => $_POST['status'] ?? 'active'
        ];
        try {
            Position::update($id, $data);
        } catch (InvalidArgumentException $e) {
            Helper::json('error', $e->getMessage());
        }
        AuditLog::record('UPDATE', 'Updated Position ID: ' . $id);
        Helper::json('success', 'Position updated successfully');
    }

    public static function deletePosition(): void {
        Auth::requirePermission('positions', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        if (!Helper::verifyCsrf($_POST['csrf_token'] ?? '')) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !Position::findById($id)) Helper::json('error', 'Position not found.');
        Position::delete($id);
        AuditLog::record('DELETE', 'Deleted Position ID: ' . $id);
        Helper::json('success', 'Position deleted successfully');
    }

    // --- Org Chart ---

    public static function chart(): void {
        Auth::requirePermission('org_chart', 'view');
        $pageTitle = 'Organization Chart';
        include __DIR__ . '/../views/organization/chart.php';
    }

    public static function getChartData(): void {
        Auth::requirePermission('org_chart', 'view');
        $data = Position::getOrgChartData();
        // The D3 library we'll use often expects a single root node or array of roots.
        // We wrap it in a root node if there are multiple top-level nodes, or just return the array.
        // Usually, a company has one CEO. Let's wrap it in a dummy root if there are multiple, or just return it.
        // For simplicity, we return the array.
        echo json_encode($data);
        exit;
    }
}
?>
