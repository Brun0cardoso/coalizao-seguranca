<?php include 'includes/header.php'; ?>
<main class="container" style="padding:150px 0 80px;max-width:700px">
    <span class="section-tag">TRABALHE CONOSCO</span>
    <h1 class="section-title">Envie seu currículo</h1>
    <p class="section-subtitle">Aceitamos arquivos PDF de até 5 MB.</p>
    <?php if (($_GET['curriculo'] ?? '') === 'sucesso'): ?><p>Currículo enviado com sucesso. <?php if (($_GET['confirmacao'] ?? '') === 'enviado'): ?>Enviamos uma confirmação para o seu e-mail.<?php else: ?>O currículo foi recebido; a confirmação por e-mail está pendente de configuração do servidor.<?php endif; ?></p><?php endif; ?>
    <?php if (isset($_GET['curriculo']) && $_GET['curriculo'] !== 'sucesso'): ?><p>Não foi possível enviar. Confira os dados e o arquivo.</p><?php endif; ?>
    <form action="php/enviar_curriculo.php" method="post" enctype="multipart/form-data" class="contato-formulario">
        <div class="form-grupo"><label for="name">Nome</label><input id="name" name="name" required></div>
        <div class="form-grupo"><label for="email">E-mail</label><input id="email" name="email" type="email" required></div>
        <div class="form-grupo"><label for="phone">Telefone</label><input id="phone" name="phone" type="tel" required></div>
        <div class="form-grupo"><label for="resume">Currículo</label><input id="resume" name="resume" type="file" accept=".pdf,application/pdf" required></div>
        <button class="btn" type="submit">Enviar currículo</button>
    </form>
</main>
<?php include 'includes/footer.php'; ?>
