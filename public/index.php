<?php
/**
 * StudentOrbit CEO Dashboard — single-page HTML shell.
 * All data is loaded by assets/js/app.js from api.php.
 */
require __DIR__ . '/auth.php';
require_login_page();
$me = current_user();
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudentOrbit — CEO Dashboard</title>
<link rel="stylesheet" href="assets/css/app.css">
<script>
  // Apply saved theme before first paint to avoid a flash.
  (function () {
    var t = localStorage.getItem('so-theme');
    if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
  })();
</script>
</head>
<body>
<header class="topbar">
  <div class="brand">
    <span class="logo">◆</span>
    <div>
      <h1>StudentOrbit</h1>
      <p class="tagline">CEO Dashboard</p>
    </div>
  </div>
  <nav class="tabs" id="tabs">
    <button data-section="overview" class="active">Overview</button>
    <button data-section="roadmap">Roadmap</button>
    <button data-section="kanban">Kanban</button>
    <button data-section="targets">Targets</button>
    <button data-section="leads">Leads</button>
    <button data-section="todos">To-dos</button>
  </nav>
  <div class="user-box">
    <span class="user-email" title="<?= htmlspecialchars($me['email']) ?>"><?= htmlspecialchars($me['name'] ?: $me['email']) ?></span>
    <button id="themeToggle" class="icon-btn" title="Toggle theme">◐</button>
    <a class="btn ghost sm" href="login.php?logout=1" title="Sign out">Sign out</a>
  </div>
</header>

<main id="app">
  <!-- Overview -->
  <section id="overview" class="section active">
    <div class="section-head">
      <h2>Overview</h2>
      <div class="actions">
        <button class="btn ghost sm" id="editGoalBtn">Edit monthly goal</button>
      </div>
    </div>
    <div class="kpi-grid" id="kpiGrid"></div>
    <div class="card">
      <h2>Phase progress</h2>
      <div id="overviewPhases" class="phase-mini"></div>
    </div>
  </section>

  <!-- Roadmap -->
  <section id="roadmap" class="section">
    <div class="section-head">
      <h2>Roadmap <span class="muted">— phases unlock in order</span></h2>
      <div class="actions">
        <button class="btn" id="addPhaseBtn">+ Phase</button>
      </div>
    </div>
    <div id="roadmap-list" class="roadmap"></div>
  </section>

  <!-- Kanban -->
  <section id="kanban" class="section">
    <div class="section-head">
      <h2>Kanban board</h2>
      <div class="actions">
        <select id="kanbanPhase" class="sel"></select>
        <button class="btn" id="addTaskBtn">+ Task</button>
        <a class="btn ghost" href="api.php?r=export/tasks.csv">Export CSV</a>
      </div>
    </div>
    <div id="board" class="board"></div>
  </section>

  <!-- Targets -->
  <section id="targets" class="section">
    <h2>Targets by vertical</h2>
    <div class="chart-row">
      <div class="card"><h3>Revenue (₹) — target vs actual</h3><div id="revChart"></div></div>
      <div class="card"><h3>Students — target vs actual</h3><div id="stuChart"></div></div>
    </div>
    <div class="card">
      <table class="grid" id="verticalTable"></table>
    </div>
  </section>

  <!-- Leads -->
  <section id="leads" class="section">
    <div class="section-head">
      <h2>Leads pipeline</h2>
      <div class="actions">
        <button class="btn" id="addLeadBtn">+ Lead</button>
        <a class="btn ghost" href="api.php?r=export/leads.csv">Export CSV</a>
      </div>
    </div>
    <div id="pipeline" class="board"></div>
  </section>

  <!-- To-dos -->
  <section id="todos" class="section">
    <h2>To-dos</h2>
    <div class="todo-cols">
      <div class="card todo-col" data-bucket="today">
        <h3>Today</h3>
        <ul class="todo-list" data-bucket="today"></ul>
        <form class="todo-add" data-bucket="today"><input placeholder="Add for today…" required><button class="btn">Add</button></form>
      </div>
      <div class="card todo-col" data-bucket="week">
        <h3>This week</h3>
        <ul class="todo-list" data-bucket="week"></ul>
        <form class="todo-add" data-bucket="week"><input placeholder="Add for this week…" required><button class="btn">Add</button></form>
      </div>
    </div>
  </section>
</main>

<!-- Modal shell (reused for task + lead forms) -->
<div id="modal" class="modal-backdrop hidden">
  <div class="modal">
    <div class="modal-head"><h3 id="modalTitle">Edit</h3><button class="icon-btn" id="modalClose">✕</button></div>
    <form id="modalForm" class="modal-body"></form>
  </div>
</div>

<div id="toast" class="toast hidden"></div>

<script src="assets/js/app.js"></script>
</body>
</html>
