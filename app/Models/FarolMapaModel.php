<?php

namespace App\Models;

/**
 * Mapas roteirizados do Farol (Promax 03.11.49.02).
 */
class FarolMapaModel extends ImportavelModel
{
    protected string $table = 'farol_mapas';

    protected array $colunasImportacao = ['data_entrega', 'numero_mapa', 'veiculo', 'placa', 'carga', 'entregas', 'caixas', 'peso'];

    public function daRevenda(string $revenda): array
    {
        $stmt = $this->db->prepare(
            'SELECT data_entrega, numero_mapa, veiculo, placa, carga, entregas, caixas, peso
             FROM farol_mapas WHERE revenda = :revenda ORDER BY id'
        );
        $stmt->execute(['revenda' => $revenda]);

        return $stmt->fetchAll();
    }
}
