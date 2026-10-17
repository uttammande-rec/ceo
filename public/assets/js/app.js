/* StudentOrbit CEO Dashboard — vanilla JS SPA
   State lives in MySQL; every mutation hits api.php so a refresh restores identical state. */

'use strict';

const API = 'api.php';
const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

/* in-memory caches of the last fetch, for rendering */
const state = { summary: null, phases: [], tasks: [], verticals: [], leads: [], leadMeta: {}, todos: [], today: null };

const TASK_COLS = [
  ['todo', 'To do'], ['in_progress', 'In progress'], ['review', 'Review'], ['done', 'Done'],
];
const LEAD_STAGES = [
  ['new', 'New'], ['contacted', 'Contacted'], ['interested', 'Interested'],
  ['converted', 'Converted'], ['lost', 'Lost'],
];

/* ----------------------------- fetch helpers ----------------------------- */
async function api(route, opts = {}) {
  const res = await fetch(`${API}?r=${route}`, {
    headers: { 'Content-Type': 'application/json' },
    ...opts,
    body: opts.body ? JSON.stringify(opts.body) : undefined,
  });
  if (res.status === 401) {
    // Session expired or not signed in — send to the login screen.
    window.location.href = 'login.php';
    throw new Error('Not authenticated');
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}
const get   = (r) => api(r);
const post  = (r, body) => api(r, { method: 'POST', body });
const patch = (r, body) => api(r, { method: 'PATCH', body });
const del   = (r) => api(r, { method: 'DELETE' });

/* ----------------------------- utilities ----------------------------- */
function money(n) {
  n = Number(n) || 0;
  if (n >= 1e7) return '₹' + (n / 1e7).toFixed(2) + ' Cr';
  if (n >= 1e5) return '₹' + (n / 1e5).toFixed(2) + ' L';
  return '₹' + n.toLocaleString('en-IN');
}
const num = (n) => (Number(n) || 0).toLocaleString('en-IN');
const vById = (id) => state.verticals.find((v) => String(v.id) === String(id));
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
// <input type="color"> requires a 6-digit hex; normalise 3-digit / missing values.
const hex6 = (c) => {
  c = String(c || '').trim();
  if (/^#[0-9a-fA-F]{6}$/.test(c)) return c;
  if (/^#[0-9a-fA-F]{3}$/.test(c)) return '#' + c.slice(1).split('').map((x) => x + x).join('');
  return '#888888';
};

let toastTimer;
function toast(msg, isErr = false) {
  const t = $('#toast');
  t.textContent = msg;
  t.className = 'toast' + (isErr ? ' err' : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.add('hidden'), 2600);
}

/* ----------------------------- data load ----------------------------- */
async function loadAll() {
  const [summary, phases, tasks, verticals, leads, todos] = await Promise.all([
    get('summary'), get('phases'), get('tasks'), get('verticals'), get('leads'), get('todos'),
  ]);
  state.summary = summary;
  state.today = summary.today;
  state.phases = phases;
  state.tasks = tasks;
  state.verticals = verticals;
  state.leads = leads.items;
  state.leadMeta = leads.meta;
  state.todos = todos;
}

function renderAll() {
  renderKPIs();
  renderOverviewPhases();
  renderRoadmap();
  renderKanban();
  renderTargets();
  renderLeads();
  renderTodos();
}

/* ----------------------------- KPIs / overview ----------------------------- */
function renderKPIs() {
  const s = state.summary;
  const cards = [
    { label: 'Overall progress', value: s.tasks.percent + '%', sub: `${s.tasks.done}/${s.tasks.total} tasks done`, pct: s.tasks.percent },
    { label: 'Revenue (actual)', value: money(s.revenue.actual), sub: `of ${money(s.revenue.target)} target`, pct: Math.round(s.revenue.actual / s.revenue.target * 100) },
    { label: 'Students reached', value: num(s.students.actual), sub: `of ${num(s.students.target)} target`, pct: Math.round(s.students.actual / s.students.target * 100) },
    { label: 'Active leads', value: num(s.leads.active), sub: `${s.leads.converted} converted · ${s.leads.conversion_rate}%`, pct: s.leads.conversion_rate },
    { label: "Follow-ups today", value: num(s.leads.followups_today), sub: s.leads.followups_today ? 'needs attention' : 'all clear', pct: null },
  ];
  $('#kpiGrid').innerHTML = cards.map((c) => `
    <div class="kpi">
      <div class="label">${c.label}</div>
      <div class="value">${c.value}</div>
      <div class="sub">${c.sub}</div>
      ${c.pct !== null ? `<div class="bar"><span style="width:${Math.min(100, c.pct)}%"></span></div>` : ''}
    </div>`).join('');
}

function renderOverviewPhases() {
  $('#overviewPhases').innerHTML = state.phases.map((p) => `
    <div class="row">
      <div>${esc(p.name)}</div>
      <div class="track"><span style="width:${p.percent}%"></span></div>
      <div style="text-align:right">${p.percent}%</div>
    </div>`).join('');
}

/* ----------------------------- roadmap ----------------------------- */
function renderRoadmap() {
  const el = $('#roadmap-list');
  const last = state.phases.length - 1;
  el.innerHTML = state.phases.map((p, i) => {
    const tasks = state.tasks.filter((t) => String(t.phase_id) === String(p.id));
    const locked = !p.unlocked;
    return `
    <div class="phase ${locked ? 'locked' : ''} ${p.percent === 100 || !locked ? 'open' : ''}" data-phase="${p.id}">
      <div class="phase-head">
        <div class="idx">${p.position}</div>
        <div class="meta">
          <div class="name">${esc(p.name)}</div>
          <div class="desc">${esc(p.description || '')}</div>
        </div>
        <div class="pct">${p.percent}%</div>
        <div class="phase-ctl">
          <button class="icon-btn mv" data-id="${p.id}" data-dir="-1" title="Move up" ${i === 0 ? 'disabled' : ''}>▲</button>
          <button class="icon-btn mv" data-id="${p.id}" data-dir="1" title="Move down" ${i === last ? 'disabled' : ''}>▼</button>
          <button class="btn ghost sm edit-phase" data-id="${p.id}">Edit</button>
          <button class="btn ghost sm del-phase" data-id="${p.id}" title="Delete phase">✕</button>
        </div>
      </div>
      <div class="track"><span style="width:${p.percent}%"></span></div>
      ${locked ? `<div class="lock-note">🔒 Unlocks when the previous phase is 100% complete</div>` : ''}
      <div class="phase-tasks">
        ${tasks.map((t) => `
          <div class="ptask ${t.status === 'done' ? 'done' : ''}">
            <input type="checkbox" data-task="${t.id}" ${t.status === 'done' ? 'checked' : ''}>
            <span class="ptxt">${esc(t.title)}</span>
            <span class="pill ${t.priority}">${t.priority}</span>
            ${t.owner ? `<span class="pill">${esc(t.owner)}</span>` : ''}
          </div>`).join('') || '<div class="muted" style="padding:8px 0">No tasks yet.</div>'}
      </div>
    </div>`;
  }).join('');

  $$('.phase-head', el).forEach((h) => h.addEventListener('click', (e) => {
    if (e.target.closest('.phase-ctl')) return; // don't toggle when using a control
    h.parentElement.classList.toggle('open');
  }));
  $$('.phase-ctl .mv', el).forEach((b) => b.addEventListener('click', (e) => {
    e.stopPropagation();
    movePhase(b.dataset.id, Number(b.dataset.dir));
  }));
  $$('.phase-ctl .edit-phase', el).forEach((b) => b.addEventListener('click', (e) => {
    e.stopPropagation();
    openPhaseModal(state.phases.find((p) => String(p.id) === String(b.dataset.id)));
  }));
  $$('.phase-ctl .del-phase', el).forEach((b) => b.addEventListener('click', (e) => {
    e.stopPropagation();
    removePhase(b.dataset.id);
  }));
  $$('input[data-task]', el).forEach((cb) => cb.addEventListener('change', async () => {
    try {
      await patch('tasks/' + cb.dataset.task, { status: cb.checked ? 'done' : 'todo' });
      toast('Task updated');
      await loadAll(); renderAll();
    } catch (e) { toast(e.message, true); cb.checked = !cb.checked; }
  }));
}

/* phase add / edit / reorder / delete */
function openPhaseModal(phase) {
  const p = phase || {};
  openModal(phase ? 'Edit phase' : 'New phase', `
    <label>Name<input name="name" value="${esc(p.name || '')}" required></label>
    <label>Description<textarea name="description" rows="2">${esc(p.description || '')}</textarea></label>`,
    async (fd) => {
      const body = { name: fd.get('name'), description: fd.get('description') };
      if (phase) await patch('phases/' + phase.id, body);
      else await post('phases', body);
      toast('Phase saved');
      await loadAll(); renderAll();
    });
}

async function movePhase(id, dir) {
  const order = state.phases.map((p) => p.id);
  const i = order.findIndex((x) => String(x) === String(id));
  const j = i + dir;
  if (i < 0 || j < 0 || j >= order.length) return;
  [order[i], order[j]] = [order[j], order[i]];
  const items = order.map((pid, idx) => ({ id: Number(pid), position: idx + 1 }));
  try {
    await post('phases/reorder', { items });
    toast('Reordered');
    await loadAll(); renderAll();
  } catch (e) { toast(e.message, true); }
}

async function removePhase(id) {
  const p = state.phases.find((x) => String(x.id) === String(id));
  if (p && p.total > 0) {
    toast('Move or delete this phase’s tasks first', true);
    return;
  }
  if (!confirm(`Delete phase "${p ? p.name : ''}"? This cannot be undone.`)) return;
  try { await del('phases/' + id); toast('Phase deleted'); await loadAll(); renderAll(); }
  catch (e) { toast(e.message, true); }
}

function openGoalModal() {
  const s = state.summary;
  openModal('Edit monthly goal', `
    <label>Revenue target (₹ per month)
      <input type="number" name="revenue_target_month" min="0" step="1" value="${s.revenue.target}" required></label>
    <label>Students target (per month)
      <input type="number" name="students_target_month" min="0" step="1" value="${s.students.target}" required></label>
    <p class="muted" style="font-size:12px;margin:2px 0 0">Headline company-wide goal shown on the KPI cards.</p>`,
    async (fd) => {
      await patch('settings', {
        revenue_target_month: Number(fd.get('revenue_target_month')),
        students_target_month: Number(fd.get('students_target_month')),
      });
      toast('Monthly goal updated');
      await loadAll(); renderAll();
    });
}

/* ----------------------------- kanban ----------------------------- */
function renderKanban() {
  const sel = $('#kanbanPhase');
  if (!sel.dataset.ready) {
    sel.innerHTML = '<option value="">All phases</option>' +
      state.phases.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
    sel.dataset.ready = '1';
    sel.addEventListener('change', renderKanban);
  }
  const phaseFilter = sel.value;
  const board = $('#board');
  const tasks = state.tasks.filter((t) => !phaseFilter || String(t.phase_id) === String(phaseFilter));

  board.innerHTML = TASK_COLS.map(([status, label]) => {
    const items = tasks.filter((t) => t.status === status).sort((a, b) => a.board_position - b.board_position);
    return `
    <div class="col">
      <div class="col-head"><span>${label}</span><span class="count">${items.length}</span></div>
      <div class="col-body" data-status="${status}">
        ${items.map(ticketHtml).join('')}
      </div>
    </div>`;
  }).join('');

  wireTicketDnD(board, 'tasks');
  $$('.ticket .edit', board).forEach((b) => b.addEventListener('click', () => openTaskModal(stateTask(b.dataset.id))));
  $$('.ticket .del', board).forEach((b) => b.addEventListener('click', () => removeTask(b.dataset.id)));
}

const stateTask = (id) => state.tasks.find((t) => String(t.id) === String(id));

function ticketHtml(t) {
  const v = vById(t.vertical_id);
  const overdue = t.due_date && t.due_date < state.today && t.status !== 'done';
  return `
  <div class="ticket ${overdue ? 'overdue' : ''}" draggable="true" data-id="${t.id}">
    <div class="t-title">${esc(t.title)}</div>
    <div class="t-meta">
      <span class="pill ${t.priority}">${t.priority}</span>
      ${v ? `<span class="pill vert" style="background:${v.color || '#888'}">${esc(v.name)}</span>` : ''}
      ${t.owner ? `<span class="pill">${esc(t.owner)}</span>` : ''}
    </div>
    ${t.due_date ? `<div class="due ${overdue ? 'red' : ''}">📅 ${t.due_date}${overdue ? ' · overdue' : ''}</div>` : ''}
    <div class="t-actions">
      <button class="btn ghost sm edit" data-id="${t.id}">Edit</button>
      <button class="btn ghost sm del" data-id="${t.id}">✕</button>
    </div>
  </div>`;
}

/* ----------------------------- shared drag & drop ----------------------------- */
/* kind: 'tasks' (col data-status) or 'leads' (col data-stage) */
function wireTicketDnD(container, kind) {
  const field = kind === 'tasks' ? 'status' : 'stage';
  let dragEl = null;

  $$('.ticket', container).forEach((card) => {
    card.addEventListener('dragstart', () => { dragEl = card; card.classList.add('dragging'); });
    card.addEventListener('dragend', () => { dragEl = null; card.classList.remove('dragging'); });
  });

  $$('.col-body', container).forEach((body) => {
    body.addEventListener('dragover', (e) => {
      e.preventDefault();
      body.classList.add('drag-over');
      const after = afterElement(body, e.clientY);
      if (!dragEl) return;
      if (after == null) body.appendChild(dragEl);
      else body.insertBefore(dragEl, after);
    });
    body.addEventListener('dragleave', () => body.classList.remove('drag-over'));
    body.addEventListener('drop', async (e) => {
      e.preventDefault();
      body.classList.remove('drag-over');
      if (!dragEl) return;
      const newVal = body.dataset[field === 'status' ? 'status' : 'stage'];
      await persistBoard(container, kind, field, newVal, dragEl.dataset.id);
    });
  });
}

function afterElement(body, y) {
  const els = [...body.querySelectorAll('.ticket:not(.dragging)')];
  return els.reduce((closest, child) => {
    const box = child.getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closest.offset) return { offset, element: child };
    return closest;
  }, { offset: -Infinity, element: null }).element;
}

async function persistBoard(container, kind, field, newVal, movedId) {
  // Build reorder payload from current DOM order across all columns.
  const items = [];
  $$('.col-body', container).forEach((body) => {
    const val = body.dataset[field === 'status' ? 'status' : 'stage'];
    $$('.ticket', body).forEach((card, i) => {
      const entry = { id: Number(card.dataset.id), position: i };
      entry[field] = val;
      items.push(entry);
    });
  });
  try {
    if (kind === 'tasks') {
      await post('tasks/reorder', { items });
    } else {
      // leads has no bulk reorder endpoint; patch each (small N)
      await Promise.all(items.map((it) => patch('leads/' + it.id, { stage: it.stage, pipeline_position: it.position })));
    }
    toast('Saved');
    await loadAll();
    if (kind === 'tasks') { renderKanban(); renderKPIs(); renderRoadmap(); renderOverviewPhases(); }
    else { renderLeads(); renderKPIs(); }
  } catch (e) {
    toast(e.message, true);
    await loadAll(); renderAll();
  }
}

/* ----------------------------- targets ----------------------------- */
function renderTargets() {
  $('#revChart').innerHTML = barChart(state.verticals, 'revenue_target', 'revenue_actual', money);
  $('#stuChart').innerHTML = barChart(state.verticals, 'students_target', 'students_actual', num);

  const t = $('#verticalTable');
  t.innerHTML = `
    <thead><tr>
      <th class="c">Color</th><th>Vertical</th><th class="num">Rev target</th><th class="num">Rev actual</th>
      <th class="num">Students target</th><th class="num">Students actual</th>
    </tr></thead>
    <tbody>
      ${state.verticals.map((v) => `
        <tr data-id="${v.id}">
          <td class="c"><input type="color" class="color" data-f="color" value="${hex6(v.color)}" title="Vertical colour"></td>
          <td><input type="text" class="vname" data-f="name" value="${esc(v.name)}"></td>
          <td class="num"><input type="number" data-f="revenue_target" value="${v.revenue_target}"></td>
          <td class="num"><input type="number" data-f="revenue_actual" value="${v.revenue_actual}"></td>
          <td class="num"><input type="number" data-f="students_target" value="${v.students_target}"></td>
          <td class="num"><input type="number" data-f="students_actual" value="${v.students_actual}"></td>
        </tr>`).join('')}
    </tbody>`;

  $$('#verticalTable input', t).forEach((inp) => inp.addEventListener('change', async () => {
    const id = inp.closest('tr').dataset.id;
    const f = inp.dataset.f;
    const val = (f === 'name' || f === 'color') ? inp.value : Number(inp.value);
    try {
      await patch('verticals/' + id, { [f]: val });
      toast('Vertical updated');
      await loadAll(); renderTargets(); renderKPIs();
    } catch (e) { toast(e.message, true); }
  }));
}

function barChart(rows, targetKey, actualKey, fmt) {
  const W = 520, H = 230, padL = 10, padB = 46, padT = 10;
  const max = Math.max(1, ...rows.map((r) => Math.max(+r[targetKey], +r[actualKey])));
  const groupW = (W - padL) / rows.length;
  const barW = Math.min(20, groupW / 3);
  const scaleY = (v) => (H - padB - padT) * (v / max);
  let svg = `<svg class="chart" viewBox="0 0 ${W} ${H}" role="img">`;
  rows.forEach((r, i) => {
    const gx = padL + i * groupW + groupW / 2;
    const tH = scaleY(+r[targetKey]), aH = scaleY(+r[actualKey]);
    const baseY = H - padB;
    svg += `
      <rect x="${gx - barW - 2}" y="${baseY - tH}" width="${barW}" height="${tH}" rx="3" fill="var(--border)"></rect>
      <rect x="${gx + 2}" y="${baseY - aH}" width="${barW}" height="${aH}" rx="3" fill="${r.color || 'var(--primary)'}"></rect>
      <text class="lbl" x="${gx}" y="${H - padB + 14}" text-anchor="middle">${esc(r.name.split(' ')[0])}</text>
      <text class="lbl" x="${gx}" y="${H - padB + 28}" text-anchor="middle">${fmt(r[actualKey])}</text>`;
  });
  svg += `</svg>
    <div class="muted" style="font-size:12px;margin-top:4px">
      <span class="dot" style="background:var(--border)"></span>Target
      <span class="dot" style="background:var(--primary);margin-left:12px"></span>Actual
    </div>`;
  return svg;
}

/* ----------------------------- leads ----------------------------- */
function renderLeads() {
  const board = $('#pipeline');
  board.innerHTML = LEAD_STAGES.map(([stage, label]) => {
    const items = state.leads.filter((l) => l.stage === stage).sort((a, b) => a.pipeline_position - b.pipeline_position);
    return `
    <div class="col">
      <div class="col-head"><span>${label}</span><span class="count">${items.length}</span></div>
      <div class="col-body" data-stage="${stage}">
        ${items.map(leadHtml).join('')}
      </div>
    </div>`;
  }).join('');

  wireTicketDnD(board, 'leads');
  $$('.ticket .edit', board).forEach((b) => b.addEventListener('click', () => openLeadModal(state.leads.find((l) => String(l.id) === b.dataset.id))));
  $$('.ticket .del', board).forEach((b) => b.addEventListener('click', () => removeLead(b.dataset.id)));
}

function leadHtml(l) {
  const v = vById(l.vertical_id);
  const dueToday = l.followup_date === state.today;
  return `
  <div class="ticket ${dueToday ? 'lead-today' : ''}" draggable="true" data-id="${l.id}">
    <div class="t-title">${esc(l.name)}</div>
    <div class="t-meta">
      ${l.source ? `<span class="pill">${esc(l.source)}</span>` : ''}
      ${v ? `<span class="pill vert" style="background:${v.color || '#888'}">${esc(v.name)}</span>` : ''}
    </div>
    ${l.college ? `<div class="due">🎓 ${esc(l.college)}</div>` : ''}
    ${l.phone ? `<div class="due">📞 ${esc(l.phone)}</div>` : ''}
    ${l.followup_date ? `<div class="due ${dueToday ? 'red' : ''}">📅 ${l.followup_date}${dueToday ? ' · today' : ''}</div>` : ''}
    <div class="t-actions">
      <button class="btn ghost sm edit" data-id="${l.id}">Edit</button>
      <button class="btn ghost sm del" data-id="${l.id}">✕</button>
    </div>
  </div>`;
}

/* ----------------------------- todos ----------------------------- */
function renderTodos() {
  ['today', 'week'].forEach((bucket) => {
    const ul = $(`.todo-list[data-bucket="${bucket}"]`);
    const items = state.todos.filter((t) => t.bucket === bucket).sort((a, b) => a.position - b.position);
    ul.innerHTML = items.map((t) => `
      <li class="${+t.done ? 'done' : ''}" draggable="true" data-id="${t.id}">
        <input type="checkbox" ${+t.done ? 'checked' : ''}>
        <span>${esc(t.text)}</span>
        <button class="del" title="Delete">✕</button>
      </li>`).join('') || '<li class="muted" style="cursor:default">Nothing here.</li>';

    $$('li[data-id]', ul).forEach((li) => {
      $('input', li).addEventListener('change', async (e) => {
        try { await patch('todos/' + li.dataset.id, { done: e.target.checked ? 1 : 0 }); await loadAll(); renderTodos(); }
        catch (err) { toast(err.message, true); }
      });
      $('.del', li).addEventListener('click', async () => {
        try { await del('todos/' + li.dataset.id); toast('Deleted'); await loadAll(); renderTodos(); }
        catch (err) { toast(err.message, true); }
      });
    });
    wireTodoDnD(ul, bucket);
  });
}

function wireTodoDnD(ul, bucket) {
  let dragEl = null;
  $$('li[data-id]', ul).forEach((li) => {
    li.addEventListener('dragstart', () => { dragEl = li; li.classList.add('dragging'); });
    li.addEventListener('dragend', () => { dragEl = null; li.classList.remove('dragging'); });
  });
  ul.addEventListener('dragover', (e) => {
    e.preventDefault();
    if (!dragEl) return;
    const after = afterElementV(ul, e.clientY);
    if (after == null) ul.appendChild(dragEl); else ul.insertBefore(dragEl, after);
  });
  ul.addEventListener('drop', async (e) => {
    e.preventDefault();
    if (!dragEl) return;
    const items = $$('li[data-id]', ul).map((li, i) => ({ id: Number(li.dataset.id), position: i, bucket }));
    try { await post('todos/reorder', { items }); await loadAll(); }
    catch (err) { toast(err.message, true); await loadAll(); renderTodos(); }
  });
}
function afterElementV(ul, y) {
  const els = [...ul.querySelectorAll('li[data-id]:not(.dragging)')];
  return els.reduce((closest, child) => {
    const box = child.getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closest.offset) return { offset, element: child };
    return closest;
  }, { offset: -Infinity, element: null }).element;
}

$$('.todo-add').forEach((form) => form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const inp = $('input', form);
  const text = inp.value.trim();
  if (!text) return;
  try {
    await post('todos', { text, bucket: form.dataset.bucket });
    inp.value = '';
    await loadAll(); renderTodos();
  } catch (err) { toast(err.message, true); }
}));

/* ----------------------------- modals (task + lead) ----------------------------- */
function openModal(title, html, onSubmit) {
  $('#modalTitle').textContent = title;
  $('#modalForm').innerHTML = html + `
    <div class="foot">
      <button type="button" class="btn ghost" id="modalCancel">Cancel</button>
      <button type="submit" class="btn">Save</button>
    </div>`;
  $('#modal').classList.remove('hidden');
  $('#modalCancel').addEventListener('click', closeModal);
  $('#modalForm').onsubmit = async (e) => {
    e.preventDefault();
    try { await onSubmit(new FormData(e.target)); closeModal(); }
    catch (err) { toast(err.message, true); }
  };
}
const closeModal = () => $('#modal').classList.add('hidden');
$('#modalClose').addEventListener('click', closeModal);
$('#modal').addEventListener('click', (e) => { if (e.target.id === 'modal') closeModal(); });

function vertOptions(selected) {
  return '<option value="">—</option>' + state.verticals.map((v) =>
    `<option value="${v.id}" ${String(v.id) === String(selected) ? 'selected' : ''}>${esc(v.name)}</option>`).join('');
}
function phaseOptions(selected) {
  return state.phases.map((p) =>
    `<option value="${p.id}" ${String(p.id) === String(selected) ? 'selected' : ''}>${esc(p.name)}</option>`).join('');
}
function opts(list, selected) {
  return list.map(([v, l]) => `<option value="${v}" ${v === selected ? 'selected' : ''}>${l}</option>`).join('');
}

function openTaskModal(task) {
  const t = task || {};
  openModal(task ? 'Edit task' : 'New task', `
    <label>Title<input name="title" value="${esc(t.title || '')}" required></label>
    <div class="row2">
      <label>Phase<select name="phase_id">${phaseOptions(t.phase_id)}</select></label>
      <label>Vertical<select name="vertical_id">${vertOptions(t.vertical_id)}</select></label>
    </div>
    <div class="row2">
      <label>Status<select name="status">${opts(TASK_COLS, t.status || 'todo')}</select></label>
      <label>Priority<select name="priority">${opts([['high','High'],['medium','Medium'],['low','Low']], t.priority || 'medium')}</select></label>
    </div>
    <div class="row2">
      <label>Owner<input name="owner" value="${esc(t.owner || '')}"></label>
      <label>Due date<input type="date" name="due_date" value="${t.due_date || ''}"></label>
    </div>`,
    async (fd) => {
      const body = Object.fromEntries(fd.entries());
      if (task) await patch('tasks/' + task.id, body);
      else await post('tasks', body);
      toast('Task saved');
      await loadAll(); renderAll();
    });
}

function openLeadModal(lead) {
  const l = lead || {};
  openModal(lead ? 'Edit lead' : 'New lead', `
    <label>Name<input name="name" value="${esc(l.name || '')}" required></label>
    <div class="row2">
      <label>Phone<input name="phone" value="${esc(l.phone || '')}"></label>
      <label>College<input name="college" value="${esc(l.college || '')}"></label>
    </div>
    <div class="row2">
      <label>Vertical<select name="vertical_id">${vertOptions(l.vertical_id)}</select></label>
      <label>Source<select name="source">${opts([['','—'],['workshop','Workshop'],['webinar','Webinar'],['ambassador','Ambassador'],['social','Social']], l.source || '')}</select></label>
    </div>
    <div class="row2">
      <label>Stage<select name="stage">${opts(LEAD_STAGES, l.stage || 'new')}</select></label>
      <label>Follow-up<input type="date" name="followup_date" value="${l.followup_date || ''}"></label>
    </div>
    <label>Notes<textarea name="notes" rows="2">${esc(l.notes || '')}</textarea></label>`,
    async (fd) => {
      const body = Object.fromEntries(fd.entries());
      if (lead) await patch('leads/' + lead.id, body);
      else await post('leads', body);
      toast('Lead saved');
      await loadAll(); renderAll();
    });
}

async function removeTask(id) {
  try { await del('tasks/' + id); toast('Task deleted'); await loadAll(); renderAll(); }
  catch (e) { toast(e.message, true); }
}
async function removeLead(id) {
  try { await del('leads/' + id); toast('Lead deleted'); await loadAll(); renderAll(); }
  catch (e) { toast(e.message, true); }
}

$('#addTaskBtn').addEventListener('click', () => openTaskModal(null));
$('#addLeadBtn').addEventListener('click', () => openLeadModal(null));
$('#addPhaseBtn').addEventListener('click', () => openPhaseModal(null));
$('#editGoalBtn').addEventListener('click', openGoalModal);

/* ----------------------------- navigation + theme ----------------------------- */
$('#tabs').addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-section]');
  if (!btn) return;
  $$('#tabs button').forEach((b) => b.classList.toggle('active', b === btn));
  $$('.section').forEach((s) => s.classList.toggle('active', s.id === btn.dataset.section));
});

$('#themeToggle').addEventListener('click', () => {
  const cur = document.documentElement.getAttribute('data-theme');
  const next = cur === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('so-theme', next);
  if (state.summary) renderTargets(); // recolor chart legend swatches
});

/* ----------------------------- boot ----------------------------- */
(async function boot() {
  try {
    await loadAll();
    renderAll();
  } catch (e) {
    toast('Failed to load: ' + e.message, true);
    $('#kpiGrid').innerHTML = `<div class="kpi"><div class="label">Error</div><div class="value">⚠</div><div class="sub">${esc(e.message)}. Is the database installed?</div></div>`;
  }
})();
