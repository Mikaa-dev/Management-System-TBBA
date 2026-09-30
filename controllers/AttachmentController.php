<?php
/** Permission-checked streaming for sensitive workflow attachments. */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/Tender.php';
require_once __DIR__ . '/../models/LeaveRequest.php';
require_once __DIR__ . '/../models/ExpenseClaim.php';
require_once __DIR__ . '/../models/Document.php';
require_once __DIR__ . '/../models/Letter.php';

class AttachmentController
{
    public static function download(): void
    {
        Auth::requireLogin();
        $type = trim((string)($_GET['type'] ?? ''));
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) self::notFound();

        $path = null;
        $downloadName = null;
        $allowedRoots = [];

        switch ($type) {
            case 'tender':
                Auth::requirePermission('tenders', 'view');
                $record = Tender::getById($id);
                if (!$record || !Auth::canAccessOwnedResource((int)$record['user_id'], 'tenders')) self::forbidden();
                $path = $record['attachment'] ?? null;
                $downloadName = basename((string)$path);
                $allowedRoots = ['uploads/tenders', 'storage/private/tenders'];
                break;

            case 'leave':
                Auth::requirePermission('leave', 'view');
                $record = LeaveRequest::findById($id);
                if (!$record || !Auth::canAccessOwnedResource((int)$record['user_id'], 'leave')) self::forbidden();
                $path = $record['attachment_path'] ?? null;
                $downloadName = basename((string)$path);
                $allowedRoots = ['uploads/leaves', 'storage/private/leaves'];
                break;

            case 'expense':
                Auth::requirePermission('expense', 'view');
                $record = ExpenseClaim::findById($id);
                if (!$record || !Auth::canAccessOwnedResource((int)$record['user_id'], 'expense')) self::forbidden();
                $path = $record['receipt_path'] ?? null;
                $downloadName = basename((string)$path);
                $allowedRoots = ['uploads/expenses', 'storage/private/expenses'];
                break;

            case 'document':
                Auth::requirePermission('documents', 'view');
                $record = Document::getById($id);
                if (!$record) self::notFound();
                $path = $record['file_path'] ?? null;
                $downloadName = $record['file_name'] ?? basename((string)$path);
                $allowedRoots = ['uploads/documents', 'storage/private/documents'];
                break;

            case 'letter':
                Auth::requirePermission('letters', 'view');
                $record = Letter::getById($id);
                if (!$record) self::notFound();
                $path = $record['file_path'] ?? null;
                $downloadName = basename((string)$path);
                $allowedRoots = ['uploads/letters', 'storage/private/letters'];
                break;

            default:
                self::notFound();
        }

        if (!$path) self::notFound();
        $absolutePath = realpath(__DIR__ . '/../' . ltrim((string)$path, '/'));
        if (!$absolutePath || !is_file($absolutePath) || !self::insideAllowedRoot($absolutePath, $allowedRoots)) {
            self::notFound();
        }

        $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename((string)$downloadName)) ?: 'attachment';
        $disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
        header('Content-Type: ' . (mime_content_type($absolutePath) ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes($safeName, "\"\\") . '"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        readfile($absolutePath);
        exit;
    }

    private static function insideAllowedRoot(string $absolutePath, array $roots): bool
    {
        foreach ($roots as $root) {
            $absoluteRoot = realpath(__DIR__ . '/../' . $root);
            if ($absoluteRoot && str_starts_with($absolutePath, $absoluteRoot . DIRECTORY_SEPARATOR)) return true;
        }
        return false;
    }

    private static function forbidden(): never
    {
        http_response_code(403);
        exit('Access denied.');
    }

    private static function notFound(): never
    {
        http_response_code(404);
        exit('Attachment not found.');
    }
}

