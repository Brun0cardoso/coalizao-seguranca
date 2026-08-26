<?php
/** Configurações públicas e credenciais do painel. */
$config = [
    'admin_password_hash' => '',
    // E-mail que recebe os links de recuperação do painel.
    'admin_recovery_email' => 'contato@coalizacaoseguranca.com.br',
    // URL pública do site, usada no link enviado por e-mail. Ex.: https://seusite.com.br
    'site_url' => '',
    'developer_github' => 'https://github.com/Brun0cardoso',
    'whatsapp_number' => '',
    'company_email' => 'contato@coalizacaoseguranca.com.br',
    'company_address' => 'Rua Terezinha Venâncio, 100, CEP 83.025-318, Santo Antônio',
    'social_links' => ['instagram' => '', 'facebook' => '', 'linkedin' => ''],
];

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    $local = require $localConfig;
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}
return $config;
