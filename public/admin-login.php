<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/admin-auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ADMIN_PASSWORD === '') {
        $error = 'Admin desativado: define ADMIN_PASSWORD no ficheiro .env';
    }
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    if (ADMIN_PASSWORD !== '' && hash_equals(ADMIN_PASSWORD, $password)) {
        $_SESSION[ADMIN_SESSION_KEY] = true;
        header('Location: admin.php');
        exit;
    }
    if (!isset($error)) {
        $error = 'Senha inválida.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Login Admin</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; min-height: 100vh; display: grid; place-items: center; }
    .card { width: min(380px, 92vw); background: #fff; border-radius: 10px; box-shadow: 0 8px 22px rgba(0,0,0,.12); padding: 20px; }
    h1 { margin: 0 0 8px; font-size: 22px; }
    p { margin: 0 0 14px; color: #5b6675; }
    label { display: block; margin-bottom: 6px; font-weight: 600; color: #334155; }
    input { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 12px; }
    button { width: 100%; border: 0; background: #4caf50; color: #fff; padding: 11px; border-radius: 8px; font-weight: 700; cursor: pointer; }
    .err { color: #b91c1c; margin-bottom: 10px; font-size: 14px; }
    .hint { margin-top: 10px; font-size: 12px; color: #64748b; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Painel Admin</h1>
    <p>Faça login para aceder ao painel.</p>
    <?php if (!empty($error)): ?>
      <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post">
      <label for="password">Senha</label>
      <input id="password" name="password" type="password" required>
      <button type="submit">Entrar</button>
    </form>
    <div class="hint">Defina <code>ADMIN_PASSWORD</code> no ficheiro <code>.env</code>.</div>
  </div>
</body>
</html>
