<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Helpers\BeesLoat;

/**
 * Aba Bees Loat: gera o CSV de dias de entrega do BEES a partir do Relatório Bees
 * e da rotina 0105070402 do Promax. Nada é gravado no banco; os arquivos são lidos
 * do temporário do upload e o CSV volta direto para download.
 */
class BeesLoatController extends BaseController
{
    /** Extensões aceitas (o Promax grava o VDE como .inf) e MIME real esperado. */
    private const EXTENSOES = ['csv', 'inf', 'txt'];
    private const MIMES = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'];

    public function index(): void
    {
        $this->view('bees-loat/index', [
            'entregas' => BeesLoat::ENTREGA_POR_VISITA,
        ]);
    }

    public function gerar(): void
    {
        set_time_limit(300);
        ini_set('memory_limit', '1024M');

        $pasta = $this->arquivosValidos('pasta')[0] ?? null;
        $vdes = $this->arquivosValidos('vde');

        if (!$pasta) {
            $this->voltar('bees-loat', 'danger', 'Envie o Relatório Bees (.csv).');
        }

        if (!$vdes) {
            $this->voltar('bees-loat', 'danger', 'Envie ao menos um arquivo da rotina 0105070402.');
        }

        try {
            $bees = new BeesLoat();
            foreach ($vdes as $vde) {
                $bees->lerVde($vde);
            }
            $resultado = $bees->gerar($pasta);
        } catch (\RuntimeException $e) {
            $this->voltar('bees-loat', 'danger', $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error('Bees Loat: falha ao gerar CSV: ' . $e->getMessage());
            $this->voltar('bees-loat', 'danger', 'Não foi possível processar os arquivos. Confira se são os relatórios corretos.');
        }

        Logger::info(sprintf(
            'Bees Loat gerado por %s: %d linhas, %d clientes com FREE, %d clientes da rotina 0105070402 fora do Relatório Bees.',
            $this->usuario()['email'] ?? '?',
            $resultado['linhas'],
            $resultado['marcados'],
            $resultado['semPasta']
        ));

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="Relatorio Bees ' . date('Y-m-d') . '.csv"');
        header('Content-Length: ' . strlen($resultado['csv']));
        echo $resultado['csv'];
        exit;
    }

    /**
     * Caminhos temporários dos uploads válidos do campo (aceita campo simples ou múltiplo).
     *
     * @return string[]
     */
    private function arquivosValidos(string $campo): array
    {
        $arquivos = $_FILES[$campo] ?? null;

        if (!$arquivos) {
            return [];
        }

        $nomes = (array) $arquivos['name'];
        $temporarios = (array) $arquivos['tmp_name'];
        $erros = (array) $arquivos['error'];
        $validos = [];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($nomes as $i => $nome) {
            if (($erros[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $nome = basename((string) $nome);

            if ($erros[$i] !== UPLOAD_ERR_OK || !is_uploaded_file($temporarios[$i])) {
                $this->voltar('bees-loat', 'danger', "Não foi possível receber o arquivo {$nome} (verifique o limite upload_max_filesize).");
            }

            $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            $mime = $finfo->file($temporarios[$i]);

            if (!in_array($extensao, self::EXTENSOES, true) || !in_array($mime, self::MIMES, true)) {
                Logger::warning("Bees Loat: upload recusado {$nome} com MIME {$mime}");
                $this->voltar('bees-loat', 'danger', "O arquivo {$nome} não é um CSV/relatório de texto válido.");
            }

            $validos[] = $temporarios[$i];
        }

        return $validos;
    }
}
