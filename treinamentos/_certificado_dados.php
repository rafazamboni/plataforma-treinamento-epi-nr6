<?php
if (!function_exists('tr_url')) { require_once __DIR__ . '/_lib.php'; }
require_once __DIR__ . '/_qrcode.php';

function trc_fontes(): array
{
    return [
        'inter'      => ['rotulo' => 'Inter',              'nota' => 'Sem serifa neutra',
                         'css' => "'Inter',system-ui,-apple-system,'Segoe UI',sans-serif",
                         'google' => 'Inter:wght@400;500;600;700;800'],
        'montserrat' => ['rotulo' => 'Montserrat',         'nota' => 'Sem serifa geométrica',
                         'css' => "'Montserrat',system-ui,sans-serif",
                         'google' => 'Montserrat:wght@400;500;600;700;800'],
        'ibmplex'    => ['rotulo' => 'IBM Plex Sans',      'nota' => 'Sem serifa técnica',
                         'css' => "'IBM Plex Sans',system-ui,sans-serif",
                         'google' => 'IBM+Plex+Sans:wght@400;500;600;700'],
        'playfair'   => ['rotulo' => 'Playfair Display',   'nota' => 'Serifada de alto contraste',
                         'css' => "'Playfair Display',Georgia,serif",
                         'google' => 'Playfair+Display:wght@500;600;700;800'],
        'lora'       => ['rotulo' => 'Lora',               'nota' => 'Serifada legível',
                         'css' => "'Lora',Georgia,serif",
                         'google' => 'Lora:wght@400;500;600;700'],
        'cormorant'  => ['rotulo' => 'Cormorant Garamond', 'nota' => 'Serifada clássica',
                         'css' => "'Cormorant Garamond',Garamond,Georgia,serif",
                         'google' => 'Cormorant+Garamond:wght@500;600;700'],
        'manuscrita' => ['rotulo' => 'Manuscrita',         'nota' => 'Caligráfica — só para o nome',
                         'css' => "'Great Vibes','Segoe Script',cursive",
                         'google' => 'Great+Vibes'],
    ];
}

function trc_fontes_texto(): array
{
    $f = trc_fontes();
    unset($f['manuscrita']);
    return $f;
}

function trc_estilos(): array
{
    return [
        'classico'      => ['Clássico',      'Ornamento discreto e muito respiro no topo'],
        'moderno'       => ['Moderno',       'Traço de acento sob o título'],
        'institucional' => ['Institucional', 'Faixa superior na cor primária'],
        'minimo'        => ['Mínimo',        'Sem ornamento, tipografia leve'],
    ];
}

function trc_bordas(): array
{
    return [
        'dupla'    => ['Dupla',        'Dois filetes concêntricos'],
        'fina'     => ['Fina',         'Um filete discreto'],
        'faixa'    => ['Faixa lateral','Barra colorida à esquerda'],
        'cantos'   => ['Cantos',       'Ornamento apenas nos quatro cantos'],
        'completa' => ['Sólida',       'Moldura cheia na cor primária'],
        'nenhuma'  => ['Sem moldura',  'Folha limpa'],
    ];
}

function trc_fundos(): array
{
    return [
        'branco'    => ['Branco',      'Papel branco'],
        'creme'     => ['Creme',       'Fundo levemente amarelado'],
        'guilhoche' => ['Guilhochê',   'Trama fina de segurança'],
        'degrade'   => ['Degradê',     'Leve variação da cor primária'],
    ];
}

function trc_densidades(): array
{
    return [
        'compacta' => ['Compacta', 'Cabe mais conteúdo'],
        'normal'   => ['Normal',   'Equilíbrio padrão'],
        'ampla'    => ['Ampla',    'Mais respiro entre os blocos'],
    ];
}

function trc_alinhamentos(): array
{
    return ['centro' => 'Centralizado', 'esquerda' => 'À esquerda', 'direita' => 'À direita'];
}

function trc_nome_estilos(): array
{
    return [
        'linha'   => ['Sublinhado',  'Filete de acento sob o nome'],
        'caixa'   => ['Em destaque', 'Fundo suave atrás do nome'],
        'simples' => ['Simples',     'Somente o nome'],
    ];
}

function trc_selos(): array
{
    return [
        'patch-check-fill' => 'Selo conferido',
        'award-fill'       => 'Medalha',
        'shield-check'      => 'Escudo',
        'mortarboard-fill' => 'Formatura',
        'rosette'          => 'Roseta',
        'star-fill'        => 'Estrela',
    ];
}

function trc_qr_posicoes(): array
{
    return [
        'rodape-direita'  => 'Rodapé, à direita',
        'rodape-esquerda' => 'Rodapé, à esquerda',
        'topo-direita'    => 'Topo, à direita',
    ];
}

function trc_verso_modos(): array
{
    return [
        'auto'   => ['Automático', 'Gera a lista a partir dos slides do treinamento'],
        'manual' => ['Manual',     'Usa apenas o texto que você escrever'],
        'ambos'  => ['Os dois',    'Texto próprio primeiro, depois a lista dos slides'],
    ];
}

function trc_cfg(array $ap): array
{
    $v = static function (string $c, $padrao) use ($ap) {
        $x = $ap[$c] ?? null;
        return ($x === null || $x === '') ? $padrao : $x;
    };
    $b = static function (string $c, int $padrao) use ($ap) {
        return (int)($ap[$c] ?? $padrao) === 1;
    };

    $corModo = (string)$v('cert_cor_modo', 'tema');
    $primaria = $corModo === 'propria'
        ? (string)$v('cert_cor_primaria', (string)($ap['cor_primaria'] ?? '#1E3A5F'))
        : (string)($ap['cor_primaria'] ?? '#1E3A5F');
    $acento = $corModo === 'propria'
        ? (string)$v('cert_cor_acento', (string)($ap['cor_acento'] ?? '#1F93A6'))
        : (string)($ap['cor_acento'] ?? '#1F93A6');

    return [
        'estilo'       => (string)$v('cert_estilo', 'classico'),
        'borda'        => (string)$v('cert_borda', 'dupla'),
        'fundo'        => (string)$v('cert_fundo', 'branco'),
        'orientacao'   => (string)$v('cert_orientacao', 'paisagem'),
        'papel'        => (string)$v('cert_papel', 'a4'),
        'densidade'    => (string)$v('cert_densidade', 'normal'),
        'alinhamento'  => (string)$v('cert_alinhamento', 'centro'),
        'cor_modo'     => $corModo,
        'primaria'     => $primaria,
        'acento'       => $acento,
        'tema_primaria' => (string)($ap['cor_primaria'] ?? '#1E3A5F'),
        'tema_acento'   => (string)($ap['cor_acento'] ?? '#1F93A6'),

        'fonte_titulo' => (string)$v('cert_fonte_titulo', 'playfair'),
        'fonte_texto'  => (string)$v('cert_fonte_texto', 'inter'),
        'fonte_nome'   => (string)$v('cert_fonte_nome', 'playfair'),
        'nome_estilo'  => (string)$v('cert_nome_estilo', 'linha'),
        'nome_alta'    => $b('cert_nome_caixa_alta', 0),
        'nome_tamanho' => max(70, min(160, (int)$v('cert_nome_tamanho', 100))),

        'titulo'       => (string)$v('cert_titulo', 'Certificado'),
        'subtitulo'    => (string)$v('cert_subtitulo', 'de conclusão de treinamento'),
        'preambulo'    => (string)$v('cert_preambulo', 'Certificamos que'),
        'empresa'      => (string)$v('cert_empresa', ''),
        'frase'        => (string)($ap['cert_texto'] ?? ''),
        'rodape'       => (string)($ap['cert_rodape'] ?? ''),
        'cidade'       => (string)($ap['cert_cidade'] ?? ''),
        'data_extenso' => $b('cert_data_extenso', 1),
        'local_data'   => $b('cert_mostra_local_data', 1),

        'assina_nome'   => (string)$v('cert_assina_nome', 'Recursos Humanos'),
        'assina_cargo'  => (string)($ap['cert_assina_cargo'] ?? ''),
        'assina2_nome'  => (string)($ap['cert_assina2_nome'] ?? ''),
        'assina2_cargo' => (string)($ap['cert_assina2_cargo'] ?? ''),

        'selo'        => $b('cert_mostra_selo', 1),
        'selo_texto'  => (string)$v('cert_selo_texto', 'Concluído'),
        'selo_icone'  => (string)$v('cert_selo_icone', 'patch-check-fill'),
        'selo_tamanho' => max(50, min(200, (int)$v('cert_selo_tamanho', 100))),

        'logo_tamanho'    => max(50, min(200, (int)$v('cert_logo_tamanho', 100))),
        'empresa_tamanho' => max(50, min(200, (int)$v('cert_empresa_tamanho', 100))),
        'titulos_tamanho' => max(70, min(160, (int)$v('cert_titulos_tamanho', 100))),

        'logo'         => $b('cert_mostra_logo', 1),
        'marca_dagua'  => $b('cert_marca_dagua', 0),
        'marca_tamanho'   => max(4, min(24, (int)$v('cert_marca_tamanho', 9))),
        'marca_espaco_h'  => max(2, min(30, (int)$v('cert_marca_espaco_h', 7))),
        'marca_espaco_v'  => max(2, min(30, (int)$v('cert_marca_espaco_v', 9))),
        'marca_opacidade' => max(1, min(30, (int)$v('cert_marca_opacidade', 4))),
        'codigo'       => $b('cert_mostra_codigo', 1),
        'cpf'          => $b('cert_mostra_cpf', 1),
        'carga'        => $b('cert_mostra_carga', 1),
        'tempo'        => $b('cert_mostra_tempo', 1),
        'slides'       => $b('cert_mostra_slides', 1),
        'nota'         => $b('cert_mostra_nota', 1),

        'qr'           => $b('cert_mostra_qr', 1),
        'qr_tamanho'   => max(14, min(40, (int)$v('cert_qr_tamanho', 24))),
        'qr_posicao'   => (string)$v('cert_qr_posicao', 'rodape-direita'),
        'qr_legenda'   => (string)($ap['cert_qr_legenda'] ?? 'Validar autenticidade'),
        'qr_nivel'     => (string)$v('cert_qr_nivel', 'Q'),

        'verso'          => $b('cert_verso', 0),
        'verso_titulo'   => (string)$v('cert_verso_titulo', 'Conteúdo programático'),
        'verso_intro'    => (string)($ap['cert_verso_intro'] ?? ''),
        'verso_modo'     => (string)$v('cert_verso_modo', 'auto'),
        'verso_texto'    => (string)($ap['cert_verso_texto'] ?? ''),
        'verso_colunas'  => (int)$v('cert_verso_colunas', 2) === 1 ? 1 : 2,
        'verso_carga'    => $b('cert_verso_mostra_carga', 1),
        'verso_prova'    => $b('cert_verso_mostra_prova', 1),
        'verso_numera'   => $b('cert_verso_numeracao', 1),
        'verso_rodape'   => (string)($ap['cert_verso_rodape'] ?? ''),
    ];
}

function trc_cfg_do_form(array $cfg, array $in): array
{
    $enum = static function ($valor, array $validos, string $padrao): string {
        $v = (string)$valor;
        return in_array($v, $validos, true) ? $v : $padrao;
    };
    $chaves = static function (array $lista): array { return array_keys($lista); };

    $mapaEnum = [
        'estilo'       => $chaves(trc_estilos()),
        'borda'        => $chaves(trc_bordas()),
        'fundo'        => $chaves(trc_fundos()),
        'orientacao'   => ['paisagem', 'retrato'],
        'papel'        => ['a4', 'carta'],
        'densidade'    => $chaves(trc_densidades()),
        'alinhamento'  => $chaves(trc_alinhamentos()),
        'cor_modo'     => ['tema', 'propria'],
        'fonte_titulo' => $chaves(trc_fontes_texto()),
        'fonte_texto'  => $chaves(trc_fontes_texto()),
        'fonte_nome'   => $chaves(trc_fontes()),
        'nome_estilo'  => $chaves(trc_nome_estilos()),
        'selo_icone'   => $chaves(trc_selos()),
        'qr_posicao'   => $chaves(trc_qr_posicoes()),
        'qr_nivel'     => ['M', 'Q', 'H'],
        'verso_modo'   => $chaves(trc_verso_modos()),
    ];
    foreach ($mapaEnum as $k => $validos) {
        if (isset($in[$k])) { $cfg[$k] = $enum($in[$k], $validos, (string)$cfg[$k]); }
    }

    foreach (['titulo', 'subtitulo', 'preambulo', 'empresa', 'frase', 'rodape', 'cidade',
              'assina_nome', 'assina_cargo', 'assina2_nome', 'assina2_cargo',
              'selo_texto', 'qr_legenda', 'verso_titulo', 'verso_intro', 'verso_texto',
              'verso_rodape'] as $k) {
        if (isset($in[$k])) { $cfg[$k] = trim((string)$in[$k]); }
    }

    foreach (['data_extenso', 'local_data', 'selo', 'logo', 'marca_dagua', 'codigo', 'cpf',
              'carga', 'tempo', 'slides', 'nota', 'qr', 'verso', 'verso_carga',
              'verso_prova', 'verso_numera', 'nome_alta'] as $k) {
        if (isset($in[$k])) { $cfg[$k] = ((int)$in[$k] === 1); }
    }

    if (isset($in['nome_tamanho'])) { $cfg['nome_tamanho'] = max(70, min(160, (int)$in['nome_tamanho'])); }
    if (isset($in['qr_tamanho']))   { $cfg['qr_tamanho']   = max(14, min(40, (int)$in['qr_tamanho'])); }
    if (isset($in['verso_colunas'])){ $cfg['verso_colunas']= ((int)$in['verso_colunas'] === 1) ? 1 : 2; }
    if (isset($in['selo_tamanho']))    { $cfg['selo_tamanho']    = max(50, min(200, (int)$in['selo_tamanho'])); }
    if (isset($in['logo_tamanho']))    { $cfg['logo_tamanho']    = max(50, min(200, (int)$in['logo_tamanho'])); }
    if (isset($in['empresa_tamanho'])) { $cfg['empresa_tamanho'] = max(50, min(200, (int)$in['empresa_tamanho'])); }
    if (isset($in['titulos_tamanho'])) { $cfg['titulos_tamanho'] = max(70, min(160, (int)$in['titulos_tamanho'])); }
    if (isset($in['marca_tamanho']))   { $cfg['marca_tamanho']   = max(4, min(24, (int)$in['marca_tamanho'])); }
    if (isset($in['marca_espaco_h']))  { $cfg['marca_espaco_h']  = max(2, min(30, (int)$in['marca_espaco_h'])); }
    if (isset($in['marca_espaco_v']))  { $cfg['marca_espaco_v']  = max(2, min(30, (int)$in['marca_espaco_v'])); }
    if (isset($in['marca_opacidade'])) { $cfg['marca_opacidade'] = max(1, min(30, (int)$in['marca_opacidade'])); }

    foreach (['primaria', 'acento'] as $k) {
        if (isset($in[$k]) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string)$in[$k])) {
            $cfg[$k] = strtoupper((string)$in[$k]);
        }
    }

    if ($cfg['cor_modo'] === 'tema') {
        $cfg['primaria'] = $cfg['tema_primaria'];
        $cfg['acento']   = $cfg['tema_acento'];
    }

    return $cfg;
}

function trc_classes(array $cfg, bool $verso = false): string
{
    return 'cert-folha'
        . ($verso ? ' cert-folha-verso' : '')
        . ' estilo-' . $cfg['estilo']
        . ' borda-' . $cfg['borda']
        . ' fundo-' . $cfg['fundo']
        . ' orient-' . $cfg['orientacao']
        . ' papel-' . $cfg['papel']
        . ' dens-' . $cfg['densidade']
        . ' al-' . $cfg['alinhamento']
        . ' nome-' . $cfg['nome_estilo']
        . ' qr-' . $cfg['qr_posicao']
        . ($cfg['verso_colunas'] === 2 ? ' verso-2col' : ' verso-1col');
}

function trc_vars(array $cfg): string
{
    $f = trc_fontes();
    $ff = static function (string $chave) use ($f): string {
        return $f[$chave]['css'] ?? $f['inter']['css'];
    };
    return '--cert-primaria:' . $cfg['primaria'] . ';'
         . '--cert-acento:' . $cfg['acento'] . ';'
         . '--cert-f-titulo:' . $ff($cfg['fonte_titulo']) . ';'
         . '--cert-f-texto:' . $ff($cfg['fonte_texto']) . ';'
         . '--cert-f-nome:' . $ff($cfg['fonte_nome']) . ';'
         . '--cert-nome-escala:' . ($cfg['nome_tamanho'] / 100) . ';'
         . '--cert-logo-escala:' . ($cfg['logo_tamanho'] / 100) . ';'
         . '--cert-empresa-escala:' . ($cfg['empresa_tamanho'] / 100) . ';'
         . '--cert-selo-escala:' . ($cfg['selo_tamanho'] / 100) . ';'
         . '--cert-titulos-escala:' . ($cfg['titulos_tamanho'] / 100) . ';'
         . '--cert-qr:' . round($cfg['qr_tamanho'] / trc_largura_util($cfg) * 100, 3) . 'cqw;';
}

function trc_largura_util(array $cfg): float
{
    $largura = $cfg['papel'] === 'carta'
        ? ($cfg['orientacao'] === 'retrato' ? 215.9 : 279.4)
        : ($cfg['orientacao'] === 'retrato' ? 210.0 : 297.0);
    return max(80.0, $largura);
}

function trc_papel_mm(array $cfg): array
{
    if ($cfg['papel'] === 'carta') {
        return $cfg['orientacao'] === 'retrato' ? [215.9, 279.4] : [279.4, 215.9];
    }
    return $cfg['orientacao'] === 'retrato' ? [210.0, 297.0] : [297.0, 210.0];
}

function trc_marca_dagua(array $cfg, string $logoUrl): string
{
    $tam  = (float)$cfg['marca_tamanho'];
    $gapH = (float)$cfg['marca_espaco_h'];
    $gapV = (float)$cfg['marca_espaco_v'];
    $op   = max(1, min(30, (int)$cfg['marca_opacidade'])) / 100;

    [$larguraMm, $alturaMm] = trc_papel_mm($cfg);
    $razao = $larguraMm / $alturaMm;

    $passoH = $tam + $gapH;
    $passoVAltura = ($tam / $razao) + $gapV;

    $cols = min(14, (int)ceil(100 / $passoH) + 2);
    $rows = min(14, (int)ceil(100 / $passoVAltura) + 2);

    $h = '<div class="cert-marca" aria-hidden="true" style="opacity:' . round($op, 3) . '">';
    for ($lin = -1; $lin <= $rows; $lin++) {
        $desloc = ($lin % 2 !== 0) ? $passoH / 2 : 0;
        for ($col = -1; $col <= $cols; $col++) {
            $x = $col * $passoH + $desloc;
            $y = $lin * $passoVAltura;
            if ($x <= -$tam || $x >= 100 + $tam || $y <= -$tam || $y >= 100 + $tam) { continue; }
            $h .= '<img src="' . trc_e($logoUrl) . '" alt="" style="left:' . round($x, 2)
                . '%;top:' . round($y, 2) . '%;width:' . round($tam, 2) . '%">';
        }
    }
    return $h . '</div>';
}

function trc_fontes_url(array $chaves): string
{
    $f = trc_fontes();
    $nomes = [];
    foreach (array_unique($chaves) as $c) {
        if (!isset($f[$c])) { continue; }
        $nomes[] = str_replace('+', ' ', explode(':', (string)$f[$c]['google'])[0]);
    }
    if (!$nomes) { return ''; }
    return nv_url('/assets/vendor/fonts/css.php?f=' . rawurlencode(implode(',', array_unique($nomes))));
}

function trc_page_css(array $cfg): string
{
    $tam = $cfg['papel'] === 'carta' ? 'Letter' : 'A4';
    $ori = $cfg['orientacao'] === 'retrato' ? 'portrait' : 'landscape';
    return '@page{size:' . $tam . ' ' . $ori . ';margin:0}';
}

function trc_data_extenso(string $iso): string
{
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
              'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $t = strtotime($iso);
    if (!$t) { return ''; }
    return (int)date('j', $t) . ' de ' . $meses[(int)date('n', $t) - 1] . ' de ' . date('Y', $t);
}

function trc_conteudo_slides(PDO $pdo, int $apresentacaoId): array
{
    $st = $pdo->prepare(
        'SELECT titulo, resumo FROM treinamentos_slides
          WHERE apresentacao_id = ? ORDER BY ordem, id'
    );
    $st->execute([$apresentacaoId]);

    $itens = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $titulo = trim((string)$s['titulo']);
        if ($titulo === '') { continue; }
        $itens[] = ['t' => $titulo, 'd' => trim((string)$s['resumo']), 'secao' => false];
    }
    return $itens;
}

function trc_conteudo_texto(string $texto): array
{
    $itens = [];
    foreach (preg_split('/\r\n|\r|\n/', $texto) as $linha) {
        $linha = trim($linha);
        if ($linha === '') { continue; }
        if (strpos($linha, '#') === 0) {
            $itens[] = ['t' => trim(substr($linha, 1)), 'd' => '', 'secao' => true];
            continue;
        }
        $partes = explode('|', $linha, 2);
        $itens[] = [
            't'     => trim($partes[0]),
            'd'     => isset($partes[1]) ? trim($partes[1]) : '',
            'secao' => false,
        ];
    }
    return $itens;
}

function trc_verso_itens(array $cfg, PDO $pdo, int $apresentacaoId): array
{
    $manual = trc_conteudo_texto((string)$cfg['verso_texto']);
    $auto   = trc_conteudo_slides($pdo, $apresentacaoId);

    if ($cfg['verso_modo'] === 'manual') { return $manual; }
    if ($cfg['verso_modo'] === 'auto')   { return $auto; }
    return array_merge($manual, $auto);
}

function trc_dados(array $insc, array $ap, array $cfg): array
{
    $temProva = (int)($ap['prova_ativa'] ?? 0) === 1 && (int)($insc['prova_aprovado'] ?? 0) === 1;
    $codigo   = (string)($insc['certificado_codigo'] ?? '');

    return [
        'nome'        => (string)$insc['nome'],
        'cpf'         => (string)($insc['cpf'] ?? ''),
        'cargo'       => (string)($insc['cargo'] ?? ''),
        'filial'      => (string)($insc['filial'] ?? ''),
        'curso'       => (string)$ap['titulo'],
        'curso_sub'   => (string)($ap['subtitulo'] ?? ''),
        'data'        => date('d/m/Y', strtotime((string)$insc['concluido_em'])),
        'data_ext'    => trc_data_extenso((string)$insc['concluido_em']),
        'hora'        => date('H:i', strtotime((string)$insc['concluido_em'])),
        'carga'       => (int)($ap['carga_horaria_min'] ?? 0),
        'tempo'       => (int)($insc['tempo_seg'] ?? 0),
        'slides'      => (int)($insc['slides_total'] ?? 0),
        'nota'        => (int)($insc['prova_percentual'] ?? 0),
        'nota_minima' => (int)($ap['prova_nota_minima'] ?? 0),
        'tem_prova'   => $temProva,
        'codigo'      => $codigo,
        'url'         => $codigo !== '' ? trc_url_validacao($codigo) : '',
        'logo'        => trc_logo_url(),
    ];
}

function trc_dados_exemplo(array $ap, int $qtdSlides): array
{
    $codigo = 'NV-' . date('Y') . '-' . str_pad((string)(int)$ap['id'], 3, '0', STR_PAD_LEFT) . '-A3F91B';
    return [
        'nome'        => 'Maria da Silva Souza',
        'cpf'         => '12345678900',
        'cargo'       => 'Consultora de Vendas',
        'filial'      => 'Matriz',
        'curso'       => (string)$ap['titulo'],
        'curso_sub'   => (string)($ap['subtitulo'] ?? ''),
        'data'        => date('d/m/Y'),
        'data_ext'    => trc_data_extenso(date('Y-m-d')),
        'hora'        => '10:32',
        'carga'       => (int)($ap['carga_horaria_min'] ?? 0),
        'tempo'       => 2520,
        'slides'      => $qtdSlides,
        'nota'        => 90,
        'nota_minima' => (int)($ap['prova_nota_minima'] ?? 70),
        'tem_prova'   => (int)($ap['prova_ativa'] ?? 0) === 1,
        'codigo'      => $codigo,
        'url'         => trc_url_validacao($codigo),
        'logo'        => trc_logo_url(),
    ];
}

function trc_logo_url(): string
{
    return is_file(tr_logo_empresa_caminho()) ? tr_url('midia.php?marca=1') : '';
}

function trc_url_curta(): string
{
    return preg_replace('#^https?://#', '', tr_url_absoluta('validar.php')) ?? '';
}

function trc_url_validacao(string $codigo): string
{
    return tr_url_absoluta('validar.php') . '?c=' . rawurlencode($codigo);
}

function trc_frase(array $cfg, array $d): string
{
    $frase = trim((string)$cfg['frase']);
    if ($frase === '') { $frase = 'concluiu integralmente o treinamento'; }

    return strtr($frase, [
        '{nome}'        => $d['nome'],
        '{treinamento}' => $d['curso'],
        '{data}'        => $d['data'],
        '{carga}'       => $d['carga'] > 0 ? trc_carga_fmt($d['carga']) : '',
        '{empresa}'     => (string)$cfg['empresa'],
        '{cpf}'         => $d['cpf'] !== '' ? nv_format_cpf($d['cpf']) : '',
    ]);
}

function trc_carga_fmt(int $min): string
{
    if ($min <= 0) { return '—'; }
    if ($min < 60) { return $min . ' min'; }
    $h = intdiv($min, 60);
    $m = $min % 60;
    return $m === 0 ? $h . 'h' : $h . 'h' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
}

function trc_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function trc_reguas(array $cfg, array $d): array
{
    $r = [];
    $r[] = ['b' => $d['data'], 's' => 'Data de conclusão'];
    if ($cfg['carga'] && $d['carga'] > 0)  { $r[] = ['b' => trc_carga_fmt($d['carga']), 's' => 'Carga horária']; }
    if ($cfg['tempo'] && $d['tempo'] > 0)  { $r[] = ['b' => trm_tempo_fmt($d['tempo']), 's' => 'Tempo dedicado']; }
    if ($cfg['slides'] && $d['slides'] > 0){ $r[] = ['b' => (string)$d['slides'], 's' => 'Slides concluídos']; }
    if ($cfg['nota'] && $d['tem_prova'])   { $r[] = ['b' => $d['nota'] . '%', 's' => 'Aproveitamento']; }
    return $r;
}

function trc_selo_svg(string $chave): string
{
    $svg = static function (string $interior): string {
        return '<svg class="cert-selo-svg" viewBox="0 0 24 24" role="img" aria-hidden="true" '
             . 'fill="none" stroke="currentColor" stroke-width="1.6" '
             . 'stroke-linecap="round" stroke-linejoin="round">' . $interior . '</svg>';
    };
    $check = '<path d="M7.9 12.2l2.8 2.8 5.4-5.8" stroke-width="2"/>';

    switch ($chave) {
        case 'award-fill':
            return $svg('<path d="M8.6 9.1L5.4 2.6h4.2L12 7.2l2.4-4.6h4.2l-3.2 6.5"/>'
                      . '<circle cx="12" cy="15.4" r="6"/>'
                      . '<path d="M9.6 15.6l1.8 1.8 3.4-3.8" stroke-width="1.8"/>');

        case 'shield-check':
            return $svg('<path d="M12 2.6l7.2 2.6v5.9c0 4.4-3 8.2-7.2 9.6-4.2-1.4-7.2-5.2-7.2-9.6V5.2z"/>'
                      . '<path d="M8.9 12.1l2.2 2.2 4-4.4" stroke-width="1.8"/>');

        case 'mortarboard-fill':
            return $svg('<path d="M12 3.1L22.2 8 12 12.9 1.8 8z"/>'
                      . '<path d="M6.3 10.5v4.6c0 1.8 2.6 3.2 5.7 3.2s5.7-1.4 5.7-3.2v-4.6"/>'
                      . '<path d="M21 9v5"/>');

        case 'rosette':
            return $svg(trc_estrela_path(12, 10.6, 8) . '<circle cx="12" cy="12" r="5.6"/>' . $check);

        case 'star-fill':
            return $svg(trc_estrela_path(5, 10.4, 4.5));

        case 'patch-check-fill':
        default:
            return $svg(trc_estrela_path(8, 10.8, 8.6) . '<circle cx="12" cy="12" r="7.2"/>' . $check);
    }
}

function trc_estrela_path(int $pontas, float $raioExterno, float $raioInterno): string
{
    $pontos = [];
    $total  = $pontas * 2;
    for ($i = 0; $i < $total; $i++) {
        $r = ($i % 2 === 0) ? $raioExterno : $raioInterno;
        $a = -M_PI / 2 + $i * M_PI / $pontas;
        $pontos[] = round(12 + $r * cos($a), 2) . ',' . round(12 + $r * sin($a), 2);
    }
    return '<polygon points="' . implode(' ', $pontos) . '"/>';
}

function trc_qr_bloco(array $cfg, array $d): string
{
    if (!$cfg['qr'] || $d['url'] === '') { return ''; }

    $svg = tr_qr_svg($d['url'], [
        'nivel'  => (string)$cfg['qr_nivel'],
        'margem' => 2,
        'cor'    => '#101820',
        'fundo'  => '#ffffff',
        'rotulo' => 'QR Code para validar o certificado ' . $d['codigo'],
    ]);
    if ($svg === '') { return ''; }

    $legenda = trim((string)$cfg['qr_legenda']);
    return '<div class="cert-qr">' . $svg
         . ($legenda !== '' ? '<small>' . trc_e($legenda) . '</small>' : '')
         . '</div>';
}

function trc_folha(array $cfg, array $d, bool $ids = false): string
{
    $id = static function (string $nome) use ($ids): string {
        return $ids ? ' id="pv' . $nome . '"' : '';
    };

    $h  = '<div class="' . trc_classes($cfg) . '" style="' . trc_e(trc_vars($cfg)) . '">';

    if ($cfg['marca_dagua'] && $d['logo'] !== '') {
        $h .= trc_marca_dagua($cfg, $d['logo']);
    }
    if ($cfg['fundo'] === 'guilhoche') {
        $h .= '<div class="cert-trama" aria-hidden="true"></div>';
    }
    if ($cfg['estilo'] === 'institucional') {
        $h .= '<div class="cert-faixa-topo" aria-hidden="true"></div>';
    }
    if ($cfg['borda'] === 'cantos') {
        $h .= '<div class="cert-cantos" aria-hidden="true"><i></i><i></i><i></i><i></i></div>';
    }
    if ($cfg['qr_posicao'] === 'topo-direita') {
        $h .= '<div class="cert-qr-flutuante">' . trc_qr_bloco($cfg, $d) . '</div>';
    }

    $h .= '<div class="cert-corpo">';

    $temTopo = ($cfg['logo'] && $d['logo'] !== '') || trim((string)$cfg['empresa']) !== '';
    if ($temTopo) {
        $h .= '<header class="cert-topo"' . $id('Topo') . '>';
        if ($cfg['logo'] && $d['logo'] !== '') {
            $h .= '<img src="' . trc_e($d['logo']) . '" alt=""' . $id('Logo') . '>';
        }
        if (trim((string)$cfg['empresa']) !== '') {
            $h .= '<span' . $id('Empresa') . '>' . trc_e($cfg['empresa']) . '</span>';
        }
        $h .= '</header>';
    }

    $h .= '<h1 class="cert-titulo"' . $id('Titulo') . '>' . trc_e($cfg['titulo']) . '</h1>';
    if (trim((string)$cfg['subtitulo']) !== '') {
        $h .= '<p class="cert-subtitulo"' . $id('Subtitulo') . '>' . trc_e($cfg['subtitulo']) . '</p>';
    }

    if (trim((string)$cfg['preambulo']) !== '') {
        $h .= '<p class="cert-texto"' . $id('Preambulo') . '>' . trc_e($cfg['preambulo']) . '</p>';
    }

    $nome = $cfg['nome_alta'] ? mb_strtoupper($d['nome'], 'UTF-8') : $d['nome'];
    $h .= '<div class="cert-nome"' . $id('Nome') . '>' . trc_e($nome) . '</div>';

    if ($cfg['cpf']) {
        $extra = trim(implode(' · ', array_filter([$d['cargo'], $d['filial']])));
        $cpf   = $d['cpf'] !== '' ? 'CPF ' . nv_format_cpf($d['cpf']) : '';
        $linha = trim(implode(' — ', array_filter([$cpf, $extra])));
        if ($linha !== '') {
            $h .= '<p class="cert-cpf"' . $id('Cpf') . '>' . trc_e($linha) . '</p>';
        }
    }

    $h .= '<p class="cert-texto cert-frase"' . $id('Frase') . '>' . trc_e(trc_frase($cfg, $d)) . '</p>';
    $h .= '<h2 class="cert-curso">' . trc_e($d['curso']) . '</h2>';
    if ($d['curso_sub'] !== '') {
        $h .= '<p class="cert-curso-sub">' . trc_e($d['curso_sub']) . '</p>';
    }

    $reguas = trc_reguas($cfg, $d);
    if ($reguas) {
        $h .= '<div class="cert-dados"' . $id('Dados') . '>';
        foreach ($reguas as $r) {
            $h .= '<div class="cert-dado"><b>' . trc_e($r['b']) . '</b><small>'
                . trc_e($r['s']) . '</small></div>';
        }
        $h .= '</div>';
    }

    if (trim((string)$cfg['rodape']) !== '') {
        $h .= '<p class="cert-rodape-txt"' . $id('Rodape') . '>' . nl2br(trc_e($cfg['rodape'])) . '</p>';
    }

    if ($cfg['local_data']) {
        $quando = $cfg['data_extenso'] ? $d['data_ext'] : $d['data'];
        $texto  = trim((string)$cfg['cidade']) !== ''
            ? trim((string)$cfg['cidade']) . ', ' . $quando
            : $quando;
        $h .= '<p class="cert-local-data"' . $id('LocalData') . '>' . trc_e($texto) . '</p>';
    }

    $h .= '<footer class="cert-pe">';

    if (trim((string)$cfg['assina2_nome']) !== '') {
        $h .= '<div class="cert-assinaturas">';
        $h .= '<div class="cert-assinatura"><hr><b>' . trc_e($cfg['assina2_nome'])
            . '</b><span>' . trc_e($cfg['assina2_cargo']) . '</span></div>';
        $h .= '</div>';
    }

    if ($cfg['selo']) {
        $h .= '<div class="cert-selo cert-selo-grande"' . $id('Selo') . '>'
            . trc_selo_svg((string)$cfg['selo_icone']);
        if (trim((string)$cfg['selo_texto']) !== '') {
            $h .= '<span>' . trc_e($cfg['selo_texto']) . '</span>';
        }
        $h .= '</div>';
    }

    $direita = '';
    if ($cfg['codigo'] && $d['codigo'] !== '') {
        $direita .= '<div class="cert-validacao"' . $id('Validacao') . '>';
        $direita .= 'Emitido eletronicamente pela Nórdica Veículos em ' . trc_e($d['data'])
                 . ' às ' . trc_e($d['hora']) . '.<br>';
        if ($d['tem_prova'] && $cfg['nota']) {
            $direita .= 'Avaliação aprovada com ' . (int)$d['nota'] . '% (mínimo '
                     . (int)$d['nota_minima'] . '%).<br>';
        }
        $direita .= 'Código: <b>' . trc_e($d['codigo']) . '</b>';
        if (!$cfg['qr']) {
            $direita .= '<br>Confira em ' . trc_e(trc_url_curta());
        }
        $direita .= '</div>';
    }
    if ($cfg['qr_posicao'] !== 'topo-direita') {
        $direita .= trc_qr_bloco($cfg, $d);
    }
    if ($direita !== '') {
        $h .= '<div class="cert-pe-direita">' . $direita . '</div>';
    }

    $h .= '</footer></div></div>';
    return $h;
}

function trc_verso(array $cfg, array $d, array $itens, bool $ids = false): string
{
    $id = static function (string $nome) use ($ids): string {
        return $ids ? ' id="pv' . $nome . '"' : '';
    };

    $h  = '<div class="' . trc_classes($cfg, true) . '" style="' . trc_e(trc_vars($cfg)) . '">';
    if ($cfg['fundo'] === 'guilhoche') {
        $h .= '<div class="cert-trama" aria-hidden="true"></div>';
    }
    if ($cfg['borda'] === 'cantos') {
        $h .= '<div class="cert-cantos" aria-hidden="true"><i></i><i></i><i></i><i></i></div>';
    }

    $h .= '<div class="cert-corpo">';

    $h .= '<header class="cert-verso-topo">';
    if ($cfg['logo'] && $d['logo'] !== '') {
        $h .= '<img src="' . trc_e($d['logo']) . '" alt="">';
    }
    $h .= '<div><h1' . $id('VersoTitulo') . '>' . trc_e($cfg['verso_titulo']) . '</h1>';
    $h .= '<p>' . trc_e($d['curso']) . '</p></div>';
    $h .= '</header>';

    $meta = [];
    if ($cfg['verso_carga'] && $d['carga'] > 0) {
        $meta[] = ['b' => trc_carga_fmt($d['carga']), 's' => 'Carga horária'];
    }
    $qtd = 0;
    foreach ($itens as $i) { if (empty($i['secao'])) { $qtd++; } }
    if ($qtd > 0) { $meta[] = ['b' => (string)$qtd, 's' => 'Tópicos']; }
    if ($cfg['verso_prova'] && $d['tem_prova']) {
        $meta[] = ['b' => $d['nota_minima'] . '%', 's' => 'Nota mínima'];
        $meta[] = ['b' => $d['nota'] . '%', 's' => 'Aproveitamento'];
    }
    if ($meta) {
        $h .= '<div class="cert-verso-meta">';
        foreach ($meta as $m) {
            $h .= '<div><b>' . trc_e($m['b']) . '</b><small>' . trc_e($m['s']) . '</small></div>';
        }
        $h .= '</div>';
    }

    if (trim((string)$cfg['verso_intro']) !== '') {
        $h .= '<p class="cert-verso-intro"' . $id('VersoIntro') . '>'
            . nl2br(trc_e($cfg['verso_intro'])) . '</p>';
    }

    if ($itens) {
        $h .= '<div class="cert-verso-lista"' . $id('VersoLista') . '>';
        $n = 0;
        foreach ($itens as $i) {
            if (!empty($i['secao'])) {
                $h .= '<div class="cert-verso-secao">' . trc_e($i['t']) . '</div>';
                continue;
            }
            $n++;
            $h .= '<div class="cert-verso-item">';
            if ($cfg['verso_numera']) { $h .= '<em>' . $n . '</em>'; }
            $h .= '<div><b>' . trc_e($i['t']) . '</b>';
            if ($i['d'] !== '') { $h .= '<small>' . trc_e($i['d']) . '</small>'; }
            $h .= '</div></div>';
        }
        $h .= '</div>';
    } else {
        $h .= '<p class="cert-verso-vazio">Nenhum tópico cadastrado para este treinamento.</p>';
    }

    $h .= '<footer class="cert-verso-pe">';
    if (trim((string)$cfg['verso_rodape']) !== '') {
        $h .= '<p' . $id('VersoRodape') . '>' . nl2br(trc_e($cfg['verso_rodape'])) . '</p>';
    }
    if ($d['codigo'] !== '') {
        $h .= '<span>Certificado <b>' . trc_e($d['codigo']) . '</b> · '
            . trc_e(trc_url_curta()) . '</span>';
    }
    $h .= '</footer>';

    $h .= '</div></div>';
    return $h;
}
