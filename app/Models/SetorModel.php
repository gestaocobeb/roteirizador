<?php

namespace App\Models;

use App\Core\Model;

/**
 * Setores de venda e seus limites de prioridade (clientes e HL) por Mesa.
 */
class SetorModel extends Model
{
    protected string $table = 'setores';

    /**
     * Setores, opcionalmente de uma revenda.
     */
    public function listar(?string $revenda = null): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM setores
             WHERE :todas = 1 OR revenda = :revenda
             ORDER BY revenda, mesa, codigo'
        );
        $stmt->execute(['todas' => $revenda === null ? 1 : 0, 'revenda' => (string) $revenda]);

        return $stmt->fetchAll();
    }

    public function buscarPorCodigo(int $codigo): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM setores WHERE codigo = :codigo LIMIT 1');
        $stmt->execute(['codigo' => $codigo]);
        $setor = $stmt->fetch();

        return $setor ?: null;
    }

    /**
     * Limites de um setor já cadastrado na Mesa (padrão para novos setores dela).
     */
    public function limitesDaMesa(int $mesa): ?array
    {
        $stmt = $this->db->prepare('SELECT limite_clientes, limite_hl FROM setores WHERE mesa = :mesa ORDER BY ativo DESC, codigo LIMIT 1');
        $stmt->execute(['mesa' => $mesa]);
        $limites = $stmt->fetch();

        return $limites ?: null;
    }

    /**
     * Aplica os mesmos limites a todos os setores da Mesa. Retorna os setores alterados.
     */
    public function atualizarLimitesDaMesa(int $mesa, int $limiteClientes, float $limiteHl): int
    {
        $stmt = $this->db->prepare('UPDATE setores SET limite_clientes = :clientes, limite_hl = :hl WHERE mesa = :mesa');
        $stmt->execute(['clientes' => $limiteClientes, 'hl' => $limiteHl, 'mesa' => $mesa]);

        return $stmt->rowCount();
    }

    public function codigos(): array
    {
        $stmt = $this->db->query('SELECT codigo FROM setores');
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
