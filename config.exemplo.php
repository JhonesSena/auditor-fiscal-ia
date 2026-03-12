<?php
// ============================================================
//  config.exemplo.php — Copie para config.php e preencha
//  NUNCA commite o config.php com dados reais!
// ============================================================

define('ANTHROPIC_API_KEY', 'sk-ant-COLOQUE-SUA-CHAVE-AQUI');

define('DB_HOST',    'localhost');
define('DB_NAME',    'auditor_fiscal');
define('DB_USER',    'SEU_USUARIO_MYSQL');
define('DB_PASS',    'SUA_SENHA_MYSQL');
define('DB_CHARSET', 'utf8mb4');

define('SENHA_ACESSO', '');          // deixe '' para desativar

define('MAX_MSG_POR_HORA',    30);
define('MAX_SESSOES_POR_IP',  10);
define('MAX_HISTORICO_MSGS',  20);
define('MAX_UPLOAD_BYTES',  5242880);
define('SESSAO_EXPIRA_HORAS', 24);

define('CORS_ORIGIN', '*');
