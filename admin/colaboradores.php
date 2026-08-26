<?php
$pageTitle = 'Colaboradores';
$activePage = 'colaboradores';
require_once __DIR__ . '/../php/conexao.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = trim($_POST['name'] ?? '');
	$cpf = trim($_POST['cpf'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$phone = trim($_POST['phone'] ?? '');
	$role = trim($_POST['role'] ?? '');
	$status = $_POST['status'] ?? 'active';
	$admissionDate = trim($_POST['admission_date'] ?? '');
	$notes = trim($_POST['notes'] ?? '');
	$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT) ?: null;
	$assignmentStart = trim($_POST['assignment_start'] ?? '');
	$shift = trim($_POST['shift'] ?? '');

	if ($name === '' || $role === '') {
		$error = 'Informe o nome e a função do colaborador.';
	} elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$error = 'Informe um e-mail válido.';
	} elseif (!in_array($status, ['active', 'on_leave', 'inactive'], true)) {
		$error = 'Situação inválida.';
	} elseif ($postId && $assignmentStart === '') {
		$error = 'Informe a data de início da alocação.';
	} else {
		try {
			$pdo->beginTransaction();
			$statement = $pdo->prepare(
				'INSERT INTO employees (name, cpf, email, phone, role, status, admission_date, notes)
				 VALUES (:name, :cpf, :email, :phone, :role, :status, :admission_date, :notes)'
			);
			$statement->execute([
				':name' => $name,
				':cpf' => $cpf !== '' ? $cpf : null,
				':email' => $email !== '' ? $email : null,
				':phone' => $phone !== '' ? $phone : null,
				':role' => $role,
				':status' => $status,
				':admission_date' => $admissionDate !== '' ? $admissionDate : null,
				':notes' => $notes !== '' ? $notes : null,
			]);
			$employeeId = (int) $pdo->lastInsertId();
			if ($postId) {
				$assignment = $pdo->prepare(
					'INSERT INTO post_assignments (post_id, employee_id, starts_on, shift)
					 VALUES (:post_id, :employee_id, :starts_on, :shift)'
				);
				$assignment->execute([
					':post_id' => $postId,
					':employee_id' => $employeeId,
					':starts_on' => $assignmentStart,
					':shift' => $shift !== '' ? $shift : null,
				]);
			}
			$pdo->commit();
			header('Location: colaboradores.php?cadastro=sucesso', true, 303);
			exit;
		} catch (PDOException $exception) {
			if ($pdo->inTransaction()) $pdo->rollBack();
			$error = ((int) $exception->errorInfo[1] ?? 0) === 1062
				? 'Já existe um colaborador com este CPF.'
				: 'Não foi possível cadastrar o colaborador.';
		}
	}
	}

$posts = $pdo->query(
	"SELECT id, name FROM service_posts WHERE status = 'active' ORDER BY name"
)->fetchAll();

$employees = $pdo->query(
	'SELECT e.name, e.role, e.status, e.admission_date, sp.name AS post_name
	 FROM employees e
	 LEFT JOIN post_assignments pa ON pa.employee_id = e.id AND pa.ends_on IS NULL
	 LEFT JOIN service_posts sp ON sp.id = pa.post_id
	 ORDER BY e.created_at DESC'
)->fetchAll();
include 'includes/header.php';
?>
<section class="welcome compact"><div><p class="eyebrow">EQUIPE</p><h2>Gerencie pessoas, documentos e alocações.</h2><p>Cadastre e acompanhe os colaboradores da operação.</p></div></section>
<?php if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso'): ?><p class="notice success">Colaborador cadastrado com sucesso.</p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<section class="form-panel"><form method="post"><div class="form-heading"><h2>Novo colaborador</h2><p>Preencha os dados principais e, se necessário, vincule a pessoa a um posto.</p></div><div class="form-grid"><label>Nome completo<input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Função<input type="text" name="role" required value="<?= htmlspecialchars($_POST['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>CPF<input type="text" name="cpf" value="<?= htmlspecialchars($_POST['cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>E-mail<input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Telefone<input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Situação<select name="status"><option value="active">Ativo</option><option value="on_leave">Em folga</option><option value="inactive">Inativo</option></select></label><label>Data de admissão<input type="date" name="admission_date" value="<?= htmlspecialchars($_POST['admission_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Posto de serviço<select name="post_id"><option value="">Nenhum posto</option><?php foreach ($posts as $post): ?><option value="<?= (int) $post['id'] ?>" <?= (string) ($_POST['post_id'] ?? '') === (string) $post['id'] ? 'selected' : '' ?>><?= htmlspecialchars($post['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label>Início da alocação<input type="date" name="assignment_start" value="<?= htmlspecialchars($_POST['assignment_start'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Turno<input type="text" name="shift" placeholder="Ex.: 12x36 noturno" value="<?= htmlspecialchars($_POST['shift'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label class="full">Observações<textarea name="notes" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></label></div><div class="form-actions"><span>Os dados serão salvos no banco de dados.</span><button class="button" type="submit">Cadastrar colaborador</button></div></form></section>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">CADASTRO</p><h2>Equipe cadastrada</h2></div><input class="search" type="search" placeholder="Buscar colaborador"></div><div class="table-wrap"><table><thead><tr><th>Colaborador</th><th>Função</th><th>Posto atual</th><th>Admissão</th><th>Situação</th></tr></thead><tbody><?php foreach ($employees as $employee): ?><tr><td><?= htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($employee['role'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($employee['post_name'] ?? 'Sem posto', ENT_QUOTES, 'UTF-8') ?></td><td><?= $employee['admission_date'] ? date('d/m/Y', strtotime($employee['admission_date'])) : '—' ?></td><td><span class="status <?= $employee['status'] === 'active' ? 'approved' : 'sent' ?>"><?= $employee['status'] === 'active' ? 'Ativo' : ($employee['status'] === 'on_leave' ? 'Em folga' : 'Inativo') ?></span></td></tr><?php endforeach; ?><?php if (!$employees): ?><tr><td colspan="5">Nenhum colaborador cadastrado.</td></tr><?php endif; ?></tbody></table></div></section>
<?php include 'includes/footer.php'; ?>
