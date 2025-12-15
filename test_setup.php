<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = db();

    // Check columns
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $hasEmailVerified = in_array('email_verified', $columns);

    $password = password_hash('Password123!', PASSWORD_DEFAULT);

    $users = [
        ['name' => 'Test User', 'email' => 'user@test.com', 'role' => 'user'],
        ['name' => 'Test Admin', 'email' => 'admin@test.com', 'role' => 'admin'],
        ['name' => 'Test SuperAdmin', 'email' => 'superadmin@test.com', 'role' => 'superadmin']
    ];

    foreach ($users as $u) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$u['email']]);

        if ($stmt->fetch()) {
            $sql = "UPDATE users SET password = ?, role = ?, name = ?";
            $params = [$password, $u['role'], $u['name']];

            if ($hasEmailVerified) {
                $sql .= ", email_verified = 1";
            }

            $sql .= " WHERE email = ?";
            $params[] = $u['email'];

            $pdo->prepare($sql)->execute($params);
            echo "Updated {$u['email']}\n";
        } else {
            $sql = "INSERT INTO users (name, email, password, role";
            $values = "VALUES (?, ?, ?, ?";
            $params = [$u['name'], $u['email'], $password, $u['role']];

            if ($hasEmailVerified) {
                $sql .= ", email_verified";
                $values .= ", 1";
            }

            $sql .= ") " . $values . ")";

            $pdo->prepare($sql)->execute($params);
            echo "Created {$u['email']}\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
