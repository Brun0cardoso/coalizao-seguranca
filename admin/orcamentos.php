<?php
$pageTitle = 'Orçamentos';
$activePage = 'orcamentos';
include 'includes/header.php';
?>
<p><a class="button" href="download.php?type=orcamentos">Baixar orçamentos (CSV)</a></p>
<section class="welcome compact"><div><p class="eyebrow">ORÇAMENTOS</p><h2>Crie propostas com mais agilidade.</h2><p>O cálculo automático será conectado às tabelas de preços no banco de dados.</p></div></section>
<section class="form-panel"><form><div class="form-heading"><h2>Novo orçamento</h2><p>Preencha os dados para montar uma proposta demonstrativa.</p></div><div class="form-grid"><label>Cliente<input type="text" placeholder="Nome ou empresa"></label><label>Tipo de serviço<select><option>Vigilância patrimonial</option><option>Portaria e recepção</option><option>Monitoramento</option></select></label><label>Quantidade de postos<input type="number" min="1" value="1"></label><label>Período<select><option>Mensal</option><option>Eventual</option></select></label><label class="full">Observações<textarea rows="4" placeholder="Descreva a necessidade do cliente"></textarea></label></div><div class="form-actions"><span>O cálculo será liberado com a tabela de custos.</span><button class="button" type="button">Gerar prévia</button></div></form></section>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">HISTÓRICO</p><h2>Propostas recentes</h2></div></div><div class="table-wrap"><table><thead><tr><th>Proposta</th><th>Cliente</th><th>Valor</th><th>Status</th></tr></thead><tbody><tr><td>#00012</td><td>Condomínio Aurora</td><td>R$ 8.900/mês</td><td><span class="status pending">Em análise</span></td></tr><tr><td>#00011</td><td>Grupo Horizonte</td><td>R$ 12.500/mês</td><td><span class="status approved">Aprovado</span></td></tr></tbody></table></div></section>
<?php include 'includes/footer.php'; ?>
