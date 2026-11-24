<?php
if (!function_exists('trm_inscricao')) {
    require_once __DIR__ . '/_matriculas.php';
}

function trp_tipos(): array
{
    return [
        'unica'    => 'Múltipla escolha (uma resposta)',
        'multipla' => 'Múltipla escolha (várias respostas)',
        'vf'       => 'Verdadeiro ou falso',
    ];
}

function trp_questoes(PDO $pdo, int $apresentacaoId, bool $somenteAtivas = false): array
{
    $sql = 'SELECT * FROM treinamentos_questoes WHERE apresentacao_id = ?'
         . ($somenteAtivas ? ' AND ativa = 1' : '')
         . ' ORDER BY ordem, id';
    $st = $pdo->prepare($sql);
    $st->execute([$apresentacaoId]);
    $questoes = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$questoes) { return []; }

    $ids = array_map(static fn($q) => (int)$q['id'], $questoes);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $st = $pdo->prepare(
        "SELECT * FROM treinamentos_alternativas WHERE questao_id IN ($in) ORDER BY questao_id, ordem, id"
    );
    $st->execute($ids);

    $porQuestao = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $porQuestao[(int)$a['questao_id']][] = $a;
    }
    foreach ($questoes as &$q) {
        $q['alternativas'] = $porQuestao[(int)$q['id']] ?? [];
    }
    unset($q);

    return $questoes;
}

function trp_total_valido(PDO $pdo, int $apresentacaoId): int
{
    $n = 0;
    foreach (trp_questoes($pdo, $apresentacaoId, true) as $q) {
        if (trp_questao_valida($q)) { $n++; }
    }
    return $n;
}

const TRP_AMOSTRA_MIN = 5;

function trp_estatisticas_questoes(PDO $pdo, int $apresentacaoId): array
{
    $st = $pdo->prepare(
        'SELECT respostas_json FROM treinamentos_tentativas WHERE apresentacao_id = ?'
    );
    $st->execute([$apresentacaoId]);

    $cont = [];
    while (($json = $st->fetchColumn()) !== false) {
        $itens = json_decode((string)$json, true);
        if (!is_array($itens)) { continue; }
        foreach ($itens as $it) {
            $qid = (int)($it['questao_id'] ?? 0);
            if ($qid <= 0) { continue; }
            $cont[$qid]['respondidas'] = ($cont[$qid]['respondidas'] ?? 0) + 1;
            $cont[$qid]['acertos']     = ($cont[$qid]['acertos'] ?? 0) + (!empty($it['certa']) ? 1 : 0);
        }
    }

    $saida = [];
    foreach ($cont as $qid => $c) {
        $resp   = (int)$c['respondidas'];
        $acerto = (int)$c['acertos'];
        $pct    = $resp > 0 ? (int)round($acerto / $resp * 100) : 0;
        $saida[$qid] = [
            'respondidas' => $resp,
            'acertos'     => $acerto,
            'erros'       => $resp - $acerto,
            'pct_acerto'  => $pct,
            'pct_erro'    => 100 - $pct,
            'nivel'       => $resp < TRP_AMOSTRA_MIN ? 'amostra'
                           : ($pct >= 80 ? 'facil' : ($pct >= 50 ? 'medio' : 'dificil')),
        ];
    }
    return $saida;
}

function trp_questao_valida(array $q): bool
{
    $alts = $q['alternativas'] ?? [];
    if (count($alts) < 2) { return false; }
    foreach ($alts as $a) {
        if ((int)$a['correta'] === 1) { return true; }
    }
    return false;
}

function trp_prova_ativa(array $ap, PDO $pdo): bool
{
    return (int)($ap['prova_ativa'] ?? 0) === 1
        && trp_total_valido($pdo, (int)$ap['id']) > 0;
}

function trp_montar(PDO $pdo, array $ap): array
{
    $todas = array_values(array_filter(
        trp_questoes($pdo, (int)$ap['id'], true),
        'trp_questao_valida'
    ));

    if ((int)($ap['prova_embaralhar'] ?? 1) === 1) { shuffle($todas); }

    $sorteio = (int)($ap['prova_qtd_sorteada'] ?? 0);
    if ($sorteio > 0 && $sorteio < count($todas)) {
        $todas = array_slice($todas, 0, $sorteio);
    }

    $saida = [];
    $ids   = [];
    foreach ($todas as $q) {
        $alts = $q['alternativas'];
        if ((int)($ap['prova_embaralhar'] ?? 1) === 1) { shuffle($alts); }

        $saida[] = [
            'id'           => (int)$q['id'],
            'tipo'         => (string)$q['tipo'],
            'enunciado'    => (string)$q['enunciado'],
            'peso'         => max(1, (int)$q['peso']),
            'alternativas' => array_map(static fn($a) => [
                'id'    => (int)$a['id'],
                'texto' => (string)$a['texto'],
            ], $alts),
        ];
        $ids[] = (int)$q['id'];
    }

    return ['questoes' => $saida, 'ids' => $ids];
}

function trp_tentativas(PDO $pdo, int $inscricaoId): array
{
    $st = $pdo->prepare(
        'SELECT * FROM treinamentos_tentativas WHERE inscricao_id = ? ORDER BY numero DESC'
    );
    $st->execute([$inscricaoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function trp_pode_tentar(array $insc, array $ap): bool
{
    if ((int)$insc['prova_aprovado'] === 1) { return false; }
    $limite = (int)($ap['prova_tentativas'] ?? 0);
    if ($limite <= 0) { return true; }
    return (int)$insc['prova_tentativas_feitas'] < $limite;
}

function trp_corrigir(PDO $pdo, array $insc, array $ap, array $respostas, int $tempoSeg = 0, array $idsAplicadas = []): array
{
    $inscId = (int)$insc['id'];
    $apId   = (int)$ap['id'];

    $questoes = [];
    foreach (trp_questoes($pdo, $apId, true) as $q) {
        if (trp_questao_valida($q)) { $questoes[(int)$q['id']] = $q; }
    }

    $detalhe       = [];
    $pontos        = 0;
    $pontosTotais  = 0;
    $acertos       = 0;
    $avaliadas     = 0;

    $alvo = $idsAplicadas
        ? array_map('intval', $idsAplicadas)
        : array_map('intval', array_keys($respostas));

    foreach ($alvo as $qid) {
        if (!isset($questoes[$qid])) { continue; }

        $marcadas = $respostas[$qid] ?? [];
        $q        = $questoes[$qid];
        $peso  = max(1, (int)$q['peso']);
        $avaliadas++;
        $pontosTotais += $peso;

        $corretas = [];
        foreach ($q['alternativas'] as $a) {
            if ((int)$a['correta'] === 1) { $corretas[] = (int)$a['id']; }
        }

        $marcadas = array_values(array_unique(array_map('intval', (array)$marcadas)));
        sort($marcadas);
        sort($corretas);

        $certa = ($marcadas === $corretas);
        if ($certa) { $acertos++; $pontos += $peso; }

        $textos = [];
        foreach ($q['alternativas'] as $a) { $textos[(int)$a['id']] = (string)$a['texto']; }

        $detalhe[] = [
            'questao_id'  => $qid,
            'enunciado'   => (string)$q['enunciado'],
            'marcadas'    => $marcadas,
            'corretas'    => $corretas,
            'certa'       => $certa,
            'peso'        => $peso,
            'explicacao'  => (string)$q['explicacao'],
            'alternativas' => $textos,
        ];
    }

    $pct      = $pontosTotais > 0 ? (int)round(($pontos / $pontosTotais) * 100) : 0;
    $minima   = max(0, min(100, (int)($ap['prova_nota_minima'] ?? 70)));
    $aprovado = $pct >= $minima;

    $numero = (int)$insc['prova_tentativas_feitas'] + 1;

    $st = $pdo->prepare(
        'INSERT INTO treinamentos_tentativas
            (inscricao_id, apresentacao_id, numero, questoes, acertos, pontos, pontos_totais,
             percentual, aprovado, respostas_json, tempo_seg, ip)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        $inscId, $apId, $numero, $avaliadas, $acertos, $pontos, $pontosTotais,
        $pct, $aprovado ? 1 : 0,
        json_encode($detalhe, JSON_UNESCAPED_UNICODE),
        max(0, min(86400, $tempoSeg)),
        nv_client_ip(),
    ]);

    $st = $pdo->prepare(
        'UPDATE treinamentos_inscricoes
            SET prova_tentativas_feitas = ?,
                prova_percentual = GREATEST(prova_percentual, ?),
                prova_aprovado   = GREATEST(prova_aprovado, ?),
                prova_concluida_em = CASE WHEN ? = 1 THEN NOW() ELSE prova_concluida_em END
          WHERE id = ? LIMIT 1'
    );
    $st->execute([$numero, $pct, $aprovado ? 1 : 0, $aprovado ? 1 : 0, $inscId]);

    trm_log($pdo, $inscId, $aprovado ? 'prova_aprovado' : 'prova_reprovado',
        'Tentativa ' . $numero . ' — ' . $pct . '%');

    $limite = (int)($ap['prova_tentativas'] ?? 0);

    return [
        'percentual'    => $pct,
        'nota_minima'   => $minima,
        'aprovado'      => $aprovado,
        'acertos'       => $acertos,
        'questoes'      => $avaliadas,
        'tentativa'     => $numero,
        'restantes'     => $limite > 0 ? max(0, $limite - $numero) : null,
        'pode_repetir'  => !$aprovado && ($limite <= 0 || $numero < $limite),
        'gabarito'      => (int)($ap['prova_mostrar_gabarito'] ?? 1) === 1 ? $detalhe : [],
    ];
}

function trp_requisitos(PDO $pdo, array $insc, array $ap): array
{
    $temProva = trp_prova_ativa($ap, $pdo);

    $conteudoOk = (int)$insc['slides_total'] > 0
        && (int)$insc['slides_vistos'] >= (int)$insc['slides_total'];

    if (!$conteudoOk) {
        $ids = trm_slides_ids($pdo, (int)$ap['id']);
        if ($ids) {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM treinamentos_progresso
                  WHERE inscricao_id = ? AND concluido = 1 AND slide_id IN ('
                . implode(',', array_fill(0, count($ids), '?')) . ')'
            );
            $st->execute(array_merge([(int)$insc['id']], $ids));
            $conteudoOk = ((int)$st->fetchColumn()) >= count($ids);
        }
    }

    return [
        'tem_prova'   => $temProva,
        'conteudo_ok' => $conteudoOk,
        'prova_ok'    => !$temProva || (int)$insc['prova_aprovado'] === 1,
        'completo'    => $conteudoOk && (!$temProva || (int)$insc['prova_aprovado'] === 1),
    ];
}
