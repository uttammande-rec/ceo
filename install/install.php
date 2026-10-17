<?php
/**
 * StudentOrbit installer — runs schema.sql then seed.sql.
 *
 * Usage:  php install/install.php
 * Idempotent: schema uses IF NOT EXISTS, seed truncates+inserts.
 */

require __DIR__ . '/../src/Database.php';

function runSqlFile(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read $file");
    }
    // PDO can execute multiple statements in one exec() for the mysqlnd driver.
    $pdo->exec($sql);
    echo "  ✔ ran " . basename($file) . "\n";
}

try {
    echo "StudentOrbit installer\n";

    // Connect without selecting a DB so schema.sql can CREATE DATABASE.
    $pdo = Database::serverPdo();
    echo "Connected to MySQL server.\n";

    runSqlFile($pdo, __DIR__ . '/schema.sql');
    runSqlFile($pdo, __DIR__ . '/seed.sql');

    // Create / update the default CEO login (password stored hashed, never plaintext).
    $cfg   = require __DIR__ . '/../config/config.php';
    $admin = $cfg['admin'];
    if (($admin['password'] ?? '') === '') {
        throw new RuntimeException(
            'Admin password is empty. Set SO_ADMIN_PASS (e.g. source .env.local) before running the installer.'
        );
    }
    $hash  = password_hash($admin['password'], PASSWORD_DEFAULT);
    $db    = Database::pdo();
    $stmt  = $db->prepare(
        "INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash)"
    );
    $stmt->execute([$admin['email'], $admin['name'], $hash]);
    echo "  ✔ admin user ready: {$admin['email']}\n";

    echo "\nDone. Start the portal with:\n";
    echo "  php -S localhost:8000 -t public\n";
    echo "Then open http://localhost:8000\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nInstall failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Check config/config.php DB credentials (or set SO_DB_* env vars).\n");
    exit(1);
}
