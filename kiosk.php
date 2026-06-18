<?php
require_once __DIR__ . '/ui.php';

$services = ui_active_services();

ui_head('RFID Kiosk');
ui_public_nav();
?>
    <main class="container py-4">
      <div class="card icct-hero mb-4">
        <div class="card-body p-4">
          <h1 class="h4 mb-2">Arduino UNO RFID Kiosk</h1>
          <p class="lead mb-0">Scan your card at the physical kiosk. The PC bridge sends your tap to this website and creates a live queue ticket.</p>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-lg-6">
          <div class="card icct-panel h-100">
            <div class="card-body p-4">
              <h2 class="h6 mb-3">Bridge Status</h2>
              <div id="bridge-status" class="alert alert-secondary mb-0">Checking bridge...</div>
              <div class="small text-secondary mt-3" id="bridge-details"></div>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card icct-panel h-100">
            <div class="card-body p-4">
              <h2 class="h6 mb-3">How to use the kiosk</h2>
              <ol class="mb-0 small text-secondary">
                <li>Scan your RFID card on the reader.</li>
                <li>Press <strong>S1</strong> Prospectus, <strong>S2</strong> SOG, or <strong>S3</strong> Letter.</li>
                <li>Press <strong>S4 (Confirm)</strong> on the keypad.</li>
                <li>LCD shows your ticket number and window.</li>
                <li>Wait for your number on the <a href="/icct-queue-thesis/display.php">Now Serving Display</a>.</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <div class="card icct-panel mb-4">
        <div class="card-body p-4">
          <div class="icct-panel-header">
            <h2 class="h6 mb-1">Service Windows (Keypad mapping)</h2>
            <div class="small text-secondary">Arduino keypad choice → website service</div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Keypad</th>
                  <th>Document</th>
                  <th>Service Code</th>
                  <th>Window</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($services as $svc): ?>
                  <tr>
                    <td>
                      <?php
                        $key = match ((string)$svc['code']) {
                            'PROS' => 'S1',
                            'REG' => 'S2',
                            'ENR' => 'S3',
                            default => '—',
                        };
                        echo h($key);
                      ?>
                    </td>
                    <td><?= h((string)$svc['name']) ?></td>
                    <td><span class="badge text-bg-primary"><?= h((string)$svc['code']) ?></span></td>
                    <td>Window <?= (int)$svc['window_no'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card icct-panel">
        <div class="card-body p-4">
          <h2 class="h6 mb-3">PC Setup (run once per session)</h2>
          <ol class="small text-secondary mb-3">
            <li>Start <strong>XAMPP</strong> — Apache + MySQL.</li>
            <li>Upload <code>Final.ino</code> to Arduino UNO (9600 baud).</li>
            <li>Double-click <code>C:\xampp\htdocs\icct-queue-thesis\hardware\START_BRIDGE.bat</code></li>
            <li>Leave the bridge window open while students use the kiosk.</li>
          </ol>
          <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="/icct-queue-thesis/display.php">Open Now Serving Display</a>
            <a class="btn btn-outline-secondary" href="/icct-queue-thesis/">Login</a>
          </div>
        </div>
      </div>
    </main>

    <script>
      async function refreshBridge() {
        try {
          const r = await fetch('/icct-queue-thesis/api/bridge_status.php');
          const data = await r.json();
          const box = document.getElementById('bridge-status');
          const details = document.getElementById('bridge-details');

          if (data.connected) {
            box.className = 'alert alert-success mb-0';
            box.textContent = data.message || 'Bridge connected';
          } else {
            box.className = 'alert alert-warning mb-0';
            box.textContent = data.message || 'Bridge not running';
          }

          let lines = [];
          if (data.updated_at) lines.push('Updated: ' + data.updated_at);
          if (data.last_ticket) lines.push('Last ticket: ' + data.last_ticket + ' (Window ' + data.last_window + ')');
          if (data.last_student) lines.push('Student: ' + data.last_student);
          if (data.last_uid) lines.push('RFID UID: ' + data.last_uid);
          if (data.last_error) lines.push('Last error: ' + data.last_error);
          details.textContent = lines.join(' · ');
        } catch (e) {
          document.getElementById('bridge-status').className = 'alert alert-danger mb-0';
          document.getElementById('bridge-status').textContent = 'Cannot read bridge status';
        }
      }
      refreshBridge();
      setInterval(refreshBridge, 3000);
    </script>
<?php ui_foot(); ?>
