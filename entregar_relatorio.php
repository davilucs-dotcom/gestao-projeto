<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/relatorio_service.php';

$user = require_auth();
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = mensagem_erro_upload(UPLOAD_ERR_INI_SIZE);
    } else {
        verify_csrf();

        $reportId = filter_input(INPUT_POST, 'relatorio_id', FILTER_VALIDATE_INT);
        $upload = $_FILES['arquivo'] ?? null;

        try {
            if (!$reportId || !is_array($upload)) {
                throw new RelatorioException('Relatorio ou arquivo invalido.');
            }
            entregar_relatorio($pdo, (int) $user['id'], $reportId, $upload, private_upload_dir());
            set_flash('Relatorio enviado. O progresso agora representa a proporcao de relatorios entregues.');
            redirect('entregar_relatorio.php');
        } catch (RelatorioException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('SGP report upload failed: ' . $exception->getMessage());
            $errors[] = 'Nao foi possivel entregar o relatorio. Tente novamente.';
        }
    }
}

$reportsQuery = $pdo->prepare(
    'SELECT v.id, v.projeto_id, v.tipo, v.data_limite, v.data_entrega, v.arquivo_path, v.situacao, p.titulo
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
        <td><?php if ($report['data_entrega'] !== null): ?>
            <?php if (relatorio_arquivo_disponivel($report['arquivo_path'], private_upload_dir())): ?><a href="baixar_relatorio.php?id=<?= (int) $report['id'] ?>">Baixar PDF</a>
            <?php else: ?><span class="muted">Entregue; arquivo indisponível</span><?php endif; ?>
        <?php else: ?><form method="post" enctype="multipart/form-data" class="upload-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="relatorio_id" value="<?= (int) $report['id'] ?>"><input type="file" name="arquivo" accept="application/pdf,.pdf" required><button class="button primary" type="submit">Enviar PDF</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$reports): ?><tr><td colspan="5" class="empty">Nenhum relatório disponível para seus projetos.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
