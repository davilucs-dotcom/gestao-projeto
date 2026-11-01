<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_auth();
$projectId = filter_input(INPUT_GET, 'projeto_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'projeto_id', FILTER_VALIDATE_INT);
if (!$projectId) {
    http_response_code(400);
    exit('Projeto invalido.');
}
$pdo = db();
$projectQuery = $pdo->prepare('SELECT id, titulo, data_inicio, data_fim FROM projetos WHERE id = ? AND coordenador_id = ?');
$projectQuery->execute([$projectId, $user['id']]);
$project = $projectQuery->fetch();
if (!$project) {
    http_response_code(403);
    exit('Somente o coordenador pode gerenciar esta equipe.');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['acao'] ?? '');
    try {
        if ($action === 'adicionar') {
            $memberId = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
            $role = (string) ($_POST['papel'] ?? '');
            $hours = filter_input(INPUT_POST, 'carga_horaria', FILTER_VALIDATE_INT);
            $entryDate = (string) ($_POST['data_entrada'] ?? '');
            $allowedRoles = ['Bolsista', 'Voluntario', 'Colaborador'];
            $validDate = DateTimeImmutable::createFromFormat('!Y-m-d', $entryDate);
            if (!$memberId || !in_array($role, $allowedRoles, true) || !$hours || $hours < 1 || $hours > 40 || !$validDate || $validDate->format('Y-m-d') !== $entryDate || $entryDate < $project['data_inicio'] || $entryDate > $project['data_fim']) {
                throw new InvalidArgumentException('Confira usuário, papel, carga horária e data de entrada.');
            }
            $statement = $pdo->prepare('INSERT INTO equipes (projeto_id, usuario_id, papel, carga_horaria_semanal, data_entrada) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$projectId, $memberId, $role, $hours, $entryDate]);
            set_flash('Membro adicionado a equipe.');
        } elseif ($action === 'remover') {
            $teamId = filter_input(INPUT_POST, 'equipe_id', FILTER_VALIDATE_INT);
            if (!$teamId) throw new InvalidArgumentException('Membro invalido.');
            $statement = $pdo->prepare("DELETE FROM equipes WHERE id = ? AND projeto_id = ? AND papel <> 'Coordenador'");
            $statement->execute([$teamId, $projectId]);
            if ($statement->rowCount() !== 1) throw new InvalidArgumentException('O membro nao existe ou nao pode ser removido.');
            set_flash('Membro removido da equipe.');
        } else {
            throw new InvalidArgumentException('Acao invalida.');
        }
        redirect('equipe.php?projeto_id=' . $projectId);
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('SGP team update failed: ' . $exception->getMessage());
        $errors[] = 'Nao foi possivel alterar a equipe. Verifique se o usuario ja participa do projeto.';
    }
}

$membersQuery = $pdo->prepare('SELECT e.id, e.usuario_id, e.papel, e.carga_horaria_semanal, e.data_entrada, u.nome, u.email, u.perfil FROM equipes e JOIN usuarios u ON u.id = e.usuario_id WHERE e.projeto_id = ? ORDER BY e.papel, u.nome');
$membersQuery->execute([$projectId]);
$members = $membersQuery->fetchAll();
$users = $pdo->prepare('SELECT id, nome, perfil FROM usuarios WHERE id <> ? ORDER BY nome');
$users->execute([$user['id']]);
$availableUsers = $users->fetchAll();
$pageTitle = 'Equipe do projeto';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">Projeto</p><h1><?= h($project['titulo']) ?></h1></div><a class="button quiet" href="index.php">Voltar ao painel</a></section>
<?php foreach ($errors as $error): ?><div class="notice error"><?= h($error) ?></div><?php endforeach; ?>
<div class="content-grid">
    <section class="panel">
        <h2>Adicionar membro</h2>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="projeto_id" value="<?= (int) $projectId ?>"><input type="hidden" name="acao" value="adicionar">
            <label>Usuário<select name="usuario_id" required><option value="">Selecione</option><?php foreach ($availableUsers as $candidate): ?><option value="<?= (int) $candidate['id'] ?>"><?= h($candidate['nome']) ?> · <?= h($candidate['perfil']) ?></option><?php endforeach; ?></select></label>
            <label>Papel<select name="papel" required><option value="Bolsista">Bolsista</option><option value="Voluntario">Voluntário</option><option value="Colaborador">Colaborador</option></select></label>
            <label>Horas semanais<input type="number" name="carga_horaria" min="1" max="40" value="4" required></label>
            <label>Data de entrada<input type="date" name="data_entrada" min="<?= h($project['data_inicio']) ?>" max="<?= h($project['data_fim']) ?>" required></label>
            <button class="button primary" type="submit">Adicionar à equipe</button>
        </form>
    </section>
    <section class="panel panel-wide">
        <h2>Equipe <span class="count"><?= count($members) ?></span></h2>
        <div class="table-wrap"><table><thead><tr><th>Nome</th><th>Perfil</th><th>Papel</th><th>Horas/semana</th><th>Entrada</th><th></th></tr></thead><tbody>
        <?php foreach ($members as $member): ?><tr><td><?= h($member['nome']) ?><small class="table-subtitle"><?= h($member['email']) ?></small></td><td><?= h($member['perfil']) ?></td><td><?= h($member['papel']) ?></td><td><?= (int) $member['carga_horaria_semanal'] ?></td><td><?= h($member['data_entrada']) ?></td><td><?php if ($member['papel'] !== 'Coordenador'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="projeto_id" value="<?= (int) $projectId ?>"><input type="hidden" name="acao" value="remover"><input type="hidden" name="equipe_id" value="<?= (int) $member['id'] ?>"><button class="button danger" type="submit">Remover</button></form><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
