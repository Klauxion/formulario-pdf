<?php
declare(strict_types=1);

/**
 * Load key/value pairs from .env into process env if not already set.
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

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
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
$publicEnv = __DIR__ . DIRECTORY_SEPARATOR . '.env';
loadDotEnvFile($rootEnv);
loadDotEnvFile($publicEnv);

return [
    // Gmail SMTP defaults. Override in .env for other providers.
    'host' => envValue('SMTP_HOST', 'smtp.gmail.com'),
    'port' => (int)envValue('SMTP_PORT', '587'),
    'secure' => envValue('SMTP_SECURE', 'tls'),

    // Sender account (must be a real mailbox).
    'username' => envValue('SMTP_USERNAME', ''),
    'password' => envValue('SMTP_PASSWORD', ''),

    // Visible sender. Recipient comes from the form "Email" field.
    'from_email' => envValue('SMTP_FROM_EMAIL', ''),
    'from_name' => envValue('SMTP_FROM_NAME', 'Val do Rio Form'),
];
