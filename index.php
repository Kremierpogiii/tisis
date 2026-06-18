<?php
require_once __DIR__ . '/ui.php';
start_session();

if (!empty($_SESSION['admin_id'])) {
    redirect('/icct-queue-thesis/admin/dashboard.php');
}
if (!empty($_SESSION['student_id'])) {
    redirect('/icct-queue-thesis/student_portal.php');
}

$role = (string)($_GET['role'] ?? $_POST['role'] ?? 'student');
$role = $role === 'admin' ? 'admin' : 'student';

$studentError = '';
$adminError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginRole = (string)($_POST['role'] ?? 'student');

    if ($loginRole === 'admin') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $adminError = 'Enter username and password.';
            $role = 'admin';
        } else {
            $c = db();
            $stmt = $c->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if ($row && password_verify($password, (string)$row['password_hash'])) {
                $_SESSION['admin_id'] = (int)$row['id'];
                $_SESSION['admin_username'] = (string)$row['username'];
                redirect('/icct-queue-thesis/admin/dashboard.php');
            }
            $adminError = 'Invalid admin login.';
            $role = 'admin';
        }
    } else {
        $studentNo = trim((string)($_POST['student_no'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($studentNo === '' || $password === '') {
            $studentError = 'Please enter your student number and password.';
            $role = 'student';
        } else {
            $c = db();
            $stmt = $c->prepare('SELECT id, student_no, fullname, password_hash FROM students WHERE student_no = ? LIMIT 1');
            $stmt->bind_param('s', $studentNo);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if ($row && password_verify($password, (string)$row['password_hash'])) {
                $_SESSION['student_id'] = (int)$row['id'];
                $_SESSION['student_no'] = (string)$row['student_no'];
                $_SESSION['fullname'] = (string)$row['fullname'];
                redirect('/icct-queue-thesis/student_portal.php');
            }
            $studentError = 'Invalid student login.';
            $role = 'student';
        }
    }
}

ui_head('Login — ICCT Hybrid Queue System', ['body_class' => 'bg-light icct-app icct-login-body']);
ui_login_nav();
?>
    <main class="container py-5" style="max-width: 520px;">
      <div class="text-center mb-4">
        <div class="small fw-semibold text-secondary mb-2">ICCT QUEUE THESIS SYSTEM</div>
        <h1 class="h4 fw-bold mb-1">Sign in to continue</h1>
        <p class="text-secondary small mb-0">Choose your role and log in to access the system.</p>
      </div>

      <div class="card icct-form-card">
        <div class="card-body p-4">
          <ul class="nav nav-pills nav-fill icct-login-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
              <a class="nav-link<?= $role === 'student' ? ' active' : '' ?>" href="?role=student">Student Login</a>
            </li>
            <li class="nav-item" role="presentation">
              <a class="nav-link<?= $role === 'admin' ? ' active' : '' ?>" href="?role=admin">Admin Login</a>
            </li>
          </ul>

          <?php if ($role === 'admin'): ?>
            <div class="icct-panel-header">
              <h2 class="h6 mb-1">Admin Login</h2>
              <div class="text-secondary small">Manage queues, windows, and analytics</div>
            </div>

            <?php if ($adminError): ?>
              <div class="alert alert-danger"><?= h($adminError) ?></div>
            <?php endif; ?>

            <form method="post" class="vstack gap-3">
              <input type="hidden" name="role" value="admin" />
              <div>
                <label class="form-label">Username</label>
                <input class="form-control" name="username" required autofocus />
              </div>
              <div>
                <label class="form-label">Password</label>
                <input class="form-control" type="password" name="password" required />
              </div>
              <button class="btn btn-primary btn-lg">Login as Admin</button>
            </form>

            <div class="mt-3 alert alert-info small mb-0">
              Default: <strong>admin</strong> / <strong>admin123</strong>
            </div>
          <?php else: ?>
            <div class="icct-panel-header">
              <h2 class="h6 mb-1">Student Login</h2>
              <div class="text-secondary small">Sign in to book online queue tickets</div>
            </div>

            <?php if ($studentError): ?>
              <div class="alert alert-danger"><?= h($studentError) ?></div>
            <?php endif; ?>

            <form method="post" class="vstack gap-3">
              <input type="hidden" name="role" value="student" />
              <div>
                <label class="form-label">Student No</label>
                <input class="form-control" name="student_no" required autofocus />
              </div>
              <div>
                <label class="form-label">Password</label>
                <input class="form-control" type="password" name="password" required />
              </div>
              <button class="btn btn-primary btn-lg">Login as Student</button>
            </form>

            <div class="mt-3 small text-secondary">
              No account yet? <a href="/icct-queue-thesis/student_register.php">Register</a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <p class="icct-footer-note text-center mt-4 mb-0">
        Public displays:
        <a href="/icct-queue-thesis/display.php">Now Serving</a> ·
        <a href="/icct-queue-thesis/kiosk.php">RFID Kiosk</a>
      </p>
    </main>
<?php ui_foot(); ?>
