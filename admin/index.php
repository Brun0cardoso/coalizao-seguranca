<?php
$pageTitle = 'Visão geral';
$activePage = 'dashboard';
require_once __DIR__ . '/../php/conexao.php';

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$dashboardSummary = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN type = 'income' AND status <> 'cancelled' AND due_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) AS revenue_month,
        COALESCE(SUM(CASE WHEN type = 'expense' AND status <> 'cancelled' AND due_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) AS expense_month,
        COALESCE(SUM(CASE WHEN type = 'income' AND status = 'pending' THEN amount ELSE 0 END), 0) AS pending_income,
        COALESCE(SUM(CASE WHEN due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status <> 'cancelled' THEN amount ELSE 0 END), 0) AS due_next_7_days
     FROM financial_entries"
);
$dashboardSummary->execute([$monthStart, $monthEnd, $monthStart, $monthEnd]);
$summary = $dashboardSummary->fetch(PDO::FETCH_ASSOC);

$openQuotesCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM quotes WHERE status IN ('draft', 'under_review', 'sent')"
)->fetchColumn();

$quotePipeline = $pdo->query(
    "SELECT
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS drafts,
        SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) AS under_review,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
     FROM quotes"
)->fetch(PDO::FETCH_ASSOC);

$serviceSummary = $pdo->query(
    "SELECT
        service_type,
        COUNT(*) AS total_quotes,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_quotes,
        COALESCE(SUM(CASE WHEN estimated_amount IS NOT NULL THEN estimated_amount ELSE 0 END), 0) AS total_value
     FROM quotes
     GROUP BY service_type
     ORDER BY total_value DESC, total_quotes DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$activeEmployeesCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM employees WHERE status = 'active'"
)->fetchColumn();

$topClients = $pdo->query(
    "SELECT cl.company_name, SUM(c.monthly_value) AS monthly_value
     FROM contracts c
     LEFT JOIN clients cl ON cl.id = c.client_id
     WHERE c.status IN ('active', 'renewed')
     GROUP BY cl.id, cl.company_name
     ORDER BY monthly_value DESC
     LIMIT 5"
)->fetchAll();

$revenueByMonth = $pdo->query(
    "SELECT DATE_FORMAT(due_date, '%Y-%m') AS month_key,
            SUM(CASE WHEN type = 'income' AND status <> 'cancelled' THEN amount ELSE 0 END) AS total
     FROM financial_entries
     WHERE due_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
     GROUP BY DATE_FORMAT(due_date, '%Y-%m')
     ORDER BY month_key ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $date = new DateTimeImmutable('first day of this month');
    $date = $date->modify("-$i month");
    $monthKey = $date->format('Y-m');
    $label = $date->format('M');
    $months[] = ['month_key' => $monthKey, 'label' => $label, 'total' => 0];
}

$revenueMap = [];
foreach ($revenueByMonth as $row) {
    $revenueMap[$row['month_key']] = (float) $row['total'];
}
foreach ($months as $index => $month) {
    $months[$index]['total'] = $revenueMap[$month['month_key']] ?? 0;
}
$maxMonthlyRevenue = max(array_column($months, 'total')) ?: 0;

$recentQuotes = $pdo->query(
    "SELECT q.id, cl.company_name, q.service_type, q.status, q.estimated_amount, q.created_at
     FROM quotes q
     LEFT JOIN clients cl ON cl.id = q.client_id
     ORDER BY q.created_at DESC
     LIMIT 4"
)->fetchAll();

$approvedQuotes = $pdo->query(
    "SELECT q.id, COALESCE(cl.company_name, q.contact_name) AS title, q.service_type AS detail, q.created_at AS action_date, 'quote' AS type
     FROM quotes q
     LEFT JOIN clients cl ON cl.id = q.client_id
     WHERE q.status = 'approved'
     ORDER BY q.created_at DESC
     LIMIT 3"
)->fetchAll(PDO::FETCH_ASSOC);

$expiringContracts = $pdo->query(
    "SELECT c.id, COALESCE(cl.company_name, 'Cliente não informado') AS title, c.title AS detail, c.end_date AS action_date, 'contract' AS type
     FROM contracts c
     LEFT JOIN clients cl ON cl.id = c.client_id
     WHERE c.status IN ('active', 'expiring')
       AND c.end_date IS NOT NULL
       AND c.end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY c.end_date ASC
     LIMIT 3"
)->fetchAll(PDO::FETCH_ASSOC);

$nextActions = array_merge($approvedQuotes, $expiringContracts);
usort($nextActions, static function (array $a, array $b): int {
    $dateA = strtotime((string) ($a['action_date'] ?? '1970-01-01'));
    $dateB = strtotime((string) ($b['action_date'] ?? '1970-01-01'));
    return $dateB <=> $dateA;
});

function moneyFormat(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function serviceLabel(string $value): string
{
    return [
        'security' => 'Vigilância patrimonial',
        'concierge' => 'Portaria e recepção',
        'monitoring' => 'Monitoramento',
        'access_control' => 'Controle de acesso',
        'other' => 'Outro',
    ][$value] ?? ucfirst(str_replace('_', ' ', $value));
}

function quoteLabel(string $value): string
{
    return [
        'draft' => 'Rascunho',
        'under_review' => 'Em análise',
        'sent' => 'Enviado',
        'approved' => 'Aprovado',
        'rejected' => 'Recusado',
        'cancelled' => 'Cancelado',
    ][$value] ?? ucfirst(str_replace('_', ' ', $value));
}

include __DIR__ . '/includes/header.php';
?>
<p><a class="button" href="download.php?type=dashboard">Baixar resumo do dashboard (CSV)</a></p>
<section class="welcome">
    <div><p class="eyebrow">RESUMO OPERACIONAL</p><h2>Tenha controle da operação em um só lugar.</h2><p>Indicadores em tempo real com base nos dados do painel administrativo.</p></div>
    <a class="button" href="orcamentos.php">Novo orçamento</a>
</section>
<section class="panel" style="margin-bottom:22px;">
    <div class="panel-heading">
        <div><p class="eyebrow">AÇÕES RÁPIDAS</p><h2>Fluxo principal</h2></div>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
        <a class="button" href="orcamentos.php">Criar orçamento</a>
        <a class="button secondary" href="contratos.php">Gerenciar contratos</a>
        <a class="button secondary" href="financeiro.php">Financeiro</a>
        <a class="button secondary" href="relatorios.php">Relatórios</a>
    </div>
</section>
<section class="stats-grid" aria-label="Indicadores principais">
    <article class="stat-card"><span>Faturamento do mês</span><strong><?= moneyFormat((float) ($summary['revenue_month'] ?? 0)) ?></strong><small>Receitas previstas e registradas</small></article>
    <article class="stat-card"><span>Orçamentos em aberto</span><strong><?= $openQuotesCount ?></strong><small>Em análise, enviados e em revisão</small></article>
    <article class="stat-card"><span>Colaboradores ativos</span><strong><?= $activeEmployeesCount ?></strong><small>Funcionários com status ativo</small></article>
    <article class="stat-card"><span>Contas a vencer</span><strong><?= moneyFormat((float) ($summary['due_next_7_days'] ?? 0)) ?></strong><small>Próximos 7 dias</small></article>
</section>
<section class="stats-grid" aria-label="Pipeline comercial">
    <article class="stat-card"><span>Rascunhos</span><strong><?= (int) ($quotePipeline['drafts'] ?? 0) ?></strong><small>Orçamentos em desenvolvimento</small></article>
    <article class="stat-card"><span>Em análise</span><strong><?= (int) ($quotePipeline['under_review'] ?? 0) ?></strong><small>Demandas pendentes de revisão</small></article>
    <article class="stat-card"><span>Enviados</span><strong><?= (int) ($quotePipeline['sent'] ?? 0) ?></strong><small>Propostas já encaminhadas</small></article>
    <article class="stat-card"><span>Aprovados</span><strong><?= (int) ($quotePipeline['approved'] ?? 0) ?></strong><small>Prontos para gerar contrato</small></article>
</section>
<section class="panel">
    <div class="panel-heading">
        <div><p class="eyebrow">SERVIÇOS</p><h2>Resumo por tipo de serviço</h2></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Serviço</th><th>Orçamentos</th><th>Aprovados</th><th>Valor estimado</th></tr>
            </thead>
            <tbody>
                <?php foreach ($serviceSummary as $service): ?>
                    <tr>
                        <td><?= htmlspecialchars(serviceLabel((string) $service['service_type']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $service['total_quotes'] ?></td>
                        <td><?= (int) $service['approved_quotes'] ?></td>
                        <td><?= moneyFormat((float) $service['total_value']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$serviceSummary): ?>
                    <tr><td colspan="4">Nenhum orçamento cadastrado para segmentar por serviço.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel">
    <div class="panel-heading">
        <div><p class="eyebrow">PRÓXIMAS AÇÕES</p><h2>O que merece atenção agora</h2></div>
    </div>
    <ul class="activity-list">
        <?php foreach ($nextActions as $action): ?>
            <li>
                <b><?= htmlspecialchars($action['title'] ?? 'Item sem identificação', ENT_QUOTES, 'UTF-8') ?></b>
                <span>
                    <?= htmlspecialchars($action['type'] === 'quote' ? 'Orçamento aprovado' : 'Contrato próximo de vencimento', ENT_QUOTES, 'UTF-8') ?> ·
                    <?= htmlspecialchars($action['detail'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($action['action_date'])): ?>
                        · <?= date('d/m/Y', strtotime((string) $action['action_date'])) ?>
                    <?php endif; ?>
                </span>
            </li>
        <?php endforeach; ?>
        <?php if (!$nextActions): ?>
            <li><b>Sem pendências prioritárias</b><span>Não há orçamentos aprovados nem contratos em alerta nesta janela.</span></li>
        <?php endif; ?>
    </ul>
</section>
<section class="panel">
    <div class="panel-heading">
        <div><p class="eyebrow">FATURAMENTO</p><h2>Evolução dos últimos 6 meses</h2></div>
    </div>
    <div aria-label="Gráfico de faturamento" style="display:flex; align-items:flex-end; gap:12px; min-height:240px; padding:20px 12px 6px; border-radius:12px; background:#f8fafc; border:1px solid #e5edf6;">
        <?php foreach ($months as $month): ?>
            <?php $barHeight = $maxMonthlyRevenue > 0 ? max(8, (int) (($month['total'] / $maxMonthlyRevenue) * 100)) : 0; ?>
            <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:8px;">
                <div style="width:100%; height:170px; display:flex; align-items:flex-end; justify-content:center;">
                    <div title="<?= htmlspecialchars($month['label'], ENT_QUOTES, 'UTF-8') ?>: <?= moneyFormat((float) $month['total']) ?>" style="width:100%; max-width:42px; height:<?= $barHeight ?>%; min-height:<?= $month['total'] > 0 ? 8 : 2 ?>px; border-radius:10px 10px 0 0; background:linear-gradient(180deg, #00b164 0%, #164f7d 100%); box-shadow:0 8px 18px rgba(22,79,125,0.18);"></div>
                </div>
                <small style="font-size:11px; color:#567; text-transform:uppercase; letter-spacing:0.05em;"><?= htmlspecialchars($month['label'], ENT_QUOTES, 'UTF-8') ?></small>
                <strong style="font-size:11px; color:#102a43;"><?= moneyFormat((float) $month['total']) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="content-grid">
    <article class="panel">
        <div class="panel-heading">
            <div><p class="eyebrow">ORÇAMENTOS</p><h2>Últimas solicitações</h2></div>
            <a href="orcamentos.php">Ver todos</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Cliente</th><th>Serviço</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentQuotes as $quote): ?>
                        <tr>
                            <td><?= htmlspecialchars($quote['company_name'] ?? 'Cliente não informado', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(serviceLabel((string) $quote['service_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="status <?= $quote['status'] === 'approved' ? 'approved' : ($quote['status'] === 'rejected' ? 'sent' : 'pending') ?>"><?= htmlspecialchars(quoteLabel((string) $quote['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentQuotes): ?>
                        <tr><td colspan="3">Nenhum orçamento cadastrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="panel">
        <div class="panel-heading">
            <div><p class="eyebrow">CLIENTES</p><h2>Top faturamento recorrente</h2></div>
            <a href="contratos.php">Ver contratos</a>
        </div>
        <ul class="activity-list">
            <?php foreach ($topClients as $client): ?>
                <li>
                    <b><?= htmlspecialchars($client['company_name'] ?? 'Cliente não informado', ENT_QUOTES, 'UTF-8') ?></b>
                    <span><?= moneyFormat((float) $client['monthly_value']) ?> / mês</span>
                </li>
            <?php endforeach; ?>
            <?php if (!$topClients): ?>
                <li><b>Nenhum cliente com contrato ativo</b><span>Cadastre um contrato para aparecer aqui.</span></li>
            <?php endif; ?>
        </ul>
    </article>
</section>

<style>
    @media (max-width: 980px) {
        .admin-main .content-grid {
            display: block !important;
            grid-template-columns: none !important;
        }

        .admin-main .content-grid > .panel {
            margin-bottom: 18px;
        }

        .admin-main .content-grid .table-wrap {
            overflow-x: auto;
        }

        .admin-main .content-grid .table-wrap table {
            min-width: 420px;
        }
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
