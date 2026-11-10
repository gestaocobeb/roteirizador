<?php

namespace App\Helpers;

use RuntimeException;
use ZipArchive;

/**
 * Gera planilhas .xlsx (uma aba) sem bibliotecas externas: só a extensão zip.
 *
 * - gerar(): planilha simples com os estilos ESTILO_* abaixo;
 * - pacote(): monta o .xlsx com a folha, os estilos e o tema prontos (ex.: os da
 *   planilha original, para exportar no mesmo formato).
 *
 * Cada célula é um valor (string, int, float ou null) ou ['v' => valor, 's' => estilo].
 */
class XlsxEscritor
{
    public const PADRAO = 0;
    public const CABECALHO = 1;   // negrito, fundo cinza, borda, centralizado
    public const TEXTO = 2;       // borda
    public const MOEDA = 3;       // borda, #,##0.00
    public const INTEIRO = 4;     // borda, #,##0
    public const DATA = 5;        // negrito, dd/mm/aaaa (valor = 'AAAA-MM-DD')
    public const TITULO = 6;      // negrito, centralizado

    /**
     * @param array<int, array> $linhas   Linhas (a partir de A1), cada uma uma lista de células
     * @param float[]           $larguras Largura de cada coluna (A, B, ...)
     * @param string|null       $areaImpressao Ex.: "A1:J33"
     * @return string Conteúdo binário do .xlsx
     */
    public static function gerar(string $aba, array $linhas, array $larguras = [], ?string $areaImpressao = null): string
    {
        $cols = '';
        foreach (array_values($larguras) as $i => $largura) {
            $cols .= sprintf('<col min="%d" max="%d" width="%s" customWidth="1"/>', $i + 1, $i + 1, (float) $largura);
        }

        $folha = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . ($cols ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . self::linhasXml($linhas, self::DATA) . '</sheetData>'
            // Retrato, ajustado à largura de uma página.
            . '<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';

        return self::pacote($aba, $folha, self::estilos(), null, $areaImpressao);
    }

    /**
     * Monta o .xlsx a partir do XML da folha (worksheet), dos estilos e, opcionalmente, do tema.
     */
    public static function pacote(string $aba, string $folhaXml, string $estilosXml, ?string $temaXml = null, ?string $areaImpressao = null): string
    {
        $temporario = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();

        if ($temporario === false || $zip->open($temporario, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível gerar a planilha.');
        }

        $aba = mb_substr((string) preg_replace('/[\\\\\/?*\[\]:]/', '', $aba), 0, 31) ?: 'Planilha1';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($temaXml ? '<Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>' : '')
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . ($temaXml ? '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>' : '')
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xml($aba) . '" sheetId="1" r:id="rId1"/></sheets>'
            . ($areaImpressao ? '<definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\'' . self::xml($aba) . '\'!' . self::xml(self::absoluto($areaImpressao)) . '</definedName></definedNames>' : '')
            . '</workbook>');
        $zip->addFromString('xl/styles.xml', $estilosXml);
        if ($temaXml) {
            $zip->addFromString('xl/theme/theme1.xml', $temaXml);
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $folhaXml);
        $zip->close();

        $conteudo = (string) file_get_contents($temporario);
        @unlink($temporario);

        return $conteudo;
    }

    /**
     * XML das linhas (<row>...) a partir de A1.
     *
     * @param array<int, array> $linhas
     * @param int|null          $estiloData Estilo cujas células recebem 'AAAA-MM-DD' como data do Excel
     * @param array<int, float> $alturas    Altura por linha (índice 0 = linha 1)
     */
    public static function linhasXml(array $linhas, ?int $estiloData = null, array $alturas = []): string
    {
        $xml = '';
        foreach (array_values($linhas) as $l => $celulas) {
            $altura = $alturas[$l] ?? null;
            $xml .= '<row r="' . ($l + 1) . '"' . ($altura ? ' ht="' . (float) $altura . '" customHeight="1"' : '') . '>';
            foreach (array_values($celulas) as $c => $celula) {
                $xml .= self::celula(self::coluna($c) . ($l + 1), $celula, $estiloData);
            }
            $xml .= '</row>';
        }

        return $xml;
    }

    /** Letra da coluna (0 = A, 26 = AA). */
    public static function coluna(int $indice): string
    {
        $letra = '';
        for ($i = $indice + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $letra = chr(65 + ($i - 1) % 26) . $letra;
        }

        return $letra;
    }

    public static function xml(string $texto): string
    {
        // Remove caracteres de controle inválidos em XML.
        $texto = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto);

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function celula(string $ref, mixed $celula, ?int $estiloData): string
    {
        [$valor, $estilo] = is_array($celula) ? [$celula['v'] ?? null, (int) ($celula['s'] ?? 0)] : [$celula, 0];
        $s = $estilo ? ' s="' . $estilo . '"' : '';

        if ($valor === null || $valor === '') {
            return $estilo ? "<c r=\"{$ref}\"{$s}/>" : '';
        }

        if ($estiloData !== null && $estilo === $estiloData && is_string($valor) && ($t = strtotime($valor . ' 00:00:00 UTC')) !== false) {
            // Data do Excel: dias desde 30/12/1899.
            return "<c r=\"{$ref}\"{$s}><v>" . (int) round($t / 86400 + 25569) . '</v></c>';
        }

        if (is_int($valor) || is_float($valor)) {
            return "<c r=\"{$ref}\"{$s}><v>" . $valor . '</v></c>';
        }

        return "<c r=\"{$ref}\"{$s} t=\"inlineStr\"><is><t xml:space=\"preserve\">" . self::xml((string) $valor) . '</t></is></c>';
    }

    private static function estilos(): string
    {
        $borda = 'applyBorder="1" borderId="1"';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" ' . $borda . ' applyFont="1" applyFill="1" applyAlignment="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" ' . $borda . ' xfId="0"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" ' . $borda . ' applyNumberFormat="1" xfId="0"/>'
            . '<xf numFmtId="3" fontId="0" fillId="0" ' . $borda . ' applyNumberFormat="1" xfId="0"/>'
            . '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" applyNumberFormat="1" applyFont="1" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1" applyAlignment="1" xfId="0"><alignment horizontal="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** "A1:J33" -> "$A$1:$J$33". */
    private static function absoluto(string $area): string
    {
        return (string) preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', strtoupper($area));
    }
}
