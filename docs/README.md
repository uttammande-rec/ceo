# StudentOrbit — Docs

Narrative + decision record for the CEO Dashboard. Everything here is versioned by hand so we can trace *why* the app looks the way it does.

## Layout
- **`prompts/`** — every instruction given to the build agent, one file per version (`v1-...`, `v2-...`). Captured verbatim.
- **`plan/`** — the implementation plan(s) produced by the planning agent.
- **`discussion/`** — dated logs of decisions, trade-offs, and open questions.

## Index
| Version | File | Summary |
|---|---|---|
| v1 | [prompts/v1-initial-prompt.md](prompts/v1-initial-prompt.md) | Initial full build request (7 verticals, 6 sections, PHP+MySQL) |
| v1 | [plan/implementation-plan-v1.md](plan/implementation-plan-v1.md) | Architecture, schema, API, frontend plan |
| — | [discussion/2026-10-10-kickoff.md](discussion/2026-10-10-kickoff.md) | Kickoff decisions |
| v2 | [prompts/v2-auth-login.md](prompts/v2-auth-login.md) | Session login / auth (⚠️ prompt reconstructed, not verbatim) |
| v2 | [plan/implementation-plan-v2-auth.md](plan/implementation-plan-v2-auth.md) | `users` table, login page, page + API guards |
| — | [discussion/2026-10-12-auth.md](discussion/2026-10-12-auth.md) | Auth decisions |
| v3 | [prompts/v3-password-reset-otp.md](prompts/v3-password-reset-otp.md) | Forgot-password via emailed OTP (verbatim) |
| v3 | [plan/implementation-plan-v3-password-reset.md](plan/implementation-plan-v3-password-reset.md) | `password_resets` table, SMTP mailer, OTP flow |
| — | [discussion/2026-10-16-password-reset.md](discussion/2026-10-16-password-reset.md) | Reset decisions, timezone-bug fix, security note, v4 editing follow-up |
| v4 | [prompts/v4-editable-phases-goal-verticals.md](prompts/v4-editable-phases-goal-verticals.md) | In-app editing: phases, monthly goal, vertical name/color (verbatim) |
| v4 | [plan/implementation-plan-v4-editing.md](plan/implementation-plan-v4-editing.md) | phases CRUD+reorder, settings endpoint, editable targets table |

## How to add a new version
1. Save the new instruction verbatim as `prompts/vN-short-title.md`.
2. If it changes architecture, add/update a plan in `plan/`.
3. Append decisions to the latest `discussion/` log (or start a new dated one).
4. Add a row to the Index table above.
