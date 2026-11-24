<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_lib.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
tr_exigir('treinamentos.apresentacoes.editar');

function tr_json(array $dados, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    tr_json(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}

$token = (string)($_POST['csrf'] ?? '');
if ($token === '' || !hash_equals((string)csrf_token(), $token)) {
    tr_json(['ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente de novo.'], 419);
}

$id   = (int)($_POST['id'] ?? 0);
$acao = (string)($_POST['acao'] ?? 'enviar');
if ($id <= 0) {
    tr_json(['ok' => false, 'erro' => 'Treinamento não informado.'], 422);
}

$pdo = db();

$temColuna = false;
try {
    $temColuna = (bool)$pdo->query("SHOW COLUMNS FROM treinamentos_apresentacoes LIKE 'capa'")->fetch();
} catch (Throwable $e) {
    $temColuna = false;
}
if (!$temColuna) {
    tr_json(['ok' => false, 'erro' => 'Rode a migração migration_capa.sql antes de usar capas.'], 500);
}

$st = $pdo->prepare('SELECT id, capa FROM treinamentos_apresentacoes WHERE id = ? LIMIT 1');
$st->execute([$id]);
$reg = $st->fetch(PDO::FETCH_ASSOC);
if (!$reg) {
    tr_json(['ok' => false, 'erro' => 'Treinamento não encontrado.'], 404);
}

$dirBase = __DIR__ . '/uploads/capas';
$anterior = (string)($reg['capa'] ?? '');

$apagarAnterior = static function (string $nome) use ($dirBase): void {
    if ($nome === '') {
        return;
    }
    $nome = basename($nome);
    $alvo = $dirBase . '/' . $nome;
    if (is_file($alvo)) {
        @unlink($alvo);
    }
};

if ($acao === 'remover') {
    $pdo->prepare('UPDATE treinamentos_apresentacoes SET capa = NULL WHERE id = ?')->execute([$id]);
    $apagarAnterior($anterior);
    tr_json(['ok' => true, 'url' => null]);
}

if (!isset($_FILES['capa']) || !is_array($_FILES['capa'])) {
    tr_json(['ok' => false, 'erro' => 'Nenhuma imagem recebida.'], 422);
}

$arq = $_FILES['capa'];
if ((int)($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $mapa = [
        UPLOAD_ERR_INI_SIZE   => 'A imagem passou do limite do servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'A imagem passou do limite do formulário.',
        UPLOAD_ERR_PARTIAL    => 'O envio foi interrompido. Tente de novo.',
        UPLOAD_ERR_NO_FILE    => 'Nenhuma imagem recebida.',
        UPLOAD_ERR_NO_TMP_DIR => 'O servidor está sem pasta temporária.',
        UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar o arquivo.',
    ];
    tr_json(['ok' => false, 'erro' => $mapa[(int)$arq['error']] ?? 'Falha no envio.'], 422);
}

if ((int)$arq['size'] > 5 * 1024 * 1024) {
    tr_json(['ok' => false, 'erro' => 'A imagem passa de 5 MB. Reduza e tente de novo.'], 422);
}

$info = @getimagesize($arq['tmp_name']);
if (!$info) {
    tr_json(['ok' => false, 'erro' => 'O arquivo enviado não é uma imagem válida.'], 422);
}

$tipos = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_GIF  => 'gif',
    IMAGETYPE_WEBP => 'webp',
];
$tipo = (int)$info[2];
if (!isset($tipos[$tipo])) {
    tr_json(['ok' => false, 'erro' => 'Use JPG, PNG, WEBP ou GIF.'], 422);
}
$ext = $tipos[$tipo];

if (!is_dir($dirBase) && !@mkdir($dirBase, 0755, true) && !is_dir($dirBase)) {
    tr_json(['ok' => false, 'erro' => 'Não foi possível criar a pasta de capas.'], 500);
}

$htaccess = $dirBase . '/.htaccess';
if (!is_file($htaccess)) {
    @file_put_contents(
        $htaccess,
        "Options -Indexes -ExecCGI\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|pht|phps|cgi|pl|py|sh|html?|svg|js)$\">\n  Require all denied\n</FilesMatch>\n"
    );
}

$nome  = 'capa-' . $id . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest  = $dirBase . '/' . $nome;
$largA = (int)$info[0];
$altA  = (int)$info[1];
$maxL  = 1200;

$gravou = false;

if ($ext !== 'gif' && $largA > $maxL && function_exists('imagecreatetruecolor')) {
    $origem = null;
    if ($ext === 'jpg'  && function_exists('imagecreatefromjpeg')) { $origem = @imagecreatefromjpeg($arq['tmp_name']); }
    if ($ext === 'png'  && function_exists('imagecreatefrompng'))  { $origem = @imagecreatefrompng($arq['tmp_name']); }
    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) { $origem = @imagecreatefromwebp($arq['tmp_name']); }

    if ($origem) {
        $novaL = $maxL;
        $novaA = (int)round($altA * ($maxL / $largA));
        $tela  = imagecreatetruecolor($novaL, $novaA);

        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($tela, false);
            imagesavealpha($tela, true);
        }
        imagecopyresampled($tela, $origem, 0, 0, 0, 0, $novaL, $novaA, $largA, $altA);

        if ($ext === 'jpg')  { $gravou = imagejpeg($tela, $dest, 82); }
        if ($ext === 'png')  { $gravou = imagepng($tela, $dest, 6); }
        if ($ext === 'webp') { $gravou = imagewebp($tela, $dest, 82); }

        imagedestroy($tela);
        imagedestroy($origem);
    }
}

if (!$gravou && !move_uploaded_file($arq['tmp_name'], $dest)) {
    tr_json(['ok' => false, 'erro' => 'Não foi possível salvar a imagem.'], 500);
}
@chmod($dest, 0644);

$pdo->prepare('UPDATE treinamentos_apresentacoes SET capa = ? WHERE id = ?')->execute([$nome, $id]);
$apagarAnterior($anterior);

tr_json([
    'ok'      => true,
    'arquivo' => $nome,
    'url'     => tr_url('uploads/capas/' . $nome),
]);
