<?php
require_once __DIR__ . '/ui.php';
$student = require_student();

$c = db();

$nodeId = isset($_GET['node']) ? (int)$_GET['node'] : 0;
if ($nodeId <= 0) {
    $root = $c->query('SELECT id, prompt FROM recommendation_nodes WHERE parent_id IS NULL ORDER BY id ASC LIMIT 1')->fetch_assoc();
    $nodeId = $root ? (int)$root['id'] : 0;
}

$stmt = $c->prepare('SELECT id, prompt, service_id FROM recommendation_nodes WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $nodeId);
$stmt->execute();
$node = $stmt->get_result()->fetch_assoc();

if (!$node) {
    redirect('/icct-queue-thesis/recommend.php');
}

$service = null;
if (!empty($node['service_id'])) {
    $service = get_service_by_id((int)$node['service_id']);
}

$childrenStmt = $c->prepare('SELECT id, prompt, service_id FROM recommendation_nodes WHERE parent_id = ? ORDER BY sort_order, id');
$childrenStmt->bind_param('i', $nodeId);
$childrenStmt->execute();
$children = $childrenStmt->get_result()->fetch_all(MYSQLI_ASSOC);

ui_head('Service Recommendation');
ui_public_nav();
?>
    <main class="container py-4" style="max-width: 900px;">
      <div class="card icct-panel">
        <div class="card-body p-4">
          <div class="icct-panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h1 class="h5 mb-1">Smart Service Recommendation</h1>
              <div class="small text-secondary">We’ll guide you to the right window</div>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="/icct-queue-thesis/recommend.php">Start over</a>
          </div>

          <div class="text-secondary small mb-2">Question</div>
          <div class="h5 mb-4"><?= h((string)$node['prompt']) ?></div>

          <?php if ($service): ?>
            <?php $wClass = ui_window_class((int)$service['window_no']); ?>
            <div class="icct-window-card <?= h($wClass) ?> mb-4">
              <div class="d-flex gap-3 align-items-start mb-3">
                <span class="icct-window-badge <?= h($wClass) ?>">W<?= (int)$service['window_no'] ?></span>
                <div>
                  <div class="fw-bold">Recommended: Window <?= (int)$service['window_no'] ?></div>
                  <div class="text-secondary"><?= h((string)$service['name']) ?></div>
                  <span class="badge text-bg-primary icct-code-badge mt-2"><?= h((string)$service['code']) ?></span>
                </div>
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary" href="/icct-queue-thesis/book.php?service=<?= (int)$service['id'] ?>">Book Online Queue</a>
                <a class="btn btn-outline-secondary" href="/icct-queue-thesis/display.php">View Now Serving</a>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($children): ?>
            <div class="text-secondary small mb-2">Choose one</div>
            <?php foreach ($children as $ch): ?>
              <a class="icct-recommend-option" href="/icct-queue-thesis/recommend.php?node=<?= (int)$ch['id'] ?>">
                <span><?= h((string)$ch['prompt']) ?></span>
                <span class="badge text-bg-light">→</span>
              </a>
            <?php endforeach; ?>
          <?php elseif (!$service): ?>
            <div class="text-secondary">
              No more options under this choice.
              <a href="/icct-queue-thesis/recommend.php">Go back to start</a>.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
<?php ui_foot(); ?>
