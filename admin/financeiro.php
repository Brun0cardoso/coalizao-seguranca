<?php
$pageTitle = 'Controle financeiro';
$activePage = 'financeiro';
require_once __DIR__ . '/../php/conexao.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
			header('Location: financeiro.php?cadastro=sucesso', true, 303);
			exit;
		} catch (PDOException $exception) {
			$error = 'Não foi possível adicionar o lançamento.';
		}
	}
}

$clients = $pdo->query('SELECT id, company_name FROM clients ORDER BY company_name')->fetchAll();
$entries = $pdo->query(
	'SELECT f.description, f.category, f.type, f.amount, f.due_date, f.status, c.company_name
	 FROM financial_entries f LEFT JOIN clients c ON c.id = f.client_id
	 ORDER BY f.due_date DESC, f.created_at DESC'
)->fetchAll();
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$summary = $pdo->prepare(
	"SELECT type, COALESCE(SUM(amount), 0) AS total FROM financial_entries
	 WHERE due_date BETWEEN :start AND :end AND status <> 'cancelled' GROUP BY type"
);
$summary->execute([':start' => $monthStart, ':end' => $monthEnd]);
$totals = ['income' => 0, 'expense' => 0];
foreach ($summary as $row) $totals[$row['type']] = (float) $row['total'];

function financeMoney(float $value): string
{
	return 'R$ ' . number_format($value, 2, ',', '.');
}

include 'includes/header.php';
?>
<p><a class="button" href="download.php?type=financeiro">Baixar relatório financeiro (CSV)</a></p>
<section class="welcome compact"><div><p class="eyebrow">FINANCEIRO</p><h2>Organize receitas, despesas e vencimentos.</h2><p>Registre os lançamentos e acompanhe o fluxo financeiro.</p></div></section>
<?php if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso'): ?><p class="notice success">Lançamento adicionado com sucesso.</p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<section class="form-panel"><form method="post"><div class="form-heading"><h2>Adicionar lançamento</h2><p>Informe os dados da receita ou despesa.</p></div><div class="form-grid"><label>Descrição<input type="text" name="description" required value="<?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Categoria<input type="text" name="category" placeholder="Ex.: Folha de pagamento" required value="<?= htmlspecialchars($_POST['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Tipo<select name="type" required><option value="income">Receita</option><option value="expense">Despesa</option></select></label><label>Valor<input type="number" name="amount" min="0.01" step="0.01" required value="<?= htmlspecialchars($_POST['amount'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Vencimento<input type="date" name="due_date" required value="<?= htmlspecialchars($_POST['due_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Situação<select name="status"><option value="pending">Pendente</option><option value="paid">Pago</option><option value="overdue">Em atraso</option><option value="cancelled">Cancelado</option></select></label><label>Cliente<select name="client_id"><option value="">Nenhum cliente</option><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>"><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label class="full">Observações<textarea name="notes" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></label></div><div class="form-actions"><span>O lançamento será salvo no banco de dados.</span><button class="button" type="submit">Adicionar lançamento</button></div></form></section>
<section class="stats-grid"><article class="stat-card"><span>Receitas do mês</span><strong><?= financeMoney($totals['income']) ?></strong><small>Vencimentos do mês atual</small></article><article class="stat-card"><span>Despesas do mês</span><strong><?= financeMoney($totals['expense']) ?></strong><small>Vencimentos do mês atual</small></article><article class="stat-card"><span>Saldo projetado</span><strong><?= financeMoney($totals['income'] - $totals['expense']) ?></strong><small>Receitas menos despesas</small></article></section>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">LANÇAMENTOS</p><h2>Histórico financeiro</h2></div></div><div class="table-wrap"><table><thead><tr><th>Descrição</th><th>Cliente</th><th>Categoria</th><th>Vencimento</th><th>Valor</th><th>Situação</th></tr></thead><tbody><?php foreach ($entries as $entry): ?><tr><td><?= htmlspecialchars($entry['description'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($entry['company_name'] ?? 'Nenhum', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($entry['category'], ENT_QUOTES, 'UTF-8') ?></td><td><?= date('d/m/Y', strtotime($entry['due_date'])) ?></td><td><?= ($entry['type'] === 'expense' ? '-' : '+') . financeMoney((float) $entry['amount']) ?></td><td><span class="status <?= $entry['status'] === 'paid' ? 'approved' : ($entry['status'] === 'cancelled' ? 'sent' : 'pending') ?>"><?= $entry['status'] === 'paid' ? 'Pago' : ($entry['status'] === 'overdue' ? 'Em atraso' : ($entry['status'] === 'cancelled' ? 'Cancelado' : 'Pendente')) ?></span></td></tr><?php endforeach; ?><?php if (!$entries): ?><tr><td colspan="6">Nenhum lançamento cadastrado.</td></tr><?php endif; ?></tbody></table></div></section>
<?php include 'includes/footer.php'; ?>
