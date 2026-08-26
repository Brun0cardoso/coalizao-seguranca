<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../index.php#contato', true, 302); exit; }
require __DIR__ . '/conexao.php';
$config = require __DIR__ . '/config.php';
$name = trim($_POST['nome'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone = trim($_POST['telefone'] ?? '');
$message = trim($_POST['mensagem'] ?? '');
if ($name === '' || !$email || $phone === '' || $message === '') { header('Location: ../index.php?orcamento=erro#contato', true, 303); exit; }
$insert = $pdo->prepare("INSERT INTO quotes (contact_name, contact_email, contact_phone, service_type, status, notes) VALUES (?, ?, ?, 'other', 'under_review', ?)");
$insert->execute([$name, $email, $phone, $message]);
$subject = 'Nova solicitação de orçamento - Coalizão Segurança';
$body = "Nome: $name\nE-mail: $email\nTelefone: $phone\n\nMensagem:\n$message";
$headers = "From: {$config['company_email']}\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
@mail($config['company_email'], $subject, $body, $headers);
header('Location: ../index.php?orcamento=sucesso#contato', true, 303);
exit;
