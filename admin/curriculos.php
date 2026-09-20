<?php
$pageTitle = 'Currículos';
$activePage = 'curriculos';
require_once __DIR__ . '/../php/conexao.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'delete') {
    $applicationId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT) ?: null;
    if (!$applicationId) {
        $error = 'Currículo não encontrado para exclusão.';
    } else {
        try {
            $deleteStatement = $pdo->prepare('DELETE FROM job_applications WHERE id = :id');
            $deleteStatement->execute([':id' => $applicationId]);
            $success = 'Currículo removido com sucesso.';
        } catch (PDOException $exception) {
            $error = 'Não foi possível remover o currículo.';
        }
    }
}

$searchTerm = trim((string) ($_GET['q'] ?? ''));
$where = [];
$params = [];
if ($searchTerm !== '') {
    $where[] = '(name LIKE :search OR email LIKE :search OR phone LIKE :search OR original_filename LIKE :search)';
    $params[':search'] = '%' . $searchTerm . '%';
}

$sql = 'SELECT id, name, email, phone, original_filename, created_at FROM job_applications';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$applications = $statement->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section class="welcome compact"><div><p class="eyebrow">BANCO DE TALENTOS</p><h2>Currículos recebidos</h2><p>Baixe em PDF os arquivos enviados pelos candidatos.</p></div></section>
<?php if ($success): ?><p class="notice success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<section class="panel">
    <div class="panel-heading">
        <div><p class="eyebrow">LISTAGEM</p><h2>Currículos cadastrados</h2></div>
        <form method="get" style="display:flex; gap:8px; align-items:center; margin:0;">
            <input class="search" type="search" name="q" value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar candidato" style="min-width:220px;">
            <button class="button secondary" type="submit" style="padding:9px 14px;">Buscar</button>
            <?php if ($searchTerm !== ''): ?><a class="button secondary" href="curriculos.php" style="padding:9px 14px;">Limpar</a><?php endif; ?>
        </form>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Candidato</th><th>Contato</th><th>Arquivo</th><th>Recebido em</th><th>Ações</th></tr></thead><tbody>
    <?php if (!$applications): ?><tr><td colspan="5">Nenhum currículo encontrado.</td></tr><?php endif; ?>
    <?php foreach ($applications as $application): ?><tr><td><?= htmlspecialchars($application['name']) ?></td><td><?= htmlspecialchars($application['email']) ?><br><?= htmlspecialchars($application['phone']) ?></td><td><?= htmlspecialchars($application['original_filename']) ?></td><td><?= date('d/m/Y H:i', strtotime($application['created_at'])) ?></td><td><div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;"><a class="text-button pdf-download" href="download.php?type=curriculo&id=<?= (int) $application['id'] ?>" title="Baixar currículo em PDF"><span aria-hidden="true">↓</span> PDF</a><form method="post" onsubmit="return confirm('Deseja remover este currículo?');" style="margin:0;"><input type="hidden" name="form_type" value="delete"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><button class="button secondary" type="submit" style="padding:6px 10px; font-size:12px;">Excluir</button></form></div></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
