<?php
/**
 * Cria o banco e as tabelas a partir de schema.sql.
 * Execute somente pelo terminal: php database/setup.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execução permitida somente pelo terminal.');
}

$config = require __DIR__ . '/../php/conexao.local.php';
$dsn = sprintf(
    'mysql:host=%s;port=%d;charset=utf8mb4',
    $config['host'] ?? '127.0.0.1',
    $config['port'] ?? 3306
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $statements = preg_split('/;\s*(?:\R|$)/', $schema);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    echo "Banco coalizao_db criado e estruturado com sucesso.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Não foi possível criar o banco: {$e->getMessage()}\n");
    exit(1);
}
