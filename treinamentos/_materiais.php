<?php
if (!function_exists('tr_url')) {
    require_once __DIR__ . '/_lib.php';
}

if (!function_exists('trmat_pode_ver')) {
    function trmat_pode_ver(): bool
    {
        return tr_pode('treinamentos.materiais.ver')
            || tr_pode('treinamentos.apresentacoes.ver');
    }
}

if (!function_exists('trmat_pode_gerenciar')) {
    function trmat_pode_gerenciar(): bool
    {
        return tr_pode('treinamentos.materiais.gerenciar')
            || tr_pode('treinamentos.apresentacoes.editar');
    }
}

function trmat_exigir_gerenciar_ajax(): void
{
    if (!trmat_pode_gerenciar()) {
        nv_json(['ok' => false, 'msg' => 'Sem permissão para gerenciar o material de apoio.'], 403);
    }
}

function trmat_formatos(): array
{
    return [
        'pdf'  => ['application/pdf', 'file-earmark-pdf', 'PDF', true],
        'doc'  => ['application/msword', 'file-earmark-word', 'Word', false],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                   'file-earmark-word', 'Word', false],
        'xls'  => ['application/vnd.ms-excel', 'file-earmark-spreadsheet', 'Excel', false],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                   'file-earmark-spreadsheet', 'Excel', false],
        'ppt'  => ['application/vnd.ms-powerpoint', 'file-earmark-slides', 'PowerPoint', false],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation',
                   'file-earmark-slides', 'PowerPoint', false],
        'png'  => ['image/png', 'file-earmark-image', 'Imagem', true],
        'jpg'  => ['image/jpeg', 'file-earmark-image', 'Imagem', true],
        'jpeg' => ['image/jpeg', 'file-earmark-image', 'Imagem', true],
        'webp' => ['image/webp', 'file-earmark-image', 'Imagem', true],
        'txt'  => ['text/plain; charset=utf-8', 'file-earmark-text', 'Texto', true],
        'csv'  => ['text/csv; charset=utf-8', 'file-earmark-text', 'CSV', false],
        'zip'  => ['application/zip', 'file-earmark-zip', 'ZIP', false],
        'mp4'  => ['video/mp4', 'file-earmark-play', 'Vídeo', true],
    ];
}

function trmat_tamanho_max(): int
{
    return 30 * 1024 * 1024;
}

function trmat_dir(int $apresentacaoId): string
{
    return tr_dir_midia($apresentacaoId) . '/apoio';
}

function trmat_garantir_dir(int $apresentacaoId): ?string
{
    $dir = trmat_dir($apresentacaoId);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) { return null; }
    return $dir;
}

function trmat_apagar_pasta(int $apresentacaoId): void
{
    $dir = trmat_dir($apresentacaoId);
    if (!is_dir($dir)) { return; }
    foreach ((array)glob($dir . '/*') as $f) { if (is_file($f)) { @unlink($f); } }
    @rmdir($dir);
}

function trmat_listar(PDO $pdo, int $apresentacaoId, bool $somenteAtivos = true): array
{
    $sql = 'SELECT * FROM treinamentos_materiais WHERE apresentacao_id = ?'
         . ($somenteAtivos ? ' AND ativo = 1' : '')
         . ' ORDER BY ordem, id';
    $st = $pdo->prepare($sql);
    $st->execute([$apresentacaoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function trmat_total(PDO $pdo, int $apresentacaoId, bool $somenteAtivos = true): int
{
    try {
        $sql = 'SELECT COUNT(*) FROM treinamentos_materiais WHERE apresentacao_id = ?'
             . ($somenteAtivos ? ' AND ativo = 1' : '');
        $st = $pdo->prepare($sql);
        $st->execute([$apresentacaoId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function trmat_item(PDO $pdo, int $id, int $apresentacaoId = 0): ?array
{
    if ($id <= 0) { return null; }
    $sql = 'SELECT * FROM treinamentos_materiais WHERE id = ?'
         . ($apresentacaoId > 0 ? ' AND apresentacao_id = ?' : '') . ' LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute($apresentacaoId > 0 ? [$id, $apresentacaoId] : [$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function trmat_referencias(PDO $pdo, int $apresentacaoId, bool $somenteAtivos = true): array
{
    $sql = 'SELECT * FROM treinamentos_referencias WHERE apresentacao_id = ?'
         . ($somenteAtivos ? ' AND ativo = 1' : '')
         . ' ORDER BY ordem, id';
    $st = $pdo->prepare($sql);
    $st->execute([$apresentacaoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function trmat_total_ref(PDO $pdo, int $apresentacaoId, bool $somenteAtivos = true): int
{
    try {
        $sql = 'SELECT COUNT(*) FROM treinamentos_referencias WHERE apresentacao_id = ?'
             . ($somenteAtivos ? ' AND ativo = 1' : '');
        $st = $pdo->prepare($sql);
        $st->execute([$apresentacaoId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function trmat_extensao(array $m): string
{
    $arq = (string)($m['arquivo'] ?? '');
    return $arq !== '' ? strtolower(pathinfo($arq, PATHINFO_EXTENSION)) : '';
}

function trmat_icone(array $m): string
{
    if (($m['tipo'] ?? '') === 'link') { return 'link-45deg'; }
    $f = trmat_formatos();
    $e = trmat_extensao($m);
    return $f[$e][1] ?? 'file-earmark';
}

function trmat_formato_rotulo(array $m): string
{
    if (($m['tipo'] ?? '') === 'link') { return 'Link'; }
    $f = trmat_formatos();
    $e = trmat_extensao($m);
    return $f[$e][2] ?? strtoupper($e);
}

function trmat_tamanho_rotulo(array $m): string
{
    $b = (int)($m['arquivo_tamanho'] ?? 0);
    if ($b <= 0) { return ''; }
    if (function_exists('nv_fmt_bytes')) { return (string)nv_fmt_bytes($b); }
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $v = (float)$b;
    while ($v >= 1024 && $i < 3) { $v /= 1024; $i++; }
    return number_format($v, $i === 0 ? 0 : 1, ',', '.') . ' ' . $u[$i];
}

function trmat_abre_no_navegador(array $m): bool
{
    $f = trmat_formatos();
    $e = trmat_extensao($m);
    return (bool)($f[$e][3] ?? false);
}

function trmat_caminho(array $m): ?string
{
    if (($m['tipo'] ?? '') !== 'arquivo') { return null; }
    $arq = basename((string)($m['arquivo'] ?? ''));
    if ($arq === '') { return null; }
    $caminho = trmat_dir((int)$m['apresentacao_id']) . '/' . $arq;
    return is_file($caminho) ? $caminho : null;
}

function trmat_entregar(array $m, bool $baixar = false): void
{
    $caminho = trmat_caminho($m);
    if ($caminho === null) { http_response_code(404); exit; }

    $ext  = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
    $f    = trmat_formatos();
    $mime = $f[$ext][0] ?? 'application/octet-stream';

    $nome = (string)($m['arquivo_nome'] ?? '');
    if ($nome === '') { $nome = basename($caminho); }
    $nome = preg_replace('/[^\p{L}\p{N}\.\-_ ]+/u', '_', $nome) ?: 'documento.' . $ext;

    $inline = !$baixar && trmat_abre_no_navegador($m);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($caminho));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
        . '; filename="' . $nome . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($caminho);
    exit;
}

function trmat_entregar_por_id(PDO $pdo, int $apresentacaoId, int $materialId, bool $baixar = false): void
{
    if ($materialId < 0) {
        require_once __DIR__ . '/_apostila.php';
        $ap = tr_apresentacao($pdo, $apresentacaoId);
        if (!$ap) { http_response_code(404); exit; }
        trap_entregar($pdo, $ap, $baixar);
    }

    $m = trmat_item($pdo, $materialId, $apresentacaoId);
    if (!$m || (int)$m['ativo'] !== 1 || $m['tipo'] !== 'arquivo') {
        http_response_code(404);
        exit;
    }
    trmat_entregar($m, $baixar);
}

function trmat_payload(PDO $pdo, int $apresentacaoId, string $baseArquivo, ?array $ap = null): array
{
    $materiais = [];
    $referencias = [];

    require_once __DIR__ . '/_apostila.php';
    if ($ap === null) { $ap = tr_apresentacao($pdo, $apresentacaoId); }
    $apostila = $ap ? trap_item($ap, $baseArquivo) : null;
    if ($apostila) { $materiais[] = $apostila; }

    try {
        foreach (trmat_listar($pdo, $apresentacaoId) as $m) {
            $arquivo = $m['tipo'] === 'arquivo';
            if ($arquivo && trmat_caminho($m) === null) { continue; }
            if (!$arquivo && trim((string)$m['url']) === '') { continue; }

            $materiais[] = [
                'id'        => (int)$m['id'],
                'titulo'    => (string)$m['titulo'],
                'descricao' => (string)$m['descricao'],
                'tipo'      => (string)$m['tipo'],
                'url'       => $arquivo ? ($baseArquivo . (int)$m['id']) : (string)$m['url'],
                'baixar'    => $arquivo ? ($baseArquivo . (int)$m['id'] . '&baixar=1') : '',
                'icone'     => trmat_icone($m),
                'formato'   => trmat_formato_rotulo($m),
                'tamanho'   => trmat_tamanho_rotulo($m),
                'destaque'  => ((int)$m['destaque'] === 1),
            ];
        }
    } catch (Throwable $e) {
        $materiais = [];
    }

    try {
        foreach (trmat_referencias($pdo, $apresentacaoId) as $r) {
            $referencias[] = [
                'texto' => (string)$r['texto'],
                'url'   => (string)$r['url'],
            ];
        }
    } catch (Throwable $e) {
        $referencias = [];
    }

    return ['materiais' => $materiais, 'referencias' => $referencias];
}

function trmat_tem_conteudo(?array $apoio): bool
{
    if (!$apoio) { return false; }
    return !empty($apoio['materiais']) || !empty($apoio['referencias']);
}
