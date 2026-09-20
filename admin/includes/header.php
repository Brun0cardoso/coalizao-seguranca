<?php
require_once __DIR__ . '/../../php/auth.php';
require_admin();
$pageTitle = $pageTitle ?? 'Painel Administrativo';
$activePage = $activePage ?? 'dashboard';
$adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin'), '/');
$siteBase = preg_replace('#/admin/?$#', '', $adminBase) ?: '';
$logoUrl = $siteBase . '/assets/images/logo.png';
$adminCssUrl = $adminBase . '/assets/admin.css';
$adminJsUrl = $adminBase . '/assets/admin.js';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Coalizão Segurança</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($adminCssUrl, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="<?= htmlspecialchars($adminBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>" aria-label="Ir para o painel inicial">
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Coalizão Segurança">
                <span>Coalizão<br><strong>Admin</strong></span>
            </a>
            <nav aria-label="Navegação administrativa">
                <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>">Visão geral</a>
                <a class="<?= $activePage === 'financeiro' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminBase . '/financeiro.php', ENT_QUOTES, 'UTF-8') ?>">Financeiro</a>
                <a class="<?= $activePage === 'contratos' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminBase . '/contratos.php', ENT_QUOTES, 'UTF-8') ?>">Contratos</a>
                <a class="<?= $activePage === 'relatorios' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminBase . '/relatorios.php', ENT_QUOTES, 'UTF-8') ?>">Relatórios</a>
                <a class="<?= $activePage === 'orcamentos' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminBase . '/orcamentos.php', ENT_QUOTES, 'UTF-8') ?>">Orçamentos</a>
                <a class="<?= $activePage === 'colaboradores' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminBase . '/colaboradores.php', ENT_QUOTES, 'UTF-8') ?>">Colaboradores</a>
                <a class="<?= $activePage === 'clientes' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminBase . '/clientes.php', ENT_QUOTES, 'UTF-8') ?>">Clientes e postos</a>
                <a class="<?= $activePage === 'curriculos' ? 'active' : '' ?>" href="<?= htmlspecialchars($adminBase . '/curriculos.php', ENT_QUOTES, 'UTF-8') ?>">Currículos</a>
            </nav>
            <a class="site-link" href="<?= htmlspecialchars($siteBase . '/logout.php', ENT_QUOTES, 'UTF-8') ?>">Sair</a>
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
