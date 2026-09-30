<?php
require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not Found');
}

try {
    Database::query("SET FOREIGN_KEY_CHECKS = 0;");
    Database::query("TRUNCATE TABLE `positions`");
    // We will keep existing departments but insert some basic ones to be sure
    // Or just link to existing departments.
    // Let's create specific departments just for the test, or fetch existing.
    
    // Seed Departments
    Database::query("INSERT IGNORE INTO `departments` (id, name, code, status) VALUES 
        (101, 'Executive', 'EXEC', 'active'),
        (102, 'Information Technology', 'IT', 'active'),
        (103, 'Sales & Marketing', 'SALES', 'active'),
        (104, 'Human Resources', 'HR', 'active')
    ");

    // Seed Positions
    // 1. CEO (Top level)
    Database::query("INSERT INTO `positions` (id, title, department_id, reports_to_position_id, level, status) VALUES (1, 'Chief Executive Officer', 101, NULL, 1, 'active')");
    
    // 2. Heads
    Database::query("INSERT INTO `positions` (id, title, department_id, reports_to_position_id, level, status) VALUES (2, 'Head of IT', 102, 1, 2, 'active')");
    Database::query("INSERT INTO `positions` (id, title, department_id, reports_to_position_id, level, status) VALUES (3, 'Head of Sales', 103, 1, 2, 'active')");
    Database::query("INSERT INTO `positions` (id, title, department_id, reports_to_position_id, level, status) VALUES (4, 'Head of HR', 104, 1, 2, 'active')");
    
    // 3. Executives
    Database::query("INSERT INTO `positions` (id, title, department_id, reports_to_position_id, level, status) VALUES (5, 'Senior IT Executive', 102, 2, 3, 'active')");
    Database::query("INSERT INTO `positions` (id, title, department_id, reports_to_position_id, level, status) VALUES (6, 'IT Support Specialist', 102, 5, 4, 'active')");
    
    Database::query("INSERT INTO `positions` (id, title, department_id, reports_to_position_id, level, status) VALUES (7, 'Sales Executive', 103, 3, 3, 'active')");
    Database::query("INSERT INTO `positions` (id, title, department_id, reports_to_position_id, level, status) VALUES (8, 'Marketing Specialist', 103, 3, 3, 'active')");
    
    Database::query("INSERT INTO `positions` (id, title, department_id, reports_to_position_id, level, status) VALUES (9, 'HR Executive', 104, 4, 3, 'active')");
    
    // 4. Vacant position
    Database::query("INSERT INTO `positions` (id, title, department_id, reports_to_position_id, level, status) VALUES (10, 'Sales Manager (Vacant)', 103, 1, 2, 'active')");
    
    // Update Users to map to these positions
    // Clear old staff to insert exactly 16 staff cleanly
    Database::query("DELETE FROM `users` WHERE `email` LIKE 'test_%@example.com'");
    
    $staff = [
        ['Test CEO', 'test_ceo@example.com', 'super_admin', 1, 101],
        
        ['Test Head IT', 'test_head_it@example.com', 'manager', 2, 102],
        ['Test Head Sales', 'test_head_sales@example.com', 'manager', 3, 103],
        ['Test Head HR', 'test_head_hr@example.com', 'manager', 4, 104],
        
        ['Test Senior IT 1', 'test_sit1@example.com', 'staff', 5, 102],
        ['Test Senior IT 2', 'test_sit2@example.com', 'staff', 5, 102],
        ['Test IT Support 1', 'test_its1@example.com', 'staff', 6, 102],
        ['Test IT Support 2', 'test_its2@example.com', 'staff', 6, 102],
        ['Test IT Support 3', 'test_its3@example.com', 'staff', 6, 102],
        
        ['Test Sales 1', 'test_sales1@example.com', 'staff', 7, 103],
        ['Test Sales 2', 'test_sales2@example.com', 'staff', 7, 103],
        ['Test Sales 3', 'test_sales3@example.com', 'staff', 7, 103],
        ['Test Sales 4', 'test_sales4@example.com', 'staff', 7, 103],
        
        ['Test Marketing 1', 'test_mkt1@example.com', 'staff', 8, 103],
        ['Test Marketing 2', 'test_mkt2@example.com', 'staff', 8, 103],
        
        ['Test HR 1', 'test_hr1@example.com', 'hr', 9, 104]
    ];

    $seededCredentials = [];
    foreach ($staff as $s) {
        // Find position name
        $pos = Database::query("SELECT title FROM positions WHERE id = ?", [$s[3]])->fetchColumn();
        $plainPassword = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=') . '!Aa1';
        Database::query("INSERT INTO `users` (name, email, password, role, position, department_id, position_id, status, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 'default.png')",
        [$s[0], $s[1], password_hash($plainPassword, PASSWORD_DEFAULT), $s[2], $pos, $s[4], $s[3]]);
        $seededCredentials[] = [$s[1], $plainPassword];
    }
    
    Database::query("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "Seed completed successfully. 16 users and positions inserted.\n";
    echo "One-time test credentials (save now; they are not stored in source):\n";
    foreach ($seededCredentials as [$email, $password]) {
        echo $email . ' | ' . $password . "\n";
    }

} catch (Exception $e) {
    echo "Error seeding: " . $e->getMessage() . "\n";
}
