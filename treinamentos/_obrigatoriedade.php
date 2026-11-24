<?php
if (!function_exists('trm_colab_select')) {
    require_once __DIR__ . '/_matriculas.php';
}

function tro_cargos_disponiveis(PDO $pdo): array
{
    $st = $pdo->query(
        'SELECT DISTINCT cargo FROM colaboradores
          WHERE cargo IS NOT NULL AND cargo <> "" AND ativo = 1
            AND (status_codigo IS NULL OR status_codigo <> "7")
       ORDER BY cargo'
    );
    return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function tro_cargos_do_treinamento(PDO $pdo, int $apresentacaoId): array
{
    $f = tro_situacao_filtro_sql($pdo, $apresentacaoId, 'c');
    $st = $pdo->prepare(
        'SELECT co.*,
                (SELECT COUNT(*) FROM colaboradores c
                  WHERE c.cargo = co.cargo AND c.ativo = 1
                    AND (c.status_codigo IS NULL OR c.status_codigo <> "7")' . $f . ') AS total_cargo,
                (SELECT COUNT(*) FROM colaboradores c
                   JOIN treinamentos_inscricoes i
                     ON i.colaborador_id = c.id AND i.apresentacao_id = co.apresentacao_id
                  WHERE c.cargo = co.cargo AND c.ativo = 1
                    AND (c.status_codigo IS NULL OR c.status_codigo <> "7")' . $f . ') AS matriculados,
                (SELECT COUNT(*) FROM colaboradores c
                   JOIN treinamentos_inscricoes i
                     ON i.colaborador_id = c.id AND i.apresentacao_id = co.apresentacao_id
                  WHERE c.cargo = co.cargo AND c.ativo = 1 AND i.status = "concluido"
                    AND (c.status_codigo IS NULL OR c.status_codigo <> "7")' . $f . ') AS concluidos
           FROM treinamentos_cargos_obrigatorios co
          WHERE co.apresentacao_id = ?
       ORDER BY co.cargo'
    );
    $st->execute([$apresentacaoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function tro_colaboradores_pendentes(PDO $pdo, int $apresentacaoId): array
{
    $st = $pdo->prepare(
        'SELECT DISTINCT cargo FROM treinamentos_cargos_obrigatorios WHERE apresentacao_id = ?'
    );
    $st->execute([$apresentacaoId]);
    $cargos = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
    if (!$cargos) { return []; }

    $in = implode(',', array_fill(0, count($cargos), '?'));
    $f  = tro_situacao_filtro_sql($pdo, $apresentacaoId, 'c');
    $sql = trm_colab_select()
         . " WHERE c.ativo = 1 AND (c.status_codigo IS NULL OR c.status_codigo <> '7')
               AND c.cargo IN ($in) {$f}
               AND NOT EXISTS (
                     SELECT 1 FROM treinamentos_inscricoes i
                      WHERE i.apresentacao_id = ? AND i.colaborador_id = c.id
                   )
          ORDER BY c.cargo, c.nome";

    $st = $pdo->prepare($sql);
    $st->execute(array_merge($cargos, [$apresentacaoId]));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function tro_situacoes_ignoradas(PDO $pdo, int $apresentacaoId): array
{
    try {
        $st = $pdo->prepare(
            'SELECT status_codigo, status_descricao FROM treinamentos_obrigatorios_situacoes
              WHERE apresentacao_id = ? ORDER BY status_descricao, status_codigo'
        );
        $st->execute([$apresentacaoId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function tro_situacao_filtro_sql(PDO $pdo, int $apresentacaoId, string $alias = 'c'): string
{
    $codigos = array_map('strval', array_column(tro_situacoes_ignoradas($pdo, $apresentacaoId), 'status_codigo'));
    if (!$codigos) { return ''; }
    $lista = implode(',', array_map(static function ($c) use ($pdo) { return $pdo->quote($c); }, $codigos));
    return " AND ($alias.status_codigo IS NULL OR $alias.status_codigo NOT IN ($lista))";
}

function tro_situacoes_disponiveis(PDO $pdo, int $apresentacaoId): array
{
    $st = $pdo->query(
        "SELECT status_codigo, MAX(status_descricao) AS status_descricao, COUNT(*) AS total
           FROM colaboradores
          WHERE ativo = 1 AND status_codigo IS NOT NULL AND status_codigo <> '' AND status_codigo <> '7'
       GROUP BY status_codigo
       ORDER BY MAX(status_descricao), status_codigo"
    );
    $ign = [];
    foreach (tro_situacoes_ignoradas($pdo, $apresentacaoId) as $r) {
        $ign[(string)$r['status_codigo']] = (string)$r['status_descricao'];
    }

    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cod = (string)$r['status_codigo'];
        $out[$cod] = [
            'codigo'    => $cod,
            'descricao' => (string)$r['status_descricao'],
            'total'     => (int)$r['total'],
            'ignorada'  => array_key_exists($cod, $ign),
        ];
    }
    foreach ($ign as $cod => $desc) {
        if (!isset($out[$cod])) {
            $out[$cod] = ['codigo' => $cod, 'descricao' => $desc, 'total' => 0, 'ignorada' => true];
        }
    }
    return array_values($out);
}

function tro_situacoes_salvar(PDO $pdo, int $apresentacaoId, array $codigos): void
{
    $validas = [];
    foreach (tro_situacoes_disponiveis($pdo, $apresentacaoId) as $r) {
        $validas[$r['codigo']] = $r['descricao'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM treinamentos_obrigatorios_situacoes WHERE apresentacao_id = ?')
            ->execute([$apresentacaoId]);
        $ins = $pdo->prepare(
            'INSERT INTO treinamentos_obrigatorios_situacoes (apresentacao_id, status_codigo, status_descricao)
             VALUES (?, ?, ?)'
        );
        foreach (array_unique(array_map('strval', $codigos)) as $cod) {
            if (isset($validas[$cod])) {
                $ins->execute([$apresentacaoId, $cod, mb_substr($validas[$cod], 0, 150)]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
