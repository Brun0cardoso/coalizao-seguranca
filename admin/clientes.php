<?php
$pageTitle = 'Clientes';
$activePage = 'clientes';
require_once __DIR__ . '/../php/conexao.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'client') {
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
                $statement = $pdo->prepare('INSERT INTO clients (name, company_name, email, phone, address) VALUES (:name, :company_name, :email, :phone, :address)');
                $statement->execute([
                    ':name' => $name,
                    ':company_name' => $companyName,
                    ':email' => $email !== '' ? $email : null,
                    ':phone' => $phone !== '' ? $phone : null,
                    ':address' => $address !== '' ? $address : null,
                ]);
                header('Location: clientes.php?cadastro=cliente', true, 303);
                exit;
            }
        } elseif ($action === 'post') {
            $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
            $name = trim($_POST['post_name'] ?? '');
            $serviceType = $_POST['service_type'] ?? 'other';
            $address = trim($_POST['post_address'] ?? '');
            if (!$clientId || $name === '' || $address === '') {
                $error = 'Informe o cliente, o nome do posto e o endereço.';
            } elseif (!in_array($serviceType, ['security', 'concierge', 'monitoring', 'access_control', 'other'], true)) {
                $error = 'Tipo de serviço inválido.';
            } else {
                $statement = $pdo->prepare('INSERT INTO service_posts (client_id, name, service_type, address, status) VALUES (:client_id, :name, :service_type, :address, \'active\')');
                $statement->execute([':client_id' => $clientId, ':name' => $name, ':service_type' => $serviceType, ':address' => $address]);
                header('Location: clientes.php?cadastro=posto', true, 303);
                exit;
            }
        }
    } catch (PDOException $exception) {
        $error = 'Não foi possível salvar os dados. Verifique as informações e tente novamente.';
    }
}

$clients = $pdo->query('SELECT id, name, company_name, email, phone, address FROM clients ORDER BY company_name')->fetchAll();
$posts = $pdo->query('SELECT id, client_id, name, service_type, address, status FROM service_posts ORDER BY name')->fetchAll();
$postsByClient = [];
foreach ($posts as $post) $postsByClient[(int) $post['client_id']][] = $post;
include 'includes/header.php';
?>
<section class="welcome compact"><div><p class="eyebrow">RELACIONAMENTO</p><h2>Organize clientes e seus postos de serviço.</h2><p>Cada cliente pode ter vários postos, com operação e equipes vinculadas.</p></div></section>
<?php if (isset($_GET['cadastro'])): ?><p class="notice success"><?= $_GET['cadastro'] === 'posto' ? 'Posto cadastrado com sucesso.' : 'Cliente cadastrado com sucesso.' ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<section class="form-panel"><form method="post"><input type="hidden" name="action" value="client"><div class="form-heading"><h2>Novo cliente</h2><p>Cadastre o cliente antes de adicionar seus postos de serviço.</p></div><div class="form-grid"><label>Responsável<input type="text" name="name" required></label><label>Empresa ou condomínio<input type="text" name="company_name" required></label><label>E-mail<input type="email" name="email"></label><label>Telefone<input type="text" name="phone"></label><label class="full">Endereço<input type="text" name="address"></label></div><div class="form-actions"><span>O cliente ficará disponível para novos postos.</span><button class="button" type="submit">Cadastrar cliente</button></div></form></section>
<section class="form-panel"><form method="post"><input type="hidden" name="action" value="post"><div class="form-heading"><h2>Novo posto de serviço</h2><p>Escolha o cliente responsável por este posto.</p></div><div class="form-grid"><label>Cliente<select name="client_id" required><option value="">Selecione um cliente</option><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>"><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label>Nome do posto<input type="text" name="post_name" placeholder="Ex.: Portaria principal" required></label><label>Tipo de serviço<select name="service_type"><option value="security">Vigilância</option><option value="concierge">Portaria e recepção</option><option value="monitoring">Monitoramento</option><option value="access_control">Controle de acesso</option><option value="other">Outro</option></select></label><label>Endereço do posto<input type="text" name="post_address" required></label></div><div class="form-actions"><span>O posto será exibido dentro do cliente escolhido.</span><button class="button" type="submit">Cadastrar posto</button></div></form></section>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">CARTEIRA</p><h2>Clientes e postos</h2></div><input class="search" type="search" placeholder="Buscar cliente"></div><?php if (!$clients): ?><p>Nenhum cliente cadastrado.</p><?php else: ?><div class="cards-grid"><?php foreach ($clients as $client): ?><article class="service-card"><p class="eyebrow">CLIENTE</p><h2><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($client['address'] ?? 'Endereço não informado', ENT_QUOTES, 'UTF-8') ?></p><div><strong><?= count($postsByClient[(int) $client['id']] ?? []) ?></strong><span> posto(s) de serviço</span></div><?php if (!empty($postsByClient[(int) $client['id']])): ?><ul><?php foreach ($postsByClient[(int) $client['id']] as $post): ?><li><?= htmlspecialchars($post['name'], ENT_QUOTES, 'UTF-8') ?> <small><?= htmlspecialchars($post['address'], ENT_QUOTES, 'UTF-8') ?></small></li><?php endforeach; ?></ul><?php else: ?><p>Nenhum posto vinculado.</p><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?></section>
<?php include 'includes/footer.php'; ?>
