<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Helpers\Farol;
use App\Helpers\Importador;
use App\Models\FarolBufferModel;
use App\Models\FarolMapaModel;
use App\Models\FarolRevendaModel;

/**
 * Aba Farol: painel do dia por revenda (substitui a PLANILHA FAROL).
 * Mapas (03.11.49.02) e pedidos do Cora (volumes vendido, roteirizado e buffer)
 * chegam pela importação; as observações são digitadas aqui.
 */
class FarolController extends BaseController
{
    public function index(): void
    {
        $mapas = new FarolMapaModel();
        $buffer = new FarolBufferModel();
        $digitados = new FarolRevendaModel();

        $farois = [];
        foreach ($this->revendasPermitidas() as $revenda) {
            $roteirizado = $buffer->volumeRoteirizado($revenda);
            $reprogramados = $roteirizado !== null ? $buffer->reprogramados($revenda) : [];

            $dados = $digitados->daRevenda($revenda);
            $farol = Farol::calcular($mapas->daRevenda($revenda), $reprogramados, $roteirizado);

            $farois[$revenda] = Farol::aplicarAjustes($farol, $dados) + [
                'digitados' => $dados,
                'buffer' => $reprogramados,
                'buffer_importado' => $roteirizado !== null,
            ];
        }

        $this->view('farol/index', [
            'farois' => $farois,
            'resumo' => Farol::resumo($farois),
            'tipos' => Farol::TIPOS,
            'tiposImportacao' => array_intersect_key(Importador::TIPOS, array_flip(['farol_mapas', 'farol_buffer'])),
            'podeImportarTodas' => count($this->revendasPermitidas()) > 1,
        ]);
    }

    /**
     * Baixa o buffer reprogramado da revenda em CSV para Excel (";" e UTF-8 com BOM),
     * igual à tela: uma linha por cliente, com o volume total, por setor, volume e cliente.
     */
    public function exportarBuffer(string $revenda): void
    {
        $revenda = strtoupper($revenda);

        if (!$this->podeAcessarRevenda($revenda)) {
            $this->voltar('farol', 'danger', 'Revenda não encontrada.');
        }

        $pedidos = (new FarolBufferModel())->reprogramados($revenda);

        // Texto iniciado por = + - @ seria interpretado como fórmula pelo Excel.
        $celula = fn ($valor) => preg_match('/^[=+\-@]/', (string) $valor) ? "'" . $valor : (string) $valor;
        $numero = fn ($valor) => number_format((float) $valor, 2, ',', '');

        // Uma linha por cliente: pedidos duplicados juntados e volume total do cliente.
        $linhas = [['Cliente', 'Nome do cliente', 'Cód. setor', 'Desc. setor', 'Pedidos', 'Volume total (HL)']];
        foreach ($pedidos as $p) {
            $linhas[] = [
                (string) $p['cliente_codigo'],
                $celula($p['nome_fantasia'] ?? ''),
                (string) ($p['setor_codigo'] ?? ''),
                $celula($p['setor_descricao'] ?? ''),
                $celula($p['numero_pedido']),
                $numero($p['volume_hl']),
            ];
        }
        $linhas[] = ['Total', '', '', '', '', $numero(array_sum(array_column($pedidos, 'volume_hl')))];

        $saida = fopen('php://temp', 'r+');
        fwrite($saida, "\xEF\xBB\xBF");
        foreach ($linhas as $linha) {
            fputcsv($saida, $linha, ';', '"', '');
        }
        rewind($saida);
        $csv = stream_get_contents($saida);
        fclose($saida);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="buffer-reprogramado-' . strtolower($revenda) . '-' . date('Y-m-d') . '.csv"');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
        exit;
    }

    /**
     * Grava as observações de Spot e recarga e o ajuste das quantidades de mapas
     * FF, Spot e recarga (campo vazio = quantidade do 03.11.49.02).
     */
    public function salvar(): void
    {
        $revenda = (string) $this->input('revenda', '');

        if (!$this->podeAcessarRevenda($revenda)) {
            $this->voltar('farol', 'danger', 'Selecione uma revenda válida.');
        }

        $texto = fn (string $campo) => mb_substr(trim((string) $this->input($campo, '')), 0, 150) ?: null;

        $ajustes = [];
        foreach (['ff' => 'mapas_ff', 'spot' => 'mapas_spot', 'recarga' => 'recargas'] as $chave => $campo) {
            $valor = trim((string) $this->input($campo, ''));
            if ($valor !== '' && (!ctype_digit($valor) || (int) $valor > 999)) {
                $this->voltar('farol', 'danger', 'Informe as quantidades de mapas como números inteiros (0 a 999) ou deixe em branco.');
            }
            $ajustes[$chave] = $valor === '' ? null : (int) $valor;
        }

        // Os ajustes valem para a entrega dos mapas importados agora.
        $dataEntrega = Farol::calcular((new FarolMapaModel())->daRevenda($revenda), [], null)['data_entrega'];

        (new FarolRevendaModel())->salvar(
            $revenda,
            $texto('observacao_spot'),
            $texto('observacao_recarga'),
            $ajustes,
            $dataEntrega,
            (int) $this->usuario()['id']
        );

        Logger::info("Farol {$revenda}: dados salvos por " . $this->usuario()['email']
            . ' (ajustes FF/Spot/Recarga: ' . implode('/', array_map(fn ($v) => $v ?? 'arquivo', $ajustes)) . ')');

        $this->voltar('farol', 'success', "Dados do Farol de {$revenda} salvos.");
    }
}
