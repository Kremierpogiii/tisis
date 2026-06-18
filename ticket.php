<?php
require_once __DIR__ . '/ui.php';
start_session();

global $BASE_URL;

$token = trim((string)($_GET['token'] ?? ''));
$ticketNo = trim((string)($_GET['t'] ?? ''));

$c = db();

if ($token !== '') {
    $stmt = $c->prepare("SELECT qt.ticket_no, qt.status, qt.source, qt.created_at, qt.booked_for, qt.token, s.name AS service_name, s.code AS service_code, s.window_no,
      st.fullname, st.student_no
      FROM queue_tickets qt
      JOIN services s ON s.id = qt.service_id
      LEFT JOIN students st ON st.id = qt.student_id
      WHERE qt.token = ?
      LIMIT 1");
    $stmt->bind_param('s', $token);
} elseif ($ticketNo !== '') {
    $stmt = $c->prepare("SELECT qt.ticket_no, qt.status, qt.source, qt.created_at, qt.booked_for, qt.token, s.name AS service_name, s.code AS service_code, s.window_no,
      st.fullname, st.student_no
      FROM queue_tickets qt
      JOIN services s ON s.id = qt.service_id
      LEFT JOIN students st ON st.id = qt.student_id
      WHERE qt.ticket_no = ?
      LIMIT 1");
    $stmt->bind_param('s', $ticketNo);
} else {
    redirect(login_url('student'));
}

$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
if (!$t) {
    redirect(login_url('student'));
}

$publicUrl = $BASE_URL . '/ticket.php?token=' . urlencode((string)($t['token'] ?? $token));
$wClass = ui_window_class((int)$t['window_no']);

ui_head('Ticket ' . (string)$t['ticket_no'], [
    'extra_head' => '<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>',
]);
ui_public_nav();
?>
    <main class="container py-4" style="max-width: 900px;">
      <div class="row g-3">
        <div class="col-md-7">
          <div class="card icct-panel">
            <div class="card-body p-4">
              <div class="icct-window-card <?= h($wClass) ?> mb-4">
                <div class="d-flex gap-3 align-items-start">
                  <span class="icct-window-badge <?= h($wClass) ?>">W<?= (int)$t['window_no'] ?></span>
                  <div>
                    <div class="text-secondary small">Service Window</div>
                    <div class="fw-bold">Window <?= (int)$t['window_no'] ?> — <?= h((string)$t['service_name']) ?></div>
                    <span class="badge text-bg-primary icct-code-badge mt-2"><?= h((string)$t['service_code']) ?></span>
                  </div>
                </div>
              </div>

              <div class="text-secondary small">Ticket Number</div>
              <div class="icct-ticket-number mb-3"><?= h((string)$t['ticket_no']) ?></div>

              <div class="d-flex gap-2 flex-wrap mb-3">
                <span class="badge text-bg-secondary"><?= h((string)$t['status']) ?></span>
                <span class="badge text-bg-light text-dark">Source: <?= h((string)$t['source']) ?></span>
              </div>

              <div class="small text-secondary">
                Student: <?= h((string)($t['fullname'] ?? '')) ?> <?= $t['student_no'] ? '(' . h((string)$t['student_no']) . ')' : '' ?><br />
                Created: <?= h((string)$t['created_at']) ?><br />
                Appointment: <?= h((string)($t['booked_for'] ?: 'N/A')) ?>
              </div>

              <div class="mt-4 d-flex gap-2 flex-wrap">
                <a class="btn btn-primary" href="/icct-queue-thesis/display.php">Check Now Serving</a>
                <a class="btn btn-outline-secondary" href="/icct-queue-thesis/book.php">Book Another</a>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-5">
          <div class="card icct-panel">
            <div class="card-body p-4 text-center">
              <div class="text-secondary small mb-2">QR Code</div>
              <div id="qrcode" class="d-flex justify-content-center"></div>
              <div class="small text-secondary mt-3">
                Scan to open this ticket on another device.
                <div class="mt-1"><code><?= h($publicUrl) ?></code></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <script>
      new QRCode(document.getElementById("qrcode"), {
        text: <?= json_encode($publicUrl) ?>,
        width: 220,
        height: 220
      });
    </script>
<?php ui_foot(); ?>
