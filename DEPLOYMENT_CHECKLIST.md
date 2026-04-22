# Checklist de Implementação - Ficha de Inscrição Val do Rio

## 📋 Pré-requisitos do Servidor

Antes de fazer o upload, certifique-se de que o servidor possui:

- ✅ **PHP 7.4+** (mínimo recomendado: PHP 8.0)
- ✅ **Extensões PHP ativas:**
  - `json` (geralmente ativa por padrão)
  - `filter` (validação de emails)
  - `fileinfo` (manipulação de arquivos)
  - `date` (processamento de datas)
- ✅ **SMTP configurado** (para envio de emails) - dados fornecidos em `smtp-config.php`

## 📁 Estrutura de Arquivos a Fazer Upload

```
valdoRio-formulario-v1.4/
├── public/                          (raiz do servidor web)
│   ├── index.html                   ✅ Formulário principal
│   ├── script.js                    ✅ Lógica do formulário
│   ├── style.css                    ✅ Estilos
│   ├── submit.php                   ✅ Processamento do formulário
│   ├── smtp-config.php              ✅ Configuração de email
│   ├── vr_logo_2026.png             ✅ Logo da escola
│   └── submissions/                 📁 Pasta para PDFs gerados (criar no servidor)
├── vendor/                          ✅ Bibliotecas PHP (FPDF, PHPMailer)
├── admin-login.php                  ✅ Acesso para administrador
├── admin.php                        ✅ Painel do administrador
└── storage/                         📁 Pasta para dados (criar no servidor)
    └── submissions/                 📁 Subcasta para registros
```

## 🚀 Passos de Implementação

### 1️⃣ Preparar o Servidor
```bash
# Conexão SSH ao servidor (exemplo)
ssh usuario@seu-servidor.com

# Criar pasta do projeto
mkdir -p /var/www/valdoRio-formulario
cd /var/www/valdoRio-formulario
```

### 2️⃣ Fazer Upload dos Arquivos
Use FTP, SFTP ou SCP para fazer upload de todos os arquivos:
- ✅ Incluir pasta `vendor/` completa
- ✅ Incluir pasta `public/` com todos os arquivos
- ✅ Incluir arquivo `smtp-config.php`
- ✅ Incluir arquivos `admin-*.php`

### 3️⃣ Criar Pastas Necessárias
```bash
mkdir -p public/submissions
mkdir -p storage/submissions
chmod 755 public/submissions
chmod 755 storage/submissions
```

### 4️⃣ Configurar Email (IMPORTANTE)
Editar arquivo `smtp-config.php`:
```php
return [
    'host' => 'seu-servidor-smtp.com',      // Ex: smtp.gmail.com
    'port' => 587,                           // Porta SMTP (587 ou 465)
    'username' => 'seu-email@dominio.com',   // Email para envio
    'password' => 'sua-senha-app',           // Senha ou token
    'secure' => 'tls',                       // 'tls' ou 'ssl'
    'from_email' => 'seu-email@dominio.com', // Email de origem
    'from_name' => 'Val do Rio',             // Nome de origem
];
```

### 5️⃣ Testar a Instalação
1. Abra no navegador: `https://seu-dominio.com/index.html`
2. Preencha o formulário com dados de teste
3. Clique em "Enviar formulário"
4. Verifique se:
   - ✅ PDF foi gerado em `public/submissions/`
   - ✅ Email foi recebido
   - ✅ Arquivo JSON criado em `storage/submissions/inscricoes.jsonl`

## 🔧 Configurações Recomendadas

### Permissões de Arquivos
```bash
# Permissões de pasta
chmod 755 public/
chmod 755 storage/
chmod 755 public/submissions/
chmod 755 storage/submissions/

# Permissões de arquivo PHP
chmod 644 public/*.php
chmod 644 public/*.php
```

### Configuração Apache (.htaccess)
Se usar Apache, criar arquivo `.htaccess` na raiz:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Impedir acesso direto a arquivos de configuração
    RewriteRule ^smtp-config\.php$ - [F]
    RewriteRule ^admin-auth\.php$ - [F]
</IfModule>

# Bloquear listagem de diretórios
Options -Indexes
```

### Configuração Nginx
Se usar Nginx, adicionar ao bloco `server`:
```nginx
# Bloquear acesso a arquivos de configuração
location ~ /smtp-config\.php$ {
    deny all;
}
location ~ /admin-auth\.php$ {
    deny all;
}
```

## 📝 Estrutura de Dados Gerada

### PDF Gerado
- **Localização:** `public/submissions/[nome]_[data]_[hora].pdf`
- **Conteúdo:** Ficha de inscrição preenchida com caixas azuis
- **Incluído na:** Email enviado para o candidato

### Registro JSON
- **Localização:** `storage/submissions/inscricoes.jsonl`
- **Formato:** Uma linha JSON por inscrição (JSONL)
- **Contém:** 
  - ID da inscrição
  - Data/hora de submissão
  - IP do candidato
  - Todos os dados do formulário
  - Nome do arquivo PDF

## 🛡️ Segurança

### Proteger Dados Sensíveis
1. ✅ Arquivo `smtp-config.php` nunca deve ser acessível via web
2. ✅ Pasta `storage/` deve estar fora do documento root se possível
3. ✅ Usar HTTPS em produção (obrigatório!)
4. ✅ Configurar firewall para limitar acesso a `/admin-*`

### Backup Regular
```bash
# Backup diário (adicionar ao crontab)
0 2 * * * tar -czf /backups/valdoRio-$(date +\%Y\%m\%d).tar.gz /var/www/valdoRio-formulario/storage/
```

## ❌ Arquivos a NÃO Fazer Upload

Os seguintes arquivos NÃO são necessários para produção:
- ❌ `submit_template.php` (alternativa não utilizada)
- ❌ `scripts/fill_pdf_template.py` (Python não instalado)
- ❌ `public/test-python.php` (arquivo de teste)
- ❌ `basePDF_image/` (pasta de template não utilizada)
- ❌ `.git/` (histórico do git)
- ❌ `node_modules/` (se existir)
- ❌ Arquivos `*.md` de desenvolvimento (opcional)

## 🐛 Troubleshooting

### PDFs não estão sendo gerados
```bash
# Verificar permissões
ls -la public/submissions/

# Verificar se PHP pode escrever
touch public/submissions/test.txt
rm public/submissions/test.txt
```

### Emails não estão sendo enviados
1. Verificar credenciais SMTP em `smtp-config.php`
2. Testar porta SMTP (587 ou 465)
3. Verificar se servidor SMTP permite autenticação
4. Verificar se firewall bloqueia porta SMTP

### Formulário não funciona
1. Abrir console do navegador (F12 → Console)
2. Procurar por erros em vermelho
3. Verificar aba Network para erros de requisição
4. Verificar logs PHP do servidor

## 📞 Suporte

Se encontrar problemas:
1. Verificar arquivo de erro PHP: `/var/log/php-fpm.log` ou similar
2. Verificar logs do servidor web (Apache/Nginx)
3. Testar conectividade SMTP separadamente
4. Garantir que todas as permissões estão corretas

## ✅ Checklist Final

Antes de considerar pronto:
- [ ] Todos os arquivos foram feitos upload
- [ ] Pastas `submissions/` foram criadas
- [ ] `smtp-config.php` foi configurado corretamente
- [ ] Formulário abre sem erros
- [ ] Teste de submissão funciona completamente
- [ ] PDF é gerado corretamente
- [ ] Email é recebido
- [ ] Arquivo JSON é criado
- [ ] HTTPS está ativo
- [ ] Permissões de pasta estão corretas
- [ ] Backup automático foi configurado

---

**Última atualização:** 21 de Abril de 2026  
**Versão:** 1.4  
**Desenvolvido para:** Escola Profissional Val do Rio
