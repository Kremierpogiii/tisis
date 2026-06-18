<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

function ui_window_class(int $windowNo): string {
    $n = max(1, min(3, $windowNo));
    return 'window-' . $n;
}

function ui_public_stats(): array {
    $c = db();
    return [
        'students' => (int)$c->query('SELECT COUNT(*) AS c FROM students')->fetch_assoc()['c'],
        'waiting' => (int)$c->query("SELECT COUNT(*) AS c FROM queue_tickets WHERE status='waiting'")->fetch_assoc()['c'],
        'called' => (int)$c->query("SELECT COUNT(*) AS c FROM queue_tickets WHERE status IN ('called','serving')")->fetch_assoc()['c'],
        'completed_today' => (int)$c->query("SELECT COUNT(*) AS c FROM queue_tickets WHERE status='completed' AND DATE(completed_at)=CURDATE()")->fetch_assoc()['c'],
    ];
}

function ui_waiting_per_service(): array {
    $c = db();
    $rows = $c->query("SELECT service_id, COUNT(*) AS c
      FROM queue_tickets
      WHERE status='waiting'
      GROUP BY service_id")->fetch_all(MYSQLI_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $map[(int)$row['service_id']] = (int)$row['c'];
    }
    return $map;
}

function ui_active_services(): array {
    $c = db();
    return $c->query('SELECT id, code, name, window_no FROM services WHERE is_active=1 ORDER BY window_no, name')->fetch_all(MYSQLI_ASSOC);
}

function ui_head(string $title, array $opts = []): void {
    $bodyClass = (string)($opts['body_class'] ?? 'bg-light icct-app');
    $extraHead = (string)($opts['extra_head'] ?? '');
    echo '<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>' . h($title) . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/icct-queue-thesis/assets/app.css" rel="stylesheet">
    ' . $extraHead . '
  </head>
  <body class="' . h($bodyClass) . '">';
}

function ui_login_nav(): void {
    echo '<nav class="navbar navbar-expand-lg navbar-dark icct-navbar">
      <div class="container">
        <span class="navbar-brand fw-semibold">ICCT Hybrid Queue</span>
      </div>
    </nav>';
}

function ui_public_nav(): void {
    start_session();
    $studentLoggedIn = !empty($_SESSION['student_id']);
    echo '<nav class="navbar navbar-expand-lg navbar-dark icct-navbar">
      <div class="container">
        <a class="navbar-brand fw-semibold" href="/icct-queue-thesis/student_portal.php">ICCT Hybrid Queue</a>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn btn-outline-light btn-sm" href="/icct-queue-thesis/display.php">Display</a>
          <a class="btn btn-outline-light btn-sm" href="/icct-queue-thesis/kiosk.php">RFID Kiosk</a>';
    if ($studentLoggedIn) {
        echo '<a class="btn btn-light btn-sm" href="/icct-queue-thesis/student_portal.php">Home</a>
          <a class="btn btn-outline-light btn-sm" href="/icct-queue-thesis/book.php">Book Queue</a>
          <a class="btn btn-outline-light btn-sm" href="/icct-queue-thesis/recommend.php">Recommendation</a>
          <a class="btn btn-outline-light btn-sm" href="/icct-queue-thesis/logout.php">Logout</a>';
    } else {
        echo '<a class="btn btn-light btn-sm" href="/icct-queue-thesis/">Login</a>';
    }
    echo '</div>
      </div>
    </nav>';
}

function ui_admin_nav(string $active = 'dashboard'): void {
    $dashClass = $active === 'dashboard' ? 'btn-light' : 'btn-outline-light';
    $analyticsClass = $active === 'analytics' ? 'btn-light' : 'btn-outline-light';
    $studentsClass = $active === 'students' ? 'btn-light' : 'btn-outline-light';
    echo '<nav class="navbar navbar-expand-lg navbar-dark icct-navbar">
      <div class="container">
        <a class="navbar-brand fw-semibold" href="/icct-queue-thesis/admin/dashboard.php">ICCT Admin</a>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn ' . $dashClass . ' btn-sm" href="/icct-queue-thesis/admin/dashboard.php">Dashboard</a>
          <a class="btn ' . $studentsClass . ' btn-sm" href="/icct-queue-thesis/admin/students.php">RFID Students</a>
          <a class="btn btn-outline-light btn-sm" href="/icct-queue-thesis/kiosk.php">Kiosk</a>
          <a class="btn btn-outline-light btn-sm" href="/icct-queue-thesis/display.php">Display</a>
          <a class="btn ' . $analyticsClass . ' btn-sm" href="/icct-queue-thesis/admin/analytics.php">Analytics</a>
          <a class="btn btn-outline-light btn-sm" href="/icct-queue-thesis/admin/logout.php">Logout</a>
        </div>
      </div>
    </nav>';
}

function ui_stat_cards(array $stats): void {
    $items = [
        ['label' => 'Waiting', 'value' => $stats['waiting'], 'color' => '#e65100'],
        ['label' => 'Called / Serving', 'value' => $stats['called'], 'color' => '#1565c0'],
        ['label' => 'Completed Today', 'value' => $stats['completed_today'], 'color' => '#00897b'],
        ['label' => 'Registered Students', 'value' => $stats['students'], 'color' => '#5e35b1'],
    ];
    echo '<div class="row g-3 mb-4">';
    foreach ($items as $item) {
        echo '<div class="col-6 col-lg-3">
          <div class="card icct-stat-card h-100">
            <div class="card-body">
              <div class="icct-stat-label">' . h($item['label']) . '</div>
              <div class="icct-stat-value" style="color:' . h($item['color']) . '">' . (int)$item['value'] . '</div>
            </div>
          </div>
        </div>';
    }
    echo '</div>';
}

function ui_window_card(array $svc, array $opts = []): void {
    $waiting = isset($opts['waiting']) ? (int)$opts['waiting'] : null;
    $showBook = (bool)($opts['show_book'] ?? true);
    $selected = (bool)($opts['selected'] ?? false);
    $wClass = ui_window_class((int)$svc['window_no']);
    $id = (int)$svc['id'];
    $selectedClass = $selected ? ' selected' : '';

    echo '<div class="col-md-4">
      <div class="icct-window-card ' . h($wClass) . ' h-100">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="d-flex gap-3 align-items-start">
            <span class="icct-window-badge ' . h($wClass) . '">W' . (int)$svc['window_no'] . '</span>
            <div>
              <div class="fw-bold">Window ' . (int)$svc['window_no'] . '</div>
              <div class="text-secondary small">' . h((string)$svc['name']) . '</div>
            </div>
          </div>
          <span class="badge text-bg-primary icct-code-badge">' . h((string)$svc['code']) . '</span>
        </div>';

    if ($waiting !== null) {
        echo '<div class="mt-3">
          <span class="icct-waiting-pill">
            <span class="text-secondary">Waiting</span>
            <strong>' . $waiting . '</strong>
          </span>
        </div>';
    }

    if ($showBook) {
        echo '<div class="mt-3 d-flex gap-2 flex-wrap">
          <a class="btn btn-sm btn-primary" href="/icct-queue-thesis/book.php?service=' . $id . '">Book Online Queue</a>
          <a class="btn btn-sm btn-outline-secondary" href="/icct-queue-thesis/display.php">Now Serving</a>
        </div>';
    }

    echo '</div>
    </div>';
}

function ui_window_select_card(array $svc, bool $selected = false): void {
    $wClass = ui_window_class((int)$svc['window_no']);
    $id = (int)$svc['id'];
  echo '<div class="col-md-4">
      <label class="icct-window-select w-100' . ($selected ? ' selected' : '') . '" for="service-' . $id . '">
        <input type="radio" name="service_id" id="service-' . $id . '" value="' . $id . '"' . ($selected ? ' checked' : '') . ' required />
        <div class="icct-window-card ' . h($wClass) . ' h-100">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="d-flex gap-3 align-items-start">
              <span class="icct-window-badge ' . h($wClass) . '">W' . (int)$svc['window_no'] . '</span>
              <div>
                <div class="fw-bold">Window ' . (int)$svc['window_no'] . '</div>
                <div class="text-secondary small">' . h((string)$svc['name']) . '</div>
              </div>
            </div>
            <span class="badge text-bg-primary icct-code-badge">' . h((string)$svc['code']) . '</span>
          </div>
        </div>
      </label>
    </div>';
}

function ui_foot(): void {
    echo '</body></html>';
}

function ui_window_select_script(): void {
    echo '<script>
      document.querySelectorAll(".icct-window-select input").forEach((input) => {
        input.addEventListener("change", () => {
          document.querySelectorAll(".icct-window-select").forEach((el) => el.classList.remove("selected"));
          input.closest(".icct-window-select")?.classList.add("selected");
        });
      });
    </script>';
}
