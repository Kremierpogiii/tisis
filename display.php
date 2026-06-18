<?php
require_once __DIR__ . '/ui.php';

$c = db();
$services = ui_active_services();
$displayMinutes = display_serving_minutes();

foreach ($services as $svc) {
    process_display_auto_advance($c, (int)$svc['id']);
}

ui_head('Now Serving Display', [
    'body_class' => 'icct-display-body',
]);
?>
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
          <div class="h3 fw-bold mb-1 text-white">ICCT Queue Display</div>
          <div class="text-white-50 small">
            Each called number stays on screen for <?= (int)$displayMinutes ?> minutes, then advances automatically ·
            <span id="display-updated-at">updates every 3 seconds</span>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn btn-sm btn-outline-light" href="/icct-queue-thesis/">Login</a>
        </div>
      </div>

      <div class="row g-3" id="display-windows">
        <?php foreach ($services as $svc): ?>
          <?php
            $now = get_display_serving($c, (int)$svc['id']);
            $next = get_display_next_waiting($c, (int)$svc['id']);
            $wClass = ui_window_class((int)$svc['window_no']);
            $secondsRemaining = null;
            if ($now && !empty($now['display_until'])) {
                $until = new DateTimeImmutable((string)$now['display_until'], new DateTimeZone('Asia/Manila'));
                $secondsRemaining = max(0, $until->getTimestamp() - (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->getTimestamp());
            }
          ?>
          <div class="col-lg-4" data-service-id="<?= (int)$svc['id'] ?>">
            <div class="icct-display-window <?= h($wClass) ?>">
              <div class="icct-display-accent"></div>
              <div class="icct-display-inner">
                <div class="d-flex justify-content-between align-items-start mb-3">
                  <div>
                    <div class="text-white-50 small">Window <?= (int)$svc['window_no'] ?></div>
                    <div class="h6 mb-0 fw-semibold"><?= h((string)$svc['name']) ?></div>
                  </div>
                  <span class="badge text-bg-dark icct-code-badge"><?= h((string)$svc['code']) ?></span>
                </div>

                <div class="text-white-50 small">Now Serving</div>
                <div class="icct-display-serving mb-1 display-now-serving">
                  <?= $now ? h((string)$now['ticket_no']) : '—' ?>
                </div>
                <div class="text-white-50 small mb-3 display-timer" data-seconds="<?= $secondsRemaining !== null ? (int)$secondsRemaining : '' ?>">
                  <?php if ($secondsRemaining !== null): ?>
                    On screen for <?= (int)$displayMinutes ?> min · <?= (int)ceil($secondsRemaining / 60) ?> min left
                  <?php else: ?>
                    Waiting for next call
                  <?php endif; ?>
                </div>

                <div class="text-white-50 small">Next in queue</div>
                <div class="display-next-queue">
                  <?php if (!$next): ?>
                    <div class="text-white-50">No waiting tickets</div>
                  <?php else: ?>
                    <ul class="list-unstyled mb-0 mt-1">
                      <?php foreach ($next as $n): ?>
                        <li class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-25">
                          <span class="fw-semibold"><?= h((string)$n['ticket_no']) ?></span>
                          <span class="text-white-50 small"><?= h((string)($n['booked_for'] ?: $n['created_at'])) ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <script>
      const displayMinutes = <?= (int)$displayMinutes ?>;

      function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
      }

      function formatTimer(seconds) {
        if (seconds === null || seconds === undefined || seconds === '') {
          return 'Waiting for next call';
        }
        const mins = Math.max(0, Math.ceil(Number(seconds) / 60));
        return `On screen for ${displayMinutes} min · ${mins} min left`;
      }

      function renderNext(next) {
        if (!next.length) {
          return '<div class="text-white-50">No waiting tickets</div>';
        }
        const items = next.map((n) => {
          const when = n.booked_for || n.created_at;
          return `<li class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-25">
            <span class="fw-semibold">${esc(n.ticket_no)}</span>
            <span class="text-white-50 small">${esc(when)}</span>
          </li>`;
        }).join('');
        return `<ul class="list-unstyled mb-0 mt-1">${items}</ul>`;
      }

      function tickLocalTimers() {
        document.querySelectorAll('.display-timer[data-seconds]').forEach((el) => {
          const raw = el.getAttribute('data-seconds');
          if (raw === '' || raw === null) {
            el.textContent = 'Waiting for next call';
            return;
          }
          let seconds = Number(raw);
          if (Number.isNaN(seconds)) return;
          seconds = Math.max(0, seconds - 1);
          el.setAttribute('data-seconds', String(seconds));
          el.textContent = formatTimer(seconds);
        });
      }

      async function refreshDisplay() {
        try {
          const r = await fetch('/icct-queue-thesis/api/display_snapshot.php');
          const data = await r.json();
          if (!data.ok) return;

          data.windows.forEach((w) => {
            const col = document.querySelector(`[data-service-id="${w.service_id}"]`);
            if (!col) return;
            const serving = col.querySelector('.display-now-serving');
            const timer = col.querySelector('.display-timer');
            const nextBox = col.querySelector('.display-next-queue');
            if (serving) serving.textContent = w.now_serving || '—';
            if (timer) {
              const seconds = w.seconds_remaining ?? '';
              timer.setAttribute('data-seconds', seconds === null ? '' : String(seconds));
              timer.textContent = formatTimer(seconds);
            }
            if (nextBox) nextBox.innerHTML = renderNext(w.next);
          });

          const stamp = document.getElementById('display-updated-at');
          if (stamp) stamp.textContent = 'updated ' + data.updated_at;
        } catch (e) {}
      }

      refreshDisplay();
      setInterval(refreshDisplay, 3000);
      setInterval(tickLocalTimers, 1000);
    </script>
<?php ui_foot(); ?>
