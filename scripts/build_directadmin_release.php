<?php
/** Build a DirectAdmin code-only ZIP without secrets or user/business data. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not Found');
}
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The PHP zip extension is required.\n");
    exit(1);
}

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Application root is unavailable.\n");
    exit(1);
}

$dist = $root . '/dist';
if (!is_dir($dist) && !mkdir($dist, 0750, true) && !is_dir($dist)) {
    fwrite(STDERR, "Unable to create dist/.\n");
    exit(1);
}

$output = $dist . '/tbba-directadmin-code-' . date('Ymd-His') . '.zip';
$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    fwrite(STDERR, "Unable to create release archive.\n");
    exit(1);
}

$excludedPrefixes = [
    '.git/', '.agents/', '.codex/', 'dist/',
    'storage/backups/', 'storage/logs/', 'storage/private/', 'storage/quarantine/',
];
$excludedExact = [
    '.env', '.DS_Store', 'router.php', 'PUSH_NOTIFICATION_SETUP.md',
    'config/google-credentials.json', 'config/service-account.json',
    'scripts/responsive_audit.mjs', 'scripts/repair_orphaned_artifacts.php',
];
$isExcluded = static function (string $relative) use ($excludedPrefixes, $excludedExact): bool {
    $relative = str_replace('\\', '/', $relative);
    if (in_array($relative, $excludedExact, true) || str_ends_with($relative, '/.DS_Store')) return true;
    foreach ($excludedPrefixes as $prefix) {
        if (str_starts_with($relative, $prefix)) return true;
    }
    if (str_starts_with($relative, 'uploads/') && basename($relative) !== '.htaccess') return true;
    return false;
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
$added = 0;
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $absolute = $file->getPathname();
    $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
    if ($isExcluded($relative)) continue;
    if (!$zip->addFile($absolute, $relative)) {
        $zip->close();
        @unlink($output);
        fwrite(STDERR, "Unable to add {$relative}.\n");
        exit(1);
    }
    $added++;
}

$runtimeDirectories = [
    'storage/backups', 'storage/logs', 'storage/logs/ddos_ratelimit',
    'storage/logs/login_throttle', 'storage/logs/inquiry_throttle',
    'storage/private', 'storage/private/documents', 'storage/private/expenses',
    'storage/private/finance', 'storage/private/leaves', 'storage/private/letters',
    'storage/private/signatures', 'storage/private/signed_pdfs',
    'storage/private/tender_pricing', 'storage/private/tenders', 'storage/quarantine',
    'uploads/avatars', 'uploads/documents', 'uploads/expenses', 'uploads/finance',
    'uploads/leaves', 'uploads/letters', 'uploads/signatures', 'uploads/signed_pdfs',
    'uploads/tenders',
];
foreach ($runtimeDirectories as $directory) $zip->addEmptyDir($directory);

// Keep deny rules in empty server-only directories.
foreach (['storage/backups/.htaccess', 'storage/logs/.htaccess', 'storage/private/.htaccess'] as $relative) {
    $absolute = $root . '/' . $relative;
    if (is_file($absolute)) $zip->addFile($absolute, $relative);
}

if (!$zip->close()) {
    @unlink($output);
    fwrite(STDERR, "Unable to finalize release archive.\n");
    exit(1);
}

$verification = new ZipArchive();
if ($verification->open($output) !== true) {
    fwrite(STDERR, "Unable to verify release archive.\n");
    exit(1);
}
$forbidden = [];
for ($index = 0; $index < $verification->numFiles; $index++) {
    $name = $verification->getNameIndex($index);
    $isSensitiveRuntimeFile = !str_ends_with($name, '/')
        && basename($name) !== '.htaccess'
        && (str_starts_with($name, 'storage/backups/')
            || str_starts_with($name, 'storage/private/')
            || str_starts_with($name, 'storage/logs/'));
    if ($name === '.env'
        || in_array($name, ['config/google-credentials.json', 'config/service-account.json'], true)
        || $isSensitiveRuntimeFile) {
        $forbidden[] = $name;
    }
}
$verification->close();
if ($forbidden) {
    @unlink($output);
    fwrite(STDERR, "Release verification failed: " . implode(', ', $forbidden) . "\n");
    exit(1);
}

@chmod($output, 0640);
echo json_encode([
    'status' => 'success',
    'archive' => $output,
    'files' => $added,
    'bytes' => filesize($output),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
