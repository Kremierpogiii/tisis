<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

function login_url(string $role = 'student'): string {
    $role = $role === 'admin' ? 'admin' : 'student';
    return '/icct-queue-thesis/?role=' . $role;
}

function require_student(): array {
    start_session();
    if (empty($_SESSION['student_id'])) {
        redirect(login_url('student'));
    }
    return [
        'student_id' => (int)$_SESSION['student_id'],
        'student_no' => (string)($_SESSION['student_no'] ?? ''),
        'fullname' => (string)($_SESSION['fullname'] ?? ''),
    ];
}

function require_admin(): array {
    start_session();
    if (empty($_SESSION['admin_id'])) {
        redirect(login_url('admin'));
    }
    return [
        'admin_id' => (int)$_SESSION['admin_id'],
        'username' => (string)($_SESSION['admin_username'] ?? ''),
    ];
}

function update_ticket_status(mysqli $c, int $ticketId, string $newStatus, array $tsCols): void {
    $sets = ['status=?'];
    $params = [$newStatus];
    $types = 's';

    foreach ($tsCols as $col => $val) {
        $sets[] = $col . '=?';
        $params[] = $val;
        $types .= 's';
    }
    $setsSql = implode(', ', $sets);
    $sql = "UPDATE queue_tickets SET $setsSql WHERE id=?";
    $stmt = $c->prepare($sql);
    $types .= 'i';
    $params[] = $ticketId;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
}

function get_queue_active(mysqli $c, int $serviceId, int $limit = 5): array {
    $stmt = $c->prepare("SELECT qt.id, qt.ticket_no, qt.status, qt.called_at, qt.served_at, qt.created_at, qt.booked_for,
      st.student_no, st.fullname
      FROM queue_tickets qt
      LEFT JOIN students st ON st.id = qt.student_id
      WHERE qt.service_id=? AND qt.status IN ('called','serving')
      ORDER BY COALESCE(qt.served_at, qt.called_at) DESC, qt.id DESC
      LIMIT ?");
    $stmt->bind_param('ii', $serviceId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_queue_waiting(mysqli $c, int $serviceId, int $limit = 50): array {
    $stmt = $c->prepare("SELECT qt.id, qt.ticket_no, qt.status, qt.created_at, qt.booked_for, qt.source,
      st.student_no, st.fullname
      FROM queue_tickets qt
      LEFT JOIN students st ON st.id = qt.student_id
      WHERE qt.service_id=? AND qt.status='waiting'
      ORDER BY COALESCE(qt.booked_for, qt.created_at) ASC, qt.id ASC
      LIMIT ?");
    $stmt->bind_param('ii', $serviceId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function display_serving_minutes(): int {
    global $DISPLAY_SERVING_MINUTES;
    $minutes = (int)($DISPLAY_SERVING_MINUTES ?? 5);
    return max(1, min(60, $minutes));
}

function process_display_auto_advance(mysqli $c, int $serviceId): void {
    $minutes = display_serving_minutes();
    $now = now_ts();

    $expireStmt = $c->prepare("SELECT id FROM queue_tickets
      WHERE service_id=? AND status IN ('called','serving')
      AND called_at IS NOT NULL
      AND DATE_ADD(called_at, INTERVAL ? MINUTE) <= NOW()
      ORDER BY called_at ASC");
    $expireStmt->bind_param('ii', $serviceId, $minutes);
    $expireStmt->execute();
    $expired = $expireStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($expired as $row) {
        update_ticket_status($c, (int)$row['id'], 'completed', ['completed_at' => $now]);
    }

    $activeStmt = $c->prepare("SELECT id FROM queue_tickets
      WHERE service_id=? AND called_at IS NOT NULL
      AND DATE_ADD(called_at, INTERVAL ? MINUTE) > NOW()
      LIMIT 1");
    $activeStmt->bind_param('ii', $serviceId, $minutes);
    $activeStmt->execute();
    if ($activeStmt->get_result()->fetch_assoc()) {
        return;
    }

    $historyStmt = $c->prepare("SELECT id FROM queue_tickets
      WHERE service_id=? AND called_at IS NOT NULL
      LIMIT 1");
    $historyStmt->bind_param('i', $serviceId);
    $historyStmt->execute();
    if (!$historyStmt->get_result()->fetch_assoc()) {
        return;
    }

    $nextStmt = $c->prepare("SELECT id FROM queue_tickets
      WHERE service_id=? AND status='waiting'
      ORDER BY COALESCE(booked_for, created_at) ASC, id ASC
      LIMIT 1");
    $nextStmt->bind_param('i', $serviceId);
    $nextStmt->execute();
    $next = $nextStmt->get_result()->fetch_assoc();
    if ($next) {
        update_ticket_status($c, (int)$next['id'], 'called', ['called_at' => $now]);
    }
}

function get_display_serving(mysqli $c, int $serviceId): ?array {
    $minutes = display_serving_minutes();
    $stmt = $c->prepare("SELECT ticket_no, status, called_at,
      DATE_ADD(called_at, INTERVAL ? MINUTE) AS display_until
      FROM queue_tickets
      WHERE service_id=? AND called_at IS NOT NULL
      AND DATE_ADD(called_at, INTERVAL ? MINUTE) > NOW()
      ORDER BY called_at DESC
      LIMIT 1");
    $stmt->bind_param('iii', $minutes, $serviceId, $minutes);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function get_display_next_waiting(mysqli $c, int $serviceId, int $limit = 5): array {
    $stmt = $c->prepare("SELECT ticket_no, created_at, booked_for
      FROM queue_tickets
      WHERE service_id=? AND status='waiting'
      ORDER BY COALESCE(booked_for, created_at) ASC, id ASC
      LIMIT ?");
    $stmt->bind_param('ii', $serviceId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function now_ts(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
}

function rand_token(int $bytes = 16): string {
    return bin2hex(random_bytes($bytes));
}

function sms_send_simulated(int $studentId, string $mobile, string $message): void {
    $c = db();
    $stmt = $c->prepare('INSERT INTO sms_outbox (student_id, mobile, message, status, created_at) VALUES (?,?,?,?,?)');
    $status = 'simulated';
    $createdAt = now_ts();
    $stmt->bind_param('issss', $studentId, $mobile, $message, $status, $createdAt);
    $stmt->execute();
}

function get_service_by_id(int $serviceId): ?array {
    $c = db();
    $stmt = $c->prepare('SELECT id, code, name, window_no FROM services WHERE id = ? AND is_active=1 LIMIT 1');
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function next_ticket_no(string $serviceCode, DateTimeImmutable $date): string {
    $c = db();
    $prefix = strtoupper($serviceCode) . '-' . $date->format('Ymd') . '-';
    $like = $prefix . '%';
    $stmt = $c->prepare('SELECT ticket_no FROM queue_tickets WHERE ticket_no LIKE ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $next = 1;
    if ($row && isset($row['ticket_no'])) {
        $parts = explode('-', (string)$row['ticket_no']);
        $last = (int)end($parts);
        $next = $last + 1;
    }
    return $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

function create_ticket(int $serviceId, ?int $studentId, string $source, ?DateTimeImmutable $bookedFor): array {
    $svc = get_service_by_id($serviceId);
    if (!$svc) {
        throw new RuntimeException('Invalid service.');
    }

    $c = db();
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $ticketNo = next_ticket_no((string)$svc['code'], $now);
    $status = 'waiting';
    $createdAt = $now->format('Y-m-d H:i:s');
    $bookedForStr = $bookedFor ? $bookedFor->format('Y-m-d H:i:s') : null;
    $token = rand_token(16);

    $stmt = $c->prepare('INSERT INTO queue_tickets (ticket_no, service_id, student_id, source, status, booked_for, created_at, token)
      VALUES (?,?,?,?,?,?,?,?)');
    $stmt->bind_param(
        'siisssss',
        $ticketNo,
        $serviceId,
        $studentId,
        $source,
        $status,
        $bookedForStr,
        $createdAt,
        $token
    );
    $stmt->execute();

    return [
        'ticket_no' => $ticketNo,
        'token' => $token,
        'service' => $svc,
    ];
}

