<?php
if (!class_exists('TrDocx')) {

class TrDocx
{
    private const LARGURA_UTIL = 9638;

    private array $corpo = [];
    private string $titulo;
    private string $assunto;
    private string $autor;

    public function __construct(string $titulo, string $assunto = '', string $autor = '')
    {
        $this->titulo  = $titulo;
        $this->assunto = $assunto;
        $this->autor   = $autor !== '' ? $autor : 'RH Digital';
    }

    private function esc(string $t): string
    {
        return htmlspecialchars($t, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function runs(string $texto, string $estilo = ''): string
    {
        $props = $estilo !== '' ? '<w:rPr>' . $estilo . '</w:rPr>' : '';
        $linhas = preg_split('/\r\n|\r|\n/', $texto);
        $partes = [];
        foreach ($linhas as $i => $linha) {
            if ($i > 0) { $partes[] = '<w:r>' . $props . '<w:br/></w:r>'; }
            if ($linha === '') { continue; }
            $partes[] = '<w:r>' . $props . '<w:t xml:space="preserve">'
                      . $this->esc($linha) . '</w:t></w:r>';
        }
        return $partes ? implode('', $partes) : '<w:r>' . $props . '<w:t/></w:r>';
    }

    public function titulo1(string $t): void
    {
        $this->corpo[] = '<w:p><w:pPr><w:pStyle w:val="Titulo1"/></w:pPr>'
                       . $this->runs($t) . '</w:p>';
    }

    public function titulo2(string $t): void
    {
        $this->corpo[] = '<w:p><w:pPr><w:pStyle w:val="Titulo2"/></w:pPr>'
                       . $this->runs($t) . '</w:p>';
    }

    public function titulo3(string $t): void
    {
        $this->corpo[] = '<w:p><w:pPr><w:pStyle w:val="Titulo3"/></w:pPr>'
                       . $this->runs($t) . '</w:p>';
    }

    public function paragrafo(string $t, bool $negrito = false, bool $italico = false, int $tam = 0): void
    {
        $rpr = ($negrito ? '<w:b/>' : '') . ($italico ? '<w:i/>' : '')
             . ($tam > 0 ? '<w:sz w:val="' . ($tam * 2) . '"/>' : '');
        $this->corpo[] = '<w:p>' . $this->runs($t, $rpr) . '</w:p>';
    }

    public function nota(string $t): void
    {
        $this->corpo[] = '<w:p><w:pPr><w:spacing w:before="0" w:after="60"/></w:pPr>'
                       . $this->runs($t, '<w:i/><w:color w:val="6B7A8C"/><w:sz w:val="17"/>')
                       . '</w:p>';
    }

    public function lista(array $itens): void
    {
        foreach ($itens as $i) {
            $this->corpo[] = '<w:p><w:pPr><w:pStyle w:val="Marcador"/>'
                           . '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>'
                           . $this->runs((string)$i) . '</w:p>';
        }
    }

    public function espaco(int $pontos = 6): void
    {
        $this->corpo[] = '<w:p><w:pPr><w:spacing w:after="' . ($pontos * 20) . '"/></w:pPr></w:p>';
    }

    public function quebraPagina(): void
    {
        $this->corpo[] = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    }

    public function tabela(array $cab, array $linhas, array $larguras, bool $zebra = true): void
    {
        $cols = max(count($cab), $linhas ? count($linhas[0]) : count($larguras));
        if (!$larguras) { $larguras = array_fill(0, $cols, intdiv(100, $cols)); }

        $grid = '';
        foreach ($larguras as $p) {
            $grid .= '<w:gridCol w:w="' . (int)round(self::LARGURA_UTIL * $p / 100) . '"/>';
        }

        $xml = '<w:tbl><w:tblPr>'
             . '<w:tblW w:w="' . self::LARGURA_UTIL . '" w:type="dxa"/>'
             . '<w:tblBorders>'
             . '<w:top w:val="single" w:sz="4" w:color="D8E0E8"/>'
             . '<w:left w:val="single" w:sz="4" w:color="D8E0E8"/>'
             . '<w:bottom w:val="single" w:sz="4" w:color="D8E0E8"/>'
             . '<w:right w:val="single" w:sz="4" w:color="D8E0E8"/>'
             . '<w:insideH w:val="single" w:sz="4" w:color="D8E0E8"/>'
             . '<w:insideV w:val="single" w:sz="4" w:color="D8E0E8"/>'
             . '</w:tblBorders>'
             . '<w:tblCellMar>'
             . '<w:top w:w="80" w:type="dxa"/><w:left w:w="110" w:type="dxa"/>'
             . '<w:bottom w:w="80" w:type="dxa"/><w:right w:w="110" w:type="dxa"/>'
             . '</w:tblCellMar></w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>';

        if ($cab) {
            $xml .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
            foreach ($cab as $i => $c) {
                $xml .= $this->celula((string)$c, $larguras[$i] ?? 0, '1E3A5F', true, 'FFFFFF');
            }
            $xml .= '</w:tr>';
        }

        foreach ($linhas as $n => $linha) {
            $fundo = ($zebra && $n % 2 === 1) ? 'F4F7FA' : '';
            $xml .= '<w:tr>';
            foreach ($linha as $i => $c) {
                $xml .= $this->celula((string)$c, $larguras[$i] ?? 0, $fundo, false, '');
            }
            $xml .= '</w:tr>';
        }

        $this->corpo[] = $xml . '</w:tbl>';
        $this->espaco(4);
    }

    private function celula(string $texto, int $pct, string $fundo, bool $negrito, string $cor): string
    {
        $rpr = ($negrito ? '<w:b/>' : '')
             . ($cor !== '' ? '<w:color w:val="' . $cor . '"/>' : '')
             . '<w:sz w:val="19"/>';
        $sombra = $fundo !== '' ? '<w:shd w:val="clear" w:fill="' . $fundo . '"/>' : '';
        return '<w:tc><w:tcPr>'
             . '<w:tcW w:w="' . (int)round(self::LARGURA_UTIL * $pct / 100) . '" w:type="dxa"/>'
             . $sombra . '<w:vAlign w:val="top"/></w:tcPr>'
             . '<w:p><w:pPr><w:spacing w:before="20" w:after="20"/></w:pPr>'
             . $this->runs($texto, $rpr) . '</w:p></w:tc>';
    }

    public function caixa(string $titulo, string $texto, string $cor = '1E3A5F', string $fundo = 'F4F7FA'): void
    {
        $conteudo = '<w:p><w:pPr><w:spacing w:before="40" w:after="20"/></w:pPr>'
                  . $this->runs($titulo, '<w:b/><w:color w:val="' . $cor . '"/><w:sz w:val="19"/>')
                  . '</w:p>';
        if ($texto !== '') {
            $conteudo .= '<w:p><w:pPr><w:spacing w:before="0" w:after="40"/></w:pPr>'
                       . $this->runs($texto, '<w:sz w:val="19"/>') . '</w:p>';
        }

        $this->corpo[] = '<w:tbl><w:tblPr>'
            . '<w:tblW w:w="' . self::LARGURA_UTIL . '" w:type="dxa"/>'
            . '<w:tblBorders>'
            . '<w:top w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/>'
            . '<w:left w:val="single" w:sz="18" w:color="' . $cor . '"/>'
            . '</w:tblBorders>'
            . '<w:tblCellMar><w:left w:w="200" w:type="dxa"/><w:right w:w="160" w:type="dxa"/>'
            . '<w:top w:w="60" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/></w:tblCellMar>'
            . '</w:tblPr><w:tblGrid><w:gridCol w:w="' . self::LARGURA_UTIL . '"/></w:tblGrid>'
            . '<w:tr><w:tc><w:tcPr>'
            . '<w:tcW w:w="' . self::LARGURA_UTIL . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:fill="' . $fundo . '"/></w:tcPr>'
            . $conteudo . '</w:tc></w:tr></w:tbl>';
        $this->espaco(6);
    }

    public function espacoRevisao(string $rotulo = 'Observações da revisão'): void
    {
        $this->caixa($rotulo, str_repeat("\n", 2), 'B08A2E', 'FFFBF2');
    }

    private function documentoXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . implode('', $this->corpo)
            . '<w:sectPr>'
            . '<w:footerReference w:type="default" r:id="rIdF" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>'
            . '<w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" '
            . 'w:header="567" w:footer="567" w:gutter="0"/>'
            . '</w:sectPr></w:body></w:document>';
    }

    private function estilosXml(): string
    {
        $base = '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr>' . $base
            . '<w:sz w:val="21"/><w:szCs w:val="21"/><w:color w:val="1F2A36"/>'
            . '</w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:before="0" w:after="120" w:line="276" '
            . 'w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'

            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
            . '<w:name w:val="Normal"/></w:style>'

            . '<w:style w:type="paragraph" w:styleId="Titulo1">'
            . '<w:name w:val="heading 1"/><w:basedOn w:val="Normal"/>'
            . '<w:pPr><w:outlineLvl w:val="0"/><w:spacing w:before="360" w:after="140"/>'
            . '<w:pBdr><w:bottom w:val="single" w:sz="8" w:color="1E3A5F"/></w:pBdr></w:pPr>'
            . '<w:rPr><w:b/><w:sz w:val="34"/><w:color w:val="1E3A5F"/></w:rPr></w:style>'

            . '<w:style w:type="paragraph" w:styleId="Titulo2">'
            . '<w:name w:val="heading 2"/><w:basedOn w:val="Normal"/>'
            . '<w:pPr><w:outlineLvl w:val="1"/><w:spacing w:before="260" w:after="100"/></w:pPr>'
            . '<w:rPr><w:b/><w:sz w:val="26"/><w:color w:val="1E3A5F"/></w:rPr></w:style>'

            . '<w:style w:type="paragraph" w:styleId="Titulo3">'
            . '<w:name w:val="heading 3"/><w:basedOn w:val="Normal"/>'
            . '<w:pPr><w:outlineLvl w:val="2"/><w:spacing w:before="180" w:after="70"/></w:pPr>'
            . '<w:rPr><w:b/><w:sz w:val="21"/><w:color w:val="3D5570"/>'
            . '<w:caps/><w:spacing w:val="24"/></w:rPr></w:style>'

            . '<w:style w:type="paragraph" w:styleId="Marcador">'
            . '<w:name w:val="List Paragraph"/><w:basedOn w:val="Normal"/>'
            . '<w:pPr><w:ind w:left="454" w:hanging="227"/>'
            . '<w:spacing w:before="0" w:after="60"/></w:pPr></w:style>'

            . '</w:styles>';
    }

    private function numeracaoXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0">'
            . '<w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="' . "\u{2022}" . '"/>'
            . '<w:lvlJc w:val="left"/>'
            . '<w:pPr><w:ind w:left="454" w:hanging="227"/></w:pPr>'
            . '<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr>'
            . '</w:lvl></w:abstractNum>'
            . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
            . '</w:numbering>';
    }

    private function rodapeXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:p><w:pPr><w:jc w:val="center"/>'
            . '<w:pBdr><w:top w:val="single" w:sz="4" w:color="D8E0E8"/></w:pBdr></w:pPr>'
            . '<w:r><w:rPr><w:sz w:val="16"/><w:color w:val="8496A8"/></w:rPr>'
            . '<w:t xml:space="preserve">' . $this->esc($this->titulo) . "  \u{2022}  p\u{e1}gina " . '</w:t></w:r>'
            . '<w:r><w:rPr><w:sz w:val="16"/><w:color w:val="8496A8"/></w:rPr>'
            . '<w:fldChar w:fldCharType="begin"/></w:r>'
            . '<w:r><w:rPr><w:sz w:val="16"/></w:rPr><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>'
            . '<w:r><w:fldChar w:fldCharType="end"/></w:r>'
            . '</w:p></w:ftr>';
    }

    public function salvar(string $caminho): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
            . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>');

        $zip->addFromString('word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rIdN" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>'
            . '<Relationship Id="rIdF" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
            . '</Relationships>');

        $zip->addFromString('word/document.xml', $this->documentoXml());
        $zip->addFromString('word/styles.xml', $this->estilosXml());
        $zip->addFromString('word/numbering.xml', $this->numeracaoXml());
        $zip->addFromString('word/footer1.xml', $this->rodapeXml());

        $agora = gmdate('Y-m-d\TH:i:s\Z');
        $zip->addFromString('docProps/core.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties '
            . 'xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . $this->esc($this->titulo) . '</dc:title>'
            . '<dc:subject>' . $this->esc($this->assunto) . '</dc:subject>'
            . '<dc:creator>' . $this->esc($this->autor) . '</dc:creator>'
            . '<cp:lastModifiedBy>' . $this->esc($this->autor) . '</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $agora . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $agora . '</dcterms:modified>'
            . '</cp:coreProperties>');

        $zip->addFromString('docProps/app.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            . '<Application>RH Digital</Application></Properties>');

        return $zip->close();
    }

    public function enviar(string $nomeArquivo): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'trdocx');
        if ($tmp === false || !$this->salvar($tmp)) {
            http_response_code(500);
            echo 'Não foi possível gerar o documento.';
            exit;
        }

        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}

}
