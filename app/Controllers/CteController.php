<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Helpers\Cte;
use App\Helpers\CteExcel;
use App\Helpers\Importador;
use App\Helpers\Revenda;
use App\Models\CteFreteModel;
use App\Models\CteMapaModel;

/**
 * Aba CTE: espelha a planilha "Ct'e PM", uma revenda por vez (PM, LP, AB).
 * Mapas do 03.11.49.02 (aqui ou pela Tirinha), valor do 03.03.12 colado,
 * frete pela tabela de custos da revenda; Ct'e, motorista e código digitados.
 */
class CteController extends BaseController
{
    /** Limite do texto colado do 03.03.12. */
    private const LIMITE_TEXTO = 500000;

    public function index(): void
    {
        $revenda = $this->revendaDaTela();
        $fretes = (new CteFreteModel())->daRevenda($revenda);
        $linhas = $this->linhas($revenda, $fretes);

        $this->view('cte/index', [
            'revenda' => $revenda,
            'revendas' => $this->revendasPermitidas(),
            'linhas' => $linhas,
            'fretes' => $fretes,
            'dataEntrega' => max(array_column($linhas, 'data_entrega') ?: [null]),
            // Cidades dos mapas sem frete cadastrado (para cadastrar na hora).
            'semFrete' => array_values(array_unique(array_filter(array_map(
                fn ($l) => $l['frete'] === null ? (string) $l['cidades'] : '',
                $linhas
            )))),
        ]);
    }

    /**
     * Recebe o 03.11.49.02 (opcional) e o 03.03.12 colado (opcional).
     */
    public function importar(): void
    {
        set_time_limit(300);

        $revenda = $this->revendaDoFormulario();
        $voltar = 'cte?revenda=' . $revenda;
        $arquivo = $this->arquivosEnviados('mapas', ['xlsx', 'xlsm', 'csv', 'inf', 'txt'], $voltar)[0] ?? null;
        $texto = substr((string) $this->input('valores', ''), 0, self::LIMITE_TEXTO);
        $textoMotoristas = substr((string) $this->input('motoristas', ''), 0, self::LIMITE_TEXTO);
        $model = new CteMapaModel();
        $mensagens = [];

        if (!$arquivo && trim($texto) === '' && trim($textoMotoristas) === '') {
            $this->voltar($voltar, 'warning', 'Envie o 03.11.49.02 e/ou cole o 03.03.12 ou os motoristas.');
        }

        if ($arquivo) {
            try {
                $ignorados = 0;
                $registros = Cte::lerMapas($arquivo['caminho'], $arquivo['extensao'], Revenda::REVENDAS[$revenda]['unb'], $ignorados);
            } catch (\RuntimeException $e) {
                $this->voltar($voltar, 'danger', $e->getMessage());
            } catch (\Throwable $e) {
                Logger::error('CTE: falha ao ler o 03.11.49.02: ' . $e->getMessage());
                $this->voltar($voltar, 'danger', 'Não foi possível ler o arquivo. Confira se é o 03.11.49.02.');
            }

            if (!$registros) {
                $this->voltar($voltar, 'danger', "Nenhum mapa de {$revenda} no 03.11.49.02 enviado. Confira a revenda escolhida.");
            }

            $total = $model->importar($revenda, $registros);
            $mensagens[] = "03.11.49.02: {$total} placa(s)" . ($ignorados ? " ({$ignorados} linha(s) de outras revendas ignorada(s))" : '') . '.';
        }

        if (trim($texto) !== '') {
            $valores = Cte::lerValores($texto);
            $gravados = $model->gravarValores($revenda, $valores);
            $mensagens[] = "03.03.12: valor de {$gravados} mapa(s) atualizado(s)" . (count($valores) > $gravados ? ' (' . (count($valores) - $gravados) . ' mapa(s) colado(s) não estão no CTE)' : '') . '.';
        }

        // Depois do 03.11.49.02: os mapas novos já existem para receber o motorista.
        if (trim($textoMotoristas) !== '') {
            $motoristas = Cte::lerMotoristas($textoMotoristas);
            $gravados = $model->gravarMotoristas($revenda, $motoristas);
            $mensagens[] = "Motoristas: {$gravados} mapa(s) preenchido(s)" . (count($motoristas) > $gravados ? ' (' . (count($motoristas) - $gravados) . ' mapa(s) colado(s) não estão no CTE)' : '') . '.';
        }

        Logger::info("CTE {$revenda} atualizado por " . $this->usuario()['email'] . ': ' . implode(' ', $mensagens));
        $this->voltar($voltar, 'success', implode(' ', $mensagens));
    }

    /**
     * Grava o Ct'e, o motorista e o código digitados em cada linha.
     */
    public function salvar(): void
    {
        $revenda = $this->revendaDoFormulario();
        $texto = fn ($valor, int $tamanho) => mb_substr(trim((string) $valor), 0, $tamanho) ?: null;
        $linhas = [];

        foreach ((array) $this->input('linhas', []) as $id => $l) {
            if (!ctype_digit((string) $id) || !is_array($l)) {
                continue;
            }
            $linhas[(int) $id] = [
                'cte_numero' => $texto($l['cte_numero'] ?? '', 30),
                'motorista_nome' => $texto(mb_strtoupper((string) ($l['motorista_nome'] ?? ''), 'UTF-8'), 100),
                'motorista_codigo' => $texto($l['motorista_codigo'] ?? '', 20),
            ];
        }

        (new CteMapaModel())->gravarDigitados($revenda, $linhas);
        Logger::info("CTE {$revenda}: dados digitados salvos por " . $this->usuario()['email']);

        if ($this->input('exportar')) {
            $this->exportar($revenda);
        }

        $this->voltar('cte?revenda=' . $revenda, 'success', "CTE de {$revenda} salvo.");
    }

    /**
     * Baixa a planilha do CT-e da revenda (.xlsx no layout da aba Cte).
     */
    public function exportar(string $revenda): void
    {
        $revenda = strtoupper($revenda);
        if (!$this->podeAcessarRevenda($revenda)) {
            $this->voltar('cte', 'danger', 'Revenda não encontrada.');
        }

        $linhas = $this->linhas($revenda, (new CteFreteModel())->daRevenda($revenda));
        $data = max(array_column($linhas, 'data_entrega') ?: [null]);
        // Mesmo formato da aba Cte da planilha "Ct'e PM".
        $conteudo = CteExcel::gerar($linhas, $data ?: date('Y-m-d', strtotime('+1 day')));
        $nome = 'cte-' . strtolower($revenda) . '-' . ($data ?: date('Y-m-d')) . '.xlsx';

        Logger::info("CTE {$revenda} exportado por " . $this->usuario()['email']);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        header('Content-Length: ' . strlen($conteudo));
        echo $conteudo;
        exit;
    }

    /**
     * Cadastra ou atualiza um frete da tabela de custos da revenda.
     */
    public function salvarFrete(): void
    {
        $revenda = $this->revendaDoFormulario();
        $voltar = 'cte?revenda=' . $revenda . '#fretes';
        $cidades = Cte::cidades((string) $this->input('cidades', ''));
        $valorTexto = trim((string) $this->input('valor', ''));
        $valor = Importador::numero($valorTexto);
        $codigo = mb_substr(trim((string) $this->input('codigo', '')), 0, 20);

        if ($cidades === '' || $valorTexto === '' || $valor <= 0 || $valor > 99999999 || $codigo === '') {
            $this->voltar($voltar, 'danger', 'Informe as cidades, o valor do frete e o código.');
        }

        (new CteFreteModel())->salvar($revenda, $cidades, $valor, $codigo);
        Logger::info("CTE {$revenda}: frete {$cidades} = {$valor} - {$codigo} salvo por " . $this->usuario()['email']);
        $this->voltar($voltar, 'success', "Frete de {$cidades} salvo.");
    }

    public function excluirFrete(string $id): void
    {
        $revenda = $this->revendaDoFormulario();

        if ((new CteFreteModel())->excluir($revenda, (int) $id)) {
            Logger::info("CTE {$revenda}: frete {$id} excluído por " . $this->usuario()['email']);
        }

        $this->voltar('cte?revenda=' . $revenda . '#fretes', 'success', 'Frete excluído.');
    }

    /**
     * Linhas do CTE com o frete da tabela (PROCV das cidades, como na planilha).
     */
    private function linhas(string $revenda, array $fretes): array
    {
        return array_map(
            fn ($l) => $l + ['frete' => $fretes[(string) $l['cidades']] ?? null],
            (new CteMapaModel())->daRevenda($revenda)
        );
    }

    /**
     * Revenda da tela: a escolhida (?revenda=) ou a primeira que o usuário acessa.
     */
    private function revendaDaTela(): string
    {
        $revenda = $this->revendaSelecionada() ?? ($this->revendasPermitidas()[0] ?? null);

        if (!$this->podeAcessarRevenda($revenda)) {
            $this->voltar('', 'danger', 'Nenhuma revenda disponível para o seu usuário.');
        }

        return $revenda;
    }

    private function revendaDoFormulario(): string
    {
        $revenda = (string) $this->input('revenda', '');

        if (!$this->podeAcessarRevenda($revenda)) {
            $this->voltar('cte', 'danger', 'Selecione uma revenda válida.');
        }

        return $revenda;
    }
}
