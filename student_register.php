<?php
require_once __DIR__ . '/ui.php';
start_session();

if (!empty($_SESSION['student_id'])) {
    redirect('/icct-queue-thesis/student_portal.php');
}

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentNo = trim((string)($_POST['student_no'] ?? ''));
    $fullname = trim((string)($_POST['fullname'] ?? ''));
    $mobile = trim((string)($_POST['mobile'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($studentNo === '' || $fullname === '' || $password === '') {
        $error = 'Please fill out all required fields.';
    } else {
        try {
            $c = db();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $createdAt = now_ts();
            $stmt = $c->prepare('INSERT INTO students (student_no, fullname, mobile, password_hash, created_at) VALUES (?,?,?,?,?)');
            $stmt->bind_param('sssss', $studentNo, $fullname, $mobile, $hash, $createdAt);
            $stmt->execute();
            $ok = 'Registration successful. You can now log in.';
        } catch (mysqli_sql_exception $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                $error = 'Student number already exists.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

ui_head('Student Registration');
ui_public_nav();
?>
    <main class="container py-4" style="max-width: 560px;">
      <div class="card icct-form-card">
        <div class="card-body p-4">
          <div class="icct-panel-header">
            <h1 class="h5 mb-1">Student Registration</h1>
            <div class="text-secondary small">Create an account to book online queue tickets</div>
          </div>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
          <?php elseif ($ok): ?>
            <div class="alert alert-success"><?= h($ok) ?></div>
          <?php endif; ?>

          <form method="post" class="vstack gap-3">
            <div>
              <label class="form-label">Student No *</label>
              <input class="form-control" name="student_no" required />
            </div>
            <div>
              <label class="form-label">Full Name *</label>
              <input class="form-control" name="fullname" required />
            </div>
            <div>
              <label class="form-label">Mobile (for SMS)</label>
              <input class="form-control" name="mobile" placeholder="09xxxxxxxxx" />
            </div>
            <div>
              <label class="form-label">Password *</label>
              <input class="form-control" type="password" name="password" required />
            </div>
            <button class="btn btn-primary btn-lg">Create account</button>
          </form>

          <div class="mt-3 small text-secondary">
            Already have an account? <a href="/icct-queue-thesis/">Log in</a>
          </div>
        </div>
      </div>
    </main>
<?php ui_foot(); ?>
