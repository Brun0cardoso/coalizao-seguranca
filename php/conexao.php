<?php
/**
 * Conexão PDO centralizada. As credenciais ficam em conexao.local.php,
 * arquivo ignorado pelo Git.
 */
$databaseConfig = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'coalizao_db',
    'username' => 'root',
    'password' => '',
];

$localDatabaseConfig = __DIR__ . '/conexao.local.php';
if (is_file($localDatabaseConfig)) {
    $local = require $localDatabaseConfig;
    if (is_array($local)) {
        $databaseConfig = array_replace($databaseConfig, $local);
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$databaseConfig['host']};port={$databaseConfig['port']};dbname={$databaseConfig['dbname']};charset=utf8mb4",
        $databaseConfig['username'],
        $databaseConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados. Verifique se o MySQL está ativo.');
}
