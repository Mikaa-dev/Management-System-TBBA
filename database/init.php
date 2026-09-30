<?php
/**
 * Skrip Pemasangan & Seeder Automatik Pangkalan Data
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Helper.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not Found');
}

umask(0027);
$seededCredentials = [];

header('Content-Type: text/html; charset=utf-8');
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>";
echo "<h2 style='color: #0f172a; border-bottom: 2px solid #2563eb; padding-bottom: 10px;'>TBBA Mini ERP System Installation</h2>";

try {
    // 1. Bina Database jika belum wujud
    $dsnNoDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
    $pdoNoDb = new PDO($dsnNoDb, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdoNoDb->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "<p style='color: #059669;'>✔ Database <strong>" . DB_NAME . "</strong> created / verified successfully.</p>";

    // 2. Sambung ke database & laksanakan skema SQL
    $pdo = Database::getInstance();
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // Pecahkan arahan SQL dengan delimiter ';'
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        $trimmed = trim($query);
        if (!empty($trimmed)) {
            $pdo->exec($trimmed);
        }
    }
    echo "<p style='color: #059669;'>✔ Table schema (Users, Attendance, Tenders, Letters) created successfully.</p>";

    // 3. Wujudkan direktori muat naik fail
    $uploadDirs = [
        __DIR__ . '/../storage/private/tenders',
        __DIR__ . '/../storage/private/letters',
        __DIR__ . '/../assets/images'
    ];
    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
    }
    echo "<p style='color: #059669;'>✔ Upload directories (uploads/tenders & uploads/letters) are ready.</p>";

    // 4. Seeder Data Pengguna (Users)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $newBootstrapPassword = static function (): string {
            return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=') . '!Aa1';
        };
        $users = [
            [
                'name' => 'Tengku Ahmad (Assistant CEO)',
                'email' => 'ceo@tbba.com',
                'password' => $newBootstrapPassword(),
                'role' => 'admin',
                'position' => 'Assistant CEO'
            ],
            [
                'name' => 'Sara Daniel (Executive)',
                'email' => 'staff1@tbba.com',
                'password' => $newBootstrapPassword(),
                'role' => 'staff',
                'position' => 'Senior Tender Executive'
            ],
            [
                'name' => 'Farish Hakim (Officer)',
                'email' => 'staff2@tbba.com',
                'password' => $newBootstrapPassword(),
                'role' => 'staff',
                'position' => 'Project Liaison Officer'
            ]
        ];

        $insUser = $pdo->prepare("INSERT INTO users (name, email, password, role, position) VALUES (?, ?, ?, ?, ?)");
        foreach ($users as $u) {
            $insUser->execute([$u['name'], $u['email'], password_hash($u['password'], PASSWORD_DEFAULT), $u['role'], $u['position']]);
            $seededCredentials[] = ['email' => $u['email'], 'password' => $u['password']];
        }
        echo "<p style='color: #059669;'>✔ User accounts (Admin & Staff) inserted successfully.</p>";

        // 5. Seeder Data Kehadiran Hari Ini
        $today = date('Y-m-d');
        $pdo->prepare("INSERT INTO attendance (user_id, date, clock_in, clock_in_lat, clock_in_lng, status) VALUES (2, ?, ?, 3.139003, 101.686855, 'present')")
            ->execute([$today, $today . ' 08:45:00']);
        echo "<p style='color: #059669;'>✔ Sample attendance records generated successfully.</p>";

        // 6. Seeder Data Tender (KPI Tracker)
        $curMonth = date('Y-m');
        $tenders = [
            [2, 'Corporate Network System Supply', 'Ministry of Digital Malaysia', 450000.00, date('Y-m-d', strtotime('+15 days')), 'submitted', $curMonth],
            [2, 'Primary Data Centre Maintenance', 'Petronas Dagangan Berhad', 1200000.00, date('Y-m-d', strtotime('+20 days')), 'submitted', $curMonth],
            [2, 'Cloud Infrastructure Upgrade', 'Bank Negara Malaysia', 850000.00, date('Y-m-d', strtotime('+25 days')), 'submitted', $curMonth],
            [3, 'Smart City Application Development', 'DBKL', 620000.00, date('Y-m-d', strtotime('+10 days')), 'won', $curMonth]
        ];
        $insTender = $pdo->prepare("INSERT INTO tenders (user_id, project_name, client_name, project_value, closing_date, status, month_year) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($tenders as $t) {
            $insTender->execute($t);
        }
        echo "<p style='color: #059669;'>✔ Sample Tender KPI records generated successfully.</p>";

        // 7. Seeder Data Surat Masuk & Keluar
        $letters = [
            [2, 'IN', Helper::generateLetterRef('IN', 1), 'Invitation to Participate in an Official Quotation', 'Ministry of Economy', date('Y-m-d', strtotime('-2 days')), 'pending', 'Please review the attached technical specifications.'],
            [2, 'IN', Helper::generateLetterRef('IN', 2), 'Confirmation of Proposal Receipt', 'Khazanah Nasional', date('Y-m-d', strtotime('-1 days')), 'in_progress', 'Prepare the project presentation for next week.'],
            [3, 'OUT', Helper::generateLetterRef('OUT', 1), '2026 Tender Prospectus Submission Letter', 'MDEC Berhad', date('Y-m-d'), 'completed', 'Sent by dedicated courier.']
        ];
        $insLetter = $pdo->prepare("INSERT INTO letters (user_id, type, ref_no, title, sender_receiver, letter_date, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($letters as $l) {
            $insLetter->execute($l);
        }
        echo "<p style='color: #059669;'>✔ Incoming and outgoing letter records generated successfully.</p>";
    } else {
        echo "<p style='color: #d97706;'>⚠ The database already contains user records. Seeder skipped to prevent duplicates.</p>";
    }

    if ($seededCredentials) {
        echo "<div style='margin-top: 30px; padding: 15px; background: #fff7ed; border-left: 4px solid #ea580c; border-radius: 4px;'>";
        echo "<h4 style='margin: 0 0 10px 0; color: #9a3412;'>One-time bootstrap credentials</h4>";
        echo "<p>Save these generated passwords now. They are not stored in source code and will not be displayed again.</p><ul>";
        foreach ($seededCredentials as $credential) {
            echo '<li><code>' . htmlspecialchars($credential['email'], ENT_QUOTES, 'UTF-8') . '</code> | Password: <code>'
                . htmlspecialchars($credential['password'], ENT_QUOTES, 'UTF-8') . '</code></li>';
        }
        echo "</ul></div>";
    }

    echo "<div style='margin-top: 20px; text-align: center;'>";
    echo "<a href='../index.php' style='display: inline-block; padding: 12px 24px; background: #2563eb; color: #ffffff; text-decoration: none; font-weight: bold; border-radius: 6px; box-shadow: 0 2px 4px rgba(37,99,235,0.3);'>Open the Mini ERP System Now &rarr;</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<p style='color: #dc2626;'>✘ Installation Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>
