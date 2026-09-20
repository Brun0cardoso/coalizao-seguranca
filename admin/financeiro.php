<?php
$pageTitle = 'Controle financeiro';
$activePage = 'financeiro';
require_once __DIR__ . '/../php/conexao.php';

$error = '';
$success = '';
$editingEntry = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: null;
if ($editId) {
	$editStatement = $pdo->prepare('SELECT * FROM financial_entries WHERE id = :id LIMIT 1');
	$editStatement->execute([':id' => $editId]);
	$editingEntry = $editStatement->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$formType = $_POST['form_type'] ?? 'create';

	if ($formType === 'delete') {
		$entryId = filter_input(INPUT_POST, 'entry_id', FILTER_VALIDATE_INT) ?: null;
		if (!$entryId) {
			$error = 'Lançamento não encontrado para exclusão.';
		} else {
			try {
				$deleteStatement = $pdo->prepare('DELETE FROM financial_entries WHERE id = :id');
				$deleteStatement->execute([':id' => $entryId]);
				$success = 'Lançamento removido com sucesso.';
			} catch (PDOException $exception) {
				$error = 'Não foi possível remover o lançamento.';
			}
		}
	} else {
		$entryId = filter_input(INPUT_POST, 'entry_id', FILTER_VALIDATE_INT) ?: null;
		$description = trim($_POST['description'] ?? '');
		$category = trim($_POST['category'] ?? '');
		$type = $_POST['type'] ?? '';
		$amount = str_replace(',', '.', trim($_POST['amount'] ?? ''));
		$dueDate = trim($_POST['due_date'] ?? '');
		$status = $_POST['status'] ?? 'pending';
		$clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT) ?: null;
		$notes = trim($_POST['notes'] ?? '');

		if ($description === '' || $category === '' || $amount === '' || $dueDate === '') {
			$error = 'Informe descrição, categoria, valor e vencimento.';
		} elseif (!is_numeric($amount) || (float) $amount <= 0) {
			$error = 'Informe um valor maior que zero.';
		} elseif (!in_array($type, ['income', 'expense'], true) || !in_array($status, ['pending', 'paid', 'overdue', 'cancelled'], true)) {
			$error = 'Tipo ou situação inválida.';
		} else {
			try {
				if ($entryId) {
					$statement = $pdo->prepare(
						'UPDATE financial_entries SET client_id = :client_id, description = :description, category = :category, type = :type, amount = :amount, due_date = :due_date, status = :status, notes = :notes, updated_at = NOW() WHERE id = :id'
					);
					$statement->execute([
						':client_id' => $clientId,
						':description' => $description,
						':category' => $category,
						':type' => $type,
						':amount' => number_format((float) $amount, 2, '.', ''),
						':due_date' => $dueDate,
						':status' => $status,
						':notes' => $notes !== '' ? $notes : null,
						':id' => $entryId,
					]);
					$success = 'Lançamento atualizado com sucesso.';
				} else {
					$statement = $pdo->prepare(
						'INSERT INTO financial_entries (client_id, description, category, type, amount, due_date, status, notes)
						 VALUES (:client_id, :description, :category, :type, :amount, :due_date, :status, :notes)'
					);
					$statement->execute([
						':client_id' => $clientId,
						':description' => $description,
						':category' => $category,
						':type' => $type,
						':amount' => number_format((float) $amount, 2, '.', ''),
						':due_date' => $dueDate,
						':status' => $status,
						':notes' => $notes !== '' ? $notes : null,
					]);
					$success = 'Lançamento adicionado com sucesso.';
				}
			} catch (PDOException $exception) {
				$error = 'Não foi possível salvar o lançamento.';
			}
		}
	}
}

$clients = $pdo->query('SELECT id, company_name FROM clients ORDER BY company_name')->fetchAll();

$filterClient = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_INT) ?: null;
$filterType = $_GET['type'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';
$searchTerm = trim((string) ($_GET['q'] ?? ''));
if (!in_array($filterType, ['all', 'income', 'expense'], true)) {
	$filterType = 'all';
}
if (!in_array($filterStatus, ['all', 'pending', 'paid', 'overdue', 'cancelled'], true)) {
	$filterStatus = 'all';
}

$where = [];
$params = [];
if ($filterClient) {
	$where[] = 'f.client_id = :client_id';
	$params[':client_id'] = $filterClient;
}
if ($filterType !== 'all') {
	$where[] = 'f.type = :type';
	$params[':type'] = $filterType;
}
if ($filterStatus !== 'all') {
	$where[] = 'f.status = :status';
	$params[':status'] = $filterStatus;
}
if ($searchTerm !== '') {
	$where[] = '(f.description LIKE :search OR f.category LIKE :search OR c.company_name LIKE :search OR ct.contract_number LIKE :search)';
	$params[':search'] = '%' . $searchTerm . '%';
}

$entriesSql = 'SELECT f.id, f.contract_id, f.description, f.category, f.type, f.amount, f.due_date, f.status, c.company_name, ct.contract_number, ct.title AS contract_title
	 FROM financial_entries f
	 LEFT JOIN clients c ON c.id = f.client_id
	 LEFT JOIN contracts ct ON ct.id = f.contract_id';
if ($where) {
	$entriesSql .= ' WHERE ' . implode(' AND ', $where);
}
$entriesSql .= ' ORDER BY f.due_date DESC, f.created_at DESC';
$entriesStatement = $pdo->prepare($entriesSql);
$entriesStatement->execute($params);
$entries = $entriesStatement->fetchAll();

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$summary = $pdo->prepare(
	"SELECT type, COALESCE(SUM(amount), 0) AS total FROM financial_entries
	 WHERE due_date BETWEEN :start AND :end AND status <> 'cancelled' GROUP BY type"
);
$summary->execute([':start' => $monthStart, ':end' => $monthEnd]);
$totals = ['income' => 0, 'expense' => 0];
foreach ($summary as $row) $totals[$row['type']] = (float) $row['total'];

$clientRevenueSummary = $pdo->query(
	"SELECT cl.company_name,
	        COUNT(c.id) AS total_contratos,
	        COALESCE(SUM(c.monthly_value), 0) AS valor_contratado,
	        COALESCE(SUM(CASE WHEN fe.type = 'income' AND fe.status <> 'cancelled' THEN fe.amount ELSE 0 END), 0) AS receitas_programadas,
	        MAX(fe.due_date) AS ultima_vencimento
	 FROM clients cl
	 LEFT JOIN contracts c ON c.client_id = cl.id
	 LEFT JOIN financial_entries fe ON fe.client_id = cl.id AND fe.type = 'income'
	 GROUP BY cl.id, cl.company_name
	 ORDER BY valor_contratado DESC, total_contratos DESC
	 LIMIT 6"
)->fetchAll();

function financeMoney(float $value): string
{
	return 'R$ ' . number_format($value, 2, ',', '.');
}

function financeTypeLabel(string $value): string
{
	return ['income' => 'Receita', 'expense' => 'Despesa'][$value] ?? ucfirst($value);
}

function financeStatusLabel(string $value): string
{
	return ['pending' => 'Pendente', 'paid' => 'Pago', 'overdue' => 'Em atraso', 'cancelled' => 'Cancelado'][$value] ?? ucfirst($value);
}

$formValues = [
	'entry_id' => $editingEntry['id'] ?? '',
	'description' => $editingEntry['description'] ?? ($_POST['description'] ?? ''),
	'category' => $editingEntry['category'] ?? ($_POST['category'] ?? ''),
	'type' => $editingEntry['type'] ?? ($_POST['type'] ?? 'income'),
	'amount' => $editingEntry['amount'] ?? ($_POST['amount'] ?? ''),
	'due_date' => $editingEntry['due_date'] ?? ($_POST['due_date'] ?? ''),
	'status' => $editingEntry['status'] ?? ($_POST['status'] ?? 'pending'),
	'client_id' => $editingEntry['client_id'] ?? ($_POST['client_id'] ?? ''),
	'notes' => $editingEntry['notes'] ?? ($_POST['notes'] ?? ''),
];

include __DIR__ . '/includes/header.php';
?>
<p><a class="button" href="download.php?type=financeiro">Baixar relatório financeiro (CSV)</a></p>
<section class="welcome compact"><div><p class="eyebrow">FINANCEIRO</p><h2>Organize receitas, despesas e vencimentos.</h2><p>Registre os lançamentos e acompanhe o fluxo financeiro.</p></div></section>
<?php if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso'): ?><p class="notice success">Lançamento adicionado com sucesso.</p><?php endif; ?>
<?php if ($success): ?><p class="notice success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<section class="form-panel"><form method="post"><input type="hidden" name="form_type" value="<?= $editingEntry ? 'update' : 'create' ?>"><?php if ($editingEntry): ?><input type="hidden" name="entry_id" value="<?= (int) $editingEntry['id'] ?>"><?php endif; ?><div class="form-heading"><h2><?= $editingEntry ? 'Editar lançamento' : 'Adicionar lançamento' ?></h2><p><?= $editingEntry ? 'Atualize os dados do lançamento selecionado.' : 'Informe os dados da receita ou despesa.' ?></p></div><div class="form-grid"><label>Descrição<input type="text" name="description" required value="<?= htmlspecialchars($formValues['description'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Categoria<input type="text" name="category" placeholder="Ex.: Folha de pagamento" required value="<?= htmlspecialchars($formValues['category'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Tipo<select name="type" required><option value="income" <?= $formValues['type'] === 'income' ? 'selected' : '' ?>>Receita</option><option value="expense" <?= $formValues['type'] === 'expense' ? 'selected' : '' ?>>Despesa</option></select></label><label>Valor<input type="number" name="amount" min="0.01" step="0.01" required value="<?= htmlspecialchars($formValues['amount'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Vencimento<input type="date" name="due_date" required value="<?= htmlspecialchars($formValues['due_date'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Situação<select name="status"><option value="pending" <?= $formValues['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option><option value="paid" <?= $formValues['status'] === 'paid' ? 'selected' : '' ?>>Pago</option><option value="overdue" <?= $formValues['status'] === 'overdue' ? 'selected' : '' ?>>Em atraso</option><option value="cancelled" <?= $formValues['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option></select></label><label>Cliente<select name="client_id"><option value="">Nenhum cliente</option><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>" <?= ((string) $formValues['client_id'] === (string) $client['id']) ? 'selected' : '' ?>><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label class="full">Observações<textarea name="notes" rows="3"><?= htmlspecialchars($formValues['notes'], ENT_QUOTES, 'UTF-8') ?></textarea></label></div><div class="form-actions"><span>O lançamento será salvo no banco de dados.</span><button class="button" type="submit"><?= $editingEntry ? 'Salvar alterações' : 'Adicionar lançamento' ?></button><?php if ($editingEntry): ?><a class="button secondary" href="financeiro.php">Cancelar edição</a><?php endif; ?></div></form></section>
<p><a class="button" href="download.php?type=resumo_cliente">Baixar resumo executivo por cliente (CSV)</a></p>
<section class="stats-grid"><article class="stat-card"><span>Receitas do mês</span><strong><?= financeMoney($totals['income']) ?></strong><small>Vencimentos do mês atual</small></article><article class="stat-card"><span>Despesas do mês</span><strong><?= financeMoney($totals['expense']) ?></strong><small>Vencimentos do mês atual</small></article><article class="stat-card"><span>Saldo projetado</span><strong><?= financeMoney($totals['income'] - $totals['expense']) ?></strong><small>Receitas menos despesas</small></article></section>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">CLIENTES</p><h2>Resumo de contrato e faturamento</h2></div></div><div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Contratos</th><th>Valor contratado</th><th>Receitas previstas</th><th>Último vencimento</th></tr></thead><tbody><?php foreach ($clientRevenueSummary as $row): ?><tr><td><?= htmlspecialchars($row['company_name'] ?? 'Cliente não informado', ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $row['total_contratos'] ?></td><td><?= financeMoney((float) ($row['valor_contratado'] ?? 0)) ?></td><td><?= financeMoney((float) ($row['receitas_programadas'] ?? 0)) ?></td><td><?= !empty($row['ultima_vencimento']) ? date('d/m/Y', strtotime($row['ultima_vencimento'])) : '—' ?></td></tr><?php endforeach; ?><?php if (!$clientRevenueSummary): ?><tr><td colspan="5">Nenhum cliente com contrato ou movimento.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">LANÇAMENTOS</p><h2>Histórico financeiro</h2></div></div>
    <form method="get" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin:0 0 18px; align-items:end;">
        <label>Busca
            <input type="search" name="q" value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>" placeholder="Descrição, cliente, categoria...">
        </label>
        <label>Cliente
            <select name="client_id">
                <option value="">Todos</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int) $client['id'] ?>" <?= $filterClient === (int) $client['id'] ? 'selected' : '' ?>><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo
            <select name="type">
                <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="income" <?= $filterType === 'income' ? 'selected' : '' ?>>Receita</option>
                <option value="expense" <?= $filterType === 'expense' ? 'selected' : '' ?>>Despesa</option>
            </select>
        </label>
        <label>Situação
            <select name="status">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Todas</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pendente</option>
                <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>Pago</option>
                <option value="overdue" <?= $filterStatus === 'overdue' ? 'selected' : '' ?>>Em atraso</option>
                <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
            </select>
        </label>
        <div style="display:flex; gap:8px;">
            <button class="button" type="submit">Filtrar</button>
            <a class="button secondary" href="financeiro.php">Limpar</a>
        </div>
    </form>
    <div class="table-wrap"><table><thead><tr><th>Descrição</th><th>Contrato</th><th>Cliente</th><th>Categoria</th><th>Vencimento</th><th>Valor</th><th>Situação</th><th>Ações</th></tr></thead><tbody><?php foreach ($entries as $entry): ?><tr><td><?= htmlspecialchars($entry['description'], ENT_QUOTES, 'UTF-8') ?></td><td><?= !empty($entry['contract_number']) ? htmlspecialchars($entry['contract_number'], ENT_QUOTES, 'UTF-8') : 'Sem vínculo' ?></td><td><?= htmlspecialchars($entry['company_name'] ?? 'Nenhum', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($entry['category'], ENT_QUOTES, 'UTF-8') ?></td><td><?= date('d/m/Y', strtotime($entry['due_date'])) ?></td><td><?= ($entry['type'] === 'expense' ? '-' : '+') . financeMoney((float) $entry['amount']) ?></td><td><span class="status <?= $entry['status'] === 'paid' ? 'approved' : ($entry['status'] === 'cancelled' ? 'sent' : 'pending') ?>"><?= htmlspecialchars(financeStatusLabel((string) $entry['status']), ENT_QUOTES, 'UTF-8') ?></span></td><td><div style="display:flex; gap:8px; flex-wrap:wrap;"><a class="button secondary" href="financeiro.php?edit=<?= (int) $entry['id'] ?>" style="padding:6px 10px; font-size:12px;">Editar</a><form method="post" onsubmit="return confirm('Deseja excluir este lançamento?');" style="margin:0;"><input type="hidden" name="form_type" value="delete"><input type="hidden" name="entry_id" value="<?= (int) $entry['id'] ?>"><button class="button secondary" type="submit" style="padding:6px 10px; font-size:12px;">Excluir</button></form></div></td></tr><?php endforeach; ?><?php if (!$entries): ?><tr><td colspan="8">Nenhum lançamento cadastrado para os filtros selecionados.</td></tr><?php endif; ?></tbody></table></div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>
