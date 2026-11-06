<?php

namespace App\Helpers;

/**
 * Revendas da COBEB e seus códigos nos relatórios de origem:
 * "Cód. unidade venda" no Cora e "UNB" no Promax (03.18.05).
 * A revenda de cada Mesa fica na tabela "mesas".
 */
class Revenda
{
    public const REVENDAS = [
        'PM' => ['unidade_venda' => 772003, 'unb' => 1, 'nome' => 'Pará de Minas'],
        'LP' => ['unidade_venda' => 1883003, 'unb' => 2, 'nome' => 'Lagoa da Prata'],
        'AB' => ['unidade_venda' => 984507, 'unb' => 5, 'nome' => 'Abaeté'],
    ];

    public static function nome(string $sigla): string
    {
        return self::REVENDAS[$sigla]['nome'] ?? $sigla;
    }

    public static function siglas(): array
    {
        return array_keys(self::REVENDAS);
    }

    public static function valida(?string $sigla): bool
    {
        return $sigla !== null && isset(self::REVENDAS[$sigla]);
    }

    /**
     * Revenda pelo código do relatório (campo "unidade_venda" ou "unb"), ou null.
     */
    public static function porCodigo(string $campo, ?int $codigo): ?string
    {
        foreach (self::REVENDAS as $sigla => $codigos) {
            if ($codigo !== null && $codigos[$campo] === $codigo) {
                return $sigla;
            }
        }

        return null;
    }
}
