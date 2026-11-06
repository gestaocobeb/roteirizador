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
 * Mapas (03.11.49.02) e buffer (Cora) chegam pela importação; o volume vendido
 * e as observações são digitados aqui.
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
            $dados = $digitados->daRevenda($revenda);
            $reprogramados = $buffer->reprogramados($revenda);
            $vendido = isset($dados['volume_vendido_hl']) ? (float) $dados['volume_vendido_hl'] : null;

            $farois[$revenda] = Farol::calcular($mapas->daRevenda($revenda), $reprogramados, $vendido) + [
                'digitados' => $dados,
                'buffer' => $reprogramados,
                'buffer_importado' => $reprogramados !== [] || $buffer->importado($revenda),
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
     * na mesma ordem da tela: por cliente e, dentro do cliente, maior volume primeiro.
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

        $linhas = [['Pedido', 'Cliente', 'Nome fantasia', 'Cód. setor', 'Desc. setor', 'Tipo pedido', 'Volume (HL)']];
        foreach ($pedidos as $p) {
            $linhas[] = [
                $celula($p['numero_pedido']),
                (string) $p['cliente_codigo'],
                $celula($p['nome_fantasia'] ?? ''),
                (string) ($p['setor_codigo'] ?? ''),
                $celula($p['setor_descricao'] ?? ''),
                $celula($p['tipo_pedido'] ?? ''),
                $numero($p['volume_hl']),
            ];
        }
        $linhas[] = ['Total', '', '', '', '', '', $numero(array_sum(array_column($pedidos, 'volume_hl')))];

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
     * Grava o volume vendido e as observações de uma revenda.
     */
    public function salvar(): void
    {
        $revenda = (string) $this->input('revenda', '');

        if (!$this->podeAcessarRevenda($revenda)) {
            $this->voltar('farol', 'danger', 'Selecione uma revenda válida.');
        }

        $volumeTexto = trim((string) $this->input('volume_vendido', ''));
        $volume = $volumeTexto === '' ? null : Importador::numero($volumeTexto);

        if ($volume !== null && $volume < 0) {
            $this->voltar('farol', 'danger', 'O volume vendido não pode ser negativo.');
        }

        $texto = fn (string $campo) => mb_substr(trim((string) $this->input($campo, '')), 0, 150) ?: null;

        (new FarolRevendaModel())->salvar($revenda, $volume, $texto('observacao_spot'), $texto('observacao_recarga'), (int) $this->usuario()['id']);

        Logger::info("Farol {$revenda}: volume vendido " . ($volume ?? 'vazio') . ' salvo por ' . $this->usuario()['email']);

        $this->voltar('farol', 'success', "Dados do Farol de {$revenda} salvos.");
    }
}
