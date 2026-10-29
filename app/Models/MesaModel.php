<?php

namespace App\Models;

use App\Core\Model;

/**
 * Mesas (código do gerente de vendas) e as revendas que cada uma atende.
 * Um registro por Mesa + revenda: a mesma Mesa pode atender mais de uma revenda.
 */
class MesaModel extends Model
{
    protected string $table = 'mesas';

    public function listar(): array
    {
        return $this->db->query('SELECT * FROM mesas ORDER BY codigo, revenda')->fetchAll();
    }

    /**
     * Códigos das Mesas, opcionalmente das que atendem uma revenda.
     */
    public function codigos(?string $revenda = null): array
    {
        $sql = 'SELECT DISTINCT codigo FROM mesas';
        $parametros = [];

        if ($revenda !== null) {
            $sql .= ' WHERE revenda = :revenda';
            $parametros['revenda'] = $revenda;
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY codigo');
        $stmt->execute($parametros);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Revendas atendidas pela Mesa (vazio = Mesa não cadastrada).
     */
    public function revendasDa(int $mesa): array
    {
        $stmt = $this->db->prepare('SELECT revenda FROM mesas WHERE codigo = :codigo ORDER BY revenda');
        $stmt->execute(['codigo' => $mesa]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function atende(int $mesa, string $revenda): bool
    {
        return in_array($revenda, $this->revendasDa($mesa), true);
    }
}
