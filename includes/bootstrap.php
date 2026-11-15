<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/security.php';

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$defaultCookiePath = $scriptDirectory === '/' || $scriptDirectory === '.'
    ? '/'
    : rtrim($scriptDirectory, '/') . '/';
$cookiePath = getenv('SGP_COOKIE_PATH') ?: $defaultCookiePath;
if ($cookiePath[0] !== '/') {
    $cookiePath = $defaultCookiePath;
}

if (getenv('SGP_ENV') !== 'dev') {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
date_default_timezone_set('America/Belem');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookiePath,
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
enforce_session_timeout();
send_security_headers(false);

function h(mixed $value): string
{
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function corrigir_texto_legado(string $value): string
{
    if (!str_contains($value, '├')) {
        return $value;
    }

    if (!function_exists('iconv')) {
        return $value;
    }

    $converted = iconv('UTF-8', 'CP437', $value);
    return $converted !== false && mb_check_encoding($converted, 'UTF-8') ? $converted : $value;
}

function consolidar_projetos_por_titulo(array $projects): array
{
    $projectsByTitle = [];
    foreach ($projects as $project) {
        $originalTitle = (string) $project['titulo'];
        $project['titulo'] = corrigir_texto_legado($originalTitle);
        $titleKey = mb_strtolower(trim($project['titulo']), 'UTF-8');
        $isCorrupted = $originalTitle !== $project['titulo'];

        if (!isset($projectsByTitle[$titleKey]) || ($projectsByTitle[$titleKey]['corrupted'] && !$isCorrupted)) {
            $projectsByTitle[$titleKey] = [
                'project' => $project,
                'corrupted' => $isCorrupted,
            ];
        }
    }

    return array_values(array_map(
        static fn(array $entry): array => $entry['project'],
        $projectsByTitle
    ));
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

    $user = $_SESSION['user'];
    if (!authenticated_session_is_current($user)) {
        expire_authenticated_session();
    }

    return $user;
}

function authenticated_session_is_current(array $user): bool
{
    static $validatedSessions = [];

    $userId = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT);
    $sessionVersion = filter_var($user['session_version'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId || $sessionVersion === false) {
        return false;
    }

    $cacheKey = $userId . ':' . $sessionVersion;
    if (array_key_exists($cacheKey, $validatedSessions)) {
        return $validatedSessions[$cacheKey];
    }

    $statement = db()->prepare('SELECT session_version FROM usuarios WHERE id = ?');
    $statement->execute([$userId]);
    $version = $statement->fetchColumn();
    $databaseVersion = $version === false ? null : (int) $version;

    $validatedSessions[$cacheKey] = session_version_matches($user, $databaseVersion);
    return $validatedSessions[$cacheKey];
}

function clear_authenticated_session(string $message): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => 'error',
    ];
}

function expire_authenticated_session(): void
{
    clear_authenticated_session('Sua sessao foi encerrada porque as credenciais da conta mudaram. Entre novamente.');
    header('Location: login.php');
    exit;
}

function require_docente(array $user): void
{
    if (($user['perfil'] ?? null) !== 'Docente') {
        http_response_code(403);
        exit('Acesso permitido somente a docentes.');
    }
}

function redirect(string $location): void
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
        $normalized = rtrim($configured, "/\\");
        return $normalized === '' ? DIRECTORY_SEPARATOR : $normalized;
    }
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR . 'sgp';
}
