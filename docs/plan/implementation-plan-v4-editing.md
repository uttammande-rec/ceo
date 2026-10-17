# StudentOrbit — Implementation Plan v4: In-app Editing (Phases, Goal, Verticals)

- **Date:** 2026-10-16
- **Related:** [v4 prompt](../prompts/v4-editable-phases-goal-verticals.md)

## Background
Audit of edit-capability (2026-10-16) found three things with no in-app edit path:
roadmap **phases** (API was GET-only), the headline **monthly goal** (`settings`, no write), and
a vertical's **name/color** (API already allowed them; the UI only exposed the 4 numbers).

## API changes (`public/api.php`)
| Route | Added |
|---|---|
| `POST phases` | create (name, description; `position` auto = max+1) |
| `PATCH phases/{id}` | edit name/description (ordering is via reorder, not here) |
| `DELETE phases/{id}` | delete; **409** if the phase still has tasks (FK-safe guard) |
| `POST phases/reorder` | `{items:[{id,position}]}` |
| `GET/PATCH settings` | read / update `revenue_target_month`, `students_target_month` (upsert) |
| `PATCH verticals/{id}` | (unchanged — already whitelisted `name` + `color`) |

### Reorder & the UNIQUE TINYINT `position`
`phases.position` is `TINYINT UNIQUE`. Assigning new positions one-by-one risks a transient
duplicate-key error. Reorder runs in a transaction that first flips every row to its **negative**
(`SET position = -position` — still unique, still inside TINYINT range), then writes the final
positive positions. **Bug caught in testing:** the first attempt shifted by `+100000`, which
overflows TINYINT (max 127) — fixed to the negate approach.

## Frontend changes
- **`index.php`** — "Edit monthly goal" button (Overview), "+ Phase" button (Roadmap).
- **`app.js`**
  - Roadmap: per-phase ▲/▼ reorder, **Edit**, **✕** controls in the phase header; clicks
    `stopPropagation()` so they don't toggle the expand. New `openPhaseModal`, `movePhase`,
    `removePhase` (pre-checks task count, confirms before delete).
  - `openGoalModal` → `PATCH settings`.
  - Targets table: a `<input type="color">` and a name `<input>` per vertical; the change
    handler sends strings for `name`/`color` and numbers for the four numeric fields. New
    `hex6()` helper normalises colours for the native colour input.
- **`app.css`** — `.phase-ctl` controls, editable vertical colour/name cells.

## Verified (2026-10-16, live API)
- settings PATCH → summary reflects new targets; restored to ₹2 Cr / 150k.
- vertical PATCH name+color round-trips.
- phase create → PATCH rename → reorder to front → delete; delete-with-tasks returns **409**;
  original 6-phase order restored. Reorder no longer overflows.
- `index.php`, `app.js` serve 200; new DOM hooks + JS handlers present.

## Note
No schema change (all tables already existed). Nothing to re-install; changes are code-only
plus data edited through the new endpoints.
