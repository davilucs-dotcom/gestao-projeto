<?php
declare(strict_types=1);

const RELATORIO_MAX_BYTES = 10 * 1024 * 1024;

class RelatorioException extends InvalidArgumentException
{
}

function mensagem_erro_upload(int $codigo): string
{
    return match ($codigo) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho permitido (maximo de 10 MB).',
        UPLOAD_ERR_PARTIAL => 'O envio foi interrompido. Tente novamente.',
        UPLOAD_ERR_NO_FILE => 'Selecione um arquivo PDF.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'O servidor nao conseguiu receber o arquivo. Avise o administrador.',
        default => 'Selecione um arquivo PDF valido.',
    };
}

function relatorio_arquivo_disponivel(?string $filename, string $directory): bool
{
    if ($filename === null || !preg_match('/\A[a-f0-9]{64}\.pdf\z/', $filename)) {
        return false;
    }

    $path = $directory . DIRECTORY_SEPARATOR . $filename;
    return is_file($path) && is_readable($path);
}

function calcular_progresso(int $entregues, int $total): int
{
    if ($total <= 0) {
        return 0;
    }
    $entregues = max(0, min($entregues, $total));
    return (int) round(100 * $entregues / $total);
}

function validar_upload_pdf(array $upload): void
{
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RelatorioException(mensagem_erro_upload($error));
    }

    $temporaryPath = (string) ($upload['tmp_name'] ?? '');
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new RelatorioException('Selecione um arquivo PDF valido.');
    }

    $size = (int) ($upload['size'] ?? 0);
    if ($size < 1 || $size > RELATORIO_MAX_BYTES) {
        throw new RelatorioException('O PDF deve ter no maximo 10 MB.');
    }

    if ((new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath) !== 'application/pdf') {
        throw new RelatorioException('O conteudo do arquivo nao foi reconhecido como PDF.');
    }

    $handle = fopen($temporaryPath, 'rb');
    $signature = $handle ? fread($handle, 5) : false;
    if (is_resource($handle)) {
        fclose($handle);
    }
    if ($signature !== '%PDF-') {
        throw new RelatorioException('Arquivo PDF invalido.');
    }
}

function entregar_relatorio(PDO $pdo, int $usuarioId, int $relatorioId, array $upload, string $diretorio): void
{
    $statement = $pdo->prepare(
        'SELECT r.projeto_id FROM relatorios r
         WHERE r.id = ? AND EXISTS (
             SELECT 1 FROM equipes e WHERE e.projeto_id = r.projeto_id AND e.usuario_id = ?
         )'
    );
    $statement->execute([$relatorioId, $usuarioId]);
    $projectId = (int) $statement->fetchColumn();
    if ($projectId < 1) {
        throw new RelatorioException('Relatorio nao encontrado ou sem permissao.');
    }

    validar_upload_pdf($upload);

    if (!is_dir($diretorio) && !mkdir($diretorio, 0700, true) && !is_dir($diretorio)) {
        throw new RuntimeException('Nao foi possivel preparar o armazenamento privado.');
    }

    $filename = bin2hex(random_bytes(32)) . '.pdf';
    $path = $diretorio . DIRECTORY_SEPARATOR . $filename;
    $fileStored = false;

    try {
        $pdo->beginTransaction();

        $lockProject = $pdo->prepare('SELECT id FROM projetos WHERE id = ? FOR UPDATE');
        $lockProject->execute([$projectId]);
        if (!$lockProject->fetch()) {
            throw new RelatorioException('Projeto nao encontrado.');
        }

        // Lock the membership row so removing a user cannot race with an upload.
        $lockMembership = $pdo->prepare(
            'SELECT id FROM equipes WHERE projeto_id = ? AND usuario_id = ? FOR UPDATE'
        );
        $lockMembership->execute([$projectId, $usuarioId]);
        if (!$lockMembership->fetch()) {
            throw new RelatorioException('Relatorio nao encontrado ou sem permissao.');
        }

        $lockReport = $pdo->prepare(
            'SELECT id, data_entrega FROM relatorios
             WHERE id = ? AND projeto_id = ? FOR UPDATE'
        );
        $lockReport->execute([$relatorioId, $projectId]);
        $report = $lockReport->fetch();
        if (!$report) {
            throw new RelatorioException('Relatorio nao encontrado ou sem permissao.');
        }
        if ($report['data_entrega'] !== null) {
            throw new RelatorioException('Este relatorio ja foi entregue.');
        }

        if (!move_uploaded_file((string) $upload['tmp_name'], $path)) {
            throw new RuntimeException('Nao foi possivel armazenar o arquivo.');
        }
        $fileStored = true;

        $updateReport = $pdo->prepare(
            'UPDATE relatorios SET data_entrega = CURDATE(), arquivo_path = ?
             WHERE id = ? AND projeto_id = ? AND data_entrega IS NULL'
        );
        $updateReport->execute([$filename, $relatorioId, $projectId]);
        if ($updateReport->rowCount() !== 1) {
            throw new RelatorioException('Relatorio ja entregue ou acesso negado.');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($fileStored && is_file($path)) {
            unlink($path);
        }
        throw $exception;
    }
}
