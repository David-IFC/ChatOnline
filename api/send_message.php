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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido']);
    exit;
}

$message = trim((string)($_POST['message'] ?? ''));
if ($message === '' || strlen($message) > 500) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Mensaje invalido']);
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare('INSERT INTO messages (user_id, message) VALUES (:user_id, :message)');
$stmt->execute([
    'user_id' => currentUserId(),
    'message' => $message,
]);

echo json_encode(['ok' => true]);
