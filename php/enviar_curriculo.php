<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../curriculo.php', true, 302);
    exit;
}

require __DIR__ . '/conexao.php';
$config = require __DIR__ . '/config.php';

$name = trim($_POST['name'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone = trim($_POST['phone'] ?? '');
$file = $_FILES['resume'] ?? null;

if ($name === '' || !$email || $phone === '' || !$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
    header('Location: ../curriculo.php?curriculo=erro', true, 303);
    exit;
}

$allowed = ['application/pdf' => 'pdf'];
$mime = null;
if (class_exists('finfo')) {
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
} else {
    $handle = fopen($file['tmp_name'], 'rb');
    $signature = $handle ? fread($handle, 5) : false;
    if (is_resource($handle)) fclose($handle);
    if ($signature === '%PDF-') $mime = 'application/pdf';
}
if (!isset($allowed[$mime])) {
    header('Location: ../curriculo.php?curriculo=tipo-invalido', true, 303);
    exit;
}

$storage = dirname(__DIR__) . '/storage/curriculos';
if (!is_dir($storage) && !mkdir($storage, 0750, true)) {
    http_response_code(500);
    exit('Não foi possível receber o currículo.');
}

$storedName = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
if (!move_uploaded_file($file['tmp_name'], $storage . '/' . $storedName)) {
    http_response_code(500);
    exit('Não foi possível salvar o currículo.');
}

$statement = $pdo->prepare('INSERT INTO job_applications (name, email, phone, original_filename, stored_filename, mime_type) VALUES (?, ?, ?, ?, ?, ?)');
$statement->execute([$name, $email, $phone, basename($file['name']), $storedName, $mime]);
$sender = filter_var($config['company_email'] ?? '', FILTER_VALIDATE_EMAIL);
$subject = 'Recebemos seu currículo - Coalizão Segurança';
$body = "Olá, $name!\n\nRecebemos seu currículo com sucesso. Nossa equipe analisará suas informações e entrará em contato caso surja uma oportunidade compatível.\n\nAtenciosamente,\nCoalizão Segurança";
$headers = 'From: ' . ($sender ?: 'noreply@localhost') . "\r\n"
    . 'Reply-To: ' . ($sender ?: 'noreply@localhost') . "\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n";
$confirmationSent = $sender && mail($email, $subject, $body, $headers);
$mailStatus = $confirmationSent ? 'enviado' : 'pendente';
header('Location: ../curriculo.php?curriculo=sucesso&confirmacao=' . $mailStatus, true, 303);
exit;
