<?php
$pageTitle = 'Visão geral';
$activePage = 'dashboard';
include 'includes/header.php';
?>
<section class="welcome">
    <div><p class="eyebrow">RESUMO OPERACIONAL</p><h2>Tenha controle da operação em um só lugar.</h2><p>Os indicadores abaixo são demonstrativos até a integração com o banco de dados.</p></div>
    <a class="button" href="orcamentos.php">Novo orçamento</a>
</section>
<section class="stats-grid" aria-label="Indicadores principais">
    <article class="stat-card"><span>Faturamento previsto</span><strong>R$ 48.750</strong><small>Dados demonstrativos</small></article>
    <article class="stat-card"><span>Orçamentos em aberto</span><strong>12</strong><small>4 aguardando retorno</small></article>
    <article class="stat-card"><span>Colaboradores ativos</span><strong>38</strong><small>Em 8 postos de serviço</small></article>
    <article class="stat-card"><span>Contas a vencer</span><strong>R$ 8.240</strong><small>Próximos 7 dias</small></article>
</section>
<section class="content-grid">
    <article class="panel"><div class="panel-heading"><div><p class="eyebrow">ORÇAMENTOS</p><h2>Últimas solicitações</h2></div><a href="orcamentos.php">Ver todos</a></div><div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Serviço</th><th>Status</th></tr></thead><tbody><tr><td>Condomínio Aurora</td><td>Portaria</td><td><span class="status pending">Em análise</span></td></tr><tr><td>Grupo Horizonte</td><td>Vigilância patrimonial</td><td><span class="status approved">Aprovado</span></td></tr><tr><td>Residencial Mirante</td><td>Monitoramento</td><td><span class="status sent">Enviado</span></td></tr></tbody></table></div></article>
    <article class="panel"><div class="panel-heading"><div><p class="eyebrow">POSTOS</p><h2>Escalas de hoje</h2></div><a href="postos.php">Gerenciar</a></div><ul class="activity-list"><li><b>Portaria Ed. Aurora</b><span>4 profissionais escalados</span></li><li><b>Grupo Horizonte</b><span>2 profissionais escalados</span></li><li><b>Residencial Mirante</b><span>2 profissionais escalados</span></li></ul></article>
</section>
<?php include 'includes/footer.php'; ?>
