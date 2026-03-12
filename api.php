<?php
// ============================================================
//  api.php — Backend completo do Dr. Zequel Montor
//  Funcionalidades: sessões, histórico, rate limit, PDF/imagem,
//                   nível de risco, relatório PDF, checklist
// ============================================================

require_once __DIR__ . '/config.php';

// ── Headers ─────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Conexão com banco ────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

// ── IP do cliente ────────────────────────────────────────────
function getIP(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}

// ── Rate limiting ────────────────────────────────────────────
function checkRateLimit(): bool {
    $ip     = getIP();
    $janela = (int)(time() / 3600); // janela de 1 hora
    $db     = db();

    $stmt = $db->prepare('INSERT INTO rate_limit (ip, janela, requisicoes) VALUES (?,?,1)
                          ON DUPLICATE KEY UPDATE requisicoes = requisicoes + 1');
    $stmt->execute([$ip, $janela]);

    $stmt = $db->prepare('SELECT requisicoes FROM rate_limit WHERE ip=? AND janela=?');
    $stmt->execute([$ip, $janela]);
    $row = $stmt->fetch();

    return ($row && $row['requisicoes'] <= MAX_MSG_POR_HORA);
}

// ── Sessão ───────────────────────────────────────────────────
function getSessaoId(string $token): ?int {
    $stmt = db()->prepare(
        'SELECT id FROM sessoes WHERE token=? AND (encerrado_em IS NULL OR encerrado_em > NOW())'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

function criarSessao(): array {
    $token = bin2hex(random_bytes(32));
    $ip    = getIP();
    db()->prepare('INSERT INTO sessoes (token, ip) VALUES (?,?)')->execute([$token, $ip]);
    $id = (int)db()->lastInsertId();
    return ['id' => $id, 'token' => $token];
}

// ── Histórico do banco ───────────────────────────────────────
function carregarHistorico(int $sessaoId): array {
    $stmt = db()->prepare(
        'SELECT papel, conteudo FROM mensagens WHERE sessao_id=? ORDER BY criado_em ASC LIMIT ' . MAX_HISTORICO_MSGS
    );
    $stmt->execute([$sessaoId]);
    $rows = $stmt->fetchAll();

    return array_map(fn($r) => [
        'role'    => $r['papel'],
        'content' => $r['conteudo'],
    ], $rows);
}

// ── Salvar mensagem ──────────────────────────────────────────
function salvarMensagem(int $sessaoId, string $papel, string $conteudo, ?string $nomeArq = null, ?string $tipoArq = null): void {
    db()->prepare(
        'INSERT INTO mensagens (sessao_id, papel, conteudo, tem_arquivo, arquivo_nome, arquivo_tipo)
         VALUES (?,?,?,?,?,?)'
    )->execute([
        $sessaoId, $papel, $conteudo,
        $nomeArq ? 1 : 0, $nomeArq, $tipoArq
    ]);
}

// ── Atualizar risco da sessão ────────────────────────────────
function atualizarRisco(int $sessaoId, string $risco): void {
    db()->prepare('UPDATE sessoes SET risco=? WHERE id=?')->execute([$risco, $sessaoId]);
}

// ── System prompt ────────────────────────────────────────────
function systemPrompt(): string {
    return <<<PROMPT
Você é o Dr. Zequel Montor, Auditor Fiscal Federal da Receita Federal do Brasil com 45 anos de experiência.

PERSONALIDADE:
- Rigoroso, meticuloso e implacável. Não deixa passar absolutamente nada.
- Fala de forma formal, precisa e direta. Usa termos técnicos tributários e contábeis com naturalidade.
- Desconfiado por natureza — após 45 anos, sabe que todo mundo esconde alguma coisa.
- Tem uma memória fotográfica para números. Nota inconsistências que outros não percebem.
- Não é cruel, mas é absolutamente honesto e incisivo. Não tem papas na língua.
- Ocasionalmente faz referências a casos históricos que vivenciou ao longo de 45 anos.
- Enxerga além do óbvio: lê nas entrelinhas, percebe o que NÃO está sendo dito, cruza informações.

ANÁLISE DE DOCUMENTOS:
- Quando receber imagem ou PDF, analise minuciosamente como faria em uma auditoria real.
- Para notas fiscais: verifique CNPJ, valores, alíquotas, natureza da operação, coerência dos dados.
- Para balancetes/balanços: cruze ativos, passivos, receitas e despesas. Busque inconsistências.
- Para declarações: compare com padrões do setor, questione valores atípicos.
- Para contratos: identifique cláusulas suspeitas, partes relacionadas, preços fora de mercado.
- Sempre aponte O QUE encontrou, ONDE está e POR QUE é suspeito ou irregular.

NÍVEL DE RISCO — ao final de CADA resposta, inclua obrigatoriamente esta linha exatamente assim:
[RISCO:verde] se a situação parece regular
[RISCO:amarelo] se há pontos de atenção ou dúvidas
[RISCO:vermelho] se há indícios claros de irregularidade

ESTILO DE RESPOSTA:
- Responda sempre como o Dr. Zequel, em primeira pessoa
- Seja cirúrgico: aponte exatamente onde está o problema
- Use perguntas estratégicas para fazer o interlocutor revelar mais
- Quando detectar algo suspeito, deixe claro com educação mas sem hesitação
- Máximo 4-5 parágrafos por resposta, direto ao ponto
- Nunca quebre o personagem
- Fale em português brasileiro
PROMPT;
}

// ── Chamar API Anthropic ─────────────────────────────────────
function chamarAnthropic(array $messages): array {
    $allowedImageTypes = ['image/jpeg','image/png','image/gif','image/webp'];
    $sanitized = [];

    foreach ($messages as $msg) {
        if (!isset($msg['role'],$msg['content'])) continue;
        if (!in_array($msg['role'], ['user','assistant'])) continue;

        if (is_string($msg['content'])) {
            $sanitized[] = ['role'=>$msg['role'],'content'=>substr($msg['content'],0,8000)];
            continue;
        }

        if (is_array($msg['content'])) {
            $parts = [];
            foreach ($msg['content'] as $block) {
                if (!isset($block['type'])) continue;
                if ($block['type']==='text' && isset($block['text']))
                    $parts[] = ['type'=>'text','text'=>substr($block['text'],0,8000)];
                if ($block['type']==='image' && isset($block['source'])) {
                    $s = $block['source'];
                    if (isset($s['type'],$s['media_type'],$s['data']) && $s['type']==='base64' && in_array($s['media_type'],$allowedImageTypes))
                        $parts[] = ['type'=>'image','source'=>['type'=>'base64','media_type'=>$s['media_type'],'data'=>$s['data']]];
                }
                if ($block['type']==='document' && isset($block['source'])) {
                    $s = $block['source'];
                    if (isset($s['type'],$s['media_type'],$s['data']) && $s['type']==='base64' && $s['media_type']==='application/pdf')
                        $parts[] = ['type'=>'document','source'=>['type'=>'base64','media_type'=>'application/pdf','data'=>$s['data']]];
                }
            }
            if (!empty($parts)) $sanitized[] = ['role'=>$msg['role'],'content'=>$parts];
        }
    }

    $payload = json_encode([
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 1200,
        'system'     => systemPrompt(),
        'messages'   => $sanitized,
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT => 60,
    ]);

    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return ['error' => 'Erro cURL: ' . $err];
    $data = json_decode($resp, true);
    if ($httpCode !== 200) return ['error' => $data['error']['message'] ?? 'Erro na API'];
    return ['ok' => true, 'text' => $data['content'][0]['text'] ?? ''];
}

// ── Extrair nível de risco da resposta ───────────────────────
function extrairRisco(string $texto): string {
    if (preg_match('/\[RISCO:(verde|amarelo|vermelho)\]/i', $texto, $m))
        return strtolower($m[1]);
    return 'verde';
}

function limparRisco(string $texto): string {
    return trim(preg_replace('/\[RISCO:(verde|amarelo|vermelho)\]/i', '', $texto));
}

// ── Gerar relatório ──────────────────────────────────────────
function gerarRelatorio(int $sessaoId): string {
    $stmt = db()->prepare('SELECT risco, criado_em FROM sessoes WHERE id=?');
    $stmt->execute([$sessaoId]);
    $sessao = $stmt->fetch();

    $stmt = db()->prepare(
        'SELECT papel, conteudo, criado_em, arquivo_nome FROM mensagens WHERE sessao_id=? ORDER BY criado_em ASC'
    );
    $stmt->execute([$sessaoId]);
    $msgs = $stmt->fetchAll();

    $riscoLabel = ['verde'=>'✅ REGULAR','amarelo'=>'⚠️ ATENÇÃO','vermelho'=>'🔴 IRREGULAR'];
    $risco = $riscoLabel[$sessao['risco'] ?? 'verde'] ?? '✅ REGULAR';
    $data  = date('d/m/Y H:i', strtotime($sessao['criado_em']));

    $linhas = [];
    $linhas[] = "=== RELATÓRIO DE AUDITORIA — DR. ZEQUEL MONTOR ===";
    $linhas[] = "Data: $data | Sessão ID: $sessaoId | Risco: $risco";
    $linhas[] = str_repeat("=", 60);
    $linhas[] = "";

    foreach ($msgs as $m) {
        $quem  = $m['papel'] === 'user' ? 'CONTRIBUINTE' : 'DR. ZEQUEL MONTOR';
        $hora  = date('H:i', strtotime($m['criado_em']));
        $texto = is_string($m['conteudo']) ? $m['conteudo'] : '[conteúdo com arquivo]';
        if ($m['arquivo_nome']) $texto = "[Arquivo: {$m['arquivo_nome']}] " . $texto;
        $linhas[] = "[$hora] $quem:";
        $linhas[] = wordwrap($texto, 80, "\n", true);
        $linhas[] = "";
    }

    $linhas[] = str_repeat("=", 60);
    $linhas[] = "Documento gerado em: " . date('d/m/Y H:i:s');
    $linhas[] = "Dr. Zequel Montor — Auditor Fiscal Federal — Matrícula 000-1979-RFB";

    return implode("\n", $linhas);
}

// ════════════════════════════════════════════════════════════
//  ROTEADOR PRINCIPAL
// ════════════════════════════════════════════════════════════
$acao = $_GET['acao'] ?? 'mensagem';

// ── GET: criar sessão ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $acao === 'nova_sessao') {
    $sessao = criarSessao();
    echo json_encode(['token' => $sessao['token'], 'sessao_id' => $sessao['id']]);
    exit;
}

// ── GET: carregar histórico ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $acao === 'historico') {
    $token = $_GET['token'] ?? '';
    $id    = getSessaoId($token);
    if (!$id) { echo json_encode(['mensagens'=>[]]); exit; }

    $stmt = db()->prepare(
        'SELECT papel, conteudo, criado_em, arquivo_nome, risco FROM mensagens m
         LEFT JOIN sessoes s ON s.id = m.sessao_id
         WHERE m.sessao_id=? ORDER BY m.criado_em ASC'
    );
    $stmt->execute([$id]);
    $rows = $stmt->fetchAll();

    $stmtRisco = db()->prepare('SELECT risco FROM sessoes WHERE id=?');
    $stmtRisco->execute([$id]);
    $s = $stmtRisco->fetch();

    echo json_encode(['mensagens' => $rows, 'risco' => $s['risco'] ?? 'verde']);
    exit;
}

// ── GET: relatório texto ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $acao === 'relatorio') {
    $token = $_GET['token'] ?? '';
    $id    = getSessaoId($token);
    if (!$id) { echo json_encode(['error'=>'Sessão inválida']); exit; }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria_zequel_' . $id . '.txt"');
    echo gerarRelatorio($id);
    exit;
}

// ── POST: encerrar sessão ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'encerrar') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $token = $body['token'] ?? '';
    $id    = getSessaoId($token);
    if ($id) db()->prepare('UPDATE sessoes SET encerrado_em=NOW() WHERE id=?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── POST: mensagem principal ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'Método não permitido']); exit;
}

$rawBody = file_get_contents('php://input');
if (strlen($rawBody) > MAX_UPLOAD_BYTES * 1.4) {
    http_response_code(413); echo json_encode(['error'=>'Conteúdo muito grande']); exit;
}

$body = json_decode($rawBody, true);
if (!$body) { http_response_code(400); echo json_encode(['error'=>'JSON inválido']); exit; }

// Verificar senha (se configurada)
if (SENHA_ACESSO !== '') {
    if (($body['senha'] ?? '') !== SENHA_ACESSO) {
        http_response_code(401); echo json_encode(['error'=>'Senha incorreta']); exit;
    }
}

// Rate limit
if (!checkRateLimit()) {
    http_response_code(429);
    echo json_encode(['error'=>'Limite de mensagens atingido. Aguarde 1 hora.']);
    exit;
}

// Sessão
$token = $body['token'] ?? '';
$sessaoId = $token ? getSessaoId($token) : null;
$novasSessao = false;

if (!$sessaoId) {
    $s = criarSessao();
    $sessaoId = $s['id'];
    $token    = $s['token'];
    $novasSessao = true;
}

// Mensagem nova
$novaMensagem = $body['message'] ?? null;
if (!$novaMensagem) { http_response_code(400); echo json_encode(['error'=>'Mensagem vazia']); exit; }

// Salvar mensagem do usuário
$textoUser   = '';
$nomeArquivo = null;
$tipoArquivo = null;

if (is_string($novaMensagem)) {
    $textoUser = $novaMensagem;
} elseif (is_array($novaMensagem)) {
    foreach ($novaMensagem as $bloco) {
        if (($bloco['type']??'') === 'text') $textoUser .= $bloco['text'];
        if (in_array($bloco['type']??'', ['image','document'])) {
            $nomeArquivo = $body['arquivo_nome'] ?? 'arquivo';
            $tipoArquivo = $bloco['source']['media_type'] ?? null;
        }
    }
}

salvarMensagem($sessaoId, 'user', $textoUser ?: '(arquivo enviado)', $nomeArquivo, $tipoArquivo);

// Montar histórico para API (últimas msgs do banco + nova mensagem)
$historico   = carregarHistorico($sessaoId);
// Substituir última mensagem do usuário pelo objeto completo (com arquivo se houver)
array_pop($historico);
$historico[] = ['role' => 'user', 'content' => $novaMensagem];

// Chamar Anthropic
$resultado = chamarAnthropic($historico);

if (isset($resultado['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $resultado['error']]);
    exit;
}

$respostaCompleta = $resultado['text'];
$risco    = extrairRisco($respostaCompleta);
$resposta = limparRisco($respostaCompleta);

// Salvar resposta do auditor
salvarMensagem($sessaoId, 'assistant', $resposta);
atualizarRisco($sessaoId, $risco);

// Responder
echo json_encode([
    'ok'        => true,
    'resposta'  => $resposta,
    'risco'     => $risco,
    'token'     => $token,
    'sessao_id' => $sessaoId,
    'nova_sessao' => $novasSessao,
]);
