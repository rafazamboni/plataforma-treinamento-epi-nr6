<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';

require_once __DIR__ . '/_matriculas.php';
require_once __DIR__ . '/_prova.php';
require_once __DIR__ . '/_materiais.php';
require_once __DIR__ . '/_obrigatoriedade.php';

require_login();
tr_exigir_ajax('treinamentos.apresentacoes.ver');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    nv_json(['ok' => false, 'msg' => 'Método não permitido.'], 405);
}

csrf_validate();

$pdo   = db();
$acao  = (string)($_POST['acao'] ?? '');
$dados = json_decode((string)($_POST['dados'] ?? '{}'), true);
if (!is_array($dados)) { $dados = []; }

function tr_int($d, string $k, int $p = 0): int   { return isset($d[$k]) ? (int)$d[$k] : $p; }
function tr_num($d, string $k, float $p = 0): float { return isset($d[$k]) ? (float)$d[$k] : $p; }
function tr_txt($d, string $k, string $p = ''): string { return isset($d[$k]) ? trim((string)$d[$k]) : $p; }

function tr_cor($valor, string $padrao): string
{
    $v = strtoupper(trim((string)$valor));
    return preg_match('/^#[0-9A-F]{6}$/', $v) ? $v : $padrao;
}

function tr_enum(string $v, array $validos, string $padrao): string
{
    return in_array($v, $validos, true) ? $v : $padrao;
}

function tr_apres_do_slide(PDO $pdo, int $slideId): int
{
    $st = $pdo->prepare('SELECT apresentacao_id FROM treinamentos_slides WHERE id = ? LIMIT 1');
    $st->execute([$slideId]);
    return (int)$st->fetchColumn();
}

function tr_apres_do_dialogo(PDO $pdo, int $dialogoId): int
{
    $st = $pdo->prepare(
        'SELECT s.apresentacao_id FROM treinamentos_dialogos d
           JOIN treinamentos_slides s ON s.id = d.slide_id
          WHERE d.id = ? LIMIT 1'
    );
    $st->execute([$dialogoId]);
    return (int)$st->fetchColumn();
}

function tr_proxima_ordem(PDO $pdo, string $tabela, string $coluna, int $pai): int
{
    $st = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 FROM $tabela WHERE $coluna = ?");
    $st->execute([$pai]);
    return (int)$st->fetchColumn();
}

require_once __DIR__ . '/_api_matriculas.php';
require_once __DIR__ . '/_api_prova.php';
require_once __DIR__ . '/_api_avatares.php';
require_once __DIR__ . '/_api_materiais.php';
require_once __DIR__ . '/_api_obrigatoriedade.php';
tr_api_matriculas($pdo, $acao, $dados);
tr_api_prova($pdo, $acao, $dados);
tr_api_avatares($pdo, $acao, $dados);
tr_api_materiais($pdo, $acao, $dados);
tr_api_obrigatoriedade($pdo, $acao, $dados);

switch ($acao) {

case 'apres_criar': {
    tr_exigir_ajax('treinamentos.apresentacoes.criar');
    $titulo = tr_txt($dados, 'titulo');
    if ($titulo === '') { nv_json(['ok' => false, 'msg' => 'Informe o título.'], 422); }

    $st = $pdo->prepare(
        'INSERT INTO treinamentos_apresentacoes (slug, titulo, subtitulo, criado_por) VALUES (?, ?, ?, ?)'
    );
    $st->execute([tr_slug_unico($pdo, $titulo), $titulo, tr_txt($dados, 'subtitulo'), current_user_id() ?: null]);
    $id = (int)$pdo->lastInsertId();

    $st = $pdo->prepare(
        'INSERT INTO treinamentos_slides (apresentacao_id, ordem, titulo, transicao, layout, conteudo_html)
         VALUES (?, 1, ?, "zoom", "esquerda", ?)'
    );
    $st->execute([
        $id, 'Abertura',
        "<div class=\"nv-eyebrow\">Treinamento interno</div>\n"
        . "<h2 class=\"nv-titulo\">" . h($titulo) . "</h2>\n"
        . "<p class=\"nv-subtitulo\">" . h(tr_txt($dados, 'subtitulo')) . "</p>",
    ]);

    nv_json(['ok' => true, 'id' => $id, 'msg' => 'Apresentação criada.']);
}

case 'apres_salvar': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $id = tr_int($dados, 'id');
    $ap = tr_apresentacao($pdo, $id);
    if (!$ap) { nv_json(['ok' => false, 'msg' => 'Apresentação não encontrada.'], 404); }

    $titulo = tr_txt($dados, 'titulo', (string)$ap['titulo']);
    if ($titulo === '') { nv_json(['ok' => false, 'msg' => 'O título não pode ficar vazio.'], 422); }
    $slugPedido = tr_txt($dados, 'slug', (string)$ap['slug']);
    $slug = tr_slug_unico($pdo, $slugPedido !== '' ? $slugPedido : $titulo, $id);

    $vozNome = tr_txt($dados, 'voz_nome', (string)$ap['voz_nome']);
    if (!array_key_exists($vozNome, tr_vozes())) { $vozNome = (string)$ap['voz_nome']; }

    $colunas = [
        'slug'           => $slug,
        'titulo'         => $titulo,
        'subtitulo'      => tr_txt($dados, 'subtitulo', (string)($ap['subtitulo'] ?? '')),
        'marca'          => mb_substr(tr_txt($dados, 'marca', (string)$ap['marca']), 0, 8),
        'descricao'      => tr_txt($dados, 'descricao', (string)($ap['descricao'] ?? '')),
        'status'         => tr_enum(tr_txt($dados, 'status', (string)$ap['status']), ['rascunho', 'publicado', 'arquivado'], (string)$ap['status']),
        'avanco_auto'    => tr_int($dados, 'avanco_auto', (int)$ap['avanco_auto']) ? 1 : 0,
        'tema'           => tr_enum(tr_txt($dados, 'tema', (string)$ap['tema']), ['claro', 'escuro'], (string)$ap['tema']),
        'cor_primaria'   => tr_cor($dados['cor_primaria'] ?? null, (string)$ap['cor_primaria']),
        'cor_acento'     => tr_cor($dados['cor_acento'] ?? null, (string)$ap['cor_acento']),
        'cor_fundo'      => tr_cor($dados['cor_fundo'] ?? null, (string)$ap['cor_fundo']),
        'avatar_nome'    => mb_substr(tr_txt($dados, 'avatar_nome', (string)$ap['avatar_nome']), 0, 60),
        'avatar_camera'  => tr_enum(tr_txt($dados, 'avatar_camera', (string)$ap['avatar_camera']), array_keys(tr_cameras()), (string)$ap['avatar_camera']),
        'avatar_humor'   => tr_enum(tr_txt($dados, 'avatar_humor', (string)$ap['avatar_humor']), array_keys(tr_humores()), (string)$ap['avatar_humor']),
        'voz_nome'       => $vozNome,
        'voz_velocidade' => max(0.7, min(1.4, tr_num($dados, 'voz_velocidade', (float)$ap['voz_velocidade']))),
    ];

    if (array_key_exists('voz_tom', $ap)) {
        $colunas['voz_tom']       = max(0.80, min(1.25, tr_num($dados, 'voz_tom', (float)$ap['voz_tom'])));
        $colunas['voz_pausa_ms']  = max(0, min(1200, tr_int($dados, 'voz_pausa_ms', (int)$ap['voz_pausa_ms'])));
        $colunas['voz_ganho']     = max(0.50, min(1.80, tr_num($dados, 'voz_ganho', (float)$ap['voz_ganho'])));
        $colunas['voz_qualidade'] = tr_enum(tr_txt($dados, 'voz_qualidade', (string)$ap['voz_qualidade']), array_keys(tr_voz_qualidades()), 'alta');
        $colunas['voz_kbps']      = tr_enum((string)tr_int($dados, 'voz_kbps', (int)$ap['voz_kbps']), ['64', '96', '128', '160'], '96');
    }

    $sets = [];
    foreach (array_keys($colunas) as $c) { $sets[] = '`' . $c . '` = ?'; }
    $st = $pdo->prepare(
        'UPDATE treinamentos_apresentacoes SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1'
    );
    $valores = array_values($colunas);
    $valores[] = $id;
    $st->execute($valores);
    nv_json(['ok' => true, 'slug' => $slug, 'msg' => 'Apresentação salva.']);
}

case 'apres_publicar': {
    tr_exigir_ajax('treinamentos.apresentacoes.publicar');
    $id = tr_int($dados, 'id');
    $ap = tr_apresentacao($pdo, $id);
    if (!$ap) { nv_json(['ok' => false, 'msg' => 'Apresentação não encontrada.'], 404); }
    $novo = ((int)$ap['publico'] === 1) ? 0 : 1;
    $st = $pdo->prepare('UPDATE treinamentos_apresentacoes SET publico = ?, publico_desde = ?, status = ? WHERE id = ? LIMIT 1');
    $st->execute([$novo, $novo ? date('Y-m-d H:i:s') : null, $novo ? 'publicado' : $ap['status'], $id]);
    nv_json([
        'ok' => true, 'publico' => $novo,
        'msg' => $novo ? 'Link público liberado.' : 'Apresentação voltou a ser interna.',
    ]);
}

case 'apres_excluir': {
    tr_exigir_ajax('treinamentos.apresentacoes.excluir');
    $id = tr_int($dados, 'id');
    trmat_apagar_pasta($id);
    $dir = tr_dir_midia($id);
    if (is_dir($dir)) {
        foreach ((array)glob($dir . '/*') as $f) { if (is_file($f)) { @unlink($f); } }
        @rmdir($dir);
    }
    $st = $pdo->prepare('DELETE FROM treinamentos_apresentacoes WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    nv_json(['ok' => true, 'msg' => 'Apresentação excluída.']);
}

case 'apres_duplicar': {
    tr_exigir_ajax('treinamentos.apresentacoes.criar');
    $id = tr_int($dados, 'id');
    $ap = tr_apresentacao($pdo, $id);
    if (!$ap) { nv_json(['ok' => false, 'msg' => 'Apresentação não encontrada.'], 404); }

    $pdo->beginTransaction();
    try {
        $novoTitulo = mb_substr($ap['titulo'] . ' (cópia)', 0, 160);
        $colunas = ['titulo','subtitulo','marca','descricao','tema','cor_primaria','cor_acento','cor_fundo',
                    'avatar_arquivo','avatar_nome','avatar_camera','avatar_humor',
                    'voz_nome','voz_velocidade','avanco_auto'];
        $valores = [];
        foreach ($colunas as $c) { $valores[] = $c === 'titulo' ? $novoTitulo : $ap[$c]; }

        $st = $pdo->prepare(
            'INSERT INTO treinamentos_apresentacoes (slug, ' . implode(',', $colunas) . ', criado_por, status)
             VALUES (?, ' . rtrim(str_repeat('?,', count($colunas)), ',') . ', ?, "rascunho")'
        );
        $st->execute(array_merge([tr_slug_unico($pdo, $novoTitulo)], $valores, [current_user_id() ?: null]));
        $novoId = (int)$pdo->lastInsertId();

        foreach (tr_estrutura($pdo, $id) as $s) {
            $stS = $pdo->prepare(
                'INSERT INTO treinamentos_slides
                    (apresentacao_id, ordem, titulo, transicao, layout, fundo_tipo, fundo_cor, fundo_cor2,
                     fundo_angulo, fundo_imagem, fundo_escurecer, cor_texto,
                     instrutor_visivel, instrutor_posicao, instrutor_escala, instrutor_camera,
                     instrutor_lado_conteudo, instrutor_giro, conteudo_html, resumo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stS->execute([
                $novoId, $s['ordem'], $s['titulo'], $s['transicao'], $s['layout'], $s['fundo_tipo'],
                $s['fundo_cor'], $s['fundo_cor2'], $s['fundo_angulo'], $s['fundo_imagem'],
                $s['fundo_escurecer'], $s['cor_texto'], $s['instrutor_visivel'], $s['instrutor_posicao'],
                $s['instrutor_escala'], $s['instrutor_camera'],
                $s['instrutor_lado_conteudo'] ?? 'auto', $s['instrutor_giro'] ?? 'herdar',
                $s['conteudo_html'], $s['resumo'],
            ]);
            $novoSlide = (int)$pdo->lastInsertId();

            foreach ($s['dialogos'] as $d) {
                $stD = $pdo->prepare(
                    'INSERT INTO treinamentos_dialogos
                        (slide_id, ordem, texto, balao, humor, gesto, gesto_duracao, gesto_espelhado,
                         gesto_braco, gesto_intensidade, tronco_giro, cabeca_mov, olhar_forca, olhar, pausa_ms)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stD->execute([
                    $novoSlide, $d['ordem'], $d['texto'], $d['balao'], $d['humor'], $d['gesto'],
                    $d['gesto_duracao'], $d['gesto_espelhado'],
                    $d['gesto_braco'] ?? 'auto', $d['gesto_intensidade'] ?? 1,
                    $d['tronco_giro'] ?? 'auto', $d['cabeca_mov'] ?? 'auto', $d['olhar_forca'] ?? 1,
                    $d['olhar'], $d['pausa_ms'],
                ]);
                $novoDialogo = (int)$pdo->lastInsertId();

                foreach ($d['acoes'] as $a) {
                    $stA = $pdo->prepare(
                        'INSERT INTO treinamentos_acoes
                            (dialogo_id, ordem, momento, fase, tipo, alvo, valor, rotulo, titulo, html_conteudo, remover)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $stA->execute([
                        $novoDialogo, $a['ordem'], $a['momento'], $a['fase'], $a['tipo'], $a['alvo'],
                        $a['valor'], $a['rotulo'], $a['titulo'], $a['html_conteudo'], $a['remover'],
                    ]);
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        nv_json(['ok' => false, 'msg' => 'Não foi possível duplicar.'], 500);
    }
    nv_json(['ok' => true, 'id' => $novoId, 'msg' => 'Apresentação duplicada.']);
}

case 'estrutura': {
    $id = tr_int($dados, 'id');
    $ap = tr_apresentacao($pdo, $id);
    if (!$ap) { nv_json(['ok' => false, 'msg' => 'Apresentação não encontrada.'], 404); }
    nv_json(['ok' => true, 'apresentacao' => $ap, 'slides' => tr_estrutura($pdo, $id)]);
}

case 'slide_criar': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $apId = tr_int($dados, 'apresentacao_id');
    if (!tr_apresentacao($pdo, $apId)) { nv_json(['ok' => false, 'msg' => 'Apresentação não encontrada.'], 404); }
    $ordem = tr_proxima_ordem($pdo, 'treinamentos_slides', 'apresentacao_id', $apId);
    $st = $pdo->prepare(
        'INSERT INTO treinamentos_slides (apresentacao_id, ordem, titulo, conteudo_html) VALUES (?, ?, ?, ?)'
    );
    $st->execute([
        $apId, $ordem, 'Slide ' . $ordem,
        "<div class=\"nv-eyebrow\">Seção</div>\n<h2 class=\"nv-titulo\">Título do slide</h2>\n"
        . "<p class=\"nv-subtitulo\">Linha de apoio.</p>",
    ]);
    nv_json(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'msg' => 'Slide criado.']);
}

case 'slide_salvar': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $id = tr_int($dados, 'id');
    if (!tr_apres_do_slide($pdo, $id)) { nv_json(['ok' => false, 'msg' => 'Slide não encontrado.'], 404); }

    $st = $pdo->prepare(
        'UPDATE treinamentos_slides SET
            titulo = ?, transicao = ?, layout = ?,
            fundo_tipo = ?, fundo_cor = ?, fundo_cor2 = ?, fundo_angulo = ?, fundo_escurecer = ?, cor_texto = ?,
            instrutor_visivel = ?, instrutor_posicao = ?, instrutor_escala = ?, instrutor_camera = ?,
            instrutor_lado_conteudo = ?, instrutor_giro = ?,
            conteudo_html = ?, resumo = ?
          WHERE id = ? LIMIT 1'
    );
    $st->execute([
        mb_substr(tr_txt($dados, 'titulo', 'Slide'), 0, 160),
        tr_enum(tr_txt($dados, 'transicao', 'fade'), array_keys(tr_transicoes()), 'fade'),
        tr_enum(tr_txt($dados, 'layout', 'esquerda'), array_keys(tr_layouts()), 'esquerda'),
        tr_enum(tr_txt($dados, 'fundo_tipo', 'tema'), ['tema', 'solido', 'gradiente', 'imagem'], 'tema'),
        preg_match('/^#[0-9A-Fa-f]{6}$/', tr_txt($dados, 'fundo_cor')) ? strtoupper(tr_txt($dados, 'fundo_cor')) : null,
        preg_match('/^#[0-9A-Fa-f]{6}$/', tr_txt($dados, 'fundo_cor2')) ? strtoupper(tr_txt($dados, 'fundo_cor2')) : null,
        max(0, min(360, tr_int($dados, 'fundo_angulo', 160))),
        max(0, min(85, tr_int($dados, 'fundo_escurecer'))),
        preg_match('/^#[0-9A-Fa-f]{6}$/', tr_txt($dados, 'cor_texto')) ? strtoupper(tr_txt($dados, 'cor_texto')) : null,
        tr_int($dados, 'instrutor_visivel', 1) ? 1 : 0,
        tr_enum(tr_txt($dados, 'instrutor_posicao', 'direita'), array_keys(tr_posicoes()), 'direita'),
        max(0.6, min(1.6, tr_num($dados, 'instrutor_escala', 1))),
        tr_enum(tr_txt($dados, 'instrutor_camera', 'herdar'), array_merge(['herdar'], array_keys(tr_cameras())), 'herdar'),
        tr_enum(tr_txt($dados, 'instrutor_lado_conteudo', 'auto'), array_keys(tr_lados_conteudo()), 'auto'),
        tr_enum(tr_txt($dados, 'instrutor_giro', 'herdar'), array_keys(tr_giros_slide()), 'herdar'),
        (string)($dados['conteudo_html'] ?? ''),
        mb_substr(tr_txt($dados, 'resumo'), 0, 255),
        $id,
    ]);
    nv_json(['ok' => true, 'msg' => 'Slide salvo.']);
}

case 'slide_excluir': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $st = $pdo->prepare('DELETE FROM treinamentos_slides WHERE id = ? LIMIT 1');
    $st->execute([tr_int($dados, 'id')]);
    nv_json(['ok' => true, 'msg' => 'Slide excluído.']);
}

case 'slide_reordenar': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $ordens = $dados['ordens'] ?? [];
    if (!is_array($ordens)) { nv_json(['ok' => false, 'msg' => 'Ordem inválida.'], 422); }
    $st = $pdo->prepare('UPDATE treinamentos_slides SET ordem = ? WHERE id = ? LIMIT 1');
    foreach ($ordens as $i => $sid) { $st->execute([$i + 1, (int)$sid]); }
    nv_json(['ok' => true, 'msg' => 'Ordem atualizada.']);
}

case 'dialogo_criar': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $slideId = tr_int($dados, 'slide_id');
    if (!tr_apres_do_slide($pdo, $slideId)) { nv_json(['ok' => false, 'msg' => 'Slide não encontrado.'], 404); }
    $ordem = tr_proxima_ordem($pdo, 'treinamentos_dialogos', 'slide_id', $slideId);
    $st = $pdo->prepare('INSERT INTO treinamentos_dialogos (slide_id, ordem, texto) VALUES (?, ?, ?)');
    $st->execute([$slideId, $ordem, 'Escreva aqui a fala do instrutor.']);
    nv_json(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'msg' => 'Fala adicionada.']);
}

case 'dialogo_salvar': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $id = tr_int($dados, 'id');
    if (!tr_apres_do_dialogo($pdo, $id)) { nv_json(['ok' => false, 'msg' => 'Fala não encontrada.'], 404); }
    $texto = trim((string)($dados['texto'] ?? ''));
    if ($texto === '') { nv_json(['ok' => false, 'msg' => 'O texto da fala não pode ficar vazio.'], 422); }

    $st = $pdo->prepare('SELECT audio_hash FROM treinamentos_dialogos WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $hashAtual = (string)$st->fetchColumn();
    $hashNovo  = sha1($texto);

    $gesto = tr_txt($dados, 'gesto');
    if (!array_key_exists($gesto, tr_gestos())) { $gesto = ''; }

    $st = $pdo->prepare(
        'UPDATE treinamentos_dialogos SET
            texto = ?, balao = ?, humor = ?, gesto = ?, gesto_duracao = ?, gesto_espelhado = ?,
            gesto_braco = ?, gesto_intensidade = ?, tronco_giro = ?, cabeca_mov = ?, olhar_forca = ?,
            olhar = ?, pausa_ms = ?
          WHERE id = ? LIMIT 1'
    );
    $st->execute([
        $texto,
        mb_substr(tr_txt($dados, 'balao'), 0, 120),
        tr_enum(tr_txt($dados, 'humor', 'neutral'), array_keys(tr_humores()), 'neutral'),
        $gesto !== '' ? $gesto : null,
        max(1, min(12, tr_num($dados, 'gesto_duracao', 3))),
        tr_int($dados, 'gesto_espelhado') ? 1 : 0,
        tr_enum(tr_txt($dados, 'gesto_braco', 'auto'), array_keys(tr_bracos()), 'auto'),
        max(0, min(1.5, tr_num($dados, 'gesto_intensidade', 1))),
        tr_enum(tr_txt($dados, 'tronco_giro', 'auto'), array_keys(tr_troncos()), 'auto'),
        tr_enum(tr_txt($dados, 'cabeca_mov', 'auto'), array_keys(tr_cabeca_movs()), 'auto'),
        max(0, min(1.5, tr_num($dados, 'olhar_forca', 1))),
        tr_enum(tr_txt($dados, 'olhar', 'camera'), array_keys(tr_olhares()), 'camera'),
        max(0, min(10000, tr_int($dados, 'pausa_ms', 320))),
        $id,
    ]);

    nv_json([
        'ok' => true,
        'audio_desatualizado' => ($hashAtual !== '' && $hashAtual !== $hashNovo),
        'msg' => 'Fala salva.',
    ]);
}

case 'dialogo_excluir': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $id = tr_int($dados, 'id');
    $apId = tr_apres_do_dialogo($pdo, $id);
    if ($apId) {
        foreach ((array)glob(tr_dir_midia($apId) . '/fala-' . $id . '.*') as $f) { @unlink($f); }
    }
    $st = $pdo->prepare('DELETE FROM treinamentos_dialogos WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    nv_json(['ok' => true, 'msg' => 'Fala excluída.']);
}

case 'dialogo_reordenar': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $ordens = $dados['ordens'] ?? [];
    if (!is_array($ordens)) { nv_json(['ok' => false, 'msg' => 'Ordem inválida.'], 422); }
    $st = $pdo->prepare('UPDATE treinamentos_dialogos SET ordem = ? WHERE id = ? LIMIT 1');
    foreach ($ordens as $i => $did) { $st->execute([$i + 1, (int)$did]); }
    nv_json(['ok' => true, 'msg' => 'Ordem das falas atualizada.']);
}

case 'acao_salvar': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $dialogoId = tr_int($dados, 'dialogo_id');
    if (!tr_apres_do_dialogo($pdo, $dialogoId)) { nv_json(['ok' => false, 'msg' => 'Fala não encontrada.'], 404); }
    $tipo = tr_txt($dados, 'tipo');
    if (!array_key_exists($tipo, tr_tipos_acao())) { nv_json(['ok' => false, 'msg' => 'Tipo de ação inválido.'], 422); }

    $campos = [
        max(0, min(1, tr_num($dados, 'momento'))),
        tr_txt($dados, 'fase', 'durante') === 'apos' ? 'apos' : 'durante',
        $tipo,
        mb_substr(tr_txt($dados, 'alvo'), 0, 255),
        mb_substr(tr_txt($dados, 'valor'), 0, 120),
        mb_substr(tr_txt($dados, 'rotulo'), 0, 120),
        mb_substr(tr_txt($dados, 'titulo'), 0, 160),
        (string)($dados['html_conteudo'] ?? ''),
        tr_int($dados, 'remover') ? 1 : 0,
    ];
    $id = tr_int($dados, 'id');

    if ($id > 0) {
        $st = $pdo->prepare(
            'UPDATE treinamentos_acoes SET momento=?, fase=?, tipo=?, alvo=?, valor=?, rotulo=?, titulo=?,
                    html_conteudo=?, remover=? WHERE id = ? LIMIT 1'
        );
        $st->execute(array_merge($campos, [$id]));
    } else {
        $ordem = tr_proxima_ordem($pdo, 'treinamentos_acoes', 'dialogo_id', $dialogoId);
        $st = $pdo->prepare(
            'INSERT INTO treinamentos_acoes
                (dialogo_id, ordem, momento, fase, tipo, alvo, valor, rotulo, titulo, html_conteudo, remover)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute(array_merge([$dialogoId, $ordem], $campos));
        $id = (int)$pdo->lastInsertId();
    }
    nv_json(['ok' => true, 'id' => $id, 'msg' => 'Ação salva.']);
}

case 'acao_excluir': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $st = $pdo->prepare('DELETE FROM treinamentos_acoes WHERE id = ? LIMIT 1');
    $st->execute([tr_int($dados, 'id')]);
    nv_json(['ok' => true, 'msg' => 'Ação removida.']);
}

case 'narrar_salvar': {
    tr_exigir_ajax('treinamentos.apresentacoes.narrar');
    $dialogoId = (int)($_POST['dialogo_id'] ?? 0);
    $apId = tr_apres_do_dialogo($pdo, $dialogoId);
    if (!$apId) { nv_json(['ok' => false, 'msg' => 'Fala não encontrada.'], 404); }
    if (!isset($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        nv_json(['ok' => false, 'msg' => 'Áudio não recebido.'], 422);
    }
    if ($_FILES['arquivo']['size'] > 15 * 1024 * 1024) {
        nv_json(['ok' => false, 'msg' => 'Áudio acima de 15 MB.'], 422);
    }
    $ext = strtolower(pathinfo((string)$_FILES['arquivo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp3', 'ogg', 'wav', 'm4a'], true)) {
        nv_json(['ok' => false, 'msg' => 'Formato de áudio não aceito.'], 422);
    }
    $dir = tr_garantir_dir($apId);
    if ($dir === null) { nv_json(['ok' => false, 'msg' => 'Não foi possível criar a pasta de mídia.'], 500); }

    foreach ((array)glob($dir . '/fala-' . $dialogoId . '.*') as $antigo) { @unlink($antigo); }
    $nome = 'fala-' . $dialogoId . '.' . $ext;
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . '/' . $nome)) {
        nv_json(['ok' => false, 'msg' => 'Falha ao gravar o áudio.'], 500);
    }

    $lip = (string)($_POST['lipsync'] ?? '');
    if ($lip !== '' && json_decode($lip, true) === null) { $lip = ''; }

    $st = $pdo->prepare('SELECT texto FROM treinamentos_dialogos WHERE id = ? LIMIT 1');
    $st->execute([$dialogoId]);
    $texto = (string)$st->fetchColumn();

    $st = $pdo->prepare(
        'UPDATE treinamentos_dialogos SET
            voz_modo = "arquivo", audio_arquivo = ?, audio_duracao_ms = ?, lipsync_json = ?,
            audio_gerado_em = NOW(), audio_hash = ?
          WHERE id = ? LIMIT 1'
    );
    $st->execute([
        $nome,
        max(0, (int)($_POST['duracao_ms'] ?? 0)) ?: null,
        $lip !== '' ? $lip : null,
        sha1($texto),
        $dialogoId,
    ]);
    nv_json(['ok' => true, 'arquivo' => $nome, 'msg' => 'Narração gravada.']);
}

case 'narrar_remover': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $dialogoId = tr_int($dados, 'dialogo_id');
    $apId = tr_apres_do_dialogo($pdo, $dialogoId);
    if ($apId) {
        foreach ((array)glob(tr_dir_midia($apId) . '/fala-' . $dialogoId . '.*') as $f) { @unlink($f); }
    }
    $st = $pdo->prepare(
        'UPDATE treinamentos_dialogos SET voz_modo = "sintese", audio_arquivo = NULL,
                audio_duracao_ms = NULL, lipsync_json = NULL, audio_gerado_em = NULL, audio_hash = NULL
          WHERE id = ? LIMIT 1'
    );
    $st->execute([$dialogoId]);
    nv_json(['ok' => true, 'msg' => 'Narração removida.']);
}

case 'avatar_upload': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $apId = (int)($_POST['apresentacao_id'] ?? 0);
    if (!tr_apresentacao($pdo, $apId)) { nv_json(['ok' => false, 'msg' => 'Apresentação não encontrada.'], 404); }
    if (!isset($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        nv_json(['ok' => false, 'msg' => 'Nenhum arquivo recebido.'], 422);
    }
    if ($_FILES['arquivo']['size'] > 60 * 1024 * 1024) {
        nv_json(['ok' => false, 'msg' => 'O avatar deve ter no máximo 60 MB.'], 422);
    }
    $ext = strtolower(pathinfo((string)$_FILES['arquivo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['vrm', 'glb'], true)) {
        nv_json(['ok' => false, 'msg' => 'Envie um arquivo .vrm (VRoid Studio) ou .glb.'], 422);
    }
    $dir = tr_garantir_dir($apId);
    if ($dir === null) { nv_json(['ok' => false, 'msg' => 'Não foi possível criar a pasta de mídia.'], 500); }

    foreach ((array)glob($dir . '/avatar.*') as $antigo) { @unlink($antigo); }
    $nome = 'avatar.' . $ext;
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . '/' . $nome)) {
        nv_json(['ok' => false, 'msg' => 'Falha ao gravar o avatar.'], 500);
    }
    $st = $pdo->prepare('UPDATE treinamentos_apresentacoes SET avatar_arquivo = ? WHERE id = ? LIMIT 1');
    $st->execute([$nome, $apId]);
    nv_json(['ok' => true, 'arquivo' => $nome, 'msg' => 'Avatar enviado.']);
}

case 'avatar_biblioteca_upload': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $chave = tr_enum((string)($_POST['modelo'] ?? ''), array_keys(tr_avatares_biblioteca()), '');
    if ($chave === '') { nv_json(['ok' => false, 'msg' => 'Modelo inválido.'], 422); }
    if (!isset($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        nv_json(['ok' => false, 'msg' => 'Nenhum arquivo recebido.'], 422);
    }
    if ($_FILES['arquivo']['size'] > 60 * 1024 * 1024) {
        nv_json(['ok' => false, 'msg' => 'O avatar deve ter no máximo 60 MB.'], 422);
    }
    $ext = strtolower(pathinfo((string)$_FILES['arquivo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['vrm', 'glb'], true)) {
        nv_json(['ok' => false, 'msg' => 'Envie um arquivo .vrm (VRoid Studio) ou .glb.'], 422);
    }
    $dir = tr_garantir_dir_avatares();
    if ($dir === null) { nv_json(['ok' => false, 'msg' => 'Não foi possível criar a pasta da biblioteca.'], 500); }

    foreach ((array)glob($dir . '/' . $chave . '.*') as $antigo) { @unlink($antigo); }
    $nome = $chave . '.' . $ext;
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . '/' . $nome)) {
        nv_json(['ok' => false, 'msg' => 'Falha ao gravar o modelo.'], 500);
    }
    nv_json(['ok' => true, 'arquivo' => $nome, 'msg' => 'Modelo instalado na biblioteca.']);
}

case 'avatar_biblioteca_thumb': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $chave = tr_enum((string)($_POST['modelo'] ?? ''), array_keys(tr_avatares_biblioteca()), '');
    if ($chave === '') { nv_json(['ok' => false, 'msg' => 'Modelo inválido.'], 422); }
    if (!isset($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        nv_json(['ok' => false, 'msg' => 'Nenhuma imagem recebida.'], 422);
    }
    if ($_FILES['arquivo']['size'] > 2 * 1024 * 1024) {
        nv_json(['ok' => false, 'msg' => 'Miniatura acima de 2 MB.'], 422);
    }
    $info = @getimagesize($_FILES['arquivo']['tmp_name']);
    if (!$info || ($info[2] ?? 0) !== IMAGETYPE_PNG) {
        nv_json(['ok' => false, 'msg' => 'A miniatura precisa ser PNG.'], 422);
    }
    $dir = tr_garantir_dir_avatares();
    if ($dir === null) { nv_json(['ok' => false, 'msg' => 'Não foi possível criar a pasta da biblioteca.'], 500); }
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . '/' . $chave . '.png')) {
        nv_json(['ok' => false, 'msg' => 'Falha ao gravar a miniatura.'], 500);
    }
    nv_json(['ok' => true, 'msg' => 'Miniatura atualizada.']);
}

case 'avatar_escolher': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $apId = (int)($dados['apresentacao_id'] ?? 0);
    if (!tr_apresentacao($pdo, $apId)) { nv_json(['ok' => false, 'msg' => 'Apresentação não encontrada.'], 404); }

    $modelo = (string)($dados['modelo'] ?? '');
    if ($modelo === 'proprio') {
        $atual = null;
        foreach ((array)glob(tr_dir_midia($apId) . '/avatar.*') as $arq) { $atual = basename($arq); }
        if ($atual === null) { nv_json(['ok' => false, 'msg' => 'Envie um arquivo para esta apresentação primeiro.'], 422); }
        $valor = $atual;
    } else {
        $chave = tr_enum($modelo, array_keys(tr_avatares_biblioteca()), '');
        if ($chave === '') { nv_json(['ok' => false, 'msg' => 'Modelo inválido.'], 422); }
        $arquivo = tr_avatar_biblioteca_arquivo($chave);
        if ($arquivo === null) { nv_json(['ok' => false, 'msg' => 'Esse modelo ainda não foi instalado na biblioteca.'], 422); }
        $valor = 'lib:' . $arquivo;
    }

    $st = $pdo->prepare('UPDATE treinamentos_apresentacoes SET avatar_arquivo = ? WHERE id = ? LIMIT 1');
    $st->execute([$valor, $apId]);
    nv_json(['ok' => true, 'arquivo' => $valor, 'msg' => 'Instrutor definido.']);
}

case 'midia_upload': {
    tr_exigir_ajax('treinamentos.apresentacoes.editar');
    $apId = (int)($_POST['apresentacao_id'] ?? 0);
    $tipo = (string)($_POST['tipo'] ?? 'fundo');
    $slideId = (int)($_POST['slide_id'] ?? 0);
    if (!tr_apresentacao($pdo, $apId)) { nv_json(['ok' => false, 'msg' => 'Apresentação não encontrada.'], 404); }
    if (!isset($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        nv_json(['ok' => false, 'msg' => 'Nenhum arquivo recebido.'], 422);
    }
    $ext = strtolower(pathinfo((string)$_FILES['arquivo']['name'], PATHINFO_EXTENSION));

    if ($tipo === 'video') {
        if (!in_array($ext, ['mp4', 'webm'], true)) {
            nv_json(['ok' => false, 'msg' => 'Use MP4 (H.264) ou WEBM.'], 422);
        }
        if ($_FILES['arquivo']['size'] > 20 * 1024 * 1024) {
            nv_json(['ok' => false, 'msg' => 'O vídeo deve ter no máximo 20 MB.'], 422);
        }
    } else {
        if ($_FILES['arquivo']['size'] > 4 * 1024 * 1024) {
            nv_json(['ok' => false, 'msg' => 'A imagem deve ter no máximo 4 MB.'], 422);
        }
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
            nv_json(['ok' => false, 'msg' => 'Use PNG, JPG, WEBP ou SVG.'], 422);
        }
    }
    $dir = tr_garantir_dir($apId);
    if ($dir === null) { nv_json(['ok' => false, 'msg' => 'Não foi possível criar a pasta de mídia.'], 500); }

    if ($tipo === 'imagem' || $tipo === 'video') {
        $prefixo = $tipo === 'video' ? 'vid' : 'img';
        $nome = $prefixo . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . '/' . $nome)) {
            nv_json(['ok' => false, 'msg' => 'Falha ao gravar o arquivo.'], 500);
        }
        nv_json(['ok' => true, 'arquivo' => $nome,
                 'msg' => $tipo === 'video' ? 'Vídeo enviado.' : 'Imagem enviada.']);
    }

    $nome = ($tipo === 'logo' ? 'logo' : 'fundo-' . $slideId) . '.' . $ext;
    foreach ((array)glob($dir . '/' . ($tipo === 'logo' ? 'logo' : 'fundo-' . $slideId) . '.*') as $antigo) {
        @unlink($antigo);
    }
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $dir . '/' . $nome)) {
        nv_json(['ok' => false, 'msg' => 'Falha ao gravar a imagem.'], 500);
    }

    if ($tipo === 'logo') {
        $st = $pdo->prepare('UPDATE treinamentos_apresentacoes SET logo_arquivo = ? WHERE id = ? LIMIT 1');
        $st->execute([$nome, $apId]);
    } else {
        if (tr_apres_do_slide($pdo, $slideId) !== $apId) {
            nv_json(['ok' => false, 'msg' => 'Slide inválido.'], 422);
        }
        $st = $pdo->prepare('UPDATE treinamentos_slides SET fundo_imagem = ?, fundo_tipo = "imagem" WHERE id = ? LIMIT 1');
        $st->execute([$nome, $slideId]);
    }
    nv_json(['ok' => true, 'arquivo' => $nome, 'msg' => 'Imagem enviada.']);
}

default:
    nv_json(['ok' => false, 'msg' => 'Ação desconhecida.'], 400);
}
