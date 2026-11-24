<?php
function tr_api_avatares(PDO $pdo, string $acao, array $dados): void
{
    if (strpos($acao, 'av_') !== 0) { return; }

    if (!tr_pode('treinamentos.instrutores.gerenciar')
        && !tr_pode('treinamentos.apresentacoes.publicar')) {
        nv_json(['ok' => false, 'msg' => 'Sem permissão para editar a biblioteca de instrutores.'], 403);
    }

    switch ($acao) {

    case 'av_salvar': {
        $id   = tr_int($dados, 'id');
        $nome = mb_substr(tr_txt($dados, 'nome'), 0, 80);
        if ($nome === '') { nv_json(['ok' => false, 'msg' => 'Dê um nome ao instrutor.'], 422); }

        $campos = [
            'nome'           => $nome,
            'descricao'      => mb_substr(tr_txt($dados, 'descricao'), 0, 240),
            'camera'         => tr_enum(tr_txt($dados, 'camera', 'upper'), array_keys(tr_cameras()), 'upper'),
            'humor'          => tr_enum(tr_txt($dados, 'humor', 'neutral'), array_keys(tr_humores()), 'neutral'),
            'voz_nome'       => tr_enum(tr_txt($dados, 'voz_nome', 'F1'), array_keys(tr_vozes()), 'F1'),
            'voz_velocidade' => max(0.7, min(1.4, (float)($dados['voz_velocidade'] ?? 1))),
            'voz_tom'        => max(0.85, min(1.2, (float)($dados['voz_tom'] ?? 1))),
            'voz_pausa_ms'   => max(0, min(900, tr_int($dados, 'voz_pausa_ms', 260))),
            'voz_ganho'      => max(0.6, min(1.6, (float)($dados['voz_ganho'] ?? 1))),
            'voz_qualidade'  => tr_enum(tr_txt($dados, 'voz_qualidade', 'alta'), array_keys(tr_voz_qualidades()), 'alta'),
            'voz_kbps'       => tr_enum((string)tr_int($dados, 'voz_kbps', 96), ['64', '96', '128', '160'], '96'),
            'ativo'          => tr_int($dados, 'ativo', 1) ? 1 : 0,

            'mao_dominante'   => tr_enum(tr_txt($dados, 'mao_dominante', 'direita'), array_keys(tr_maos_dominantes()), 'direita'),
            'orient_auto'     => tr_int($dados, 'orient_auto', 1) ? 1 : 0,
            'giro_corpo'      => tr_enum(tr_txt($dados, 'giro_corpo', 'leve'), array_keys(tr_giros()), 'leve'),
            'giro_quadril'    => tr_int($dados, 'giro_quadril', 0) ? 1 : 0,
            'forca_tronco'    => max(0, min(2, (float)($dados['forca_tronco'] ?? 1))),
            'forca_cabeca'    => max(0, min(2, (float)($dados['forca_cabeca'] ?? 1))),
            'forca_gesto'     => max(0, min(1.5, (float)($dados['forca_gesto'] ?? 1))),
            'olhar_cabeca'    => max(0, min(1, (float)($dados['olhar_cabeca'] ?? 0.35))),
            'rosto_publico'   => max(0, min(1, (float)($dados['rosto_publico'] ?? 0.5))),
            'altura_conteudo' => max(-1, min(1, (float)($dados['altura_conteudo'] ?? 0))),
            'suavidade'       => max(0.3, min(2.5, (float)($dados['suavidade'] ?? 1))),
            'olhar_padrao'    => tr_enum(tr_txt($dados, 'olhar_padrao', 'camera'), array_keys(tr_olhares()), 'camera'),
        ];

        if ($id > 0) {
            if (!tr_avatar($pdo, $id)) { nv_json(['ok' => false, 'msg' => 'Instrutor não encontrado.'], 404); }
            $sets = [];
            foreach (array_keys($campos) as $c) { $sets[] = "$c = ?"; }
            $st = $pdo->prepare('UPDATE treinamentos_avatares SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
            $st->execute(array_merge(array_values($campos), [$id]));
        } else {
            $st = $pdo->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM treinamentos_avatares');
            $campos['ordem'] = (int)$st->fetchColumn();
            $campos['criado_por'] = function_exists('current_user_id') ? (current_user_id() ?: null) : null;

            $cols = implode(', ', array_keys($campos));
            $marc = implode(', ', array_fill(0, count($campos), '?'));
            $st = $pdo->prepare("INSERT INTO treinamentos_avatares ($cols) VALUES ($marc)");
            $st->execute(array_values($campos));
            $id = (int)$pdo->lastInsertId();
        }

        nv_json(['ok' => true, 'id' => $id, 'msg' => 'Instrutor salvo.']);
    }

    case 'av_upload': {
        $id = (int)($_POST['id'] ?? 0);
        $av = tr_avatar($pdo, $id);
        if (!$av) { nv_json(['ok' => false, 'msg' => 'Instrutor não encontrado.'], 404); }

        if (empty($_FILES['arquivo']['tmp_name'])) {
            nv_json(['ok' => false, 'msg' => 'Selecione o arquivo .vrm.'], 422);
        }
        if ((int)$_FILES['arquivo']['size'] > 60 * 1024 * 1024) {
            nv_json(['ok' => false, 'msg' => 'O modelo deve ter no máximo 60 MB.'], 422);
        }

        $ext = strtolower(pathinfo((string)$_FILES['arquivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['vrm', 'glb'], true)) {
            nv_json(['ok' => false, 'msg' => 'Envie um arquivo .vrm (ou .glb).'], 422);
        }

        $dir = tr_garantir_dir_avatares();
        foreach ((array)glob($dir . '/av-' . $id . '.{vrm,glb}', GLOB_BRACE) as $antigo) { @unlink($antigo); }

        $nomeArq = 'av-' . $id . '.' . $ext;
        if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . '/' . $nomeArq)) {
            nv_json(['ok' => false, 'msg' => 'Falha ao gravar o modelo.'], 500);
        }

        $st = $pdo->prepare('UPDATE treinamentos_avatares SET arquivo = ? WHERE id = ? LIMIT 1');
        $st->execute([$nomeArq, $id]);

        nv_json(['ok' => true, 'arquivo' => $nomeArq, 'msg' => 'Modelo enviado.']);
    }

    case 'av_thumb': {
        $id = tr_int($dados, 'id');
        $av = tr_avatar($pdo, $id);
        if (!$av) { nv_json(['ok' => false, 'msg' => 'Instrutor não encontrado.'], 404); }

        $png = (string)($dados['png'] ?? '');
        if (strpos($png, 'data:image/png;base64,') !== 0) {
            nv_json(['ok' => false, 'msg' => 'Imagem inválida.'], 422);
        }
        $bin = base64_decode(substr($png, 22), true);
        if ($bin === false || strlen($bin) > 2 * 1024 * 1024) {
            nv_json(['ok' => false, 'msg' => 'Imagem inválida.'], 422);
        }

        $dir = tr_garantir_dir_avatares();
        $nomeArq = 'av-' . $id . '.png';
        if (file_put_contents($dir . '/' . $nomeArq, $bin) === false) {
            nv_json(['ok' => false, 'msg' => 'Falha ao gravar a miniatura.'], 500);
        }

        $st = $pdo->prepare('UPDATE treinamentos_avatares SET miniatura = ? WHERE id = ? LIMIT 1');
        $st->execute([$nomeArq, $id]);

        nv_json(['ok' => true, 'msg' => 'Miniatura atualizada.']);
    }

    case 'av_excluir': {
        $id = tr_int($dados, 'id');
        $av = tr_avatar($pdo, $id);
        if (!$av) { nv_json(['ok' => false, 'msg' => 'Instrutor não encontrado.'], 404); }

        $st = $pdo->prepare('SELECT COUNT(*) FROM treinamentos_apresentacoes WHERE avatar_id = ?');
        $st->execute([$id]);
        $usos = (int)$st->fetchColumn();
        if ($usos > 0) {
            nv_json([
                'ok' => false,
                'msg' => 'Este instrutor está em uso em ' . $usos . ' treinamento(s). '
                       . 'Troque o instrutor nesses treinamentos antes de excluir.',
            ], 409);
        }

        foreach ((array)glob(tr_dir_avatares() . '/av-' . $id . '.*') as $arq) { @unlink($arq); }
        $st = $pdo->prepare('DELETE FROM treinamentos_avatares WHERE id = ? LIMIT 1');
        $st->execute([$id]);

        nv_json(['ok' => true, 'msg' => 'Instrutor excluído.']);
    }

    case 'av_usar': {
        $apId = tr_int($dados, 'apresentacao_id');
        $ap   = tr_apresentacao($pdo, $apId);
        if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

        $id = tr_int($dados, 'id');
        if ($id > 0 && !tr_avatar($pdo, $id)) {
            nv_json(['ok' => false, 'msg' => 'Instrutor não encontrado.'], 404);
        }

        $st = $pdo->prepare('UPDATE treinamentos_apresentacoes SET avatar_id = ? WHERE id = ? LIMIT 1');
        $st->execute([$id > 0 ? $id : null, $apId]);

        nv_json(['ok' => true, 'msg' => $id > 0 ? 'Instrutor definido.' : 'Instrutor removido.']);
    }

    }

    nv_json(['ok' => false, 'msg' => 'Ação de instrutor desconhecida.'], 400);
}
