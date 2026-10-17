# Prompt v2 — Authentication / Login

- **Date:** 2026-10-12
- **Author:** CEO (uttam.mande@raghuenggcollege.in)
- **Status:** ⚠️ Reconstructed — the original instruction was **not captured verbatim** at the
  time the feature was built. This file documents the request as inferred from the
  implementation (commit of 2026-10-12) so the record is complete. Future prompts must be
  captured verbatim per the process in `../README.md`.

---

## Request (reconstructed)

Add a login so the dashboard is not open to anyone who opens the URL. A single CEO account is
enough for now (the kickoff listed multi-user auth + roles as a later item). Requirements
inferred from the build:

- A sign-in page; the whole app and the JSON API require an authenticated session.
- Credentials stored securely (hashed password, never plaintext in the database).
- A default CEO account created by the installer from `config/config.php` (overridable via
  `SO_ADMIN_*` env vars).
- A "Sign out" control in the app header.

See the implementation notes in [../plan/implementation-plan-v2-auth.md](../plan/implementation-plan-v2-auth.md)
and the decisions in [../discussion/2026-10-12-auth.md](../discussion/2026-10-12-auth.md).
