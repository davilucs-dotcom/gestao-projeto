<?php
declare(strict_types=1);

function enforce_session_timeout(int $seconds = 1800): void
{
    if (!isset($_SESSION['user'])) {
        return;
    }

    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > $seconds) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'] = [
            'message' => 'Sessao expirada por inatividade. Entre novamente.',
            'type' => 'error',
        ];
        return;
    }

    $_SESSION['last_activity'] = time();
}

function session_version_matches(array $sessionUser, ?int $databaseVersion): bool
{
    return isset($sessionUser['session_version'])
        && $databaseVersion !== null
        && (int) $sessionUser['session_version'] === $databaseVersion;
}

function send_security_headers(bool $cspReportOnly = false): void
{
    if (headers_sent($file, $line)) {
        error_log("SGP security headers not sent; output started at {$file}:{$line}");
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    $csp = "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
        . "frame-ancestors 'none'; form-action 'self'; base-uri 'self'";
    $header = $cspReportOnly
        ? 'Content-Security-Policy-Report-Only'
        : 'Content-Security-Policy';
    header($header . ': ' . $csp);
}
