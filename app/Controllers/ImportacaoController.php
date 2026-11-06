<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Helpers\Importador;
use App\Models\BlackListModel;
use App\Models\ClienteModel;
use App\Models\FarolBufferModel;
use App\Models\FarolMapaModel;
use App\Models\HistoricoCompraModel;
use App\Models\ImportacaoModel;
use App\Models\ImportavelModel;
use App\Models\IndicadorVendaModel;
use App\Models\PedidoCoraModel;
use App\Models\SetorModel;
use App\Models\TopLineModel;
use App\Models\TravaModel;
use App\Models\TrocaModel;
use App\Models\TrocaNotaModel;
use App\Models\VendaSetorModel;

/**
 * Importação dos exports do Cora/Promax (perfil Logística), por revenda.
 */
class ImportacaoController extends BaseController
{
    /** Tipos MIME aceitos por extensão (validados pelo conteúdo real do arquivo). */
    private const MIMES = [
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
    ];

    /** Valor do campo revenda para importar um arquivo com várias revendas (Cora, 03.18.05). */
    private const TODAS_REVENDAS = 'todas';

    /** Telas que podem enviar arquivos para esta rota (para onde o usuário volta). */
    private const RETORNOS = ['importacoes', 'troca', 'aprovacao-troca', 'farol'];

    public function index(): void
    {
        $this->view('importacoes/index', [
            'tipos' => Importador::TIPOS,
            'recentes' => (new ImportacaoModel())->recentes($this->revendaRestrita()),
            'dataPadrao' => date('Y-m-d', strtotime('+1 day')),
        ]);
    }

    public function store(): void
    {
        // Exports do Promax podem ter dezenas de milhares de linhas.
        set_time_limit(300);
        ini_set('memory_limit', '1024M');

        $tipo = (string) $this->input('tipo', '');
        $revenda = (string) $this->input('revenda', '');
        $data = (string) $this->input('data', '');
        $retorno = in_array($this->input('voltar'), self::RETORNOS, true) ? (string) $this->input('voltar') : 'importacoes';

        if (!array_key_exists($tipo, Importador::TIPOS)) {
            $this->voltar($retorno, 'danger', 'Selecione o tipo de importação.');
        }

        // "todas": o arquivo traz várias revendas e é separado pela coluna de revenda de cada linha.
        $todasRevendas = $revenda === self::TODAS_REVENDAS;

        if ($todasRevendas && !Importador::separaPorRevenda($tipo)) {
            $this->voltar($retorno, 'danger', 'A opção "Todas" vale só para o Cora, o 03.18.05 e os relatórios do Farol, que informam a revenda em cada linha. Para este relatório, escolha a revenda.');
        }

        if ($todasRevendas ? count($this->revendasPermitidas()) < 2 : !$this->podeAcessarRevenda($revenda)) {
            $this->voltar($retorno, 'danger', 'Selecione uma revenda válida.');
        }

        if ($tipo === 'cora' && !data_valida($data)) {
            $this->voltar($retorno, 'danger', 'Informe a data de entrega dos pedidos do Cora.');
        }

        $arquivo = $_FILES['arquivo'] ?? null;

        if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $erro = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;
            $mensagem = in_array($erro, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Arquivo maior que o limite do servidor (upload_max_filesize). Veja o Guia de Implantação.'
                : 'Selecione o arquivo a importar.';
            $this->voltar($retorno, 'danger', $mensagem);
        }

        $nomeOriginal = basename((string) $arquivo['name']);
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        // O Promax grava alguns relatórios como ".csv.inf" (ex.: 03.18.05); o conteúdo é CSV.
        if (str_ends_with(strtolower($nomeOriginal), '.csv.inf')) {
            $extensao = 'csv';
        }

        if (!isset(self::MIMES[$extensao])) {
            $this->voltar($retorno, 'danger', 'Formato não aceito. Envie o arquivo em .xlsx ou .csv.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);

        if (!in_array($mime, self::MIMES[$extensao], true)) {
            Logger::warning("Upload recusado: {$nomeOriginal} com MIME {$mime}");
            $this->voltar($retorno, 'danger', 'O conteúdo do arquivo não corresponde à extensão informada.');
        }

        // Processado fora do webroot e apagado ao final.
        $destino = dirname(__DIR__, 2) . '/storage/temp/importacao_' . bin2hex(random_bytes(8)) . '.' . $extensao;

        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            $this->voltar($retorno, 'danger', 'Não foi possível receber o arquivo. Verifique a permissão de escrita em storage/temp.');
        }

        $totais = [];

        try {
            $importador = new Importador();
            $setores = (new SetorModel())->codigos();
            $porRevenda = $todasRevendas
                ? $importador->lerTodasRevendas($tipo, $destino, $extensao, $setores)
                : [$revenda => $importador->ler($tipo, $destino, $extensao, $revenda, $setores)];

            // Revendas do arquivo que o usuário não acessa não são gravadas.
            $foraDoAcesso = array_diff(array_keys($porRevenda), $this->revendasPermitidas());
            $porRevenda = array_diff_key($porRevenda, array_flip($foraDoAcesso));
            $avisosLista = $importador->avisos();
            if ($foraDoAcesso) {
                $avisosLista[] = 'revenda(s) sem permissão não importada(s): ' . implode(', ', $foraDoAcesso);
            }
            $avisos = $avisosLista ? ' (' . implode('; ', $avisosLista) . ')' : '';

            if ($porRevenda === []) {
                throw new \RuntimeException('O arquivo não tem linhas das revendas que você pode importar.' . $avisos);
            }

            // Cada revenda é substituída e registrada separadamente (pode ser excluída depois por revenda).
            foreach ($porRevenda as $sigla => $registros) {
                $totais[$sigla] = $this->gravar($tipo, $sigla, $data, $registros);
                (new ImportacaoModel())->registrar($tipo, $sigla, $tipo === 'cora' ? $data : null, $nomeOriginal, $totais[$sigla], (int) $this->usuario()['id']);
            }

            $resumo = implode(', ', array_map(fn ($s, $t) => "{$s}: {$t}", array_keys($totais), $totais));
            Logger::info("Importação {$tipo} ({$resumo}) concluída: {$nomeOriginal}{$avisos}");

            $complemento = $tipo === 'cora' ? ' para entrega em ' . fmt_data($data) : '';
            $this->flash('success', Importador::TIPOS[$tipo] . " - registros importados {$resumo}{$complemento}{$avisos}.");
        } catch (\PDOException $e) {
            Logger::error("Falha ao gravar importação {$tipo} ({$revenda}): " . $e->getMessage());
            // Cada revenda é gravada em transação própria: as anteriores à falha ficam importadas.
            $gravadas = $totais ? ' Já importada(s): ' . implode(', ', array_keys($totais)) . '; as demais não foram alteradas.' : ' Nada foi alterado.';
            $this->flash('danger', 'Erro ao gravar os dados no banco.' . $gravadas . ' Detalhes no log do sistema.');
        } catch (\RuntimeException $e) {
            Logger::warning("Importação {$tipo} ({$revenda}) recusada ({$nomeOriginal}): " . $e->getMessage());
            $this->flash('danger', $e->getMessage());
        } finally {
            if (is_file($destino)) {
                unlink($destino);
            }
        }

        $this->redirect(base_url($retorno));
    }

    /**
     * Exclui a importação vigente: apaga os dados que ela trouxe (somente da
     * revenda dela) e marca o registro como excluído (mantido para auditoria).
     */
    public function excluir(string $id): void
    {
        $model = new ImportacaoModel();
        $importacao = $model->buscarComSituacao((int) $id);

        // Importação de outra revenda é tratada como inexistente.
        if (!$importacao || !$this->podeAcessarRevenda($importacao['revenda'])) {
            $this->voltar('importacoes', 'danger', 'Importação não encontrada.');
        }

        if ($importacao['situacao'] !== 'vigente') {
            $this->voltar('importacoes', 'warning', 'Só a importação vigente pode ser excluída. Esta já foi ' . ($importacao['situacao'] === 'excluida' ? 'excluída.' : 'substituída por uma importação mais recente.'));
        }

        $tipo = $importacao['tipo'];
        $revenda = $importacao['revenda'];
        $descricao = Importador::TIPOS[$tipo] ?? $tipo;

        try {
            $apagados = $this->limpar($tipo, $revenda, $importacao['data_referencia']);
            $model->marcarExcluida((int) $id, (int) $this->usuario()['id']);
        } catch (\PDOException $e) {
            Logger::error("Falha ao excluir importação {$id} ({$tipo} {$revenda}): " . $e->getMessage());
            $this->voltar('importacoes', 'danger', 'Erro ao excluir a importação. Detalhes no log do sistema.');
        }

        $complemento = $tipo === 'cora' ? ' de entrega em ' . fmt_data($importacao['data_referencia']) : '';
        Logger::info("Importação {$id} ({$tipo} {$revenda}{$complemento}, {$importacao['arquivo_nome']}) excluída por " . $this->usuario()['email'] . ": {$apagados} registros apagados");

        $this->voltar('importacoes', 'success', "{$descricao} - {$revenda}{$complemento} excluída: {$apagados} registros apagados.");
    }

    private function limpar(string $tipo, string $revenda, ?string $data): int
    {
        return $tipo === 'cora'
            ? (new PedidoCoraModel())->excluirData((string) $data, $revenda)
            : $this->modelCadastro($tipo)->limparRevenda($revenda);
    }

    private function gravar(string $tipo, string $revenda, string $data, array $registros): int
    {
        return $tipo === 'cora'
            ? (new PedidoCoraModel())->substituirData($data, $revenda, $registros)
            : $this->modelCadastro($tipo)->substituirRevenda($revenda, $registros);
    }

    private function modelCadastro(string $tipo): ImportavelModel
    {
        return match ($tipo) {
            'clientes' => new ClienteModel(),
            'notas' => new HistoricoCompraModel(),
            'black_list' => new BlackListModel(),
            'top_line' => new TopLineModel(),
            'travas' => new TravaModel(),
            'trocas' => new TrocaModel(),
            'notas_troca' => new TrocaNotaModel(),
            'vendas_setor' => new VendaSetorModel(),
            'indicadores' => new IndicadorVendaModel(),
            'farol_mapas' => new FarolMapaModel(),
            'farol_buffer' => new FarolBufferModel(),
        };
    }
}
