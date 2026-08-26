<?php
require_once __DIR__ . '/php/auth.php';
$config = require __DIR__ . '/php/config.php';
start_secure_session();
if (is_admin_authenticated()) { header('Location: admin/index.php', true, 302); exit; }
$error = '';
$message = isset($_GET['senha']) && $_GET['senha'] === 'alterada' ? 'Senha alterada. Entre com a nova senha.' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $passwordHash = $config['admin_password_hash'] ?? '';
    try {
        require __DIR__ . '/php/conexao.php';
        $override = $pdo->query('SELECT password_hash FROM admin_password_overrides WHERE id = 1')->fetchColumn();
        if (is_string($override) && $override !== '') $passwordHash = $override;
    } catch (Throwable $exception) {
        // Keeps the configured password working before the recovery tables are created.
    }
    if (!empty($passwordHash) && is_string($password) && password_verify($password, $passwordHash)) {
        session_regenerate_id(true); $_SESSION['admin_authenticated'] = true;
        header('Location: admin/index.php', true, 302); exit;
    }
    $error = 'Senha invalida. Tente novamente.';
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Acesso administrativo | Coalizão Segurança</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="css/login.css"></head><body><main class="login-page"><form class="login-card" method="post"><a href="index.php" class="login-brand"><img src="assets/images/logo.png" alt="Coalizão Segurança"><span>Coalizão<br><strong>Admin</strong></span></a><h1>Acesso ao painel</h1><p>Informe a senha de administrador para continuar.</p><?php if ($error): ?><p class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><?php if ($message): ?><p class="login-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><label for="password">Senha</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus><label class="password-toggle"><input id="show-password" type="checkbox"> Mostrar senha</label><button type="submit">Entrar no painel</button><a class="back-link" href="recuperar_senha.php">Esqueci minha senha</a><a class="back-link" href="index.php">← Voltar ao site</a></form></main><script>const passwordInput = document.getElementById('password'); const showPassword = document.getElementById('show-password'); showPassword.addEventListener('change', () => { passwordInput.type = showPassword.checked ? 'text' : 'password'; });</script></body></html>
