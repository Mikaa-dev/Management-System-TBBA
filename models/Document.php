<?php
/**
 * Document Management & SOPs Model (PDO)
 * Company: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../config/database.php';

class Document {
    private static bool $schemaChecked = false;

    // Verify migrations without changing schema during a web request.
    public static function ensureTable() {
        if (self::$schemaChecked) return;
        try {
            Database::query("SELECT id, title, category, description, file_path, file_name, file_size, uploaded_by, created_at FROM company_documents LIMIT 0");
            self::$schemaChecked = true;
        } catch (Throwable $e) {
            throw new RuntimeException('Document schema is unavailable. Run the database migrations.', 0, $e);
        }
    }

    // Get all documents with uploader details
    public static function getAll($category = null) {
        self::ensureTable();
        $sql = "SELECT d.*, u.name as uploader_name, u.role as uploader_role 
                FROM company_documents d 
                LEFT JOIN users u ON d.uploaded_by = u.id ";
        $params = [];

        if (!empty($category) && $category !== 'ALL') {
            $sql .= "WHERE d.category = ? ";
            $params[] = $category;
        }

        $sql .= "ORDER BY d.created_at DESC";
        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    // Get single document by ID
    public static function getById($id) {
        self::ensureTable();
        $stmt = Database::query("SELECT * FROM company_documents WHERE id = ? LIMIT 1", [$id]);
        return $stmt->fetch();
    }

    // Create new document record
    public static function create($data) {
        self::ensureTable();
        Database::query("INSERT INTO company_documents (title, category, description, file_path, file_name, file_size, uploaded_by) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)", [
            $data['title'],
            $data['category'],
            $data['description'] ?? '',
            $data['file_path'],
            $data['file_name'],
            $data['file_size'],
            $data['uploaded_by']
        ]);
        return Database::lastInsertId();
    }

    // Delete document record
    public static function delete($id) {
        self::ensureTable();
        return Database::query("DELETE FROM company_documents WHERE id = ?", [$id]);
    }
}
?>
