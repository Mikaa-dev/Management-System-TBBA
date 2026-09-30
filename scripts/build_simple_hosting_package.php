<?php
/** Build the two-file hosting handoff: one project ZIP and one database SQL. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not Found');
}
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP zip extension is required.\n");
    exit(1);
}

$root = realpath(dirname(__DIR__));
if ($root === false) throw new RuntimeException('Application root is unavailable.');
$dist = $root . '/dist';
if (!is_dir($dist) && !mkdir($dist, 0750, true) && !is_dir($dist)) {
    throw new RuntimeException('Unable to create dist directory.');
}

$backups = glob($root . '/storage/backups/TBBA_System_Backup_*.zip') ?: [];
usort($backups, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
$latestBackup = $backups[0] ?? '';
if ($latestBackup === '') throw new RuntimeException('No full-system backup is available.');

$backup = new ZipArchive();
if ($backup->open($latestBackup) !== true) throw new RuntimeException('Unable to open the latest backup.');
$manifestRaw = $backup->getFromName('BACKUP_MANIFEST.json');
$manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
$sqlEntry = is_array($manifest) ? (string)($manifest['sql_entry'] ?? '') : '';
if ($sqlEntry === '') {
    for ($index = 0; $index < $backup->numFiles; $index++) {
        $candidate = (string)$backup->getNameIndex($index);
        if (preg_match('#^database/.*\.sql$#i', $candidate)) {
            $sqlEntry = $candidate;
            break;
        }
    }
}
$databaseSql = $sqlEntry !== '' ? $backup->getFromName($sqlEntry) : false;
$backup->close();
if (!is_string($databaseSql) || $databaseSql === '') {
    throw new RuntimeException('The latest backup does not contain a database dump.');
}

// DirectAdmin shared hosting commonly runs MariaDB, which does not support
// MySQL 8's utf8mb4_0900_ai_ci collation. Normalise it so phpMyAdmin does not
// stop halfway through the import (the users table is near the end of the dump).
$databaseSql = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $databaseSql);

$postImport = file_get_contents($root . '/database/production_post_import.sql');
if (!is_string($postImport) || $postImport === '') throw new RuntimeException('Post-import SQL is missing.');
$databaseOutput = $dist . '/2_IMPORT_DATABASE.sql';
$combinedSql = $databaseSql
    . "\n\n-- ============================================================\n"
    . "-- AUTOMATIC PRODUCTION POST-IMPORT SETTINGS\n"
    . "-- ============================================================\n\n"
    . $postImport;
if (file_put_contents($databaseOutput, $combinedSql, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write the combined database SQL.');
}
@chmod($databaseOutput, 0600);

$projectOutput = $dist . '/1_UPLOAD_PROJECT.zip';
if (is_file($projectOutput) && !unlink($projectOutput)) {
    throw new RuntimeException('Unable to replace the previous project ZIP.');
}
$project = new ZipArchive();
if ($project->open($projectOutput, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Unable to create the project ZIP.');
}

$excludedPrefixes = [
    '.git/', '.agents/', '.codex/', 'dist/',
    'storage/backups/', 'storage/logs/', 'storage/quarantine/',
];
$excludedExact = [
    '.env', '.DS_Store', 'router.php',
    'config/google-credentials.json', 'config/service-account.json',
    'scripts/responsive_audit.mjs',
];
$excluded = static function (string $relative) use ($excludedPrefixes, $excludedExact): bool {
    $relative = str_replace('\\', '/', $relative);
    if (in_array($relative, $excludedExact, true) || str_ends_with($relative, '/.DS_Store')) return true;
    foreach ($excludedPrefixes as $prefix) {
        if (str_starts_with($relative, $prefix)) return true;
    }
    return false;
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
$filesAdded = 0;
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $absolute = $file->getPathname();
    $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
    if ($excluded($relative)) continue;
    if (!$project->addFile($absolute, $relative)) throw new RuntimeException("Unable to add {$relative}.");
    $filesAdded++;
}

// Include a safe .env placeholder so the hosting workflow is simply:
// extract, edit .env, then import the single SQL file.
$productionEnv = file_get_contents($root . '/.env.production.example');
if (!is_string($productionEnv) || $productionEnv === '') throw new RuntimeException('Production environment template is missing.');
$project->addFromString('.env', $productionEnv);

foreach (['storage/backups', 'storage/logs', 'storage/logs/ddos_ratelimit',
          'storage/logs/login_throttle', 'storage/logs/inquiry_throttle',
          'storage/quarantine'] as $directory) {
    $project->addEmptyDir($directory);
}
foreach (['storage/backups/.htaccess', 'storage/logs/.htaccess'] as $relative) {
    if (is_file($root . '/' . $relative)) $project->addFile($root . '/' . $relative, $relative);
}

if (!$project->close()) throw new RuntimeException('Unable to finalize the project ZIP.');
@chmod($projectOutput, 0640);

$verify = new ZipArchive();
if ($verify->open($projectOutput) !== true) throw new RuntimeException('Unable to verify the project ZIP.');
$forbidden = [];
for ($index = 0; $index < $verify->numFiles; $index++) {
    $name = (string)$verify->getNameIndex($index);
    if (in_array($name, ['config/google-credentials.json', 'config/service-account.json'], true)
        || str_starts_with($name, 'storage/backups/') && basename($name) !== '.htaccess' && !str_ends_with($name, '/')
        || str_starts_with($name, 'storage/logs/') && basename($name) !== '.htaccess' && !str_ends_with($name, '/')) {
        $forbidden[] = $name;
    }
}
$envInArchive = $verify->getFromName('.env');
$verify->close();
if ($forbidden || !is_string($envInArchive) || str_contains($envInArchive, '-----BEGIN PRIVATE KEY-----')) {
    @unlink($projectOutput);
    throw new RuntimeException('Project ZIP verification failed.');
}

echo json_encode([
    'status' => 'success',
    'project_zip' => $projectOutput,
    'database_sql' => $databaseOutput,
    'source_backup' => basename($latestBackup),
    'project_files' => $filesAdded,
    'project_bytes' => filesize($projectOutput),
    'database_bytes' => filesize($databaseOutput),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
