<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/config/database.php';

if (isset($_POST['_chat_action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No autorizado']);
        exit;
    }

    $ajaxAction = (string)$_POST['_chat_action'];
    $pdo = getPDO();

    if ($ajaxAction === 'get_messages') {
        $lastId = (int)($_POST['last_id'] ?? 0);
        $stmt = $pdo->prepare(
            'SELECT m.id, m.message, m.created_at, u.username
             FROM messages m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.id > :last_id
             ORDER BY m.id ASC
             LIMIT 100'
        );
        $stmt->execute(['last_id' => $lastId]);
        echo json_encode(['ok' => true, 'messages' => $stmt->fetchAll()]);
        exit;
    }

    if ($ajaxAction === 'send_message') {
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

        $stmt = $pdo->prepare('INSERT INTO messages (user_id, message) VALUES (:user_id, :message)');
        $stmt->execute([
            'user_id' => currentUserId(),
            'message' => $message,
        ]);

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($ajaxAction === 'clear_messages') {
        $pdo->exec('DELETE FROM messages');
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Accion invalida']);
    exit;
}

if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit;
}

$pdo = getPDO();
$stmt = $pdo->query(
    'SELECT m.id, m.message, m.created_at, u.username
     FROM messages m
     INNER JOIN users u ON u.id = m.user_id
     ORDER BY m.id DESC
     LIMIT 50'
);
$messages = array_reverse($stmt->fetchAll());
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat en tiempo real</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<main class="chat-container">
    <header class="chat-header">
        <h1>Chat general</h1>
        <div class="user-info">
            <span>Hola, <?= htmlspecialchars(currentUsername()) ?></span>
            <a href="auth/logout.php">Salir</a>
        </div>
    </header>

    <section id="messages" class="messages">
        <?php foreach ($messages as $item): ?>
            <article class="message">
                <div class="meta">
                    <strong><?= htmlspecialchars((string)$item['username']) ?></strong>
                    <small><?= htmlspecialchars((string)$item['created_at']) ?></small>
                </div>
                <p><?= nl2br(htmlspecialchars((string)$item['message'])) ?></p>
            </article>
        <?php endforeach; ?>
    </section>

    <form id="chat-form" class="chat-form" method="post">
        <input type="text" id="message-input" name="message" maxlength="500" placeholder="Escribe tu mensaje..." required>
        <button type="submit">Enviar</button>
        <button type="button" id="clear-messages-btn" class="danger-btn">Eliminar todos los mensajes</button>
    </form>
    <p id="chat-status" class="chat-status"></p>
</main>

<script>
    window.chatConfig = {
        initialLastId: <?= (int)(count($messages) ? end($messages)['id'] : 0) ?>,
        getMessagesUrl: 'index.php',
        sendMessageUrl: 'index.php'
    };
</script>
<script>
(() => {
    const messagesEl = document.getElementById('messages');
    const formEl = document.getElementById('chat-form');
    const inputEl = document.getElementById('message-input');
    const clearBtnEl = document.getElementById('clear-messages-btn');
    const statusEl = document.getElementById('chat-status');
    const getMessagesUrl = window.chatConfig?.getMessagesUrl || 'index.php';
    const sendMessageUrl = window.chatConfig?.sendMessageUrl || 'index.php';
    let lastId = Number(window.chatConfig?.initialLastId || 0);

    if (!messagesEl || !formEl || !inputEl) {
        console.error('No se pudo inicializar el chat: faltan elementos del DOM');
        return;
    }

    const escapeHtml = (text) => text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const appendMessage = (item) => {
        const wrapper = document.createElement('article');
        wrapper.className = 'message';
        wrapper.innerHTML = `
            <div class="meta">
                <strong>${escapeHtml(String(item.username))}</strong>
                <small>${escapeHtml(String(item.created_at))}</small>
            </div>
            <p>${escapeHtml(String(item.message)).replaceAll('\n', '<br>')}</p>
        `;
        messagesEl.appendChild(wrapper);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const setStatus = (text) => {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = text;
    };

    const fetchMessages = async () => {
        try {
            const body = new URLSearchParams({
                _chat_action: 'get_messages',
                last_id: String(lastId)
            });
            const response = await fetch(getMessagesUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                credentials: 'same-origin',
                body
            });
            if (!response.ok) {
                setStatus(`Error cargando mensajes (${response.status})`);
                return;
            }

            const data = await response.json();
            if (!data.ok || !Array.isArray(data.messages)) {
                setStatus('No se pudieron cargar los mensajes');
                return;
            }

            data.messages.forEach((item) => {
                appendMessage(item);
                lastId = Math.max(lastId, Number(item.id));
            });
            setStatus('');
        } catch (error) {
            console.error(error);
            setStatus('Fallo de red al cargar mensajes');
        }
    };

    formEl.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        const message = inputEl.value.trim();
        if (!message) {
            return;
        }

        try {
            const body = new URLSearchParams({
                _chat_action: 'send_message',
                message
            });
            const response = await fetch(sendMessageUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                credentials: 'same-origin',
                body
            });

            if (response.ok) {
                inputEl.value = '';
                setStatus('');
                await fetchMessages();
            } else {
                const errorBody = await response.text();
                console.error('Error send_message:', response.status, errorBody);
                setStatus(`No se pudo enviar (${response.status})`);
            }
        } catch (error) {
            console.error(error);
            setStatus('Fallo de red al enviar');
        }
    });

    clearBtnEl?.addEventListener('click', async () => {
        const confirmDelete = window.confirm('Se eliminaran todos los mensajes del chat. Deseas continuar?');
        if (!confirmDelete) {
            return;
        }

        try {
            const body = new URLSearchParams({
                _chat_action: 'clear_messages'
            });
            const response = await fetch(sendMessageUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                credentials: 'same-origin',
                body
            });

            if (!response.ok) {
                setStatus(`No se pudieron eliminar los mensajes (${response.status})`);
                return;
            }

            messagesEl.innerHTML = '';
            lastId = 0;
            setStatus('Mensajes eliminados correctamente');
        } catch (error) {
            console.error(error);
            setStatus('Fallo de red al eliminar mensajes');
        }
    });

    messagesEl.scrollTop = messagesEl.scrollHeight;
    setInterval(fetchMessages, 2000);
})();
</script>
</body>
</html>
