<?php
if (!function_exists('trm_cpf_limpo')) {
    require_once __DIR__ . '/_matriculas.php';
}

const TRPO_CHAVE      = 'tr_portal';
const TRPO_TENTATIVAS = 'tr_portal_tentativas';
const TRPO_MAX_ERROS  = 6;
const TRPO_JANELA_SEG = 900;
const TRPO_INATIVIDADE = 7200;

function trpo_sessao_iniciar(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

const TRPO_COL_NASCIMENTO = 'data_nascimento';

function trpo_col_nascimento(PDO $pdo): string
{
    static $col = null;
    if ($col !== null) { return $col; }

    $col = '';
    try {
        $st = $pdo->query("SHOW COLUMNS FROM colaboradores LIKE '" . TRPO_COL_NASCIMENTO . "'");
        if ($st && $st->fetch()) { $col = TRPO_COL_NASCIMENTO; }
    } catch (Throwable $e) {
        $col = '';
    }

    return $col;
}

function trpo_sql_cpf(string $coluna = 'c.cpf'): string
{
    return "LPAD(REPLACE(REPLACE(REPLACE(TRIM($coluna), '.', ''), '-', ''), ' ', ''), 11, '0')";
}

function trpo_data_valida(string $data): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $m)) { return false; }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

function trpo_normalizar_data(string $bruto): string
{
    $bruto = trim($bruto);
    if ($bruto === '') { return ''; }
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $bruto, $m)) {
        $bruto = $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return trpo_data_valida($bruto) ? $bruto : '';
}

function trpo_log(PDO $pdo, ?int $colaboradorId, string $cpf, string $resultado, string $detalhe = ''): void
{
    try {
        $st = $pdo->prepare(
            'INSERT INTO treinamentos_portal_acessos
                (colaborador_id, cpf, resultado, detalhe, ip, user_agent)
             VALUES (?,?,?,?,?,?)'
        );
        $st->execute([
            $colaboradorId,
            mb_substr(nv_format_cpf($cpf), 0, 14),
            $resultado,
            mb_substr($detalhe, 0, 190),
            nv_client_ip(),
            trm_agente(),
        ]);
    } catch (Throwable $e) {
    }
}

function trpo_bloqueado(): bool
{
    trpo_sessao_iniciar();
    $t = $_SESSION[TRPO_TENTATIVAS] ?? null;
    if (!$t) { return false; }
    if ((time() - (int)$t['em']) > TRPO_JANELA_SEG) {
        unset($_SESSION[TRPO_TENTATIVAS]);
        return false;
    }
    return (int)$t['n'] >= TRPO_MAX_ERROS;
}

function trpo_registrar_erro(): void
{
    trpo_sessao_iniciar();
    $t = $_SESSION[TRPO_TENTATIVAS] ?? null;
    if (!$t || (time() - (int)$t['em']) > TRPO_JANELA_SEG) {
        $_SESSION[TRPO_TENTATIVAS] = ['n' => 1, 'em' => time()];
        return;
    }
    $_SESSION[TRPO_TENTATIVAS] = ['n' => (int)$t['n'] + 1, 'em' => (int)$t['em']];
}

function trpo_limpar_erros(): void
{
    trpo_sessao_iniciar();
    unset($_SESSION[TRPO_TENTATIVAS]);
}

function trpo_autenticar(PDO $pdo, string $cpfBruto, string $dataBruta): array
{
    $cpf  = trm_cpf_limpo($cpfBruto);
    $data = trpo_normalizar_data($dataBruta);

    if (strlen($cpf) !== 11) {
        return ['ok' => false, 'motivo' => 'dados',
                'msg' => 'Informe o CPF completo, com 11 dígitos.', 'colab' => null];
    }
    if ($data === '') {
        return ['ok' => false, 'motivo' => 'dados',
                'msg' => 'Informe uma data de nascimento válida.', 'colab' => null];
    }

    $col = trpo_col_nascimento($pdo);
    if ($col === '') {
        return ['ok' => false, 'motivo' => 'config',
                'msg' => 'O acesso por data de nascimento ainda não está configurado. Procure o RH.',
                'colab' => null];
    }

    $sql = trm_colab_select()
         . ' WHERE ' . trpo_sql_cpf() . ' = ?'
         . ' AND DATE(c.`' . $col . '`) = ?'
         . ' ORDER BY c.id DESC';

    $st = $pdo->prepare($sql);
    $st->execute([$cpf, $data]);
    $cadastros = $st->fetchAll(PDO::FETCH_ASSOC);

    $colab = null;
    foreach ($cadastros as $cad) {
        if (trm_colab_ativo($cad)) { $colab = $cad; break; }
    }
    if (!$colab && $cadastros) { $colab = $cadastros[0]; }

    if (!$colab) {
        trpo_registrar_erro();
        trpo_log($pdo, null, $cpf, 'negado', 'CPF ou data não conferem');
        return ['ok' => false, 'motivo' => 'nao_confere',
                'msg' => 'CPF ou data de nascimento não conferem com o cadastro.',
                'colab' => null];
    }

    if (!trm_colab_ativo($colab)) {
        trpo_registrar_erro();
        trpo_log($pdo, (int)$colab['id'], $cpf, 'inativo',
                 (string)($colab['status_descricao'] ?? ''));
        return ['ok' => false, 'motivo' => 'inativo',
                'msg' => 'Este acesso está encerrado. Colaboradores desligados não têm '
                       . 'acesso ao portal de treinamentos. Em caso de dúvida, procure o RH.',
                'colab' => null];
    }

    trpo_limpar_erros();
    trpo_log($pdo, (int)$colab['id'], $cpf, 'ok');

    return ['ok' => true, 'motivo' => '', 'msg' => '', 'colab' => $colab];
}

function trpo_entrar(array $colab): void
{
    trpo_sessao_iniciar();
    $_SESSION[TRPO_CHAVE] = [
        'id'    => (int)$colab['id'],
        'nome'  => (string)$colab['nome'],
        'em'    => time(),
        'visto' => time(),
    ];
}

function trpo_sair(): void
{
    trpo_sessao_iniciar();
    unset($_SESSION[TRPO_CHAVE]);
}

function trpo_colaborador(PDO $pdo): ?array
{
    trpo_sessao_iniciar();
    $s = $_SESSION[TRPO_CHAVE] ?? null;
    if (!$s || empty($s['id'])) { return null; }

    if ((time() - (int)($s['visto'] ?? 0)) > TRPO_INATIVIDADE) {
        trpo_sair();
        return null;
    }

    $colab = trm_colab($pdo, (int)$s['id']);
    if (!$colab || !trm_colab_ativo($colab)) {
        trpo_sair();
        return null;
    }

    $_SESSION[TRPO_CHAVE]['visto'] = time();
    return $colab;
}

function trpo_inscricoes(PDO $pdo, array $colab): array
{
    $cpf = trm_cpf_limpo((string)($colab['cpf'] ?? ''));

    $sql =
        'SELECT i.*,
                a.titulo             AS ap_titulo,
                a.subtitulo          AS ap_subtitulo,
                a.descricao          AS ap_descricao,
                a.status             AS ap_status,
                a.carga_horaria_min  AS ap_carga,
                a.emitir_certificado AS ap_certificado,
                a.permite_revisao    AS ap_revisao,
                a.prova_ativa        AS ap_prova,
                a.prova_nota_minima  AS ap_nota_minima,
                a.cor_primaria       AS ap_cor
           FROM treinamentos_inscricoes i
           JOIN treinamentos_apresentacoes a ON a.id = i.apresentacao_id
          WHERE i.colaborador_id = ?'
        . ($cpf !== '' ? ' OR ' . trpo_sql_cpf('i.cpf') . ' = ?' : '')
        . ' ORDER BY FIELD(i.status, "em_andamento", "pendente", "concluido", "cancelado"),
                   i.prazo IS NULL, i.prazo, a.titulo';

    $st = $pdo->prepare($sql);
    $st->execute($cpf !== '' ? [(int)$colab['id'], $cpf] : [(int)$colab['id']]);
    $linhas = $st->fetchAll(PDO::FETCH_ASSOC);

    $hoje = date('Y-m-d');
    $saida = [];
    $vistos = [];

    foreach ($linhas as $r) {
        $id = (int)$r['id'];
        if (isset($vistos[$id])) { continue; }
        $vistos[$id] = true;

        $concluido = $r['status'] === 'concluido';
        $cancelado = $r['status'] === 'cancelado';
        $prazo     = (string)($r['prazo'] ?? '');
        $atrasado  = !$concluido && !$cancelado && $prazo !== '' && $prazo < $hoje;

        $dias = null;
        if ($prazo !== '') {
            $dias = (int)floor((strtotime($prazo) - strtotime($hoje)) / 86400);
        }

        $r['calc'] = [
            'concluido'  => $concluido,
            'cancelado'  => $cancelado,
            'atrasado'   => $atrasado,
            'disponivel' => !$cancelado && !$atrasado && $r['ap_status'] === 'publicado',
            'dias'       => $dias,
            'iniciado'   => !empty($r['iniciado_em']),
            'pct'        => max(0, min(100, (int)$r['percentual'])),
            'link'       => tr_url('treinar.php') . '?t=' . rawurlencode((string)$r['token']),
            'cert'       => ((int)$r['ap_certificado'] === 1 && (string)$r['certificado_codigo'] !== '')
                ? tr_url('certificado.php') . '?c=' . rawurlencode((string)$r['certificado_codigo'])
                : '',
        ];

        $saida[] = $r;
    }

    return $saida;
}

function trpo_resumo(array $inscricoes): array
{
    $r = [
        'total' => 0, 'pendentes' => 0, 'andamento' => 0,
        'concluidos' => 0, 'atrasados' => 0, 'certificados' => 0,
        'tempo' => 0,
    ];

    foreach ($inscricoes as $i) {
        if ($i['calc']['cancelado']) { continue; }
        $r['total']++;
        $r['tempo'] += (int)$i['tempo_seg'];

        if ($i['calc']['concluido']) {
            $r['concluidos']++;
            if ($i['calc']['cert'] !== '') { $r['certificados']++; }
            continue;
        }
        if ($i['calc']['atrasado']) { $r['atrasados']++; }
        if ($i['calc']['iniciado']) { $r['andamento']++; } else { $r['pendentes']++; }
    }

    $r['pct'] = $r['total'] > 0 ? (int)round(($r['concluidos'] / $r['total']) * 100) : 0;
    return $r;
}

function trpo_situacao(array $i): array
{
    $c = $i['calc'];
    if ($c['cancelado'])  { return ['cancelado', 'Cancelado',    'person-x']; }
    if ($c['concluido'])  { return ['ok',        'Concluído',    'patch-check-fill']; }
    if ($c['atrasado'])   { return ['atraso',    'Prazo vencido', 'calendar-x']; }
    if ($c['iniciado'])   { return ['curso',     'Em andamento', 'hourglass-split']; }
    return ['novo', 'Não iniciado', 'play-circle'];
}
