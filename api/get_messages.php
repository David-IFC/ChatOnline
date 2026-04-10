<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$lastId = (int)($_GET['last_id'] ?? 0);

$pdo = getPDO();
$stmt = $pdo->prepare(
    'SELECT m.id, m.message, m.created_at, u.username
     FROM messages m
     INNER JOIN users u ON u.id = m.user_id
     WHERE m.id > :last_id
     ORDER BY m.id ASC
     LIMIT 100'
);
$stmt->execute(['last_id' => $lastId]);
$messages = $stmt->fetchAll();

echo json_encode(['ok' => true, 'messages' => $messages]);
