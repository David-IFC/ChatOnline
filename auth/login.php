<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';

if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        $error = 'Usuario o contrasena incorrectos.';
    } else {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = (string)$user['username'];

        header('Location: ../index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesion</title>
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
<main class="auth-container">
    <h1>Iniciar sesion</h1>
    <?php if ($error): ?>
        <div class="error-box">
            <p><?= htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>
    <form method="post" class="auth-form">
        <label>Usuario</label>
        <input type="text" name="username" required maxlength="30">

        <label>Contrasena</label>
        <input type="password" name="password" required minlength="6">

        <button type="submit">Entrar</button>
    </form>
    <p>No tienes cuenta? <a href="register.php">Registrate</a></p>
</main>
</body>
</html>
