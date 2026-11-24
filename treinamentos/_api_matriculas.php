<?php
function tr_api_matriculas(PDO $pdo, string $acao, array $dados): void
{
    if (strpos($acao, 'insc_') !== 0
        && $acao !== 'trein_config'
        && $acao !== 'cert_config') { return; }

    if ($acao === 'trein_config') {
        trm_exigir_gerenciar_ajax();

        $id = tr_int($dados, 'id');
        $ap = tr_apresentacao($pdo, $id);
        if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

        $modo = tr_enum(tr_txt($dados, 'modo_acesso', (string)$ap['modo_acesso']),
                        ['interno', 'token', 'publico'], (string)$ap['modo_acesso']);

        $interacao = tr_enum(
            tr_txt($dados, 'interacao_obrigatoria', (string)($ap['interacao_obrigatoria'] ?? 'todas')),
            ['nenhuma', 'quiz', 'todas'],
            (string)($ap['interacao_obrigatoria'] ?? 'todas')
        );

        $st = $pdo->prepare(
            'UPDATE treinamentos_apresentacoes SET
                modo_acesso = ?, publico = ?, publico_desde = ?,
                prazo_dias = ?, carga_horaria_min = ?, descricao = ?,
                exige_termo = ?, emitir_certificado = ?, permite_revisao = ?,
                mensagem_conclusao = ?, email_conclusao = ?,
                interacao_obrigatoria = ?, interacao_momento = ?, quiz_exige_acerto = ?,
                tempo_min_slide = ?, exige_leitura = ?
              WHERE id = ? LIMIT 1'
        );
        $st->execute([
            $modo,
            $modo === 'publico' ? 1 : 0,
            $modo === 'publico' ? ($ap['publico_desde'] ?: date('Y-m-d H:i:s')) : null,
            max(0, min(365, tr_int($dados, 'prazo_dias', (int)$ap['prazo_dias']))),
            max(0, min(600, tr_int($dados, 'carga_horaria_min', (int)$ap['carga_horaria_min']))),
            mb_substr(tr_txt($dados, 'descricao', (string)($ap['descricao'] ?? '')), 0, 2000),
            tr_int($dados, 'exige_termo', (int)$ap['exige_termo']) ? 1 : 0,
            tr_int($dados, 'emitir_certificado', (int)$ap['emitir_certificado']) ? 1 : 0,
            tr_int($dados, 'permite_revisao', (int)$ap['permite_revisao']) ? 1 : 0,
            mb_substr(tr_txt($dados, 'mensagem_conclusao', ''), 0, 400),
            mb_substr(tr_txt($dados, 'email_conclusao', ''), 0, 255),
            $interacao,
            tr_enum(
                tr_txt($dados, 'interacao_momento', (string)($ap['interacao_momento'] ?? 'narracao')),
                ['narracao', 'imediato'],
                (string)($ap['interacao_momento'] ?? 'narracao')
            ),
            tr_int($dados, 'quiz_exige_acerto', (int)($ap['quiz_exige_acerto'] ?? 0)) ? 1 : 0,
            max(0, min(600, tr_int($dados, 'tempo_min_slide', (int)($ap['tempo_min_slide'] ?? 0)))),
            tr_int($dados, 'exige_leitura', (int)($ap['exige_leitura'] ?? 1)) ? 1 : 0,

            $id,
        ]);

        nv_json(['ok' => true, 'msg' => 'Configuração salva.']);
    }

    if ($acao === 'cert_config') {
        trm_exigir_gerenciar_ajax();

        require_once __DIR__ . '/_certificado_dados.php';

        $id = tr_int($dados, 'id');
        $ap = tr_apresentacao($pdo, $id);
        if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

        $in  = is_array($dados['cfg'] ?? null) ? $dados['cfg'] : [];
        $cfg = trc_cfg_do_form(trc_cfg($ap), $in);

        $texto = static function (string $v, int $max): string {
            return mb_substr(trim($v), 0, $max);
        };

        $colunas = [
            'cert_estilo'             => $cfg['estilo'],
            'cert_borda'              => $cfg['borda'],
            'cert_fundo'              => $cfg['fundo'],
            'cert_orientacao'         => $cfg['orientacao'],
            'cert_papel'              => $cfg['papel'],
            'cert_densidade'          => $cfg['densidade'],
            'cert_alinhamento'        => $cfg['alinhamento'],
            'cert_cor_modo'           => $cfg['cor_modo'],
            'cert_cor_primaria'       => $cfg['primaria'],
            'cert_cor_acento'         => $cfg['acento'],

            'cert_fonte_titulo'       => $cfg['fonte_titulo'],
            'cert_fonte_texto'        => $cfg['fonte_texto'],
            'cert_fonte_nome'         => $cfg['fonte_nome'],
            'cert_nome_estilo'        => $cfg['nome_estilo'],
            'cert_nome_caixa_alta'    => $cfg['nome_alta'] ? 1 : 0,
            'cert_nome_tamanho'       => (int)$cfg['nome_tamanho'],

            'cert_titulo'             => $texto((string)$cfg['titulo'], 60),
            'cert_subtitulo'          => $texto((string)$cfg['subtitulo'], 90),
            'cert_preambulo'          => $texto((string)$cfg['preambulo'], 90),
            'cert_empresa'            => $texto((string)$cfg['empresa'], 90),
            'cert_texto'              => $texto((string)$cfg['frase'], 400),
            'cert_rodape'             => $texto((string)$cfg['rodape'], 240),
            'cert_cidade'             => $texto((string)$cfg['cidade'], 60),
            'cert_data_extenso'       => $cfg['data_extenso'] ? 1 : 0,
            'cert_mostra_local_data'  => $cfg['local_data'] ? 1 : 0,

            'cert_assina_nome'        => $texto((string)$cfg['assina_nome'], 90),
            'cert_assina_cargo'       => $texto((string)$cfg['assina_cargo'], 90),
            'cert_assina2_nome'       => $texto((string)$cfg['assina2_nome'], 90),
            'cert_assina2_cargo'      => $texto((string)$cfg['assina2_cargo'], 90),

            'cert_mostra_selo'        => $cfg['selo'] ? 1 : 0,
            'cert_selo_texto'         => $texto((string)$cfg['selo_texto'], 40),
            'cert_selo_icone'         => $cfg['selo_icone'],

            'cert_mostra_logo'        => $cfg['logo'] ? 1 : 0,
            'cert_marca_dagua'        => $cfg['marca_dagua'] ? 1 : 0,
            'cert_mostra_codigo'      => $cfg['codigo'] ? 1 : 0,
            'cert_mostra_cpf'         => $cfg['cpf'] ? 1 : 0,
            'cert_mostra_carga'       => $cfg['carga'] ? 1 : 0,
            'cert_mostra_tempo'       => $cfg['tempo'] ? 1 : 0,
            'cert_mostra_slides'      => $cfg['slides'] ? 1 : 0,
            'cert_mostra_nota'        => $cfg['nota'] ? 1 : 0,

            'cert_mostra_qr'          => $cfg['qr'] ? 1 : 0,
            'cert_qr_tamanho'         => (int)$cfg['qr_tamanho'],
            'cert_qr_posicao'         => $cfg['qr_posicao'],
            'cert_qr_legenda'         => $texto((string)$cfg['qr_legenda'], 60),
            'cert_qr_nivel'           => $cfg['qr_nivel'],

            'cert_verso'              => $cfg['verso'] ? 1 : 0,
            'cert_verso_titulo'       => $texto((string)$cfg['verso_titulo'], 90),
            'cert_verso_intro'        => $texto((string)$cfg['verso_intro'], 400),
            'cert_verso_modo'         => $cfg['verso_modo'],
            'cert_verso_texto'        => $texto((string)$cfg['verso_texto'], 8000),
            'cert_verso_colunas'      => (int)$cfg['verso_colunas'],
            'cert_verso_mostra_carga' => $cfg['verso_carga'] ? 1 : 0,
            'cert_verso_mostra_prova' => $cfg['verso_prova'] ? 1 : 0,
            'cert_verso_numeracao'    => $cfg['verso_numera'] ? 1 : 0,
            'cert_verso_rodape'       => $texto((string)$cfg['verso_rodape'], 240),

            'emitir_certificado'      => tr_int($dados, 'emitir_certificado',
                                                (int)($ap['emitir_certificado'] ?? 1)) ? 1 : 0,
        ];

        if (array_key_exists('cert_selo_tamanho', $ap))    { $colunas['cert_selo_tamanho']    = (int)$cfg['selo_tamanho']; }
        if (array_key_exists('cert_logo_tamanho', $ap))    { $colunas['cert_logo_tamanho']    = (int)$cfg['logo_tamanho']; }
        if (array_key_exists('cert_empresa_tamanho', $ap)) { $colunas['cert_empresa_tamanho'] = (int)$cfg['empresa_tamanho']; }
        if (array_key_exists('cert_titulos_tamanho', $ap)) { $colunas['cert_titulos_tamanho'] = (int)$cfg['titulos_tamanho']; }
        if (array_key_exists('cert_marca_tamanho', $ap))   { $colunas['cert_marca_tamanho']   = (int)$cfg['marca_tamanho']; }
        if (array_key_exists('cert_marca_espaco_h', $ap))  { $colunas['cert_marca_espaco_h']  = (int)$cfg['marca_espaco_h']; }
        if (array_key_exists('cert_marca_espaco_v', $ap))  { $colunas['cert_marca_espaco_v']  = (int)$cfg['marca_espaco_v']; }
        if (array_key_exists('cert_marca_opacidade', $ap)) { $colunas['cert_marca_opacidade'] = (int)$cfg['marca_opacidade']; }

        $sets = [];
        foreach (array_keys($colunas) as $c) { $sets[] = $c . ' = ?'; }

        $st = $pdo->prepare(
            'UPDATE treinamentos_apresentacoes SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1'
        );
        $st->execute(array_merge(array_values($colunas), [$id]));

        nv_json(['ok' => true, 'msg' => 'Modelo do certificado salvo.']);
    }

    if ($acao === 'insc_buscar_colab') {
        if (!trm_pode_ver()) { nv_json(['ok' => false, 'msg' => 'Sem permissão.'], 403); }
    } else {
        trm_exigir_gerenciar_ajax();
    }

    switch ($acao) {

    case 'insc_buscar_colab': {
        $apId  = tr_int($dados, 'apresentacao_id');
        $lista = trm_colab_buscar($pdo, tr_txt($dados, 'termo'), $apId);

        nv_json(['ok' => true, 'itens' => array_map(static function ($c) {
            return [
                'id'          => (int)$c['id'],
                'matricula'   => (string)$c['matricula'],
                'nome'        => (string)$c['nome'],
                'cargo'       => (string)($c['cargo'] ?? ''),
                'filial'      => (string)($c['filial'] ?? ''),
                'email'       => (string)($c['email'] ?? ''),
                'matriculado' => !empty($c['matriculado']),
            ];
        }, $lista)]);
    }

    case 'insc_criar': {
        $apId = tr_int($dados, 'apresentacao_id');
        $ap   = tr_apresentacao($pdo, $apId);
        if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

        $colab = trm_colab($pdo, tr_int($dados, 'colaborador_id'));
        if (!$colab) { $colab = trm_colab_por_matricula($pdo, tr_txt($dados, 'matricula')); }
        if (!$colab) { nv_json(['ok' => false, 'msg' => 'Colaborador não encontrado no cadastro.'], 404); }

        try {
            $r = trm_criar($pdo, $ap, $colab, tr_txt($dados, 'prazo'));
        } catch (InvalidArgumentException $e) {
            nv_json(['ok' => false, 'msg' => nv_safe_err($e)], 422);
        }

        if (!$r['novo']) {
            nv_json(['ok' => false, 'msg' => $colab['nome'] . ' já está matriculado neste treinamento.'], 409);
        }

        $enviou = false;
        if (!empty($dados['enviar'])) {
            $insc = trm_inscricao($pdo, $r['id']);
            $enviou = trm_api_enviar($pdo, $insc, $ap);
        }

        nv_json([
            'ok'      => true,
            'id'      => $r['id'],
            'nome'    => (string)$colab['nome'],
            'link'    => trm_link_token($r['token']),
            'enviado' => $enviou,
            'msg'     => $colab['nome'] . ' matriculado.' . ($enviou ? ' Link enviado por e-mail.' : ''),
        ]);
    }

    case 'insc_marcar_externo': {
        $inscId  = tr_int($dados, 'id');
        $colabId = tr_int($dados, 'colaborador_id');

        $insc = $inscId > 0 ? trm_inscricao($pdo, $inscId) : null;
        if ($inscId > 0 && !$insc) { nv_json(['ok' => false, 'msg' => 'Matrícula não encontrada.'], 404); }

        $apId = $insc ? (int)$insc['apresentacao_id'] : tr_int($dados, 'apresentacao_id');
        $ap   = tr_apresentacao($pdo, $apId);
        if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

        if (!$insc) {
            $colab = trm_colab($pdo, $colabId);
            if (!$colab) { nv_json(['ok' => false, 'msg' => 'Colaborador não encontrado no cadastro.'], 404); }
            try {
                $r = trm_criar($pdo, $ap, $colab);
            } catch (InvalidArgumentException $e) {
                nv_json(['ok' => false, 'msg' => nv_safe_err($e)], 422);
            }
            $insc = trm_inscricao($pdo, $r['id']);
        }

        $dataConclusao = tr_txt($dados, 'data_conclusao');
        if ($dataConclusao === '') { $dataConclusao = date('Y-m-d'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataConclusao)) {
            nv_json(['ok' => false, 'msg' => 'Data de conclusão inválida.'], 422);
        }
        $obs = mb_substr(tr_txt($dados, 'observacao'), 0, 255);

        $arqNome = null; $arqOriginal = null;
        if (isset($_FILES['arquivo']) && ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ((int)$_FILES['arquivo']['size'] > 10 * 1024 * 1024) {
                nv_json(['ok' => false, 'msg' => 'O certificado deve ter no máximo 10 MB.'], 422);
            }
            $original = (string)$_FILES['arquivo']['name'];
            if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'pdf'
                || !nv_upload_mime_ok((string)$_FILES['arquivo']['tmp_name'], ['application/pdf'])) {
                nv_json(['ok' => false, 'msg' => 'Envie o certificado em PDF.'], 422);
            }
            $dir = tr_garantir_dir((int)$ap['id']);
            if ($dir === null) { nv_json(['ok' => false, 'msg' => 'Não foi possível gravar o arquivo.'], 500); }
            $arqNome = 'certext-' . (int)$insc['id'] . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.pdf';
            if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . '/' . $arqNome)) {
                nv_json(['ok' => false, 'msg' => 'Falha ao gravar o certificado.'], 500);
            }
            $arqOriginal = mb_substr(basename($original), 0, 190);
        }

        trm_concluir_externo($pdo, $insc, $ap, $dataConclusao, $obs, $arqNome, $arqOriginal);

        nv_json(['ok' => true, 'id' => (int)$insc['id'], 'msg' => 'Treinamento marcado como realizado.']);
    }

    case 'insc_lote': {
        $apId = tr_int($dados, 'apresentacao_id');
        $ap   = tr_apresentacao($pdo, $apId);
        if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

        $texto = (string)($dados['texto'] ?? '');
        if (trim($texto) === '') {
            nv_json(['ok' => false, 'msg' => 'Informe as matrículas separadas por vírgula.'], 422);
        }

        $r = trm_importar_matriculas($pdo, $ap, $texto, tr_txt($dados, 'prazo'));

        $enviados = 0;
        if (!empty($dados['enviar']) && $r['novos']) {
            foreach ($r['novos'] as $novoId) {
                $insc = trm_inscricao($pdo, (int)$novoId);
                if ($insc && trm_api_enviar($pdo, $insc, $ap)) { $enviados++; }
            }
        }

        $msg = $r['criados'] . ' matrícula(s) criada(s).';
        if ($r['repetidos'] > 0) { $msg .= ' ' . $r['repetidos'] . ' já existia(m).'; }
        if ($enviados > 0)       { $msg .= ' ' . $enviados . ' link(s) enviado(s).'; }

        $novas = [];
        foreach ($r['novos'] as $novoId) {
            $insc = trm_inscricao($pdo, (int)$novoId);
            if ($insc) {
                $novas[] = ['id' => (int)$insc['id'], 'nome' => (string)$insc['nome'], 'email' => (string)$insc['email']];
            }
        }

        nv_json(['ok' => true, 'criados' => $r['criados'], 'erros' => $r['erros'], 'novos' => $novas, 'msg' => $msg]);
    }

    case 'insc_pendentes_envio': {
        $apId = tr_int($dados, 'apresentacao_id');
        $ap   = tr_apresentacao($pdo, $apId);
        if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

        $st = $pdo->prepare(
            'SELECT id, nome, email FROM treinamentos_inscricoes
              WHERE apresentacao_id = ? AND email <> "" AND status NOT IN ("cancelado","concluido")
                AND COALESCE(concluido_origem, "plataforma") <> "externo"
                AND ' . trm_sql_sem_desligados('treinamentos_inscricoes')
            . (empty($dados['todos']) ? ' AND enviado_em IS NULL' : '')
            . ' ORDER BY nome, id'
        );
        $st->execute([$apId]);

        nv_json(['ok' => true, 'itens' => array_map(static function ($r) {
            return ['id' => (int)$r['id'], 'nome' => (string)$r['nome'], 'email' => (string)$r['email']];
        }, $st->fetchAll(PDO::FETCH_ASSOC))]);
    }

    case 'insc_prazo': {
        $inscId = tr_int($dados, 'id');
        $insc   = trm_inscricao($pdo, $inscId);
        if (!$insc) { nv_json(['ok' => false, 'msg' => 'Matrícula não encontrada.'], 404); }

        $prazo = tr_txt($dados, 'prazo');
        if ($prazo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazo)) {
            nv_json(['ok' => false, 'msg' => 'Data inválida.'], 422);
        }

        $st = $pdo->prepare('UPDATE treinamentos_inscricoes SET prazo = ? WHERE id = ? LIMIT 1');
        $st->execute([$prazo !== '' ? $prazo : null, $inscId]);
        trm_log($pdo, $inscId, 'prazo_alterado', $prazo !== '' ? $prazo : 'sem prazo');

        nv_json(['ok' => true, 'msg' => $prazo !== ''
            ? 'Prazo alterado para ' . date('d/m/Y', strtotime($prazo)) . '.'
            : 'Prazo removido.']);
    }

    case 'insc_prazo_lote': {
        $apId = tr_int($dados, 'apresentacao_id');
        if (!tr_apresentacao($pdo, $apId)) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', is_array($dados['ids'] ?? null) ? $dados['ids'] : []),
            static fn($v) => $v > 0
        )));
        if (!$ids) { nv_json(['ok' => false, 'msg' => 'Nenhuma matrícula selecionada.'], 422); }
        if (count($ids) > 1000) { nv_json(['ok' => false, 'msg' => 'Seleção grande demais.'], 422); }

        $prazo = tr_txt($dados, 'prazo');
        if ($prazo !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazo) || !strtotime($prazo))) {
            nv_json(['ok' => false, 'msg' => 'Data inválida.'], 422);
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id FROM treinamentos_inscricoes WHERE apresentacao_id = ? AND id IN ($in)");
        $st->execute(array_merge([$apId], $ids));
        $validos = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        if (!$validos) { nv_json(['ok' => false, 'msg' => 'Matrículas não encontradas.'], 404); }

        $in = implode(',', array_fill(0, count($validos), '?'));
        $st = $pdo->prepare("UPDATE treinamentos_inscricoes SET prazo = ? WHERE id IN ($in)");
        $st->execute(array_merge([$prazo !== '' ? $prazo : null], $validos));

        foreach ($validos as $vid) {
            trm_log($pdo, $vid, 'prazo_alterado', ($prazo !== '' ? $prazo : 'sem prazo') . ' (lote)');
        }

        $n = count($validos);
        nv_json(['ok' => true, 'alterados' => $n, 'msg' => $prazo !== ''
            ? 'Prazo de ' . $n . ' matrícula(s) alterado para ' . date('d/m/Y', strtotime($prazo)) . '.'
            : 'Prazo removido de ' . $n . ' matrícula(s).']);
    }

    case 'insc_sincronizar': {
        $inscId = tr_int($dados, 'id');
        $insc   = trm_inscricao($pdo, $inscId);
        if (!$insc) { nv_json(['ok' => false, 'msg' => 'Matrícula não encontrada.'], 404); }

        if (!trm_sincronizar($pdo, $insc)) {
            nv_json(['ok' => false, 'msg' => 'Esta matrícula não está vinculada a um colaborador do cadastro.'], 422);
        }
        nv_json(['ok' => true, 'msg' => 'Dados atualizados a partir do cadastro.']);
    }

    case 'insc_enviar': {
        $inscId = tr_int($dados, 'id');

        if ($inscId <= 0) {
            $apId = tr_int($dados, 'apresentacao_id');
            $ap   = tr_apresentacao($pdo, $apId);
            if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

            $st = $pdo->prepare(
                'SELECT * FROM treinamentos_inscricoes
                  WHERE apresentacao_id = ? AND email <> "" AND status NOT IN ("cancelado","concluido")
                    AND COALESCE(concluido_origem, "plataforma") <> "externo"
                    AND ' . trm_sql_sem_desligados('treinamentos_inscricoes')
                . (empty($dados['todos']) ? ' AND enviado_em IS NULL' : '')
            );
            $st->execute([$apId]);
            $linhas = $st->fetchAll(PDO::FETCH_ASSOC);

            $ok = 0; $falhas = 0;
            foreach ($linhas as $insc) {
                if (trm_api_enviar($pdo, $insc, $ap)) { $ok++; } else { $falhas++; }
            }

            if ($ok === 0 && $falhas === 0) {
                nv_json(['ok' => true, 'enviados' => 0, 'msg' => 'Nenhum link pendente de envio.']);
            }
            nv_json([
                'ok' => true, 'enviados' => $ok,
                'msg' => $ok . ' link(s) enviado(s).' . ($falhas > 0 ? ' ' . $falhas . ' falha(s) no envio.' : ''),
            ]);
        }

        $insc = trm_inscricao($pdo, $inscId);
        if (!$insc) { nv_json(['ok' => false, 'msg' => 'Matrícula não encontrada.'], 404); }
        if ((string)$insc['email'] === '') {
            nv_json(['ok' => false, 'msg' => 'Esta matrícula não tem e-mail cadastrado. Copie o link e entregue manualmente.'], 422);
        }

        if (trm_insc_externa($insc)) {
            nv_json(['ok' => false, 'msg' => 'Treinamento concluído em outra plataforma: esta matrícula não recebe link.'], 422);
        }

        $ap = tr_apresentacao($pdo, (int)$insc['apresentacao_id']);
        if (!trm_api_enviar($pdo, $insc, $ap)) {
            nv_json(['ok' => false, 'msg' => 'Não foi possível enviar o e-mail. Verifique o endereço e tente novamente.'], 500);
        }
        nv_json(['ok' => true, 'msg' => 'Link enviado para ' . $insc['email'] . '.']);
    }

    case 'insc_status': {
        $inscId = tr_int($dados, 'id');
        $insc   = trm_inscricao($pdo, $inscId);
        if (!$insc) { nv_json(['ok' => false, 'msg' => 'Matrícula não encontrada.'], 404); }

        $novo = tr_txt($dados, 'status');
        if ($novo === 'cancelado') {
            $st = $pdo->prepare('UPDATE treinamentos_inscricoes SET status = "cancelado" WHERE id = ? LIMIT 1');
            $st->execute([$inscId]);
            trm_log($pdo, $inscId, 'cancelado');
            nv_json(['ok' => true, 'msg' => 'Acesso cancelado. O link deixa de funcionar.']);
        }

        if ($novo === 'reativar') {
            $colabReativar = trm_colab($pdo, (int)($insc['colaborador_id'] ?? 0));
            if ($colabReativar && trm_colab_desligado($colabReativar)) {
                nv_json(['ok' => false, 'msg' => 'Colaborador desligado: a matrícula não pode ser reativada.'], 422);
            }
            $volta = ((int)$insc['slides_vistos'] > 0) ? 'em_andamento' : 'pendente';
            if (!empty($insc['concluido_em'])) { $volta = 'concluido'; }
            $st = $pdo->prepare('UPDATE treinamentos_inscricoes SET status = ? WHERE id = ? LIMIT 1');
            $st->execute([$volta, $inscId]);
            trm_log($pdo, $inscId, 'reativado');
            nv_json(['ok' => true, 'msg' => 'Matrícula reativada.']);
        }

        nv_json(['ok' => false, 'msg' => 'Situação inválida.'], 422);
    }

    case 'insc_reiniciar': {
        $inscId = tr_int($dados, 'id');
        if (!trm_inscricao($pdo, $inscId)) { nv_json(['ok' => false, 'msg' => 'Matrícula não encontrada.'], 404); }
        trm_reiniciar($pdo, $inscId);
        nv_json(['ok' => true, 'msg' => 'Progresso zerado. O colaborador recomeça do início.']);
    }

    case 'insc_token': {
        $inscId = tr_int($dados, 'id');
        $insc   = trm_inscricao($pdo, $inscId);
        if (!$insc) { nv_json(['ok' => false, 'msg' => 'Matrícula não encontrada.'], 404); }
        if (trm_insc_externa($insc)) {
            nv_json(['ok' => false, 'msg' => 'Treinamento concluído em outra plataforma: não é necessário gerar link.'], 422);
        }

        $novo = trm_token_novo();
        $st = $pdo->prepare(
            'UPDATE treinamentos_inscricoes SET token = ?, enviado_em = NULL WHERE id = ? LIMIT 1'
        );
        $st->execute([$novo, $inscId]);
        trm_log($pdo, $inscId, 'token_regerado');

        nv_json([
            'ok'   => true,
            'link' => trm_link_token($novo),
            'msg'  => 'Novo link gerado. O anterior deixou de valer — reenvie ao colaborador.',
        ]);
    }

    case 'insc_excluir': {
        $inscId = tr_int($dados, 'id');
        if (!trm_inscricao($pdo, $inscId)) { nv_json(['ok' => false, 'msg' => 'Matrícula não encontrada.'], 404); }

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('DELETE FROM treinamentos_progresso WHERE inscricao_id = ?');
            $st->execute([$inscId]);
            $st = $pdo->prepare('DELETE FROM treinamentos_inscricoes_log WHERE inscricao_id = ?');
            $st->execute([$inscId]);
            $st = $pdo->prepare('DELETE FROM treinamentos_inscricoes WHERE id = ? LIMIT 1');
            $st->execute([$inscId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            nv_json(['ok' => false, 'msg' => 'Não foi possível excluir a matrícula.'], 500);
        }

        nv_json(['ok' => true, 'msg' => 'Matrícula excluída.']);
    }

    }

    nv_json(['ok' => false, 'msg' => 'Ação de matrícula desconhecida.'], 400);
}

function trm_insc_externa(array $insc): bool
{
    return (string)($insc['status'] ?? '') === 'concluido'
        && (string)($insc['concluido_origem'] ?? 'plataforma') === 'externo';
}

function trm_api_enviar(PDO $pdo, array $insc, array $ap): bool
{
    if ((string)$insc['email'] === '') { return false; }
    if (trm_insc_externa($insc) || (string)($insc['status'] ?? '') === 'cancelado') { return false; }

    $reenvio = !empty($insc['enviado_em']);
    $ok = trm_email_convite($pdo, $insc, $ap, $reenvio);
    if (!$ok) { return false; }

    $st = $pdo->prepare(
        'UPDATE treinamentos_inscricoes SET enviado_em = NOW(), envios = envios + 1 WHERE id = ? LIMIT 1'
    );
    $st->execute([(int)$insc['id']]);
    trm_log($pdo, (int)$insc['id'], $reenvio ? 'link_reenviado' : 'link_enviado', (string)$insc['email']);

    return true;
}
