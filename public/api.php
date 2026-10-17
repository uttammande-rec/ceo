<?php
/**
 * StudentOrbit JSON API — single routed front controller.
 *
 * Routes are addressed via ?r=<resource>[/<id>] and the HTTP method:
 *   GET    summary
 *   GET    phases       POST phases       PATCH phases/{id}  DELETE phases/{id}  POST phases/reorder
 *   GET    tasks        POST tasks        PATCH tasks/{id}   DELETE tasks/{id}   POST tasks/reorder
 *   GET    verticals    PATCH verticals/{id}   (name, color, targets/actuals)
 *   GET    leads        POST leads        PATCH leads/{id}   DELETE leads/{id}
 *   GET    todos        POST todos        PATCH todos/{id}   DELETE todos/{id}   POST todos/reorder
 *   GET    settings     PATCH settings    (revenue_target_month, students_target_month)
 *   GET    export/tasks.csv   GET export/leads.csv
 */

declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/auth.php';

// All API routes require an authenticated session.
require_login_api();

$TODAY = date('Y-m-d');

/* ----------------------------- helpers ----------------------------- */

function send_json($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $msg, int $code = 400): void
{
    send_json(['error' => $msg], $code);
}

function body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Pick allowed keys present in input, mapped to column => value. */
function pick(array $src, array $allowed): array
{
    $out = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $src)) {
            $out[$k] = $src[$k] === '' ? null : $src[$k];
        }
    }
    return $out;
}

function csv_out(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $fh = fopen('php://output', 'w');
    // PHP 8.4: $escape must be passed explicitly ('' = standard RFC-4180 CSV, no escape char).
    fputcsv($fh, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($fh, $row, ',', '"', '');
    }
    fclose($fh);
    exit;
}

/* ----------------------------- routing ----------------------------- */

try {
    $pdo    = Database::pdo();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $route  = $_GET['r'] ?? 'summary';
    $parts  = array_values(array_filter(explode('/', trim($route, '/'))));
    $resource = $parts[0] ?? '';
    $arg      = $parts[1] ?? null;        // id, or sub-action like "reorder"
    $id       = ctype_digit((string)$arg) ? (int)$arg : null;

    switch ($resource) {

        /* ---------------- summary ---------------- */
        case 'summary': {
            // Overall task progress
            $tot  = (int)$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
            $done = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status='done'")->fetchColumn();

            // Revenue + students (sum over verticals) and settings targets
            $v = $pdo->query("SELECT
                    SUM(revenue_target) rt, SUM(revenue_actual) ra,
                    SUM(students_target) st, SUM(students_actual) sa
                 FROM verticals")->fetch();
            $settings = $pdo->query("SELECT k,v FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

            // Leads stats
            $leadTotal  = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
            $active     = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE stage IN ('new','contacted','interested')")->fetchColumn();
            $converted  = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE stage='converted'")->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE followup_date = ?");
            $stmt->execute([$GLOBALS['TODAY']]);
            $followToday = (int)$stmt->fetchColumn();

            // Per-phase progress
            $phases = $pdo->query("
                SELECT p.id, p.name, p.position,
                       COUNT(t.id) total,
                       SUM(t.status='done') done
                FROM phases p
                LEFT JOIN tasks t ON t.phase_id = p.id
                GROUP BY p.id ORDER BY p.position")->fetchAll();
            $prevComplete = true;
            foreach ($phases as &$p) {
                $p['total'] = (int)$p['total'];
                $p['done']  = (int)$p['done'];
                $p['percent'] = $p['total'] ? round($p['done'] / $p['total'] * 100) : 0;
                $p['unlocked'] = $prevComplete;
                $prevComplete = $p['total'] > 0 && $p['done'] === $p['total'];
            }
            unset($p);

            send_json([
                'today' => $GLOBALS['TODAY'],
                'tasks' => [
                    'total' => $tot,
                    'done'  => $done,
                    'percent' => $tot ? round($done / $tot * 100) : 0,
                ],
                'revenue' => [
                    'target' => (int)($settings['revenue_target_month'] ?? $v['rt']),
                    'actual' => (int)$v['ra'],
                    'vertical_target' => (int)$v['rt'],
                ],
                'students' => [
                    'target' => (int)($settings['students_target_month'] ?? $v['st']),
                    'actual' => (int)$v['sa'],
                    'vertical_target' => (int)$v['st'],
                ],
                'leads' => [
                    'total' => $leadTotal,
                    'active' => $active,
                    'converted' => $converted,
                    'conversion_rate' => $leadTotal ? round($converted / $leadTotal * 100, 1) : 0,
                    'followups_today' => $followToday,
                ],
                'phases' => $phases,
            ]);
        }

        /* ---------------- phases ---------------- */
        case 'phases': {
            if ($method === 'GET') {
                $phases = $pdo->query("
                    SELECT p.id, p.name, p.position, p.description,
                           COUNT(t.id) total, SUM(t.status='done') done
                    FROM phases p LEFT JOIN tasks t ON t.phase_id = p.id
                    GROUP BY p.id ORDER BY p.position")->fetchAll();
                $prevComplete = true;
                foreach ($phases as &$p) {
                    $p['total'] = (int)$p['total'];
                    $p['done']  = (int)$p['done'];
                    $p['percent'] = $p['total'] ? round($p['done'] / $p['total'] * 100) : 0;
                    $p['unlocked'] = $prevComplete;
                    $prevComplete = $p['total'] > 0 && $p['done'] === $p['total'];
                }
                unset($p);
                send_json($phases);
            }
            if ($method === 'POST' && $arg === 'reorder') {
                // position is UNIQUE TINYINT. Move every row to a temporary negative
                // value first (still unique, and within TINYINT range), then assign the
                // final positive positions — avoids transient duplicate-key collisions.
                $items = body()['items'] ?? [];
                $pdo->beginTransaction();
                $pdo->exec("UPDATE phases SET position = -position");
                $stmt = $pdo->prepare("UPDATE phases SET position=? WHERE id=?");
                foreach ($items as $it) {
                    $stmt->execute([(int)($it['position'] ?? 0), (int)$it['id']]);
                }
                $pdo->commit();
                send_json(['ok' => true]);
            }
            if ($method === 'POST') {
                $d = pick(body(), ['name','description']);
                if (empty($d['name'])) { fail('name is required'); }
                // New phases go to the end of the order.
                $d['position'] = (int)$pdo->query("SELECT COALESCE(MAX(position),0)+1 FROM phases")->fetchColumn();
                $cols = array_keys($d);
                $ph   = implode(',', array_fill(0, count($cols), '?'));
                $stmt = $pdo->prepare("INSERT INTO phases (" . implode(',', $cols) . ") VALUES ($ph)");
                $stmt->execute(array_values($d));
                $newId = (int)$pdo->lastInsertId();
                send_json($pdo->query("SELECT * FROM phases WHERE id=$newId")->fetch(), 201);
            }
            if ($method === 'PATCH') {
                if (!$id) { fail('id required'); }
                // Ordering is done via /reorder; PATCH only edits name/description.
                $d = pick(body(), ['name','description']);
                if (!$d) { fail('nothing to update'); }
                $set = implode(',', array_map(fn($c) => "$c=?", array_keys($d)));
                $stmt = $pdo->prepare("UPDATE phases SET $set WHERE id=?");
                $stmt->execute([...array_values($d), $id]);
                send_json($pdo->query("SELECT * FROM phases WHERE id=$id")->fetch());
            }
            if ($method === 'DELETE') {
                if (!$id) { fail('id required'); }
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE phase_id=?");
                $cnt->execute([$id]);
                if ((int)$cnt->fetchColumn() > 0) {
                    fail('Cannot delete a phase that still has tasks. Move or delete its tasks first.', 409);
                }
                $pdo->prepare("DELETE FROM phases WHERE id=?")->execute([$id]);
                send_json(['ok' => true]);
            }
            fail('method not allowed', 405);
        }

        /* ---------------- tasks ---------------- */
        case 'tasks': {
            if ($method === 'GET') {
                $where = [];
                $args  = [];
                foreach (['vertical' => 'vertical_id', 'phase' => 'phase_id', 'status' => 'status',
                          'owner' => 'owner', 'priority' => 'priority'] as $q => $col) {
                    if (!empty($_GET[$q])) { $where[] = "$col = ?"; $args[] = $_GET[$q]; }
                }
                $sql = "SELECT * FROM tasks";
                if ($where) { $sql .= " WHERE " . implode(' AND ', $where); }
                $sql .= " ORDER BY phase_id, board_position, id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($args);
                send_json($stmt->fetchAll());
            }
            if ($method === 'POST' && $arg === 'reorder') {
                $items = body()['items'] ?? [];
                $stmt = $pdo->prepare("UPDATE tasks SET board_position=?, status=? WHERE id=?");
                foreach ($items as $it) {
                    $stmt->execute([(int)($it['position'] ?? 0), $it['status'] ?? 'todo', (int)$it['id']]);
                }
                send_json(['ok' => true]);
            }
            if ($method === 'POST') {
                $d = pick(body(), ['title','phase_id','vertical_id','status','owner','priority','due_date','board_position']);
                if (empty($d['title']))    { fail('title is required'); }
                if (empty($d['phase_id'])) { fail('phase_id is required'); }
                $cols = array_keys($d);
                $ph   = implode(',', array_fill(0, count($cols), '?'));
                $stmt = $pdo->prepare("INSERT INTO tasks (" . implode(',', $cols) . ") VALUES ($ph)");
                $stmt->execute(array_values($d));
                $newId = (int)$pdo->lastInsertId();
                send_json($pdo->query("SELECT * FROM tasks WHERE id=$newId")->fetch(), 201);
            }
            if ($method === 'PATCH') {
                if (!$id) { fail('id required'); }
                $d = pick(body(), ['title','phase_id','vertical_id','status','owner','priority','due_date','board_position']);
                if (!$d) { fail('nothing to update'); }
                $set = implode(',', array_map(fn($c) => "$c=?", array_keys($d)));
                $stmt = $pdo->prepare("UPDATE tasks SET $set WHERE id=?");
                $stmt->execute([...array_values($d), $id]);
                send_json($pdo->query("SELECT * FROM tasks WHERE id=$id")->fetch());
            }
            if ($method === 'DELETE') {
                if (!$id) { fail('id required'); }
                $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$id]);
                send_json(['ok' => true]);
            }
            fail('method not allowed', 405);
        }

        /* ---------------- verticals ---------------- */
        case 'verticals': {
            if ($method === 'GET') {
                send_json($pdo->query("SELECT * FROM verticals ORDER BY sort_order, id")->fetchAll());
            }
            if ($method === 'PATCH') {
                if (!$id) { fail('id required'); }
                $d = pick(body(), ['name','revenue_target','revenue_actual','students_target','students_actual','color']);
                if (!$d) { fail('nothing to update'); }
                $set = implode(',', array_map(fn($c) => "$c=?", array_keys($d)));
                $stmt = $pdo->prepare("UPDATE verticals SET $set WHERE id=?");
                $stmt->execute([...array_values($d), $id]);
                send_json($pdo->query("SELECT * FROM verticals WHERE id=$id")->fetch());
            }
            fail('method not allowed', 405);
        }

        /* ---------------- leads ---------------- */
        case 'leads': {
            if ($method === 'GET') {
                $rows = $pdo->query("SELECT * FROM leads ORDER BY stage, pipeline_position, id")->fetchAll();
                $total = count($rows);
                $converted = count(array_filter($rows, fn($r) => $r['stage'] === 'converted'));
                $active = count(array_filter($rows, fn($r) => in_array($r['stage'], ['new','contacted','interested'], true)));
                $followToday = count(array_filter($rows, fn($r) => $r['followup_date'] === $GLOBALS['TODAY']));
                send_json([
                    'items' => $rows,
                    'meta'  => [
                        'total' => $total,
                        'active' => $active,
                        'converted' => $converted,
                        'conversion_rate' => $total ? round($converted / $total * 100, 1) : 0,
                        'followups_today' => $followToday,
                    ],
                ]);
            }
            if ($method === 'POST') {
                $d = pick(body(), ['name','phone','college','vertical_id','source','stage','followup_date','notes','pipeline_position']);
                if (empty($d['name'])) { fail('name is required'); }
                $cols = array_keys($d);
                $ph   = implode(',', array_fill(0, count($cols), '?'));
                $stmt = $pdo->prepare("INSERT INTO leads (" . implode(',', $cols) . ") VALUES ($ph)");
                $stmt->execute(array_values($d));
                $newId = (int)$pdo->lastInsertId();
                send_json($pdo->query("SELECT * FROM leads WHERE id=$newId")->fetch(), 201);
            }
            if ($method === 'PATCH') {
                if (!$id) { fail('id required'); }
                $d = pick(body(), ['name','phone','college','vertical_id','source','stage','followup_date','notes','pipeline_position']);
                if (!$d) { fail('nothing to update'); }
                $set = implode(',', array_map(fn($c) => "$c=?", array_keys($d)));
                $stmt = $pdo->prepare("UPDATE leads SET $set WHERE id=?");
                $stmt->execute([...array_values($d), $id]);
                send_json($pdo->query("SELECT * FROM leads WHERE id=$id")->fetch());
            }
            if ($method === 'DELETE') {
                if (!$id) { fail('id required'); }
                $pdo->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
                send_json(['ok' => true]);
            }
            fail('method not allowed', 405);
        }

        /* ---------------- todos ---------------- */
        case 'todos': {
            if ($method === 'GET') {
                send_json($pdo->query("SELECT * FROM todos ORDER BY bucket, position, id")->fetchAll());
            }
            if ($method === 'POST' && $arg === 'reorder') {
                $items = body()['items'] ?? [];
                $stmt = $pdo->prepare("UPDATE todos SET position=?, bucket=? WHERE id=?");
                foreach ($items as $it) {
                    $stmt->execute([(int)($it['position'] ?? 0), $it['bucket'] ?? 'today', (int)$it['id']]);
                }
                send_json(['ok' => true]);
            }
            if ($method === 'POST') {
                $d = pick(body(), ['text','done','bucket','position']);
                if (empty($d['text'])) { fail('text is required'); }
                $cols = array_keys($d);
                $ph   = implode(',', array_fill(0, count($cols), '?'));
                $stmt = $pdo->prepare("INSERT INTO todos (" . implode(',', $cols) . ") VALUES ($ph)");
                $stmt->execute(array_values($d));
                $newId = (int)$pdo->lastInsertId();
                send_json($pdo->query("SELECT * FROM todos WHERE id=$newId")->fetch(), 201);
            }
            if ($method === 'PATCH') {
                if (!$id) { fail('id required'); }
                $d = pick(body(), ['text','done','bucket','position']);
                if (!$d) { fail('nothing to update'); }
                $set = implode(',', array_map(fn($c) => "$c=?", array_keys($d)));
                $stmt = $pdo->prepare("UPDATE todos SET $set WHERE id=?");
                $stmt->execute([...array_values($d), $id]);
                send_json($pdo->query("SELECT * FROM todos WHERE id=$id")->fetch());
            }
            if ($method === 'DELETE') {
                if (!$id) { fail('id required'); }
                $pdo->prepare("DELETE FROM todos WHERE id=?")->execute([$id]);
                send_json(['ok' => true]);
            }
            fail('method not allowed', 405);
        }

        /* ---------------- CSV export ---------------- */
        case 'export': {
            if ($arg === 'tasks.csv') {
                $rows = $pdo->query("
                    SELECT t.id, t.title, p.name phase, v.name vertical, t.status, t.owner,
                           t.priority, t.due_date
                    FROM tasks t
                    LEFT JOIN phases p ON p.id=t.phase_id
                    LEFT JOIN verticals v ON v.id=t.vertical_id
                    ORDER BY t.phase_id, t.board_position")->fetchAll();
                csv_out('tasks.csv',
                    ['ID','Title','Phase','Vertical','Status','Owner','Priority','Due date'],
                    array_map('array_values', $rows));
            }
            if ($arg === 'leads.csv') {
                $rows = $pdo->query("
                    SELECT l.id, l.name, l.phone, l.college, v.name vertical, l.source,
                           l.stage, l.followup_date, l.notes
                    FROM leads l LEFT JOIN verticals v ON v.id=l.vertical_id
                    ORDER BY l.stage, l.pipeline_position")->fetchAll();
                csv_out('leads.csv',
                    ['ID','Name','Phone','College','Vertical','Source','Stage','Follow-up','Notes'],
                    array_map('array_values', $rows));
            }
            fail('unknown export', 404);
        }

        /* ---------------- settings (headline monthly goal) ---------------- */
        case 'settings': {
            if ($method === 'GET') {
                send_json($pdo->query("SELECT k,v FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR));
            }
            if ($method === 'PATCH') {
                $d = pick(body(), ['revenue_target_month','students_target_month']);
                if (!$d) { fail('nothing to update'); }
                $stmt = $pdo->prepare(
                    "INSERT INTO settings (k,v) VALUES (?,?)
                     ON DUPLICATE KEY UPDATE v=VALUES(v)"
                );
                foreach ($d as $k => $v) {
                    $val = (int)$v;
                    if ($val < 0) { fail('values must be non-negative'); }
                    $stmt->execute([$k, (string)$val]);
                }
                send_json($pdo->query("SELECT k,v FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR));
            }
            fail('method not allowed', 405);
        }

        default:
            fail('unknown resource: ' . $resource, 404);
    }
} catch (Throwable $e) {
    fail('server error: ' . $e->getMessage(), 500);
}
