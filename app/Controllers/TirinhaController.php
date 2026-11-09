<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Helpers\Revenda;
use App\Helpers\Tirinha;

/**
 * Aba Tirinha: monta as tirinhas de mapa para impressão (substitui a planilha
 * "TIRINHA PM"). Nada é gravado no banco: o resultado volta na própria tela.
 */
class TirinhaController extends BaseController
{
    /** Limite do texto colado do 03.02.31 (a tela do Promax tem poucos KB). */
    private const LIMITE_TEXTO = 500000;

    private const MIMES_TEXTO = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'];
    private const MIMES_XLSX = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel.sheet.macroEnabled.12', 'application/zip', 'application/octet-stream'];

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

        $arquivoMapas = $this->arquivos('mapas', ['xlsx', 'xlsm', 'csv', 'inf', 'txt'])[0] ?? null;
        if (!$arquivoMapas) {
            $this->voltar('tirinha', 'danger', 'Envie o 03.11.49.02 (mapas com placa, KM e tempo previsto).');
        }
        $arquivos037 = $this->arquivos('pagamentos', ['csv', 'inf', 'txt']);

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

        $this->view('tirinha/index', array_merge($this->dadosTela($tirinhas, $textoNotas, $avisos, $revenda), [
            'cartao' => $pagamentos && $pagamentos['temCondicao'] ? $this->notasNoCartao($tirinhas, $pagamentos['cartaoNotas']) : null,
            'dataEntrega' => max(array_column($mapas, 'data_entrega') ?: [null]),
        ]));
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

    /**
     * Uploads válidos do campo (simples ou múltiplo), validando extensão e MIME real.
     *
     * @return array<int, array{caminho: string, extensao: string}>
     */
    private function arquivos(string $campo, array $extensoes): array
    {
        $arquivos = $_FILES[$campo] ?? null;
        if (!$arquivos) {
            return [];
        }

        $nomes = (array) $arquivos['name'];
        $temporarios = (array) $arquivos['tmp_name'];
        $erros = (array) $arquivos['error'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $validos = [];

        foreach ($nomes as $i => $nome) {
            if (($erros[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $nome = basename((string) $nome);

            if ($erros[$i] !== UPLOAD_ERR_OK || !is_uploaded_file($temporarios[$i])) {
                $this->voltar('tirinha', 'danger', "Não foi possível receber o arquivo {$nome} (verifique o limite upload_max_filesize).");
            }

            $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            $mime = $finfo->file($temporarios[$i]);
            $planilha = in_array($extensao, ['xlsx', 'xlsm'], true);
            $mimes = $planilha ? self::MIMES_XLSX : self::MIMES_TEXTO;

            if (!in_array($extensao, $extensoes, true) || !in_array($mime, $mimes, true)) {
                Logger::warning("Tirinha: upload recusado {$nome} com MIME {$mime}");
                $this->voltar('tirinha', 'danger', "O arquivo {$nome} não está num formato aceito (" . implode(', ', $extensoes) . ').');
            }

            // O leitor trata .inf/.txt como CSV e .xlsm como .xlsx.
            $validos[] = ['caminho' => $temporarios[$i], 'extensao' => $planilha ? 'xlsx' : 'csv'];
        }

        return $validos;
    }
}
