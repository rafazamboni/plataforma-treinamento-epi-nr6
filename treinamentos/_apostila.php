<?php
if (!function_exists('tr_url')) {
    require_once __DIR__ . '/_lib.php';
}

function trap_cfg(array $ap): array
{
    return [
        'ativa'       => (int)($ap['apostila_ativa'] ?? 0) === 1,
        'titulo'      => trim((string)($ap['apostila_titulo'] ?? '')),
        'narracao'    => (int)($ap['apostila_narracao'] ?? 1) === 1,
        'imagens'     => (int)($ap['apostila_imagens'] ?? 1) === 1,
        'questoes'    => (int)($ap['apostila_questoes'] ?? 1) === 1,
        'gabarito'    => (int)($ap['apostila_gabarito'] ?? 0) === 1,
        'referencias' => (int)($ap['apostila_referencias'] ?? 1) === 1,
        'anotacoes'   => (int)($ap['apostila_anotacoes'] ?? 0) === 1,
    ];
}

function trap_ativa(array $ap): bool
{
    return trap_cfg($ap)['ativa'];
}

function trap_titulo(array $ap): string
{
    $c = trap_cfg($ap);
    return $c['titulo'] !== '' ? $c['titulo'] : 'Apostila — ' . (string)$ap['titulo'];
}

function trap_item(array $ap, string $baseArquivo): ?array
{
    if (!trap_ativa($ap)) { return null; }

    return [
        'id'        => -1,
        'titulo'    => trap_titulo($ap),
        'descricao' => 'Todo o conteúdo do treinamento em PDF, para ler e imprimir.',
        'tipo'      => 'arquivo',
        'url'       => $baseArquivo . '-1',
        'baixar'    => $baseArquivo . '-1&baixar=1',
        'icone'     => 'file-earmark-pdf',
        'formato'   => 'PDF',
        'tamanho'   => '',
        'destaque'  => true,
    ];
}

function trap_e(string $t): string
{
    return htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
}

function trap_texto(string $html): string
{
    $t = preg_replace('~<(br|/p|/h[1-6]|/li|/div)\s*/?>~i', "\n", $html);
    $t = strip_tags((string)$t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('~[ \t]+~', ' ', (string)$t);
    $t = preg_replace('~\n{3,}~', "\n\n", (string)$t);
    return trim((string)$t);
}

function trap_html_limpo(string $html): string
{
    $t = strip_tags($html, '<p><br><b><strong><i><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6>');
    $t = preg_replace('~<(\w+)[^>]*>~', '<$1>', (string)$t);
    $t = preg_replace('~<h[1-6]>~', '<h3>', (string)$t);
    $t = preg_replace('~</h[1-6]>~', '</h3>', (string)$t);
    $t = trim((string)$t);
    if ($t === '') { return ''; }
    if (stripos($t, '<p>') === false && stripos($t, '<ul>') === false
        && stripos($t, '<ol>') === false && stripos($t, '<h3>') === false) {
        $t = '<p>' . $t . '</p>';
    }
    return $t;
}

function trap_elementos(string $conteudoHtml): array
{
    if (!preg_match('~data-tr-canvas>(.*?)</script>~s', $conteudoHtml, $m)) {
        $t = trap_texto($conteudoHtml);
        return $t === '' ? [] : [['t' => 'texto', 'html' => nl2br(trap_e($t)), 'bloco' => '',
                                  'dados' => [], 'src' => '', 'alt' => '']];
    }

    $dados = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
    if (!is_array($dados) || empty($dados['els'])) { return []; }

    $saida = [];
    foreach ($dados['els'] as $el) {
        $tipo = (string)($el['t'] ?? 'texto');
        if ($tipo === 'forma' || $tipo === 'video') { continue; }
        $saida[] = [
            't'     => $tipo,
            'html'  => (string)($el['html'] ?? ''),
            'bloco' => (string)($el['bloco'] ?? ''),
            'dados' => is_array($el['dados'] ?? null) ? $el['dados'] : [],
            'src'   => (string)($el['src'] ?? ''),
            'alt'   => (string)($el['alt'] ?? ''),
        ];
    }
    return $saida;
}

function trap_bloco_html(string $tipo, array $d, array $cfg): string
{
    $o = '';

    switch ($tipo) {

        case 'quiz': {
            $o .= '<div class="cx cx-quiz">';
            $o .= '<div class="cx-tit">PERGUNTA</div>';
            if (!empty($d['pergunta'])) { $o .= '<p>' . trap_e((string)$d['pergunta']) . '</p>'; }
            $letra = 0;
            $certa = [];
            foreach ((array)($d['opcoes'] ?? []) as $op) {
                $l = chr(65 + $letra);
                $ok = !empty($op['ok']);
                if ($ok) { $certa[] = $l; }
                $marca = ($cfg['gabarito'] && $ok) ? '<b>&#10003; </b>' : '';
                $o .= '<p class="alt">' . $marca . '<b>' . $l . ')</b> ' . trap_e((string)($op['t'] ?? '')) . '</p>';
                $letra++;
            }
            if ($cfg['gabarito']) {
                if ($certa) { $o .= '<p class="gab"><b>Resposta:</b> ' . implode(', ', $certa) . '</p>'; }
                if (!empty($d['feedback'])) {
                    $o .= '<p class="gab">' . trap_e((string)$d['feedback']) . '</p>';
                }
            }
            $o .= '</div>';
            break;
        }

        case 'revelar':
        case 'abas': {
            if (!empty($d['titulo'])) { $o .= '<p class="sub">' . trap_e((string)$d['titulo']) . '</p>'; }
            foreach ((array)($d['itens'] ?? []) as $it) {
                $o .= '<p class="item"><b>' . trap_e((string)($it['t'] ?? '')) . '</b>';
                if (!empty($it['d'])) { $o .= '<br>' . trap_e((string)$it['d']); }
                $o .= '</p>';
            }
            break;
        }

        case 'passos': {
            $n = 1;
            foreach ((array)($d['itens'] ?? []) as $it) {
                $o .= '<p class="item"><b>' . $n . '. ' . trap_e((string)($it['t'] ?? '')) . '</b>';
                if (!empty($it['d'])) { $o .= '<br>' . trap_e((string)$it['d']); }
                $o .= '</p>';
                $n++;
            }
            break;
        }

        case 'checklist': {
            $o .= '<div class="cx">';
            if (!empty($d['titulo'])) { $o .= '<div class="cx-tit">' . trap_e(mb_strtoupper((string)$d['titulo'], 'UTF-8')) . '</div>'; }
            foreach ((array)($d['itens'] ?? []) as $it) {
                $o .= '<p class="check">[ &nbsp; ] ' . trap_e((string)($it['t'] ?? '')) . '</p>';
            }
            $o .= '</div>';
            break;
        }

        case 'comparativo': {
            $a = (array)($d['aItens'] ?? []);
            $b = (array)($d['bItens'] ?? []);
            $o .= '<table class="tb"><thead><tr>'
                . '<th width="50%">' . trap_e((string)($d['aTitulo'] ?? 'Faça assim')) . '</th>'
                . '<th width="50%">' . trap_e((string)($d['bTitulo'] ?? 'Evite')) . '</th>'
                . '</tr></thead><tbody>';
            $tot = max(count($a), count($b));
            for ($i = 0; $i < $tot; $i++) {
                $o .= '<tr><td>' . trap_e((string)($a[$i]['t'] ?? '')) . '</td>'
                    . '<td>' . trap_e((string)($b[$i]['t'] ?? '')) . '</td></tr>';
            }
            $o .= '</tbody></table>';
            break;
        }

        case 'estatisticas': {
            $o .= '<table class="tb"><tbody>';
            foreach ((array)($d['itens'] ?? []) as $it) {
                $o .= '<tr><td class="num" width="22%">' . trap_e((string)($it['v'] ?? '')) . '</td>'
                    . '<td>' . trap_e((string)($it['r'] ?? '')) . '</td></tr>';
            }
            $o .= '</tbody></table>';
            break;
        }

        case 'alerta': {
            $rot = ['dica' => 'DICA', 'atencao' => 'ATENÇÃO', 'perigo' => 'RISCO', 'ok' => 'BOA PRÁTICA'];
            $t = (string)($d['tipo'] ?? 'atencao');
            $o .= '<div class="cx cx-' . trap_e($t) . '">';
            $o .= '<div class="cx-tit">' . trap_e(mb_strtoupper((string)($d['titulo'] ?? ($rot[$t] ?? 'ATENÇÃO')), 'UTF-8')) . '</div>';
            if (!empty($d['texto'])) { $o .= '<p>' . nl2br(trap_e((string)$d['texto'])) . '</p>'; }
            $o .= '</div>';
            break;
        }

        case 'citacao': {
            $o .= '<div class="cit"><p>' . trap_e((string)($d['texto'] ?? '')) . '</p>';
            if (!empty($d['autor'])) { $o .= '<p class="cit-autor">— ' . trap_e((string)$d['autor']) . '</p>'; }
            $o .= '</div>';
            break;
        }

        case 'linhaTempo': {
            foreach ((array)($d['itens'] ?? []) as $it) {
                $o .= '<p class="item"><b>' . trap_e((string)($it['t'] ?? '')) . '</b>';
                if (!empty($it['d'])) { $o .= ' — ' . trap_e((string)$it['d']); }
                $o .= '</p>';
            }
            break;
        }

        case 'tabela': {
            $cols = array_map('trim', explode('|', (string)($d['cab'] ?? '')));
            $o .= '<table class="tb">';
            if ($cols && $cols[0] !== '') {
                $o .= '<thead><tr>';
                foreach ($cols as $c) { $o .= '<th>' . trap_e($c) . '</th>'; }
                $o .= '</tr></thead>';
            }
            $o .= '<tbody>';
            foreach (preg_split('/\r?\n/', (string)($d['linhas'] ?? '')) as $linha) {
                if (trim($linha) === '') { continue; }
                $o .= '<tr>';
                foreach (explode('|', $linha) as $c) { $o .= '<td>' . trap_e(trim($c)) . '</td>'; }
                $o .= '</tr>';
            }
            $o .= '</tbody></table>';
            break;
        }

        case 'botao': {
            $rot = (string)($d['rotulo'] ?? '');
            $url = (string)($d['url'] ?? '');
            if ($rot !== '' || $url !== '') {
                $o .= '<p class="link"><b>' . trap_e($rot !== '' ? $rot : 'Link') . '</b>'
                    . ($url !== '' ? '<br>' . trap_e($url) : '') . '</p>';
            }
            break;
        }

        default: {
            $l = [];
            foreach (['titulo', 'texto', 'pergunta'] as $k) {
                if (!empty($d[$k])) { $l[] = trap_e((string)$d[$k]); }
            }
            if ($l) { $o .= '<p>' . implode('<br>', $l) . '</p>'; }
        }
    }

    return $o;
}

function trap_img(int $apId, string $arquivo): string
{
    $nome = basename($arquivo);
    if ($nome === '') { return ''; }
    $caminho = tr_dir_midia($apId) . '/' . $nome;
    return is_file($caminho) ? $caminho : '';
}

function trap_logo(): string
{
    foreach (['logo2.png', 'logo.png'] as $arq) {
        $c = dirname(__DIR__, 3) . '/app/layout/' . $arq;
        if (is_file($c)) { return $c; }
    }
    return '';
}

function trap_header_html(array $ap): string
{
    $logo = trap_logo();
    $logoTag = $logo !== ''
        ? '<img src="' . trap_e($logo) . '" style="height:9mm;width:auto">'
        : '<span style="color:#fff;font-weight:700;font-size:9pt">LOGO</span>';

    return '
<table width="100%" style="border-collapse:collapse"><tr>
  <td style="background:#003057;padding:3mm 6mm;width:26mm" valign="middle">' . $logoTag . '</td>
  <td style="background:#003057;padding:3mm 6mm" valign="middle">
    <div style="color:#b9d4ec;font-size:5.5pt;font-weight:700;letter-spacing:.03em">APOSTILA DE TREINAMENTO</div>
   <div style="color:#fff;font-size:8pt;font-weight:700;margin-top:.8mm;">
    ' . trap_e(mb_strtoupper((string)$ap['titulo'], 'UTF-8')) . '
</div>
  </td>
  <td style="background:#003057;padding:3mm 6mm;text-align:right;white-space:nowrap" valign="middle">
    <span style="color:#9fc1e0;font-weight:700;font-size:7.5pt;letter-spacing:.03em">NÓRDICA VEÍCULOS S/A</span>
  </td>
</tr></table>';
}

function trap_footer_html(array $ap, int $margem): string
{
    return '
<div style="padding:0 ' . $margem . 'mm">
  <div style="border-top:0.2mm solid #e2e8f0;padding-top:1.5mm">
    <table width="100%" style="font-size:6.5pt;color:#64748b">
    <tr>
      <td width="70%">' . trap_e((string)$ap['titulo']) . ' — Apostila</td>
      <td width="30%" style="text-align:right">Página {PAGENO} / {nbpg}</td>
    </tr>
    </table>
  </div>
</div>';
}

function trap_corpo(PDO $pdo, array $ap, array $cfg): string
{
    $apId   = (int)$ap['id'];
    $slides = tr_estrutura($pdo, $apId);

    $questoes = [];
    if ($cfg['questoes']) {
        if (!function_exists('trp_questoes')) { require_once __DIR__ . '/_prova.php'; }
        try { $questoes = trp_questoes($pdo, $apId, true); } catch (Throwable $e) { $questoes = []; }
    }

    $referencias = [];
    if ($cfg['referencias'] && function_exists('trmat_referencias')) {
        try { $referencias = trmat_referencias($pdo, $apId); } catch (Throwable $e) { $referencias = []; }
    }

    $o = '<div class="pg">';

    $o .= '<h1 class="doctit">' . trap_e((string)$ap['titulo']) . '</h1>';
    if (!empty($ap['subtitulo'])) {
        $o .= '<div class="docsub">' . trap_e((string)$ap['subtitulo']) . '</div>';
    }

    $meta = [
        ['DOCUMENTO', 'Apostila'],
        ['CARGA HORÁRIA', ((int)($ap['carga_horaria_min'] ?? 0)) . ' minutos'],
        ['CONTEÚDO', count($slides) . ' etapas'],
        ['GERADO EM', date('d/m/Y')],
    ];
    $o .= '<table class="metagrid"><tr>';
    foreach ($meta as $i => $col) {
        $o .= '<td' . ($i === 0 ? ' class="first"' : '') . ' width="25%">'
            . '<div class="meta-lbl">' . trap_e($col[0]) . '</div>'
            . '<div class="meta-val">' . trap_e($col[1]) . '</div>'
            . '</td>';
    }
    $o .= '</tr></table>';

    $o .= '<div class="contlbl">SOBRE ESTE TREINAMENTO</div>';

    if (!empty($ap['descricao'])) {
        $o .= '<div class="intro body"><p>' . nl2br(trap_e((string)$ap['descricao'])) . '</p></div>';
    }
    $o .= '<div class="intro body"><p>Este documento reproduz o conteúdo do treinamento para leitura '
        . 'e consulta. Ele não substitui a realização do treinamento no sistema, que é o que registra '
        . 'a conclusão e emite o certificado.</p></div>';
    $o .= '<div class="intro-rule"></div>';

    if (count($slides) > 1) {
        $o .= '<div class="contlbl">CONTEÚDO</div><table class="sum"><tbody>';
        foreach ($slides as $s) {
            $o .= '<tr><td class="sum-n" width="10mm">'
                . str_pad((string)(int)$s['ordem'], 2, '0', STR_PAD_LEFT) . '</td>'
                . '<td>' . trap_e((string)$s['titulo']) . '</td></tr>';
        }
        if ($questoes)    { $o .= '<tr><td class="sum-n">&mdash;</td><td>Questões para fixação</td></tr>'; }
        if ($referencias) { $o .= '<tr><td class="sum-n">&mdash;</td><td>Referências</td></tr>'; }
        $o .= '</tbody></table><div class="intro-rule"></div>';
    }

    $n = 0;
    foreach ($slides as $s) {
        $n++;
        $o .= '<div class="sec">';
        $o .= '<div class="sec-hd"><span class="sec-num">'
            . str_pad((string)$n, 2, '0', STR_PAD_LEFT) . ' &mdash; </span>'
            . '<span class="sec-tit">' . trap_e((string)$s['titulo']) . '</span></div>';

        $corpoSec = '';

        if (!empty($s['resumo'])) {
            $corpoSec .= '<p class="resumo">' . trap_e((string)$s['resumo']) . '</p>';
        }

        foreach (trap_elementos((string)$s['conteudo_html']) as $el) {
            if ($el['t'] === 'bloco') {
                $corpoSec .= trap_bloco_html($el['bloco'], $el['dados'], $cfg);
            } elseif ($el['t'] === 'imagem') {
                if (!$cfg['imagens']) { continue; }
                $src = trap_img($apId, $el['src']);
                if ($src !== '') {
                    $corpoSec .= '<div class="imgwrap"><img src="' . trap_e($src) . '"></div>';
                    if ($el['alt'] !== '') {
                        $corpoSec .= '<div class="imgleg">' . trap_e($el['alt']) . '</div>';
                    }
                }
            } else {
                $corpoSec .= trap_html_limpo($el['html']);
            }
        }

        if ($cfg['narracao'] && $s['dialogos']) {
            $corpoSec .= '<div class="narr"><div class="narr-tit">O QUE O INSTRUTOR EXPLICA</div>';
            foreach ($s['dialogos'] as $d) {
                $t = trim((string)$d['texto']);
                if ($t === '') { continue; }
                $corpoSec .= '<p>' . trap_e($t) . '</p>';
            }
            $corpoSec .= '</div>';
        }

        if ($cfg['anotacoes']) {
            $corpoSec .= '<div class="anot"><div class="narr-tit">ANOTAÇÕES</div>'
                . '<div class="anot-l"></div><div class="anot-l"></div>'
                . '<div class="anot-l"></div><div class="anot-l"></div></div>';
        }

        if ($corpoSec !== '') {
            $o .= '<div class="sec-body body">' . $corpoSec . '</div>';
        }
        $o .= '</div>';
    }

    if ($questoes) {
        $o .= '<div class="anexo">';
        $o .= '<div class="anexo-rule"></div>';
        $o .= '<div class="anexo-hd"><span class="anexo-badge">AVALIAÇÃO &mdash; </span>'
            . '<span class="anexo-tit">QUESTÕES PARA FIXAÇÃO</span></div>';
        $o .= '<div class="anexo-rule2"></div>';
        $o .= '<div class="anexo-body body">';
        $o .= '<p class="resumo">' . ($cfg['gabarito']
            ? 'As respostas corretas estão assinaladas.'
            : 'Use as questões para revisar o conteúdo. A correção é feita na prova do sistema.')
            . '</p>';

        $q = 0;
        foreach ($questoes as $questao) {
            $q++;
            $o .= '<div class="q"><div class="q-tit">Questão ' . $q . '</div>';
            $o .= '<p>' . nl2br(trap_e((string)$questao['enunciado'])) . '</p>';
            $letra = 0;
            $certa = [];
            foreach ((array)$questao['alternativas'] as $a) {
                $l  = chr(65 + $letra);
                $ok = !empty($a['correta']);
                if ($ok) { $certa[] = $l; }
                $marca = ($cfg['gabarito'] && $ok) ? '<b>&#10003; </b>' : '';
                $o .= '<p class="alt">' . $marca . '<b>' . $l . ')</b> ' . trap_e((string)$a['texto']) . '</p>';
                $letra++;
            }
            if ($cfg['gabarito']) {
                if ($certa) { $o .= '<p class="gab"><b>Resposta:</b> ' . implode(', ', $certa) . '</p>'; }
                if (!empty($questao['explicacao'])) {
                    $o .= '<p class="gab">' . trap_e((string)$questao['explicacao']) . '</p>';
                }
            }
            $o .= '</div>';
        }
        $o .= '</div></div>';
    }

    if ($referencias) {
        $o .= '<div class="anexo">';
        $o .= '<div class="anexo-rule"></div>';
        $o .= '<div class="anexo-hd"><span class="anexo-badge">FONTES &mdash; </span>'
            . '<span class="anexo-tit">REFERÊNCIAS</span></div>';
        $o .= '<div class="anexo-rule2"></div>';
        $o .= '<div class="anexo-body body">';
        foreach ($referencias as $r) {
            $o .= '<p class="ref">' . trap_e((string)$r['texto']);
            if ((string)$r['url'] !== '') { $o .= '<br>' . trap_e((string)$r['url']); }
            $o .= '</p>';
        }
        $o .= '</div></div>';
    }

    $o .= '</div>';
    return $o;
}

function trap_css(int $margem): string
{
    return '
body        { font-family: dejavusans; }
.pg         { padding: 0 ' . $margem . 'mm; }
h1.doctit   { font-size:16pt; font-weight:bold; color:#0f172a; margin:0 0 1.5mm; }
.docsub     { font-size:9.5pt; color:#64748b; margin:0 0 3mm; }
.metagrid   { width:100%; border-collapse:collapse; margin-bottom:5mm; }
.metagrid td{ border:0.2mm solid #e2e8f0; padding:2mm 3mm; vertical-align:top; }
.metagrid td.first{ border-left:1mm solid #003057; }
.meta-lbl   { font-size:5.5pt; font-weight:bold; color:#64748b; letter-spacing:.03em; }
.meta-val   { font-size:8pt; font-weight:bold; color:#0f172a; margin-top:1mm; }
.contlbl    { font-size:6.5pt; font-weight:bold; color:#2563eb; letter-spacing:.05em; border-bottom:0.35mm solid #dbeafe; padding-bottom:2mm; margin-bottom:5mm; }
.intro      { font-size:9pt; color:#334155; line-height:1.55; text-align:justify; margin-bottom:2mm; }
.intro-rule { border-top:0.2mm solid #e2e8f0; margin:4mm 0 6mm; }
.sum        { width:100%; border-collapse:collapse; margin-bottom:2mm; }
.sum td     { border-bottom:0.2mm solid #f1f5f9; padding:1.4mm 2mm; font-size:8.5pt; color:#334155; }
.sum-n      { color:#2563eb; font-weight:bold; }
.sec        { margin-bottom:6mm; }
.sec-hd     { border-left:1.1mm solid #2563eb; padding:0.8mm 0 0.8mm 4mm; margin-bottom:2.8mm; }
.sec-num    { color:#2563eb; font-weight:bold; font-size:11pt; }
.sec-tit    { font-size:11pt; font-weight:bold; color:#0f172a; }
.sec-body   { padding-left:10mm; font-size:9pt; color:#334155; line-height:1.55; text-align:justify; }
.anexo      { page-break-before: always; }
.anexo-rule { border-top:0.5mm solid #d97706; margin-bottom:3mm; }
.anexo-hd   { border-left:1.1mm solid #d97706; padding:0.8mm 0 0.8mm 4mm; margin-bottom:0; }
.anexo-badge{ color:#d97706; font-weight:bold; font-size:9pt; letter-spacing:.05em; }
.anexo-tit  { font-size:11pt; font-weight:bold; color:#0f172a; }
.anexo-rule2{ border-top:0.3mm solid #fed7aa; margin:3mm 0 4mm; }
.anexo-body { font-size:9pt; color:#334155; line-height:1.55; text-align:justify; }
.imgwrap    { text-align:center; margin:2mm 0 1mm; }
.imgwrap img{ max-width:100%; }
.imgleg     { text-align:center; font-size:6.5pt; color:#64748b; margin-bottom:3mm; }
.body p     { margin:0 0 2.5mm; }
.body h3    { font-size:10pt; font-weight:bold; margin:3mm 0 1mm; color:#0f172a; }
.body ul,.body ol{ margin:1.5mm 0 2.5mm; padding-left:6mm; }
.body li    { margin-bottom:1.3mm; }
.body strong,.body b { font-weight:bold; color:#0f172a; }
.body em,.body i     { font-style:italic; }
.resumo     { color:#64748b; font-style:italic; margin-bottom:2.5mm; }
.sub        { font-weight:bold; margin:3mm 0 1.5mm; color:#0f172a; }
.item       { margin:0 0 2mm; }
.alt        { margin:0 0 1.2mm; padding-left:4mm; }
.check      { margin:0 0 1.4mm; }
.gab        { color:#1f6a3f; font-size:8.5pt; margin:1.4mm 0 0; }
.link       { color:#2563eb; }
.cx         { border:0.2mm solid #e2e8f0; background:#f8fafc; padding:2.6mm 3.4mm; margin:2.5mm 0 3mm; }
.cx-tit     { font-size:6.5pt; font-weight:bold; color:#64748b; letter-spacing:.05em; margin-bottom:1.6mm; }
.cx-quiz    { background:#eff6ff; border-color:#bfdbfe; }
.cx-quiz .cx-tit{ color:#2563eb; }
.cx-dica    { background:#eff6ff; border-color:#bfdbfe; }
.cx-atencao { background:#fffbeb; border-color:#fde68a; }
.cx-atencao .cx-tit{ color:#b45309; }
.cx-perigo  { background:#fef2f2; border-color:#fecaca; }
.cx-perigo .cx-tit{ color:#b91c1c; }
.cx-ok      { background:#f0fdf4; border-color:#bbf7d0; }
.cx-ok .cx-tit{ color:#15803d; }
.cit        { border-left:0.8mm solid #2563eb; background:#eff6ff; padding:2.3mm 4mm; margin:2.5mm 0; color:#1e3a8a; font-size:8.5pt; font-style:italic; }
.cit-autor  { font-style:normal; font-size:7.5pt; color:#64748b; margin:1mm 0 0; }
.tb         { width:100%; border-collapse:collapse; margin:2.5mm 0 3mm; }
.tb th      { background:#f1f5f9; border:0.25mm solid #cbd5e1; padding:1.3mm 2mm; font-size:8.5pt; font-weight:bold; color:#0f172a; text-align:left; }
.tb td      { border:0.25mm solid #cbd5e1; padding:1.3mm 2mm; font-size:8.5pt; vertical-align:top; }
.num        { font-weight:bold; color:#2563eb; }
.narr       { border-top:0.2mm solid #e2e8f0; margin-top:3mm; padding-top:2.2mm; }
.narr-tit   { font-size:6pt; font-weight:bold; color:#94a3b8; letter-spacing:.05em; margin-bottom:1.6mm; }
.anot       { margin-top:3mm; }
.anot-l     { border-bottom:0.2mm solid #e2e8f0; height:5mm; margin-bottom:1mm; }
.q          { border-top:0.2mm solid #f1f5f9; padding-top:2.5mm; margin-top:2.5mm; }
.q-tit      { font-weight:bold; color:#0f172a; margin-bottom:1.2mm; }
.ref        { margin-bottom:2.5mm; }
';
}

function trap_dir_cache(int $apId): ?string
{
    $dir = tr_dir_midia($apId) . '/apoio';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) { return null; }
    return $dir;
}

function trap_dir_tmp(): string
{
    $base = nv_config()['paths']['uploads'];
    $dir  = rtrim($base, '/\\') . '/treinamentos/_tmp';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return is_dir($dir) ? $dir : sys_get_temp_dir();
}

function trap_arquivo_nome(array $ap): string
{
    return 'apostila-' . tr_slugify((string)$ap['titulo']) . '.pdf';
}

function trap_gerar_pdf(PDO $pdo, array $ap, array $cfg): ?string
{
    $apId = (int)$ap['id'];
    $dir  = trap_dir_cache($apId);
    if ($dir === null) { return null; }

    $margem = 14;
    $corpo  = trap_corpo($pdo, $ap, $cfg);
    $css    = trap_css($margem);

    $hash    = substr(md5($corpo . $css . trap_titulo($ap)), 0, 12);
    $arquivo = $dir . '/apostila-' . $hash . '.pdf';
    if (is_file($arquivo)) { return $arquivo; }

    foreach ((array)glob($dir . '/apostila-*.pdf') as $velho) { @unlink($velho); }

    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'margin_left'       => 0,
            'margin_right'      => 0,
            'margin_top'        => 21,
            'margin_bottom'     => 16,
            'margin_header'     => 0,
            'margin_footer'     => 7,
            'default_font'      => 'dejavusans',
            'default_font_size' => 9,
            'tempDir'           => trap_dir_tmp(),
        ]);
        $mpdf->SetCreator('RH Digital');
        $mpdf->SetAuthor('Nórdica Veículos S/A');
        $mpdf->SetTitle(trap_titulo($ap));
        $mpdf->autoLangToFont   = false;
        $mpdf->autoScriptToLang = false;

        $mpdf->SetHTMLHeader(trap_header_html($ap));
        $mpdf->SetHTMLFooter(trap_footer_html($ap, $margem));

        $mpdf->WriteHTML($css,   \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($corpo, \Mpdf\HTMLParserMode::HTML_BODY);
        $mpdf->Output($arquivo, 'F');
    } catch (Throwable $e) {
        @unlink($arquivo);
        return null;
    }

    return is_file($arquivo) ? $arquivo : null;
}

function trap_entregar(PDO $pdo, array $ap, bool $baixar = false): void
{
    if (!trap_ativa($ap)) { http_response_code(404); exit; }

    $autoload = dirname(__DIR__, 3) . '/app/lib/vendor/autoload.php';
    if (!is_file($autoload)) {
        http_response_code(500);
        echo 'mPDF não está instalado no servidor. Execute "composer require mpdf/mpdf" dentro de app/lib e suba a pasta vendor/.';
        exit;
    }
    require_once $autoload;

    $pdf = trap_gerar_pdf($pdo, $ap, trap_cfg($ap));
    if ($pdf === null) {
        http_response_code(500);
        echo 'Não foi possível gerar a apostila. Tente novamente em alguns instantes.';
        exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Length: ' . (string)filesize($pdf));
    header('Content-Disposition: ' . ($baixar ? 'attachment' : 'inline')
        . '; filename="' . trap_arquivo_nome($ap) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=600');
    readfile($pdf);
    exit;
}
