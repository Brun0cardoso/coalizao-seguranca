<?php
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('coalizao_admin');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function is_admin_authenticated(): bool
{
    start_secure_session();
    return ($_SESSION['admin_authenticated'] ?? false) === true;
}

function require_admin(): void
{
    if (!is_admin_authenticated()) {
        header('Location: ../login.php', true, 302);
        exit;
    }
}
