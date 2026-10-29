<?php

namespace App\Models;

/**
 * Volume vendido (HL) por setor, do Promax 03.05.09.
 */
class VendaSetorModel extends ImportavelModel
{
    protected string $table = 'vendas_setores';

    protected array $colunasImportacao = ['setor_codigo', 'volume_hl'];

    /**
     * Volume da revenda por setor (setor => HL).
     */
    public function volumesPorSetor(string $revenda): array
    {
        $stmt = $this->db->prepare('SELECT setor_codigo, volume_hl FROM vendas_setores WHERE revenda = :revenda');
        $stmt->execute(['revenda' => $revenda]);

        return array_map('floatval', $stmt->fetchAll(\PDO::FETCH_KEY_PAIR));
    }
}
