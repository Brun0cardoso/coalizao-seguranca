<?php
$pageTitle = 'Colaboradores';
$activePage = 'colaboradores';
require_once __DIR__ . '/../php/conexao.php';

$error = '';
$success = '';
$editingEmployee = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: null;
if ($editId) {
    $statement = $pdo->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $editId]);
    $editingEmployee = $statement->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT) ?: null;
        if (!$employeeId) {
            $error = 'Colaborador não encontrado para exclusão.';
        } else {
            try {
                $pdo->beginTransaction();
                $assignmentDelete = $pdo->prepare('DELETE FROM post_assignments WHERE employee_id = :employee_id');
                $assignmentDelete->execute([':employee_id' => $employeeId]);
                $employeeDelete = $pdo->prepare('DELETE FROM employees WHERE id = :id');
                $employeeDelete->execute([':id' => $employeeId]);
                $pdo->commit();
                $success = 'Colaborador removido com sucesso.';
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Não foi possível remover o colaborador.';
            }
        }
    } else {
        $employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT) ?: null;
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

                if ($employeeId) {
                    $statement = $pdo->prepare(
                        'UPDATE employees SET name = :name, cpf = :cpf, email = :email, phone = :phone, role = :role, status = :status, admission_date = :admission_date, notes = :notes, updated_at = NOW() WHERE id = :id'
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
                        ':id' => $employeeId,
                    ]);
                    $employeeDbId = $employeeId;
                    $success = 'Colaborador atualizado com sucesso.';
                } else {
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
                    $employeeDbId = (int) $pdo->lastInsertId();
                    $success = 'Colaborador cadastrado com sucesso.';
                }

                if ($postId) {
                    $closeCurrent = $pdo->prepare('UPDATE post_assignments SET ends_on = :ends_on WHERE employee_id = :employee_id AND ends_on IS NULL');
                    $closeCurrent->execute([
                        ':ends_on' => $assignmentStart,
                        ':employee_id' => $employeeDbId,
                    ]);

                    $assign = $pdo->prepare(
                        'INSERT INTO post_assignments (post_id, employee_id, starts_on, shift)
                         VALUES (:post_id, :employee_id, :starts_on, :shift)'
                    );
                    $assign->execute([
                        ':post_id' => $postId,
                        ':employee_id' => $employeeDbId,
                        ':starts_on' => $assignmentStart,
                        ':shift' => $shift !== '' ? $shift : null,
                    ]);
                }

                $pdo->commit();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = ((int) $exception->errorInfo[1] ?? 0) === 1062
                    ? 'Já existe um colaborador com este CPF.'
                    : 'Não foi possível salvar o colaborador.';
            }
        }
    }
}

$posts = $pdo->query(
    "SELECT id, name FROM service_posts WHERE status = 'active' ORDER BY name"
)->fetchAll();

$employees = $pdo->query(
    'SELECT e.id, e.name, e.role, e.status, e.admission_date, sp.name AS post_name
     FROM employees e
     LEFT JOIN post_assignments pa ON pa.employee_id = e.id AND pa.ends_on IS NULL
     LEFT JOIN service_posts sp ON sp.id = pa.post_id
     ORDER BY e.created_at DESC'
)->fetchAll();

$formValues = [
    'employee_id' => $editingEmployee['id'] ?? ($_POST['employee_id'] ?? ''),
    'name' => $editingEmployee['name'] ?? ($_POST['name'] ?? ''),
    'cpf' => $editingEmployee['cpf'] ?? ($_POST['cpf'] ?? ''),
    'email' => $editingEmployee['email'] ?? ($_POST['email'] ?? ''),
    'phone' => $editingEmployee['phone'] ?? ($_POST['phone'] ?? ''),
    'role' => $editingEmployee['role'] ?? ($_POST['role'] ?? ''),
    'status' => $editingEmployee['status'] ?? ($_POST['status'] ?? 'active'),
    'admission_date' => $editingEmployee['admission_date'] ?? ($_POST['admission_date'] ?? ''),
    'post_id' => $_POST['post_id'] ?? '',
    'assignment_start' => $_POST['assignment_start'] ?? '',
    'shift' => $_POST['shift'] ?? '',
    'notes' => $editingEmployee['notes'] ?? ($_POST['notes'] ?? ''),
];

include __DIR__ . '/includes/header.php';
?>
<section class="welcome compact"><div><p class="eyebrow">EQUIPE</p><h2>Gerencie pessoas, documentos e alocações.</h2><p>Cadastre e acompanhe os colaboradores da operação.</p></div></section>
<?php if ($success): ?><p class="notice success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<section class="form-panel"><form method="post"><input type="hidden" name="action" value="save"><?php if ($editingEmployee): ?><input type="hidden" name="employee_id" value="<?= (int) $editingEmployee['id'] ?>"><?php endif; ?><div class="form-heading"><h2><?= $editingEmployee ? 'Editar colaborador' : 'Novo colaborador' ?></h2><p><?= $editingEmployee ? 'Atualize os dados principais e a alocação do posto.' : 'Preencha os dados principais e, se necessário, vincule a pessoa a um posto.' ?></p></div><div class="form-grid"><label>Nome completo<input type="text" name="name" required value="<?= htmlspecialchars($formValues['name'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Função<input type="text" name="role" required value="<?= htmlspecialchars($formValues['role'], ENT_QUOTES, 'UTF-8') ?>"></label><label>CPF<input type="text" name="cpf" value="<?= htmlspecialchars($formValues['cpf'], ENT_QUOTES, 'UTF-8') ?>"></label><label>E-mail<input type="email" name="email" value="<?= htmlspecialchars($formValues['email'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Telefone<input type="text" name="phone" value="<?= htmlspecialchars($formValues['phone'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Situação<select name="status"><option value="active" <?= $formValues['status'] === 'active' ? 'selected' : '' ?>>Ativo</option><option value="on_leave" <?= $formValues['status'] === 'on_leave' ? 'selected' : '' ?>>Em folga</option><option value="inactive" <?= $formValues['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option></select></label><label>Data de admissão<input type="date" name="admission_date" value="<?= htmlspecialchars($formValues['admission_date'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Posto de serviço<select name="post_id"><option value="">Nenhum posto</option><?php foreach ($posts as $post): ?><option value="<?= (int) $post['id'] ?>" <?= ((string) $formValues['post_id'] === (string) $post['id']) ? 'selected' : '' ?>><?= htmlspecialchars($post['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label>Início da alocação<input type="date" name="assignment_start" value="<?= htmlspecialchars($formValues['assignment_start'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Turno<input type="text" name="shift" placeholder="Ex.: 12x36 noturno" value="<?= htmlspecialchars($formValues['shift'], ENT_QUOTES, 'UTF-8') ?>"></label><label class="full">Observações<textarea name="notes" rows="3"><?= htmlspecialchars($formValues['notes'], ENT_QUOTES, 'UTF-8') ?></textarea></label></div><div class="form-actions"><span>O colaborador ficará disponível para cadastro e alocação.</span><button class="button" type="submit"><?= $editingEmployee ? 'Salvar alterações' : 'Cadastrar colaborador' ?></button><?php if ($editingEmployee): ?><a class="button secondary" href="colaboradores.php">Cancelar</a><?php endif; ?></div></form></section>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">CADASTRO</p><h2>Equipe cadastrada</h2></div><input class="search" type="search" placeholder="Buscar colaborador"></div><div class="table-wrap"><table><thead><tr><th>Colaborador</th><th>Função</th><th>Posto atual</th><th>Admissão</th><th>Situação</th><th>Ações</th></tr></thead><tbody><?php foreach ($employees as $employee): ?><tr><td><?= htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($employee['role'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($employee['post_name'] ?? 'Sem posto', ENT_QUOTES, 'UTF-8') ?></td><td><?= $employee['admission_date'] ? date('d/m/Y', strtotime($employee['admission_date'])) : '—' ?></td><td><span class="status <?= $employee['status'] === 'active' ? 'approved' : 'sent' ?>"><?= $employee['status'] === 'active' ? 'Ativo' : ($employee['status'] === 'on_leave' ? 'Em folga' : 'Inativo') ?></span></td><td><div style="display:flex; gap:8px; flex-wrap:wrap;"><a class="button secondary" href="colaboradores.php?edit=<?= (int) $employee['id'] ?>" style="padding:6px 10px; font-size:12px;">Editar</a><form method="post" onsubmit="return confirm('Deseja remover este colaborador?');" style="margin:0;"><input type="hidden" name="action" value="delete"><input type="hidden" name="employee_id" value="<?= (int) $employee['id'] ?>"><button class="button secondary" type="submit" style="padding:6px 10px; font-size:12px;">Excluir</button></form></div></td></tr><?php endforeach; ?><?php if (!$employees): ?><tr><td colspan="6">Nenhum colaborador cadastrado.</td></tr><?php endif; ?></tbody></table></div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>
