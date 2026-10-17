# Prompt v1 — Initial Build Request

- **Date:** 2026-10-10
- **Author:** CEO (uttam.mande@raghuenggcollege.in)
- **Status:** Captured verbatim

---

## Original prompt

Build a single-page CEO dashboard web app for my startup "StudentOrbit",
a student pathways platform with 7 verticals: Govt Job Seekers,
Private Job Seekers, Overseas, Educational/Part-time Jobs,
Creative/Cultural/Sports Clubs, Public Speaking (Toastmasters),
and Research & Entrepreneurship (Cognitia Connect).
Goal: ₹2 Cr revenue per month from ~1.5 lakh students
(₹25 workshops + paid upgrades).

### Sections

1. **TOP KPI CARDS**
   - Monthly revenue target vs achieved (₹ + progress bar)
   - Students registered this month vs target
   - Active leads, tasks done / total, overall project % complete

2. **PROJECT ROADMAP** (phases, done one after another)
   Phase 1 Benchmarking & research, Phase 2 Branding & legal,
   Phase 3 Website & app build, Phase 4 Content & trainers,
   Phase 5 Marketing & launch, Phase 6 Scale-up.
   Each phase has tasks; a phase unlocks when the previous one
   is complete. Show % complete per phase.

3. **KANBAN TASK BOARD** (drag and drop)
   Columns: To do, In progress, Review, Done.
   Each card: title, vertical tag, phase, owner, priority
   (High/Medium/Low), due date. Add, edit, delete cards.
   Filter by vertical, phase, owner and priority.
   Overdue cards highlighted in red.

4. **TARGETS BY VERTICAL**
   Table + bar chart: monthly target vs actual revenue and students
   per vertical (defaults: Govt ₹50L, Private ₹50L, Overseas ₹40L,
   Educational ₹20L, Clubs ₹15L, Public speaking ₹13L,
   Research ₹10L). Editable actuals.

5. **LEADS TRACKER** (drag and drop pipeline)
   Stages: New, Contacted, Interested, Converted, Lost.
   Lead fields: name, phone, college, vertical, source
   (workshop/webinar/ambassador/social), follow-up date, notes.
   Show conversion rate and today's follow-ups.

6. **MY TO-DO LIST** (CEO personal)
   Quick add, checkbox, drag to reorder, "Today" and "This week".

### Requirements
- Clean, professional, mobile-friendly design, light and dark mode
- Save all data so it stays after refresh
- Export tasks and leads to CSV
- Pre-fill sample tasks for each phase
- Create docs folder and store version-wise prompts; store every prompt,
  discussion and plan (using a planning agent)
- Implement in PHP with a MySQL database
