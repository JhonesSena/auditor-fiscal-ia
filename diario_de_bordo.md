# 📋 Diário de Bordo — Dr. Zequel Montor
### Sistema de Auditoria Fiscal com IA
> Documentação técnica completa do projeto

---

## 🗂 Índice

1. [Visão Geral](#visão-geral)
2. [Estrutura de Arquivos](#estrutura-de-arquivos)
3. [Requisitos do Servidor](#requisitos-do-servidor)
4. [Instalação Passo a Passo](#instalação-passo-a-passo)
5. [Configuração (config.php)](#configuração-configphp)
6. [Banco de Dados](#banco-de-dados)
7. [API — Rotas e Parâmetros](#api--rotas-e-parâmetros)
8. [Frontend — Funcionalidades](#frontend--funcionalidades)
9. [Fluxo de uma Conversa](#fluxo-de-uma-conversa)
10. [Segurança](#segurança)
11. [Troubleshooting](#troubleshooting)
12. [Registro de Versões](#registro-de-versões)

---

## Visão Geral

O **Dr. Zequel Montor** é um sistema de chat com IA que simula um Auditor Fiscal Federal com 45 anos de experiência. O usuário pode conversar com o personagem, enviar documentos (imagens e PDFs) para análise e receber um parecer com indicação de nível de risco fiscal.

O sistema é composto por:

- **Frontend** em HTML/CSS/JavaScript puro (sem frameworks)
- **Backend** em PHP com chamadas à API da Anthropic (Claude)
- **Banco de dados** MySQL para persistência de sessões e histórico
- **Integração** com o modelo `claude-sonnet-4-20250514` via API REST

---

## Estrutura de Arquivos

```
/pasta-do-projeto/
│
├── index.html       → Interface visual do chat (frontend completo)
├── api.php          → Backend: gerencia sessões, mensagens, rate limit e chamadas à API
├── config.php       → Configurações: chave da API, banco de dados, senha de acesso
└── banco.sql        → Script SQL para criação das tabelas (rodar uma vez)
```

---

## Requisitos do Servidor

| Requisito | Versão mínima | Notas |
|---|---|---|
| PHP | 7.4+ | Recomendado 8.1+ |
| MySQL / MariaDB | 5.7+ / 10.3+ | Charset utf8mb4 |
| Extensão cURL | habilitada | Para chamadas à API Anthropic |
| Extensão PDO | habilitada | Para conexão com o banco |
| HTTPS | recomendado | Protege a chave de API em trânsito |

---

## Instalação Passo a Passo

### 1. Criar o banco de dados

Acesse o **phpMyAdmin** ou o terminal MySQL da sua hospedagem e execute:

```sql
-- Opção A: via phpMyAdmin
-- Importe o arquivo banco.sql diretamente pela interface

-- Opção B: via terminal
mysql -u seu_usuario -p < banco.sql
```

O script cria automaticamente o banco `auditor_fiscal` e as tabelas necessárias.

### 2. Configurar credenciais

Abra o arquivo `config.php` e preencha:

```php
define('ANTHROPIC_API_KEY', 'sk-ant-SUA-CHAVE-REAL-AQUI');
define('DB_HOST', 'localhost');
define('DB_NAME', 'auditor_fiscal');
define('DB_USER', 'seu_usuario_mysql');
define('DB_PASS', 'sua_senha_mysql');
```

### 3. Obter a chave da API Anthropic

1. Acesse [console.anthropic.com](https://console.anthropic.com)
2. Crie uma conta ou faça login
3. No menu lateral, clique em **API Keys**
4. Clique em **Create Key**, dê um nome (ex: `auditor-zequel`)
5. **Copie a chave imediatamente** — ela só aparece uma vez
6. Em **Billing**, adicione créditos para o consumo funcionar

> ⚠️ A chave começa sempre com `sk-ant-`

### 4. Fazer upload dos arquivos

Suba os 4 arquivos para a mesma pasta no servidor (ex: `public_html/auditor/`):

```
index.html
api.php
config.php
banco.sql   ← pode apagar depois de rodar
```

### 5. Testar

Acesse pelo navegador: `https://seusite.com.br/auditor/`

Se aparecer a interface do Dr. Zequel Montor, está funcionando. ✅

---

## Configuração (config.php)

Todas as opções do sistema ficam centralizadas no `config.php`:

```php
// Chave da API Anthropic
define('ANTHROPIC_API_KEY', 'sk-ant-...');

// Banco de dados MySQL
define('DB_HOST',    'localhost');
define('DB_NAME',    'auditor_fiscal');
define('DB_USER',    'root');
define('DB_PASS',    'senha');
define('DB_CHARSET', 'utf8mb4');

// Senha de acesso à interface (deixe '' para desativar)
define('SENHA_ACESSO', '');

// Limites de uso
define('MAX_MSG_POR_HORA',    30);   // mensagens por IP por hora
define('MAX_SESSOES_POR_IP',  10);   // sessões ativas por IP
define('MAX_HISTORICO_MSGS',  20);   // msgs enviadas ao contexto da IA
define('MAX_UPLOAD_BYTES',  5242880); // 5 MB por arquivo
define('SESSAO_EXPIRA_HORAS', 24);   // horas até a sessão expirar

// CORS
define('CORS_ORIGIN', '*'); // ou 'https://seusite.com.br'
```

---

## Banco de Dados

### Diagrama das tabelas

```
sessoes
├── id              INT (PK, auto increment)
├── token           VARCHAR(64) — identificador único da sessão
├── ip              VARCHAR(45) — IP do usuário
├── criado_em       DATETIME
├── atualizado_em   DATETIME
├── encerrado_em    DATETIME (null = sessão ativa)
├── risco           ENUM(verde, amarelo, vermelho)
└── resumo          TEXT (reservado para uso futuro)

mensagens
├── id              INT (PK)
├── sessao_id       INT (FK → sessoes.id)
├── papel           ENUM(user, assistant)
├── conteudo        MEDIUMTEXT — texto da mensagem
├── tem_arquivo     TINYINT(1)
├── arquivo_nome    VARCHAR(255)
├── arquivo_tipo    VARCHAR(100) — MIME type
└── criado_em       DATETIME

rate_limit
├── ip              VARCHAR(45) (PK parcial)
├── janela          INT — timestamp arredondado por hora (PK parcial)
└── requisicoes     SMALLINT — contador de requisições na janela

log_acesso
├── id              INT (PK)
├── ip              VARCHAR(45)
├── sessao_id       INT (null ok)
├── acao            VARCHAR(50)
└── criado_em       DATETIME
```

### Limpeza manual (opcional)

Execute periodicamente para manter o banco leve:

```sql
-- Apagar sessões com mais de 90 dias
DELETE FROM sessoes WHERE criado_em < NOW() - INTERVAL 90 DAY;

-- Apagar rate_limit expirado
DELETE FROM rate_limit WHERE janela < UNIX_TIMESTAMP() - 7200;

-- Apagar logs antigos
DELETE FROM log_acesso WHERE criado_em < NOW() - INTERVAL 30 DAY;
```

---

## API — Rotas e Parâmetros

### `GET api.php?acao=nova_sessao`

Cria uma nova sessão de auditoria.

**Resposta:**
```json
{
  "token": "abc123...",
  "sessao_id": 42
}
```

---

### `GET api.php?acao=historico&token=TOKEN`

Retorna o histórico de mensagens de uma sessão.

**Resposta:**
```json
{
  "mensagens": [
    { "papel": "user", "conteudo": "...", "criado_em": "2025-03-11 10:00:00" },
    { "papel": "assistant", "conteudo": "...", "criado_em": "2025-03-11 10:00:05" }
  ],
  "risco": "verde"
}
```

---

### `GET api.php?acao=relatorio&token=TOKEN`

Faz download do relatório da sessão em `.txt`.

**Headers de resposta:**
```
Content-Type: text/plain
Content-Disposition: attachment; filename="auditoria_zequel_42.txt"
```

---

### `POST api.php` (mensagem principal)

Envia uma mensagem para o Dr. Zequel Montor.

**Body (JSON):**
```json
{
  "token": "abc123...",
  "message": "Preciso analisar esta nota fiscal.",
  "arquivo_nome": "nota.pdf"
}
```

Para mensagens com arquivo, `message` é um **array de blocos**:
```json
{
  "token": "abc123...",
  "arquivo_nome": "nota.jpg",
  "message": [
    {
      "type": "image",
      "source": {
        "type": "base64",
        "media_type": "image/jpeg",
        "data": "BASE64_DA_IMAGEM"
      }
    },
    {
      "type": "text",
      "text": "Analise esta nota fiscal."
    }
  ]
}
```

**Resposta:**
```json
{
  "ok": true,
  "resposta": "Texto da resposta do Dr. Zequel...",
  "risco": "amarelo",
  "token": "abc123...",
  "sessao_id": 42,
  "nova_sessao": false
}
```

**Possíveis erros:**
```json
{ "error": "Limite de mensagens atingido. Aguarde 1 hora." }
{ "error": "Sessão inválida" }
{ "error": "Conteúdo muito grande" }
{ "error": "Senha incorreta" }
```

---

### `POST api.php?acao=encerrar`

Encerra uma sessão ativa.

**Body:**
```json
{ "token": "abc123..." }
```

---

## Frontend — Funcionalidades

### Interface principal

| Elemento | Descrição |
|---|---|
| Badge ⚖ | Animação de pulso dourada no header |
| Indicador de risco | Badge no canto superior direito: ✅ Verde / ⚠️ Amarelo / 🔴 Vermelho |
| Sidebar | Ficha técnica, atributos, especialidades e checklist |
| Área de chat | Mensagens com animação de entrada |
| Indicador de digitação | Pontinhos animados enquanto o Dr. Zequel "pensa" |

### Checklist rápido

Botões pré-configurados na sidebar que enviam automaticamente um tópico para o auditor:

- 🧾 Analisar Nota Fiscal
- 🔍 Verificar CNPJ
- 👥 Revisar Folha de Pagamento
- 📊 Analisar Balanço
- 🌐 Bens no Exterior
- 📝 Contratos Partes Relacionadas

### Upload de documentos

Formatos aceitos: `JPG`, `PNG`, `GIF`, `WEBP`, `PDF`
Tamanho máximo: **5 MB** por arquivo

O arquivo é convertido para Base64 no navegador e enviado junto com a mensagem. Imagens aparecem como miniatura no chat; PDFs aparecem como etiqueta com o nome do arquivo.

### Indicador de risco

O Dr. Zequel inclui ao final de cada resposta uma tag `[RISCO:verde|amarelo|vermelho]` que é:

1. Detectada pelo backend com regex
2. Removida do texto exibido ao usuário
3. Salva no banco na coluna `sessoes.risco`
4. Exibida visualmente no badge do header e abaixo da mensagem

### Som de carimbo

Quando o risco detectado é `vermelho`, o sistema toca um som sintético de carimbo gerado pela Web Audio API — sem dependências externas.

### Persistência de sessão

O token e o ID da sessão são salvos no `localStorage` do navegador. Ao recarregar a página, a sessão é retomada automaticamente (enquanto não expirar).

### Relatório

Ao clicar em **⬇ Relatório**, o navegador baixa um arquivo `.txt` com toda a conversa formatada, incluindo horários, nível de risco e identificação do auditor.

### Nova Auditoria

O botão **⟳ Nova Auditoria** na sidebar encerra a sessão atual no banco e abre uma sessão nova limpa.

---

## Fluxo de uma Conversa

```
Usuário abre o sistema
        │
        ▼
Frontend verifica localStorage
        │
   ┌────┴────┐
   │ tem     │ não tem
   │ token?  │
   │         ▼
   │   GET nova_sessao → token salvo no localStorage
   │
   ▼
Usuário digita mensagem (+ arquivo opcional)
        │
        ▼
Frontend converte arquivo para Base64 (se houver)
        │
        ▼
POST api.php { token, message, arquivo_nome }
        │
        ▼
api.php verifica:
  ├─ Rate limit (IP)
  ├─ Sessão válida (ou cria nova)
  └─ Senha (se configurada)
        │
        ▼
Salva mensagem do usuário no banco (mensagens)
        │
        ▼
Carrega histórico das últimas 20 msgs do banco
        │
        ▼
Chama API Anthropic (claude-sonnet-4-20250514)
        │
        ▼
Extrai nível de risco da resposta [RISCO:xxx]
        │
        ▼
Salva resposta do auditor + atualiza risco na sessão
        │
        ▼
Retorna JSON { resposta, risco, token, sessao_id }
        │
        ▼
Frontend exibe mensagem + atualiza badge de risco
```

---

## Segurança

### Proteções implementadas

| Proteção | Como funciona |
|---|---|
| **Chave de API oculta** | Nunca exposta no frontend — fica apenas no `api.php` no servidor |
| **Rate limiting** | Máximo de 30 requisições por IP por hora (tabela `rate_limit`) |
| **Validação de tipos** | Apenas `image/jpeg`, `image/png`, `image/gif`, `image/webp` e `application/pdf` são aceitos |
| **Limite de tamanho** | Requisições acima de ~7 MB são rejeitadas com HTTP 413 |
| **Sanitização** | Mensagens e blocos de conteúdo são validados antes de chegar à API |
| **Histórico limitado** | Máximo de 20 mensagens enviadas ao contexto (evita custos excessivos) |
| **Senha de acesso** | Opcional — protege o sistema de uso não autorizado |
| **PDO com prepared statements** | Protege contra SQL Injection |

### Recomendações adicionais

- Coloque o sistema em **HTTPS** para proteger os dados em trânsito
- No `config.php`, troque `CORS_ORIGIN` de `*` para o domínio real do seu site
- Considere colocar o `config.php` **fora do `public_html`** e usar `require_once` com caminho absoluto
- Monitore o consumo da API Anthropic em [console.anthropic.com](https://console.anthropic.com) para evitar surpresas na fatura

---

## Troubleshooting

### "Houve um problema técnico na conexão"

- Verifique se a chave da API em `config.php` está correta e tem créditos
- Confirme que a extensão `cURL` do PHP está habilitada
- Teste a conexão com: `php -r "var_dump(curl_version());"`

### Banco de dados não conecta

- Confirme `DB_HOST`, `DB_USER`, `DB_PASS` e `DB_NAME` no `config.php`
- Verifique se o banco `auditor_fiscal` foi criado e o script `banco.sql` foi executado
- Confirme que a extensão `PDO` e `PDO_MySQL` estão habilitadas no PHP

### Arquivo não é enviado

- Verifique se o arquivo tem menos de 5 MB
- Confirme que o tipo é aceito: JPG, PNG, GIF, WEBP ou PDF
- Em alguns servidores é necessário aumentar `upload_max_filesize` e `post_max_size` no `php.ini`

### Rate limit atingido

- Aguarde 1 hora ou aumente `MAX_MSG_POR_HORA` no `config.php`
- Para limpar manualmente: `DELETE FROM rate_limit;`

### Sessão não persiste

- Verifique se o navegador permite `localStorage` (bloqueado em modo anônimo em alguns casos)
- Confirme que as colunas e tabelas foram criadas corretamente pelo `banco.sql`

---

## Registro de Versões

| Versão | Data | Mudanças |
|---|---|---|
| 1.0 | Mar/2025 | Versão inicial — chat com IA, interface básica |
| 1.1 | Mar/2025 | Suporte a upload de imagens e PDFs |
| 1.2 | Mar/2025 | Renomeação do personagem para Dr. Zequel Montor |
| 2.0 | Mar/2025 | Backend PHP completo com banco MySQL, sessões persistentes, rate limiting, nível de risco, checklist, relatório .txt, som de carimbo, nova auditoria, senha de acesso |

---

*Documentação gerada em março de 2025 — Dr. Zequel Montor — Sistema de Auditoria Fiscal com IA*
*"Em 45 anos nunca deixei uma vírgula escapar. Não vou começar agora."*
