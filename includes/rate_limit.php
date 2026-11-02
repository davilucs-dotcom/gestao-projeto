<?php
declare(strict_types=1);

const LOGIN_MAX_POR_EMAIL_IP = 5;
const LOGIN_MAX_POR_IP = 30;
const LOGIN_JANELA_MINUTOS = 15;

function login_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function login_email_hash(string $email): string
{
    return hash('sha256', mb_strtolower(trim($email)));
}

function login_bloqueado(PDO $pdo, string $email, string $ip): bool
{
    try {
        $minutes = LOGIN_JANELA_MINUTOS;
        $byEmailAndIp = $pdo->prepare(
            "SELECT COUNT(*) FROM login_tentativas
             WHERE email_hash = ? AND ip = ?
               AND tentou_em > DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)"
        );
        $byEmailAndIp->execute([login_email_hash($email), $ip]);
        if ((int) $byEmailAndIp->fetchColumn() >= LOGIN_MAX_POR_EMAIL_IP) {
            return true;
        }

        $byIp = $pdo->prepare(
            "SELECT COUNT(*) FROM login_tentativas
             WHERE ip = ? AND tentou_em > DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)"
        );
        $byIp->execute([$ip]);
        return (int) $byIp->fetchColumn() >= LOGIN_MAX_POR_IP;
    } catch (PDOException $exception) {
        error_log('SGP rate limit indisponivel: ' . $exception->getMessage());
        return false;
    }
}

function login_registrar_falha(PDO $pdo, string $email, string $ip): void
{
    try {
        $pdo->prepare('INSERT INTO login_tentativas (email_hash, ip) VALUES (?, ?)')
            ->execute([login_email_hash($email), $ip]);

        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM login_tentativas WHERE tentou_em < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        }
    } catch (PDOException $exception) {
        error_log('SGP nao registrou tentativa: ' . $exception->getMessage());
    }
}

function login_limpar(PDO $pdo, string $email, string $ip): void
{
    try {
        $pdo->prepare('DELETE FROM login_tentativas WHERE email_hash = ? AND ip = ?')
            ->execute([login_email_hash($email), $ip]);
    } catch (PDOException $exception) {
        error_log('SGP nao limpou tentativas: ' . $exception->getMessage());
    }
}

function login_hash_falso(): string
{
    static $hash = null;
    return $hash ??= password_hash('sgp-hash-falso', PASSWORD_DEFAULT);
}
