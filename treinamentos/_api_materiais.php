<?php
function trmat_proxima_ordem(PDO $pdo, string $tabela, int $apresentacaoId): int
{
    $st = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 FROM $tabela WHERE apresentacao_id = ?");
    $st->execute([$apresentacaoId]);
    return (int)$st->fetchColumn();
}

function trmat_mover(PDO $pdo, string $tabela, int $id, int $apresentacaoId, int $direcao): bool
{
    $st = $pdo->prepare("SELECT id, ordem FROM $tabela WHERE apresentacao_id = ? ORDER BY ordem, id");
    $st->execute([$apresentacaoId]);
    $linhas = $st->fetchAll(PDO::FETCH_ASSOC);

    $pos = -1;
    foreach ($linhas as $i => $l) {
        if ((int)$l['id'] === $id) { $pos = $i; break; }
    }
    if ($pos < 0) { return false; }

    $destino = $pos + ($direcao < 0 ? -1 : 1);
    if ($destino < 0 || $destino >= count($linhas)) { return false; }

    $tmp = $linhas[$pos];
    $linhas[$pos] = $linhas[$destino];
    $linhas[$destino] = $tmp;

    $up = $pdo->prepare("UPDATE $tabela SET ordem = ? WHERE id = ? AND apresentacao_id = ? LIMIT 1");
    foreach ($linhas as $i => $l) {
        $up->execute([$i + 1, (int)$l['id'], $apresentacaoId]);
    }
    return true;
}

function trmat_receber_arquivo(int $apresentacaoId): array
{
    if (!isset($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        nv_json(['ok' => false, 'msg' => 'Nenhum arquivo recebido.'], 422);
    }
    if ((int)$_FILES['arquivo']['size'] > trmat_tamanho_max()) {
        nv_json(['ok' => false, 'msg' => 'O documento deve ter no máximo 30 MB.'], 422);
    }

    $original = (string)$_FILES['arquivo']['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!array_key_exists($ext, trmat_formatos())) {
        nv_json([
            'ok'  => false,
            'msg' => 'Formato não aceito. Use ' . implode(', ', array_keys(trmat_formatos())) . '.',
        ], 422);
    }

    $ole  = ['application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint',
             'application/cdfv2', 'application/x-ole-storage', 'application/vnd.ms-office'];
    $ooxml = ['application/zip', 'application/octet-stream',
              'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
              'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
              'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
    $mimesPorExt = [
        'pdf' => ['application/pdf'],
        'doc' => $ole, 'xls' => $ole, 'ppt' => $ole,
        'docx' => $ooxml, 'xlsx' => $ooxml, 'pptx' => $ooxml,
        'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'webp' => ['image/webp'],
        'txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'zip' => ['application/zip'], 'mp4' => ['video/mp4'],
    ];
    if (!nv_upload_mime_ok((string)$_FILES['arquivo']['tmp_name'], $mimesPorExt[$ext] ?? [])) {
        nv_json(['ok' => false, 'msg' => 'O conteúdo do arquivo não corresponde à extensão .' . $ext . '.'], 422);
    }

    $dir = trmat_garantir_dir($apresentacaoId);
    if ($dir === null) {
        nv_json(['ok' => false, 'msg' => 'Não foi possível criar a pasta de apoio.'], 500);
    }

    $nome = 'apoio-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . '/' . $nome)) {
        nv_json(['ok' => false, 'msg' => 'Falha ao gravar o documento.'], 500);
    }

    return [$nome, mb_substr(basename($original), 0, 190), (int)$_FILES['arquivo']['size']];
}

function tr_api_materiais(PDO $pdo, string $acao, array $dados): void
{
    if (strpos($acao, 'mat_') !== 0 && strpos($acao, 'ref_') !== 0) { return; }

    $apId = tr_int($dados, 'apresentacao_id');
    if ($apId <= 0) { $apId = (int)($_POST['apresentacao_id'] ?? 0); }

    $ap = tr_apresentacao($pdo, $apId);
    if (!$ap) { nv_json(['ok' => false, 'msg' => 'Treinamento não encontrado.'], 404); }

    trmat_exigir_gerenciar_ajax();

    if ($acao === 'mat_salvar') {
        $id     = tr_int($dados, 'id') ?: (int)($_POST['id'] ?? 0);
        $atual  = $id > 0 ? trmat_item($pdo, $id, $apId) : null;
        if ($id > 0 && !$atual) { nv_json(['ok' => false, 'msg' => 'Documento não encontrado.'], 404); }

        $titulo = tr_txt($dados, 'titulo', (string)($_POST['titulo'] ?? ''));
        if ($titulo === '') { nv_json(['ok' => false, 'msg' => 'Informe o título do documento.'], 422); }

        $tipo = tr_enum(
            tr_txt($dados, 'tipo', (string)($_POST['tipo'] ?? 'arquivo')),
            ['arquivo', 'link'],
            'arquivo'
        );
        $descricao = tr_txt($dados, 'descricao', (string)($_POST['descricao'] ?? ''));
        $url       = tr_txt($dados, 'url', (string)($_POST['url'] ?? ''));
        $destaque  = (tr_int($dados, 'destaque', (int)($_POST['destaque'] ?? 0)) === 1) ? 1 : 0;
        $ativo     = (tr_int($dados, 'ativo', (int)($_POST['ativo'] ?? 1)) === 1) ? 1 : 0;

        $temUpload = isset($_FILES['arquivo'])
            && ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

        if ($tipo === 'link') {
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                nv_json(['ok' => false, 'msg' => 'Informe um endereço começando com http:// ou https://'], 422);
            }
        } elseif (!$temUpload && (!$atual || (string)$atual['arquivo'] === '')) {
            nv_json(['ok' => false, 'msg' => 'Envie o arquivo do documento.'], 422);
        }

        $arquivo   = $atual ? (string)$atual['arquivo'] : '';
        $arqNome   = $atual ? (string)$atual['arquivo_nome'] : '';
        $arqTam    = $atual ? (int)$atual['arquivo_tamanho'] : 0;

        if ($tipo === 'arquivo' && $temUpload) {
            [$novoArq, $novoNome, $novoTam] = trmat_receber_arquivo($apId);
            if ($arquivo !== '') { @unlink(trmat_dir($apId) . '/' . basename($arquivo)); }
            $arquivo = $novoArq;
            $arqNome = $novoNome;
            $arqTam  = $novoTam;
        }

        if ($tipo === 'link') {
            if ($arquivo !== '') { @unlink(trmat_dir($apId) . '/' . basename($arquivo)); }
            $arquivo = '';
            $arqNome = '';
            $arqTam  = 0;
        } else {
            $url = '';
        }

        if ($id > 0) {
            $st = $pdo->prepare(
                'UPDATE treinamentos_materiais SET
                    titulo = ?, descricao = ?, tipo = ?, arquivo = ?, arquivo_nome = ?,
                    arquivo_tamanho = ?, url = ?, destaque = ?, ativo = ?
                  WHERE id = ? AND apresentacao_id = ? LIMIT 1'
            );
            $st->execute([
                mb_substr($titulo, 0, 160), mb_substr($descricao, 0, 400), $tipo,
                $arquivo, $arqNome, $arqTam, mb_substr($url, 0, 500),
                $destaque, $ativo, $id, $apId,
            ]);
        } else {
            $st = $pdo->prepare(
                'INSERT INTO treinamentos_materiais
                    (apresentacao_id, ordem, titulo, descricao, tipo, arquivo, arquivo_nome,
                     arquivo_tamanho, url, destaque, ativo, criado_por)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $st->execute([
                $apId,
                trmat_proxima_ordem($pdo, 'treinamentos_materiais', $apId),
                mb_substr($titulo, 0, 160), mb_substr($descricao, 0, 400), $tipo,
                $arquivo, $arqNome, $arqTam, mb_substr($url, 0, 500),
                $destaque, $ativo,
                function_exists('current_user_id') ? (current_user_id() ?: null) : null,
            ]);
            $id = (int)$pdo->lastInsertId();
        }

        nv_json(['ok' => true, 'id' => $id, 'msg' => 'Documento de apoio salvo.']);
    }

    if ($acao === 'mat_excluir') {
        $id = tr_int($dados, 'id');
        $m  = trmat_item($pdo, $id, $apId);
        if (!$m) { nv_json(['ok' => false, 'msg' => 'Documento não encontrado.'], 404); }

        if ((string)$m['arquivo'] !== '') {
            @unlink(trmat_dir($apId) . '/' . basename((string)$m['arquivo']));
        }
        $st = $pdo->prepare('DELETE FROM treinamentos_materiais WHERE id = ? AND apresentacao_id = ? LIMIT 1');
        $st->execute([$id, $apId]);

        nv_json(['ok' => true, 'msg' => 'Documento excluído.']);
    }

    if ($acao === 'mat_ativar') {
        $id = tr_int($dados, 'id');
        $m  = trmat_item($pdo, $id, $apId);
        if (!$m) { nv_json(['ok' => false, 'msg' => 'Documento não encontrado.'], 404); }
        $novo = (int)$m['ativo'] === 1 ? 0 : 1;
        $st = $pdo->prepare('UPDATE treinamentos_materiais SET ativo = ? WHERE id = ? AND apresentacao_id = ? LIMIT 1');
        $st->execute([$novo, $id, $apId]);
        nv_json(['ok' => true, 'ativo' => $novo,
                 'msg' => $novo ? 'Documento liberado.' : 'Documento oculto para o colaborador.']);
    }

    if ($acao === 'mat_apostila') {
        $bool = static fn(string $k, int $padrao = 0): int => (tr_int($dados, $k, $padrao) === 1) ? 1 : 0;

        try {
            $st = $pdo->prepare(
                'UPDATE treinamentos_apresentacoes SET
                    apostila_ativa = ?, apostila_titulo = ?, apostila_narracao = ?,
                    apostila_imagens = ?, apostila_questoes = ?, apostila_gabarito = ?,
                    apostila_referencias = ?, apostila_anotacoes = ?
                  WHERE id = ? LIMIT 1'
            );
            $st->execute([
                $bool('ativa'),
                mb_substr(tr_txt($dados, 'titulo'), 0, 160),
                $bool('narracao', 1),
                $bool('imagens', 1),
                $bool('questoes', 1),
                $bool('gabarito'),
                $bool('referencias', 1),
                $bool('anotacoes'),
                $apId,
            ]);
        } catch (Throwable $e) {
            nv_json([
                'ok'  => false,
                'msg' => 'Execute sql/migration_apostila.sql no phpMyAdmin antes de usar a apostila automática.',
            ], 500);
        }

        foreach ((array)glob(trmat_dir($apId) . '/apostila-*.pdf') as $velho) { @unlink($velho); }

        nv_json(['ok' => true, 'msg' => 'Apostila atualizada.']);
    }

    if ($acao === 'mat_mover') {
        $id = tr_int($dados, 'id');
        trmat_mover($pdo, 'treinamentos_materiais', $id, $apId, tr_int($dados, 'direcao', 1));
        nv_json(['ok' => true, 'msg' => 'Ordem atualizada.']);
    }

    if ($acao === 'ref_salvar') {
        $id    = tr_int($dados, 'id');
        $texto = tr_txt($dados, 'texto');
        $url   = tr_txt($dados, 'url');

        if ($texto === '') { nv_json(['ok' => false, 'msg' => 'Informe a referência.'], 422); }
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            nv_json(['ok' => false, 'msg' => 'O link deve começar com http:// ou https://'], 422);
        }

        $ativo = (tr_int($dados, 'ativo', 1) === 1) ? 1 : 0;

        if ($id > 0) {
            $st = $pdo->prepare(
                'UPDATE treinamentos_referencias SET texto = ?, url = ?, ativo = ?
                  WHERE id = ? AND apresentacao_id = ? LIMIT 1'
            );
            $st->execute([mb_substr($texto, 0, 600), mb_substr($url, 0, 500), $ativo, $id, $apId]);
        } else {
            $st = $pdo->prepare(
                'INSERT INTO treinamentos_referencias
                    (apresentacao_id, ordem, texto, url, ativo, criado_por)
                 VALUES (?,?,?,?,?,?)'
            );
            $st->execute([
                $apId,
                trmat_proxima_ordem($pdo, 'treinamentos_referencias', $apId),
                mb_substr($texto, 0, 600), mb_substr($url, 0, 500), $ativo,
                function_exists('current_user_id') ? (current_user_id() ?: null) : null,
            ]);
            $id = (int)$pdo->lastInsertId();
        }

        nv_json(['ok' => true, 'id' => $id, 'msg' => 'Referência salva.']);
    }

    if ($acao === 'ref_excluir') {
        $st = $pdo->prepare('DELETE FROM treinamentos_referencias WHERE id = ? AND apresentacao_id = ? LIMIT 1');
        $st->execute([tr_int($dados, 'id'), $apId]);
        nv_json(['ok' => true, 'msg' => 'Referência excluída.']);
    }

    if ($acao === 'ref_ativar') {
        $id = tr_int($dados, 'id');
        $st = $pdo->prepare('SELECT ativo FROM treinamentos_referencias WHERE id = ? AND apresentacao_id = ? LIMIT 1');
        $st->execute([$id, $apId]);
        $atual = $st->fetchColumn();
        if ($atual === false) { nv_json(['ok' => false, 'msg' => 'Referência não encontrada.'], 404); }
        $novo = (int)$atual === 1 ? 0 : 1;
        $st = $pdo->prepare('UPDATE treinamentos_referencias SET ativo = ? WHERE id = ? AND apresentacao_id = ? LIMIT 1');
        $st->execute([$novo, $id, $apId]);
        nv_json(['ok' => true, 'ativo' => $novo,
                 'msg' => $novo ? 'Referência liberada.' : 'Referência oculta para o colaborador.']);
    }

    if ($acao === 'ref_mover') {
        trmat_mover($pdo, 'treinamentos_referencias', tr_int($dados, 'id'), $apId, tr_int($dados, 'direcao', 1));
        nv_json(['ok' => true, 'msg' => 'Ordem atualizada.']);
    }

    nv_json(['ok' => false, 'msg' => 'Ação desconhecida.'], 400);
}
