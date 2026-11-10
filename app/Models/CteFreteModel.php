<?php

namespace App\Models;

use App\Core\Model;

/**
 * Tabela de custos de frete do CTE por revenda: cidades do mapa => valor e código.
 */
class CteFreteModel extends Model
{
    protected string $table = 'cte_fretes';

    /**
     * Fretes da revenda indexados pelas cidades.
     */
    public function daRevenda(string $revenda): array
    {
        $stmt = $this->db->prepare('SELECT id, cidades, valor, codigo FROM cte_fretes WHERE revenda = :revenda ORDER BY cidades');
        $stmt->execute(['revenda' => $revenda]);

        $fretes = [];
        foreach ($stmt->fetchAll() as $f) {
            $fretes[$f['cidades']] = $f;
        }

        return $fretes;
    }

    /**
     * Cadastra ou atualiza o frete das cidades na revenda.
     */
    public function salvar(string $revenda, string $cidades, float $valor, string $codigo): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cte_fretes (revenda, cidades, valor, codigo) VALUES (:revenda, :cidades, :valor, :codigo)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), codigo = VALUES(codigo)'
        );
        $stmt->execute(['revenda' => $revenda, 'cidades' => $cidades, 'valor' => $valor, 'codigo' => $codigo]);
    }

    public function excluir(string $revenda, int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM cte_fretes WHERE id = :id AND revenda = :revenda');
        $stmt->execute(['id' => $id, 'revenda' => $revenda]);

        return $stmt->rowCount() > 0;
    }
}
