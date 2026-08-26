<?php
require_once __DIR__ . '/../php/auth.php';
require_admin();
require __DIR__ . '/../php/conexao.php';
require __DIR__ . '/../php/pdf.php';

$type = $_GET['type'] ?? '';

if ($type === 'curriculo') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) { http_response_code(400); exit('Currículo inválido.'); }
    $query = $pdo->prepare('SELECT original_filename, stored_filename, mime_type FROM job_applications WHERE id = ?');
    $query->execute([$id]);
    $file = $query->fetch();
    if (!$file) { http_response_code(404); exit('Arquivo não encontrado.'); }
    $path = dirname(__DIR__) . '/storage/curriculos/' . basename($file['stored_filename']);
    if (!is_file($path)) { http_response_code(404); exit('Arquivo não encontrado.'); }
    $downloadName = pathinfo($file['original_filename'], PATHINFO_FILENAME) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $downloadName) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

$datasets = [
    'orcamentos' => ['orcamentos.pdf', 'Orçamentos', 'SELECT id, contact_name, contact_email, contact_phone, service_type, quantity_posts, billing_period, estimated_amount, status, created_at FROM quotes ORDER BY created_at DESC'],
    'financeiro' => ['relatorio-financeiro.pdf', 'Relatório financeiro', 'SELECT description, category, type, amount, due_date, paid_at, status, created_at FROM financial_entries ORDER BY due_date DESC'],
];
if (!isset($datasets[$type])) { http_response_code(404); exit('Relatório não encontrado.'); }

[$filename, $title, $sql] = $datasets[$type];
$rows = $pdo->query($sql)->fetchAll();
download_simple_pdf($filename, $title, $rows ? array_keys($rows[0]) : ['Sem dados'], $rows);
