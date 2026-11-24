<?php
if (!function_exists('tr_qr_matriz')) {

function tr_qr_blocos(int $versao, string $nivel): array
{
    static $t = [
        '1L' => [[1, 26, 19]],                  '1M' => [[1, 26, 16]],
        '1Q' => [[1, 26, 13]],                  '1H' => [[1, 26, 9]],
        '2L' => [[1, 44, 34]],                  '2M' => [[1, 44, 28]],
        '2Q' => [[1, 44, 22]],                  '2H' => [[1, 44, 16]],
        '3L' => [[1, 70, 55]],                  '3M' => [[1, 70, 44]],
        '3Q' => [[2, 35, 17]],                  '3H' => [[2, 35, 13]],
        '4L' => [[1, 100, 80]],                 '4M' => [[2, 50, 32]],
        '4Q' => [[2, 50, 24]],                  '4H' => [[4, 25, 9]],
        '5L' => [[1, 134, 108]],                '5M' => [[2, 67, 43]],
        '5Q' => [[2, 33, 15], [2, 34, 16]],     '5H' => [[2, 33, 11], [2, 34, 12]],
        '6L' => [[2, 86, 68]],                  '6M' => [[4, 43, 27]],
        '6Q' => [[4, 43, 19]],                  '6H' => [[4, 43, 15]],
        '7L' => [[2, 98, 78]],                  '7M' => [[4, 49, 31]],
        '7Q' => [[2, 32, 14], [4, 33, 15]],     '7H' => [[4, 39, 13], [1, 40, 14]],
        '8L' => [[2, 121, 97]],                 '8M' => [[2, 60, 38], [2, 61, 39]],
        '8Q' => [[4, 40, 18], [2, 41, 19]],     '8H' => [[4, 40, 14], [2, 41, 15]],
        '9L' => [[2, 146, 116]],                '9M' => [[3, 58, 36], [2, 59, 37]],
        '9Q' => [[4, 36, 16], [4, 37, 17]],     '9H' => [[4, 36, 12], [4, 37, 13]],
        '10L' => [[2, 86, 68], [2, 87, 69]],    '10M' => [[4, 69, 43], [1, 70, 44]],
        '10Q' => [[6, 43, 19], [2, 44, 20]],    '10H' => [[6, 43, 15], [2, 44, 16]],
    ];
    return $t[$versao . $nivel] ?? [];
}

function tr_qr_alinhamento(int $versao): array
{
    static $t = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];
    return $t[$versao] ?? [];
}

function tr_qr_bits_nivel(string $nivel): int
{
    return ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2][$nivel] ?? 0;
}

function tr_qr_gf(): array
{
    static $exp = null, $log = null;
    if ($exp === null) {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) { $x ^= 0x11D; }
        }
        for ($i = 255; $i < 512; $i++) { $exp[$i] = $exp[$i - 255]; }
    }
    return [$exp, $log];
}

function tr_qr_mult(int $a, int $b): int
{
    if ($a === 0 || $b === 0) { return 0; }
    [$exp, $log] = tr_qr_gf();
    return $exp[($log[$a] + $log[$b]) % 255];
}

function tr_qr_gerador(int $grau): array
{
    [$exp] = tr_qr_gf();
    $g = [1];
    for ($i = 0; $i < $grau; $i++) {
        $novo = array_fill(0, count($g) + 1, 0);
        foreach ($g as $j => $c) {
            $novo[$j]     ^= $c;
            $novo[$j + 1] ^= tr_qr_mult($c, $exp[$i]);
        }
        $g = $novo;
    }
    return $g;
}

function tr_qr_ec(array $dados, int $qtdEc): array
{
    $g = tr_qr_gerador($qtdEc);
    $res = array_merge($dados, array_fill(0, $qtdEc, 0));
    $n = count($dados);
    for ($i = 0; $i < $n; $i++) {
        $fator = $res[$i];
        if ($fator === 0) { continue; }
        foreach ($g as $j => $c) {
            $res[$i + $j] ^= tr_qr_mult($c, $fator);
        }
    }
    return array_slice($res, $n, $qtdEc);
}

function tr_qr_bch_digitos(int $v): int
{
    $n = 0;
    while ($v !== 0) { $n++; $v >>= 1; }
    return $n;
}

function tr_qr_bch_formato(int $dados): int
{
    $d = $dados << 10;
    while (tr_qr_bch_digitos($d) - tr_qr_bch_digitos(0x537) >= 0) {
        $d ^= (0x537 << (tr_qr_bch_digitos($d) - tr_qr_bch_digitos(0x537)));
    }
    return (($dados << 10) | $d) ^ 0x5412;
}

function tr_qr_bch_versao(int $versao): int
{
    $d = $versao << 12;
    while (tr_qr_bch_digitos($d) - tr_qr_bch_digitos(0x1F25) >= 0) {
        $d ^= (0x1F25 << (tr_qr_bch_digitos($d) - tr_qr_bch_digitos(0x1F25)));
    }
    return ($versao << 12) | $d;
}

function tr_qr_mascara(int $m, int $i, int $j): bool
{
    switch ($m) {
        case 0: return (($i + $j) % 2) === 0;
        case 1: return ($i % 2) === 0;
        case 2: return ($j % 3) === 0;
        case 3: return (($i + $j) % 3) === 0;
        case 4: return ((intdiv($i, 2) + intdiv($j, 3)) % 2) === 0;
        case 5: return ((($i * $j) % 2) + (($i * $j) % 3)) === 0;
        case 6: return (((($i * $j) % 2) + (($i * $j) % 3)) % 2) === 0;
        default: return (((($i * $j) % 3) + (($i + $j) % 2)) % 2) === 0;
    }
}

function tr_qr_pen1(array $m, int $n): int
{
    $p = 0;
    for ($r = 0; $r < $n; $r++) {
        $cor = $m[$r][0];
        $len = 0;
        for ($c = 0; $c < $n; $c++) {
            if ($m[$r][$c] === $cor) { $len++; continue; }
            if ($len >= 5) { $p += $len - 2; }
            $len = 1;
            $cor = $m[$r][$c];
        }
        if ($len >= 5) { $p += $len - 2; }
    }
    for ($c = 0; $c < $n; $c++) {
        $cor = $m[0][$c];
        $len = 0;
        for ($r = 0; $r < $n; $r++) {
            if ($m[$r][$c] === $cor) { $len++; continue; }
            if ($len >= 5) { $p += $len - 2; }
            $len = 1;
            $cor = $m[$r][$c];
        }
        if ($len >= 5) { $p += $len - 2; }
    }
    return $p;
}

function tr_qr_pen2(array $m, int $n): int
{
    $p = 0;
    for ($r = 0; $r < $n - 1; $r++) {
        for ($c = 0; $c < $n - 1; $c++) {
            $a = $m[$r][$c];
            if ($a === $m[$r][$c + 1] && $a === $m[$r + 1][$c] && $a === $m[$r + 1][$c + 1]) {
                $p += 3;
            }
        }
    }
    return $p;
}

function tr_qr_pen3(array $m, int $n): int
{
    $p = 0;
    $casa = static function (array $v): bool {
        $a = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $b = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        $ok1 = true; $ok2 = true;
        for ($i = 0; $i < 11; $i++) {
            if ($v[$i] !== $a[$i]) { $ok1 = false; }
            if ($v[$i] !== $b[$i]) { $ok2 = false; }
        }
        return $ok1 || $ok2;
    };

    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c <= $n - 11; $c++) {
            $v = [];
            for ($i = 0; $i < 11; $i++) { $v[] = $m[$r][$c + $i] ? 1 : 0; }
            if ($casa($v)) { $p += 40; }
        }
    }
    for ($c = 0; $c < $n; $c++) {
        for ($r = 0; $r <= $n - 11; $r++) {
            $v = [];
            for ($i = 0; $i < 11; $i++) { $v[] = $m[$r + $i][$c] ? 1 : 0; }
            if ($casa($v)) { $p += 40; }
        }
    }
    return $p;
}

function tr_qr_pen4(array $m, int $n): int
{
    $escuros = 0;
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            if ($m[$r][$c]) { $escuros++; }
        }
    }
    $percentual = 100 * $escuros / ($n * $n);
    return ((int)(abs($percentual - 50) / 5)) * 10;
}

function tr_qr_penalidade(array $m): int
{
    $n = count($m);
    return tr_qr_pen1($m, $n) + tr_qr_pen2($m, $n) + tr_qr_pen3($m, $n) + tr_qr_pen4($m, $n);
}

function tr_qr_probe(array &$m, int $linha, int $coluna): void
{
    $n = count($m);
    for ($r = -1; $r <= 7; $r++) {
        if ($linha + $r < 0 || $n <= $linha + $r) { continue; }
        for ($c = -1; $c <= 7; $c++) {
            if ($coluna + $c < 0 || $n <= $coluna + $c) { continue; }
            $borda = (0 <= $r && $r <= 6 && ($c === 0 || $c === 6))
                  || (0 <= $c && $c <= 6 && ($r === 0 || $r === 6))
                  || (2 <= $r && $r <= 4 && 2 <= $c && $c <= 4);
            $m[$linha + $r][$coluna + $c] = $borda;
        }
    }
}

function tr_qr_montar(int $versao, string $nivel, array $dados, int $mascara, bool $teste = false): array
{
    $n = $versao * 4 + 17;
    $m = array_fill(0, $n, array_fill(0, $n, null));

    tr_qr_probe($m, 0, 0);
    tr_qr_probe($m, $n - 7, 0);
    tr_qr_probe($m, 0, $n - 7);

    $pos = tr_qr_alinhamento($versao);
    foreach ($pos as $r) {
        foreach ($pos as $c) {
            if ($m[$r][$c] !== null) { continue; }
            for ($dr = -2; $dr <= 2; $dr++) {
                for ($dc = -2; $dc <= 2; $dc++) {
                    $m[$r + $dr][$c + $dc] =
                        ($dr === -2 || $dr === 2 || $dc === -2 || $dc === 2 || ($dr === 0 && $dc === 0));
                }
            }
        }
    }

    for ($i = 8; $i < $n - 8; $i++) {
        if ($m[$i][6] === null) { $m[$i][6] = ($i % 2) === 0; }
        if ($m[6][$i] === null) { $m[6][$i] = ($i % 2) === 0; }
    }

    if ($versao >= 7) {
        $bits = tr_qr_bch_versao($versao);
        for ($i = 0; $i < 18; $i++) {
            $b = !$teste && ((($bits >> $i) & 1) === 1);
            $m[intdiv($i, 3)][$i % 3 + $n - 8 - 3] = $b;
            $m[$i % 3 + $n - 8 - 3][intdiv($i, 3)] = $b;
        }
    }

    $bits = tr_qr_bch_formato((tr_qr_bits_nivel($nivel) << 3) | $mascara);
    for ($i = 0; $i < 15; $i++) {
        $b = !$teste && ((($bits >> $i) & 1) === 1);
        if ($i < 6)       { $m[$i][8] = $b; }
        elseif ($i < 8)   { $m[$i + 1][8] = $b; }
        else              { $m[$n - 15 + $i][8] = $b; }

        if ($i < 8)       { $m[8][$n - $i - 1] = $b; }
        elseif ($i < 9)   { $m[8][15 - $i - 1 + 1] = $b; }
        else              { $m[8][15 - $i - 1] = $b; }
    }
    $m[$n - 8][8] = !$teste;

    $bitIndex = 7;
    $byteIndex = 0;
    $inc = -1;
    $linha = $n - 1;
    $total = count($dados);

    for ($coluna = $n - 1; $coluna > 0; $coluna -= 2) {
        if ($coluna === 6) { $coluna--; }
        while (true) {
            for ($c = 0; $c < 2; $c++) {
                if ($m[$linha][$coluna - $c] !== null) { continue; }
                $escuro = false;
                if ($byteIndex < $total) {
                    $escuro = ((($dados[$byteIndex] >> $bitIndex) & 1) === 1);
                }
                if (tr_qr_mascara($mascara, $linha, $coluna - $c)) { $escuro = !$escuro; }
                $m[$linha][$coluna - $c] = $escuro;
                $bitIndex--;
                if ($bitIndex === -1) { $byteIndex++; $bitIndex = 7; }
            }
            $linha += $inc;
            if ($linha < 0 || $n <= $linha) { $linha -= $inc; $inc = -$inc; break; }
        }
    }

    return $m;
}

function tr_qr_codewords(string $texto, int $versao, string $nivel): array
{
    $blocos = tr_qr_blocos($versao, $nivel);
    $capacidade = 0;
    foreach ($blocos as $b) { $capacidade += $b[0] * $b[2]; }

    $bits = '';
    $bits .= '0100';
    $tamContador = $versao < 10 ? 8 : 16;
    $bits .= str_pad(decbin(strlen($texto)), $tamContador, '0', STR_PAD_LEFT);
    for ($i = 0, $n = strlen($texto); $i < $n; $i++) {
        $bits .= str_pad(decbin(ord($texto[$i])), 8, '0', STR_PAD_LEFT);
    }

    $limite = $capacidade * 8;
    if (strlen($bits) > $limite) { return []; }

    $bits .= str_repeat('0', min(4, $limite - strlen($bits)));
    if (strlen($bits) % 8 !== 0) { $bits .= str_repeat('0', 8 - (strlen($bits) % 8)); }

    $palavras = [];
    for ($i = 0; $i < strlen($bits); $i += 8) {
        $palavras[] = bindec(substr($bits, $i, 8));
    }
    $preenche = [0xEC, 0x11];
    $k = 0;
    while (count($palavras) < $capacidade) {
        $palavras[] = $preenche[$k % 2];
        $k++;
    }

    $blocosDados = [];
    $blocosEc = [];
    $offset = 0;
    $maxDados = 0;
    $maxEc = 0;

    foreach ($blocos as $b) {
        [$qtd, $totalCw, $dadosCw] = $b;
        $qtdEc = $totalCw - $dadosCw;
        for ($i = 0; $i < $qtd; $i++) {
            $parte = array_slice($palavras, $offset, $dadosCw);
            $offset += $dadosCw;
            $blocosDados[] = $parte;
            $blocosEc[]    = tr_qr_ec($parte, $qtdEc);
            $maxDados = max($maxDados, $dadosCw);
            $maxEc    = max($maxEc, $qtdEc);
        }
    }

    $saida = [];
    for ($i = 0; $i < $maxDados; $i++) {
        foreach ($blocosDados as $b) {
            if ($i < count($b)) { $saida[] = $b[$i]; }
        }
    }
    for ($i = 0; $i < $maxEc; $i++) {
        foreach ($blocosEc as $b) {
            if ($i < count($b)) { $saida[] = $b[$i]; }
        }
    }

    return $saida;
}

function tr_qr_matriz(string $texto, string $nivel = 'M'): array
{
    $nivel = strtoupper($nivel);
    if (!in_array($nivel, ['L', 'M', 'Q', 'H'], true)) { $nivel = 'M'; }

    for ($versao = 1; $versao <= 10; $versao++) {
        $dados = tr_qr_codewords($texto, $versao, $nivel);
        if (!$dados) { continue; }

        $melhorMascara = 0;
        $melhorNota = PHP_INT_MAX;
        for ($mascara = 0; $mascara < 8; $mascara++) {
            $nota = tr_qr_penalidade(tr_qr_montar($versao, $nivel, $dados, $mascara, true));
            if ($nota < $melhorNota) { $melhorNota = $nota; $melhorMascara = $mascara; }
        }
        return tr_qr_montar($versao, $nivel, $dados, $melhorMascara);
    }

    return [];
}

function tr_qr_svg(string $texto, array $opts = []): string
{
    $nivel  = (string)($opts['nivel'] ?? 'M');
    $margem = max(0, min(8, (int)($opts['margem'] ?? 2)));
    $cor    = (string)($opts['cor'] ?? '#000000');
    $fundo  = array_key_exists('fundo', $opts) ? (string)$opts['fundo'] : '#ffffff';
    $classe = (string)($opts['classe'] ?? '');
    $rotulo = (string)($opts['rotulo'] ?? 'QR Code de validação');

    $m = tr_qr_matriz($texto, $nivel);
    if (!$m) { return ''; }

    $n = count($m);
    $lado = $n + $margem * 2;

    $d = '';
    for ($r = 0; $r < $n; $r++) {
        $c = 0;
        while ($c < $n) {
            if (!$m[$r][$c]) { $c++; continue; }
            $ini = $c;
            while ($c < $n && $m[$r][$c]) { $c++; }
            $d .= 'M' . ($ini + $margem) . ' ' . ($r + $margem)
                . 'h' . ($c - $ini) . 'v1h-' . ($c - $ini) . 'z';
        }
    }

    $svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $lado . ' ' . $lado . '"';
    $svg .= ' role="img" aria-label="' . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . '"';
    if ($classe !== '') { $svg .= ' class="' . htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') . '"'; }
    $svg .= ' shape-rendering="crispEdges">';
    if ($fundo !== '') {
        $svg .= '<rect width="' . $lado . '" height="' . $lado . '" fill="'
             . htmlspecialchars($fundo, ENT_QUOTES, 'UTF-8') . '"/>';
    }
    $svg .= '<path d="' . $d . '" fill="' . htmlspecialchars($cor, ENT_QUOTES, 'UTF-8') . '"/>';
    $svg .= '</svg>';

    return $svg;
}

}
