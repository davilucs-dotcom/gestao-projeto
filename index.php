<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_auth();
$pdo = db();

$statsQuery = $pdo->prepare(
    'SELECT COUNT(DISTINCT p.id) AS total,
            SUM(p.status = \'Em andamento\') AS andamento,
            SUM(p.status = \'Concluido\') AS concluidos,
            AVG(p.progresso) AS progresso_medio
     FROM projetos p JOIN equipes e ON e.projeto_id = p.id WHERE e.usuario_id = ?'
);
$statsQuery->execute([$user['id']]);
$stats = $statsQuery->fetch() ?: [];
$projectsQuery = $pdo->prepare(
    'SELECT p.id, p.titulo, p.modalidade, p.data_inicio, p.data_fim, p.progresso, p.status, p.coordenador_id,
            u.nome AS coordenador,
            (SELECT COUNT(*) FROM equipes e2 WHERE e2.projeto_id = p.id) AS total_membros
     FROM projetos p JOIN equipes e ON e.projeto_id = p.id
     JOIN usuarios u ON u.id = p.coordenador_id
     WHERE e.usuario_id = ? ORDER BY p.criado_em DESC'
);
$projectsQuery->execute([$user['id']]);
$projects = $projectsQuery->fetchAll();
$reportsQuery = $pdo->prepare(
    'SELECT v.projeto_id, v.tipo, v.data_limite, v.situacao, p.titulo
     FROM vw_relatorios_situacao v JOIN projetos p ON p.id = v.projeto_id
     WHERE EXISTS (SELECT 1 FROM equipes e WHERE e.projeto_id = p.id AND e.usuario_id = ?)
     ORDER BY v.data_limite LIMIT 8'
);
$reportsQuery->execute([$user['id']]);
$upcomingReports = $reportsQuery->fetchAll();
$chartQuery = $pdo->prepare(
    'SELECT p.modalidade, COUNT(DISTINCT p.id) AS total FROM projetos p
     JOIN equipes e ON e.projeto_id = p.id WHERE e.usuario_id = ? GROUP BY p.modalidade'
);
$chartQuery->execute([$user['id']]);
$chartRows = $chartQuery->fetchAll();
$chartLabels = array_column($chartRows, 'modalidade');
$chartValues = array_map('intval', array_column($chartRows, 'total'));

$reportChartQuery = $pdo->prepare(
    'SELECT v.situacao, COUNT(*) AS total
     FROM vw_relatorios_situacao v
     WHERE EXISTS (
         SELECT 1 FROM equipes e WHERE e.projeto_id = v.projeto_id AND e.usuario_id = ?
     )
     GROUP BY v.situacao'
);
$reportChartQuery->execute([$user['id']]);
$reportChartRows = $reportChartQuery->fetchAll();
$reportChartLabels = array_column($reportChartRows, 'situacao');
$reportChartValues = array_map('intval', array_column($reportChartRows, 'total'));
$statusColors = [
    'Pendente' => '#d4a017',
    'Entregue' => '#3d8b63',
    'Atrasado' => '#a63b34',
];
$reportChartColors = array_map(
    static fn(string $label): string => $statusColors[$label] ?? '#9cafa2',
    $reportChartLabels
);

$pageTitle = 'Painel';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">Visão geral</p><h1>Olá, <?= h($user['nome']) ?></h1><p>Projetos e prazos associados à sua equipe.</p></div>
    <?php if ($user['perfil'] === 'Docente'): ?><a class="button primary" href="#novo-projeto">Novo projeto</a><?php endif; ?>
</section>
<section class="metrics" aria-label="Indicadores">
    <article class="metric"><span>Projetos</span><strong><?= (int) ($stats['total'] ?? 0) ?></strong></article>
    <article class="metric"><span>Em andamento</span><strong><?= (int) ($stats['andamento'] ?? 0) ?></strong></article>
    <article class="metric"><span>Concluídos</span><strong><?= (int) ($stats['concluidos'] ?? 0) ?></strong></article>
    <article class="metric"><span>Progresso médio</span><strong><?= (int) round((float) ($stats['progresso_medio'] ?? 0)) ?>%</strong></article>
</section>
<section class="charts-row" aria-label="Gráficos de acompanhamento">
    <article class="panel chart-panel">
        <h2>Projetos por modalidade</h2>
        <canvas id="modalityChart" aria-label="Projetos por modalidade"></canvas>
    </article>
    <article class="panel chart-panel">
        <h2>Situação dos relatórios</h2>
        <canvas id="reportStatusChart" aria-label="Situação dos relatórios parcial e final"></canvas>
    </article>
</section>
<div class="dashboard-grid">
    <section class="panel project-panel"><div class="section-heading"><h2>Seus projetos</h2><span class="count"><?= count($projects) ?></span></div>
        <div class="table-wrap"><table><thead><tr><th>Projeto</th><th>Modalidade</th><th>Vigência</th><th>Progresso</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($projects as $project): ?><tr>
            <td><strong><?= h($project['titulo']) ?></strong><small class="table-subtitle">Coord.: <?= h($project['coordenador']) ?> · <?= (int) $project['total_membros'] ?> membros</small></td>
            <td><?= h($project['modalidade']) ?></td>
            <td><?= h($project['data_inicio']) ?><small class="table-subtitle">até <?= h($project['data_fim']) ?></small></td>
            <td><div class="progress-line"><span style="width: <?= max(0, min(100, (int) $project['progresso'])) ?>%"></span></div><small><?= (int) $project['progresso'] ?>%</small></td>
            <td><span class="tag"><?= h($project['status']) ?></span></td>
            <td><a href="entregar_relatorio.php">Relatórios</a><?php if ((int) $project['coordenador_id'] === (int) $user['id']): ?> <a href="equipe.php?projeto_id=<?= (int) $project['id'] ?>">Equipe</a><?php endif; ?></td>
        </tr><?php endforeach; ?>
        <?php if (!$projects): ?><tr><td colspan="6" class="empty">Você ainda não participa de projetos.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <aside class="side-column">
        <section class="panel"><h2>Prazos de relatórios</h2><ul class="deadline-list">
            <?php foreach ($upcomingReports as $report): ?><li><div><strong><?= h($report['titulo']) ?></strong><small><?= h($report['tipo']) ?> · <?= h($report['data_limite']) ?></small></div><span class="status status-<?= strtolower(h($report['situacao'])) ?>"><?= h($report['situacao']) ?></span></li><?php endforeach; ?>
            <?php if (!$upcomingReports): ?><li class="empty">Sem prazos cadastrados.</li><?php endif; ?>
        </ul><a class="text-link" href="entregar_relatorio.php">Ver relatórios</a></section>
    </aside>
</div>
<?php if ($user['perfil'] === 'Docente'): ?>
<section class="panel form-panel" id="novo-projeto"><h2>Cadastrar projeto</h2>
    <form method="post" action="cadastrar_projeto.php" class="project-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>Título<input name="titulo" maxlength="200" required></label>
        <label>Modalidade<select name="modalidade" required><option value="">Selecione</option><option>Ensino</option><option>Pesquisa</option><option>Extensao</option></select></label>
        <label>Data de início<input type="date" name="data_inicio" required></label>
        <label>Data de término<input type="date" name="data_fim" required></label>
        <label class="full-field">Resumo<textarea name="resumo" rows="3"></textarea></label>
        <button class="button primary" type="submit">Cadastrar projeto</button>
    </form>
</section>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const chartDefaults = { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } };
if (window.Chart) {
    const modalityCanvas = document.getElementById('modalityChart');
    if (modalityCanvas) {
        new Chart(modalityCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                datasets: [{ data: <?= json_encode($chartValues) ?>, backgroundColor: ['#176b48', '#68a679', '#9cafa2'], borderWidth: 0 }]
            },
            options: { ...chartDefaults, cutout: '68%' }
        });
    }
    const reportCanvas = document.getElementById('reportStatusChart');
    if (reportCanvas) {
        new Chart(reportCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($reportChartLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                datasets: [{
                    label: 'Relatórios',
                    data: <?= json_encode($reportChartValues) ?>,
                    backgroundColor: <?= json_encode($reportChartColors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                    borderWidth: 0
                }]
            },
            options: {
                ...chartDefaults,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: { legend: { display: false } }
            }
        });
    }
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>