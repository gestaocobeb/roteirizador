<?php

namespace App\Models;

use App\Core\Model;

/**
 * Transferências de saldo de prioridade (vagas de cliente e HL) entre setores
 * da mesma Mesa, válidas apenas para a data de entrega.
 */
class PrioridadeTransferenciaModel extends Model
{
    protected string $table = 'prioridades_transferencias';

    public function listar(string $data, int $mesa): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, u.nome AS usuario_nome
             FROM prioridades_transferencias t
             LEFT JOIN usuarios u ON u.id = t.usuarios_id
             WHERE t.data_referencia = :data AND t.mesa = :mesa
             ORDER BY t.criado_em, t.id'
        );
        $stmt->execute(['data' => $data, 'mesa' => $mesa]);

        return $stmt->fetchAll();
    }

    /**
     * Ajuste do limite de cada setor da Mesa na data (setor => ['clientes' => ±, 'hl' => ±]).
     */
    public function ajustesPorSetor(string $data, int $mesa): array
    {
        $ajustes = [];

        foreach ($this->listar($data, $mesa) as $t) {
            foreach ([[$t['setor_origem_codigo'], -1], [$t['setor_destino_codigo'], 1]] as [$setor, $sinal]) {
                $ajustes[(int) $setor] ??= ['clientes' => 0, 'hl' => 0.0];
                $ajustes[(int) $setor]['clientes'] += $sinal * (int) $t['clientes'];
                $ajustes[(int) $setor]['hl'] += $sinal * (float) $t['hl'];
            }
        }

        return $ajustes;
    }

    public function registrar(string $data, int $mesa, int $origem, int $destino, int $clientes, float $hl, int $usuarioId): string
    {
        return $this->create([
            'data_referencia' => $data,
            'mesa' => $mesa,
            'setor_origem_codigo' => $origem,
            'setor_destino_codigo' => $destino,
            'clientes' => $clientes,
            'hl' => $hl,
            'usuarios_id' => $usuarioId,
        ]);
    }
}
