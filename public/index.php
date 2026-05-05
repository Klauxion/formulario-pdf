<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$envPath = __DIR__ . '/../.env';

if (!file_exists($envPath)) {
    $envPath = __DIR__ . '/.env';
}

loadEnv($envPath);

session_start();

/**
 * Simple .env loader
 */
function loadEnv(string $path): void {
    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        // remove quotes if they exist
        $value = trim($value, "\"'");

        $_ENV[$key] = $value;
    }
}

loadEnv(__DIR__ . '/.env'); // now inside public

// --- Security headers ---
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// --- CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- BASE PATH (automatic, portable) ---
$scriptName = $_SERVER['SCRIPT_NAME']; 
// e.g. /MyWebsite/public/index.php

$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

if ($basePath !== '') {
    $basePath .= '/';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!$postedToken || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        http_response_code(419);
        echo 'CSRF inválido';
        exit;
    }

    require 'app/envelope.php';

    $result = submitForm($_POST);

    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}


$csrfToken = $_SESSION['csrf_token'];
$showTestModeToggle = false;
$isTestMode = false;
$flash = null;

// GET → render view
require 'app/views/morf.php';
