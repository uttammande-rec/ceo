# StudentOrbit CEO Dashboard — Implementation Plan v1

- **Date:** 2026-10-10
- **Produced by:** Planning agent (architecture pass) + CEO prompt v1
- **Stack:** PHP 8.4 + MySQL 9.4 (PDO), vanilla JS/CSS (no CDN, no build step)
- **Related:** [v1 prompt](../prompts/v1-initial-prompt.md)

## Environment (verified 2026-10-10)
- PHP 8.4.11 (Homebrew) at `/usr/local/bin/php`
- MySQL 9.4.0 (Homebrew), `mysqld` + `mysql.server` present
- Working dir `/Volumes/Projects/CEO` was empty

## File / folder structure (as built)
```
/Volumes/Projects/CEO/
├── config/config.php          # DB credentials + app constants
├── src/Database.php           # PDO connection factory
├── install/
│   ├── schema.sql             # CREATE DATABASE + tables
│   ├── seed.sql               # sample phases/tasks/verticals/leads/todos
│   └── install.php            # runs schema.sql then seed.sql
├── public/                    # web root
│   ├── index.php              # single-page HTML shell
│   ├── api.php                # JSON API front controller (all /api/* routes)
│   └── assets/
│       ├── css/app.css        # theme variables, responsive layout
│       └── js/app.js          # full SPA (state, sections, drag&drop, CSV, charts)
├── docs/                      # prompts, plan, discussion (version-controlled narrative)
└── README.md                  # run instructions
```
Note: for deliverability the backend uses a single routed `public/api.php` (function-per-endpoint) instead of a controller-class-per-entity split, and the frontend is a single `app.js`. This keeps the app easy to run with zero build tooling while preserving the same endpoints and data model the plan describes.

## Data model (MySQL `studentorbit`, InnoDB, utf8mb4)
- **phases** — 6 roadmap phases, `position` 1..6 defines unlock order.
- **verticals** — 7 business verticals, `revenue_target/actual`, `students_target/actual`, `color`.
- **tasks** — shared by roadmap + kanban: `phase_id`, `vertical_id?`, `status(todo|in_progress|review|done)`, `owner`, `priority(high|medium|low)`, `due_date?`, `board_position`.
- **leads** — `name, phone, college, vertical_id?, source(workshop|webinar|ambassador|social), stage(new|contacted|interested|converted|lost), followup_date?, notes, pipeline_position`.
- **todos** — `text, done, bucket(today|week), position`.
- **settings** — key/value: `revenue_target_month=20000000` (₹2 Cr), `students_target_month=150000`.

Derived server-side: phase %complete = done/total tasks in phase; phase `unlocked` when previous phase is 100%; overall project % = done/total tasks; active leads = stages new/contacted/interested; conversion rate = converted/total; today's follow-ups = followup_date == today.

## JSON API (all under `public/api.php?r=...` / pretty routes)
- `GET  summary` — all KPI numbers + per-phase progress in one call
- `GET  phases` — phases with counts, %complete, unlocked flag
- `GET  tasks` (filters: vertical, phase, owner, priority, status) · `POST tasks` · `PATCH tasks/{id}` · `DELETE tasks/{id}` · `POST tasks/reorder`
- `GET  verticals` · `PATCH verticals/{id}` (edit actuals/targets)
- `GET  leads` (+ stats meta) · `POST leads` · `PATCH leads/{id}` · `DELETE leads/{id}`
- `GET  todos` · `POST todos` · `PATCH todos/{id}` · `DELETE todos/{id}` · `POST todos/reorder`
- `GET  export/tasks.csv` · `GET export/leads.csv`

## Frontend mechanics (vanilla JS)
- **Persistence:** every mutation calls the PHP API → MySQL; nothing lives only in memory, so refresh restores identical state.
- **Drag & drop:** one reusable HTML5 DnD helper used by kanban columns, leads pipeline stages, and todo reordering. On drop → optimistic DOM update + PATCH/reorder call; rollback on error.
- **Charts:** hand-built inline SVG grouped bar charts for targets (target vs actual, revenue + students) — no charting library.
- **CSV:** client-side builder for the current (filtered) view, plus server `export/*.csv` endpoints for an exact DB snapshot.
- **Theme:** CSS custom properties on `:root` with `[data-theme=dark]` overrides; toggle persisted to localStorage; respects `prefers-color-scheme`; applied before first paint.
- **Responsive:** CSS grid/flex; KPI cards stack, kanban/pipeline scroll horizontally, tables reflow on mobile.

## Install / seed
`php install/install.php` (or import schema.sql + seed.sql via mysql CLI). Seeds 6 phases, 7 verticals with default targets (Govt ₹50L, Private ₹50L, Overseas ₹40L, Educational ₹20L, Clubs ₹15L, Public speaking ₹13L, Research ₹10L), ~20 sample tasks across phases/statuses (a couple overdue to show the red highlight), ~10 leads across all stages (some due today), and sample todos. Seed is idempotent (truncate + insert) so the CEO can reset to a clean demo.

## Run locally
```
php install/install.php
php -S localhost:8000 -t public
# open http://localhost:8000
```

## Build sequencing
1. config + Database + schema/seed + install  →  2. api.php routes  →  3. index.php shell + app.css  →  4. app.js sections (KPIs → Roadmap → Kanban → Targets → Leads → Todos)  →  5. drag&drop, CSV/export, theme & mobile polish.
