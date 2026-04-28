# Formulario Val do Rio

Aplicacao web em PHP para inscricoes escolares, com:
- formulario no browser
- geracao de PDF a partir de template
- envio de email via SMTP
- area de administracao para consultar inscricoes

## Dependencias do servidor

### Obrigatorias

- PHP 8.1 ou superior
- Composer
- Servidor web com document root apontado para `public/`

### Extensoes PHP recomendadas/necessarias

- `mbstring` (usada para conversao de texto no PDF)
- `iconv` (normalizacao de nomes de ficheiro)
- `json`
- `openssl` (SMTP com TLS/SSL)

### Dependencias PHP instaladas pelo Composer

- `phpmailer/phpmailer`
- `setasign/fpdf`
- `setasign/fpdi`

## 1) Configuracao local

Na raiz do projeto:

```powershell
composer install
Copy-Item .env.example .env
```

Se `composer` nao estiver no PATH:

```powershell
php .\composer.phar install
```

Edite o `.env` com valores reais:

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME=Val do Rio Form
PDF_TEMPLATE_PATH=C:\caminho\absoluto\MDDPE1406_Ficha_Candidatura_r0_fixed.pdf
ADMIN_PASSWORD=trocar-esta-password
```

## 2) Executar localmente

### Laragon (recomendado)

- Aponte o **Document Root** do site para `public/`
- Inicie o site pelo Laragon (Apache/Nginx + PHP)

Abrir o URL do Laragon para o projeto (ex.: `http://formulario-pdf-main.test/`).

## 3) Deploy em servidor (producao)

1. Fazer upload dos ficheiros do projeto.
2. Executar `composer install --no-dev --optimize-autoloader`.
3. Criar/configurar `.env` no servidor.
4. Definir `PDF_TEMPLATE_PATH` com um caminho absoluto valido no servidor.
5. Configurar o servidor web para servir `public/` como raiz.
6. Garantir permissao de escrita em `storage/submissions/`.
7. Proteger `.env` (nunca publico) e usar `ADMIN_PASSWORD` forte.
8. Testar fluxo completo: submissao -> PDF -> email -> painel admin.

## Notas

- Nao usar Live Server estatico neste projeto (precisa de backend PHP).
- Nao versionar segredos (`.env`, passwords SMTP).
- Ferramentas opcionais de Python estao no repositorio separado: [python-mapping-tools](https://github.com/Klauxion/python-mapping-tools).
