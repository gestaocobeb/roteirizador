<?php

namespace App\Helpers;

use RuntimeException;

/**
 * Tirinha de mapa para o motorista (substitui a planilha "TIRINHA PM", aba Plan1).
 *
 * Fontes, como na planilha:
 *  - 03.11.49.02 (arquivo, aba "Promax"): Nro do mapa, placa, KM prev. e tempo prev. (+almoço);
 *  - 03.02.31 (colado da tela): mapa, nota inicial, nota final ... emitidas (8ª coluna);
 *  - 03.02.37 (um ou mais arquivos): notas do mapa com boleto (Nr Tit. Bco preenchido),
 *    cartão de crédito (Cond. pagt 400) e pix (Cond. pagt 850).
 * Nada é gravado no banco.
 */
class Tirinha
{
    public const COND_CARTAO = '400';
    public const COND_PIX = '850';

    /** Linhas lidas até achar o cabeçalho do 03.11.49.02. */
    private const LIMITE_CABECALHO = 30;

    /**
     * Mapas do 03.11.49.02 da revenda (coluna UNB), na ordem do arquivo: [mapa => dados].
     *
     * @param int|null $unb        Código UNB da revenda (Revenda::REVENDAS); null = todas
     * @param int      $ignorados  Recebe a quantidade de mapas de outras revendas
     */
    public static function lerMapas(string $caminho, string $extensao, ?int $unb = null, int &$ignorados = 0): array
    {
        $ignorados = 0;
        $colunas = null;
        $mapas = [];
        $lidas = 0;

        foreach (PlanilhaLeitor::linhas($caminho, $extensao, ['03 11 49 02']) as $linha) {
            if ($colunas === null) {
                $colunas = self::colunasMapas($linha);
                if ($colunas === null && ++$lidas >= self::LIMITE_CABECALHO) {
                    break;
                }
                continue;
            }

            $mapa = self::mapa((string) ($linha[$colunas['mapa']] ?? ''));
            if ($mapa === null) {
                continue;
            }

            if ($unb !== null) {
                if (!isset($colunas['unb'])) {
                    throw new RuntimeException('O 03.11.49.02 enviado não tem a coluna "UNB" para separar a revenda.');
                }
                if (Importador::codigo((string) ($linha[$colunas['unb']] ?? '')) !== $unb) {
                    $ignorados++;
                    continue;
                }
            }

            $mapas[$mapa] = [
                'mapa' => $mapa,
                'placa' => strtoupper(trim((string) ($linha[$colunas['placa']] ?? ''))),
                'km' => Importador::numero((string) ($linha[$colunas['km']] ?? '')),
                'tempo' => self::tempo((string) ($linha[$colunas['tempo']] ?? '')),
                'data_entrega' => isset($colunas['data']) ? Importador::data((string) ($linha[$colunas['data']] ?? '')) : null,
            ];
        }

        if ($colunas === null) {
            throw new RuntimeException('O arquivo de mapas não parece ser o 03.11.49.02: faltam as colunas "Nro do Mapa", "Placa", "KM Prev." e "Tempo Prev.".');
        }

        return $mapas;
    }

    /**
     * Linhas do 03.02.31: [mapa => [inicial, final, emitidas]].
     * Como na planilha (intervalo B:K), emitidas é a 8ª coluna a partir do mapa.
     */
    public static function lerNotas(string $texto): array
    {
        $notas = [];

        foreach (preg_split('/\R/u', $texto) as $linha) {
            $campos = preg_split('/\s+/u', trim($linha), -1, PREG_SPLIT_NO_EMPTY);
            $mapa = self::mapa($campos[0] ?? '');
            if ($mapa === null || count($campos) < 4) {
                continue;
            }

            $notas[$mapa] = [
                'inicial' => self::digitos($campos[1]),
                'final' => self::digitos($campos[2]),
                'emitidas' => (int) self::digitos($campos[7] ?? $campos[3]),
            ];
        }

        return $notas;
    }

    /**
     * Notas por mapa e forma de pagamento nos arquivos do 03.02.37.
     * Uma nota que aparece em mais de um arquivo (ou em várias linhas) conta uma vez.
     *
     * @param string[] $caminhos
     * 'cartaoNotas' traz o detalhe das notas no cartão por mapa (para a imagem do WhatsApp).
     *
     * @return array{boleto: array<string,int>, cartao: array<string,int>, pix: array<string,int>, cartaoNotas: array<string,array>, temCondicao: bool}
     */
    public static function contarPagamentos(array $caminhos): array
    {
        $notas = ['boleto' => [], 'cartao' => [], 'pix' => []];
        $cartaoNotas = [];
        $temCondicao = false;

        foreach ($caminhos as $caminho) {
            $col = null;

            foreach (PlanilhaLeitor::linhas($caminho, 'csv') as $linha) {
                if ($col === null) {
                    $cabecalho = array_map([Importador::class, 'normalizar'], $linha);
                    $col = [
                        'mapa' => array_search('mapa', $cabecalho, true),
                        'nota' => array_search('nota', $cabecalho, true),
                        'titulo' => array_search('nr tit bco', $cabecalho, true),
                        'condicao' => array_search('cond pagt', $cabecalho, true),
                        'cliente' => array_search('cliente', $cabecalho, true),
                        'nome' => array_search('nome', $cabecalho, true),
                        'valor' => array_search('total', $cabecalho, true),
                    ];

                    if ($col['mapa'] === false || $col['nota'] === false) {
                        throw new RuntimeException('Um dos arquivos enviados não parece ser o 03.02.37: faltam as colunas "Mapa" e "Nota".');
                    }
                    $temCondicao = $temCondicao || $col['condicao'] !== false;
                    continue;
                }

                $mapa = self::mapa((string) ($linha[$col['mapa']] ?? ''));
                $nota = self::digitos((string) ($linha[$col['nota']] ?? ''));
                if ($mapa === null || $nota === '') {
                    continue;
                }

                if ($col['titulo'] !== false && ltrim(trim((string) ($linha[$col['titulo']] ?? '')), '0') !== '') {
                    $notas['boleto'][$mapa][$nota] = true;
                }

                $condicao = $col['condicao'] !== false ? ltrim(trim((string) ($linha[$col['condicao']] ?? '')), '0') : '';
                if ($condicao === self::COND_CARTAO) {
                    $notas['cartao'][$mapa][$nota] = true;
                    $campo = fn (string $chave) => $col[$chave] !== false ? trim((string) ($linha[$col[$chave]] ?? '')) : '';
                    $cartaoNotas[$mapa][$nota] = [
                        'nota' => $nota,
                        'cliente' => ltrim($campo('cliente'), '0'),
                        'nome' => $campo('nome'),
                        'valor' => Importador::numero($campo('valor')),
                    ];
                } elseif ($condicao === self::COND_PIX) {
                    $notas['pix'][$mapa][$nota] = true;
                }
            }

            if ($col === null) {
                throw new RuntimeException('Um dos arquivos do 03.02.37 está vazio.');
            }
        }

        return [
            'boleto' => array_map('count', $notas['boleto']),
            'cartao' => array_map('count', $notas['cartao']),
            'cartaoNotas' => array_map('array_values', $cartaoNotas),
            'pix' => array_map('count', $notas['pix']),
            'temCondicao' => $temCondicao,
        ];
    }

    /**
     * Junta as fontes na ordem do 03.11.49.02 (uma tirinha por mapa).
     *
     * @param array|null $pagamentos Resultado de contarPagamentos(); null = 03.02.37 não enviado
     */
    public static function montar(array $mapas, array $notas, ?array $pagamentos): array
    {
        $tirinhas = [];
        $comCondicao = $pagamentos !== null && $pagamentos['temCondicao'];

        foreach ($mapas as $mapa => $m) {
            $n = $notas[$mapa] ?? null;
            $tirinhas[] = $m + [
                'inicial' => $n['inicial'] ?? null,
                'final' => $n['final'] ?? null,
                'emitidas' => $n['emitidas'] ?? null,
                'boleto' => $pagamentos === null ? null : ($pagamentos['boleto'][$mapa] ?? 0),
                'cartao' => $comCondicao ? ($pagamentos['cartao'][$mapa] ?? 0) : null,
                'pix' => $comCondicao ? ($pagamentos['pix'][$mapa] ?? 0) : null,
            ];
        }

        return $tirinhas;
    }

    /**
     * Índices das colunas do 03.11.49.02 na linha de cabeçalho, ou null se não for o cabeçalho.
     */
    private static function colunasMapas(array $linha): ?array
    {
        $colunas = [];

        foreach ($linha as $i => $valor) {
            $nome = Importador::normalizar((string) $valor);
            $chave = match (true) {
                $nome === 'unb' => 'unb',
                $nome === 'nro do mapa' => 'mapa',
                $nome === 'data entrega' => 'data',
                $nome === 'placa' => 'placa',
                str_starts_with($nome, 'km prev') => 'km',
                str_starts_with($nome, 'tempo prev') => 'tempo',
                default => null,
            };
            if ($chave !== null && !isset($colunas[$chave])) {
                $colunas[$chave] = $i;
            }
        }

        // UNB é opcional aqui; a falta dela só é erro quando há revenda escolhida.
        return isset($colunas['mapa'], $colunas['placa'], $colunas['km'], $colunas['tempo']) ? $colunas : null;
    }

    /**
     * Número do mapa sem separador de milhar nem zeros à esquerda
     * ("397.986" e "0000397986" -> "397986"), ou null.
     */
    private static function mapa(string $valor): ?string
    {
        $valor = trim($valor);

        if (!preg_match('/^\d{1,3}(\.\d{3})+$|^\d+$/', $valor)) {
            return null;
        }

        $mapa = ltrim(self::digitos($valor), '0');

        return $mapa === '' ? null : $mapa;
    }

    private static function digitos(string $valor): string
    {
        return (string) preg_replace('/\D/', '', $valor);
    }

    /**
     * Tempo em "HH:MM" a partir de "3:15", "03:15:00" ou fração de dia do Excel ("0.1354").
     */
    private static function tempo(string $valor): string
    {
        if (trim($valor) === '') {
            return '';
        }

        if (preg_match('/^(\d{1,3}):(\d{2})/', trim($valor), $m)) {
            return sprintf('%02d:%02d', $m[1], $m[2]);
        }

        $minutos = (int) round(Importador::numero($valor) * 1440);

        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }
}
