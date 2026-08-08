<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Coalizão Segurança - Soluções profissionais em segurança patrimonial, portaria, controle de acesso e monitoramento."
    >

    <title>Coalizão Segurança</title>

    <!-- Favicon -->
    <link rel="icon" href="assets/imagens/logo/favicon.ico">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- CSS principal -->
    <link rel="stylesheet" href="css/style.css">

</head>

<body>

<header class="header" id="header">

    <div class="container header-container">

        <!-- LOGO -->

        <div class="logo">

            <a href="index.php">

                <img
                    src="assets/images/logo.png"
                    alt="Coalizão Segurança"
                >

            </a>

        </div>


        <!-- MENU -->

        <?php include 'menu.php'; ?>


        <!-- BOTÃO ORÇAMENTO -->

        <a
            href="contato.php"
            class="btn-orcamento"
        >
            Solicitar Orçamento
        </a>


        <!-- BOTÃO MENU MOBILE -->

        <button
            class="menu-toggle"
            id="menu-toggle"
            aria-label="Abrir menu"
            aria-expanded="false"
        >

            <span></span>
            <span></span>
            <span></span>

        </button>

    </div>

</header>