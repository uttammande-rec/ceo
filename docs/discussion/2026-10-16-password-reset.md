# Discussion log — 2026-10-16 Password Reset (Email OTP)

See [../prompts/v3-password-reset-otp.md](../prompts/v3-password-reset-otp.md) and
[../plan/implementation-plan-v3-password-reset.md](../plan/implementation-plan-v3-password-reset.md).

## Decisions
1. **Email OTP reset** chosen over a magic-link (simpler, and works from any device without the
   app being internet-reachable). 6-digit code, 10-min expiry, 5 attempts, 60s resend cooldown.
2. **OTP stored hashed**, single-use; no account enumeration (uniform response for unknown
   emails). New `password_resets` table added to `schema.sql` and to the live DB (no reseed, so
   existing data was preserved).
3. **No new dependency.** Wrote a minimal `src/Mailer.php` (STARTTLS *and* implicit-SSL/465)
   instead of pulling in Composer/PHPMailer, keeping the zero-build setup.
4. **Secrets in `.env.local` (chmod 600), loaded by `start.sh`** — not in `config/config.php`
   and not in these docs. Config reads `SO_SMTP_*` env vars.
5. **Transport:** cPanel mailbox `hr@kalamclss.org` on `kalamclss.org:465` (SSL). OTPs are sent
   from that mailbox to the CEO login address (`uttammande@gmail.com`).

## Bug caught before release
- The first draft mixed PHP-side time (`time()`/`date()`, UTC) with MySQL `CURRENT_TIMESTAMP`
  (system/IST). The ~5.5h skew made the resend cooldown compute a negative elapsed time,
  which would have blocked re-requests for hours. **Fix:** do all expiry/cooldown math in SQL
  (`NOW()`, `TIMESTAMPDIFF`, `expires_at < NOW()`). Re-tested 6/6 green.

## ⚠️ Security note
- The `hr@kalamclss.org` password was shared in the chat session while providing SMTP settings.
  It is stored only in `.env.local` now, but because it travelled through the conversation it
  should be **rotated in cPanel** and updated in `.env.local`.
- Same standing recommendation as v2: move the DB/admin password fallbacks out of
  `config/config.php` into env-only.

---

## Same-day follow-up — v4 In-app editing

The CEO asked whether some things had no update option ("roadmaps and like this"). Audit found
three gaps; all three were added (see [v4](../prompts/v4-editable-phases-goal-verticals.md) /
[plan v4](../plan/implementation-plan-v4-editing.md)):

1. **Roadmap phases** — add / edit / reorder / delete (delete blocked while tasks remain).
2. **Monthly goal** — the headline ₹2 Cr / 1.5 lakh target is now editable (new `settings`
   endpoint).
3. **Vertical name & color** — now editable in the Targets table (API already allowed it).

### Bug caught
Phase reorder first shifted positions by `+100000` to dodge the `UNIQUE` constraint, but
`position` is a `TINYINT` (max 127) → overflow error. Fixed by negating positions as the temp
state instead. Re-tested green.

## Open items / future prompts
- Let a signed-in user change their password from inside the app (currently only via the reset
  flow or re-running the installer).
- Phase reorder is move-up/down; drag-to-reorder could be added later if desired.
