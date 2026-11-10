<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Session;
use App\Helpers\Cte;
use App\Helpers\Revenda;
use App\Helpers\Tirinha;
use App\Helpers\TirinhaExcel;
use App\Models\CteMapaModel;

/**
 * Aba Tirinha: monta as tirinhas de mapa para impressão (substitui a planilha
 * "TIRINHA PM"). Nada é gravado no banco: o resultado volta na própria tela.
 */
class TirinhaController extends BaseController
{
    /** Limite do texto colado do 03.02.31 (a tela do Promax tem poucos KB). */
    private const LIMITE_TEXTO = 500000;

    public function index(): void
    {
        $this->view('tirinha/index', $this->dadosTela(null, '', [], $this->revendaSelecionada()));
    }

    public function gerar(): void
    {
        set_time_limit(300);
        ini_set('memory_limit', '1024M');

        $revenda = (string) $this->input('revenda', '');
        if (!$this->podeAcessarRevenda($revenda)) {
            $this->voltar('tirinha', 'danger', 'Selecione a revenda (PM, LP ou AB).');
        }

        $textoNotas = substr((string) $this->input('notas', ''), 0, self::LIMITE_TEXTO);

        $arquivoMapas = $this->arquivosEnviados('mapas', ['xlsx', 'xlsm', 'csv', 'inf', 'txt'], 'tirinha')[0] ?? null;
        if (!$arquivoMapas) {
            $this->voltar('tirinha', 'danger', 'Envie o 03.11.49.02 (mapas com placa, KM e tempo previsto).');
        }
        $arquivos037 = $this->arquivosEnviados('pagamentos', ['csv', 'inf', 'txt'], 'tirinha');

        try {
            $ignorados = 0;
            $mapas = Tirinha::lerMapas($arquivoMapas['caminho'], $arquivoMapas['extensao'], Revenda::REVENDAS[$revenda]['unb'], $ignorados);
            $pagamentos = $arquivos037 ? Tirinha::contarPagamentos(array_column($arquivos037, 'caminho')) : null;
        } catch (\RuntimeException $e) {
            $this->voltar('tirinha', 'danger', $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error('Tirinha: falha ao ler os arquivos: ' . $e->getMessage());
            $this->voltar('tirinha', 'danger', 'Não foi possível ler os arquivos. Confira se são os relatórios corretos.');
        }

        if (!$mapas) {
            $this->voltar('tirinha', 'danger', "Nenhum mapa de {$revenda} no 03.11.49.02 enviado. Confira a revenda escolhida.");
        }

        $notas = Tirinha::lerNotas($textoNotas);

        $avisos = [];

        // O mesmo 03.11.49.02 alimenta a aba CTE da revenda (uma linha por placa).
        try {
            $cte = (new CteMapaModel())->importar($revenda, Cte::lerMapas($arquivoMapas['caminho'], $arquivoMapas['extensao'], Revenda::REVENDAS[$revenda]['unb']));
            $avisos[] = "03.11.49.02 enviado também para a aba CTE de {$revenda} ({$cte} placa(s)).";
        } catch (\Throwable $e) {
            Logger::warning('Tirinha: 03.11.49.02 não gravado no CTE: ' . $e->getMessage());
            $avisos[] = 'Não foi possível enviar este 03.11.49.02 para a aba CTE: ' . ($e instanceof \RuntimeException ? $e->getMessage() : 'erro ao gravar.');
        }
        if ($ignorados > 0) {
            $avisos[] = "{$ignorados} mapa(s) de outras revendas no 03.11.49.02 foram ignorados (só {$revenda}).";
        }
        $semNotas = array_keys(array_diff_key($mapas, $notas));
        if (!$notas) {
            $avisos[] = '03.02.31 não colado: as colunas Inicial, Final e Emitidas ficaram em branco.';
        } elseif ($semNotas) {
            $avisos[] = 'Mapas sem linha no 03.02.31: ' . implode(', ', $semNotas) . '.';
        }
        if ($pagamentos === null) {
            $avisos[] = '03.02.37 não enviado: as colunas Boleto, Cartão de crédito e Pix ficaram em branco.';
        } elseif (!$pagamentos['temCondicao']) {
            $avisos[] = 'Nenhum 03.02.37 enviado tem a coluna "Cond. pagt": as colunas Cartão de crédito e Pix ficaram em branco.';
        }

        Logger::info(sprintf('Tirinha %s gerada por %s: %d mapas.', $revenda, $this->usuario()['email'] ?? '?', count($mapas)));

        $tirinhas = Tirinha::montar($mapas, $notas, $pagamentos);
        $dataEntrega = max(array_column($mapas, 'data_entrega') ?: [null]);

        // Últimas tirinhas geradas, para o botão "Baixar Excel" (nada vai para o banco).
        Session::set('tirinha_excel', ['revenda' => $revenda, 'data' => $dataEntrega, 'tirinhas' => $tirinhas]);

        $this->view('tirinha/index', array_merge($this->dadosTela($tirinhas, $textoNotas, $avisos, $revenda), [
            'cartao' => $pagamentos && $pagamentos['temCondicao'] ? $this->notasNoCartao($tirinhas, $pagamentos['cartaoNotas']) : null,
            'dataEntrega' => $dataEntrega,
        ]));
    }

    /**
     * Baixa as últimas tirinhas geradas em .xlsx, no formato da aba Plan1 da planilha.
     */
    public function exportar(): void
    {
        $ultima = Session::get('tirinha_excel');

        if (!is_array($ultima) || empty($ultima['tirinhas']) || !$this->podeAcessarRevenda($ultima['revenda'] ?? null)) {
            $this->voltar('tirinha', 'warning', 'Gere as tirinhas antes de baixar o Excel.');
        }

        $conteudo = TirinhaExcel::gerar($ultima['tirinhas']);
        $nome = 'tirinha-' . strtolower($ultima['revenda']) . '-' . ($ultima['data'] ?: date('Y-m-d')) . '.xlsx';

        Logger::info("Tirinha {$ultima['revenda']} baixada em Excel por " . ($this->usuario()['email'] ?? '?'));

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        header('Content-Length: ' . strlen($conteudo));
        echo $conteudo;
        exit;
    }

    /**
     * Notas no cartão de crédito, na ordem das tirinhas, com a placa do mapa
     * (dados da imagem para o WhatsApp).
     */
    private function notasNoCartao(array $tirinhas, array $cartaoNotas): array
    {
        $linhas = [];

        foreach ($tirinhas as $t) {
            foreach ($cartaoNotas[$t['mapa']] ?? [] as $n) {
                $linhas[] = $n + ['mapa' => $t['mapa'], 'placa' => $t['placa']];
            }
        }

        return $linhas;
    }

    private function dadosTela(?array $tirinhas, string $notas, array $avisos, ?string $revenda): array
    {
        $revendas = $this->revendasPermitidas();

        return [
            'tirinhas' => $tirinhas,
            'notas' => $notas,
            'avisos' => $avisos,
            'revendas' => $revendas,
            // Com uma revenda só, ela já vem marcada.
            'revenda' => count($revendas) === 1 ? $revendas[0] : $revenda,
            'cartao' => null,
            'dataEntrega' => null,
        ];
    }
}
