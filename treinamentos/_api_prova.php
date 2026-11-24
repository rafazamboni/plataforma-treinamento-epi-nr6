<?php
function tr_api_prova(PDO $pdo, string $acao, array $dados): void
{
    if (strpos($acao, 'prova_') !== 0) { return; }

    if ($acao === 'prova_listar') {
        if (!trm_pode_ver()) { nv_json(['ok' => false, 'msg' => 'Sem permissão.'], 403); }
        $apId = tr_int($dados, 'apresentacao_id');
        nv_json(['ok' => true, 'questoes' => trp_questoes($pdo, $apId)]);
    }

    trm_exigir_gerenciar_ajax();

    switch ($acao) {

    case 'prova_questao_salvar': {
        $apId = tr_int($dados, 'apresentacao_id');
        $ap   = tr_apresentacao($pdo, $apId);
        if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

        $enunciado = tr_txt($dados, 'enunciado');
        if ($enunciado === '') { nv_json(['ok' => false, 'msg' => 'Escreva o enunciado da questão.'], 422); }

        $tipo = tr_enum(tr_txt($dados, 'tipo', 'unica'), array_keys(trp_tipos()), 'unica');
        $alts = is_array($dados['alternativas'] ?? null) ? $dados['alternativas'] : [];

        $limpas = [];
        foreach ($alts as $a) {
            $texto = trim((string)($a['texto'] ?? ''));
            if ($texto === '') { continue; }
            $limpas[] = [
                'texto'   => mb_substr($texto, 0, 500),
                'correta' => !empty($a['correta']) ? 1 : 0,
            ];
        }

        if ($tipo === 'vf' && count($limpas) < 2) {
            $limpas = [
                ['texto' => 'Verdadeiro', 'correta' => !empty($dados['vf_correta']) ? 1 : 0],
                ['texto' => 'Falso',      'correta' => !empty($dados['vf_correta']) ? 0 : 1],
            ];
        }

        if (count($limpas) < 2) {
            nv_json(['ok' => false, 'msg' => 'Informe pelo menos duas alternativas.'], 422);
        }

        $corretas = 0;
        foreach ($limpas as $a) { $corretas += (int)$a['correta']; }
        if ($corretas === 0) {
            nv_json(['ok' => false, 'msg' => 'Marque qual alternativa é a correta.'], 422);
        }
        if ($tipo !== 'multipla' && $corretas > 1) {
            nv_json(['ok' => false, 'msg' => 'Este tipo de questão aceita apenas uma alternativa correta.'], 422);
        }
        if ($tipo === 'multipla' && $corretas < 2) {
            nv_json(['ok' => false, 'msg' => 'Questões de várias respostas precisam de pelo menos duas corretas.'], 422);
        }

        $qId  = tr_int($dados, 'id');
        $peso = max(1, min(10, tr_int($dados, 'peso', 1)));
        $expl = mb_substr(tr_txt($dados, 'explicacao'), 0, 600);
        $ativa = tr_int($dados, 'ativa', 1) ? 1 : 0;

        $pdo->beginTransaction();
        try {
            if ($qId > 0) {
                $st = $pdo->prepare(
                    'UPDATE treinamentos_questoes
                        SET tipo = ?, enunciado = ?, explicacao = ?, peso = ?, ativa = ?
                      WHERE id = ? AND apresentacao_id = ? LIMIT 1'
                );
                $st->execute([$tipo, $enunciado, $expl, $peso, $ativa, $qId, $apId]);

                $st = $pdo->prepare('DELETE FROM treinamentos_alternativas WHERE questao_id = ?');
                $st->execute([$qId]);
            } else {
                $ordem = tr_proxima_ordem($pdo, 'treinamentos_questoes', 'apresentacao_id', $apId);
                $st = $pdo->prepare(
                    'INSERT INTO treinamentos_questoes
                        (apresentacao_id, ordem, tipo, enunciado, explicacao, peso, ativa)
                     VALUES (?,?,?,?,?,?,?)'
                );
                $st->execute([$apId, $ordem, $tipo, $enunciado, $expl, $peso, $ativa]);
                $qId = (int)$pdo->lastInsertId();
            }

            $st = $pdo->prepare(
                'INSERT INTO treinamentos_alternativas (questao_id, ordem, texto, correta) VALUES (?,?,?,?)'
            );
            foreach ($limpas as $i => $a) {
                $st->execute([$qId, $i + 1, $a['texto'], $a['correta']]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            nv_json(['ok' => false, 'msg' => 'Não foi possível salvar a questão.'], 500);
        }

        nv_json(['ok' => true, 'id' => $qId, 'msg' => 'Questão salva.']);
    }

    case 'prova_questao_excluir': {
        $qId = tr_int($dados, 'id');
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('DELETE FROM treinamentos_alternativas WHERE questao_id = ?');
            $st->execute([$qId]);
            $st = $pdo->prepare('DELETE FROM treinamentos_questoes WHERE id = ? LIMIT 1');
            $st->execute([$qId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            nv_json(['ok' => false, 'msg' => 'Não foi possível excluir a questão.'], 500);
        }
        nv_json(['ok' => true, 'msg' => 'Questão excluída.']);
    }

    case 'prova_questao_ativa': {
        $qId = tr_int($dados, 'id');
        $st = $pdo->prepare('UPDATE treinamentos_questoes SET ativa = ? WHERE id = ? LIMIT 1');
        $st->execute([tr_int($dados, 'ativa', 1) ? 1 : 0, $qId]);
        nv_json(['ok' => true, 'msg' => tr_int($dados, 'ativa', 1) ? 'Questão ativada.' : 'Questão desativada.']);
    }

    case 'prova_questao_reordenar': {
        $ordem = is_array($dados['ordem'] ?? null) ? $dados['ordem'] : [];
        if (!$ordem) { nv_json(['ok' => false, 'msg' => 'Nada a reordenar.'], 422); }

        $st = $pdo->prepare('UPDATE treinamentos_questoes SET ordem = ? WHERE id = ? LIMIT 1');
        foreach (array_values($ordem) as $i => $qId) {
            $st->execute([$i + 1, (int)$qId]);
        }
        nv_json(['ok' => true, 'msg' => 'Ordem atualizada.']);
    }

    case 'prova_config': {
        $apId = tr_int($dados, 'id');
        $ap   = tr_apresentacao($pdo, $apId);
        if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

        $ativa = tr_int($dados, 'prova_ativa', (int)$ap['prova_ativa']) ? 1 : 0;
        if ($ativa === 1 && trp_total_valido($pdo, $apId) === 0) {
            nv_json(['ok' => false, 'msg' => 'Cadastre pelo menos uma questão válida antes de ativar a prova.'], 422);
        }

        $st = $pdo->prepare(
            'UPDATE treinamentos_apresentacoes SET
                prova_ativa = ?, prova_nota_minima = ?, prova_tentativas = ?,
                prova_qtd_sorteada = ?, prova_embaralhar = ?, prova_mostrar_gabarito = ?,
                prova_instrucoes = ?
              WHERE id = ? LIMIT 1'
        );
        $st->execute([
            $ativa,
            max(0, min(100, tr_int($dados, 'prova_nota_minima', (int)$ap['prova_nota_minima']))),
            max(0, min(20,  tr_int($dados, 'prova_tentativas', (int)$ap['prova_tentativas']))),
            max(0, min(200, tr_int($dados, 'prova_qtd_sorteada', (int)$ap['prova_qtd_sorteada']))),
            tr_int($dados, 'prova_embaralhar', (int)$ap['prova_embaralhar']) ? 1 : 0,
            tr_int($dados, 'prova_mostrar_gabarito', (int)$ap['prova_mostrar_gabarito']) ? 1 : 0,
            mb_substr(tr_txt($dados, 'prova_instrucoes', ''), 0, 600),
            $apId,
        ]);

        nv_json(['ok' => true, 'msg' => 'Configuração da prova salva.']);
    }

    case 'prova_liberar': {
        $inscId = tr_int($dados, 'id');
        $insc   = trm_inscricao($pdo, $inscId);
        if (!$insc) { nv_json(['ok' => false, 'msg' => 'Matrícula não encontrada.'], 404); }

        $st = $pdo->prepare(
            'UPDATE treinamentos_inscricoes SET prova_tentativas_feitas = 0 WHERE id = ? LIMIT 1'
        );
        $st->execute([$inscId]);
        trm_log($pdo, $inscId, 'prova_liberada');

        nv_json(['ok' => true, 'msg' => 'Tentativas liberadas. O colaborador pode refazer a prova.']);
    }

    }

    nv_json(['ok' => false, 'msg' => 'Ação de prova desconhecida.'], 400);
}
