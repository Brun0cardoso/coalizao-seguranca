<?php
$pageTitle = 'Orçamentos';
$activePage = 'orcamentos';
require_once __DIR__ . '/../php/conexao.php';

function quoteStatusLabel(string $status): string
{
    return [
        'draft' => 'Rascunho',
        'under_review' => 'Em análise',
        'sent' => 'Enviado',
        'approved' => 'Aprovado',
        'rejected' => 'Recusado',
        'cancelled' => 'Cancelado',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function quoteStatusClass(string $status): string
{
    return [
        'draft' => 'pending',
        'under_review' => 'pending',
        'sent' => 'sent',
        'approved' => 'approved',
        'rejected' => 'sent',
        'cancelled' => 'pending',
    ][$status] ?? 'pending';
}

function syncQuoteContractFinancialEntry(PDO $pdo, int $contractId): void
{
    $contract = $pdo->prepare('SELECT client_id, contract_number, title, start_date, renewal_date, monthly_value, status FROM contracts WHERE id = :id LIMIT 1');
    $contract->execute([':id' => $contractId]);
    $row = $contract->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    $monthlyValue = (float) ($row['monthly_value'] ?? 0);
    if ($monthlyValue <= 0) {
        $remove = $pdo->prepare('DELETE FROM financial_entries WHERE contract_id = :contract_id');
        $remove->execute([':contract_id' => $contractId]);
        return;
    }

    $dueDate = !empty($row['renewal_date']) ? $row['renewal_date'] : (!empty($row['start_date']) ? $row['start_date'] : date('Y-m-d'));
    $description = 'Contrato ' . trim((string) ($row['contract_number'] ?? '')) . ' - ' . trim((string) ($row['title'] ?? ''));
    $status = in_array($row['status'] ?? '', ['active', 'renewed', 'pending', 'expiring'], true) ? 'pending' : 'cancelled';

    $upsert = $pdo->prepare(
        'INSERT INTO financial_entries (contract_id, client_id, description, category, type, amount, due_date, status, notes)
         VALUES (:contract_id, :client_id, :description, :category, :type, :amount, :due_date, :status, :notes)
         ON DUPLICATE KEY UPDATE
            client_id = VALUES(client_id),
            description = VALUES(description),
            category = VALUES(category),
            type = VALUES(type),
            amount = VALUES(amount),
            due_date = VALUES(due_date),
            status = VALUES(status),
            notes = VALUES(notes),
            updated_at = NOW()'
    );

    $upsert->execute([
        ':contract_id' => $contractId,
        ':client_id' => $row['client_id'],
        ':description' => $description,
        ':category' => 'Contrato',
        ':type' => 'income',
        ':amount' => number_format($monthlyValue, 2, '.', ''),
        ':due_date' => $dueDate,
        ':status' => $status,
        ':notes' => 'Vinculado ao contrato ' . ($row['contract_number'] ?? ''),
    ]);
}

function logQuoteContractHistory(PDO $pdo, int $contractId, string $action, ?string $details = null): void
{
    $statement = $pdo->prepare('INSERT INTO contract_history (contract_id, action, details) VALUES (:contract_id, :action, :details)');
    $statement->execute([
        ':contract_id' => $contractId,
        ':action' => $action,
        ':details' => $details,
    ]);
}

$error = '';
$success = '';
$editingQuote = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: null;
if ($editId) {
    $editStatement = $pdo->prepare('SELECT * FROM quotes WHERE id = :id LIMIT 1');
    $editStatement->execute([':id' => $editId]);
    $editingQuote = $editStatement->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'create';

    if ($formType === 'convert') {
        $quoteId = filter_input(INPUT_POST, 'quote_id', FILTER_VALIDATE_INT) ?: null;
        if (!$quoteId) {
            $error = 'Orçamento não encontrado para conversão.';
        } else {
            $quoteStatement = $pdo->prepare(
                'SELECT q.*, c.company_name FROM quotes q LEFT JOIN clients c ON c.id = q.client_id WHERE q.id = :id LIMIT 1'
            );
            $quoteStatement->execute([':id' => $quoteId]);
            $quote = $quoteStatement->fetch(PDO::FETCH_ASSOC);
            if (!$quote) {
                $error = 'Orçamento não encontrado.';
            } elseif (($quote['status'] ?? '') !== 'approved') {
                $error = 'A conversão em contrato só pode ocorrer quando o orçamento estiver aprovado.';
            } else {
                try {
                    $contractNumber = 'CT-' . date('Y') . '-' . str_pad((string) $quoteId, 4, '0', STR_PAD_LEFT);
                    $serviceLabel = ucfirst(str_replace('_', ' ', (string) ($quote['service_type'] ?? 'other')));
                    $contractTitle = trim((string) ($quote['company_name'] ?? $quote['contact_name'])) !== ''
                        ? trim((string) ($quote['company_name'] ?? $quote['contact_name'])) . ' - ' . $serviceLabel
                        : 'Contrato ' . $serviceLabel;

                    $insertContract = $pdo->prepare(
                        'INSERT INTO contracts (client_id, contract_number, title, service_type, start_date, end_date, monthly_value, status, notes)
                         VALUES (:client_id, :contract_number, :title, :service_type, :start_date, :end_date, :monthly_value, :status, :notes)'
                    );
                    $insertContract->execute([
                        ':client_id' => $quote['client_id'],
                        ':contract_number' => $contractNumber,
                        ':title' => $contractTitle,
                        ':service_type' => $quote['service_type'] ?? 'other',
                        ':start_date' => date('Y-m-d'),
                        ':end_date' => date('Y-m-d', strtotime('+1 year')),
                        ':monthly_value' => number_format((float) ($quote['estimated_amount'] ?? 0), 2, '.', ''),
                        ':status' => 'pending',
                        ':notes' => trim((string) ($quote['notes'] ?? '')) !== '' ? 'Gerado a partir do orçamento #' . $quoteId . '. ' . $quote['notes'] : 'Gerado a partir do orçamento #' . $quoteId . '.',
                    ]);
                    $contractId = (int) $pdo->lastInsertId();
                    syncQuoteContractFinancialEntry($pdo, $contractId);
                    logQuoteContractHistory($pdo, $contractId, 'created_from_quote', 'Contrato gerado a partir do orçamento #' . $quoteId . '.');
                    $success = 'Orçamento convertido em contrato com sucesso.';
                } catch (PDOException $exception) {
                    $error = 'Não foi possível converter o orçamento em contrato.';
                }
            }
        }
    } elseif ($formType === 'delete') {
        $quoteId = filter_input(INPUT_POST, 'quote_id', FILTER_VALIDATE_INT) ?: null;
        if (!$quoteId) {
            $error = 'Orçamento não encontrado para exclusão.';
        } else {
            try {
                $deleteStatement = $pdo->prepare('DELETE FROM quotes WHERE id = :id');
                $deleteStatement->execute([':id' => $quoteId]);
                $success = 'Orçamento removido com sucesso.';
            } catch (PDOException $exception) {
                $error = 'Não foi possível remover o orçamento.';
            }
        }
    } else {
        $quoteId = filter_input(INPUT_POST, 'quote_id', FILTER_VALIDATE_INT) ?: null;
        $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT) ?: null;
        $contactName = trim($_POST['contact_name'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $serviceType = $_POST['service_type'] ?? 'other';
        $quantityPosts = max(1, (int) ($_POST['quantity_posts'] ?? 1));
        $billingPeriod = $_POST['billing_period'] ?? 'monthly';
        $estimatedAmount = str_replace(',', '.', trim($_POST['estimated_amount'] ?? ''));
        $status = $_POST['status'] ?? 'draft';
        $notes = trim($_POST['notes'] ?? '');

        if ($contactName === '' || $contactPhone === '') {
            $error = 'Informe o nome do contato e o telefone.';
        } elseif ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Informe um e-mail válido ou deixe em branco.';
        } elseif (!in_array($serviceType, ['security', 'concierge', 'monitoring', 'access_control', 'other'], true)) {
            $error = 'Tipo de serviço inválido.';
        } elseif (!in_array($billingPeriod, ['monthly', 'eventual'], true)) {
            $error = 'Período de cobrança inválido.';
        } elseif (!in_array($status, ['draft', 'under_review', 'sent', 'approved', 'rejected', 'cancelled'], true)) {
            $error = 'Status do orçamento inválido.';
        } elseif ($estimatedAmount !== '' && (!is_numeric($estimatedAmount) || (float) $estimatedAmount < 0)) {
            $error = 'Informe um valor estimado válido.';
        } else {
            try {
                if ($quoteId) {
                    $statement = $pdo->prepare(
                        'UPDATE quotes SET client_id = :client_id, contact_name = :contact_name, contact_email = :contact_email, contact_phone = :contact_phone,
                         service_type = :service_type, quantity_posts = :quantity_posts, billing_period = :billing_period, estimated_amount = :estimated_amount,
                         status = :status, notes = :notes, updated_at = NOW()
                         WHERE id = :id'
                    );
                    $statement->execute([
                        ':client_id' => $clientId,
                        ':contact_name' => $contactName,
                        ':contact_email' => $contactEmail !== '' ? $contactEmail : null,
                        ':contact_phone' => $contactPhone,
                        ':service_type' => $serviceType,
                        ':quantity_posts' => $quantityPosts,
                        ':billing_period' => $billingPeriod,
                        ':estimated_amount' => $estimatedAmount !== '' ? number_format((float) $estimatedAmount, 2, '.', '') : null,
                        ':status' => $status,
                        ':notes' => $notes !== '' ? $notes : null,
                        ':id' => $quoteId,
                    ]);
                    $success = 'Orçamento atualizado com sucesso.';
                } else {
                    $statement = $pdo->prepare(
                        'INSERT INTO quotes (client_id, contact_name, contact_email, contact_phone, service_type, quantity_posts, billing_period, estimated_amount, status, notes)
                         VALUES (:client_id, :contact_name, :contact_email, :contact_phone, :service_type, :quantity_posts, :billing_period, :estimated_amount, :status, :notes)'
                    );
                    $statement->execute([
                        ':client_id' => $clientId,
                        ':contact_name' => $contactName,
                        ':contact_email' => $contactEmail !== '' ? $contactEmail : null,
                        ':contact_phone' => $contactPhone,
                        ':service_type' => $serviceType,
                        ':quantity_posts' => $quantityPosts,
                        ':billing_period' => $billingPeriod,
                        ':estimated_amount' => $estimatedAmount !== '' ? number_format((float) $estimatedAmount, 2, '.', '') : null,
                        ':status' => $status,
                        ':notes' => $notes !== '' ? $notes : null,
                    ]);
                    $success = 'Orçamento cadastrado com sucesso.';
                }
            } catch (PDOException $exception) {
                $error = 'Não foi possível salvar o orçamento. Verifique os dados informados.';
            }
        }
    }
}

$filterStatus = $_GET['status'] ?? '';
$filterClient = $_GET['client'] ?? '';

$clients = $pdo->query('SELECT id, company_name FROM clients ORDER BY company_name')->fetchAll();

$quoteQuery = 'SELECT q.id, q.contact_name, q.contact_email, q.contact_phone, q.service_type, q.quantity_posts, q.billing_period, q.estimated_amount, q.status, q.created_at, c.company_name
               FROM quotes q
               LEFT JOIN clients c ON c.id = q.client_id
               WHERE 1 = 1';
$params = [];
if ($filterStatus !== '') {
    $quoteQuery .= ' AND q.status = :status';
    $params[':status'] = $filterStatus;
}
if ($filterClient !== '') {
    $quoteQuery .= ' AND q.client_id = :client_id';
    $params[':client_id'] = (int) $filterClient;
}
$quoteQuery .= ' ORDER BY q.created_at DESC LIMIT 50';

$quoteStatement = $pdo->prepare($quoteQuery);
$quoteStatement->execute($params);
$quotes = $quoteStatement->fetchAll(PDO::FETCH_ASSOC);

$stats = $pdo->query(
    'SELECT
        COUNT(*) AS total_quotes,
        SUM(CASE WHEN status IN (\'draft\', \'under_review\', \'sent\') THEN 1 ELSE 0 END) AS open_quotes,
        SUM(CASE WHEN status = \'approved\' THEN 1 ELSE 0 END) AS approved_quotes
     FROM quotes'
)->fetch(PDO::FETCH_ASSOC);

$formValues = [
    'quote_id' => $editingQuote['id'] ?? '',
    'client_id' => $editingQuote['client_id'] ?? '',
    'contact_name' => $editingQuote['contact_name'] ?? '',
    'contact_email' => $editingQuote['contact_email'] ?? '',
    'contact_phone' => $editingQuote['contact_phone'] ?? '',
    'service_type' => $editingQuote['service_type'] ?? 'security',
    'quantity_posts' => $editingQuote['quantity_posts'] ?? 1,
    'billing_period' => $editingQuote['billing_period'] ?? 'monthly',
    'estimated_amount' => $editingQuote['estimated_amount'] ?? '',
    'status' => $editingQuote['status'] ?? 'draft',
    'notes' => $editingQuote['notes'] ?? '',
];

include __DIR__ . '/includes/header.php';
?>
<p><a class="button" href="download.php?type=orcamentos">Baixar orçamentos (CSV)</a></p>
<?php if ($success): ?><p class="notice success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<section class="welcome compact"><div><p class="eyebrow">ORÇAMENTOS</p><h2>Crie propostas com agilidade e acompanhe o pipeline.</h2><p>Capture a demanda, organize a etapa atual e mantenha o histórico de propostas em um só lugar.</p></div></section>
<section class="stats-grid" aria-label="Resumo de orçamentos">
    <article class="stat-card"><span>Total</span><strong><?= (int) ($stats['total_quotes'] ?? 0) ?></strong><small>Orçamentos cadastrados</small></article>
    <article class="stat-card"><span>Em aberto</span><strong><?= (int) ($stats['open_quotes'] ?? 0) ?></strong><small>Rascunho, revisão e enviados</small></article>
    <article class="stat-card"><span>Aprovados</span><strong><?= (int) ($stats['approved_quotes'] ?? 0) ?></strong><small>Propostas aceitas</small></article>
</section>
<section class="form-panel">
    <form method="post">
        <?php if ($editingQuote): ?><input type="hidden" name="quote_id" value="<?= (int) $editingQuote['id'] ?>"><?php endif; ?>
        <input type="hidden" name="form_type" value="<?= $editingQuote ? 'update' : 'create' ?>">
        <div class="form-heading">
            <h2><?= $editingQuote ? 'Editar orçamento' : 'Novo orçamento' ?></h2>
            <p><?= $editingQuote ? 'Atualize os dados da proposta selecionada.' : 'Preencha os dados para montar uma proposta de serviço.' ?></p>
        </div>
        <div class="form-grid">
            <label>Cliente vinculado<select name="client_id"><option value="">Cliente não vinculado</option><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>" <?= ((int) ($formValues['client_id'] ?? 0) === (int) $client['id']) ? 'selected' : '' ?>><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
            <label>Contato<input type="text" name="contact_name" required placeholder="Nome do responsável" value="<?= htmlspecialchars($formValues['contact_name'], ENT_QUOTES, 'UTF-8') ?>"></label>
            <label>E-mail<input type="email" name="contact_email" placeholder="contato@empresa.com" value="<?= htmlspecialchars($formValues['contact_email'], ENT_QUOTES, 'UTF-8') ?>"></label>
            <label>Telefone<input type="text" name="contact_phone" required placeholder="(11) 99999-9999" value="<?= htmlspecialchars($formValues['contact_phone'], ENT_QUOTES, 'UTF-8') ?>"></label>
            <label>Tipo de serviço<select name="service_type"><option value="security" <?= $formValues['service_type'] === 'security' ? 'selected' : '' ?>>Vigilância patrimonial</option><option value="concierge" <?= $formValues['service_type'] === 'concierge' ? 'selected' : '' ?>>Portaria e recepção</option><option value="monitoring" <?= $formValues['service_type'] === 'monitoring' ? 'selected' : '' ?>>Monitoramento</option><option value="access_control" <?= $formValues['service_type'] === 'access_control' ? 'selected' : '' ?>>Controle de acesso</option><option value="other" <?= $formValues['service_type'] === 'other' ? 'selected' : '' ?>>Outro</option></select></label>
            <label>Quantidade de postos<input type="number" name="quantity_posts" min="1" value="<?= (int) $formValues['quantity_posts'] ?>"></label>
            <label>Período<select name="billing_period"><option value="monthly" <?= $formValues['billing_period'] === 'monthly' ? 'selected' : '' ?>>Mensal</option><option value="eventual" <?= $formValues['billing_period'] === 'eventual' ? 'selected' : '' ?>>Eventual</option></select></label>
            <label>Valor estimado<input type="text" name="estimated_amount" placeholder="0,00" value="<?= htmlspecialchars($formValues['estimated_amount'], ENT_QUOTES, 'UTF-8') ?>"></label>
            <label>Status<select name="status"><option value="draft" <?= $formValues['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option><option value="under_review" <?= $formValues['status'] === 'under_review' ? 'selected' : '' ?>>Em análise</option><option value="sent" <?= $formValues['status'] === 'sent' ? 'selected' : '' ?>>Enviado</option><option value="approved" <?= $formValues['status'] === 'approved' ? 'selected' : '' ?>>Aprovado</option><option value="rejected" <?= $formValues['status'] === 'rejected' ? 'selected' : '' ?>>Recusado</option><option value="cancelled" <?= $formValues['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option></select></label>
            <label class="full">Observações<textarea name="notes" rows="4" placeholder="Descreva a necessidade do cliente, horários, exigências e observações relevantes"><?= htmlspecialchars($formValues['notes'], ENT_QUOTES, 'UTF-8') ?></textarea></label>
        </div>
        <div class="form-actions"><span>Os dados entram na base de orçamentos para gestão comercial.</span><button class="button" type="submit"><?= $editingQuote ? 'Atualizar orçamento' : 'Salvar orçamento' ?></button></div>
    </form>
</section>
<section class="panel">
    <div class="panel-heading">
        <div><p class="eyebrow">HISTÓRICO</p><h2>Últimos orçamentos</h2></div>
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <select name="status" aria-label="Filtrar por status">
                <option value="">Todos</option>
                <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                <option value="under_review" <?= $filterStatus === 'under_review' ? 'selected' : '' ?>>Em análise</option>
                <option value="sent" <?= $filterStatus === 'sent' ? 'selected' : '' ?>>Enviado</option>
                <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Aprovado</option>
                <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Recusado</option>
                <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
            </select>
            <select name="client" aria-label="Filtrar por cliente">
                <option value="">Todos os clientes</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int) $client['id'] ?>" <?= (string) $filterClient === (string) $client['id'] ? 'selected' : '' ?>><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button secondary" type="submit">Filtrar</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Cliente</th><th>Contato</th><th>Serviço</th><th>Valor</th><th>Status</th><th>Data</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php if (!$quotes): ?>
                    <tr><td colspan="7">Nenhum orçamento cadastrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td><?= htmlspecialchars($quote['company_name'] ?? 'Cliente não informado', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($quote['contact_name'], ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($quote['contact_phone'], ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><?= htmlspecialchars(str_replace('_', ' ', $quote['service_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $quote['estimated_amount'] !== null ? 'R$ ' . number_format((float) $quote['estimated_amount'], 2, ',', '.') : '—' ?></td>
                            <td><span class="status <?= quoteStatusClass((string) $quote['status']) ?>"><?= htmlspecialchars(quoteStatusLabel((string) $quote['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= date('d/m/Y', strtotime($quote['created_at'])) ?></td>
                            <td>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <a class="button secondary" href="orcamentos.php?edit=<?= (int) $quote['id'] ?>">Editar</a>
                                    <?php if (($quote['status'] ?? '') === 'approved'): ?>
                                        <form method="post" onsubmit="return confirm('Converter este orçamento aprovado em contrato?');" style="margin:0;">
                                            <input type="hidden" name="form_type" value="convert">
                                            <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                            <button class="button" type="submit">Gerar contrato</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" onsubmit="return confirm('Deseja excluir este orçamento?');" style="margin:0;">
                                        <input type="hidden" name="form_type" value="delete">
                                        <input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>">
                                        <button class="button secondary" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
