<?php

namespace App\Helpers;

/**
 * Monta o CSV de dias de entrega do BEES a partir do "Relatório Bees" e da rotina 0105070402 do Promax.
 *
 * Regras (na ordem):
 * 1. Exclui a coluna Settings_Type (coluna D).
 * 2. Esvazia a coluna Reference_Date (mantém só o título).
 * 3. Nos dias da semana (Mon a Sun), troca todo FREE por NO.
 * 4. Marca FREE no dia seguinte ao dia de visita do cliente (VDE e VDI do relatório):
 *    SEG → terça, TER → quarta, QUA → quinta, QUI → sexta, SEX → segunda.
 *    SAB e DOM não geram entrega.
 */
class BeesLoat
{
    /** Dia de visita do Promax => coluna de entrega do BEES. */
    public const ENTREGA_POR_VISITA = [
        'SEG' => 'Tue',
        'TER' => 'Wed',
        'QUA' => 'Thu',
        'QUI' => 'Fri',
        'SEX' => 'Mon',
    ];

    private const DIAS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /** Colunas da rotina 0105070402 (AY e BH) com os dias de visita. */
    private const COLUNAS_VISITA = ['Dia de Visita do VDE', 'Dia de Visita do VDI'];

    /** @var array<int, array<string, true>> código do cliente => colunas de entrega */
    private array $entregas = [];

    private int $clientesVde = 0;

    /**
     * Lê um arquivo da rotina 0105070402 e acumula os dias de entrega por cliente.
     */
    public function lerVde(string $caminho): void
    {
        [$cabecalho, $linhas] = $this->lerCsv($caminho);

        $colCodigo = $this->coluna($cabecalho, 'Cód PDV', 'da rotina 0105070402');
        $colVisitas = array_map(fn ($nome) => $this->coluna($cabecalho, $nome, 'da rotina 0105070402'), self::COLUNAS_VISITA);

        foreach ($linhas as $linha) {
            $codigo = (int) trim($linha[$colCodigo] ?? '');

            if ($codigo <= 0) {
                continue;
            }

            $dias = [];
            foreach ($colVisitas as $col) {
                // Formato do Promax: "SEG/", "QUA/SEX/", "TER/DOM/".
                foreach (explode('/', strtoupper((string) ($linha[$col] ?? ''))) as $visita) {
                    $entrega = self::ENTREGA_POR_VISITA[trim($visita)] ?? null;
                    if ($entrega) {
                        $dias[$entrega] = true;
                    }
                }
            }

            if ($dias) {
                $this->clientesVde++;
                // Vários arquivos da rotina 0105070402 somam os dias do mesmo cliente.
                $this->entregas[$codigo] = ($this->entregas[$codigo] ?? []) + $dias;
            }
        }
    }

    /**
     * Aplica as regras sobre o Relatório Bees e devolve o CSV final e um resumo.
     *
     * @return array{csv: string, linhas: int, marcados: int, semPasta: int}
     */
    public function gerar(string $caminhoPasta): array
    {
        [$cabecalho, $linhas] = $this->lerCsv($caminhoPasta);

        $colCodigo = $this->coluna($cabecalho, 'ERP_POC_ID', 'Relatório Bees');
        $colTipo = $this->coluna($cabecalho, 'Settings_Type', 'Relatório Bees');
        $colReferencia = $this->coluna($cabecalho, 'Reference_Date', 'Relatório Bees');
        $colDias = [];
        foreach (self::DIAS as $dia) {
            $colDias[$dia] = $this->coluna($cabecalho, $dia, 'Relatório Bees');
        }

        $marcados = 0;
        $encontrados = [];
        $saida = [$this->semColuna($cabecalho, $colTipo)];

        foreach ($linhas as $linha) {
            $linha = array_pad($linha, count($cabecalho), '');
            $linha[$colReferencia] = '';

            foreach ($colDias as $col) {
                if (strtoupper(trim($linha[$col])) === 'FREE') {
                    $linha[$col] = 'NO';
                }
            }

            $codigo = (int) trim($linha[$colCodigo]);
            if (isset($this->entregas[$codigo])) {
                foreach (array_keys($this->entregas[$codigo]) as $dia) {
                    $linha[$colDias[$dia]] = 'FREE';
                }
                $encontrados[$codigo] = true;
                $marcados++;
            }

            $saida[] = $this->semColuna($linha, $colTipo);
        }

        return [
            'csv' => implode("\n", array_map(fn ($campos) => implode(';', $campos), $saida)),
            'linhas' => count($linhas),
            'marcados' => $marcados,
            'semPasta' => count(array_diff_key($this->entregas, $encontrados)),
        ];
    }

    public function clientesVde(): int
    {
        return $this->clientesVde;
    }

    /**
     * Lê um CSV separado por ";" (UTF-8 ou Latin-1, como o Promax grava).
     *
     * @return array{0: string[], 1: array<int, string[]>}
     */
    private function lerCsv(string $caminho): array
    {
        $conteudo = (string) file_get_contents($caminho);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }

        $linhas = preg_split('/\r\n|\n|\r/', $conteudo);
        $linhas = array_values(array_filter($linhas, fn ($l) => trim($l) !== ''));

        if (count($linhas) < 2) {
            throw new \RuntimeException('Arquivo vazio ou sem linhas de dados.');
        }

        $dados = array_map(fn ($l) => str_getcsv($l, ';', '"', ''), $linhas);
        $cabecalho = array_map('trim', array_shift($dados));

        return [$cabecalho, $dados];
    }

    private function coluna(array $cabecalho, string $nome, string $arquivo): int
    {
        $indice = array_search($nome, $cabecalho, true);

        if ($indice === false) {
            throw new \RuntimeException("Coluna \"{$nome}\" não encontrada no arquivo {$arquivo}. Confira se enviou o relatório certo.");
        }

        return (int) $indice;
    }

    private function semColuna(array $campos, int $indice): array
    {
        unset($campos[$indice]);

        return array_values($campos);
    }
}
