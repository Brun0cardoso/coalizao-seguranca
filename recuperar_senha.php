<?php
$config = require __DIR__ . '/php/config.php';
require __DIR__ . '/php/auth.php';
start_secure_session();
require __DIR__ . '/php/conexao.php';

if (empty($_SESSION['recovery_csrf'])) $_SESSION['recovery_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['recovery_csrf'];
$message = '';
$error = '';
$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
$isReset = $token !== '';
$validPost = $_SERVER['REQUEST_METHOD'] !== 'POST' || (isset($_POST['csrf']) && is_string($_POST['csrf']) && hash_equals($csrf, $_POST['csrf']));

if (!$validPost) {
    $error = 'Solicitacao invalida. Atualize a pagina e tente novamente.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {
    $baseUrl = rtrim((string) ($config['site_url'] ?? ''), '/');
    $last = (int) ($_SESSION['recovery_last_request'] ?? 0);
    if (time() - $last < 60) {
        $message = 'Se configurado, um novo link sera enviado em instantes.';
    } elseif (empty($config['admin_recovery_email']) || $baseUrl === '') {
        $error = 'Configure admin_recovery_email e site_url em php/config.local.php.';
    } else {
        $rawToken = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE used_at IS NULL')->execute();
        $pdo->prepare('INSERT INTO admin_password_resets (token_hash, expires_at) VALUES (?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')->execute([hash('sha256', $rawToken)]);
        $link = $baseUrl . '/recuperar_senha.php?token=' . rawurlencode($rawToken);
        $subject = 'Recuperacao de senha do painel';
        $body = "Use este link em ate 30 minutos para redefinir a senha do painel:\n\n{$link}\n\nSe nao foi voce, ignore este e-mail.";
        $from = filter_var($config['company_email'] ?? '', FILTER_VALIDATE_EMAIL);
        $headers = ($from ? "From: {$from}\r\n" : '') . 'Content-Type: text/plain; charset=UTF-8';
        @mail($config['admin_recovery_email'], $subject, $body, $headers);
        $_SESSION['recovery_last_request'] = time();
        $message = 'Se a recuperacao estiver configurada, voce recebera um link no e-mail cadastrado.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    $token = isset($_POST['token']) && is_string($_POST['token']) ? $_POST['token'] : '';
    $isReset = true;
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirmation'] ?? '';
    if (!is_string($password) || strlen($password) < 10) {
        $error = 'Use uma senha com pelo menos 10 caracteres.';
    } elseif ($password !== $confirmation) {
        $error = 'As senhas nao coincidem.';
    } else {
        $pdo->beginTransaction();
        $find = $pdo->prepare('SELECT id FROM admin_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
        $find->execute([hash('sha256', $token)]);
        $resetId = $find->fetchColumn();
        if (!$resetId) {
            $pdo->rollBack();
            $error = 'Este link e invalido ou expirou. Solicite outro.';
        } else {
            $pdo->prepare('INSERT INTO admin_password_overrides (id, password_hash) VALUES (1, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)')->execute([password_hash($password, PASSWORD_DEFAULT)]);
            $pdo->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE id = ?')->execute([$resetId]);
            $pdo->commit();
            unset($_SESSION['recovery_csrf']);
            header('Location: login.php?senha=alterada', true, 303);
            exit;
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Recuperar senha | Coalizão Segurança</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="css/login.css"></head><body><main class="login-page"><form class="login-card" method="post"><a href="index.php" class="login-brand"><img src="assets/images/logo.png" alt="Coalizão Segurança"><span>Coalizão<br><strong>Admin</strong></span></a><?php if ($isReset): ?><h1>Definir nova senha</h1><p>Escolha uma senha forte para acessar o painel.</p><input type="hidden" name="action" value="reset"><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"><?php else: ?><h1>Recuperar senha</h1><p>Enviaremos um link seguro para o e-mail configurado.</p><input type="hidden" name="action" value="request"><?php endif; ?><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><?php if ($error): ?><p class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><?php if ($message): ?><p class="login-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><?php if ($isReset): ?><label for="password">Nova senha</label><input id="password" name="password" type="password" minlength="10" autocomplete="new-password" required autofocus><label for="password_confirmation" style="margin-top:16px">Confirmar nova senha</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required><?php endif; ?><button type="submit"><?= $isReset ? 'Salvar nova senha' : 'Enviar link de recuperacao' ?></button><a class="back-link" href="login.php">← Voltar ao login</a></form></main></body></html>
