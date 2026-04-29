<?php
declare(strict_types=1);

session_start();

function serveAssetIfRequested(): bool
{
    if (!isset($_GET['asset'])) {
        return false;
    }

    $asset = trim((string)$_GET['asset']);
    $map = [
        'narciso.css' => [__DIR__ . '/../assets/narciso.css', 'text/css; charset=UTF-8'],
        'heavywork.js' => [__DIR__ . '/../assets/heavywork.js', 'application/javascript; charset=UTF-8'],
        'vr_logo_2026.png' => [__DIR__ . '/../assets/images/vr_logo_2026.png', 'image/png'],
    ];

    if (!isset($map[$asset])) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Asset não encontrado.';
        exit;
    }

    [$path, $mime] = $map[$asset];
    if (!is_file($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Asset não encontrado.';
        exit;
    }

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

serveAssetIfRequested();

// --- Security headers (basic) ---
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'");

function wantsJson(): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $xrw === 'xmlhttprequest';
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirectTo(string $location): void
{
    header('Location: ' . $location, true, 303);
    exit;
}

// DEV TOOLS (testing mode) - set to false or remove for production
$showTestModeToggle = true;
$isTestMode = isset($_GET['teste']) && (string)$_GET['teste'] === '1';

// --- CSRF token ---
if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        if (wantsJson()) {
            jsonResponse(['ok' => false, 'message' => 'Pedido inválido (CSRF).'], 419);
        }
        $_SESSION['flash'] = ['type' => 'error', 'title' => 'Erro', 'message' => 'Pedido inválido. Atualize a página e tente novamente.'];
        redirectTo($_SERVER['PHP_SELF']);
    }

    require_once __DIR__ . '/../app/envelope.php';
    $result = submitForm($_POST);

    if (wantsJson()) {
        $ok = ($result['ok'] ?? false) === true;
        $status = $ok ? 200 : (int)($result['status'] ?? 400);
        jsonResponse($result, $status);
    }

    // PRG pattern (avoid form resubmission)
    if (($result['ok'] ?? false) === true) {
        $_SESSION['flash'] = ['type' => 'success', 'title' => 'Sucesso', 'message' => 'Formulário enviado com sucesso.'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'title' => 'Erro', 'message' => (string)($result['message'] ?? 'Não foi possível enviar o formulário.')];
    }

    redirectTo($_SERVER['PHP_SELF'] . ($isTestMode ? '?teste=1' : ''));
}

if ($method !== 'GET') {
    if (wantsJson()) {
        jsonResponse(['ok' => false, 'message' => 'Método não permitido.'], 405);
    }
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Método não permitido.';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

$flash = null;
if (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

require __DIR__ . '/../app/views/morf.php';

