# StudentOrbit — Implementation Plan v3: Password Reset via Email OTP

- **Date:** 2026-10-16
- **Related:** [v3 prompt](../prompts/v3-password-reset-otp.md), builds on
  [v2 auth](implementation-plan-v2-auth.md).

## Goal
Let the CEO reset a forgotten password without DB access: request a one-time code by email,
then set a new password. No new third-party dependency (keep the zero-build, no-Composer
setup).

## Flow
1. **`forgot.php`** — enter email → `PasswordReset::request()` generates a 6-digit OTP, stores
   only its **hash** in `password_resets` (10-min expiry), and emails the code. Response is the
   same whether or not the account exists (no account enumeration). 60-second resend cooldown.
2. **`reset.php`** — enter the OTP + new password → `PasswordReset::verify()` validates the
   newest unused, unexpired, attempt-limited code; on success updates `users.password_hash`,
   marks the code used, and invalidates other outstanding codes. Redirects to `login.php` with
   a success flash.

## Security choices
- OTP stored as a `password_hash()` digest, never plaintext.
- 10-minute expiry; **max 5** wrong attempts per code; 60s resend throttle.
- No account enumeration (uniform response) — the email is sent only if the user exists but the
  UI never says which.
- **All time math is done in SQL (`NOW()`, `TIMESTAMPDIFF`, `expires_at < NOW()`)** so expiry
  and cooldown are correct regardless of the PHP-vs-MySQL timezone difference. (An earlier
  draft mixed PHP `time()`/`date()` with MySQL `CURRENT_TIMESTAMP`; the ~5.5h IST/UTC skew would
  have locked the resend cooldown for hours — fixed before release. See the discussion log.)

## SMTP transport
- New **`src/Mailer.php`**: a tiny self-contained SMTP client speaking EHLO / optional STARTTLS
  (port 587) **or** implicit SSL (port 465) / AUTH LOGIN / MAIL / RCPT / DATA. Plain-text body,
  RFC-2047 header encoding, dot-stuffing. Throws `RuntimeException` on any SMTP failure.
- Config read from `config['smtp']`, all env-driven (`SO_SMTP_HOST/PORT/SECURE/USER/PASS/FROM/
  FROM_NAME`). Secrets live in **`.env.local`** (chmod 600), loaded by `start.sh`; they are
  **not** written into `config/config.php` or these docs.
- Current deployment: cPanel mailbox `hr@kalamclss.org`, `kalamclss.org:465` SSL. OTPs are sent
  from that mailbox to the CEO login address.

## Files
| File | Role |
|---|---|
| `install/schema.sql` + live DB | New `password_resets` table |
| `src/Mailer.php` | Minimal SMTP sender (STARTTLS + SSL) |
| `src/PasswordReset.php` | `request()` / `verify()` OTP logic |
| `config/config.php` | New env-driven `smtp` block |
| `public/forgot.php` | Step 1 — request a code |
| `public/reset.php` | Step 2 — enter code + new password |
| `public/login.php` | "Forgot password?" link + success flash |
| `.env.local`, `start.sh` | Local secrets + launcher that loads them |

## Data model delta
```
password_resets(id PK, email VARCHAR(190), otp_hash VARCHAR(255),
                expires_at DATETIME, used TINYINT(1) DEFAULT 0,
                attempts TINYINT DEFAULT 0, created_at TIMESTAMP,
                INDEX(email), INDEX(expires_at))
```
The table is in `schema.sql` (idempotent `IF NOT EXISTS`) and was also applied to the live DB
directly so existing seeded data was not wiped.

## Verified (2026-10-16, 6/6)
request() makes a 10-min code · resend throttled (60s) · expired code rejected · wrong code
decrements attempts · correct code resets the password · used code cannot be reused. A live
SMTP connectivity test to `kalamclss.org:465` was accepted. Normal login confirmed working
after the password round-trip.

## Run
```
./start.sh          # loads .env.local (SMTP secrets) then serves on :8000
```
