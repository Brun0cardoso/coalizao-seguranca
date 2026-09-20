<?php
$pageTitle = 'Relatórios';
$activePage = 'relatorios';
require_once __DIR__ . '/../php/conexao.php';

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate = $_GET['to'] ?? date('Y-m-t');

if (!strtotime($fromDate) || !strtotime($toDate)) {
    $fromDate = date('Y-m-01');
    $toDate = date('Y-m-t');
}

function contractStatusLabel(string $status): string
{
    return [
        'active' => 'Ativo',
        'pending' => 'Pendente',
        'expiring' => 'Vencendo',
        'expired' => 'Vencido',
        'renewed' => 'Renovado',
        'cancelled' => 'Cancelado',
    ][$status] ?? ucfirst($status);
}

$summary = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN type = 'income' AND status <> 'cancelled' AND due_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' AND status <> 'cancelled' AND due_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) AS total_expense,
        COALESCE(SUM(CASE WHEN type = 'income' AND status <> 'cancelled' AND due_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0)
        - COALESCE(SUM(CASE WHEN type = 'expense' AND status <> 'cancelled' AND due_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) AS total_balance,
        COALESCE(SUM(CASE WHEN type = 'income' AND status = 'pending' THEN amount ELSE 0 END), 0) AS pending_income,
        (SELECT COUNT(*) FROM contracts WHERE status = 'active' AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)) AS active_contracts
     FROM financial_entries
     WHERE due_date BETWEEN ? AND ?"
);
$summary->execute([
    $fromDate,
    $toDate,
    $fromDate,
    $toDate,
    $fromDate,
    $toDate,
    $fromDate,
    $toDate,
    $toDate,
    $fromDate,
    $fromDate,
    $toDate,
]);
$summaryData = $summary->fetch(PDO::FETCH_ASSOC);

$contractBreakdown = $pdo->prepare(
    "SELECT c.contract_number, c.title, cl.company_name, c.status, c.monthly_value, c.end_date
     FROM contracts c
     LEFT JOIN clients cl ON cl.id = c.client_id
     WHERE c.start_date <= :to_date
       AND (c.end_date IS NULL OR c.end_date >= :from_date)
     ORDER BY c.end_date ASC, c.title ASC"
);
$contractBreakdown->execute([
    ':from_date' => $fromDate,
    ':to_date' => $toDate,
]);
$contractsInRange = $contractBreakdown->fetchAll();

$entries = $pdo->prepare(
    "SELECT f.id, f.description, f.category, f.type, f.amount, f.due_date, f.status, c.company_name AS client_name, ct.contract_number
     FROM financial_entries f
     LEFT JOIN clients c ON c.id = f.client_id
     LEFT JOIN contracts ct ON ct.id = f.contract_id
     WHERE f.due_date BETWEEN :from_date AND :to_date
     ORDER BY f.due_date DESC, f.created_at DESC"
);
$entries->execute([
    ':from_date' => $fromDate,
    ':to_date' => $toDate,
]);
$entriesList = $entries->fetchAll();

function moneyFormat(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

include __DIR__ . '/includes/header.php';
?>
<p><a class="button" href="download.php?type=relatorios&from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>">Baixar relatório por período (CSV)</a></p>
<section class="welcome compact">
    <div>
        <p class="eyebrow">RELATÓRIOS</p>
        <h2>Visão consolidada por período.</h2>
        <p>Analise receitas, despesas, vigência e contratos em um único painel.</p>
    </div>
</section>

<form method="get" class="form-panel" style="margin-bottom:22px;">
    <div class="form-heading">
        <h2>Filtrar período</h2>
    </div>
    <div class="form-grid">
        <label>Data inicial
            <input type="date" name="from" value="<?= htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>Data final
            <input type="date" name="to" value="<?= htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8') ?>">
        </label>
    </div>
    <div class="form-actions">
        <span>O relatório considera o período informado.</span>
        <button class="button" type="submit">Aplicar filtro</button>
    </div>
</form>

<section class="stats-grid">
    <article class="stat-card">
        <span>Receitas</span>
        <strong><?= moneyFormat((float) ($summaryData['total_income'] ?? 0)) ?></strong>
        <small>Do período</small>
    </article>
    <article class="stat-card">
        <span>Despesas</span>
        <strong><?= moneyFormat((float) ($summaryData['total_expense'] ?? 0)) ?></strong>
        <small>Do período</small>
    </article>
    <article class="stat-card">
        <span>Saldo</span>
        <strong><?= moneyFormat((float) ($summaryData['total_balance'] ?? 0)) ?></strong>
        <small>Receitas menos despesas</small>
    </article>
    <article class="stat-card">
        <span>Contratos ativos</span>
        <strong><?= (int) ($summaryData['active_contracts'] ?? 0) ?></strong>
        <small>Em vigência</small>
    </article>
</section>

<section class="content-grid report-grid">
    <article class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">MOVIMENTAÇÃO</p>
                <h2>Lançamentos do período</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Vencimento</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entriesList as $entry): ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['description'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($entry['client_name'] ?? 'Sem cliente', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $entry['type'] === 'income' ? 'Receita' : 'Despesa' ?></td>
                            <td><?= date('d/m/Y', strtotime($entry['due_date'])) ?></td>
                            <td><?= $entry['type'] === 'expense' ? '-' : '+' ?><?= moneyFormat((float) $entry['amount']) ?></td>
                            <td><span class="status <?= $entry['status'] === 'paid' ? 'approved' : ($entry['status'] === 'cancelled' ? 'sent' : 'pending') ?>"><?= $entry['status'] === 'paid' ? 'Pago' : ($entry['status'] === 'overdue' ? 'Em atraso' : ($entry['status'] === 'cancelled' ? 'Cancelado' : 'Pendente')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$entriesList): ?>
                        <tr><td colspan="6">Nenhum movimento no período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">CONTRATOS</p>
                <h2>Vigência no período</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Contrato</th>
                        <th>Cliente</th>
                        <th>Status</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contractsInRange as $contract): ?>
                        <tr>
                            <td><?= htmlspecialchars($contract['contract_number'], ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($contract['title'], ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><?= htmlspecialchars($contract['company_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="status <?= $contract['status'] === 'active' ? 'approved' : ($contract['status'] === 'cancelled' ? 'sent' : 'pending') ?>"><?= htmlspecialchars(contractStatusLabel($contract['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>R$ <?= number_format((float) ($contract['monthly_value'] ?? 0), 2, ',', '.') ?></td>
                            <td><?= !empty($contract['end_date']) ? date('d/m/Y', strtotime($contract['end_date'])) : 'Sem fim definido' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$contractsInRange): ?>
                        <tr><td colspan="5">Nenhum contrato no período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<style>
    .content-grid.report-grid {
        display: block !important;
        grid-template-columns: none !important;
    }

    .content-grid.report-grid > .panel {
        width: 100% !important;
        display: block !important;
        margin-bottom: 22px;
    }

    .content-grid.report-grid .table-wrap {
        overflow-x: auto;
    }

    .content-grid.report-grid .table-wrap table {
        width: 100%;
        min-width: 480px;
    }

    @media (max-width: 980px) {
        .content-grid.report-grid {
            display: block !important;
        }

        .content-grid.report-grid > .panel {
            margin-bottom: 18px;
        }

        .content-grid.report-grid .table-wrap table {
            min-width: 420px;
        }
    }

    .form-panel {
        border: 1px solid #dfe7ee;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 8px 18px rgba(8, 43, 78, 0.04);
    }

    .form-panel .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(220px, 1fr));
        gap: 18px;
        margin-top: 16px;
    }

    .form-panel .form-grid label {
        display: grid;
        gap: 8px;
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--ink, #18324b);
    }

    .form-panel .form-grid input,
    .form-panel .form-grid select {
        width: 100%;
        min-height: 46px;
        padding: 10px 12px;
        border: 1px solid #d8e1ea;
        border-radius: 10px;
        background: #f9fbfd;
        color: var(--ink, #18324b);
        font: 15px 'Open Sans', sans-serif;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .form-panel .form-grid input:focus,
    .form-panel .form-grid select:focus {
        outline: none;
        border-color: rgba(212, 160, 23, 0.9);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(212, 160, 23, 0.12);
    }

    .form-panel .form-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 18px;
        flex-wrap: wrap;
    }

    .form-panel .form-actions span {
        color: var(--muted, #607185);
        font-size: 0.9rem;
    }

    @media (max-width: 720px) {
        .form-panel .form-grid {
            grid-template-columns: 1fr;
        }

        .form-panel .form-actions {
            align-items: stretch;
        }

        .form-panel .form-actions .button {
            width: 100%;
        }
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
