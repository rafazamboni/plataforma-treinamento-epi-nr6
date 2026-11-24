<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_prova.php';
require_once __DIR__ . '/_docx.php';

require_login();
tr_exigir('treinamentos.apresentacoes.exportar');

$pdo  = db();
$id   = (int)($_GET['id'] ?? 0);
$modo = ($_GET['modo'] ?? 'completo') === 'simples' ? 'simples' : 'completo';
$ap   = $id > 0 ? tr_apresentacao($pdo, $id) : null;

if (!$ap) {
    flash_set('danger', 'Apresentação não encontrada.');
    nv_redirect(tr_url('index.php'));
}

$slides   = tr_estrutura($pdo, $id);
$questoes = trp_questoes($pdo, $id, false);

$layouts    = tr_layouts();
$transicoes = tr_transicoes();
$gestos     = tr_gestos();
$humores    = tr_humores();
$olhares    = tr_olhares();
$cameras    = tr_cameras();
$posicoes   = tr_posicoes();
$tiposAcao  = tr_tipos_acao();

function trx_texto(string $html): string
{
    $t = preg_replace('~<(br|/p|/h[1-6]|/li|/div)\s*/?>~i', "\n", $html);
    $t = preg_replace('~<li[^>]*>~i', '• ', (string)$t);
    $t = strip_tags((string)$t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('~[ \t]+~', ' ', $t);
    $t = preg_replace('~\n{3,}~', "\n\n", (string)$t);
    return trim((string)$t);
}

function trx_elementos(string $conteudoHtml, bool $tecnico = true): array
{
    if (!preg_match('~data-tr-canvas>(.*?)</script>~s', $conteudoHtml, $m)) {
        $t = trx_texto($conteudoHtml);
        return $t === '' ? [] : [['id' => '—', 'tipo' => 'Texto', 'conteudo' => $t, 'oculto' => false]];
    }

    $dados = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
    if (!is_array($dados) || empty($dados['els'])) { return []; }

    $saida = [];
    foreach ($dados['els'] as $el) {
        $tipo = $el['t'] ?? 'texto';
        $item = [
            'id'     => (string)($el['id'] ?? '—'),
            'oculto' => !empty($el['oculto']),
            'tipo'   => 'Texto',
            'conteudo' => '',
        ];

        if ($tipo === 'bloco') {
            $item['tipo'] = 'Bloco: ' . trx_nome_bloco((string)($el['bloco'] ?? ''));
            $item['conteudo'] = trx_bloco((string)($el['bloco'] ?? ''), $el['dados'] ?? [], $tecnico);
        } elseif ($tipo === 'imagem') {
            $item['tipo'] = 'Imagem';
            $item['conteudo'] = trim(($el['alt'] ?? '') . "\n" . ($el['src'] ?? ''));
        } elseif ($tipo === 'forma') {
            $item['tipo'] = 'Forma';
            $item['conteudo'] = '(elemento decorativo)';
        } else {
            $item['conteudo'] = trx_texto((string)($el['html'] ?? ''));
        }

        if ($item['conteudo'] !== '') { $saida[] = $item; }
    }
    return $saida;
}

function trx_nome_bloco(string $chave): string
{
    $nomes = [
        'quiz' => 'Pergunta rápida', 'revelar' => 'Clique e revele', 'abas' => 'Abas',
        'passos' => 'Etapas', 'checklist' => 'Checklist', 'comparativo' => 'Certo × errado',
        'estatisticas' => 'Indicadores', 'alerta' => 'Aviso', 'citacao' => 'Citação',
        'linhaTempo' => 'Linha do tempo', 'tabela' => 'Tabela', 'botao' => 'Botão',
    ];
    return $nomes[$chave] ?? ($chave !== '' ? $chave : 'bloco');
}

function trx_bloco(string $tipo, array $d, bool $tecnico = true): string
{
    $l = [];

    if (!empty($d['titulo']))   { $l[] = $d['titulo']; }
    if (!empty($d['pergunta'])) { $l[] = $d['pergunta']; }
    if (!empty($d['texto']))    { $l[] = $d['texto']; }
    if (!empty($d['autor']))    { $l[] = '— ' . $d['autor']; }
    if (!empty($d['rotulo']))   { $l[] = 'Botão: ' . $d['rotulo']; }
    if (!empty($d['url']))      { $l[] = $d['url']; }

    if ($tipo === 'quiz' && !empty($d['opcoes']) && is_array($d['opcoes'])) {
        foreach ($d['opcoes'] as $i => $o) {
            $marca = !empty($o['ok']) ? '[X] ' : '[ ] ';
            $l[] = $marca . chr(65 + $i) . ') ' . ($o['t'] ?? '');
        }
        if (!empty($d['feedback'])) { $l[] = 'Explicação: ' . $d['feedback']; }
    }

    if ($tipo === 'tabela') {
        if (!empty($d['cab']))    { $l[] = 'Cabeçalho: ' . $d['cab']; }
        if (!empty($d['linhas'])) { $l[] = (string)$d['linhas']; }
    }

    foreach ([['aTitulo', 'aItens'], ['bTitulo', 'bItens']] as [$ct, $ci]) {
        if (!empty($d[$ci]) && is_array($d[$ci])) {
            if (!empty($d[$ct])) { $l[] = $d[$ct] . ':'; }
            foreach ($d[$ci] as $o) { $l[] = '• ' . ($o['t'] ?? ''); }
        }
    }

    if (!empty($d['itens']) && is_array($d['itens'])) {
        $guiado = !empty($d['guiado']);
        foreach ($d['itens'] as $i => $o) {
            $linha = ($i + 1) . '. ' . ($o['t'] ?? '');
            if ($tecnico && $guiado && !empty($o['f'])) {
                $linha .= '  (abre após a fala ' . (int)$o['f'] . ')';
            }
            $l[] = $linha;
            if (!empty($o['d'])) { $l[] = '    ' . $o['d']; }
            if (isset($o['v']) && $o['v'] !== '') { $l[] = '    Valor: ' . $o['v']; }
        }
    }

    return trim(implode("\n", array_filter($l, static fn($x) => trim((string)$x) !== '')));
}

function trx_acoes(array $acoes, array $tiposAcao): string
{
    if (!$acoes) { return '—'; }
    $l = [];
    foreach ($acoes as $a) {
        $nome = $tiposAcao[$a['tipo']] ?? $a['tipo'];
        $quando = $a['fase'] === 'durante'
            ? (round((float)$a['momento'] * 100) . '% da fala')
            : $a['fase'];
        $alvo = trim((string)($a['alvo'] ?? ''));
        $valor = trim((string)($a['valor'] ?? ''));
        $l[] = $nome . ($alvo !== '' ? ' ' . $alvo : '')
             . ($valor !== '' ? ' = ' . $valor : '')
             . ' (' . $quando . ')';
    }
    return implode("\n", $l);
}

$titulo = (string)$ap['titulo'];
$doc = new TrDocx(
    $titulo,
    $modo === 'simples' ? 'Texto de tela e narração' : 'Roteiro para revisão de conteúdo',
    'RH Digital'
);

$totalFalas = 0;
$totalAcoes = 0;
$palavras   = 0;
foreach ($slides as $s) {
    $totalFalas += count($s['dialogos']);
    foreach ($s['dialogos'] as $d) {
        $totalAcoes += count($d['acoes']);
        $palavras += str_word_count((string)$d['texto'], 0, 'áàâãéêíóôõúüçÁÀÂÃÉÊÍÓÔÕÚÜÇ');
    }
}
$minutos = max(1, (int)round($palavras / 130));

if ($modo === 'simples') {

    $doc->paragrafo(mb_strtoupper((string)($ap['marca'] ?? ''), 'UTF-8'), true, false, 9);
    $doc->titulo1($titulo);
    if (!empty($ap['subtitulo'])) { $doc->paragrafo((string)$ap['subtitulo'], false, true, 12); }
    $doc->nota(count($slides) . ' telas  ·  ' . $totalFalas . ' falas  ·  cerca de '
             . $minutos . ' minutos de narração  ·  documento gerado em ' . date('d/m/Y'));

    $doc->caixa('Como usar este documento',
        'Ele traz apenas o que aparece escrito na tela e o que o instrutor fala, tela por tela. '
      . 'Escreva as correções direto no arquivo e devolva ao responsável pelo conteúdo. '
      . 'As alterações precisam ser aplicadas no editor para valerem no treinamento.');

    foreach ($slides as $s) {
        $doc->quebraPagina();
        $doc->titulo1('Tela ' . $s['ordem'] . ' — ' . $s['titulo']);

        $els = trx_elementos((string)$s['conteudo_html'], false);
        if ($els) {
            $doc->titulo3('Na tela');
            foreach ($els as $e) {
                foreach (preg_split('/\n/', $e['conteudo']) as $linha) {
                    $linha = rtrim($linha);
                    if (trim($linha) === '') { continue; }
                    $doc->paragrafo($linha);
                }
                $doc->espaco(2);
            }
        }

        if ($s['dialogos']) {
            $doc->titulo3('Narração');
            $linhas = [];
            foreach ($s['dialogos'] as $i => $d) {
                $linhas[] = [(string)($i + 1), (string)$d['texto']];
            }
            $doc->tabela([], $linhas, [5, 95], false);
        }

        $doc->espacoRevisao();
    }

    if ($questoes) {
        $doc->quebraPagina();
        $doc->titulo1('Avaliação final');
        foreach ($questoes as $n => $q) {
            $doc->titulo2('Questão ' . ($n + 1));
            $doc->paragrafo((string)$q['enunciado']);
            $alts = [];
            foreach ($q['alternativas'] as $i => $a) {
                $alts[] = (!empty($a['correta']) ? '[X] ' : '[  ] ')
                        . chr(65 + $i) . ') ' . (string)$a['texto'];
            }
            $doc->lista($alts);
            if (!empty($q['explicacao'])) {
                $doc->paragrafo('Justificativa: ' . (string)$q['explicacao'], false, true);
            }
            $doc->espacoRevisao('Revisão desta questão');
        }
    }

    $doc->quebraPagina();
    $doc->titulo1('Registro da revisão');
    $doc->tabela(['Campo', 'Preenchimento'], [
        ['Revisado por', ''],
        ['Data da revisão', ''],
        ['Parecer', 'aprovado sem alterações   /   aprovado com alterações   /   reprovado'],
        ['Observações gerais', "\n\n\n"],
    ], [26, 74], false);

    $doc->enviar('treinamento-' . tr_slugify($titulo) . '-texto-' . date('Y-m-d') . '.docx');
}

$doc->paragrafo(mb_strtoupper((string)($ap['marca'] ?? ''), 'UTF-8'), true, false, 9);
$doc->titulo1($titulo);
if (!empty($ap['subtitulo'])) { $doc->paragrafo((string)$ap['subtitulo'], false, true, 12); }

$doc->espaco(8);
$doc->tabela([], [
    ['Identificador', (string)$ap['slug']],
    ['Situação', ucfirst((string)($ap['status'] ?? ''))],
    ['Carga horária', ((int)($ap['carga_horaria_min'] ?? 0)) . ' minutos'],
    ['Telas', (string)count($slides)],
    ['Falas narradas', $totalFalas . '  (aproximadamente ' . $palavras . ' palavras, '
                      . $minutos . ' min de narração)'],
    ['Ações de tela', (string)$totalAcoes],
    ['Avaliação final', !empty($ap['prova_ativa'])
        ? (count($questoes) . ' questões no banco, ' . (int)($ap['prova_qtd_sorteada'] ?? 0)
           . ' sorteadas, ' . (int)($ap['prova_nota_minima'] ?? 0) . '% para aprovação, '
           . (int)($ap['prova_tentativas'] ?? 0) . ' tentativas')
        : 'desativada'],
    ['Documento gerado em', date('d/m/Y H:i')],
], [26, 74], false);

if (!empty($ap['descricao'])) {
    $doc->titulo3('Descrição');
    $doc->paragrafo((string)$ap['descricao']);
}

$doc->caixa('Como usar este documento',
    "Ele reproduz o treinamento inteiro em texto, na ordem em que aparece na tela. "
  . "Cada tela traz a ficha técnica, os elementos exibidos e a narração completa, com o gesto do "
  . "instrutor e as ações disparadas em cada fala.\n\n"
  . "Escreva as correções diretamente no documento e devolva ao responsável pelo conteúdo. "
  . "As alterações feitas aqui não voltam sozinhas para o sistema: elas precisam ser aplicadas "
  . "no editor.");

$doc->titulo2('Sumário das telas');
$linhas = [];
foreach ($slides as $s) {
    $els = trx_elementos((string)$s['conteudo_html']);
    $linhas[] = [
        (string)$s['ordem'],
        (string)$s['titulo'],
        $layouts[$s['layout']] ?? $s['layout'],
        (string)count($els),
        (string)count($s['dialogos']),
    ];
}
$doc->tabela(['#', 'Título da tela', 'Layout', 'Elementos', 'Falas'], $linhas, [6, 52, 20, 11, 11]);

foreach ($slides as $s) {
    $doc->quebraPagina();
    $doc->titulo1('Tela ' . $s['ordem'] . ' — ' . $s['titulo']);

    if (!empty($s['resumo'])) { $doc->nota((string)$s['resumo']); }

    $instr = (int)$s['instrutor_visivel'] === 1
        ? ('visível, ' . ($posicoes[$s['instrutor_posicao']] ?? $s['instrutor_posicao'])
           . ', câmera ' . ($cameras[$s['instrutor_camera']] ?? $s['instrutor_camera']))
        : 'oculto nesta tela';

    $exig = ['herdar' => 'herda da apresentação', 'nenhuma' => 'nenhuma',
             'quiz' => 'responder à pergunta', 'todas' => 'concluir todas as atividades'];

    $doc->tabela([], [
        ['Layout', ($layouts[$s['layout']] ?? $s['layout'])
                   . '  ·  transição: ' . ($transicoes[$s['transicao']] ?? $s['transicao'])],
        ['Instrutor', $instr],
        ['Para avançar', $exig[$s['exigencia']] ?? $s['exigencia']],
    ], [22, 78], false);

    $els = trx_elementos((string)$s['conteudo_html']);
    $doc->titulo3('Conteúdo exibido na tela');
    if (!$els) {
        $doc->nota('Nenhum elemento de conteúdo nesta tela.');
    } else {
        $linhas = [];
        foreach ($els as $e) {
            $linhas[] = [
                $e['id'] . ($e['oculto'] ? "\n(progressivo)" : ''),
                $e['tipo'],
                $e['conteudo'],
            ];
        }
        $doc->tabela(['Ref.', 'Tipo', 'Texto exibido'], $linhas, [9, 18, 73]);
    }

    $doc->titulo3('Narração');
    if (!$s['dialogos']) {
        $doc->nota('Tela sem narração.');
    } else {
        $linhas = [];
        foreach ($s['dialogos'] as $i => $d) {
            $marcas = [];
            if (!empty($d['gesto']))  { $marcas[] = $gestos[$d['gesto']] ?? $d['gesto']; }
            if (!empty($d['humor']) && $d['humor'] !== 'neutral') {
                $marcas[] = $humores[$d['humor']] ?? $d['humor'];
            }
            if (!empty($d['olhar']))  { $marcas[] = 'olhar: ' . ($olhares[$d['olhar']] ?? $d['olhar']); }
            $marcas[] = 'pausa ' . (int)$d['pausa_ms'] . ' ms';
            $audio = ($d['voz_modo'] === 'arquivo' && !empty($d['audio_arquivo']))
                ? 'narração gravada'
                : 'sem narração gravada';

            $linhas[] = [
                (string)($i + 1),
                (string)$d['texto'],
                implode("\n", $marcas) . "\n" . $audio,
                trx_acoes($d['acoes'], $tiposAcao),
            ];
        }
        $doc->tabela(['#', 'Texto falado', 'Instrutor', 'Ações na tela'], $linhas, [5, 52, 21, 22]);
    }

    $doc->espacoRevisao();
}

if ($questoes) {
    $doc->quebraPagina();
    $doc->titulo1('Avaliação final');
    $doc->nota(count($questoes) . ' questões no banco. A alternativa correta está marcada com [X].');

    $tipos = ['unica' => 'Resposta única', 'multipla' => 'Múltipla escolha', 'vf' => 'Verdadeiro ou falso'];

    foreach ($questoes as $n => $q) {
        $doc->titulo2('Questão ' . ($n + 1)
            . (empty($q['ativa']) ? '  (inativa)' : ''));
        $doc->nota(($tipos[$q['tipo']] ?? $q['tipo']) . '  ·  peso ' . (float)$q['peso']);
        $doc->paragrafo((string)$q['enunciado']);

        $linhas = [];
        foreach ($q['alternativas'] as $i => $a) {
            $linhas[] = [
                (!empty($a['correta']) ? '[X]' : '[ ]') . ' ' . chr(65 + $i),
                (string)$a['texto'],
            ];
        }
        $doc->tabela([], $linhas, [9, 91]);

        if (!empty($q['explicacao'])) {
            $doc->caixa('Justificativa mostrada ao aluno', (string)$q['explicacao'], '1F9254', 'F1F9F4');
        }
        $doc->espacoRevisao('Revisão desta questão');
    }
}

$doc->quebraPagina();
$doc->titulo1('Registro da revisão');
$doc->paragrafo('Preencha ao concluir a leitura do documento.');
$doc->tabela(['Campo', 'Preenchimento'], [
    ['Revisado por', ''],
    ['Cargo / função', ''],
    ['Data da revisão', ''],
    ['Parecer', 'aprovado sem alterações   /   aprovado com as alterações indicadas   /   reprovado'],
    ['Observações gerais', "\n\n\n"],
], [26, 74], false);

$doc->enviar('treinamento-' . tr_slugify($titulo) . '-completo-' . date('Y-m-d') . '.docx');
