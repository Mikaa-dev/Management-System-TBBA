<?php
/**
 * Model Pemantauan Kesihatan Sistem & Auto-Backup
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../config/database.php';

class SystemHealth {

    // Dapatkan maklumat storan cakera keras (Disk Space Usage)
    public static function getDiskUsage() {
        $rootPath = __DIR__ . '/..';
        
        $totalBytes = @disk_total_space($rootPath);
        $freeBytes  = @disk_free_space($rootPath);
        
        if ($totalBytes === false || $freeBytes === false || $totalBytes <= 0) {
            return [
                'total' => 'Unavailable', 'free' => 'Unavailable', 'used' => 'Unavailable',
                'percentage' => 0, 'uploads_size' => self::formatBytes(self::getDirSize(__DIR__ . '/../uploads')),
                'status' => 'unavailable',
            ];
        }
        
        $usedBytes = $totalBytes - $freeBytes;
        $percentage = round(($usedBytes / $totalBytes) * 100, 1);
        
        // Saiz direktori muat naik (Uploads folder size)
        $uploadsSize = self::getDirSize(__DIR__ . '/../uploads');
        
        return [
            'total'       => self::formatBytes($totalBytes),
            'free'        => self::formatBytes($freeBytes),
            'used'        => self::formatBytes($usedBytes),
            'percentage'  => $percentage,
            'uploads_size'=> self::formatBytes($uploadsSize),
            'status'      => $percentage > 85 ? 'danger' : ($percentage > 70 ? 'warning' : 'good')
        ];
    }

    // Dapatkan kelajuan dan prestasi pertanyaan MySQL (Query Performance)
    public static function getMySqlPerformance() {
        try {
            // Uptime MySQL
            $stmt = Database::query("SHOW GLOBAL STATUS LIKE 'Uptime'");
            $uptimeRow = $stmt->fetch();
            $uptimeSeconds = $uptimeRow ? (int)$uptimeRow['Value'] : 1;

            // Total Queries
            $stmt = Database::query("SHOW GLOBAL STATUS LIKE 'Queries'");
            $queriesRow = $stmt->fetch();
            $totalQueries = $queriesRow ? (int)$queriesRow['Value'] : 0;

            // Slow Queries
            $stmt = Database::query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
            $slowRow = $stmt->fetch();
            $slowQueries = $slowRow ? (int)$slowRow['Value'] : 0;

            // Threads Connected (Active connections)
            $stmt = Database::query("SHOW GLOBAL STATUS LIKE 'Threads_connected'");
            $threadsRow = $stmt->fetch();
            $threadsConnected = $threadsRow ? (int)$threadsRow['Value'] : 1;

            // MySQL Version
            $stmt = Database::query("SHOW VARIABLES LIKE 'version'");
            $verRow = $stmt->fetch();
            $version = $verRow ? $verRow['Value'] : '8.0';

            // Kira QPS (Queries Per Second)
            $qps = round($totalQueries / max($uptimeSeconds, 1), 2);

            // Saiz Pangkalan Data dalam MB
            $stmt = Database::query("SELECT SUM(data_length + index_length) / 1024 / 1024 AS db_size, COUNT(*) AS total_tables FROM information_schema.TABLES WHERE table_schema = DATABASE()");
            $dbRow = $stmt->fetch();
            $dbSizeMb = $dbRow && $dbRow['db_size'] !== null ? round((float)$dbRow['db_size'], 2) : 0.0;
            $totalTables = $dbRow ? (int)$dbRow['total_tables'] : 0;

            // Format Uptime
            $days = floor($uptimeSeconds / 86400);
            $hours = floor(($uptimeSeconds % 86400) / 3600);
            $mins = floor(($uptimeSeconds % 3600) / 60);
            $uptimeFormatted = ($days > 0 ? "{$days}d " : "") . "{$hours}h {$mins}m";

            return [
                'uptime'            => $uptimeFormatted,
                'total_queries'     => number_format($totalQueries),
                'slow_queries'      => number_format($slowQueries),
                'threads_connected' => $threadsConnected,
                'qps'               => $qps,
                'version'           => $version,
                'db_size_mb'        => $dbSizeMb . ' MB',
                'total_tables'      => $totalTables,
                'status'            => $qps > 100 ? 'high_load' : 'optimal'
            ];
        } catch (Throwable $e) {
            error_log('[SystemHealth] MySQL metrics unavailable: ' . $e->getMessage());
            return [
                'uptime' => 'Unavailable', 'total_queries' => 'Unavailable',
                'slow_queries' => 'Unavailable', 'threads_connected' => 'Unavailable',
                'qps' => 'N/A', 'version' => 'Unavailable', 'db_size_mb' => 'Unavailable',
                'total_tables' => 'Unavailable', 'status' => 'unavailable',
            ];
        }
    }

    // Dapatkan beban pelayan dan penggunaan memori (Server Load & Memory)
    public static function getServerLoad() {
        $memUsage = memory_get_usage(true);
        $memPeak  = memory_get_peak_usage(true);
        $memLimit = ini_get('memory_limit');
        $maxExec  = ini_get('max_execution_time') . 's';

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $loadOneMinute = is_array($load) ? round((float)$load[0], 2) : null;

        return [
            'php_version' => PHP_VERSION,
            'os_info'     => PHP_OS_FAMILY . ' (' . php_uname('r') . ')',
            'web_server'  => $_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI,
            'memory_used' => self::formatBytes($memUsage),
            'memory_peak' => self::formatBytes($memPeak),
            'memory_limit'=> $memLimit,
            'max_exec'    => $maxExec,
            'server_time' => date('Y-m-d H:i:s P'),
            'load_1m'     => $loadOneMinute,
            'load_status' => $loadOneMinute === null ? 'Unavailable' : ($loadOneMinute >= 4 ? 'High' : 'Normal')
        ];
    }

    // Jalankan ujian kelajuan pertanyaan (Live Benchmark)
    public static function benchmarkDb() {
        $iterations = 10;
        $startTime = microtime(true);
        try {
            // Warm the connection before measuring consistent query latency.
            Database::query("SELECT 1 AS test_col");
            $startTime = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                Database::query("SELECT 1 AS test_col");
            }
        } catch (Exception $e) {
            error_log('[SystemHealth] Database benchmark failed: ' . $e->getMessage());
            return [
                'speed_ms' => null,
                'status'   => 'ERROR',
                'rating'   => 'Database benchmark failed.',
            ];
        }
        $endTime = microtime(true);
        $averageMs = round((($endTime - $startTime) * 1000) / $iterations, 2);

        return [
            'speed_ms' => $averageMs,
            'status'   => $averageMs < 10 ? 'EXCELLENT' : ($averageMs < 50 ? 'GOOD' : 'SLOW'),
            'rating'   => $averageMs < 10 ? '⚡ Ultra Fast (< 10ms)' : ($averageMs < 50 ? '✔ Normal (< 50ms)' : '⚠ Needs Optimization')
        ];
    }

    // Senarai salinan backup yang tersimpan
    public static function getBackupsList() {
        $backupDir = __DIR__ . '/../storage/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0750, true);
        }

        $files = [];
        $scanned = scandir($backupDir);
        foreach ($scanned as $f) {
            if ($f === '.' || $f === '..') continue;
            $fullPath = $backupDir . '/' . $f;
            if (is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === 'zip') {
                $files[] = [
                    'filename'   => $f,
                    'size'       => self::formatBytes(filesize($fullPath)),
                    'size_raw'   => filesize($fullPath),
                    'created_at' => date('d M Y, h:i A', filemtime($fullPath)),
                    'timestamp'  => filemtime($fullPath),
                    'url'        => 'index.php?action=download_backup&file=' . urlencode($f)
                ];
            }
        }

        // Susun dari yang terkini
        usort($files, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $files;
    }

    // Jalankan pemampatan (.zip) pangkalan data & fail muat naik
    public static function runAutoBackup() {
        if (!class_exists('ZipArchive')) {
            return ['status' => 'error', 'message' => 'PHP ZipArchive extension is not enabled on this server.'];
        }

        $backupDir = __DIR__ . '/../storage/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0750, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $zipFilename = "TBBA_System_Backup_{$timestamp}.zip";
        $zipPath = $backupDir . '/' . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['status' => 'error', 'message' => 'Failed to create backup ZIP file in storage directory.'];
        }

        // 1. Stream SQL dump to disk so large databases do not exhaust PHP memory.
        $sqlTempPath = null;
        try {
            $sqlTempPath = tempnam($backupDir, '.tbba_sql_');
            if ($sqlTempPath === false) throw new RuntimeException('Unable to allocate a temporary dump file.');
            $dumpHandle = fopen($sqlTempPath, 'wb');
            if ($dumpHandle === false) throw new RuntimeException('Unable to open the temporary dump file.');
            $write = static function (string $content) use ($dumpHandle): void {
                if (fwrite($dumpHandle, $content) === false) throw new RuntimeException('Unable to write the database dump.');
            };
            $write("-- THE BRIDGE BUSINESS ALLIANCE (TBBA)\n");
            $write("-- System Health & Auto-Backup SQL Dump\n");
            $write("-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
            $write("SET FOREIGN_KEY_CHECKS=0;\n\n");

            $stmt = Database::query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $pdo = Database::connect();

            foreach ($tables as $table) {
                $quotedTable = '`' . str_replace('`', '``', (string)$table) . '`';
                // Table structure
                $createStmt = Database::query("SHOW CREATE TABLE {$quotedTable}");
                $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
                $createSql = $createRow['Create Table'] ?? $createRow['Create View'] ?? (array_values($createRow ?: [])[1] ?? '');
                if ($createSql === '') throw new RuntimeException("Unable to read schema for {$table}.");
                $write("DROP TABLE IF EXISTS {$quotedTable};\n");
                $write($createSql . ";\n\n");

                // Table data
                $rowsStmt = Database::query("SELECT * FROM {$quotedTable}");
                while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
                    $escapedValues = array_map(static function ($value) use ($pdo) {
                        return $value === null ? 'NULL' : $pdo->quote((string)$value);
                    }, array_values($row));
                    $write("INSERT INTO {$quotedTable} VALUES (" . implode(', ', $escapedValues) . ");\n");
                }
                $write("\n");
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n");
            fflush($dumpHandle);
            fclose($dumpHandle);

            $sqlEntry = "database/TBBA_Database_Dump_{$timestamp}.sql";
            if (!$zip->addFile($sqlTempPath, $sqlEntry)) throw new RuntimeException('Unable to add the SQL dump to the archive.');
            $manifest = [
                'format_version' => 3,
                'created_at' => date(DATE_ATOM),
                'database' => DB_NAME,
                'table_count' => count($tables),
                'sql_entry' => $sqlEntry,
                'sql_sha256' => hash_file('sha256', $sqlTempPath),
                'restore_test' => 'Validate SQL checksum and required SQL markers before any recovery operation.',
            ];
            $zip->addFromString('BACKUP_MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        } catch (Throwable $e) {
            if (isset($dumpHandle) && is_resource($dumpHandle)) fclose($dumpHandle);
            $zip->close();
            if ($sqlTempPath && is_file($sqlTempPath)) @unlink($sqlTempPath);
            if (is_file($zipPath)) @unlink($zipPath);
            error_log('[SystemHealth] Backup failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Database backup failed. No incomplete archive was kept.'];
        }

        // 2. Mampatkan direktori fail muat naik (uploads folder)
        $uploadsDir = __DIR__ . '/../uploads';
        if (is_dir($uploadsDir)) {
            self::addFolderToZip($uploadsDir, $zip, 'uploads');
        }

        // Sensitive attachments live outside the public upload tree and must
        // remain part of full-system recovery archives.
        $privateStorageDir = __DIR__ . '/../storage/private';
        if (is_dir($privateStorageDir)) {
            self::addFolderToZip($privateStorageDir, $zip, 'storage/private');
        }

        // 3. Masukkan ringkasan kesihatan sistem sebagai fail info .txt
        $infoTxt = "TBBA CORPORATE SYSTEM BACKUP METADATA\n";
        $infoTxt .= "=======================================\n";
        $infoTxt .= "Backup Date: " . date('Y-m-d H:i:s P') . "\n";
        $infoTxt .= "PHP Version: " . PHP_VERSION . "\n";
        $infoTxt .= "MySQL Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Laragon') . "\n";
        $infoTxt .= "Generated By: Admin Dashboard Auto-Backup Script\n";
        $zip->addFromString("BACKUP_METADATA.txt", $infoTxt);

        $closed = $zip->close();
        if ($sqlTempPath && is_file($sqlTempPath)) @unlink($sqlTempPath);
        if (!$closed) {
            if (is_file($zipPath)) @unlink($zipPath);
            return ['status' => 'error', 'message' => 'Backup ZIP could not be finalized.'];
        }

        if (file_exists($zipPath)) {
            @chmod($zipPath, 0600);
            self::pruneBackups((int)self::securitySetting('backup_retention_days', '30'));
            $fileSize = self::formatBytes(filesize($zipPath));
            return [
                'status'   => 'success',
                'message'  => "Full system backup successfully compressed into .zip archive ({$fileSize})!",
                'filename' => $zipFilename,
                'size'     => $fileSize,
                'date'     => date('d M Y, h:i A'),
                'url'      => 'index.php?action=download_backup&file=' . urlencode($zipFilename)
            ];
        } else {
            return ['status' => 'error', 'message' => 'Backup ZIP file was not generated properly.'];
        }
    }

    // Padam fail backup
    public static function deleteBackup($filename) {
        $safeName = basename($filename);
        if (!preg_match('/^TBBA_System_Backup_[0-9_-]+\.zip$/', $safeName)) return false;
        $filePath = __DIR__ . '/../storage/backups/' . $safeName;
        
        if (file_exists($filePath) && is_file($filePath)) {
            @unlink($filePath);
            return true;
        }
        return false;
    }

    /** Non-destructive restore readiness test for a stored archive. */
    public static function testBackup(string $filename): array {
        if (!class_exists('ZipArchive')) return ['status'=>'error','message'=>'ZipArchive is not available.'];
        $safeName = basename($filename);
        if (!preg_match('/^TBBA_System_Backup_[0-9_-]+\.zip$/', $safeName)) return ['status'=>'error','message'=>'Invalid backup filename.'];
        $path = __DIR__ . '/../storage/backups/' . $safeName;
        if (!is_file($path)) return ['status'=>'error','message'=>'Backup archive not found.'];
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return ['status'=>'error','message'=>'The backup archive cannot be opened.'];
        $manifestRaw = $zip->getFromName('BACKUP_MANIFEST.json');
        if ($manifestRaw === false) { $zip->close(); return ['status'=>'error','message'=>'Backup manifest is missing. Generate a new version 2 backup.']; }
        $manifest = json_decode($manifestRaw, true);
        $sqlEntry = $manifest['sql_entry'] ?? '';
        $stream = $sqlEntry ? $zip->getStream($sqlEntry) : false;
        if ($stream === false) { $zip->close(); return ['status'=>'error','message'=>'Database dump is missing from the archive.']; }
        $hashContext = hash_init('sha256');
        $markers = '';
        $tail = '';
        $bytesRead = 0;
        while (!feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) { fclose($stream); $zip->close(); return ['status'=>'error','message'=>'Database dump could not be read.']; }
            $bytesRead += strlen($chunk);
            hash_update($hashContext, $chunk);
            if (strlen($markers) < 4 * 1024 * 1024) $markers .= $chunk;
            $tail = substr($tail . $chunk, -1024);
        }
        fclose($stream);
        $zip->close();
        if ($bytesRead === 0) return ['status'=>'error','message'=>'Database dump is empty.'];
        if (!hash_equals((string)($manifest['sql_sha256'] ?? ''), hash_final($hashContext))) return ['status'=>'error','message'=>'Database dump checksum mismatch. The backup may be corrupted.'];
        if (!str_contains($markers, 'SET FOREIGN_KEY_CHECKS=0;') || !str_contains($markers, 'CREATE TABLE')
            || !str_contains($tail, 'SET FOREIGN_KEY_CHECKS=1;')) {
            return ['status'=>'error','message'=>'Database dump failed structural restore checks.'];
        }
        return ['status'=>'success','message'=>'Restore readiness test passed. Archive, manifest and database checksum are valid.','data'=>['table_count'=>(int)($manifest['table_count']??0),'created_at'=>$manifest['created_at']??'']];
    }

    public static function pruneBackups(int $retentionDays): int {
        $retentionDays = max(1, $retentionDays);
        $cutoff = time() - ($retentionDays * 86400);
        $removed = 0;
        foreach (glob(__DIR__ . '/../storage/backups/TBBA_System_Backup_*.zip') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff && @unlink($file)) $removed++;
        }
        return $removed;
    }

    private static function securitySetting(string $key, string $default): string {
        try {
            $value = Database::query("SELECT setting_value FROM security_settings WHERE setting_key=?", [$key])->fetchColumn();
            return $value !== false ? (string)$value : $default;
        } catch (Throwable $e) { return $default; }
    }

    // Helper: Tambah direktori ke dalam ZIP
    private static function addFolderToZip($dir, ZipArchive $zip, $zipPath = '') {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(realpath($dir)) + 1);
                $zip->addFile($filePath, $zipPath . '/' . str_replace('\\', '/', $relativePath));
            }
        }
    }

    // Helper: Kira saiz direktori
    private static function getDirSize($dir) {
        if (!is_dir($dir)) return 0;
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    // Helper: Format byte ke KB/MB/GB
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
?>
