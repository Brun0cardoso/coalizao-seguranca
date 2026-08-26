<?php
require_once __DIR__ . '/../../php/auth.php';
require_admin();
$pageTitle = $pageTitle ?? 'Painel Administrativo';
$activePage = $activePage ?? 'dashboard';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Coalizão Segurança</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="index.php" aria-label="Ir para o painel inicial">
                <img src="../assets/images/logo.png" alt="Coalizão Segurança">
                <span>Coalizão<br><strong>Admin</strong></span>
            </a>
            <nav aria-label="Navegação administrativa">
                <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="index.php">Visão geral</a>
                <a class="<?= $activePage === 'financeiro' ? 'active' : '' ?>" href="financeiro.php">Financeiro</a>
                <a class="<?= $activePage === 'orcamentos' ? 'active' : '' ?>" href="orcamentos.php">Orçamentos</a>
                <a class="<?= $activePage === 'colaboradores' ? 'active' : '' ?>" href="colaboradores.php">Colaboradores</a>
                <a class="<?= $activePage === 'clientes' ? 'active' : '' ?>" href="clientes.php">Clientes e postos</a>
                <a class="<?= $activePage === 'curriculos' ? 'active' : '' ?>" href="curriculos.php">Currículos</a>
            </nav>
            <a class="site-link" href="../logout.php">Sair</a>
        </aside>
        <div class="page-shell">
            <header class="topbar">
                <button class="menu-button" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="sidebar">☰</button>
                <div>
                    <p class="eyebrow">PAINEL ADMINISTRATIVO</p>
                    <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
            </header>
            <main class="admin-main">
