<?php

namespace App\Helpers;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * PlanilhaLeitor
 *
 * Leitura em streaming de planilhas .xlsx e .csv exportadas do Cora/Promax.
 * As linhas são lidas uma a uma (Generator) para suportar arquivos grandes
 * sem carregar a planilha inteira em memória. Não depende de bibliotecas
 * externas: usa apenas as extensões zip, xmlreader e mbstring do PHP.
 */
class PlanilhaLeitor
{
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * Retorna as linhas da planilha, indexadas pela posição da coluna (0 = A).
     *
     * @param array $abasPreferidas Nomes de aba a procurar no .xlsx; se nenhuma
     *                              existir, a primeira aba é utilizada.
     */
    public static function linhas(string $caminho, string $extensao, array $abasPreferidas = []): \Generator
    {
        return $extensao === 'csv'
            ? self::linhasCsv($caminho)
            : self::linhasXlsx($caminho, $abasPreferidas);
    }

    private static function linhasCsv(string $caminho): \Generator
    {
        $arquivo = fopen($caminho, 'r');

        if ($arquivo === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo CSV.');
        }

        try {
            $primeira = (string) fgets($arquivo);
            rewind($arquivo);
            $delimitador = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';

            while (($linha = fgetcsv($arquivo, 0, $delimitador)) !== false) {
                yield array_map([self::class, 'paraUtf8'], $linha);
            }
        } finally {
            fclose($arquivo);
        }
    }

    private static function paraUtf8(?string $valor): string
    {
        $valor = str_replace("\xEF\xBB\xBF", '', (string) $valor);

        // Exports do Promax costumam vir em Windows-1252.
        if (!mb_check_encoding($valor, 'UTF-8')) {
            $valor = mb_convert_encoding($valor, 'UTF-8', 'Windows-1252');
        }

        return trim($valor);
    }

    private static function linhasXlsx(string $caminho, array $abasPreferidas): \Generator
    {
        $zip = new ZipArchive();

        if ($zip->open($caminho) !== true) {
            throw new RuntimeException('Arquivo .xlsx inválido ou corrompido.');
        }

        $aba = self::localizarAba($zip, $abasPreferidas);
        $temCompartilhadas = $zip->locateName('xl/sharedStrings.xml') !== false;
        $zip->close();

        $compartilhadas = $temCompartilhadas ? self::stringsCompartilhadas($caminho) : [];

        $xml = new XMLReader();

        if (!$xml->open('zip://' . $caminho . '#' . $aba)) {
            throw new RuntimeException('Não foi possível ler a aba da planilha.');
        }

        $linha = [];
        $coluna = null;
        $proximaColuna = 0;
        $tipo = null;
        $valor = '';

        try {
            while ($xml->read()) {
                if ($xml->nodeType === XMLReader::ELEMENT) {
                    switch ($xml->localName) {
                        case 'row':
                            $linha = [];
                            $proximaColuna = 0;
                            if ($xml->isEmptyElement) {
                                yield [];
                            }
                            break;

                        case 'c':
                            $referencia = $xml->getAttribute('r');
                            $indice = $referencia ? self::indiceColuna($referencia) : $proximaColuna;
                            $proximaColuna = $indice + 1;
                            // Célula vazia (<c r="A1"/>) não possui conteúdo nem tag de fechamento.
                            $coluna = $xml->isEmptyElement ? null : $indice;
                            $tipo = $xml->getAttribute('t');
                            $valor = '';
                            break;

                        case 'v':
                            $valor = $xml->readString();
                            break;

                        case 't':
                            if ($tipo === 'inlineStr') {
                                $valor .= $xml->readString();
                            }
                            break;
                    }
                } elseif ($xml->nodeType === XMLReader::END_ELEMENT) {
                    if ($xml->localName === 'c' && $coluna !== null) {
                        if ($tipo === 's') {
                            $valor = $compartilhadas[(int) $valor] ?? '';
                        }
                        $linha[$coluna] = trim((string) $valor);
                        $coluna = null;
                    } elseif ($xml->localName === 'row') {
                        yield self::preencher($linha);
                    }
                }
            }
        } finally {
            $xml->close();
        }
    }

    /**
     * Localiza o caminho interno da aba desejada (ou da primeira aba).
     */
    private static function localizarAba(ZipArchive $zip, array $abasPreferidas): string
    {
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $relacoes = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));

        if ($workbook === false || $relacoes === false) {
            throw new RuntimeException('Estrutura do arquivo .xlsx não reconhecida.');
        }

        $alvos = [];
        foreach ($relacoes->Relationship as $relacao) {
            $alvo = ltrim((string) $relacao['Target'], '/');
            $alvos[(string) $relacao['Id']] = str_starts_with($alvo, 'xl/') ? $alvo : 'xl/' . $alvo;
        }

        $abas = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $id = (string) $sheet->attributes(self::NS_REL)['id'];
            if (isset($alvos[$id])) {
                $abas[Importador::normalizar((string) $sheet['name'])] = $alvos[$id];
            }
        }

        if ($abas === []) {
            throw new RuntimeException('Nenhuma aba encontrada no arquivo .xlsx.');
        }

        foreach ($abasPreferidas as $nome) {
            $chave = Importador::normalizar($nome);
            if (isset($abas[$chave])) {
                return $abas[$chave];
            }
        }

        return reset($abas);
    }

    private static function stringsCompartilhadas(string $caminho): array
    {
        $xml = new XMLReader();

        if (!$xml->open('zip://' . $caminho . '#xl/sharedStrings.xml')) {
            return [];
        }

        $lista = [];
        $atual = null;
        $fonetico = false;

        while ($xml->read()) {
            if ($xml->nodeType === XMLReader::ELEMENT) {
                if ($xml->localName === 'si') {
                    $atual = '';
                    if ($xml->isEmptyElement) {
                        $lista[] = '';
                        $atual = null;
                    }
                } elseif ($xml->localName === 'rPh') {
                    // Texto fonético (japonês) não faz parte do valor da célula.
                    $fonetico = !$xml->isEmptyElement;
                } elseif ($xml->localName === 't' && $atual !== null && !$fonetico) {
                    $atual .= $xml->readString();
                }
            } elseif ($xml->nodeType === XMLReader::END_ELEMENT) {
                if ($xml->localName === 'si' && $atual !== null) {
                    $lista[] = $atual;
                    $atual = null;
                } elseif ($xml->localName === 'rPh') {
                    $fonetico = false;
                }
            }
        }

        $xml->close();

        return $lista;
    }

    /**
     * Converte a referência da célula (ex.: "AB12") no índice da coluna (ex.: 27).
     */
    private static function indiceColuna(string $referencia): int
    {
        $letras = preg_replace('/[^A-Z]/', '', strtoupper($referencia));
        $indice = 0;

        foreach (str_split($letras) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }

        return max(0, $indice - 1);
    }

    /**
     * Garante índices contínuos (colunas vazias viram string vazia).
     */
    private static function preencher(array $linha): array
    {
        if ($linha === []) {
            return [];
        }

        $completa = array_fill(0, max(array_keys($linha)) + 1, '');

        return array_replace($completa, $linha);
    }
}
