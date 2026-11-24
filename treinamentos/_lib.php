<?php
if (!defined('TR_MODULO')) {
    define('TR_MODULO', 'treinamentos');
}

function tr_base_url(): string
{
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $dir = rtrim($dir, '/');
    return $dir === '' ? '' : $dir;
}

function tr_url(string $arquivo = ''): string
{
    return tr_base_url() . '/' . ltrim($arquivo, '/');
}

function tr_url_v(string $arquivo): string
{
    $caminho = __DIR__ . '/' . ltrim($arquivo, '/');
    $v = is_file($caminho) ? filemtime($caminho) : time();
    return tr_url($arquivo) . '?v=' . (int)$v;
}

function tr_url_absoluta(string $arquivo = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . tr_url($arquivo);
}

if (!function_exists('tr_pode')) {
    function tr_pode(string $codigo): bool
    {
        if (function_exists('is_admin_user') && is_admin_user()) {
            return true;
        }
        return has_permission(db(), $codigo);
    }
}

if (!function_exists('tr_exigir')) {
    function tr_exigir(string $codigo): void
    {
        if (!tr_pode($codigo)) {
            flash_set('danger', 'Acesso restrito.');
            nv_redirect('/index.php');
        }
    }
}

if (!function_exists('tr_exigir_ajax')) {
    function tr_exigir_ajax(string $codigo): void
    {
        if (!tr_pode($codigo)) {
            nv_json(['ok' => false, 'msg' => 'Sem permissão.'], 403);
        }
    }
}

function tr_transicoes(): array
{
    return [
        'fade'     => 'Suave',
        'esquerda' => 'Deslizar da direita',
        'direita'  => 'Deslizar da esquerda',
        'cima'     => 'Subir',
        'zoom'     => 'Aproximar',
        'flip'     => 'Girar',
        'cortina'  => 'Cortina',
    ];
}

function tr_layouts(): array
{
    return [
        'esquerda' => 'Texto à esquerda',
        'centro'   => 'Centralizado',
        'dividido' => 'Duas colunas',
        'cheio'    => 'Largura total',
    ];
}

function tr_gestos(): array
{
    return [
        ''                 => 'Nenhum',

        'apresentar'       => 'Apresentar o conteúdo',
        'apontar'          => 'Apontar para o conteúdo',
        'apontar-lado'     => 'Apontar para o lado (braço estendido)',
        'apontar-cima'     => 'Apontar para cima',
        'apontar-baixo'    => 'Apontar para baixo',
        'olhar-conteudo'   => 'Voltar-se para o conteúdo',
        'inclinar-cabeca'  => 'Inclinar a cabeça (curiosidade)',

        'acenar'           => 'Acenar (cumprimentar)',
        'explicar'         => 'Explicar gesticulando',
        'enumerar'         => 'Enumerar pontos',
        'enumerar1'        => 'Contar nos dedos: 1',
        'enumerar2'        => 'Contar nos dedos: 2',
        'enumerar3'        => 'Contar nos dedos: 3',
        'enumerar4'        => 'Contar nos dedos: 4',
        'enumerar5'        => 'Contar nos dedos: 5',
        'enumerar6'        => 'Contar nos dedos: 6',
        'enumerar7'        => 'Contar nos dedos: 7',
        'enumerar8'        => 'Contar nos dedos: 8',
        'enumerar9'        => 'Contar nos dedos: 9',
        'enumerar10'       => 'Contar nos dedos: 10',
        'enfatizar'        => 'Enfatizar com a mão',
        'convidar'         => 'Convidar o público',
        'abrir-bracos'     => 'Abrir os braços (acolher)',
        'maos-juntas'      => 'Mãos juntas (atenção)',
        'comparar'         => 'Comparar (uma mão e outra)',
        'parar'            => 'Pedir atenção (palma à frente)',
        'ok'               => 'Sinal de certo (pinça)',
        'silencio'         => 'Pedir silêncio',
        'pensar'           => 'Pensar',
        'aprovar'          => 'Aprovar (polegar para cima)',
        'atencao'          => 'Chamar atenção (indicador)',
        'aplaudir'         => 'Aplaudir',
        'agradecer'        => 'Agradecer',

        'concordar'        => 'Concordar com a cabeça',
        'discordar'        => 'Discordar com a cabeça',
    ];
}

function tr_bracos(): array
{
    return [
        'auto'     => 'Automático (segue o conteúdo)',
        'direito'  => 'Braço direito (aparece à esquerda da tela)',
        'esquerdo' => 'Braço esquerdo (aparece à direita da tela)',
        'ambos'    => 'Os dois braços',
    ];
}

function tr_giros(): array
{
    return [
        'nenhum'      => 'De frente (não gira)',
        'leve'        => 'Leve (~10°)',
        'moderado'    => 'Moderado (~20°)',
        'tresquartos' => 'Três quartos (~33°)',
        'perfil'      => 'Quase de perfil (~55°)',
    ];
}

function tr_giros_slide(): array
{
    return ['herdar' => 'Herdar do instrutor'] + tr_giros();
}

function tr_troncos(): array
{
    return [
        'auto'     => 'Automático (segue o gesto)',
        'conteudo' => 'Virar para o conteúdo',
        'publico'  => 'Ficar de frente para o público',
        'nenhum'   => 'Não girar',
    ];
}

function tr_cabeca_movs(): array
{
    return [
        'auto'       => 'Automático (segue o olhar)',
        'acompanhar' => 'Acompanhar o conteúdo',
        'publico'    => 'Manter o rosto para o público',
        'livre'      => 'Natural (leve vaivém)',
        'fixo'       => 'Parada',
    ];
}

function tr_lados_conteudo(): array
{
    return [
        'auto'     => 'Automático (lado oposto ao instrutor)',
        'esquerda' => 'À esquerda da tela',
        'direita'  => 'À direita da tela',
        'centro'   => 'Ao centro / ao redor',
    ];
}

function tr_maos_dominantes(): array
{
    return [
        'direita'  => 'Destro (mão direita — surge à esquerda da tela)',
        'esquerda' => 'Canhoto (mão esquerda — surge à direita da tela)',
    ];
}

function tr_orientacao_avatar(?array $av): array
{
    $av = $av ?: [];
    return [
        'maoDominante'   => (string)($av['mao_dominante'] ?? 'direita'),
        'autoOrientar'   => ((int)($av['orient_auto'] ?? 1) === 1),
        'giroCorpo'      => (string)($av['giro_corpo'] ?? 'leve'),
        'giroQuadril'    => ((int)($av['giro_quadril'] ?? 0) === 1),
        'forcaTronco'    => (float)($av['forca_tronco'] ?? 1),
        'forcaCabeca'    => (float)($av['forca_cabeca'] ?? 1),
        'forcaGesto'     => (float)($av['forca_gesto'] ?? 1),
        'olharCabeca'    => (float)($av['olhar_cabeca'] ?? 0.35),
        'rostoPublico'   => (float)($av['rosto_publico'] ?? 0.5),
        'alturaConteudo' => (float)($av['altura_conteudo'] ?? 0),
        'suavidade'      => (float)($av['suavidade'] ?? 1),
        'olharPadrao'    => (string)($av['olhar_padrao'] ?? 'camera'),
    ];
}

function tr_humores(): array
{
    return [
        'neutral' => 'Neutra',
        'happy'   => 'Alegre',
        'sad'     => 'Séria / pesarosa',
        'angry'   => 'Firme',
        'fear'    => 'Preocupada',
        'love'    => 'Acolhedora',
    ];
}

function tr_cameras(): array
{
    return [
        'full'    => 'Corpo inteiro',
        'joelhos' => 'Dos joelhos para cima',
        'mid'     => 'Meio corpo (cintura)',
        'upper'   => 'Do peito para cima',
        'busto'   => 'Busto (ombros)',
        'head'    => 'Rosto',
    ];
}

function tr_posicoes(): array
{
    return [
        'esquerda'        => 'Esquerda (borda)',
        'centro-esquerda' => 'Esquerda, mais ao centro',
        'centro'          => 'Centro',
        'centro-direita'  => 'Direita, mais ao centro',
        'direita'         => 'Direita (borda)',
    ];
}

function tr_dir_avatares(): string
{
    $base = nv_config()['paths']['uploads'];
    return rtrim($base, '/\\') . '/treinamentos/_biblioteca';
}

function tr_garantir_dir_avatares(): ?string
{
    $dir = tr_dir_avatares();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) { return null; }
    return $dir;
}

function tr_avatares_biblioteca(): array
{
    return [
        'masculino' => 'Instrutor (masculino)',
        'feminino'  => 'Instrutora (feminino)',
    ];
}

function tr_avatar_biblioteca_arquivo(string $chave): ?string
{
    foreach (['vrm', 'glb'] as $ext) {
        if (is_file(tr_dir_avatares() . '/' . $chave . '.' . $ext)) { return $chave . '.' . $ext; }
    }
    return null;
}

function tr_avatar_biblioteca_miniatura(string $chave): ?string
{
    $caminho = tr_dir_avatares() . '/' . $chave . '.png';
    return is_file($caminho) ? $caminho : null;
}

function tr_avatares(PDO $pdo, bool $somenteAtivos = true): array
{
    $sql = 'SELECT * FROM treinamentos_avatares'
         . ($somenteAtivos ? ' WHERE ativo = 1' : '')
         . ' ORDER BY ordem, id';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function tr_avatar(PDO $pdo, int $id): ?array
{
    if ($id <= 0) { return null; }
    $st = $pdo->prepare('SELECT * FROM treinamentos_avatares WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function tr_avatar_da_apresentacao(PDO $pdo, array $ap): ?array
{
    return tr_avatar($pdo, (int)($ap['avatar_id'] ?? 0));
}

function tr_avatar_modelo_caminho(array $av): ?string
{
    $arq = (string)($av['arquivo'] ?? '');
    if ($arq === '') { return null; }
    $caminho = tr_dir_avatares() . '/' . basename($arq);
    return is_file($caminho) ? $caminho : null;
}

function tr_avatar_thumb_caminho(array $av): ?string
{
    $arq = (string)($av['miniatura'] ?? '');
    if ($arq === '') { return null; }
    $caminho = tr_dir_avatares() . '/' . basename($arq);
    return is_file($caminho) ? $caminho : null;
}

function tr_voz_config(PDO $pdo, array $ap): array
{
    $av = tr_avatar_da_apresentacao($pdo, $ap);

    if ($av) {
        return [
            'voz'        => (string)$av['voz_nome'],
            'velocidade' => (float)$av['voz_velocidade'],
            'tom'        => (float)$av['voz_tom'],
            'pausaMs'    => (int)$av['voz_pausa_ms'],
            'ganho'      => (float)$av['voz_ganho'],
            'qualidade'  => (string)$av['voz_qualidade'],
            'kbps'       => (int)$av['voz_kbps'],
            'origem'     => 'instrutor',
            'instrutor'  => (string)$av['nome'],
        ];
    }

    return [
        'voz'        => (string)($ap['voz_nome'] ?? 'F1'),
        'velocidade' => (float)($ap['voz_velocidade'] ?? 1),
        'tom'        => (float)($ap['voz_tom'] ?? 1),
        'pausaMs'    => (int)($ap['voz_pausa_ms'] ?? 260),
        'ganho'      => (float)($ap['voz_ganho'] ?? 1),
        'qualidade'  => (string)($ap['voz_qualidade'] ?? 'alta'),
        'kbps'       => (int)($ap['voz_kbps'] ?? 96),
        'origem'     => 'apresentacao',
        'instrutor'  => (string)($ap['avatar_nome'] ?? 'Instrutor'),
    ];
}

function tr_avatar_caminho(array $ap): ?string
{
    $avId = (int)($ap['avatar_id'] ?? 0);
    if ($avId > 0) {
        $av = tr_avatar(db(), $avId);
        if ($av) {
            $caminho = tr_avatar_modelo_caminho($av);
            if ($caminho !== null) { return $caminho; }
        }
    }

    $arq = (string)($ap['avatar_arquivo'] ?? '');
    if ($arq === '') { return null; }
    if (str_starts_with($arq, 'lib:')) {
        $nome = basename(substr($arq, 4));
        $caminho = tr_dir_avatares() . '/' . $nome;
        return is_file($caminho) ? $caminho : null;
    }
    $caminho = tr_dir_midia((int)$ap['id']) . '/' . basename($arq);
    return is_file($caminho) ? $caminho : null;
}

function tr_olhares(): array
{
    return [
        'camera'   => 'Para quem assiste',
        'slide'    => 'Para o conteúdo (lado automático)',
        'livre'    => 'Natural (vagando)',
        'cima'     => 'Para cima',
        'baixo'    => 'Para baixo',
        'esquerda' => 'Para a esquerda da tela',
        'direita'  => 'Para a direita da tela',
        'ombro'    => 'Por cima do ombro',
    ];
}

function tr_tipos_acao(): array
{
    return [
        'revelar'          => 'Revelar elemento',
        'ocultar'          => 'Ocultar elemento',
        'destacar'         => 'Destacar elemento',
        'limpar-destaque'  => 'Limpar destaque',
        'foco'             => 'Focar elemento (spotlight)',
        'limpar-foco'      => 'Limpar foco',
        'quadro'           => 'Abrir quadro explicativo',
        'fechar-quadro'    => 'Fechar quadro explicativo',
        'classe'           => 'Adicionar/remover classe',
        'gesto'            => 'Gesto do instrutor',
        'humor'            => 'Expressão do instrutor',
        'olhar'            => 'Direção do olhar',
        'instrutor'        => 'Mostrar/ocultar instrutor',
        'posicao'          => 'Reposicionar instrutor',
        'giro'             => 'Girar o corpo do instrutor',
        'lado-conteudo'    => 'Definir de que lado está o conteúdo',
    ];
}

function tr_vozes(): array
{
    return [
        'F1' => 'F1 — feminina 1',
        'F2' => 'F2 — feminina 2',
        'F3' => 'F3 — feminina 3',
        'F4' => 'F4 — feminina 4',
        'F5' => 'F5 — feminina 5',
        'M1' => 'M1 — masculina 1',
        'M2' => 'M2 — masculina 2',
        'M3' => 'M3 — masculina 3',
        'M4' => 'M4 — masculina 4',
        'M5' => 'M5 — masculina 5',
    ];
}

function tr_voz_qualidades(): array
{
    return [
        'rapida' => 'Rápida — 4 passos',
        'alta'   => 'Alta — 10 passos (recomendada)',
        'maxima' => 'Máxima — 20 passos (bem mais lenta)',
    ];
}

function tr_slugify(string $texto): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $s = strtolower((string)$s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    if ($s === '') { $s = 'apresentacao'; }
    return substr($s, 0, 110);
}

function tr_slug_unico(PDO $pdo, string $slug, int $ignorarId = 0): string
{
    $base = tr_slugify($slug);
    $tentativa = $base;
    $n = 1;
    while (true) {
        $st = $pdo->prepare('SELECT id FROM treinamentos_apresentacoes WHERE slug = ? AND id <> ? LIMIT 1');
        $st->execute([$tentativa, $ignorarId]);
        if (!$st->fetchColumn()) { return $tentativa; }
        $n++;
        $tentativa = substr($base, 0, 105) . '-' . $n;
    }
}

function tr_apresentacao(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM treinamentos_apresentacoes WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function tr_apresentacao_por_slug(PDO $pdo, string $slug): ?array
{
    $st = $pdo->prepare('SELECT * FROM treinamentos_apresentacoes WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function tr_estrutura(PDO $pdo, int $apresentacaoId): array
{
    $st = $pdo->prepare('SELECT * FROM treinamentos_slides WHERE apresentacao_id = ? ORDER BY ordem, id');
    $st->execute([$apresentacaoId]);
    $slides = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$slides) { return []; }

    $slideIds = array_map(static fn($s) => (int)$s['id'], $slides);
    $in = implode(',', array_fill(0, count($slideIds), '?'));

    $st = $pdo->prepare(
        "SELECT id, slide_id, ordem, texto, balao, humor, gesto, gesto_duracao, gesto_espelhado,
                gesto_braco, gesto_intensidade, tronco_giro, cabeca_mov, olhar_forca,
                olhar, voz_modo, audio_arquivo, audio_duracao_ms, audio_gerado_em, audio_hash, pausa_ms
           FROM treinamentos_dialogos WHERE slide_id IN ($in) ORDER BY slide_id, ordem, id"
    );
    $st->execute($slideIds);
    $dialogos = $st->fetchAll(PDO::FETCH_ASSOC);

    $acoesPorDialogo = [];
    if ($dialogos) {
        $dlgIds = array_map(static fn($d) => (int)$d['id'], $dialogos);
        $in2 = implode(',', array_fill(0, count($dlgIds), '?'));
        $st = $pdo->prepare(
            "SELECT * FROM treinamentos_acoes WHERE dialogo_id IN ($in2)
              ORDER BY dialogo_id, fase, momento, ordem, id"
        );
        $st->execute($dlgIds);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $acoesPorDialogo[(int)$a['dialogo_id']][] = $a;
        }
    }

    $dialogosPorSlide = [];
    foreach ($dialogos as $d) {
        $d['acoes'] = $acoesPorDialogo[(int)$d['id']] ?? [];
        $dialogosPorSlide[(int)$d['slide_id']][] = $d;
    }
    foreach ($slides as &$s) {
        $s['dialogos'] = $dialogosPorSlide[(int)$s['id']] ?? [];
    }
    unset($s);

    return $slides;
}

function tr_slide_fundo(array $s, array $ap): string
{
    switch ($s['fundo_tipo']) {
        case 'solido':
            return 'background:' . ($s['fundo_cor'] ?: $ap['cor_fundo']) . ';';
        case 'gradiente':
            return 'background:linear-gradient(' . (int)$s['fundo_angulo'] . 'deg,'
                . ($s['fundo_cor'] ?: '#ffffff') . ',' . ($s['fundo_cor2'] ?: $ap['cor_fundo']) . ');';
        case 'imagem':
            $u = $s['fundo_imagem'] ? tr_url('midia.php?a=' . (int)$s['apresentacao_id'] . '&f=' . rawurlencode($s['fundo_imagem'])) : '';
            return $u !== '' ? "background-image:url('" . $u . "');background-size:cover;background-position:center;" : '';
        default:
            return '';
    }
}

function tr_montar_json(PDO $pdo, array $ap, string $audioBase, string $avatarUrl = ''): array
{
    $slides = tr_estrutura($pdo, (int)$ap['id']);

    $lip = [];
    $comAudio = [];
    foreach ($slides as $s) {
        foreach ($s['dialogos'] as $d) {
            if ($d['voz_modo'] === 'arquivo' && $d['audio_arquivo']) { $comAudio[] = (int)$d['id']; }
        }
    }
    if ($comAudio) {
        $in = implode(',', array_fill(0, count($comAudio), '?'));
        $st = $pdo->prepare("SELECT id, lipsync_json FROM treinamentos_dialogos WHERE id IN ($in)");
        $st->execute($comAudio);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $lip[(int)$r['id']] = $r['lipsync_json'] ? json_decode((string)$r['lipsync_json'], true) : null;
        }
    }

    $saida = [];
    foreach ($slides as $s) {
        $dialogos = [];
        foreach ($s['dialogos'] as $d) {
            $acoes = [];
            $apos  = [];
            foreach ($d['acoes'] as $a) {
                $item = ['tipo' => $a['tipo']];
                foreach (['alvo' => 'alvo', 'valor' => 'valor', 'rotulo' => 'rotulo', 'titulo' => 'titulo'] as $col => $chave) {
                    if ($a[$col] !== null && $a[$col] !== '') { $item[$chave] = $a[$col]; }
                }
                if ($a['html_conteudo'] !== null && $a['html_conteudo'] !== '') { $item['html'] = $a['html_conteudo']; }
                if ($a['tipo'] === 'classe') {
                    $item['classe']  = (string)$a['valor'];
                    $item['remover'] = ((int)$a['remover'] === 1);
                }
                if ($a['fase'] === 'apos') { $apos[] = $item; }
                else { $item['em'] = (float)$a['momento']; $acoes[] = $item; }
            }

            $dlg = [
                'id'         => (int)$d['id'],
                'texto'      => (string)$d['texto'],
                'humor'      => (string)$d['humor'],
                'olhar'      => (string)$d['olhar'],
                'olharForca' => (float)($d['olhar_forca'] ?? 1),
                'troncoGiro' => (string)($d['tronco_giro'] ?? 'auto'),
                'cabecaMov'  => (string)($d['cabeca_mov'] ?? 'auto'),
                'pausa'      => (int)$d['pausa_ms'],
            ];
            if ($d['balao']) { $dlg['balao'] = (string)$d['balao']; }
            if ($d['gesto']) {
                $dlg['gesto'] = (string)$d['gesto'];
                $dlg['gestoDuracao'] = (float)$d['gesto_duracao'];
                $dlg['gestoEspelhado'] = ((int)$d['gesto_espelhado'] === 1);
                $dlg['gestoBraco'] = (string)($d['gesto_braco'] ?? 'auto');
                $dlg['gestoIntensidade'] = (float)($d['gesto_intensidade'] ?? 1);
            }
            if ($d['voz_modo'] === 'arquivo' && $d['audio_arquivo']) {
                $dlg['audio'] = $audioBase . (int)$d['id'];
                if ($d['audio_duracao_ms']) { $dlg['duracao'] = (int)$d['audio_duracao_ms']; }
                if (!empty($lip[(int)$d['id']])) { $dlg['lipsync'] = $lip[(int)$d['id']]; }
            }
            if ($acoes) { $dlg['acoes'] = $acoes; }
            if ($apos)  { $dlg['aposFala'] = $apos; }

            $dialogos[] = $dlg;
        }

        $saida[] = [
            'id'        => 's' . (int)$s['id'],
            'titulo'    => (string)$s['titulo'],
            'transicao' => (string)$s['transicao'],
            'layout'    => (string)$s['layout'],
            'resumo'    => (string)($s['resumo'] ?? ''),
            'estilo'    => tr_slide_fundo($s, $ap),
            'corTexto'  => (string)($s['cor_texto'] ?? ''),
            'escurecer' => (int)$s['fundo_escurecer'],
            'instrutor' => [
                'visivel'      => ((int)$s['instrutor_visivel'] === 1),
                'posicao'      => (string)$s['instrutor_posicao'],
                'escala'       => (float)$s['instrutor_escala'],
                'camera'       => $s['instrutor_camera'] === 'herdar' ? (string)$ap['avatar_camera'] : (string)$s['instrutor_camera'],
                'ladoConteudo' => (string)($s['instrutor_lado_conteudo'] ?? 'auto'),
                'giro'         => (string)($s['instrutor_giro'] ?? 'herdar'),
            ],
            'conteudo'  => (string)($s['conteudo_html'] ?? ''),
            'exigencia' => (string)($s['exigencia'] ?? 'herdar'),
            'dialogos'  => $dialogos,
        ];
    }

    return [
        'id'         => (int)$ap['id'],
        'slug'       => (string)$ap['slug'],
        'titulo'     => (string)$ap['titulo'],
        'subtitulo'  => (string)($ap['subtitulo'] ?? ''),
        'marca'      => (string)$ap['marca'],
        'tema'       => (string)$ap['tema'],
        'cores'      => [
            'primaria' => (string)$ap['cor_primaria'],
            'acento'   => (string)$ap['cor_acento'],
            'fundo'    => (string)$ap['cor_fundo'],
        ],
        'logo'       => $ap['logo_arquivo'] ? tr_url('midia.php?a=' . (int)$ap['id'] . '&f=' . rawurlencode((string)$ap['logo_arquivo'])) : '',
        'avancoAuto' => ((int)$ap['avanco_auto'] === 1),
        'treino'     => [
            'interacao'  => (string)($ap['interacao_obrigatoria'] ?? 'todas'),
            'momento'    => (string)($ap['interacao_momento'] ?? 'narracao'),
            'quizAcerto' => ((int)($ap['quiz_exige_acerto'] ?? 0) === 1),
            'tempoMin'   => (int)($ap['tempo_min_slide'] ?? 0),
            'leitura'    => ((int)($ap['exige_leitura'] ?? 1) === 1),
            'navLivre'   => ((int)($ap['navegacao_livre'] ?? 1) === 1),
        ],
        'avatar'     => (function () use ($pdo, $ap, $avatarUrl) {
            $av = tr_avatar_da_apresentacao($pdo, $ap);
            $tem = tr_avatar_caminho($ap) !== null;
            return [
                'url'        => $tem ? $avatarUrl : '',
                'nome'       => (string)($av['nome']   ?? $ap['avatar_nome']),
                'camera'     => (string)($av['camera'] ?? $ap['avatar_camera']),
                'humor'      => (string)($av['humor']  ?? $ap['avatar_humor']),
                'orientacao' => tr_orientacao_avatar($av),
            ];
        })(),
        'slides'     => $saida,
    ];
}

function tr_dir_midia(int $apresentacaoId): string
{
    $base = nv_config()['paths']['uploads'];
    return rtrim($base, '/\\') . '/treinamentos/' . $apresentacaoId;
}

function tr_garantir_dir(int $apresentacaoId): ?string
{
    $dir = tr_dir_midia($apresentacaoId);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) { return null; }
    return $dir;
}

function tr_logo_empresa_caminho(): string
{
    return dirname(__DIR__, 3) . '/app/layout/logo.png';
}

function tr_enviar_arquivo(string $caminho): void
{
    if (!is_file($caminho)) { http_response_code(404); exit; }
    $ext = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
    $mime = [
        'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
        'vrm' => 'model/gltf-binary', 'glb' => 'model/gltf-binary',
        'pdf' => 'application/pdf',
    ];
    header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($caminho));
    header('Cache-Control: private, max-age=86400');
    readfile($caminho);
    exit;
}

function tr_foto_inscricao_url(int $apresentacaoId, int $inscricaoId): string
{
    return tr_url('matriculas.php') . '?id=' . $apresentacaoId . '&foto=' . $inscricaoId;
}

function tr_foto_colaborador_caminho(?string $fotoPath, ?array &$tentados = null): ?string
{
    $tentados = [];
    $fotoPath = trim(str_replace('\\', '/', (string)$fotoPath));
    if ($fotoPath === '' || strpos($fotoPath, "\0") !== false || preg_match('#^[a-z][a-z0-9+.-]*://#i', $fotoPath)) {
        return null;
    }

    $uploads = '';
    if (function_exists('nv_config')) {
        $uploads = rtrim((string)(nv_config()['paths']['uploads'] ?? ''), '/\\');
    }
    $raizApp = dirname(__DIR__, 3);
    $raizPub = dirname(__DIR__, 2);
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');

    $bases = [];
    foreach ([$uploads, $raizApp, $raizPub, $docRoot] as $b) {
        $rp = $b !== '' ? realpath($b) : false;
        if ($rp !== false && !in_array($rp, $bases, true)) { $bases[] = $rp; }
    }

    $candidatos = [$fotoPath];
    $rel = ltrim($fotoPath, '/');
    foreach ([$uploads, $raizApp, $raizPub, $docRoot] as $b) {
        if ($b !== '') { $candidatos[] = $b . '/' . $rel; }
    }
    if ($uploads !== '') {
        $candidatos[] = $uploads . '/colaboradores/' . basename($rel);
        $candidatos[] = $uploads . '/fotos/' . basename($rel);
    }

    foreach (array_unique($candidatos) as $c) {
        $tentados[] = $c;
        $rp = @realpath($c);
        if ($rp === false || !is_file($rp)) { continue; }

        $ext = strtolower(pathinfo($rp, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) { continue; }

        foreach ($bases as $b) {
            if (strpos($rp, $b . DIRECTORY_SEPARATOR) === 0) { return $rp; }
        }
    }
    return null;
}

function tr_servir_foto_inscricao(PDO $pdo, int $apresentacaoId, int $inscricaoId, bool $diag = false): void
{
    $st = $pdo->prepare(
        'SELECT matricula, colaborador_id FROM treinamentos_inscricoes
          WHERE id = ? AND apresentacao_id = ? LIMIT 1'
    );
    $st->execute([$inscricaoId, $apresentacaoId]);
    $ins = $st->fetch(PDO::FETCH_ASSOC);

    $colab = null;
    if ($ins) {
        if ((int)($ins['colaborador_id'] ?? 0) > 0) {
            $colab = trm_colab($pdo, (int)$ins['colaborador_id']);
        }
        if (!$colab && trim((string)($ins['matricula'] ?? '')) !== '') {
            $colab = trm_colab_por_matricula($pdo, (string)$ins['matricula']);
        }
    }

    $tentados = [];
    $caminho  = $colab ? tr_foto_colaborador_caminho((string)($colab['foto_path'] ?? ''), $tentados) : null;

    if ($diag && function_exists('is_admin_user') && is_admin_user()) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo "inscricao: {$inscricaoId}\n";
        echo 'colaborador: ' . ($colab ? $colab['nome'] : '(não encontrado)') . "\n";
        echo 'foto_path: ' . ($colab ? var_export($colab['foto_path'] ?? null, true) : '-') . "\n";
        echo 'resolvido: ' . ($caminho ?? '(nenhum)') . "\n";
        echo "testados:\n  " . implode("\n  ", $tentados) . "\n";
        exit;
    }

    if ($caminho === null) { http_response_code(404); exit; }
    tr_enviar_arquivo($caminho);
}

function tr_sessao_visualizacao(int $apresentacaoId): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $chave = 'tr_view_' . $apresentacaoId;
    if (empty($_SESSION[$chave])) { $_SESSION[$chave] = bin2hex(random_bytes(16)); }
    return $_SESSION[$chave];
}

function tr_registrar_visualizacao(PDO $pdo, int $apresentacaoId, string $origem, int $slidesTotal): void
{
    $sessao = tr_sessao_visualizacao($apresentacaoId);
    $uid = ($origem === 'interno' && function_exists('current_user_id')) ? (current_user_id() ?: null) : null;
    $st = $pdo->prepare(
        'INSERT INTO treinamentos_visualizacoes
            (apresentacao_id, usuario_id, sessao, origem, slide_max, slides_total, ip, user_agent)
         VALUES (?, ?, ?, ?, 1, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            treinamentos_visualizacoes.slides_total  = VALUES(slides_total),
            treinamentos_visualizacoes.atualizado_em = NOW()'
    );
    $st->execute([
        $apresentacaoId, $uid, $sessao, $origem, $slidesTotal,
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}

function tr_progresso_visualizacao(PDO $pdo, int $apresentacaoId, int $slideAtual, bool $concluido): void
{
    $sessao = tr_sessao_visualizacao($apresentacaoId);
    $st = $pdo->prepare(
        'UPDATE treinamentos_visualizacoes
            SET slide_max = GREATEST(slide_max, ?), concluido = GREATEST(concluido, ?), atualizado_em = NOW()
          WHERE apresentacao_id = ? AND sessao = ? LIMIT 1'
    );
    $st->execute([max(1, $slideAtual), $concluido ? 1 : 0, $apresentacaoId, $sessao]);
}
