<?php

namespace App\Models;

use App\Core\Model;

/**
 * Verba de troca extra por revenda + setor + mês (AAAA-MM), concedida pelo Administrador.
 * Não é apagada nas reimportações do 03.18.05 / 03.05.09.
 */
class VerbaExtraModel extends Model
{
    protected string $table = 'verbas_extras';

    /**
     * Verba extra por setor (setor => R$). Com $mes, só a do mês; sem, a soma de todos os meses.
     */
    public function porSetor(string $revenda, ?string $mes = null): array
    {
        $stmt = $this->db->prepare(
            'SELECT setor_codigo, SUM(valor) AS valor
             FROM verbas_extras
             WHERE revenda = :revenda AND (:todos = 1 OR mes = :mes)
             GROUP BY setor_codigo'
        );
        $stmt->execute(['revenda' => $revenda, 'todos' => $mes === null ? 1 : 0, 'mes' => (string) $mes]);

        return array_map('floatval', array_column($stmt->fetchAll(), 'valor', 'setor_codigo'));
    }

    /**
     * Lançamentos da revenda (do mês, ou todos), com o nome de quem lançou.
     */
    public function listar(string $revenda, ?string $mes = null): array
    {
        $stmt = $this->db->prepare(
            'SELECT v.id, v.setor_codigo, v.mes, v.valor, v.motivo, v.atualizado_em, u.nome AS usuario_nome
             FROM verbas_extras v
             JOIN usuarios u ON u.id = v.usuarios_id
             WHERE v.revenda = :revenda AND (:todos = 1 OR v.mes = :mes)
             ORDER BY v.mes DESC, v.setor_codigo'
        );
        $stmt->execute(['revenda' => $revenda, 'todos' => $mes === null ? 1 : 0, 'mes' => (string) $mes]);

        return $stmt->fetchAll();
    }

    /**
     * Grava ou substitui a verba extra do setor no mês.
     */
    public function salvar(string $revenda, int $setor, string $mes, float $valor, string $motivo, int $usuarioId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO verbas_extras (revenda, setor_codigo, mes, valor, motivo, usuarios_id)
             VALUES (:revenda, :setor, :mes, :valor, :motivo, :usuario)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), motivo = VALUES(motivo), usuarios_id = VALUES(usuarios_id)'
        );

        return $stmt->execute([
            'revenda' => $revenda,
            'setor' => $setor,
            'mes' => $mes,
            'valor' => $valor,
            'motivo' => $motivo,
            'usuario' => $usuarioId,
        ]);
    }
}
