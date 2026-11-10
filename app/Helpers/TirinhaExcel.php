<?php

namespace App\Helpers;

/**
 * Exporta as tirinhas no mesmo formato da aba "Plan1" da planilha "TIRINHA PM":
 * estilos e tema copiados da planilha (app/Helpers/Modelos), cabeçalho antes de
 * cada mapa (linhas de 47,45), bordas grossas, sem linhas de grade, TEMPO em h:mm
 * e A4 retrato em 61%.
 */
class TirinhaExcel
{
    private const ALTURA = 47.45;

    /** Larguras das colunas A:J e estilo da coluna, como na planilha. */
    private const COLUNAS = [
        [12.28515625, 6], [11.42578125, 6], [11.5703125, 6], [16, 6], [15.7109375, 6],
        [22.28515625, 6], [15.85546875, 0], [15.85546875, 0], [15.85546875, 0], [13.28515625, 20],
    ];

    /** Cabeçalho repetido antes de cada mapa: rótulo => estilo da planilha. */
    private const CABECALHO = [
        ['Mapas', 27], ['Inicial', 27], ['Final', 27], ['Emitidas', 27], ['Placas', 28],
        ['KM PLANEJADO', 4], ['BOLETO', 29], ['CARTÃO CREDITO', 30], ['PIX', 29], ['TEMPO', 31],
    ];

    /**
     * Estilos da linha do mapa (mapa, notas/placa/KM, contagens, tempo): o 1º mapa
     * usa os da linha 2 da planilha e os demais os da linha 4.
     */
    private const DADOS_PRIMEIRO = [24, 25, 26, 21];
    private const DADOS = [18, 5, 16, 19];

    /**
     * @param array $tirinhas Resultado de Tirinha::montar()
     */
    public static function gerar(array $tirinhas): string
    {
        $planilha = [];

        foreach (array_values($tirinhas) as $i => $t) {
            [$sMapa, $sTexto, $sConta, $sTempo] = $i === 0 ? self::DADOS_PRIMEIRO : self::DADOS;

            $planilha[] = array_map(fn ($c) => ['v' => $c[0], 's' => $c[1]], self::CABECALHO);
            $planilha[] = [
                ['v' => (int) $t['mapa'], 's' => $sMapa],
                ['v' => self::numero($t['inicial']), 's' => $sTexto],
                ['v' => self::numero($t['final']), 's' => $sTexto],
                ['v' => $t['emitidas'] === null ? null : (int) $t['emitidas'], 's' => $sTexto],
                ['v' => (string) $t['placa'], 's' => $sTexto],
                ['v' => (float) $t['km'], 's' => $sTexto],
                ['v' => $t['boleto'] === null ? null : (int) $t['boleto'], 's' => $sConta],
                ['v' => $t['cartao'] === null ? null : (int) $t['cartao'], 's' => $sConta],
                ['v' => $t['pix'] === null ? null : (int) $t['pix'], 's' => $sConta],
                ['v' => self::fracaoDia((string) $t['tempo']), 's' => $sTempo],
            ];
        }

        $cols = '';
        foreach (self::COLUNAS as $i => [$largura, $estilo]) {
            $cols .= sprintf('<col min="%d" max="%d" width="%s"%s customWidth="1"/>', $i + 1, $i + 1, $largura, $estilo ? ' style="' . $estilo . '"' : '');
        }

        $linhas = XlsxEscritor::linhasXml($planilha, null, array_fill(0, count($planilha), self::ALTURA));
        // Bordas grossas em cima e embaixo de cada linha, como na planilha.
        $linhas = str_replace('customHeight="1">', 'customHeight="1" thickTop="1" thickBot="1">', $linhas);

        $folha = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<sheetViews><sheetView showGridLines="0" tabSelected="1" workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="47.25" customHeight="1"/>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . $linhas . '</sheetData>'
            . '<pageMargins left="0.511811024" right="0.511811024" top="0.787401575" bottom="0.787401575" header="0.31496062" footer="0.31496062"/>'
            . '<pageSetup paperSize="9" scale="61" fitToHeight="0" orientation="portrait"/>'
            . '</worksheet>';

        $modelos = __DIR__ . '/Modelos/';

        return XlsxEscritor::pacote(
            'Plan1',
            $folha,
            (string) file_get_contents($modelos . 'tirinha-estilos.xml'),
            (string) file_get_contents($modelos . 'tirinha-tema.xml'),
            'A1:J' . max(count($planilha), 1)
        );
    }

    /** Número da nota ("112569") como número, ou vazio. */
    private static function numero(?string $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    /** "03:41" -> fração do dia do Excel (formato h:mm da planilha), ou vazio. */
    private static function fracaoDia(string $tempo): ?float
    {
        if (!preg_match('/^(\d+):(\d{2})$/', $tempo, $m)) {
            return null;
        }

        return round(((int) $m[1] * 60 + (int) $m[2]) / 1440, 10);
    }
}
