<?php
/**
 * Document & SOPs Controller
 * Company: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../models/Document.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';

class DocumentController {
    // Render Document Center Page
    public static function index() {
        Auth::requirePermission('documents', 'view');
        $selectedCategory = $_GET['category'] ?? 'ALL';
        $documents = Document::getAll($selectedCategory);
        $pageTitle = "Document Center & SOPs";
        
        include __DIR__ . '/../views/documents/index.php';
    }

    // Handle File Upload (Admin Only)
    public static function upload() {
        Auth::requireLogin();
        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') || isset($_GET['ajax']) || isset($_POST['ajax']) || (isset($_GET['action']) && $_GET['action'] === 'upload_document');

        if (!Auth::hasPermission('documents', 'create')) {
            if ($isAjax) Helper::json('error', 'Unauthorized access. You do not have permission to upload documents.');
            Helper::redirect('index.php?page=documents&error=unauthorized');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
            $csrf = $_POST['csrf_token'] ?? '';
            if (!Helper::verifyCsrf($csrf)) {
                if ($isAjax) Helper::json('error', 'Invalid CSRF token security.');
                Helper::redirect('index.php?page=documents&error=csrf_failed');
                return;
            }

            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? 'General Corporate');
            $description = trim($_POST['description'] ?? '');

            if (empty($title) || empty($_FILES['document_file']['name'])) {
                if ($isAjax) Helper::json('error', 'Please fill in the document title and select a file.');
                Helper::redirect('index.php?page=documents&error=missing_fields');
                return;
            }
            if (!in_array($category, ['HR & Policies', 'Operational SOPs', 'Forms & Templates', 'General Corporate'], true)
                || mb_strlen($title) > 255 || mb_strlen($description) > 5000) {
                if ($isAjax) Helper::json('error', 'Invalid category or field length.');
                Helper::redirect('index.php?page=documents&error=invalid_fields');
                return;
            }

            $file = $_FILES['document_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                if ($isAjax) Helper::json('error', 'File upload failed or file size exceeds maximum limit.');
                Helper::redirect('index.php?page=documents&error=upload_failed');
                return;
            }

            try {
                Helper::validateUpload($file, [
                    'pdf' => ['application/pdf'],
                    'doc' => ['application/msword', 'application/octet-stream'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                    'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
                    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
                    'zip' => ['application/zip', 'application/x-zip-compressed'],
                    'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
                    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
                ], 20 * 1024 * 1024);
            } catch (InvalidArgumentException $e) {
                if ($isAjax) Helper::json('error', $e->getMessage());
                Helper::redirect('index.php?page=documents&error=invalid_type');
                return;
            }

            // Ensure upload directory exists
            $uploadDirRelative = 'storage/private/documents';
            $uploadDirAbsolute = __DIR__ . '/../' . $uploadDirRelative;
            if (!is_dir($uploadDirAbsolute)) {
                mkdir($uploadDirAbsolute, 0750, true);
            }

            // Generate unique filename
            $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file['name']);
            $uniqueFilename = 'doc_' . time() . '_' . $safeName;
            $destination = $uploadDirAbsolute . '/' . $uniqueFilename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $filePath = $uploadDirRelative . '/' . $uniqueFilename;
                
                try {
                    $newId = Document::create([
                        'title'       => $title,
                        'category'    => $category,
                        'description' => $description,
                        'file_path'   => $filePath,
                        'file_name'   => $file['name'],
                        'file_size'   => $file['size'],
                        'uploaded_by' => Auth::id()
                    ]);
                } catch (Throwable $e) {
                    @unlink($destination);
                    error_log('[DocumentController] Upload record failed: ' . $e->getMessage());
                    if ($isAjax) Helper::json('error', 'Unable to save the document record.');
                    Helper::redirect('index.php?page=documents&error=database_error');
                    return;
                }

                // Record silent audit trail
                $timeStr = date('g:i A');
                AuditLog::record('DOCUMENT', "Uploaded official corporate document '{$title}' ({$category}) at {$timeStr}");

                if ($isAjax) {
                    Helper::json('success', 'Official document uploaded successfully and logged into Audit Trail.', [
                        'id' => $newId,
                        'title' => $title,
                        'category' => $category,
                        'description' => $description,
                        'file_path' => Helper::url('index.php?action=download_attachment&type=document&id=' . $newId),
                        'file_name' => $file['name'],
                        'file_size' => $file['size'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'uploader_name' => Auth::user()['name'] ?? 'Admin'
                    ]);
                }
                Helper::redirect('index.php?page=documents&success=uploaded');
            } else {
                if ($isAjax) Helper::json('error', 'Server error occurred while saving the uploaded file.');
                Helper::redirect('index.php?page=documents&error=upload_error');
            }
        } else {
            if ($isAjax) Helper::json('error', 'Invalid request.');
            Helper::redirect('index.php?page=documents');
        }
    }

    // Handle File Deletion (Admin Only)
    public static function delete() {
        Auth::requireLogin();
        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') || isset($_GET['ajax']) || isset($_POST['ajax']) || (isset($_GET['action']) && $_GET['action'] === 'delete_document');

        if (!Auth::hasPermission('documents', 'delete')) {
            if ($isAjax) Helper::json('error', 'Unauthorized access. You do not have permission to delete documents.');
            Helper::redirect('index.php?page=documents&error=unauthorized');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'])) {
            $csrf = $_POST['csrf_token'] ?? '';
            if (!Helper::verifyCsrf($csrf)) {
                if ($isAjax) Helper::json('error', 'Invalid CSRF token security.');
                Helper::redirect('index.php?page=documents&error=csrf_failed');
                return;
            }

            $id = intval($_POST['document_id']);
            $doc = Document::getById($id);

            if ($doc) {
                Document::delete($id);

                // Remove only files located inside the private document root.
                $relative = str_replace('\\', '/', ltrim((string)$doc['file_path'], '/'));
                if ($relative !== '' && !str_contains($relative, '..')) {
                    $fullPath = realpath(__DIR__ . '/../' . $relative);
                    $root = realpath(__DIR__ . '/../storage/private/documents');
                    if ($fullPath && $root && is_file($fullPath) && str_starts_with($fullPath, $root . DIRECTORY_SEPARATOR)) @unlink($fullPath);
                }

                // Record silent audit trail
                $timeStr = date('g:i A');
                AuditLog::record('DELETE', "Deleted official corporate document '{$doc['title']}' ({$doc['category']}) at {$timeStr}");

                if ($isAjax) {
                    Helper::json('success', 'Document permanently deleted and logged into Audit Trail.');
                }
                Helper::redirect('index.php?page=documents&success=deleted');
            } else {
                if ($isAjax) Helper::json('error', 'Document record not found.');
                Helper::redirect('index.php?page=documents&error=not_found');
            }
        } else {
            if ($isAjax) Helper::json('error', 'Invalid request method.');
            Helper::redirect('index.php?page=documents');
        }
    }
}
?>
