<?php
/**
 * Cron-safe scheduled backup entry point.
 * Example: 0 2 * * * /usr/bin/php /path/to/Bridge/scripts/run_scheduled_backup.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/SystemHealth.php';
$result = SystemHealth::runAutoBackup();
echo '[' . date(DATE_ATOM) . '] ' . json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(($result['status'] ?? '') === 'success' ? 0 : 1);
