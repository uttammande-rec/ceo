<?php
/**
 * StudentOrbit — authentication helper (session-based).
 * Shared by index.php (page guard), api.php (JSON guard) and login.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('so_session');
    session_start();
}

/** The logged-in user row, or null. */
function current_user(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $stmt = Database::pdo()->prepare("SELECT id, email, name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['uid']]);
    $u = $stmt->fetch();
    return $u ?: null;
}

/** Verify credentials; on success set the session and return true. */
function attempt_login(string $email, string $password): bool
{
    $stmt = Database::pdo()->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->execute([trim($email)]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

/** Guard for HTML pages: redirect to the login screen if not signed in. */
function require_login_page(): void
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

/** Guard for the JSON API: 401 if not signed in. */
function require_login_api(): void
{
    if (!current_user()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
}
