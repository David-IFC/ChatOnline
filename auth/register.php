<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';

if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (strlen($username) < 3 || strlen($username) > 30) {
        $errors[] = 'El usuario debe tener entre 3 y 30 caracteres.';
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'El usuario solo puede tener letras, numeros y guion bajo.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'La contrasena debe tener al menos 6 caracteres.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Las contrasenas no coinciden.';
    }

    if (!$errors) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);

        if ($stmt->fetch()) {
            $errors[] = 'Ese nombre de usuario ya existe.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
            $insert->execute([
                'username' => $username,
                'password_hash' => $hash,
            ]);

            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['username'] = $username;

            header('Location: ../index.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro</title>
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
<main class="auth-container">
    <h1>Crear cuenta</h1>
    <?php if ($errors): ?>
        <div class="error-box">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post" class="auth-form">
        <label>Usuario</label>
        <input type="text" name="username" required maxlength="30">

        <label>Contrasena</label>
        <input type="password" name="password" required minlength="6">

        <label>Confirmar contrasena</label>
        <input type="password" name="confirm_password" required minlength="6">

        <button type="submit">Registrarme</button>
    </form>
    <p>Ya tienes cuenta? <a href="login.php">Inicia sesion</a></p>
</main>
</body>
</html>
