<?php
require_once APP_PATH . '/lib/mailer.php';
if (!function_exists('tr_url')) {
    require_once __DIR__ . '/_lib.php';
}

function trm_pode_ver(): bool
{
    return tr_pode('treinamentos.matriculas.ver')
        || tr_pode('treinamentos.apresentacoes.relatorios');
}

function trm_pode_gerenciar(): bool
{
    return tr_pode('treinamentos.matriculas.gerenciar')
        || tr_pode('treinamentos.apresentacoes.publicar');
}

function trm_exigir_gerenciar_ajax(): void
{
    if (!trm_pode_gerenciar()) {
        nv_json(['ok' => false, 'msg' => 'Sem permissão para gerenciar matrículas.'], 403);
    }
}

function trm_token_novo(): string
{
    return bin2hex(random_bytes(20));
}

function trm_certificado_codigo(int $apresentacaoId): string
{
    return 'NV-' . date('Y') . '-' . str_pad((string)$apresentacaoId, 3, '0', STR_PAD_LEFT)
        . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function trm_agente(): string
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function trm_cpf_limpo(string $cpf): string
{
    return preg_replace('/\D+/', '', $cpf) ?? '';
}

function trm_cpf_mascarado(string $cpf): string
{
    $d = trm_cpf_limpo($cpf);
    if (strlen($d) !== 11) { return ''; }
    return '***.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-**';
}

function trm_tempo_fmt(int $segundos): string
{
    if ($segundos <= 0) { return '—'; }
    $h = intdiv($segundos, 3600);
    $m = intdiv($segundos % 3600, 60);
    if ($h > 0) { return $h . 'h ' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . 'min'; }
    if ($m > 0) { return $m . 'min'; }
    return $segundos . 's';
}

function trm_status_rotulo(string $status): string
{
    return [
        'pendente'     => 'Não iniciado',
        'em_andamento' => 'Em andamento',
        'concluido'    => 'Concluído',
        'cancelado'    => 'Cancelado',
    ][$status] ?? $status;
}

function trm_link_token(string $token): string
{
    return tr_url_absoluta('treinar.php') . '?t=' . rawurlencode($token);
}

function trm_link_certificado(string $codigo): string
{
    return tr_url_absoluta('certificado.php') . '?c=' . rawurlencode($codigo);
}

function trm_inscricao(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM treinamentos_inscricoes WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function trm_inscricao_por_token(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) { return null; }
    $st = $pdo->prepare('SELECT * FROM treinamentos_inscricoes WHERE token = ? LIMIT 1');
    $st->execute([$token]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function trm_inscricao_por_certificado(PDO $pdo, string $codigo): ?array
{
    if (!preg_match('/^[A-Z0-9\-]{6,24}$/i', $codigo)) { return null; }
    $st = $pdo->prepare(
        'SELECT * FROM treinamentos_inscricoes
          WHERE certificado_codigo = ? AND status = "concluido" LIMIT 1'
    );
    $st->execute([strtoupper($codigo)]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function trm_existente(PDO $pdo, int $apresentacaoId, string $cpf, string $email): ?array
{
    $cpf = trm_cpf_limpo($cpf);
    if ($cpf !== '') {
        $st = $pdo->prepare(
            'SELECT * FROM treinamentos_inscricoes
              WHERE apresentacao_id = ? AND REPLACE(REPLACE(REPLACE(cpf,".",""),"-","")," ","") = ? LIMIT 1'
        );
        $st->execute([$apresentacaoId, $cpf]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) { return $r; }
    }
    $email = trim(mb_strtolower($email));
    if ($email !== '') {
        $st = $pdo->prepare(
            'SELECT * FROM treinamentos_inscricoes
              WHERE apresentacao_id = ? AND LOWER(email) = ? LIMIT 1'
        );
        $st->execute([$apresentacaoId, $email]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) { return $r; }
    }
    return null;
}

function trm_slides_ids(PDO $pdo, int $apresentacaoId): array
{
    $st = $pdo->prepare('SELECT id FROM treinamentos_slides WHERE apresentacao_id = ? ORDER BY ordem, id');
    $st->execute([$apresentacaoId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function trm_progresso(PDO $pdo, int $inscricaoId): array
{
    $st = $pdo->prepare('SELECT * FROM treinamentos_progresso WHERE inscricao_id = ?');
    $st->execute([$inscricaoId]);
    $saida = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $saida[(int)$r['slide_id']] = $r;
    }
    return $saida;
}

function trm_log(PDO $pdo, int $inscricaoId, string $evento, string $detalhe = ''): void
{
    $uid = function_exists('current_user_id') ? (current_user_id() ?: null) : null;
    $st = $pdo->prepare(
        'INSERT INTO treinamentos_inscricoes_log (inscricao_id, evento, detalhe, usuario_id, ip)
         VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([$inscricaoId, mb_substr($evento, 0, 40), mb_substr($detalhe, 0, 255), $uid, nv_client_ip()]);
}

function trm_colab_select(): string
{
    return 'SELECT c.id, c.matricula, c.nome, c.cpf, c.email, c.cargo,
                   c.ativo, c.status_codigo, c.status_descricao, c.foto_path,
                   f.nome AS filial
              FROM colaboradores c
         LEFT JOIN filiais f ON f.id = c.filial_id';
}

function trm_colab_ativo(array $c): bool
{
    return (int)$c['ativo'] === 1
        && ((string)($c['status_codigo'] ?? '') !== '7');
}

function trm_colab_desligado(array $c): bool
{
    return (string)($c['status_codigo'] ?? '') === '7';
}

function trm_sql_sem_desligados(string $alias = 'i'): string
{
    return "NOT EXISTS (SELECT 1 FROM colaboradores cdl\n"
         . "             WHERE cdl.id = {$alias}.colaborador_id AND cdl.status_codigo = '7')";
}

const TRM_MSG_DESLIGADO = 'Este acesso está encerrado. Colaboradores desligados não têm '
                        . 'acesso aos treinamentos. Em caso de dúvida, procure o RH.';

function trm_cancelar_desligados(PDO $pdo, int $apresentacaoId = 0, int $inscricaoId = 0): int
{
    $sql = "SELECT i.id
              FROM treinamentos_inscricoes i
              JOIN colaboradores c ON c.id = i.colaborador_id
             WHERE c.status_codigo = '7'
               AND i.status IN ('pendente','em_andamento')";
    $params = [];
    if ($apresentacaoId > 0) { $sql .= ' AND i.apresentacao_id = ?'; $params[] = $apresentacaoId; }
    if ($inscricaoId > 0)    { $sql .= ' AND i.id = ?';              $params[] = $inscricaoId; }

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) { return 0; }

    $in = implode(',', array_fill(0, count($ids), '?'));
    $up = $pdo->prepare(
        "UPDATE treinamentos_inscricoes SET status = 'cancelado'
          WHERE id IN ($in) AND status IN ('pendente','em_andamento')"
    );
    $up->execute($ids);

    foreach ($ids as $id) {
        trm_log($pdo, $id, 'cancelado_desligamento', 'Colaborador desligado (status 7)');
    }
    return count($ids);
}

function trm_insc_desligada(PDO $pdo, array $insc): bool
{
    $colabId = (int)($insc['colaborador_id'] ?? 0);
    if ($colabId <= 0) { return false; }

    $colab = trm_colab($pdo, $colabId);
    if (!$colab || !trm_colab_desligado($colab)) { return false; }

    if (in_array((string)$insc['status'], ['pendente', 'em_andamento'], true)) {
        trm_cancelar_desligados($pdo, 0, (int)$insc['id']);
    }
    return true;
}

function trm_colab_por_matricula(PDO $pdo, string $matricula): ?array
{
    $matricula = trim($matricula);
    if ($matricula === '') { return null; }

    $st = $pdo->prepare(trm_colab_select() . ' WHERE c.matricula = ? LIMIT 1');
    $st->execute([$matricula]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r) { return $r; }

    $st = $pdo->prepare(
        trm_colab_select() . ' WHERE CAST(c.matricula AS UNSIGNED) = ? AND c.matricula REGEXP "^[0-9]+$" LIMIT 1'
    );
    $st->execute([(int)preg_replace('/\D+/', '', $matricula)]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function trm_colab(PDO $pdo, int $id): ?array
{
    if ($id <= 0) { return null; }
    $st = $pdo->prepare(trm_colab_select() . ' WHERE c.id = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function trm_colab_buscar(PDO $pdo, string $termo, int $apresentacaoId, int $limite = 12): array
{
    $termo = trim($termo);
    if (mb_strlen($termo) < 2) { return []; }

    $digitos = preg_replace('/\D+/', '', $termo) ?? '';
    $like    = '%' . $termo . '%';

    $sql = trm_colab_select()
         . ' WHERE c.ativo = 1 AND (c.status_codigo IS NULL OR c.status_codigo <> "7")
               AND (c.nome LIKE ? OR c.matricula LIKE ?'
         . ($digitos !== '' ? ' OR c.cpf LIKE ?' : '')
         . ')
          ORDER BY CASE WHEN c.matricula = ? THEN 0 ELSE 1 END, c.nome
             LIMIT ' . (int)$limite;

    $params = [$like, $like];
    if ($digitos !== '') { $params[] = '%' . $digitos . '%'; }
    $params[] = $termo;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $achados = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$achados) { return []; }

    $ids = array_map(static fn($c) => (int)$c['id'], $achados);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare(
        "SELECT colaborador_id FROM treinamentos_inscricoes
          WHERE apresentacao_id = ? AND colaborador_id IN ($in)"
    );
    $st->execute(array_merge([$apresentacaoId], $ids));
    $jaTem = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));

    foreach ($achados as &$c) {
        $c['matriculado'] = isset($jaTem[(int)$c['id']]);
    }
    unset($c);

    return $achados;
}

function trm_criar(PDO $pdo, array $ap, array $colab, string $prazo = ''): array
{
    $apId  = (int)$ap['id'];
    $colId = (int)$colab['id'];

    $st = $pdo->prepare(
        'SELECT * FROM treinamentos_inscricoes
          WHERE apresentacao_id = ? AND colaborador_id = ? LIMIT 1'
    );
    $st->execute([$apId, $colId]);
    $jaTem = $st->fetch(PDO::FETCH_ASSOC);
    if ($jaTem) {
        return ['id' => (int)$jaTem['id'], 'token' => (string)$jaTem['token'], 'novo' => false];
    }

    if (!trm_colab_ativo($colab)) {
        throw new InvalidArgumentException(
            $colab['nome'] . ' está inativo no cadastro'
            . (($colab['status_descricao'] ?? '') !== '' ? ' (' . $colab['status_descricao'] . ')' : '') . '.'
        );
    }

    $prazo = trim($prazo);
    if ($prazo === '' && (int)($ap['prazo_dias'] ?? 0) > 0) {
        $prazo = date('Y-m-d', strtotime('+' . (int)$ap['prazo_dias'] . ' days'));
    }
    if ($prazo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazo)) { $prazo = ''; }

    $st = $pdo->prepare(
        'INSERT INTO treinamentos_inscricoes
            (apresentacao_id, colaborador_id, matricula, nome, cpf, email, cargo, filial,
             token, prazo, slides_total, criado_por)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        $apId,
        $colId,
        (string)$colab['matricula'],
        mb_substr((string)$colab['nome'], 0, 140),
        (string)$colab['cpf'] !== '' ? nv_format_cpf((string)$colab['cpf']) : '',
        mb_substr((string)($colab['email'] ?? ''), 0, 160),
        mb_substr((string)($colab['cargo'] ?? ''), 0, 120),
        mb_substr((string)($colab['filial'] ?? ''), 0, 120),
        trm_token_novo(),
        $prazo !== '' ? $prazo : null,
        count(trm_slides_ids($pdo, $apId)),
        function_exists('current_user_id') ? (current_user_id() ?: null) : null,
    ]);

    $id = (int)$pdo->lastInsertId();
    $insc = trm_inscricao($pdo, $id);
    trm_log($pdo, $id, 'matricula_criada', $colab['matricula'] . ' — ' . $colab['nome']);

    return ['id' => $id, 'token' => (string)$insc['token'], 'novo' => true];
}

function trm_importar_matriculas(PDO $pdo, array $ap, string $texto, string $prazo = ''): array
{
    $partes = preg_split('/[\s,;]+/', trim($texto)) ?: [];
    $partes = array_values(array_filter(array_map('trim', $partes), static fn($v) => $v !== ''));

    $criados = 0; $repetidos = 0; $erros = []; $novos = [];
    $vistos = [];

    foreach ($partes as $mat) {
        if (isset($vistos[$mat])) { continue; }
        $vistos[$mat] = true;

        $colab = trm_colab_por_matricula($pdo, $mat);
        if (!$colab) {
            $erros[] = 'Matrícula ' . $mat . ' não existe no cadastro.';
            continue;
        }

        try {
            $r = trm_criar($pdo, $ap, $colab, $prazo);
            if ($r['novo']) { $criados++; $novos[] = $r['id']; }
            else { $repetidos++; }
        } catch (Throwable $e) {
            $erros[] = nv_safe_err($e);
        }
    }

    return ['criados' => $criados, 'repetidos' => $repetidos, 'erros' => $erros, 'novos' => $novos];
}

function trm_sincronizar(PDO $pdo, array $insc): bool
{
    $colab = trm_colab($pdo, (int)($insc['colaborador_id'] ?? 0));
    if (!$colab) { return false; }

    $st = $pdo->prepare(
        'UPDATE treinamentos_inscricoes
            SET matricula = ?, nome = ?, cpf = ?, email = ?, cargo = ?, filial = ?
          WHERE id = ? LIMIT 1'
    );
    $st->execute([
        (string)$colab['matricula'],
        mb_substr((string)$colab['nome'], 0, 140),
        (string)$colab['cpf'] !== '' ? nv_format_cpf((string)$colab['cpf']) : '',
        mb_substr((string)($colab['email'] ?? ''), 0, 160),
        mb_substr((string)($colab['cargo'] ?? ''), 0, 120),
        mb_substr((string)($colab['filial'] ?? ''), 0, 120),
        (int)$insc['id'],
    ]);
    trm_log($pdo, (int)$insc['id'], 'dados_sincronizados', (string)$colab['matricula']);
    return true;
}

function trm_validar_acesso(array $insc, array $ap): array
{
    if ($insc['status'] === 'cancelado') {
        return ['ok' => false, 'motivo' => 'cancelado',
                'msg' => 'Esta matrícula foi cancelada. Procure o RH para receber um novo link.'];
    }
    if (($ap['status'] ?? '') === 'arquivado') {
        return ['ok' => false, 'motivo' => 'arquivado',
                'msg' => 'Este treinamento foi encerrado e não está mais disponível.'];
    }
    if (($ap['status'] ?? '') !== 'publicado') {
        return ['ok' => false, 'motivo' => 'rascunho',
                'msg' => 'Este treinamento ainda não foi liberado. Tente novamente mais tarde.'];
    }
    if ($insc['status'] !== 'concluido'
        && !empty($insc['prazo'])
        && $insc['prazo'] < date('Y-m-d')) {
        return ['ok' => false, 'motivo' => 'prazo',
                'msg' => 'O prazo para realizar este treinamento venceu em '
                       . date('d/m/Y', strtotime((string)$insc['prazo']))
                       . '. Procure o RH para reabrir o seu acesso.'];
    }
    return ['ok' => true, 'motivo' => '', 'msg' => ''];
}

function trm_registrar_acesso(PDO $pdo, array $insc, int $slidesTotal): void
{
    $primeiro = empty($insc['iniciado_em']);
    $st = $pdo->prepare(
        'UPDATE treinamentos_inscricoes
            SET acessos = acessos + 1,
                ultimo_acesso_em = NOW(),
                slides_total = ?,
                iniciado_em = COALESCE(iniciado_em, NOW()),
                status = CASE WHEN status = "pendente" THEN "em_andamento" ELSE status END,
                ip = ?, user_agent = ?
          WHERE id = ? LIMIT 1'
    );
    $st->execute([$slidesTotal, nv_client_ip(), trm_agente(), (int)$insc['id']]);

    if ($primeiro) { trm_log($pdo, (int)$insc['id'], 'primeiro_acesso', nv_client_ip()); }
}

function trm_salvar_progresso(PDO $pdo, array $insc, array $d): array
{
    $inscId  = (int)$insc['id'];
    $slideId = (int)($d['slide_id'] ?? 0);
    $tempo   = max(0, min(3600, (int)($d['tempo_seg'] ?? 0)));

    if ($slideId > 0) {
        $st = $pdo->prepare(
            'INSERT INTO treinamentos_progresso
                (inscricao_id, slide_id, slide_indice, concluido, dialogo_max, tempo_seg)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                slide_indice = VALUES(slide_indice),
                concluido    = GREATEST(treinamentos_progresso.concluido, VALUES(concluido)),
                dialogo_max  = GREATEST(treinamentos_progresso.dialogo_max, VALUES(dialogo_max)),
                tempo_seg    = treinamentos_progresso.tempo_seg + VALUES(tempo_seg)'
        );
        $st->execute([
            $inscId,
            $slideId,
            max(0, (int)($d['slide_indice'] ?? 0)),
            !empty($d['concluido']) ? 1 : 0,
            max(0, (int)($d['dialogo_max'] ?? 0)),
            $tempo,
        ]);
    }

    $ids   = trm_slides_ids($pdo, (int)$insc['apresentacao_id']);
    $total = max(1, count($ids));

    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM treinamentos_progresso
          WHERE inscricao_id = ? AND concluido = 1 AND slide_id IN ('
        . implode(',', array_fill(0, $total, '?')) . ')'
    );
    $st->execute(array_merge([$inscId], $ids ?: [0]));
    $vistos = (int)$st->fetchColumn();

    $pct = (int)round(($vistos / $total) * 100);

    $st = $pdo->prepare(
        'UPDATE treinamentos_inscricoes
            SET slides_total  = ?,
                slides_vistos = ?,
                percentual    = ?,
                slide_indice  = GREATEST(slide_indice, ?),
                tempo_seg     = tempo_seg + ?,
                ultimo_acesso_em = NOW(),
                status = CASE WHEN status IN ("pendente","em_andamento") THEN "em_andamento" ELSE status END
          WHERE id = ? LIMIT 1'
    );
    $st->execute([
        count($ids), $vistos, $pct, max(0, (int)($d['slide_indice'] ?? 0)), $tempo, $inscId,
    ]);

    return [
        'percentual'  => $pct,
        'vistos'      => $vistos,
        'total'       => count($ids),
        'pode_concluir' => $vistos >= count($ids) && count($ids) > 0,
    ];
}

function trm_concluir(PDO $pdo, array $insc, array $ap, bool $termo): array
{
    $inscId = (int)$insc['id'];

    if ($insc['status'] === 'concluido') {
        return ['ok' => true, 'codigo' => (string)$insc['certificado_codigo'], 'repetido' => true];
    }

    $ids   = trm_slides_ids($pdo, (int)$insc['apresentacao_id']);
    $total = count($ids);

    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM treinamentos_progresso WHERE inscricao_id = ? AND concluido = 1'
    );
    $st->execute([$inscId]);
    $vistos = (int)$st->fetchColumn();

    if ($total === 0 || $vistos < $total) {
        return ['ok' => false, 'msg' => 'Ainda faltam etapas para concluir o treinamento.'];
    }
    if (function_exists('trp_prova_ativa') && trp_prova_ativa($ap, $pdo) && (int)$insc['prova_aprovado'] !== 1) {
        return ['ok' => false, 'msg' => 'É preciso ser aprovado na prova para concluir o treinamento.'];
    }
    if ((int)($ap['exige_termo'] ?? 1) === 1 && !$termo) {
        return ['ok' => false, 'msg' => 'É preciso confirmar a declaração de conclusão.'];
    }

    $codigo = (string)$insc['certificado_codigo'];
    if ($codigo === '') { $codigo = trm_certificado_codigo((int)$insc['apresentacao_id']); }

    $st = $pdo->prepare(
        'UPDATE treinamentos_inscricoes
            SET status = "concluido",
                concluido_em = NOW(),
                percentual = 100,
                slides_vistos = ?,
                slides_total = ?,
                termo_aceito = ?,
                certificado_codigo = ?,
                ip = ?, user_agent = ?
          WHERE id = ? LIMIT 1'
    );
    $st->execute([$vistos, $total, $termo ? 1 : 0, $codigo, nv_client_ip(), trm_agente(), $inscId]);

    trm_log($pdo, $inscId, 'concluido', 'Código ' . $codigo);

    try {
        $atualizada = trm_inscricao($pdo, $inscId);
        if ($atualizada) { trm_email_conclusao($pdo, $atualizada, $ap); }
    } catch (Throwable $e) {  }

    return ['ok' => true, 'codigo' => $codigo, 'repetido' => false];
}

function trm_concluir_externo(
    PDO $pdo, array $insc, array $ap, string $dataConclusao, string $obs,
    ?string $arquivoNome, ?string $arquivoOriginal
): void {
    $inscId   = (int)$insc['id'];
    $total    = count(trm_slides_ids($pdo, (int)$insc['apresentacao_id']));
    $dataHora = $dataConclusao . ' 12:00:00';

    $campos = [
        'status'        => 'concluido',
        'concluido_em'  => $dataHora,
        'iniciado_em'   => $insc['iniciado_em'] ?: $dataHora,
        'percentual'    => 100,
        'slides_vistos' => $total,
        'slides_total'  => $total,
    ];
    if (array_key_exists('concluido_origem', $insc)) {
        $campos['concluido_origem'] = 'externo';
    }
    if (array_key_exists('certificado_externo_data', $insc)) {
        $campos['certificado_externo_data'] = $dataConclusao;
    }
    if (array_key_exists('certificado_externo_obs', $insc)) {
        $campos['certificado_externo_obs'] = $obs;
    }
    if ($arquivoNome !== null && array_key_exists('certificado_externo_arquivo', $insc)) {
        $campos['certificado_externo_arquivo'] = $arquivoNome;
        $campos['certificado_externo_nome']    = $arquivoOriginal;
    }

    $sets = [];
    $vals = [];
    foreach ($campos as $coluna => $valor) { $sets[] = "$coluna = ?"; $vals[] = $valor; }
    $vals[] = $inscId;

    $st = $pdo->prepare('UPDATE treinamentos_inscricoes SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    $st->execute($vals);

    trm_log($pdo, $inscId, 'concluido_externo', $obs !== '' ? mb_substr($obs, 0, 255) : 'Certificado de outra plataforma');
}

function trm_reiniciar(PDO $pdo, int $inscricaoId): void
{
    $st = $pdo->prepare('DELETE FROM treinamentos_progresso WHERE inscricao_id = ?');
    $st->execute([$inscricaoId]);

    $st = $pdo->prepare(
        'UPDATE treinamentos_inscricoes
            SET status = "pendente", slides_vistos = 0, slide_indice = 0, percentual = 0,
                tempo_seg = 0, iniciado_em = NULL, concluido_em = NULL,
                certificado_codigo = "", termo_aceito = 0
          WHERE id = ? LIMIT 1'
    );
    $st->execute([$inscricaoId]);
    trm_log($pdo, $inscricaoId, 'reiniciado');
}

function trm_email_enviar(PDO $pdo, string $para, string $assunto, string $html): bool
{
    if ($para === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) { return false; }

    require_once dirname(__DIR__, 3) . '/app/lib/mailer.php';

    if (!function_exists('nv_send_mail_smtp')) { return false; }

    try {
        nv_send_mail_smtp($pdo, $para, $assunto, $html);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function trm_email_layout(array $ap, string $titulo, string $corpo, string $botaoTexto = '', string $botaoUrl = ''): string
{
    return nv_email_layout([
        'titulo'      => $titulo,
        'corpo'       => $corpo,
        'cor'         => (string)($ap['cor_primaria'] ?? '#1F3B63'),
        'modulo'      => 'Nórdica Veículos — Treinamentos',
        'botao_label' => $botaoTexto,
        'botao_url'   => $botaoUrl,
        'rodape'      => 'Mensagem automática do RH Digital. Não é necessário responder.',
    ]);
}

function trm_email_convite(PDO $pdo, array $insc, array $ap, bool $reenvio = false): bool
{
    $link  = trm_link_token((string)$insc['token']);
    $nome  = htmlspecialchars((string)$insc['nome']);
    $prazo = !empty($insc['prazo'])
        ? '<p style="margin:0 0 14px"><strong>Prazo para conclusão:</strong> '
          . date('d/m/Y', strtotime((string)$insc['prazo'])) . '</p>'
        : '';
    $carga = (int)($ap['carga_horaria_min'] ?? 0) > 0
        ? '<p style="margin:0 0 14px"><strong>Duração estimada:</strong> '
          . (int)$ap['carga_horaria_min'] . ' minutos</p>'
        : '';
    $desc = trim((string)($ap['descricao'] ?? ''));

    $corpo = '<p style="margin:0 0 14px">Olá, <strong>' . $nome . '</strong>.</p>'
        . '<p style="margin:0 0 14px">'
        . ($reenvio ? 'Segue novamente o seu acesso ao treinamento ' : 'Você foi matriculado no treinamento ')
        . '<strong>' . htmlspecialchars((string)$ap['titulo']) . '</strong>.</p>'
        . ($desc !== '' ? '<p style="margin:0 0 14px;color:#5a6478">' . nl2br(htmlspecialchars($desc)) . '</p>' : '')
        . $carga . $prazo
        . '<p style="margin:0 0 14px">O link abaixo é <strong>pessoal e intransferível</strong> — '
        . 'ele registra o seu progresso e permite retomar de onde você parou.</p>';

    return trm_email_enviar(
        $pdo,
        (string)$insc['email'],
        ($reenvio ? 'Reenvio de acesso — ' : 'Treinamento: ') . (string)$ap['titulo'],
        trm_email_layout($ap, 'Seu acesso ao treinamento', $corpo, 'Acessar o treinamento', $link)
    );
}

function trm_email_conclusao(PDO $pdo, array $insc, array $ap): void
{
    $nome   = htmlspecialchars((string)$insc['nome']);
    $codigo = (string)$insc['certificado_codigo'];
    $temCert = (int)($ap['emitir_certificado'] ?? 1) === 1 && $codigo !== '';
    $msg = trim((string)($ap['mensagem_conclusao'] ?? ''));

    $nota = ((int)($ap['prova_ativa'] ?? 0) === 1 && (int)$insc['prova_aprovado'] === 1)
        ? '<p style="margin:0 0 14px">Aproveitamento na prova: <strong>'
          . (int)$insc['prova_percentual'] . '%</strong>.</p>'
        : '';

    $corpo = '<p style="margin:0 0 14px">Parabéns, <strong>' . $nome . '</strong>!</p>'
        . '<p style="margin:0 0 14px">Você concluiu o treinamento <strong>'
        . htmlspecialchars((string)$ap['titulo']) . '</strong> em '
        . date('d/m/Y \à\s H:i') . '.</p>'
        . $nota
        . ($msg !== '' ? '<p style="margin:0 0 14px;color:#5a6478">' . nl2br(htmlspecialchars($msg)) . '</p>' : '')
        . ($temCert ? '<p style="margin:0 0 14px">Código do certificado: <strong>' . htmlspecialchars($codigo) . '</strong></p>' : '');

    trm_email_enviar(
        $pdo,
        (string)$insc['email'],
        'Treinamento concluído — ' . (string)$ap['titulo'],
        trm_email_layout($ap, 'Treinamento concluído', $corpo,
            $temCert ? 'Ver certificado' : '', $temCert ? trm_link_certificado($codigo) : '')
    );

    $paraRh = trim((string)($ap['email_conclusao'] ?? ''));
    if ($paraRh === '') { return; }

    $corpoRh = '<p style="margin:0 0 14px"><strong>' . $nome . '</strong> concluiu o treinamento <strong>'
        . htmlspecialchars((string)$ap['titulo']) . '</strong>.</p>'
        . '<table cellpadding="0" cellspacing="0" style="font:14px/1.7 Arial,sans-serif;color:#2c3547">'
        . '<tr><td style="padding-right:14px;color:#8a94a6">CPF</td><td>' . htmlspecialchars((string)$insc['cpf']) . '</td></tr>'
        . '<tr><td style="padding-right:14px;color:#8a94a6">Filial</td><td>' . htmlspecialchars((string)$insc['filial']) . '</td></tr>'
        . '<tr><td style="padding-right:14px;color:#8a94a6">Cargo</td><td>' . htmlspecialchars((string)$insc['cargo']) . '</td></tr>'
        . '<tr><td style="padding-right:14px;color:#8a94a6">Tempo total</td><td>' . trm_tempo_fmt((int)$insc['tempo_seg']) . '</td></tr>'
        . ((int)($ap['prova_ativa'] ?? 0) === 1
            ? '<tr><td style="padding-right:14px;color:#8a94a6">Prova</td><td>'
              . (int)$insc['prova_percentual'] . '% em '
              . (int)$insc['prova_tentativas_feitas'] . ' tentativa(s)</td></tr>'
            : '')
        . '<tr><td style="padding-right:14px;color:#8a94a6">Certificado</td><td>' . htmlspecialchars($codigo) . '</td></tr>'
        . '</table>';

    foreach (preg_split('/[;,]/', $paraRh) ?: [] as $dest) {
        trm_email_enviar($pdo, trim($dest), 'Conclusão de treinamento — ' . (string)$insc['nome'],
            trm_email_layout($ap, 'Conclusão registrada', $corpoRh));
    }
}

function trm_reescrever_midia(array &$trDados, array $ap, string $baseUrl): void
{
    $trDados['logo'] = $ap['logo_arquivo']
        ? $baseUrl . '&midia=' . rawurlencode((string)$ap['logo_arquivo'])
        : '';

    $padraoFundo = '#url\(\'[^\']*midia\.php\?a=\d+&f=([^\']+)\'\)#';
    $padraoImg   = '#(<(?:img|video)[^>]+src=["\'])[^"\']*midia\.php\?a=\d+(?:&|&amp;)f=([^"\']+)(["\'])#i';

    foreach ($trDados['slides'] as $i => $s) {
        if (strpos((string)$s['estilo'], 'midia.php') !== false) {
            $trDados['slides'][$i]['estilo'] = preg_replace(
                $padraoFundo,
                "url('" . $baseUrl . "&midia=$1')",
                (string)$s['estilo']
            );
        }

        if (strpos((string)$s['conteudo'], 'midia.php') !== false) {
            $trDados['slides'][$i]['conteudo'] = preg_replace(
                $padraoImg,
                '$1' . $baseUrl . '&midia=$2$3',
                (string)$s['conteudo']
            );
        }
    }
}
