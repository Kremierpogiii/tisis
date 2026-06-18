<?php
require_once __DIR__ . '/ui.php';
$student = require_student();

$c = db();

$activeStmt = $c->prepare("SELECT qt.id, qt.ticket_no, qt.status, qt.created_at, qt.booked_for, s.name AS service_name, s.window_no, s.code AS service_code
  FROM queue_tickets qt
  JOIN services s ON s.id = qt.service_id
  WHERE qt.student_id = ? AND qt.status IN ('waiting','called','serving')
  ORDER BY qt.id DESC");
$activeStmt->bind_param('i', $student['student_id']);
$activeStmt->execute();
$activeTickets = $activeStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$historyStmt = $c->prepare("SELECT qt.id, qt.ticket_no, qt.status, qt.created_at, qt.booked_for, s.name AS service_name, s.window_no, s.code AS service_code
  FROM queue_tickets qt
  JOIN services s ON s.id = qt.service_id
  WHERE qt.student_id = ? AND qt.status IN ('completed','cancelled','expired')
  ORDER BY qt.id DESC
  LIMIT 10");
$historyStmt->bind_param('i', $student['student_id']);
$historyStmt->execute();
$historyTickets = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stats = ui_public_stats();
$services = ui_active_services();
$waitingMap = ui_waiting_per_service();

ui_head('Student Dashboard');
ui_public_nav();
?>
    <main class="container py-4">
      <div class="card icct-hero mb-4">
        <div class="card-body p-4 p-lg-5">
          <div class="row align-items-center g-4">
            <div class="col-lg-8">
              <div class="small fw-semibold text-white-50 mb-2">WELCOME, <?= h(strtoupper($student['fullname'])) ?></div>
              <h1 class="h2 fw-bold mb-3">IoT-based Hybrid Queue Management</h1>
              <p class="lead mb-0">
                Student No: <?= h($student['student_no']) ?> — Book online, track your tickets, and view live window status.
              </p>
            </div>
            <div class="col-lg-4">
              <div class="d-flex flex-column gap-2">
                <a class="btn btn-light btn-lg fw-semibold" href="/icct-queue-thesis/book.php">Book Online Queue</a>
                <a class="btn btn-outline-light" href="/icct-queue-thesis/recommend.php">Get Service Recommendation</a>
                <a class="btn btn-outline-light" href="/icct-queue-thesis/display.php">Now Serving Display</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php ui_stat_cards($stats); ?>

      <div class="card icct-panel mb-4">
        <div class="card-body p-4">
          <div class="icct-panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h2 class="h6 mb-1">Your Active Queue</h2>
              <div class="small text-secondary">Tickets still waiting, called, or being served — completed tickets are removed automatically</div>
            </div>
            <a class="btn btn-sm btn-primary" href="/icct-queue-thesis/book.php">Book Online Queue</a>
          </div>

          <div id="student-active-queue">
            <?php if (!$activeTickets): ?>
              <div class="text-secondary">No active tickets. Book online to get a queue number for any of the three windows.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Ticket</th>
                      <th>Service</th>
                      <th>Window</th>
                      <th>Status</th>
                      <th>When</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($activeTickets as $t): ?>
                      <?php $wClass = ui_window_class((int)$t['window_no']); ?>
                      <tr data-ticket="<?= h($t['ticket_no']) ?>">
                        <td class="fw-semibold"><?= h($t['ticket_no']) ?></td>
                        <td><?= h($t['service_name']) ?></td>
                        <td>
                          <span class="icct-window-badge <?= h($wClass) ?>" style="width:1.75rem;height:1.75rem;font-size:0.75rem"><?= (int)$t['window_no'] ?></span>
                        </td>
                        <td><span class="badge text-bg-secondary"><?= h($t['status']) ?></span></td>
                        <td class="small text-secondary"><?= h($t['booked_for'] ?: $t['created_at']) ?></td>
                        <td class="text-end">
                          <a class="btn btn-sm btn-outline-primary" href="/icct-queue-thesis/ticket.php?t=<?= h($t['ticket_no']) ?>">View</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card icct-panel mb-4">
        <div class="card-body p-4">
          <div class="icct-panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h2 class="h6 mb-1">Live Queue per Window</h2>
              <div class="small text-secondary">Three service windows — book online or tap RFID at the kiosk</div>
            </div>
            <a class="btn btn-sm btn-primary" href="/icct-queue-thesis/book.php">Book Online Queue</a>
          </div>
          <div class="row g-3">
            <?php foreach ($services as $svc): ?>
              <?php ui_window_card($svc, ['waiting' => $waitingMap[(int)$svc['id']] ?? 0]); ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <?php if ($historyTickets): ?>
        <div class="card icct-panel mb-4">
          <div class="card-body p-4">
            <div class="icct-panel-header">
              <h2 class="h6 mb-0">Recent History</h2>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Ticket</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th>When</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($historyTickets as $t): ?>
                    <tr>
                      <td class="fw-semibold"><?= h($t['ticket_no']) ?></td>
                      <td><?= h($t['service_name']) ?></td>
                      <td><span class="badge text-bg-light text-dark"><?= h($t['status']) ?></span></td>
                      <td class="small text-secondary"><?= h($t['booked_for'] ?: $t['created_at']) ?></td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="/icct-queue-thesis/ticket.php?t=<?= h($t['ticket_no']) ?>">View</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <a class="icct-action-card featured h-100" href="/icct-queue-thesis/book.php">
            <div class="d-flex gap-3 align-items-start">
              <span class="icct-action-icon">Q</span>
              <div>
                <div class="fw-semibold">Online Queue Booking</div>
                <div class="small text-secondary mt-1">Pick a window, get your ticket, and track status here.</div>
              </div>
            </div>
          </a>
        </div>
        <div class="col-md-4">
          <a class="icct-action-card h-100" href="/icct-queue-thesis/recommend.php">
            <div class="d-flex gap-3 align-items-start">
              <span class="icct-action-icon" style="background:#00897b">?</span>
              <div>
                <div class="fw-semibold">Smart Recommendation</div>
                <div class="small text-secondary mt-1">Not sure which window? Answer a few questions and we'll route you.</div>
              </div>
            </div>
          </a>
        </div>
        <div class="col-md-4">
          <a class="icct-action-card h-100" href="/icct-queue-thesis/display.php">
            <div class="d-flex gap-3 align-items-start">
              <span class="icct-action-icon" style="background:#e65100">TV</span>
              <div>
                <div class="fw-semibold">Now Serving Display</div>
                <div class="small text-secondary mt-1">Live board for all three windows — updates in real time.</div>
              </div>
            </div>
          </a>
        </div>
      </div>
    </main>

    <script>
      async function refreshStudentQueue() {
        try {
          const r = await fetch('/icct-queue-thesis/api/student_queue.php');
          const data = await r.json();
          if (!data.ok) return;

          const box = document.getElementById('student-active-queue');
          if (!data.active.length) {
            box.innerHTML = '<div class="text-secondary">No active tickets. Book online to get a queue number for any of the three windows.</div>';
            return;
          }

          const rows = data.active.map((t) => {
            const when = t.booked_for || t.created_at;
            return `<tr data-ticket="${t.ticket_no}">
              <td class="fw-semibold">${t.ticket_no}</td>
              <td>${t.service_name}</td>
              <td><span class="icct-window-badge window-${t.window_no}" style="width:1.75rem;height:1.75rem;font-size:0.75rem">${t.window_no}</span></td>
              <td><span class="badge text-bg-secondary">${t.status}</span></td>
              <td class="small text-secondary">${when}</td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/icct-queue-thesis/ticket.php?t=${encodeURIComponent(t.ticket_no)}">View</a></td>
            </tr>`;
          }).join('');

          box.innerHTML = `<div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr><th>Ticket</th><th>Service</th><th>Window</th><th>Status</th><th>When</th><th></th></tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
          </div>`;
        } catch (e) {}
      }

      refreshStudentQueue();
      setInterval(refreshStudentQueue, 5000);
    </script>
<?php ui_foot(); ?>
