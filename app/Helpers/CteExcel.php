<?php

namespace App\Helpers;

/**
 * Exporta o CTE no mesmo formato da aba "Cte" da planilha "Ct'e PM": estilos e tema
 * copiados da planilha (app/Helpers/Modelos), data mesclada em A1:J1, cabeçalho na
 * linha 2, larguras, altura das linhas, destaque verde de PARA DE MINAS, A4 retrato
 * em 68% e cabeçalho/rodapé ("Cte" e "Página N").
 */
class CteExcel
{
    // Índices dos estilos (cellXfs) da planilha original.
    private const DATA = 31;
    private const CABECALHO = 16;
    private const CABECALHO_CENTRO = 17;
    private const CABECALHO_PLACA = 18;
    private const CABECALHO_FRETE = 22;
    private const DADO = 3;
    private const DADO_FRETE = 20;
    private const DADO_TEXTO = 2;
    private const DADO_VALOR = 32; // acrescentado ao modelo: igual ao 3 com #,##0.00

    /** Larguras das colunas A:J e estilo da coluna, como na planilha. */
    private const COLUNAS = [
        [12.42578125, 2], [6.85546875, 20], [10.28515625, 2], [16.42578125, 20], [12, 2],
        [8.5703125, 2], [7.85546875, 2], [39.5703125, 2], [24.28515625, 2], [8.140625, 1],
    ];

    /**
     * @param array  $linhas Linhas do CTE (CteMapaModel + 'frete')
     * @param string $data   Data de entrega (AAAA-MM-DD) da célula A1
     */
    public static function gerar(array $linhas, string $data): string
    {
        $planilha = [
            [['v' => $data, 's' => self::DATA], ...array_fill(0, 9, ['v' => null, 's' => self::DATA])],
            [
                ['v' => "Ct'e", 's' => self::CABECALHO],
                ['v' => 'MAPA', 's' => self::CABECALHO_CENTRO],
                ['v' => 'PLACA', 's' => self::CABECALHO_PLACA],
                ['v' => 'PREÇO FRETE - COD CIDADE', 's' => self::CABECALHO_FRETE],
                ['v' => 'VALOR', 's' => self::CABECALHO_CENTRO],
                ['v' => 'PEDIDO', 's' => self::CABECALHO],
                ['v' => 'PESO', 's' => self::CABECALHO],
                ['v' => 'CIDADE', 's' => self::CABECALHO],
                ['v' => 'MOTORISTA', 's' => self::CABECALHO],
                ['v' => 'CODIGO', 's' => self::CABECALHO],
            ],
        ];

        foreach ($linhas as $l) {
            $planilha[] = [
                ['v' => (string) $l['cte_numero'], 's' => self::DADO],
                ['v' => (int) $l['numero_mapa'], 's' => self::DADO],
                ['v' => (string) $l['placa'], 's' => self::DADO],
                ['v' => Cte::freteTexto($l['frete']), 's' => self::DADO_FRETE],
                ['v' => $l['valor'] === null ? null : (float) $l['valor'], 's' => self::DADO_VALOR],
                ['v' => (int) $l['entregas'], 's' => self::DADO_TEXTO],
                ['v' => (int) round((float) $l['peso']), 's' => self::DADO_TEXTO],
                ['v' => (string) $l['cidades'], 's' => self::DADO_TEXTO],
                ['v' => (string) $l['motorista_nome'], 's' => self::DADO_TEXTO],
                ['v' => (string) $l['motorista_codigo'], 's' => self::DADO_TEXTO],
            ];
        }

        $ultima = max(count($planilha), 3);
        // Altura da planilha (18,95); cidades longas quebram na coluna H (~44 caracteres por linha).
        $alturas = [21, 18.95];
        foreach ($linhas as $l) {
            $alturas[] = 18.95 * max(1, (int) ceil(mb_strlen((string) $l['cidades']) / 44));
        }

        $cols = '';
        foreach (self::COLUNAS as $i => [$largura, $estilo]) {
            $cols .= sprintf('<col min="%d" max="%d" width="%s" style="%d" customWidth="1"/>', $i + 1, $i + 1, $largura, $estilo);
        }

        $folha = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18.95" customHeight="1"/>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . XlsxEscritor::linhasXml($planilha, self::DATA, $alturas) . '</sheetData>'
            . '<mergeCells count="1"><mergeCell ref="A1:J1"/></mergeCells>'
            // Mesmo destaque da planilha: linhas de PARA DE MINAS em verde.
            . '<conditionalFormatting sqref="A3:J' . $ultima . '"><cfRule type="expression" dxfId="0" priority="1" stopIfTrue="1">'
            . '<formula>TRIM($H3)="PARA DE MINAS"</formula></cfRule></conditionalFormatting>'
            . '<pageMargins left="0" right="0" top="0.39370078740157483" bottom="0.39370078740157483" header="0" footer="0"/>'
            . '<pageSetup paperSize="9" scale="68" pageOrder="overThenDown" orientation="portrait"/>'
            . '<headerFooter><oddHeader>&amp;C&amp;A</oddHeader><oddFooter>&amp;CPágina &amp;P</oddFooter></headerFooter>'
            . '</worksheet>';

        $modelos = __DIR__ . '/Modelos/';

        return XlsxEscritor::pacote(
            'Cte',
            $folha,
            (string) file_get_contents($modelos . 'cte-estilos.xml'),
            (string) file_get_contents($modelos . 'cte-tema.xml'),
            'A1:J' . $ultima
        );
    }
}
