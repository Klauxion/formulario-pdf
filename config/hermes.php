<?php
declare(strict_types=1);

/**
 * Minimal .env loader (no external deps).
 * Loads key/value pairs into process env if not already set.
 */
function loadDotEnvFile(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Do not overwrite real environment variables.
        $existing = getenv($key);
        if ($existing !== false && $existing !== null && trim((string)$existing) !== '') {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function envValue(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    return trim((string)$value);
}

$rootEnv = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
loadDotEnvFile($rootEnv);

return [
    'host' => envValue('SMTP_HOST', 'smtp.gmail.com'),
    'port' => (int) envValue('SMTP_PORT', '587'),
    'secure' => envValue('SMTP_SECURE', 'tls'),
    'username' => envValue('SMTP_USERNAME', ''),
    'password' => envValue('SMTP_PASSWORD', ''),
    'from_email' => envValue('SMTP_FROM_EMAIL', ''),
    'from_name' => envValue('SMTP_FROM_NAME', 'Val do Rio Form'),
];

