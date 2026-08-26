<?php
require_once __DIR__ . '/php/auth.php';
start_secure_session();
$_SESSION = [];
session_destroy();
header('Location: login.php', true, 302);
exit;
