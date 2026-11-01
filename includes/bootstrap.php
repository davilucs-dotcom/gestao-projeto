<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/gestao-projetos/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function h(mixed $value): string
{
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!is_string($submitted) || !is_string($stored) || !hash_equals($stored, $submitted)) {
        http_response_code(403);
        exit('Solicitacao invalida. Atualize a pagina e tente novamente.');
    }
}

function require_auth(): array
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
    return $_SESSION['user'];
}

function require_docente(array $user): void
{
    if (($user['perfil'] ?? null) !== 'Docente') {
        http_response_code(403);
        exit('Acesso permitido somente a docentes.');
    }
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function set_flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function private_upload_dir(): string
{
    $configured = getenv('SGP_UPLOAD_DIR');
    if ($configured !== false && $configured !== '') {
        return rtrim($configured, DIRECTORY_SEPARATOR);
    }
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR . 'sgp';
}
