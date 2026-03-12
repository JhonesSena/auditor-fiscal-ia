# ⚖️ Dr. Zequel Montor — Auditor Fiscal com IA

> Projeto educacional: um assistente de IA que simula um Auditor Fiscal Federal com 45 anos de experiência. Desenvolvido para fins de aprendizado em PHP, MySQL, JavaScript e integração com APIs de IA.

![Badge PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat&logo=php&logoColor=white)
![Badge MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat&logo=mysql&logoColor=white)
![Badge Anthropic](https://img.shields.io/badge/API-Anthropic_Claude-D4A843?style=flat)
![Badge License](https://img.shields.io/badge/Licença-MIT-green?style=flat)

---

## 🎯 Sobre o Projeto

Este projeto foi criado como **material didático** para ensinar na prática:

- Integração com **APIs de IA** (Anthropic Claude)
- Desenvolvimento **fullstack** com PHP + MySQL + JavaScript puro
- Criação de **personas de IA** com system prompts
- Boas práticas de **segurança em PHP** (rate limiting, validação, PDO)
- **Persistência de sessões** e histórico de conversas
- Upload e análise de **imagens e PDFs** via IA

---

## 🖥️ Demo

O sistema permite:

- 💬 Conversar com o Dr. Zequel sobre situações fiscais
- 📎 Enviar imagens e PDFs para análise (notas fiscais, balanços, contratos)
- 🎯 Receber indicação de risco: ✅ Regular / ⚠️ Atenção / 🔴 Irregular
- 📋 Usar checklist com tópicos fiscais pré-definidos
- ⬇️ Baixar relatório completo da conversa em `.txt`
- 🔁 Iniciar novas sessões de auditoria

---

## 📁 Estrutura do Projeto

```
dr-zequel-montor/
│
├── index.html          → Frontend completo (HTML + CSS + JS)
├── api.php             → Backend PHP com todas as rotas
├── config.php          → ⚙️ Configurações (editar antes de usar)
├── banco.sql           → Script de criação do banco de dados
├── diario_de_bordo.md  → Documentação técnica completa
├── .gitignore          → Ignora config.php do repositório
└── README.md           → Este arquivo
```

---

## 🚀 Como Usar

### Pré-requisitos

- Servidor PHP 7.4+ com cURL e PDO habilitados
- MySQL 5.7+ ou MariaDB 10.3+
- Conta na [Anthropic](https://console.anthropic.com) com créditos de API

### Instalação

**1. Clone o repositório**
```bash
git clone https://github.com/seu-usuario/dr-zequel-montor.git
cd dr-zequel-montor
```

**2. Crie o banco de dados**
```bash
mysql -u seu_usuario -p < banco.sql
```

**3. Configure as credenciais**

Copie o arquivo de exemplo e edite:
```bash
cp config.exemplo.php config.php
```

Abra `config.php` e preencha:
```php
define('ANTHROPIC_API_KEY', 'sk-ant-SUA-CHAVE-AQUI');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
```

**4. Suba os arquivos no servidor e acesse pelo navegador**

---

## 🔐 Segurança

- A chave da API **nunca fica exposta** no frontend
- Rate limiting por IP (30 msgs/hora por padrão)
- Validação de tipos de arquivo e tamanho
- PDO com prepared statements (proteção contra SQL Injection)
- Senha de acesso opcional configurável

> ⚠️ **Nunca faça commit do seu `config.php`** com a chave real. O `.gitignore` já o exclui por padrão.

---

## 🧠 Como Funciona a IA

O personagem é definido por um **system prompt** detalhado enviado à API do Claude a cada interação. O prompt define:

- Personalidade e tom do personagem
- Especialidades técnicas (IRPJ, Transfer Pricing, etc.)
- Instruções de análise de documentos
- Formato de resposta com nível de risco

Isso demonstra na prática como **engenharia de prompt** pode criar experiências ricas com IA.

---

## 📚 Conteúdo Didático

Este projeto é usado em aula para ensinar:

| Tema | Onde ver no código |
|---|---|
| Integração com API REST | `api.php` → função `chamarAnthropic()` |
| System Prompt / Persona IA | `api.php` → função `systemPrompt()` |
| Sessões com banco de dados | `api.php` → funções `criarSessao()`, `carregarHistorico()` |
| Rate Limiting | `api.php` → função `checkRateLimit()` |
| Upload base64 | `index.html` → função `selecionarArquivo()` |
| Web Audio API (som) | `index.html` → função `tocarCarimbo()` |
| CSS Variables + Animações | `index.html` → bloco `<style>` |

---

## 🛠️ Tecnologias

- **Frontend:** HTML5, CSS3, JavaScript ES6+ (sem frameworks)
- **Backend:** PHP 8.1
- **Banco:** MySQL / MariaDB
- **IA:** Anthropic Claude (`claude-sonnet-4-20250514`)
- **Fontes:** Google Fonts (Playfair Display + Courier Prime)

---

## 📄 Licença

MIT License — use, modifique e compartilhe à vontade, com os devidos créditos.

---

## 👨‍🏫 Autor

Projeto desenvolvido com fins educacionais.  
Contribuições, issues e pull requests são bem-vindos!

---

> *"Em 45 anos nunca deixei uma vírgula escapar. Não vou começar agora."*  
> — Dr. Zequel Montor
