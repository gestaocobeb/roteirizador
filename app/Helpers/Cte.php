<?php

namespace App\Helpers;

use RuntimeException;

/**
 * CTE (substitui a planilha "Ct'e PM", aba Cte). Não acessa o banco.
 *
 * Fontes, como na planilha:
 *  - 03.11.49.02 (aba "03114902"): placa, mapa, motorista (código), entregas (coluna PEDIDO),
 *    total peso e cidades. Uma linha por placa: vale o 1º mapa da placa no arquivo;
 *  - 03.03.12 (colado da tela, aba "03_03_12"): valor do mapa (coluna H);
 *  - Tabela de fretes da revenda (aba "TABELA CUSTOS FRETES"): cidades => valor - código.
 */
class Cte
{
    /** Linhas lidas até achar o cabeçalho do 03.11.49.02. */
    private const LIMITE_CABECALHO = 30;

    /**
     * 1º mapa de cada placa da revenda (coluna UNB), na ordem do arquivo.
     *
     * @param int $ignorados Recebe a quantidade de linhas de outras revendas
     * @return array<int, array> registros para CteMapaModel
     */
    public static function lerMapas(string $caminho, string $extensao, int $unb, int &$ignorados = 0): array
    {
        $ignorados = 0;
        $colunas = null;
        $lidas = 0;
        $placas = [];

        foreach (PlanilhaLeitor::linhas($caminho, $extensao, ['03 11 49 02']) as $linha) {
            if ($colunas === null) {
                $colunas = self::colunas($linha);
                if ($colunas === null && ++$lidas >= self::LIMITE_CABECALHO) {
                    break;
                }
                continue;
            }

            $mapa = Importador::codigo((string) ($linha[$colunas['mapa']] ?? ''));
            $placa = strtoupper(trim((string) ($linha[$colunas['placa']] ?? '')));
            if ($mapa === null || $placa === '') {
                continue;
            }

            if (!isset($colunas['unb'])) {
                throw new RuntimeException('O 03.11.49.02 enviado não tem a coluna "UNB" para separar a revenda.');
            }
            if (Importador::codigo((string) ($linha[$colunas['unb']] ?? '')) !== $unb) {
                $ignorados++;
                continue;
            }

            // Uma linha por placa, como na planilha: os mapas seguintes da placa (recargas) não entram.
            $placas[$placa] ??= [
                'data_entrega' => isset($colunas['data']) ? Importador::data((string) ($linha[$colunas['data']] ?? '')) : null,
                'numero_mapa' => (string) $mapa,
                'placa' => $placa,
                'motorista_promax' => isset($colunas['motorista']) ? Importador::codigo((string) ($linha[$colunas['motorista']] ?? '')) : null,
                'entregas' => (int) Importador::numero((string) ($linha[$colunas['entregas']] ?? '')),
                'peso' => Importador::numero((string) ($linha[$colunas['peso']] ?? '')),
                'cidades' => isset($colunas['cidades']) ? (self::cidades((string) ($linha[$colunas['cidades']] ?? '')) ?: null) : null,
            ];
        }

        if ($colunas === null) {
            throw new RuntimeException('O arquivo não parece ser o 03.11.49.02: faltam as colunas "Nro do Mapa", "Placa", "Entregas" e "Total peso".');
        }

        return array_values($placas);
    }

    /**
     * Valor de cada mapa no 03.03.12 colado da tela: [mapa => valor].
     * A linha começa pelo mapa; o valor (coluna H) é o primeiro número com centavos
     * ("100.466,52", "0,00"), depois das datas e da quantidade.
     */
    public static function lerValores(string $texto): array
    {
        $valores = [];

        foreach (preg_split('/\R/u', $texto) as $linha) {
            $campos = preg_split('/\s+/u', trim($linha), -1, PREG_SPLIT_NO_EMPTY);
            // Mapa sem o ponto de milhar e sem zeros à esquerda ("398.891" e "0398891" -> "398891").
            $mapa = preg_match('/^\d{1,3}(\.\d{3})+$|^\d+$/', $campos[0] ?? '') ? ltrim(str_replace('.', '', $campos[0]), '0') : '';
            if ($mapa === '') {
                continue;
            }

            foreach (array_slice($campos, 1) as $campo) {
                if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $campo)) {
                    $valores[$mapa] = Importador::numero($campo);
                    break;
                }
            }
        }

        return $valores;
    }

    /**
     * Motoristas colados (do Excel ou da tela): uma linha por mapa com o mapa, o código
     * e o nome, em qualquer ordem depois do mapa ("398891  197  LEANDRO GOMES" ou
     * "398891<TAB>LEANDRO GOMES<TAB>197"). Devolve [mapa => ['codigo' => ?, 'nome' => ?]].
     */
    public static function lerMotoristas(string $texto): array
    {
        $motoristas = [];

        foreach (preg_split('/\R/u', $texto) as $linha) {
            // Com TAB (cópia do Excel) cada célula é um campo; senão, separa por espaços.
            $campos = str_contains($linha, "\t")
                ? array_values(array_filter(array_map('trim', explode("\t", $linha)), 'strlen'))
                : preg_split('/\s+/u', trim($linha), -1, PREG_SPLIT_NO_EMPTY);

            $mapa = Importador::codigo((string) array_shift($campos));
            if ($mapa === null || !$campos) {
                continue;
            }

            $codigo = null;
            $nome = [];
            foreach ($campos as $campo) {
                if ($codigo === null && preg_match('/^\d+$/', $campo)) {
                    $codigo = ltrim($campo, '0') ?: '0';
                } else {
                    $nome[] = $campo;
                }
            }

            $nome = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', implode(' ', $nome))), 'UTF-8');
            if ($codigo === null && $nome === '') {
                continue;
            }

            $motoristas[(string) $mapa] = [
                'codigo' => $codigo !== null ? mb_substr($codigo, 0, 20) : null,
                'nome' => $nome !== '' ? mb_substr($nome, 0, 100) : null,
            ];
        }

        return $motoristas;
    }

    /**
     * Cidades do mapa na forma da tabela de fretes: maiúsculas, sem acento e sem as
     * quantidades ("NOVA SERRANA (10) / IGARATINGA (6)" -> "NOVA SERRANA / IGARATINGA").
     */
    public static function cidades(string $valor): string
    {
        $valor = mb_strtoupper(trim($valor), 'UTF-8');
        $valor = strtr($valor, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I',
            'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ç' => 'C',
        ]);

        $partes = [];
        foreach (explode('/', $valor) as $parte) {
            $parte = trim((string) preg_replace(['/\(\s*\d+\s*\)/', '/\s+/'], ['', ' '], $parte));
            if ($parte !== '') {
                $partes[] = $parte;
            }
        }

        return mb_substr(implode(' / ', $partes), 0, 255);
    }

    /**
     * "1053,71 - 2556", como a coluna PREÇO FRETE - COD CIDADE da planilha.
     */
    public static function freteTexto(?array $frete): string
    {
        return $frete ? number_format((float) $frete['valor'], 2, ',', '') . ' - ' . $frete['codigo'] : '';
    }

    /**
     * Índices das colunas do 03.11.49.02 na linha de cabeçalho, ou null se não for o cabeçalho.
     */
    private static function colunas(array $linha): ?array
    {
        $colunas = [];

        foreach ($linha as $i => $valor) {
            $nome = Importador::normalizar((string) $valor);
            $chave = match (true) {
                $nome === 'unb' => 'unb',
                $nome === 'nro do mapa' => 'mapa',
                $nome === 'data entrega' => 'data',
                $nome === 'placa' => 'placa',
                $nome === 'motorista' => 'motorista',
                $nome === 'entregas' => 'entregas',
                $nome === 'total peso' => 'peso',
                str_starts_with($nome, 'cidades') => 'cidades',
                default => null,
            };
            if ($chave !== null && !isset($colunas[$chave])) {
                $colunas[$chave] = $i;
            }
        }

        return isset($colunas['mapa'], $colunas['placa'], $colunas['entregas'], $colunas['peso']) ? $colunas : null;
    }
}
