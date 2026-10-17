<?php
/**
 * StudentOrbit CEO Dashboard — configuration.
 *
 * Values fall back to typical local-MySQL defaults but can be overridden with
 * environment variables (handy for different machines without editing this file):
 *   SO_DB_HOST, SO_DB_PORT, SO_DB_NAME, SO_DB_USER, SO_DB_PASS
 */

return [
    'db' => [
        'host' => getenv('SO_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('SO_DB_PORT') ?: '3306',
        'name' => getenv('SO_DB_NAME') ?: 'studentorbit',
        'user' => getenv('SO_DB_USER') ?: 'root',
        // Secret: set SO_DB_PASS (see .env.local, loaded by start.sh). No real
        // password is committed — the fallback is intentionally empty.
        'pass' => getenv('SO_DB_PASS') !== false ? getenv('SO_DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],

    // Top-level monthly goals (stored in DB settings too; these are the install defaults).
    'revenue_target_month'  => 20000000, // ₹2,00,00,000 = ₹2 Cr
    'students_target_month' => 150000,   // 1.5 lakh

    // Default CEO login. The installer hashes the password with password_hash()
    // and stores only the hash in the `users` table (plaintext is never persisted).
    // Change the password here (or via SO_ADMIN_* env vars) and re-run install.php,
    // or let the CEO change it later from the app.
    'admin' => [
        'email'    => getenv('SO_ADMIN_EMAIL') ?: 'uttammande@gmail.com',
        'name'     => getenv('SO_ADMIN_NAME')  ?: 'Uttam Mande',
        // Secret: set SO_ADMIN_PASS (see .env.local). Empty fallback is committed
        // on purpose; the installer refuses to create an admin with a blank password.
        'password' => getenv('SO_ADMIN_PASS')  ?: '',
    ],

    // SMTP for password-reset OTP emails.
    // IMPORTANT: for Gmail use an *App Password* (needs 2-Step Verification),
    // never your Google account password. Keep the secret OUT of this file —
    // set it as the SO_SMTP_PASS environment variable before starting the
    // server, e.g.:  SO_SMTP_USER=you@gmail.com SO_SMTP_PASS='abcd efgh ijkl mnop' php -S localhost:8000 -t public
    'smtp' => [
        'host'      => getenv('SO_SMTP_HOST') ?: 'smtp.gmail.com',
        'port'      => (int) (getenv('SO_SMTP_PORT') ?: 587),
        'secure'    => getenv('SO_SMTP_SECURE') ?: 'tls', // 'tls' (STARTTLS, port 587) or 'ssl' (port 465)
        'user'      => getenv('SO_SMTP_USER') ?: '',
        'pass'      => getenv('SO_SMTP_PASS') ?: '',
        'from'      => getenv('SO_SMTP_FROM') ?: (getenv('SO_ADMIN_EMAIL') ?: 'uttammande@gmail.com'),
        'from_name' => getenv('SO_SMTP_FROM_NAME') ?: 'StudentOrbit',
    ],
];
