<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_auth();
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reportId = filter_input(INPUT_POST, 'relatorio_id', FILTER_VALIDATE_INT);
    $upload = $_FILES['arquivo'] ?? null;
    $storedPath = null;

    try {
        if (!$reportId || !is_array($upload)) throw new InvalidArgumentException('Relatorio ou arquivo invalido.');
        $lookup = $pdo->prepare(
            'SELECT r.id, r.projeto_id, r.data_entrega FROM relatorios r
             WHERE r.id = ? AND EXISTS (
                 SELECT 1 FROM equipes e WHERE e.projeto_id = r.projeto_id AND e.usuario_id = ?
             )'
        );
        $lookup->execute([$reportId, $user['id']]);
        $report = $lookup->fetch();
        if (!$report) throw new InvalidArgumentException('Relatorio nao encontrado ou sem permissao.');
        if ($report['data_entrega'] !== null) throw new InvalidArgumentException('Este relatorio ja foi entregue.');
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) {
            throw new InvalidArgumentException('Selecione um arquivo PDF valido.');
        }
        if (($upload['size'] ?? 0) < 1 || $upload['size'] > 10 * 1024 * 1024) {
            throw new InvalidArgumentException('O PDF deve ter no maximo 10 MB.');
        }
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        if ($fileInfo->file($upload['tmp_name']) !== 'application/pdf') {
            throw new InvalidArgumentException('O conteudo do arquivo nao foi reconhecido como PDF.');
        }
        $handle = fopen($upload['tmp_name'], 'rb');
        $signature = $handle ? fread($handle, 5) : false;
        if (is_resource($handle)) fclose($handle);
        if ($signature !== '%PDF-') throw new InvalidArgumentException('Arquivo PDF invalido.');

        $directory = private_upload_dir();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Nao foi possivel preparar o armazenamento privado.');
        }
        $filename = bin2hex(random_bytes(32)) . '.pdf';
        $storedPath = $directory . DIRECTORY_SEPARATOR . $filename;

        $pdo->beginTransaction();
        $lockProject = $pdo->prepare('SELECT id FROM projetos WHERE id = ? FOR UPDATE');
        $lockProject->execute([$report['projeto_id']]);
        if (!$lockProject->fetch()) throw new InvalidArgumentException('Projeto nao encontrado.');

        $lockReport = $pdo->prepare(
            'SELECT r.id FROM relatorios r
             WHERE r.id = ? AND r.projeto_id = ? AND r.data_entrega IS NULL
               AND EXISTS (
                   SELECT 1 FROM equipes e WHERE e.projeto_id = r.projeto_id AND e.usuario_id = ?
               ) FOR UPDATE'
        );
        $lockReport->execute([$reportId, $report['projeto_id'], $user['id']]);
        if (!$lockReport->fetch()) throw new InvalidArgumentException('Relatorio ja entregue ou acesso negado.');
        if (!move_uploaded_file($upload['tmp_name'], $storedPath)) {
            throw new RuntimeException('Nao foi possivel armazenar o arquivo.');
        }

        $updateReport = $pdo->prepare(
            'UPDATE relatorios SET data_entrega = CURDATE(), arquivo_path = ?
             WHERE id = ? AND projeto_id = ? AND data_entrega IS NULL
               AND EXISTS (
                   SELECT 1 FROM equipes e WHERE e.projeto_id = relatorios.projeto_id AND e.usuario_id = ?
               )'
        );
        $updateReport->execute([$filename, $reportId, $report['projeto_id'], $user['id']]);
        if ($updateReport->rowCount() !== 1) throw new InvalidArgumentException('Relatorio ja entregue ou acesso negado.');

        $progressQuery = $pdo->prepare(
            'SELECT COUNT(*) AS total, SUM(data_entrega IS NOT NULL) AS entregues FROM relatorios WHERE projeto_id = ?'
        );
        $progressQuery->execute([$report['projeto_id']]);
        $progress = $progressQuery->fetch();
        $percentage = (int) round(100 * (int) $progress['entregues'] / max(1, (int) $progress['total']));
        $updateProject = $pdo->prepare('UPDATE projetos SET progresso = ? WHERE id = ?');
        $updateProject->execute([$percentage, $report['projeto_id']]);
        $pdo->commit();
        set_flash('Relatorio enviado. O progresso agora representa a proporcao de relatorios entregues.');
        redirect('entregar_relatorio.php');
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($storedPath && is_file($storedPath)) unlink($storedPath);
        $errors[] = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($storedPath && is_file($storedPath)) unlink($storedPath);
        error_log('SGP report upload failed: ' . $exception->getMessage());
        $errors[] = 'Nao foi possivel entregar o relatorio. Tente novamente.';
    }
}

$reportsQuery = $pdo->prepare(
    'SELECT v.id, v.projeto_id, v.tipo, v.data_limite, v.data_entrega, v.situacao, p.titulo
     FROM vw_relatorios_situacao v
     JOIN projetos p ON p.id = v.projeto_id
     WHERE EXISTS (SELECT 1 FROM equipes e WHERE e.projeto_id = p.id AND e.usuario_id = ?)
     ORDER BY v.data_limite, p.titulo'
);
$reportsQuery->execute([$user['id']]);
$reports = $reportsQuery->fetchAll();
$pageTitle = 'Relatórios';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">Acompanhamento</p><h1>Relatórios</h1><p>Entregue e consulte relatórios dos projetos em que você participa.</p></div></section>
<?php foreach ($errors as $error): ?><div class="notice error"><?= h($error) ?></div><?php endforeach; ?>
<section class="panel">
    <div class="table-wrap"><table><thead><tr><th>Projeto</th><th>Tipo</th><th>Prazo</th><th>Estado</th><th>Arquivo / envio</th></tr></thead><tbody>
    <?php foreach ($reports as $report): ?><tr>
        <td><?= h($report['titulo']) ?></td><td><?= h($report['tipo']) ?></td><td><?= h($report['data_limite']) ?></td>
        <td><span class="status status-<?= strtolower(h($report['situacao'])) ?>"><?= h($report['situacao']) ?></span></td>
        <td><?php if ($report['data_entrega'] !== null): ?><a href="baixar_relatorio.php?id=<?= (int) $report['id'] ?>">Baixar PDF</a>
        <?php else: ?><form method="post" enctype="multipart/form-data" class="upload-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="relatorio_id" value="<?= (int) $report['id'] ?>"><input type="file" name="arquivo" accept="application/pdf,.pdf" required><button class="button primary" type="submit">Enviar PDF</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$reports): ?><tr><td colspan="5" class="empty">Nenhum relatório disponível para seus projetos.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
