<?php
/**
 * StudentOrbit — password-reset (email OTP) logic.
 *
 * Flow:
 *   1. request($email)  -> if the user exists, create a hashed OTP row and email
 *                          the code. Returns silently either way (no account
 *                          enumeration). Throttled by RESEND_COOLDOWN.
 *   2. verify($email,$otp,$newPassword) -> validate the newest unused, unexpired
 *                          OTP (attempt-limited); on success set the new password
 *                          hash and mark the OTP used.
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';

class PasswordReset
{
    private const OTP_TTL_MIN     = 10;  // minutes an OTP stays valid
    private const MAX_ATTEMPTS    = 5;   // wrong-code tries before the OTP is dead
    private const RESEND_COOLDOWN = 60;  // seconds between OTP requests for one email

    /**
     * Create + email an OTP for $email (if that user exists).
     *
     * @return array{cooldown?:int} cooldown present when throttled
     * @throws RuntimeException if the email could not be sent
     */
    public static function request(string $email): array
    {
        $email = trim(strtolower($email));
        $pdo   = Database::pdo();

        // Does the account exist? (We never reveal the answer to the caller.)
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            return []; // pretend success
        }

        // Throttle: block if a code was issued very recently.
        // All time math is done in SQL (NOW()) so it is timezone-consistent
        // regardless of PHP's default timezone vs the MySQL session timezone.
        $stmt = $pdo->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed
             FROM password_resets WHERE email = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$email]);
        $elapsed = $stmt->fetchColumn();
        if ($elapsed !== false && (int) $elapsed >= 0 && (int) $elapsed < self::RESEND_COOLDOWN) {
            return ['cooldown' => self::RESEND_COOLDOWN - (int) $elapsed];
        }

        // Invalidate any earlier outstanding codes for this email.
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0')
            ->execute([$email]);

        $otp     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);

        // expires_at computed by MySQL so it shares the timezone of created_at / NOW().
        $pdo->prepare(
            'INSERT INTO password_resets (email, otp_hash, expires_at)
             VALUES (?, ?, NOW() + INTERVAL ' . self::OTP_TTL_MIN . ' MINUTE)'
        )->execute([$email, $otpHash]);

        // Send the email LAST so a mail failure surfaces to the user.
        Mailer::send(
            $email,
            'Your StudentOrbit password reset code',
            "Hi,\n\n"
            . "Your StudentOrbit password reset code is:\n\n"
            . "    {$otp}\n\n"
            . 'This code expires in ' . self::OTP_TTL_MIN . " minutes and can be used once.\n"
            . "If you did not request a password reset, you can ignore this email.\n\n"
            . "— StudentOrbit CEO Dashboard"
        );

        return [];
    }

    /**
     * Verify an OTP and, on success, set the new password.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function verify(string $email, string $otp, string $newPassword): array
    {
        $email = trim(strtolower($email));
        $otp   = trim($otp);

        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
        }

        $pdo = Database::pdo();
        // (expires_at < NOW()) is evaluated in MySQL — timezone-consistent.
        $stmt = $pdo->prepare(
            'SELECT *, (expires_at < NOW()) AS is_expired FROM password_resets
             WHERE email = ? AND used = 0
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['ok' => false, 'error' => 'No active reset code. Please request a new one.'];
        }
        if ((int) $row['is_expired'] === 1) {
            $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$row['id']]);
            return ['ok' => false, 'error' => 'That code has expired. Please request a new one.'];
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$row['id']]);
            return ['ok' => false, 'error' => 'Too many incorrect attempts. Please request a new code.'];
        }

        if (!password_verify($otp, $row['otp_hash'])) {
            $pdo->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?')
                ->execute([$row['id']]);
            $left = self::MAX_ATTEMPTS - ((int) $row['attempts'] + 1);
            return ['ok' => false, 'error' => "Incorrect code. {$left} attempt(s) left."];
        }

        // Success — update password, consume the OTP.
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?')->execute([$hash, $email]);
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$row['id']]);
        // Kill any other outstanding codes too.
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0')->execute([$email]);

        return ['ok' => true];
    }
}
