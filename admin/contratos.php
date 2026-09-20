<?php
$pageTitle = 'Contratos';
$activePage = 'contratos';
require_once __DIR__ . '/../php/conexao.php';

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

function logContractHistory(PDO $pdo, int $contractId, string $action, ?string $details = null): void
{
    $statement = $pdo->prepare('INSERT INTO contract_history (contract_id, action, details) VALUES (:contract_id, :action, :details)');
    $statement->execute([
        ':contract_id' => $contractId,
        ':action' => $action,
        ':details' => $details,
    ]);
}

function syncContractFinancialEntry(PDO $pdo, int $contractId): void
{
    $statement = $pdo->prepare(
        'SELECT client_id, contract_number, title, start_date, renewal_date, monthly_value, status
         FROM contracts WHERE id = :id LIMIT 1'
    );
    $statement->execute([':id' => $contractId]);
    $contract = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        return;
    }

    $monthlyValue = (float) ($contract['monthly_value'] ?? 0);
    if ($monthlyValue <= 0) {
        $remove = $pdo->prepare('DELETE FROM financial_entries WHERE contract_id = :contract_id');
        $remove->execute([':contract_id' => $contractId]);
        return;
    }

    $dueDate = !empty($contract['renewal_date'])
        ? $contract['renewal_date']
        : (!empty($contract['start_date']) ? $contract['start_date'] : date('Y-m-d'));

    $description = 'Contrato ' . trim((string) ($contract['contract_number'] ?? '')) . ' - ' . trim((string) ($contract['title'] ?? ''));
    $category = 'Contrato';
    $status = in_array($contract['status'] ?? '', ['active', 'renewed', 'pending', 'expiring'], true) ? 'pending' : 'cancelled';

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
        ':client_id' => $contract['client_id'],
        ':description' => $description,
        ':category' => $category,
        ':type' => 'income',
        ':amount' => number_format($monthlyValue, 2, '.', ''),
        ':due_date' => $dueDate,
        ':status' => $status,
        ':notes' => 'Vinculado ao contrato ' . $contract['contract_number'],
    ]);
}

$error = '';
$success = '';
$editingContract = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: null;
if ($editId) {
    $editingStatement = $pdo->prepare('SELECT * FROM contracts WHERE id = :id LIMIT 1');
    $editingStatement->execute([':id' => $editId]);
    $editingContract = $editingStatement->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['form_type'] ?? '') === 'delete') {
        $contractId = filter_input(INPUT_POST, 'contract_id', FILTER_VALIDATE_INT) ?: null;
        if (!$contractId) {
            $error = 'Contrato não encontrado para exclusão.';
        } else {
            try {
                $removeFinancial = $pdo->prepare('DELETE FROM financial_entries WHERE contract_id = :contract_id');
                $removeFinancial->execute([':contract_id' => $contractId]);

                $statement = $pdo->prepare('DELETE FROM contracts WHERE id = :id');
                $statement->execute([':id' => $contractId]);
                logContractHistory($pdo, $contractId, 'delete', 'Contrato removido do painel.');
                $success = 'Contrato removido com sucesso.';
            } catch (PDOException $exception) {
                $error = 'Não foi possível remover o contrato.';
            }
        }
    } elseif (($_POST['form_type'] ?? '') === 'renew') {
        $contractId = filter_input(INPUT_POST, 'contract_id', FILTER_VALIDATE_INT) ?: null;
        if (!$contractId) {
            $error = 'Contrato não encontrado para renovação.';
        } else {
            try {
                $current = $pdo->prepare('SELECT start_date, end_date, renewal_date, status FROM contracts WHERE id = :id LIMIT 1');
                $current->execute([':id' => $contractId]);
                $contract = $current->fetch(PDO::FETCH_ASSOC);
                if (!$contract) {
                    $error = 'Contrato não encontrado.';
                } else {
                    $newEndDate = !empty($contract['end_date']) ? date('Y-m-d', strtotime($contract['end_date'] . ' +1 year')) : date('Y-m-d', strtotime('+1 year'));
                    $renewalDate = date('Y-m-d');
                    $statement = $pdo->prepare('UPDATE contracts SET renewal_date = :renewal_date, end_date = :end_date, status = :status, updated_at = NOW() WHERE id = :id');
                    $statement->execute([
                        ':renewal_date' => $renewalDate,
                        ':end_date' => $newEndDate,
                        ':status' => 'renewed',
                        ':id' => $contractId,
                    ]);
                    syncContractFinancialEntry($pdo, $contractId);
                    logContractHistory($pdo, $contractId, 'renew', 'Contrato renovado com vigência até ' . $newEndDate . '.');
                    $success = 'Contrato renovado com sucesso.';
                }
            } catch (PDOException $exception) {
                $error = 'Não foi possível renovar o contrato.';
            }
        }
    } else {
        $contractId = filter_input(INPUT_POST, 'contract_id', FILTER_VALIDATE_INT) ?: null;
        $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT) ?: null;
        $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT) ?: null;
        $contractNumber = trim($_POST['contract_number'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $serviceType = $_POST['service_type'] ?? 'other';
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $renewalDate = trim($_POST['renewal_date'] ?? '');
        $monthlyValue = str_replace(',', '.', trim($_POST['monthly_value'] ?? ''));
        $status = $_POST['status'] ?? 'active';
        $notes = trim($_POST['notes'] ?? '');

        if ($contractNumber === '' || $title === '' || $startDate === '' || $monthlyValue === '') {
            $error = 'Informe número do contrato, título, início e valor mensal.';
        } elseif (!in_array($serviceType, ['security', 'concierge', 'monitoring', 'access_control', 'other'], true)) {
            $error = 'Tipo de serviço inválido.';
        } elseif (!is_numeric($monthlyValue) || (float) $monthlyValue < 0) {
            $error = 'Informe um valor mensal válido.';
        } elseif (!in_array($status, ['active', 'pending', 'expiring', 'expired', 'renewed', 'cancelled'], true)) {
            $error = 'Status do contrato inválido.';
        } else {
            try {
                if ($contractId) {
                    $statement = $pdo->prepare(
                        'UPDATE contracts SET client_id = :client_id, post_id = :post_id, contract_number = :contract_number, title = :title, service_type = :service_type,
                         start_date = :start_date, end_date = :end_date, renewal_date = :renewal_date, monthly_value = :monthly_value, status = :status, notes = :notes, updated_at = NOW()
                         WHERE id = :id'
                    );
                    $statement->execute([
                        ':client_id' => $clientId,
                        ':post_id' => $postId,
                        ':contract_number' => $contractNumber,
                        ':title' => $title,
                        ':service_type' => $serviceType,
                        ':start_date' => $startDate,
                        ':end_date' => $endDate !== '' ? $endDate : null,
                        ':renewal_date' => $renewalDate !== '' ? $renewalDate : null,
                        ':monthly_value' => number_format((float) $monthlyValue, 2, '.', ''),
                        ':status' => $status,
                        ':notes' => $notes !== '' ? $notes : null,
                        ':id' => $contractId,
                    ]);
                    syncContractFinancialEntry($pdo, $contractId);
                    logContractHistory($pdo, $contractId, 'update', 'Contrato atualizado para status ' . $status . '.');
                    $success = 'Contrato atualizado com sucesso.';
                } else {
                    $statement = $pdo->prepare(
                        'INSERT INTO contracts (client_id, post_id, contract_number, title, service_type, start_date, end_date, renewal_date, monthly_value, status, notes)
                         VALUES (:client_id, :post_id, :contract_number, :title, :service_type, :start_date, :end_date, :renewal_date, :monthly_value, :status, :notes)'
                    );

                    $statement->execute([
                        ':client_id' => $clientId,
                        ':post_id' => $postId,
                        ':contract_number' => $contractNumber,
                        ':title' => $title,
                        ':service_type' => $serviceType,
                        ':start_date' => $startDate,
                        ':end_date' => $endDate !== '' ? $endDate : null,
                        ':renewal_date' => $renewalDate !== '' ? $renewalDate : null,
                        ':monthly_value' => number_format((float) $monthlyValue, 2, '.', ''),
                        ':status' => $status,
                        ':notes' => $notes !== '' ? $notes : null,
                    ]);
                    $newContractId = (int) $pdo->lastInsertId();
                    syncContractFinancialEntry($pdo, $newContractId);
                    logContractHistory($pdo, $newContractId, 'create', 'Contrato criado com status ' . $status . '.');
                    $success = 'Contrato cadastrado com sucesso.';
                }
            } catch (PDOException $exception) {
                $error = 'Não foi possível salvar o contrato. Verifique se o número já existe.';
            }
        }
    }
}

$clients = $pdo->query('SELECT id, company_name FROM clients ORDER BY company_name')->fetchAll();
$posts = $pdo->query('SELECT id, client_id, name FROM service_posts ORDER BY name')->fetchAll();
$statusFilter = $_GET['status'] ?? 'all';
$contractSearch = trim((string) ($_GET['q'] ?? ''));
if (!in_array($statusFilter, ['all', 'active', 'pending', 'expiring', 'expired', 'renewed', 'cancelled'], true)) {
    $statusFilter = 'all';
}

$contractsQuery = 'SELECT c.id, c.contract_number, c.title, c.service_type, c.start_date, c.end_date, c.renewal_date, c.monthly_value, c.status, cl.company_name, p.name AS post_name
     FROM contracts c
     LEFT JOIN clients cl ON cl.id = c.client_id
     LEFT JOIN service_posts p ON p.id = c.post_id';

$whereClauses = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereClauses[] = 'c.status = :status';
    $params[':status'] = $statusFilter;
}

if ($contractSearch !== '') {
    $whereClauses[] = '(c.contract_number LIKE :search OR c.title LIKE :search OR cl.company_name LIKE :search OR p.name LIKE :search)';
    $params[':search'] = '%' . $contractSearch . '%';
}

if ($whereClauses) {
    $contractsQuery .= ' WHERE ' . implode(' AND ', $whereClauses);
}

$contractsQuery .= ' ORDER BY c.created_at DESC';

$contractsStatement = $pdo->prepare($contractsQuery);
$contractsStatement->execute($params);
$contracts = $contractsStatement->fetchAll();

$alerts = $pdo->query(
    "SELECT c.id, c.contract_number, c.title, c.end_date, cl.company_name, c.status
     FROM contracts c
     LEFT JOIN clients cl ON cl.id = c.client_id
     WHERE c.status IN ('active', 'expiring')
       AND c.end_date IS NOT NULL
       AND c.end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY c.end_date ASC"
)->fetchAll();

$contractSummary = $pdo->query(
    "SELECT
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'expiring' THEN 1 ELSE 0 END) AS expiring_count,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired_count,
        SUM(CASE WHEN status IN ('active', 'renewed') THEN monthly_value ELSE 0 END) AS monthly_revenue
     FROM contracts"
)->fetch(PDO::FETCH_ASSOC);

$historyByContract = [];
$historyRows = $pdo->query(
    'SELECT contract_id, action, details, created_at FROM contract_history ORDER BY created_at DESC'
)->fetchAll();
foreach ($historyRows as $entry) {
    $historyByContract[(int) $entry['contract_id']] = $entry;
}

$clientSummary = $pdo->query(
    "SELECT cl.company_name, COUNT(c.id) AS total_contracts, SUM(c.monthly_value) AS total_value
     FROM contracts c
     LEFT JOIN clients cl ON cl.id = c.client_id
     GROUP BY cl.id, cl.company_name
     ORDER BY total_value DESC, total_contracts DESC
     LIMIT 8"
)->fetchAll();

$filterClient = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_INT) ?: null;
$clientFilterSql = '';
$params = [];
if ($filterClient) {
    $clientFilterSql = ' WHERE c.client_id = :client_id';
    $params[':client_id'] = $filterClient;
}

$contractsByClientQuery = $pdo->prepare(
    "SELECT cl.company_name, c.status, COUNT(c.id) AS total_contracts, SUM(c.monthly_value) AS total_value
     FROM contracts c
     LEFT JOIN clients cl ON cl.id = c.client_id
     $clientFilterSql
     GROUP BY cl.id, cl.company_name, c.status
     ORDER BY cl.company_name ASC, c.status ASC"
);
$contractsByClientQuery->execute($params);
$contractsByClient = $contractsByClientQuery->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<p><a class="button" href="download.php?type=contratos">Baixar relatório de contratos (CSV)</a></p>
<p><a class="button secondary" href="download.php?type=contratos_cliente">Baixar resumo por cliente (CSV)</a></p>
<section class="welcome compact"><div><p class="eyebrow">CONTRATOS</p><h2>Controle vigência, renovação e valor dos serviços contratados.</h2><p>Centralize os contratos ativos, vencimentos e cobranças recorrentes.</p></div></section>
<?php if ($success): ?><p class="notice success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<?php if ($alerts): ?>
<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">ALERTAS</p>
            <h2>Contratos próximos do vencimento</h2>
        </div>
    </div>
    <ul class="activity-list">
        <?php foreach ($alerts as $alert): ?>
            <li>
                <b><?= htmlspecialchars($alert['company_name'] ?? 'Cliente não informado', ENT_QUOTES, 'UTF-8') ?></b>
                <span><?= htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8') ?> · vence em <?= date('d/m/Y', strtotime($alert['end_date'])) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="stats-grid">
    <article class="stat-card">
        <span>Ativos</span>
        <strong><?= (int) ($contractSummary['active_count'] ?? 0) ?></strong>
        <small>Contratos em vigência</small>
    </article>
    <article class="stat-card">
        <span>Vencendo</span>
        <strong><?= (int) ($contractSummary['expiring_count'] ?? 0) ?></strong>
        <small>Próximos de vencer</small>
    </article>
    <article class="stat-card">
        <span>Faturamento mensal</span>
        <strong>R$ <?= number_format((float) ($contractSummary['monthly_revenue'] ?? 0), 2, ',', '.') ?></strong>
        <small>Valor recorrente ativo</small>
    </article>
    <article class="stat-card">
        <span>Pendentes</span>
        <strong><?= (int) ($contractSummary['pending_count'] ?? 0) ?></strong>
        <small>Em análise / pendentes</small>
    </article>
</section>

<section class="form-panel">
    <form method="post">
        <input type="hidden" name="contract_id" value="<?= (int) ($editingContract['id'] ?? 0) ?>">
        <div class="form-heading">
            <h2><?= $editingContract ? 'Editar contrato' : 'Novo contrato' ?></h2>
            <p><?= $editingContract ? 'Atualize os dados do contrato e a vigência.' : 'Cadastre a base do serviço e o período de vigência.' ?></p>
        </div>
        <div class="form-grid">
            <label>Cliente
                <select name="client_id">
                    <option value="">Selecione o cliente</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>" <?= ((int) ($editingContract['client_id'] ?? 0) === (int) $client['id']) ? 'selected' : '' ?>><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Posto vinculado
                <select name="post_id">
                    <option value="">Nenhum posto específico</option>
                    <?php foreach ($posts as $post): ?>
                        <option value="<?= (int) $post['id'] ?>" <?= ((int) ($editingContract['post_id'] ?? 0) === (int) $post['id']) ? 'selected' : '' ?>><?= htmlspecialchars($post['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Número do contrato
                <input type="text" name="contract_number" value="<?= htmlspecialchars($editingContract['contract_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label>Nome / título do contrato
                <input type="text" name="title" value="<?= htmlspecialchars($editingContract['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label>Tipo de serviço
                <select name="service_type">
                    <option value="security" <?= (($editingContract['service_type'] ?? 'security') === 'security') ? 'selected' : '' ?>>Vigilância</option>
                    <option value="concierge" <?= (($editingContract['service_type'] ?? 'security') === 'concierge') ? 'selected' : '' ?>>Portaria</option>
                    <option value="monitoring" <?= (($editingContract['service_type'] ?? 'security') === 'monitoring') ? 'selected' : '' ?>>Monitoramento</option>
                    <option value="access_control" <?= (($editingContract['service_type'] ?? 'security') === 'access_control') ? 'selected' : '' ?>>Controle de acesso</option>
                    <option value="other" <?= (($editingContract['service_type'] ?? 'security') === 'other') ? 'selected' : '' ?>>Outro</option>
                </select>
            </label>
            <label>Valor mensal
                <input type="number" name="monthly_value" min="0" step="0.01" value="<?= htmlspecialchars($editingContract['monthly_value'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label>Data de início
                <input type="date" name="start_date" value="<?= htmlspecialchars($editingContract['start_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label>Data de fim
                <input type="date" name="end_date" value="<?= htmlspecialchars($editingContract['end_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>Data de renovação
                <input type="date" name="renewal_date" value="<?= htmlspecialchars($editingContract['renewal_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>Status
                <select name="status">
                    <option value="active" <?= (($editingContract['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Ativo</option>
                    <option value="pending" <?= (($editingContract['status'] ?? 'active') === 'pending') ? 'selected' : '' ?>>Pendente</option>
                    <option value="expiring" <?= (($editingContract['status'] ?? 'active') === 'expiring') ? 'selected' : '' ?>>Vencendo</option>
                    <option value="expired" <?= (($editingContract['status'] ?? 'active') === 'expired') ? 'selected' : '' ?>>Vencido</option>
                    <option value="renewed" <?= (($editingContract['status'] ?? 'active') === 'renewed') ? 'selected' : '' ?>>Renovado</option>
                    <option value="cancelled" <?= (($editingContract['status'] ?? 'active') === 'cancelled') ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </label>
            <label class="full">Observações
                <textarea name="notes" rows="3" placeholder="Detalhes do contrato, cláusulas, horários, etc."><?= htmlspecialchars($editingContract['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
        </div>
        <div class="form-actions">
            <span>Os contratos ficam disponíveis para controle de vigência e renovação.</span>
            <button class="button" type="submit"><?= $editingContract ? 'Salvar alterações' : 'Salvar contrato' ?></button>
            <?php if ($editingContract): ?>
                <a class="button secondary" href="contratos.php">Cancelar edição</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">RESUMO POR CLIENTE</p>
            <h2>Faturamento e volume por cliente</h2>
        </div>
        <form method="get" style="display:flex; align-items:center; gap:8px; margin:0;">
            <label for="clientFilter" style="font-size:12px; color:#607185;">Cliente</label>
            <select id="clientFilter" name="client_id" onchange="this.form.submit()" style="min-width:180px;">
                <option value="">Todos</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int) $client['id'] ?>" <?= $filterClient === (int) $client['id'] ? 'selected' : '' ?>><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Status</th>
                    <th>Contratos</th>
                    <th>Valor mensal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contractsByClient as $clientRow): ?>
                    <tr>
                        <td><?= htmlspecialchars($clientRow['company_name'] ?? 'Cliente não informado', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(contractStatusLabel($clientRow['status']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $clientRow['total_contracts'] ?></td>
                        <td>R$ <?= number_format((float) ($clientRow['total_value'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$contractsByClient): ?>
                    <tr><td colspan="4">Nenhum contrato para este filtro.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">LISTAGEM</p>
            <h2>Contratos cadastrados</h2>
        </div>
        <form method="get" style="display:flex; align-items:center; gap:8px; margin:0; flex-wrap:wrap;">
            <input class="search" type="search" name="q" value="<?= htmlspecialchars($contractSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por contrato, cliente ou posto" style="min-width:220px; max-width:260px;">
            <label for="statusFilter" style="font-size:12px; color:#607185;">Status</label>
            <select id="statusFilter" name="status" onchange="this.form.submit()" style="min-width:140px;">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativos</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendentes</option>
                <option value="expiring" <?= $statusFilter === 'expiring' ? 'selected' : '' ?>>Vencendo</option>
                <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Vencidos</option>
                <option value="renewed" <?= $statusFilter === 'renewed' ? 'selected' : '' ?>>Renovados</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelados</option>
            </select>
            <?php if ($contractSearch !== '' || $statusFilter !== 'all'): ?><button class="button secondary" type="submit" style="padding:8px 12px;">Filtrar</button><a class="button secondary" href="contratos.php" style="padding:8px 12px;">Limpar</a><?php endif; ?>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Contrato</th>
                    <th>Cliente</th>
                    <th>Posto</th>
                    <th>Vigência</th>
                    <th>Valor</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$contracts): ?>
                    <tr><td colspan="6">Nenhum contrato cadastrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td><?= htmlspecialchars($contract['contract_number'], ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($contract['title'], ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><?= htmlspecialchars($contract['company_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($contract['post_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= date('d/m/Y', strtotime($contract['start_date'])) ?><?php if ($contract['end_date']): ?> - <?= date('d/m/Y', strtotime($contract['end_date'])) ?><?php endif; ?></td>
                            <td>R$ <?= number_format((float) $contract['monthly_value'], 2, ',', '.') ?></td>
                            <td>
                                <span class="status <?= $contract['status'] === 'active' ? 'approved' : ($contract['status'] === 'cancelled' ? 'sent' : 'pending') ?>"><?= htmlspecialchars(contractStatusLabel($contract['status']), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php $lastHistory = $historyByContract[(int) $contract['id']] ?? null; ?>
                                <?php if ($lastHistory): ?>
                                    <small style="display:block; margin-top:6px; color:#607185;">Último: <?= htmlspecialchars($lastHistory['action'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                                <div style="display:flex; gap:6px; margin-top:8px; flex-wrap:wrap;">
                                    <a class="button" href="contratos.php?edit=<?= (int) $contract['id'] ?>" style="padding:6px 10px; font-size:12px;">Editar</a>
                                    <form method="post" style="display:inline-block; margin:0;">
                                        <input type="hidden" name="form_type" value="renew">
                                        <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
                                        <button class="button" type="submit" style="padding:6px 10px; font-size:12px;">Renovar</button>
                                    </form>
                                    <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Deseja remover este contrato?');">
                                        <input type="hidden" name="form_type" value="delete">
                                        <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
                                        <button class="button" type="submit" style="padding:6px 10px; font-size:12px;">Excluir</button>
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
