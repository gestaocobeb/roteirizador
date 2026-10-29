<?php

namespace App\Helpers;

/**
 * Cálculo da Verba de troca (aba MATRIZ da planilha "Reposição Novo").
 *
 * Por setor:
 * - Verba = Volume HL vendido (03.05.09) x 10%;
 * - GAP = Verba - trocas Force aprovadas;
 * - pendência Force "cabe na verba" quando GAP - Force pendente >= 0;
 * - R$/HL = valor Force / Volume vendido da revenda x 10 (fórmula da planilha).
 * Volume vendido da revenda = 17.03 indicador 563 (saída) - 564 (devolução), quando
 * importado; senão, o total de HL do 03.05.09 (ver BaseController::verbaTroca()).
 */
class VerbaTroca
{
    public const PERCENTUAL_VERBA = 0.1;
    public const INDICADOR_SAIDA = 563;
    public const INDICADOR_DEVOLUCAO = 564;

    /**
     * @param int[] $setores        Setores exibidos, na ordem da tela
     * @param array $valores        [setor][status][sistema] => R$ (TrocaModel::valoresPorSetor)
     * @param array $volumes        setor => HL vendido (VendaSetorModel::volumesPorSetor)
     * @param float|null $volumeVendido HL vendido da revenda (null = sem 17.03 nem 03.05.09)
     */
    public static function calcular(array $setores, array $valores, array $volumes, ?float $volumeVendido): array
    {
        $linhas = [];
        $totais = array_fill_keys([
            'pendente_promax', 'pendente_force', 'pendente_customer',
            'aprovada_promax', 'aprovada_force', 'verba', 'gap', 'volume',
        ], 0.0);

        foreach ($setores as $setor) {
            $v = $valores[$setor] ?? [];
            $volume = $volumes[$setor] ?? 0.0;
            $verba = $volume * self::PERCENTUAL_VERBA;
            $aprovadaForce = $v['aprovada']['force'] ?? 0.0;
            $pendenteForce = $v['pendente']['force'] ?? 0.0;
            $gap = $verba - $aprovadaForce;

            $linha = [
                'setor' => $setor,
                'pendente_promax' => $v['pendente']['promax'] ?? 0.0,
                'pendente_force' => $pendenteForce,
                'pendente_customer' => $v['pendente']['customer'] ?? 0.0,
                'pendente_rs_hl' => self::porHl($pendenteForce, $volumeVendido, 10),
                'cabe_na_verba' => $gap - $pendenteForce >= 0,
                'aprovada_promax' => $v['aprovada']['promax'] ?? 0.0,
                'aprovada_force' => $aprovadaForce,
                'verba' => $verba,
                'gap' => $gap,
                'volume' => $volume,
                'aprovada_rs_hl' => self::porHl($aprovadaForce, $volumeVendido, 10),
            ];

            foreach ($totais as $campo => $soma) {
                $totais[$campo] = $soma + $linha[$campo];
            }

            $linhas[] = $linha;
        }

        return [
            'setores' => $linhas,
            'totais' => $totais,
            'volume_vendido' => $volumeVendido,
            'pendente_rs_hl' => self::porHl($totais['pendente_force'], $volumeVendido),
            'aprovada_rs_hl' => self::porHl($totais['aprovada_force'], $volumeVendido),
        ];
    }

    /**
     * Marca cada solicitação Force pendente como "cabe" ou "nao_cabe" na verba do setor.
     * Por setor, da mais antiga para a mais nova: cabe enquanto o valor couber no
     * saldo (GAP menos o que já coube); a que não cabe é pulada e as seguintes
     * ainda podem caber. Demais solicitações ficam com verba null (não usam verba).
     *
     * @param array $solicitacoes Lista ordenada por data (TrocaModel::solicitacoes)
     * @param array $gaps         setor => GAP (setores fora do cadastro não têm verba)
     */
    public static function classificarPendentes(array $solicitacoes, array $gaps): array
    {
        $saldos = $gaps;

        foreach ($solicitacoes as &$s) {
            $s['verba'] = null;
            $s['saldo_apos'] = null;
            $setor = $s['setor_codigo'] !== null ? (int) $s['setor_codigo'] : null;

            if (mb_strtolower((string) $s['status_solicitacao']) !== 'pendente' || mb_strtolower((string) $s['sistema_origem']) !== 'force') {
                continue;
            }

            if ($setor === null || !isset($saldos[$setor])) {
                $s['verba'] = 'nao_cabe';
                continue;
            }

            $valor = (float) $s['valor'];
            // Tolerância de centavo para arredondamentos.
            if ($valor <= $saldos[$setor] + 0.005) {
                $saldos[$setor] -= $valor;
                $s['verba'] = 'cabe';
            } else {
                $s['verba'] = 'nao_cabe';
            }
            $s['saldo_apos'] = $saldos[$setor];
        }
        unset($s);

        return $solicitacoes;
    }

    /**
     * Volume vendido da revenda (saída - devolução), ou null sem o indicador de saída.
     */
    public static function volumeVendido(?float $saida, ?float $devolucao): ?float
    {
        return $saida === null ? null : $saida - (float) $devolucao;
    }

    private static function porHl(float $valor, ?float $volume, int $fator = 1): ?float
    {
        return $volume !== null && $volume > 0 ? $valor / $volume * $fator : null;
    }
}
