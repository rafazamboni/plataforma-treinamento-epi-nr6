<?php
function tr_api_obrigatoriedade(PDO $pdo, string $acao, array $dados): void
{
    if (strpos($acao, 'cargo_') !== 0) { return; }

    $apId = tr_int($dados, 'apresentacao_id');
    $ap   = tr_apresentacao($pdo, $apId);
    if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

    trm_exigir_gerenciar_ajax();

    switch ($acao) {

    case 'cargo_adicionar': {
        $cargo = tr_txt($dados, 'cargo');
        if ($cargo === '') { nv_json(['ok' => false, 'msg' => 'Selecione um cargo.'], 422); }

        if (!in_array($cargo, tro_cargos_disponiveis($pdo), true)) {
            nv_json(['ok' => false, 'msg' => 'Cargo não encontrado no cadastro de colaboradores.'], 422);
        }

        $st = $pdo->prepare(
            'SELECT id FROM treinamentos_cargos_obrigatorios WHERE apresentacao_id = ? AND cargo = ? LIMIT 1'
        );
        $st->execute([$apId, $cargo]);
        if ($st->fetch()) {
            nv_json(['ok' => false, 'msg' => 'Este cargo já é obrigatório neste treinamento.'], 409);
        }

        $usuarioId = null;
        if (function_exists('current_user_id')) { $usuarioId = current_user_id() ?: null; }
        elseif (isset($_SESSION['usuario_id'])) { $usuarioId = (int)$_SESSION['usuario_id']; }

        $st = $pdo->prepare(
            'INSERT INTO treinamentos_cargos_obrigatorios (apresentacao_id, cargo, criado_por) VALUES (?, ?, ?)'
        );
        $st->execute([$apId, $cargo, $usuarioId]);

        nv_json(['ok' => true, 'msg' => 'Cargo "' . $cargo . '" marcado como obrigatório.']);
    }

    case 'cargo_remover': {
        $id = tr_int($dados, 'id');
        $st = $pdo->prepare(
            'DELETE FROM treinamentos_cargos_obrigatorios WHERE id = ? AND apresentacao_id = ? LIMIT 1'
        );
        $st->execute([$id, $apId]);
        if ($st->rowCount() === 0) {
            nv_json(['ok' => false, 'msg' => 'Obrigatoriedade não encontrada.'], 404);
        }
        nv_json(['ok' => true, 'msg' => 'Cargo deixou de ser obrigatório. Quem já foi matriculado continua matriculado.']);
    }

    case 'cargo_matricular_todos': {
        $pendentes = tro_colaboradores_pendentes($pdo, $apId);
        if (!$pendentes) {
            nv_json(['ok' => true, 'criados' => 0, 'msg' => 'Não há pendentes no momento.']);
        }

        $criados = 0;
        $erros   = [];
        foreach ($pendentes as $colab) {
            try {
                $r = trm_criar($pdo, $ap, $colab);
                if (!empty($r['novo'])) { $criados++; }
            } catch (Throwable $e) {
                $erros[] = $colab['nome'] . ': ' . nv_safe_err($e);
            }
        }

        $msg = $criados . ' matrícula(s) criada(s).';
        if ($erros) { $msg .= ' ' . count($erros) . ' não puderam ser criadas.'; }

        nv_json(['ok' => true, 'criados' => $criados, 'erros' => $erros, 'msg' => $msg]);
    }

    }

    nv_json(['ok' => false, 'msg' => 'Ação de obrigatoriedade desconhecida.'], 400);
}
