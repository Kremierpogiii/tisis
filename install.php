<?php
require_once __DIR__ . '/lib.php';

// Simple one-time installer:
// - sets admin password to admin123 (bcrypt)
// - creates sample student (optional)

$c = db();

$adminUser = 'admin';
$adminPass = 'admin123';
$hash = password_hash($adminPass, PASSWORD_DEFAULT);
$now = now_ts();

// Ensure admin exists and password is correct
$stmt = $c->prepare('SELECT id FROM admins WHERE username=? LIMIT 1');
$stmt->bind_param('s', $adminUser);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
    $id = (int)$row['id'];
    $up = $c->prepare('UPDATE admins SET password_hash=? WHERE id=?');
    $up->bind_param('si', $hash, $id);
    $up->execute();
} else {
    $ins = $c->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?,?,?)');
    $ins->bind_param('sss', $adminUser, $hash, $now);
    $ins->execute();
}

// Optional: seed demo student (student_no: 2026-0001 / pass: student123)
$demoNo = '2026-0001';
$demoPass = 'student123';
$demoHash = password_hash($demoPass, PASSWORD_DEFAULT);
$demoName = 'Demo Student';
$demoMobile = '09123456789';

try {
    $ins = $c->prepare('INSERT INTO students (student_no, fullname, mobile, password_hash, created_at) VALUES (?,?,?,?,?)');
    $ins->bind_param('sssss', $demoNo, $demoName, $demoMobile, $demoHash, $now);
    $ins->execute();
} catch (mysqli_sql_exception) {
    // ignore if already exists
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Installed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-5" style="max-width: 760px;">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h5">Install complete</h1>
          <p class="text-secondary mb-4">You can now log in.</p>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="border rounded p-3 bg-white h-100">
                <div class="fw-semibold mb-2">Admin</div>
                <div class="small"><strong>Username:</strong> admin</div>
                <div class="small"><strong>Password:</strong> admin123</div>
                <div class="mt-2">
                  <a class="btn btn-sm btn-primary" href="/icct-queue-thesis/?role=admin">Go to Admin Login</a>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded p-3 bg-white h-100">
                <div class="fw-semibold mb-2">Demo Student</div>
                <div class="small"><strong>Student No:</strong> 2026-0001</div>
                <div class="small"><strong>Password:</strong> student123</div>
                <div class="mt-2">
                  <a class="btn btn-sm btn-outline-primary" href="/icct-queue-thesis/">Go to Student Login</a>
                </div>
              </div>
            </div>
          </div>

          <div class="alert alert-warning small mt-4 mb-0">
            After confirming everything works, you may delete <code>install.php</code> for safety.
          </div>
        </div>
      </div>
    </div>
  </body>
</html>

