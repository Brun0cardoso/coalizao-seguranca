<?php
/**
 * Cria o banco e as tabelas a partir de schema.sql.
 * Também compatibiliza bancos legacy que já existem sem as colunas novas.
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

function tableHasColumn(PDO $pdo, string $table, string $column): bool
{
    $columns = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN, 0);
    return in_array($column, $columns, true);
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    if (tableHasColumn($pdo, $table, $column)) {
        return;
    }

    $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
}

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

    $pdo->exec('USE ' . ($config['dbname'] ?? 'coalizao_db'));

    ensureColumn($pdo, 'quotes', 'quantity_posts', 'INT UNSIGNED NOT NULL DEFAULT 1');
    ensureColumn($pdo, 'quotes', 'billing_period', "ENUM('monthly','eventual') NOT NULL DEFAULT 'monthly'");
    ensureColumn($pdo, 'quotes', 'estimated_amount', 'DECIMAL(12,2) NULL');
    ensureColumn($pdo, 'quotes', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    ensureColumn($pdo, 'contracts', 'renewal_date', 'DATE NULL');
    ensureColumn($pdo, 'contracts', 'monthly_value', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
    ensureColumn($pdo, 'contracts', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    ensureColumn($pdo, 'financial_entries', 'contract_id', 'BIGINT UNSIGNED NULL');
    ensureColumn($pdo, 'financial_entries', 'notes', 'TEXT NULL');
    ensureColumn($pdo, 'financial_entries', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    if (!tableHasColumn($pdo, 'financial_entries', 'client_id')) {
        ensureColumn($pdo, 'financial_entries', 'client_id', 'BIGINT UNSIGNED NULL');
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS contract_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contract_id BIGINT UNSIGNED NOT NULL,
        action VARCHAR(80) NOT NULL,
        details VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_contract_history_contract (contract_id),
        KEY idx_contract_history_created_at (created_at),
        CONSTRAINT fk_contract_history_contract FOREIGN KEY (contract_id) REFERENCES contracts (id)
            ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB');

    echo "Banco coalizao_db criado e estruturado com sucesso.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Não foi possível criar ou compatibilizar o banco: {$e->getMessage()}\n");
    exit(1);
}
