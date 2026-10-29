<?php

namespace App\Models;

use App\Core\Model;

/**
 * Histórico de importações.
 *
 * Situação de cada registro:
 * - vigente: última importação do grupo (tipo + revenda; no Cora, também a
 *   data de entrega) e seus dados estão no sistema;
 * - substituida: uma importação posterior do mesmo grupo substituiu os dados;
 * - excluida: os dados foram apagados pela exclusão (o registro fica para auditoria).
 */
class ImportacaoModel extends Model
{
    protected string $table = 'importacoes';

    private const SQL_SITUACAO = "CASE
            WHEN i.excluida_em IS NOT NULL THEN 'excluida'
            WHEN i.id = (
                SELECT MAX(i2.id) FROM importacoes i2
                WHERE i2.tipo = i.tipo AND i2.revenda = i.revenda
                  AND (i.tipo <> 'cora' OR i2.data_referencia = i.data_referencia)
            ) THEN 'vigente'
            ELSE 'substituida'
        END AS situacao";

    private const SQL_SELECT = 'SELECT i.*, u.nome AS usuario_nome, ue.nome AS excluida_por_nome, ' . self::SQL_SITUACAO . '
             FROM importacoes i
             JOIN usuarios u ON u.id = i.usuarios_id
             LEFT JOIN usuarios ue ON ue.id = i.excluida_por_usuarios_id';

    public function registrar(string $tipo, string $revenda, ?string $dataReferencia, string $arquivo, int $linhas, int $usuarioId): string
    {
        return $this->create([
            'tipo' => $tipo,
            'revenda' => $revenda,
            'data_referencia' => $dataReferencia,
            'arquivo_nome' => mb_substr($arquivo, 0, 255),
            'total_linhas' => $linhas,
            'usuarios_id' => $usuarioId,
        ]);
    }

    public function recentes(?string $revenda = null, int $limite = 30): array
    {
        $stmt = $this->db->prepare(
            self::SQL_SELECT . ' WHERE :todas = 1 OR i.revenda = :revenda ORDER BY i.criado_em DESC, i.id DESC LIMIT :limite'
        );
        $stmt->bindValue(':todas', $revenda === null ? 1 : 0, \PDO::PARAM_INT);
        $stmt->bindValue(':revenda', (string) $revenda);
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function buscarComSituacao(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SQL_SELECT . ' WHERE i.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $resultado = $stmt->fetch();

        return $resultado ?: null;
    }

    /**
     * Exclusão lógica: mantém o registro no histórico com autor e data.
     */
    public function marcarExcluida(int $id, int $usuarioId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE importacoes SET excluida_em = NOW(), excluida_por_usuarios_id = :usuario WHERE id = :id AND excluida_em IS NULL'
        );

        return $stmt->execute(['id' => $id, 'usuario' => $usuarioId]);
    }

    /**
     * Última importação de cada tipo por revenda ([revenda][tipo] => registro), com a situação.
     */
    public function ultimasPorTipo(): array
    {
        $stmt = $this->db->query(
            self::SQL_SELECT . ' JOIN (SELECT tipo, revenda, MAX(id) AS id FROM importacoes GROUP BY tipo, revenda) ult ON ult.id = i.id'
        );

        $resultado = [];
        foreach ($stmt->fetchAll() as $linha) {
            $resultado[$linha['revenda']][$linha['tipo']] = $linha;
        }

        return $resultado;
    }

    /**
     * Importações do Cora vigentes para a data, por revenda (revenda => registro).
     * Revenda sem importação, ou com a importação excluída, não aparece.
     */
    public function corasVigentesDaData(string $data): array
    {
        $stmt = $this->db->prepare(
            self::SQL_SELECT . " WHERE i.tipo = 'cora' AND i.data_referencia = :data ORDER BY i.id"
        );
        $stmt->execute(['data' => $data]);

        $resultado = [];
        foreach ($stmt->fetchAll() as $linha) {
            if ($linha['situacao'] === 'vigente') {
                $resultado[$linha['revenda']] = $linha;
            }
        }
        ksort($resultado);

        return $resultado;
    }
}
