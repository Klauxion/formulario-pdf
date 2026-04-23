<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/admin-auth.php';

function redirectToAdmin(): void
{
    header('Location: admin.php');
    exit;
}

function removePdfFromEntry(array $entry, string $submissionsDir): void
{
    if (empty($entry['pdf_file'])) {
        return;
    }

    $pdfPath = $submissionsDir . DIRECTORY_SEPARATOR . basename((string)$entry['pdf_file']);
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

function normalizeClientIp(?string $ip): string
{
    $value = trim((string)$ip);
    if ($value === '' || strtolower($value) === 'unknown') {
        return 'unknown';
    }

    if ($value === '::1') {
        return '127.0.0.1';
    }

    if (str_starts_with($value, '::ffff:')) {
        $mappedIpv4 = substr($value, 7);
        if (filter_var($mappedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $mappedIpv4;
        }
    }

    return $value;
}

function getSubmissionId(array $entry): string
{
    $id = trim((string)($entry['submission_id'] ?? ''));
    return $id !== '' ? $id : '-';
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];
    session_destroy();
    header('Location: admin-login.php');
    exit;
}

if (empty($_SESSION[ADMIN_SESSION_KEY])) {
    header('Location: admin-login.php');
    exit;
}

$storageFile = __DIR__ . DIRECTORY_SEPARATOR . 'submissions' . DIRECTORY_SEPARATOR . 'inscricoes.jsonl';
$submissionsDir = __DIR__ . DIRECTORY_SEPARATOR . 'submissions';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

    if ($action === 'delete_one') {
        $lineToDelete = isset($_POST['line']) ? (int)$_POST['line'] : -1;

        if ($lineToDelete >= 0 && is_file($storageFile)) {
            $lines = file($storageFile, FILE_IGNORE_NEW_LINES);
            if (is_array($lines) && isset($lines[$lineToDelete])) {
                $entry = json_decode($lines[$lineToDelete], true);
                if (is_array($entry)) {
                    removePdfFromEntry($entry, $submissionsDir);
                }

                unset($lines[$lineToDelete]);
                $newContent = implode(PHP_EOL, $lines);
                if ($newContent !== '') {
                    $newContent .= PHP_EOL;
                }
                file_put_contents($storageFile, $newContent, LOCK_EX);
            }
        }

        redirectToAdmin();
    }

    if ($action === 'delete_all') {
        if (is_file($storageFile)) {
            $lines = file($storageFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $entry = json_decode($line, true);
                    if (is_array($entry)) {
                        removePdfFromEntry($entry, $submissionsDir);
                    }
                }
            }
            file_put_contents($storageFile, '', LOCK_EX);
        }

        redirectToAdmin();
    }
}

if (isset($_GET['download']) && $_GET['download'] === '1') {
    if (!is_file($storageFile)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nenhum ficheiro de inscricoes encontrado.';
        exit;
    }

    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Content-Disposition: attachment; filename="inscricoes.jsonl"');
    readfile($storageFile);
    exit;
}

$entries = [];
$allFields = [];

if (is_file($storageFile)) {
    $lines = file($storageFile, FILE_IGNORE_NEW_LINES);

    if (is_array($lines)) {
        foreach ($lines as $lineIdx => $line) {
            if (trim($line) === '') {
                continue;
            }
            $entry = json_decode($line, true);
            if (!is_array($entry) || !isset($entry['data']) || !is_array($entry['data'])) {
                continue;
            }

            $entry['client_ip'] = normalizeClientIp((string)($entry['client_ip'] ?? $entry['ip'] ?? 'unknown'));
            $entry['ip'] = $entry['client_ip'];

            $entry['_line'] = $lineIdx;
            $entries[] = $entry;
            foreach (array_keys($entry['data']) as $field) {
                $allFields[$field] = true;
            }
        }
    }
}

$fieldNames = array_keys($allFields);
sort($fieldNames);
usort($entries, static function (array $a, array $b): int {
    return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
});
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Painel Admin - Inscricoes</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; background: #f5f6f8; }
    h1 { margin: 0 0 8px; }
    .top-actions { margin: 12px 0 18px; }
    .top-actions a {
      display: inline-block;
      margin-right: 12px;
      padding: 8px 12px;
      text-decoration: none;
      color: #fff;
      background: #4caf50;
      border-radius: 6px;
    }
    .card {
      background: #fff;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
      overflow-x: auto;
      display: block;
    }
    th, td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
      vertical-align: top;
      white-space: nowrap;
    }
    th { background: #f0f0f0; }
    .muted { color: #666; }
    .danger {
      border: 0;
      border-radius: 6px;
      color: #fff;
      background: #dc2626;
      padding: 8px 12px;
      cursor: pointer;
      font-weight: 600;
    }
    .danger:hover { background: #b91c1c; }
    .mini-form { margin: 0; }
    .actions-cell { min-width: 110px; }
  </style>
</head>
<body>
  <h1>Painel Admin</h1>
  <p class="muted">Submissoes guardadas pelo formulario.</p>

  <div class="top-actions">
    <a href="index.html">Voltar ao formulario</a>
    <a href="admin.php?download=1">Download JSONL</a>
    <a href="admin.php?logout=1">Sair</a>
    <form method="post" class="mini-form" onsubmit="return confirm('Apagar todos os formulários?');" style="display:inline-block;">
      <input type="hidden" name="action" value="delete_all">
      <button type="submit" class="danger">Apagar todos</button>
    </form>
  </div>

  <div class="card">
    <p><strong>Total de inscricoes:</strong> <?= count($entries) ?></p>

    <?php if (count($entries) === 0): ?>
      <p>Ainda nao existem inscricoes guardadas.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Data/Hora</th>
            <th>IP</th>
            <?php foreach ($fieldNames as $field): ?>
              <th><?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?></th>
            <?php endforeach; ?>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($entries as $entry): ?>
            <tr>
              <td><?= htmlspecialchars(getSubmissionId($entry), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($entry['timestamp'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($entry['ip'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <?php foreach ($fieldNames as $field): ?>
                <td><?= htmlspecialchars((string)($entry['data'][$field] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
              <?php endforeach; ?>
              <td class="actions-cell">
                <form method="post" class="mini-form" onsubmit="return confirm('Apagar este formulário?');">
                  <input type="hidden" name="action" value="delete_one">
                  <input type="hidden" name="line" value="<?= (int)$entry['_line'] ?>">
                  <button type="submit" class="danger">Apagar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
