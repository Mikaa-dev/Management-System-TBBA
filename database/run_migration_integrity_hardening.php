<?php
/** One-time integrity indexes required by the hardened workflow code. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not Found'); }

require_once __DIR__ . '/../config/database.php';

$pdo = Database::connect();

function bridgeIndexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?'
    );
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function bridgeAssertNoDuplicates(string $column, ?string $where = null): void
{
    $sql = "SELECT {$column},COUNT(*) total FROM finance_records" . ($where ? " WHERE {$where}" : '')
        . " GROUP BY {$column} HAVING COUNT(*)>1 LIMIT 1";
    if (Database::query($sql)->fetch()) {
        throw new RuntimeException("Duplicate finance_records.{$column} values must be resolved before adding the unique index.");
    }
}

bridgeAssertNoDuplicates('reference_no');
bridgeAssertNoDuplicates('source_request_id', 'source_request_id IS NOT NULL');

if (!bridgeIndexExists($pdo, 'finance_records', 'uq_finance_reference')) {
    $pdo->exec('ALTER TABLE finance_records ADD UNIQUE KEY uq_finance_reference (reference_no)');
    echo "Added uq_finance_reference\n";
}
if (!bridgeIndexExists($pdo, 'finance_records', 'uq_finance_source_request')) {
    $pdo->exec('ALTER TABLE finance_records ADD UNIQUE KEY uq_finance_source_request (source_request_id)');
    echo "Added uq_finance_source_request\n";
}

echo "Integrity hardening migration complete.\n";
