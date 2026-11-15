<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/relatorio_service.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/bootstrap.php';

function assert_same_value(mixed $expected, mixed $actual, string $test): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $test . ': esperado ' . var_export($expected, true) . ', recebido ' . var_export($actual, true)
        );
    }
}

assert_same_value(50, calcular_progresso(1, 2), 'progresso parcial');
assert_same_value(100, calcular_progresso(2, 2), 'progresso completo');
assert_same_value(0, calcular_progresso(0, 0), 'projeto sem relatorios');
assert_same_value(0, calcular_progresso(-1, 2), 'progresso negativo');
assert_same_value(100, calcular_progresso(3, 2), 'progresso acima do total');
assert_same_value(
    'Robótica Educacional nas Escolas Públicas',
    corrigir_texto_legado('Rob├│tica Educacional nas Escolas P├║blicas'),
    'correcao de texto legado em CP437'
);
assert_same_value(
    'Robótica Educacional nas Escolas Públicas',
    corrigir_texto_legado('Robótica Educacional nas Escolas Públicas'),
    'texto UTF-8 permanece inalterado'
);
$uniqueProjects = consolidar_projetos_por_titulo([
    ['id' => 1, 'titulo' => 'Rob├│tica Educacional nas Escolas P├║blicas'],
    ['id' => 2, 'titulo' => 'Robótica Educacional nas Escolas Públicas'],
]);
assert_same_value(1, count($uniqueProjects), 'titulos equivalentes aparecem uma vez');
assert_same_value(2, $uniqueProjects[0]['id'], 'titulo UTF-8 preserva o registro integro');

assert_same_value(
    'Selecione um arquivo PDF.',
    mensagem_erro_upload(UPLOAD_ERR_NO_FILE),
    'mensagem para arquivo ausente'
);
assert_same_value(
    'O arquivo excede o tamanho permitido (maximo de 10 MB).',
    mensagem_erro_upload(UPLOAD_ERR_INI_SIZE),
    'mensagem para arquivo muito grande'
);
assert_same_value(
    false,
    relatorio_arquivo_disponivel('demo_parcial_p1.pdf', sys_get_temp_dir()),
    'arquivo demo sem path seguro nao e baixavel'
);
$testDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sgp-tests-' . bin2hex(random_bytes(6));
if (!mkdir($testDirectory, 0700, true)) {
    throw new RuntimeException('Nao foi possivel criar diretorio temporario para teste.');
}
$testPdfName = bin2hex(random_bytes(32)) . '.pdf';
file_put_contents($testDirectory . DIRECTORY_SEPARATOR . $testPdfName, '%PDF-test');
assert_same_value(
    true,
    relatorio_arquivo_disponivel($testPdfName, $testDirectory),
    'arquivo de relatorio com nome seguro e existente'
);
unlink($testDirectory . DIRECTORY_SEPARATOR . $testPdfName);
rmdir($testDirectory);
assert_same_value(
    login_email_hash(' Pessoa@Exemplo.com '),
    login_email_hash('pessoa@exemplo.com'),
    'hash de e-mail sem diferenca de caixa ou espacos'
);

assert_same_value(
    true,
    session_version_matches(['session_version' => 3], 3),
    'sessao na versao atual'
);
assert_same_value(
    false,
    session_version_matches(['session_version' => 2], 3),
    'sessao revogada por troca de senha'
);
assert_same_value(
    false,
    session_version_matches(['session_version' => 3], null),
    'usuario removido'
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [
    'user' => ['id' => 1],
    'last_activity' => time() - 10,
];
enforce_session_timeout(1);
assert_same_value(false, isset($_SESSION['user']), 'timeout remove autenticacao');
assert_same_value('error', $_SESSION['flash']['type'] ?? null, 'timeout informa expiracao');

if (getenv('SGP_RUN_DB_TESTS') === '1') {
    require_once __DIR__ . '/../config/db.php';
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $progressRows = $pdo->query(
            'SELECT vp.progresso,
                    COALESCE(ROUND(
                        100 * (
                            SELECT SUM(r.data_entrega IS NOT NULL)
                            FROM relatorios r
                            WHERE r.projeto_id = vp.projeto_id
                        ) / NULLIF((
                            SELECT COUNT(*)
                            FROM relatorios r
                            WHERE r.projeto_id = vp.projeto_id
                        ), 0)
                    ), 0) AS esperado
             FROM vw_projetos_progresso vp'
        )->fetchAll();
        foreach ($progressRows as $row) {
            assert_same_value((int) $row['esperado'], (int) $row['progresso'], 'view de progresso calculado');
        }

        $testEmail = 'sgp-test-' . bin2hex(random_bytes(12)) . '@example.invalid';
        $testIp = '127.0.0.2';
        $insertAttempt = $pdo->prepare(
            'INSERT INTO login_tentativas (email_hash, ip) VALUES (?, ?)'
        );
        for ($attempt = 0; $attempt < LOGIN_MAX_POR_EMAIL_IP; $attempt++) {
            $insertAttempt->execute([login_email_hash($testEmail), $testIp]);
        }
        assert_same_value(
            true,
            login_bloqueado($pdo, $testEmail, $testIp),
            'bloqueio após limite de tentativas'
        );
        login_limpar($pdo, $testEmail, $testIp);
        assert_same_value(
            false,
            login_bloqueado($pdo, $testEmail, $testIp),
            'limpeza das tentativas de login'
        );
    } finally {
        $pdo->rollBack();
    }

    echo "Testes de banco passaram.\n";
}

echo "Testes automatizados passaram.\n";
