<?php
require_once __DIR__ . '/ui.php';
$student = require_student();

$c = db();

$serviceId = isset($_GET['service']) ? (int)$_GET['service'] : (int)($_POST['service_id'] ?? 0);
$service = $serviceId ? get_service_by_id($serviceId) : null;

$services = ui_active_services();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $service = $serviceId ? get_service_by_id($serviceId) : null;

    $bookedForRaw = trim((string)($_POST['booked_for'] ?? ''));
    $bookedFor = null;
    if ($bookedForRaw !== '') {
        try {
            $bookedFor = new DateTimeImmutable($bookedForRaw, new DateTimeZone('Asia/Manila'));
        } catch (Exception) {
            $error = 'Invalid appointment date/time.';
        }
    }

    if (!$error) {
        if (!$service) {
            $error = 'Please select a service window.';
        } else {
            $created = create_ticket((int)$service['id'], (int)$student['student_id'], 'online', $bookedFor);

            $stmt = $c->prepare('SELECT mobile FROM students WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $student['student_id']);
            $stmt->execute();
            $mobile = (string)($stmt->get_result()->fetch_assoc()['mobile'] ?? '');
            if ($mobile !== '') {
                $msg = 'ICCT Queue Ticket: ' . $created['ticket_no'] . ' | ' . (string)$created['service']['code'] . ' | Window ' . (string)$created['service']['window_no'];
                sms_send_simulated((int)$student['student_id'], $mobile, $msg);
            }

            redirect('/icct-queue-thesis/ticket.php?token=' . urlencode($created['token']));
        }
    }
}

ui_head('Book Online Queue');
ui_public_nav();
?>
    <main class="container py-4">
      <div class="card icct-form-card">
        <div class="card-body p-4">
          <div class="icct-panel-header">
            <h1 class="h5 mb-1">Book an Online Queue</h1>
            <div class="text-secondary small">Select one of the three service windows and optionally set an appointment time.</div>
          </div>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
          <?php endif; ?>

          <form method="post">
            <div class="mb-3">
              <label class="form-label fw-semibold">Choose Window *</label>
              <div class="row g-3">
                <?php foreach ($services as $s): ?>
                  <?php ui_window_select_card($s, $service && (int)$service['id'] === (int)$s['id']); ?>
                <?php endforeach; ?>
              </div>
              <div class="form-text mt-2">Tip: Use <a href="/icct-queue-thesis/recommend.php">Recommendation</a> if you’re not sure which window to pick.</div>
            </div>

            <div class="row g-3 align-items-end">
              <div class="col-md-5">
                <label class="form-label">Appointment (optional)</label>
                <input class="form-control" type="datetime-local" name="booked_for" />
                <div class="form-text">Leave empty to queue immediately.</div>
              </div>
              <div class="col-md-7 d-flex gap-2 flex-wrap">
                <button class="btn btn-primary btn-lg">Generate Ticket</button>
                <a class="btn btn-outline-secondary" href="/icct-queue-thesis/display.php">Now Serving</a>
                <a class="btn btn-outline-secondary" href="/icct-queue-thesis/student_portal.php">My Portal</a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </main>
<?php
ui_window_select_script();
ui_foot();
