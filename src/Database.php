<?php
/**
 * PDO connection factory for StudentOrbit.
 */
class Database
{
    private static ?PDO $pdo = null;

    /** Connect to the application database. */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg = require __DIR__ . '/../config/config.php';
        $db  = $cfg['db'];
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
        self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return self::$pdo;
    }

    /** Connect to the MySQL server WITHOUT selecting a database (used by the installer). */
    public static function serverPdo(): PDO
    {
        $cfg = require __DIR__ . '/../config/config.php';
        $db  = $cfg['db'];
        $dsn = "mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}";
        return new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
}
