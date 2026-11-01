<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('SGP_DB_HOST') ?: '127.0.0.1';
    $port = getenv('SGP_DB_PORT') ?: '3306';
    $database = getenv('SGP_DB_NAME') ?: 'sgp_ifpa';
    $username = getenv('SGP_DB_USER') ?: 'root';
    $password = getenv('SGP_DB_PASSWORD') ?: '';
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        error_log('SGP database connection failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Nao foi possivel conectar ao banco de dados. Confira a configuracao local.');
    }

    return $pdo;
}
