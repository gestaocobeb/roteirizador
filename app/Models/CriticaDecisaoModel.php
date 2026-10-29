<?php

namespace App\Models;

use App\Core\Model;

/**
 * Decisão do Comercial sobre cada cliente da Crítica GOD (por data de entrega).
 */
class CriticaDecisaoModel extends Model
{
    protected string $table = 'criticas_decisoes';

    public const DECISOES = [
        'libera' => 'Libera',
        'corta' => 'Corta',
        'comprador_pode_liberar' => 'Comprador pode liberar',
        'abaixo_100_corta' => 'Abaixo de R$100 corta tudo',
    ];

    public function salvar(string $data, string $revenda, int $cliente, string $decisao, ?string $observacao, int $usuarioId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO criticas_decisoes (data_referencia, revenda, cliente_codigo, decisao, observacao, usuarios_id)
             VALUES (:data, :revenda, :cliente, :decisao, :observacao, :usuario)
             ON DUPLICATE KEY UPDATE decisao = VALUES(decisao), observacao = VALUES(observacao), usuarios_id = VALUES(usuarios_id)'
        );

        return $stmt->execute([
            'data' => $data,
            'revenda' => $revenda,
            'cliente' => $cliente,
            'decisao' => $decisao,
            'observacao' => $observacao,
            'usuario' => $usuarioId,
        ]);
    }

    public function remover(string $data, string $revenda, int $cliente): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM criticas_decisoes WHERE data_referencia = :data AND revenda = :revenda AND cliente_codigo = :cliente'
        );
        return $stmt->execute(['data' => $data, 'revenda' => $revenda, 'cliente' => $cliente]);
    }
}
