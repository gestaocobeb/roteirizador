<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Helpers\VerbaTroca;
use App\Models\ImportacaoModel;
use App\Models\SetorModel;
use App\Models\TrocaJustificativaModel;
use App\Models\TrocaModel;
use App\Models\VerbaExtraModel;

/**
 * Aba Aprovação troca (Logística e Administrador): Verba de troca por setor,
 * motorista e ajudante, espelhando a aba MATRIZ da planilha "Reposição Novo".
 * Fontes: 03.18.05 (trocas) e 03.05.09 (HL por setor; o total da revenda é o
 * volume vendido do R$/HL quando não há 17.03 importado).
 */
class AprovacaoTrocaController extends BaseController
{
    /** Relatórios usados na tela (tipo de importação => rótulo curto). */
    private const RELATORIOS = [
        'trocas' => '03.18.05',
        'vendas_setor' => '03.05.09',
    ];

    public function index(): void
    {
        // A matriz é sempre de uma revenda: a escolhida no menu ou a primeira permitida.
        $revendas = $this->revendasPermitidas();
        $revenda = $this->revendaSelecionada() ?? ($revendas[0] ?? '');
        $matriz = null;
        $foraDoCadastro = [];
        $motoristas = [];
        $ajudantes = [];
        $solicitacoes = [];
        $meses = [];
        $mes = null;
        $filtros = $this->filtros();

        if ($revenda !== '') {
            $trocas = new TrocaModel();

            // Mês da Data Solicitação (vale para a tela toda); só meses com trocas da revenda.
            $meses = $trocas->meses($revenda);
            $mes = in_array($m = (string) $this->input('mes', ''), $meses, true) ? $m : null;

            ['matriz' => $matriz, 'foraDoCadastro' => $foraDoCadastro] = $this->verbaTroca($revenda, null, $mes);
            $setores = array_column($matriz['setores'], 'setor');
            $motoristas = $trocas->aprovadasPorMotorista($revenda, 'Promax', $mes);
            $ajudantes = $trocas->aprovadasPorAjudante($revenda, 'Promax', $mes);

            // "Cabe na verba" calculado sobre todas as pendências do mês; os demais filtros só limitam a exibição.
            $gaps = array_column($matriz['setores'], 'gap', 'setor');
            $solicitacoes = $this->filtrarSolicitacoes(
                VerbaTroca::classificarPendentes($trocas->solicitacoes($revenda, $mes), $gaps),
                $filtros,
                $setores
            );
        }

        $ehAdmin = $this->perfil() === 'admin';
        $verbasExtras = $ehAdmin && $revenda !== '' ? (new VerbaExtraModel())->listar($revenda, $mes) : [];

        $ultimas = (new ImportacaoModel())->ultimasPorTipo()[$revenda] ?? [];
        $importacoes = [];
        foreach (self::RELATORIOS as $tipo => $rotulo) {
            $ultima = $ultimas[$tipo] ?? null;
            $importacoes[$tipo] = $ultima && $ultima['situacao'] !== 'excluida' ? $ultima : null;
        }

        $this->view('aprovacao-troca/index', [
            'revenda' => $revenda,
            'revendas' => $revendas,
            'matriz' => $matriz,
            'foraDoCadastro' => $foraDoCadastro,
            'motoristas' => $motoristas,
            'ajudantes' => $ajudantes,
            'solicitacoes' => $solicitacoes,
            'meses' => $meses,
            'mes' => $mes,
            'ehAdmin' => $ehAdmin,
            'verbasExtras' => $verbasExtras,
            // Mês sugerido no lançamento de verba extra: o filtrado, o mais recente com trocas ou o atual.
            'mesVerbaExtra' => $mes ?? ($meses[0] ?? date('Y-m')),
            'filtros' => $filtros,
            'relatorios' => self::RELATORIOS,
            'importacoes' => $importacoes,
            'podeImportar' => isset($this->abasMenu()['importacoes']),
        ]);
    }

    /**
     * Grava (ou apaga, se vier vazia) a justificativa da Logística para uma solicitação.
     * Ela aparece para o Comercial em Troca > Solicitações por cliente.
     */
    public function justificar(): void
    {
        $revenda = (string) $this->input('revenda', '');
        $solicitacao = mb_substr(trim((string) $this->input('solicitacao', '')), 0, 30);
        $cliente = (int) $this->input('cliente', 0);
        $justificativa = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('justificativa', ''))), 0, 500);
        $voltar = $this->voltarParaSolicitacoes();

        if (!$this->podeAcessarRevenda($revenda) || $solicitacao === '' || $cliente <= 0
            || !(new TrocaModel())->existeSolicitacao($revenda, $solicitacao, $cliente)) {
            $this->voltar($voltar, 'danger', 'Solicitação não encontrada.');
        }

        $model = new TrocaJustificativaModel();

        if ($justificativa === '') {
            $model->remover($revenda, $solicitacao, $cliente);
            Logger::info("Justificativa da solicitação {$solicitacao} ({$revenda}, cliente {$cliente}) removida por " . $this->usuario()['email']);
            $this->voltar($voltar, 'success', "Justificativa da solicitação {$solicitacao} removida.");
        }

        $model->salvar($revenda, $solicitacao, $cliente, $justificativa, (int) $this->usuario()['id']);
        Logger::info("Justificativa da solicitação {$solicitacao} ({$revenda}, cliente {$cliente}) gravada por " . $this->usuario()['email']);
        $this->voltar($voltar, 'success', "Justificativa da solicitação {$solicitacao} salva. O Comercial vê na aba Troca.");
    }

    /**
     * Administrador: grava (ou substitui) a verba extra de um setor da revenda em um mês.
     */
    public function salvarVerbaExtra(): void
    {
        $revenda = (string) $this->input('revenda', '');
        $setor = (int) $this->input('setor', 0);
        $mes = (string) $this->input('mes', '');
        $valor = (float) str_replace(',', '.', (string) $this->input('valor', ''));
        $motivo = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('motivo', ''))), 0, 255);
        $voltar = 'aprovacao-troca?mes=' . urlencode((string) $this->input('voltar_mes', ''));

        $setores = array_map('intval', array_column((new SetorModel())->listar($revenda), 'codigo'));

        if (!$this->podeAcessarRevenda($revenda) || !in_array($setor, $setores, true)) {
            $this->voltar($voltar, 'danger', 'Setor não encontrado na revenda.');
        }

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $this->voltar($voltar, 'danger', 'Informe um mês válido.');
        }

        if ($valor <= 0 || $valor > 9999999) {
            $this->voltar($voltar, 'danger', 'Informe um valor de verba extra maior que zero.');
        }

        if ($motivo === '') {
            $this->voltar($voltar, 'danger', 'Informe o motivo da verba extra.');
        }

        (new VerbaExtraModel())->salvar($revenda, $setor, $mes, $valor, $motivo, (int) $this->usuario()['id']);
        Logger::info("Verba extra de {$revenda} setor {$setor} em {$mes}: " . fmt_moeda($valor) . " ({$motivo}) por " . $this->usuario()['email']);
        $this->voltar($voltar, 'success', "Verba extra de " . fmt_moeda($valor) . " gravada para o setor {$setor} em " . fmt_mes($mes) . '.');
    }

    /**
     * Administrador: remove uma verba extra.
     */
    public function removerVerbaExtra(string $id): void
    {
        $model = new VerbaExtraModel();
        $extra = $model->find((int) $id);
        $voltar = 'aprovacao-troca?mes=' . urlencode((string) $this->input('voltar_mes', ''));

        if (!$extra || !$this->podeAcessarRevenda((string) $extra['revenda'])) {
            $this->voltar($voltar, 'danger', 'Verba extra não encontrada.');
        }

        $model->delete((int) $extra['id']);
        Logger::info("Verba extra de {$extra['revenda']} setor {$extra['setor_codigo']} em {$extra['mes']} (" . fmt_moeda($extra['valor']) . ') removida por ' . $this->usuario()['email']);
        $this->voltar($voltar, 'success', "Verba extra do setor {$extra['setor_codigo']} em " . fmt_mes((string) $extra['mes']) . ' removida.');
    }

    /**
     * Volta para a lista de solicitações com os mesmos filtros do formulário.
     */
    private function voltarParaSolicitacoes(): string
    {
        // Status e sistema vão sempre (vazio = "todos"; sem eles a tela volta ao padrão Force pendente).
        $filtros = [
            'solicitacoes' => 1,
            'mes' => (string) $this->input('f_mes', ''),
            'status' => (string) $this->input('f_status', ''),
            'sistema' => (string) $this->input('f_sistema', ''),
            'verba' => (string) $this->input('f_verba', ''),
            'setor' => (int) $this->input('f_setor', 0) ?: '',
            'cliente' => (int) $this->input('f_cliente', 0) ?: '',
        ];

        return 'aprovacao-troca?' . http_build_query($filtros);
    }

    /**
     * Filtros da lista de solicitações (GET). Padrão: Force pendente, que é o que consome a verba.
     */
    private function filtros(): array
    {
        $opcao = fn (string $campo, string $padrao, array $validos): string
            => in_array($valor = (string) $this->input($campo, $padrao), $validos, true) ? $valor : $padrao;

        return [
            'ativo' => array_key_exists('solicitacoes', $_GET),
            'status' => $opcao('status', 'pendente', ['', 'pendente', 'aprovada', 'reprovada']),
            'sistema' => $opcao('sistema', 'force', ['', 'force', 'promax', 'customer']),
            'verba' => $opcao('verba', '', ['', 'cabe', 'nao_cabe']),
            'setor' => (int) $this->input('setor', 0) ?: null,
            'cliente' => (int) $this->input('cliente', 0) ?: null,
        ];
    }

    private function filtrarSolicitacoes(array $lista, array $filtros, array $setores): array
    {
        return array_values(array_filter($lista, function (array $s) use ($filtros, $setores): bool {
            $setor = $s['setor_codigo'] !== null ? (int) $s['setor_codigo'] : null;

            return ($filtros['status'] === '' || mb_strtolower((string) $s['status_solicitacao']) === $filtros['status'])
                && ($filtros['sistema'] === '' || mb_strtolower((string) $s['sistema_origem']) === $filtros['sistema'])
                && ($filtros['verba'] === '' || $s['verba'] === $filtros['verba'])
                && ($filtros['setor'] === null || ($setor === $filtros['setor'] && in_array($setor, $setores, true)))
                && ($filtros['cliente'] === null || (int) $s['cliente_codigo'] === $filtros['cliente']);
        }));
    }
}
