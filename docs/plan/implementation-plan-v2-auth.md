# StudentOrbit — Implementation Plan v2: Authentication

- **Date:** 2026-10-12
- **Related:** [v2 prompt](../prompts/v2-auth-login.md)
- **Supersedes nothing** — additive to [implementation-plan-v1.md](implementation-plan-v1.md).
- **Status note:** documented retroactively on 2026-10-16 (feature shipped 2026-10-12, docs
  were behind the code).

## Goal
Gate the entire dashboard and JSON API behind a session login. Single CEO account for now
(multi-user/roles remain a future item per the kickoff).

## Design
- **Session-based auth** using PHP native sessions (`so_session` cookie). No tokens/JWT —
  overkill for a single-user local app.
- **Password storage:** `password_hash()` (bcrypt) on install; `password_verify()` on login.
  Plaintext is never persisted. `session_regenerate_id(true)` on successful login to prevent
  fixation.
- **Default account** seeded by the installer from `config['admin']` (email/name/password),
  all overridable via `SO_ADMIN_EMAIL` / `SO_ADMIN_NAME` / `SO_ADMIN_PASS`.

## Files
| File | Role |
|---|---|
| `src/..` (schema) `install/schema.sql` | New `users` table (`id, email UNIQUE, name, password_hash, created_at`) |
| `install/install.php` | After schema+seed, upsert the admin user (hashed) via `ON DUPLICATE KEY UPDATE` |
| `public/auth.php` | Session bootstrap + `current_user()`, `attempt_login()`, `logout()`, `require_login_page()`, `require_login_api()` |
| `public/login.php` | `GET` form / `POST` authenticate / `?logout=1` sign out |
| `public/index.php` | Calls `require_login_page()`; adds a "Sign out" link |
| `public/api.php` | Calls `require_login_api()` → `401 {"error":"Not authenticated"}` when no session |

## Data model delta
```
users(id PK, email VARCHAR(190) UNIQUE, name VARCHAR(120) NULL,
      password_hash VARCHAR(255), created_at TIMESTAMP)
```

## Verified behaviour (2026-10-16 audit)
- `GET /` → `302` redirect to `login.php` when unauthenticated.
- `GET /login.php` → `200`.
- `GET /api.php?r=summary` without a session → `401`.
- `POST /login.php` with valid credentials → `302` to `index.php`.

## Known follow-ups (addressed in v3)
- No way to recover a forgotten password → see
  [implementation-plan-v3-password-reset.md](implementation-plan-v3-password-reset.md).
