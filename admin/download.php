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

function outputCsv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $header) {
            $line[] = $row[$header] ?? '';
        }
        fputcsv($output, $line);
    }
    fclose($output);
}

if ($type === 'orcamentos') {
    $query = $pdo->query(
        'SELECT q.id, c.company_name AS cliente, q.contact_name, q.contact_email, q.contact_phone, q.service_type, q.quantity_posts, q.billing_period, q.estimated_amount, q.status, q.created_at, q.notes
         FROM quotes q
         LEFT JOIN clients c ON c.id = q.client_id
         ORDER BY q.created_at DESC'
    );
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['id', 'cliente', 'contact_name', 'contact_email', 'contact_phone', 'service_type', 'quantity_posts', 'billing_period', 'estimated_amount', 'status', 'created_at', 'notes'];
    outputCsv('relatorio-orcamentos.csv', $headers, $rows);
    exit;
}

$datasets = [];

if ($type === 'financeiro') {
    $query = $pdo->query(
        'SELECT
            f.id,
            f.description,
            f.category,
            f.type,
            f.amount,
            f.due_date,
            f.paid_at,
            f.status,
            c.company_name AS cliente,
            ct.contract_number AS contrato,
            ct.title AS contrato_titulo,
            f.created_at
         FROM financial_entries f
         LEFT JOIN clients c ON c.id = f.client_id
         LEFT JOIN contracts ct ON ct.id = f.contract_id
         ORDER BY f.due_date DESC, f.created_at DESC'
    );
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['id', 'description', 'category', 'type', 'amount', 'due_date', 'paid_at', 'status', 'cliente', 'contrato', 'contrato_titulo', 'created_at'];
    outputCsv('relatorio-financeiro.csv', $headers, $rows);
    exit;
}

if ($type === 'dashboard') {
    $query = $pdo->query(
        "SELECT DATE_FORMAT(due_date, '%Y-%m') AS month_key,
                SUM(CASE WHEN type = 'income' AND status <> 'cancelled' THEN amount ELSE 0 END) AS total_income
         FROM financial_entries
         WHERE due_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
         GROUP BY DATE_FORMAT(due_date, '%Y-%m')
         ORDER BY month_key ASC"
    );
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['month_key', 'total_income'];
    outputCsv('resumo-dashboard.csv', $headers, $rows);
    exit;
}

if ($type === 'contratos') {
    $query = $pdo->query(
        'SELECT
            c.id,
            c.contract_number,
            c.title,
            c.service_type,
            c.start_date,
            c.end_date,
            c.renewal_date,
            c.monthly_value,
            c.status,
            cl.company_name AS cliente,
            p.name AS posto,
            c.notes,
            c.created_at,
            c.updated_at
         FROM contracts c
         LEFT JOIN clients cl ON cl.id = c.client_id
         LEFT JOIN service_posts p ON p.id = c.post_id
         ORDER BY c.created_at DESC'
    );
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['id', 'contract_number', 'title', 'service_type', 'start_date', 'end_date', 'renewal_date', 'monthly_value', 'status', 'cliente', 'posto', 'notes', 'created_at', 'updated_at'];
    outputCsv('relatorio-contratos.csv', $headers, $rows);
    exit;
}

if ($type === 'contratos_cliente') {
    $query = $pdo->query(
        "SELECT cl.company_name AS cliente,
                c.status,
                COUNT(c.id) AS total_contratos,
                SUM(c.monthly_value) AS valor_mensal
         FROM contracts c
         LEFT JOIN clients cl ON cl.id = c.client_id
         GROUP BY cl.id, cl.company_name, c.status
         ORDER BY cl.company_name ASC, c.status ASC"
    );
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['cliente', 'status', 'total_contratos', 'valor_mensal'];
    outputCsv('resumo-contratos-por-cliente.csv', $headers, $rows);
    exit;
}

if ($type === 'resumo_cliente') {
    $query = $pdo->query(
        "SELECT cl.company_name AS cliente,
                COUNT(c.id) AS total_contratos,
                COALESCE(SUM(c.monthly_value), 0) AS valor_contratado,
                COALESCE(SUM(CASE WHEN fe.type = 'income' AND fe.status <> 'cancelled' THEN fe.amount ELSE 0 END), 0) AS receitas_programadas,
                MAX(fe.due_date) AS ultimo_vencimento
         FROM clients cl
         LEFT JOIN contracts c ON c.client_id = cl.id
         LEFT JOIN financial_entries fe ON fe.client_id = cl.id AND fe.type = 'income'
         GROUP BY cl.id, cl.company_name
         ORDER BY valor_contratado DESC, total_contratos DESC"
    );
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['cliente', 'total_contratos', 'valor_contratado', 'receitas_programadas', 'ultimo_vencimento'];
    outputCsv('resumo-executivo-clientes.csv', $headers, $rows);
    exit;
}

if ($type === 'relatorios') {
    $fromDate = $_GET['from'] ?? date('Y-m-01');
    $toDate = $_GET['to'] ?? date('Y-m-t');
    $query = $pdo->prepare(
        "SELECT f.id, f.description, f.category, f.type, f.amount, f.due_date, f.status, c.company_name AS client_name, ct.contract_number,
                ct.title AS contract_title
         FROM financial_entries f
         LEFT JOIN clients c ON c.id = f.client_id
         LEFT JOIN contracts ct ON ct.id = f.contract_id
         WHERE f.due_date BETWEEN :from_date AND :to_date
         ORDER BY f.due_date DESC, f.created_at DESC"
    );
    $query->execute([
        ':from_date' => $fromDate,
        ':to_date' => $toDate,
    ]);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['id', 'description', 'category', 'type', 'amount', 'due_date', 'status', 'client_name', 'contract_number', 'contract_title'];
    outputCsv('relatorio-periodo.csv', $headers, $rows);
    exit;
}

if (!isset($datasets[$type])) { http_response_code(404); exit('Relatório não encontrado.'); }

[$filename, $title, $sql] = $datasets[$type];
$rows = $pdo->query($sql)->fetchAll();
download_simple_pdf($filename, $title, $rows ? array_keys($rows[0]) : ['Sem dados'], $rows);
