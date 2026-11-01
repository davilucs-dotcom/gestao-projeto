<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_auth();
$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$reportId) {
    http_response_code(404);
    exit('Arquivo nao encontrado.');
}
$statement = db()->prepare(
    'SELECT r.arquivo_path, r.id FROM relatorios r
     WHERE r.id = ? AND r.arquivo_path IS NOT NULL AND EXISTS (
         SELECT 1 FROM equipes e WHERE e.projeto_id = r.projeto_id AND e.usuario_id = ?
     )'
);
$statement->execute([$reportId, $user['id']]);
$report = $statement->fetch();
$filename = $report['arquivo_path'] ?? '';
if (!$report || !preg_match('/\A[a-f0-9]{64}\.pdf\z/', $filename)) {
    http_response_code(404);
    exit('Arquivo nao encontrado.');
}
$path = private_upload_dir() . DIRECTORY_SEPARATOR . $filename;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Arquivo nao encontrado.');
}
header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="relatorio-' . (int) $reportId . '.pdf"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
