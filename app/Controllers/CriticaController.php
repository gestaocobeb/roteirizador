<?php

namespace App\Controllers;

use App\Models\CriticaDecisaoModel;
use App\Models\PedidoCoraModel;

/**
 * Crítica GOD: indicadores por cliente com pedido aguardando roteirização
 * e decisão da Logística (caso a caso). O Comercial consulta a própria Mesa.
 */
class CriticaController extends BaseController
{
    public function index(): void
    {
        $data = $this->dataReferencia();
        $mesas = $this->mesas();
        $mesa = $this->mesaSelecionada($mesas, true);
        $revenda = $this->revendaSelecionada();

        $resumo = (new PedidoCoraModel())->resumoCritica($data, $revenda, $mesa);
        $linhas = array_map(fn ($linha) => $this->comAlertas($linha), $resumo);
        $setoresNaTela = array_values(array_unique(array_filter(array_map('intval', array_column($linhas, 'setor_codigo')))));
        sort($setoresNaTela);

        $this->view('critica/index', [
            'dataRef' => $data,
            'mesas' => $mesas,
            'mesa' => $mesa,
            'revenda' => $revenda,
            'linhas' => $linhas,
            'totais' => [
                'clientes' => count($linhas),
                'valor' => array_sum(array_column($linhas, 'valor_pedido')),
                'hl' => array_sum(array_column($linhas, 'volume_hl')),
                'decididos' => count(array_filter($linhas, fn ($l) => $l['decisao'] !== null)),
                'com_alerta' => count(array_filter($linhas, fn ($l) => $l['alertas'] !== [])),
            ],
            'setoresNaTela' => $setoresNaTela,
            'indicadores' => self::INDICADORES,
            'contagemIndicadores' => array_count_values(array_merge([], ...array_column($linhas, 'indicadores'))),
            'sinergia' => PedidoCoraModel::sinergiaPorCidade($resumo),
            'decisoes' => CriticaDecisaoModel::DECISOES,
            'podeDecidir' => $this->podeDecidirCritica(),
            'perfil' => $this->perfil(),
        ]);
    }

    /** Valor abaixo do qual o pedido é sinalizado (referência da planilha original). */
    private const VALOR_MINIMO_REFERENCIA = 100.0;

    /**
     * Indicadores da Crítica: código => [rótulo, cor do badge Bootstrap, é alerta?].
     * Top Line não é alerta: é exibido como destaque positivo.
     */
    private const INDICADORES = [
        'top_line' => ['Top Line', 'success', false],
        'black_list' => ['Black List', 'danger', true],
        'trava' => ['Trava', 'warning', true],
        'fora_rota' => ['Fora de rota', 'warning', true],
        'abaixo_100' => ['Abaixo de R$100', 'secondary', true],
        'sem_compra' => ['Sem compra no histórico', 'info', true],
    ];

    /**
     * Acrescenta à linha os códigos dos indicadores presentes e, separadamente,
     * apenas os que são alertas.
     */
    private function comAlertas(array $linha): array
    {
        $presentes = array_keys(array_filter([
            'top_line' => (int) $linha['top_line'] === 1,
            'black_list' => (int) $linha['black_list'] > 0,
            'trava' => $linha['trava_condicao'] !== null,
            'fora_rota' => trim((string) $linha['fora_rota']) !== '',
            'abaixo_100' => (float) $linha['valor_pedido'] < self::VALOR_MINIMO_REFERENCIA,
            'sem_compra' => (int) $linha['compras'] === 0,
        ]));

        $linha['indicadores'] = $presentes;
        $linha['alertas'] = array_values(array_filter($presentes, fn ($codigo) => self::INDICADORES[$codigo][2]));

        return $linha;
    }

    public function decidir(): void
    {
        $data = (string) $this->input('data', '');
        $revenda = (string) $this->input('revenda', '');
        $cliente = (int) $this->input('cliente', 0);
        $decisao = (string) $this->input('decisao', '');
        $observacao = mb_substr((string) $this->input('observacao', ''), 0, 255);
        $voltarPara = 'critica?data=' . urlencode($data);

        if (!$this->podeDecidirCritica()) {
            $this->responder(false, 'Seu perfil não pode registrar decisões.', $voltarPara);
        }

        if (!data_valida($data) || $cliente <= 0) {
            $this->responder(false, 'Dados inválidos.', $voltarPara);
        }

        if ($decisao !== '' && !array_key_exists($decisao, CriticaDecisaoModel::DECISOES)) {
            $this->responder(false, 'Decisão inválida.', $voltarPara);
        }

        // Logística restrita a uma revenda não decide pedidos de outra.
        if (!$this->podeAcessarRevenda($revenda)) {
            $this->responder(false, "Cliente {$cliente} não tem pedido para esta data.", $voltarPara);
        }

        $dados = (new PedidoCoraModel())->dadosCliente($data, $revenda, $cliente);

        if (!$dados) {
            $this->responder(false, "Cliente {$cliente} não tem pedido para esta data.", $voltarPara);
        }

        $model = new CriticaDecisaoModel();

        if ($decisao === '') {
            $model->remover($data, $revenda, $cliente);
            $this->responder(true, "Decisão do cliente {$cliente} removida.", $voltarPara);
        }

        $model->salvar($data, $revenda, $cliente, $decisao, $observacao !== '' ? $observacao : null, (int) $this->usuario()['id']);
        $this->responder(true, "Cliente {$cliente}: " . CriticaDecisaoModel::DECISOES[$decisao] . '.', $voltarPara);
    }
}
