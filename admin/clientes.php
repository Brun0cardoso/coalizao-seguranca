<?php
$pageTitle = 'Clientes';
$activePage = 'clientes';
require_once __DIR__ . '/../php/conexao.php';

$error = '';
$success = '';
$editingClientId = filter_input(INPUT_GET, 'edit_client', FILTER_VALIDATE_INT) ?: null;
$editingPostId = filter_input(INPUT_GET, 'edit_post', FILTER_VALIDATE_INT) ?: null;
$editingClient = null;
$editingPost = null;

if ($editingClientId) {
    $editingClientStatement = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $editingClientStatement->execute([':id' => $editingClientId]);
    $editingClient = $editingClientStatement->fetch(PDO::FETCH_ASSOC);
}

if ($editingPostId) {
    $editingPostStatement = $pdo->prepare('SELECT * FROM service_posts WHERE id = :id LIMIT 1');
    $editingPostStatement->execute([':id' => $editingPostId]);
    $editingPost = $editingPostStatement->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'client_delete') {
            $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT) ?: null;
            if (!$clientId) {
                $error = 'Cliente não encontrado para exclusão.';
            } else {
                $pdo->beginTransaction();
                $deleteAssignments = $pdo->prepare('DELETE FROM post_assignments WHERE post_id IN (SELECT id FROM service_posts WHERE client_id = :client_id)');
                $deleteAssignments->execute([':client_id' => $clientId]);
                $deletePosts = $pdo->prepare('DELETE FROM service_posts WHERE client_id = :client_id');
                $deletePosts->execute([':client_id' => $clientId]);
                $deleteClient = $pdo->prepare('DELETE FROM clients WHERE id = :id');
                $deleteClient->execute([':id' => $clientId]);
                $pdo->commit();
                $success = 'Cliente removido com sucesso.';
            }
        } elseif ($action === 'client_save') {
            $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT) ?: null;
            $name = trim($_POST['name'] ?? '');
            $companyName = trim($_POST['company_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($name === '' || $companyName === '') {
                $error = 'Informe o responsável e o nome da empresa.';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Informe um e-mail válido.';
            } else {
                if ($clientId) {
                    $statement = $pdo->prepare('UPDATE clients SET name = :name, company_name = :company_name, email = :email, phone = :phone, address = :address, updated_at = NOW() WHERE id = :id');
                    $statement->execute([
                        ':name' => $name,
                        ':company_name' => $companyName,
                        ':email' => $email !== '' ? $email : null,
                        ':phone' => $phone !== '' ? $phone : null,
                        ':address' => $address !== '' ? $address : null,
                        ':id' => $clientId,
                    ]);
                    $success = 'Cliente atualizado com sucesso.';
                } else {
                    $statement = $pdo->prepare('INSERT INTO clients (name, company_name, email, phone, address) VALUES (:name, :company_name, :email, :phone, :address)');
                    $statement->execute([
                        ':name' => $name,
                        ':company_name' => $companyName,
                        ':email' => $email !== '' ? $email : null,
                        ':phone' => $phone !== '' ? $phone : null,
                        ':address' => $address !== '' ? $address : null,
                    ]);
                    $success = 'Cliente cadastrado com sucesso.';
                }
            }
        } elseif ($action === 'post_delete') {
            $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT) ?: null;
            if (!$postId) {
                $error = 'Posto não encontrado para exclusão.';
            } else {
                $pdo->beginTransaction();
                $deleteAssignments = $pdo->prepare('DELETE FROM post_assignments WHERE post_id = :post_id');
                $deleteAssignments->execute([':post_id' => $postId]);
                $deletePost = $pdo->prepare('DELETE FROM service_posts WHERE id = :id');
                $deletePost->execute([':id' => $postId]);
                $pdo->commit();
                $success = 'Posto removido com sucesso.';
            }
        } elseif ($action === 'post_save') {
            $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT) ?: null;
            $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
            $name = trim($_POST['post_name'] ?? '');
            $serviceType = $_POST['service_type'] ?? 'other';
            $address = trim($_POST['post_address'] ?? '');
            if (!$clientId || $name === '' || $address === '') {
                $error = 'Informe o cliente, o nome do posto e o endereço.';
            } elseif (!in_array($serviceType, ['security', 'concierge', 'monitoring', 'access_control', 'other'], true)) {
                $error = 'Tipo de serviço inválido.';
            } else {
                if ($postId) {
                    $statement = $pdo->prepare('UPDATE service_posts SET client_id = :client_id, name = :name, service_type = :service_type, address = :address, status = :status, updated_at = NOW() WHERE id = :id');
                    $statement->execute([
                        ':client_id' => $clientId,
                        ':name' => $name,
                        ':service_type' => $serviceType,
                        ':address' => $address,
                        ':status' => 'active',
                        ':id' => $postId,
                    ]);
                    $success = 'Posto atualizado com sucesso.';
                } else {
                    $statement = $pdo->prepare('INSERT INTO service_posts (client_id, name, service_type, address, status) VALUES (:client_id, :name, :service_type, :address, \'active\')');
                    $statement->execute([':client_id' => $clientId, ':name' => $name, ':service_type' => $serviceType, ':address' => $address]);
                    $success = 'Posto cadastrado com sucesso.';
                }
            }
        }
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Não foi possível salvar os dados. Verifique as informações e tente novamente.';
    }
}

$clients = $pdo->query('SELECT id, name, company_name, email, phone, address FROM clients ORDER BY company_name')->fetchAll();
$posts = $pdo->query('SELECT id, client_id, name, service_type, address, status FROM service_posts ORDER BY name')->fetchAll();
$postsByClient = [];
foreach ($posts as $post) $postsByClient[(int) $post['client_id']][] = $post;

$clientFormValues = [
    'name' => $editingClient['name'] ?? ($_POST['name'] ?? ''),
    'company_name' => $editingClient['company_name'] ?? ($_POST['company_name'] ?? ''),
    'email' => $editingClient['email'] ?? ($_POST['email'] ?? ''),
    'phone' => $editingClient['phone'] ?? ($_POST['phone'] ?? ''),
    'address' => $editingClient['address'] ?? ($_POST['address'] ?? ''),
];

$postFormValues = [
    'client_id' => $editingPost['client_id'] ?? ($_POST['client_id'] ?? ''),
    'post_name' => $editingPost['name'] ?? ($_POST['post_name'] ?? ''),
    'service_type' => $editingPost['service_type'] ?? ($_POST['service_type'] ?? 'security'),
    'post_address' => $editingPost['address'] ?? ($_POST['post_address'] ?? ''),
];

include __DIR__ . '/includes/header.php';
?>
<section class="welcome compact"><div><p class="eyebrow">RELACIONAMENTO</p><h2>Organize clientes e seus postos de serviço.</h2><p>Cada cliente pode ter vários postos, com operação e equipes vinculadas.</p></div></section>
<?php if (isset($_GET['cadastro'])): ?><p class="notice success"><?= $_GET['cadastro'] === 'posto' ? 'Posto cadastrado com sucesso.' : 'Cliente cadastrado com sucesso.' ?></p><?php endif; ?>
<?php if ($success): ?><p class="notice success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<section class="form-panel"><form method="post"><input type="hidden" name="action" value="client_save"><?php if ($editingClient): ?><input type="hidden" name="client_id" value="<?= (int) $editingClient['id'] ?>"><?php endif; ?><div class="form-heading"><h2><?= $editingClient ? 'Editar cliente' : 'Novo cliente' ?></h2><p>Cadastre ou atualize os dados do cliente e da sua operação.</p></div><div class="form-grid"><label>Responsável<input type="text" name="name" required value="<?= htmlspecialchars($clientFormValues['name'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Empresa ou condomínio<input type="text" name="company_name" required value="<?= htmlspecialchars($clientFormValues['company_name'], ENT_QUOTES, 'UTF-8') ?>"></label><label>E-mail<input type="email" name="email" value="<?= htmlspecialchars($clientFormValues['email'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Telefone<input type="text" name="phone" value="<?= htmlspecialchars($clientFormValues['phone'], ENT_QUOTES, 'UTF-8') ?>"></label><label class="full">Endereço<input type="text" name="address" value="<?= htmlspecialchars($clientFormValues['address'], ENT_QUOTES, 'UTF-8') ?>"></label></div><div class="form-actions"><span>O cliente ficará disponível para novos postos.</span><button class="button" type="submit"><?= $editingClient ? 'Salvar alterações' : 'Cadastrar cliente' ?></button><?php if ($editingClient): ?><a class="button secondary" href="clientes.php">Cancelar</a><?php endif; ?></div></form></section>
<section class="form-panel"><form method="post"><input type="hidden" name="action" value="post_save"><?php if ($editingPost): ?><input type="hidden" name="post_id" value="<?= (int) $editingPost['id'] ?>"><?php endif; ?><div class="form-heading"><h2><?= $editingPost ? 'Editar posto de serviço' : 'Novo posto de serviço' ?></h2><p>Escolha o cliente responsável por este posto.</p></div><div class="form-grid"><label>Cliente<select name="client_id" required><option value="">Selecione um cliente</option><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>" <?= ((string) ($postFormValues['client_id'] ?? '') === (string) $client['id']) ? 'selected' : '' ?>><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label>Nome do posto<input type="text" name="post_name" placeholder="Ex.: Portaria principal" required value="<?= htmlspecialchars($postFormValues['post_name'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Tipo de serviço<select name="service_type"><option value="security" <?= $postFormValues['service_type'] === 'security' ? 'selected' : '' ?>>Vigilância</option><option value="concierge" <?= $postFormValues['service_type'] === 'concierge' ? 'selected' : '' ?>>Portaria e recepção</option><option value="monitoring" <?= $postFormValues['service_type'] === 'monitoring' ? 'selected' : '' ?>>Monitoramento</option><option value="access_control" <?= $postFormValues['service_type'] === 'access_control' ? 'selected' : '' ?>>Controle de acesso</option><option value="other" <?= $postFormValues['service_type'] === 'other' ? 'selected' : '' ?>>Outro</option></select></label><label>Endereço do posto<input type="text" name="post_address" required value="<?= htmlspecialchars($postFormValues['post_address'], ENT_QUOTES, 'UTF-8') ?>"></label></div><div class="form-actions"><span>O posto será exibido dentro do cliente escolhido.</span><button class="button" type="submit"><?= $editingPost ? 'Salvar alterações' : 'Cadastrar posto' ?></button><?php if ($editingPost): ?><a class="button secondary" href="clientes.php">Cancelar</a><?php endif; ?></div></form></section>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">CARTEIRA</p><h2>Clientes e postos</h2></div><input class="search" type="search" placeholder="Buscar cliente"></div><?php if (!$clients): ?><p>Nenhum cliente cadastrado.</p><?php else: ?><div class="cards-grid"><?php foreach ($clients as $client): ?><article class="service-card"><p class="eyebrow">CLIENTE</p><h2><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($client['address'] ?? 'Endereço não informado', ENT_QUOTES, 'UTF-8') ?></p><div><strong><?= count($postsByClient[(int) $client['id']] ?? []) ?></strong><span> posto(s) de serviço</span></div><?php if (!empty($postsByClient[(int) $client['id']])): ?><ul><?php foreach ($postsByClient[(int) $client['id']] as $post): ?><li><?= htmlspecialchars($post['name'], ENT_QUOTES, 'UTF-8') ?> <small><?= htmlspecialchars($post['address'], ENT_QUOTES, 'UTF-8') ?></small> <div style="display:flex; gap:8px; margin-top:6px;"><a class="button secondary" href="clientes.php?edit_post=<?= (int) $post['id'] ?>" style="padding:6px 10px; font-size:12px;">Editar</a><form method="post" onsubmit="return confirm('Deseja excluir este posto?');" style="margin:0;"><input type="hidden" name="action" value="post_delete"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><button class="button secondary" type="submit" style="padding:6px 10px; font-size:12px;">Excluir</button></form></div></li><?php endforeach; ?></ul><?php else: ?><p>Nenhum posto vinculado.</p><?php endif; ?><div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;"><a class="button secondary" href="clientes.php?edit_client=<?= (int) $client['id'] ?>">Editar cliente</a><form method="post" onsubmit="return confirm('Deseja excluir este cliente e todos os postos vinculados?');" style="margin:0;"><input type="hidden" name="action" value="client_delete"><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>"><button class="button secondary" type="submit">Excluir cliente</button></form></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php include __DIR__ . '/includes/footer.php'; ?>
