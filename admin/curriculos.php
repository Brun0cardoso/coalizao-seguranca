<?php
$pageTitle = 'Currículos';
$activePage = 'curriculos';
include 'includes/header.php';
require __DIR__ . '/../php/conexao.php';
$applications = $pdo->query('SELECT id, name, email, phone, original_filename, created_at FROM job_applications ORDER BY created_at DESC')->fetchAll();
?>
<section class="welcome compact"><div><p class="eyebrow">BANCO DE TALENTOS</p><h2>Currículos recebidos</h2><p>Baixe em PDF os arquivos enviados pelos candidatos.</p></div></section>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Candidato</th><th>Contato</th><th>Arquivo</th><th>Recebido em</th><th></th></tr></thead><tbody>
<?php if (!$applications): ?><tr><td colspan="5">Nenhum currículo recebido.</td></tr><?php endif; ?>
<?php foreach ($applications as $application): ?><tr><td><?= htmlspecialchars($application['name']) ?></td><td><?= htmlspecialchars($application['email']) ?><br><?= htmlspecialchars($application['phone']) ?></td><td><?= htmlspecialchars($application['original_filename']) ?></td><td><?= date('d/m/Y H:i', strtotime($application['created_at'])) ?></td><td><a class="text-button pdf-download" href="download.php?type=curriculo&id=<?= (int) $application['id'] ?>" title="Baixar currículo em PDF"><span aria-hidden="true">↓</span> Baixar PDF</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php include 'includes/footer.php'; ?>
