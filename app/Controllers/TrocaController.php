<?php

namespace App\Controllers;

use App\Helpers\Importador;
use App\Models\ImportacaoModel;
use App\Models\TrocaModel;
use App\Models\TrocaNotaModel;

/**
 * Aba Troca: solicitações de troca (Promax 03.18.05) por código de cliente e
 * notas de troca em rota do dia (Promax 03.02.37) por setor e o Faturado
 * (aprovado) da Verba de troca, nos setores da Mesa do filtro.
 */
class TrocaController extends BaseController
{
    /** Máximo de itens listados em Solicitações (o 03.18.05 do mês tem milhares). */
    private const LIMITE_SOLICITACOES = 1000;

    public function index(): void
    {
        $cliente = Importador::codigo((string) $this->input('cliente', ''));
        $setor = Importador::codigo((string) $this->input('setor', ''));
        // Comercial: somente a própria revenda e Mesa (cliente de outra Mesa = "não encontrado").
        $revenda = $this->revendaSelecionada();
        $mesa = $this->mesaRestrita();

        // Solicitações: filtros opcionais de cliente, setor e situação (só valores visíveis ao usuário).
        $trocaModel = new TrocaModel();
        $opcoesTroca = $trocaModel->opcoesFiltro($revenda, $mesa);
        $setorTroca = Importador::codigo((string) $this->input('setor_troca', ''));
        $setorTroca = in_array($setorTroca, $opcoesTroca['setores'], true) ? $setorTroca : null;
        $situacao = (string) $this->input('situacao', '');
        $situacao = in_array($situacao, $opcoesTroca['status'], true) ? $situacao : null;
        // Período da Data Solicitação (De/Até); datas inválidas são ignoradas e as invertidas, trocadas.
        $dataInicio = data_valida((string) $this->input('data_inicio', '')) ? (string) $this->input('data_inicio') : null;
        $dataFim = data_valida((string) $this->input('data_fim', '')) ? (string) $this->input('data_fim') : null;
        if ($dataInicio !== null && $dataFim !== null && $dataInicio > $dataFim) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }
        $buscaTrocas = $cliente !== null || $setorTroca !== null || $situacao !== null || $dataInicio !== null || $dataFim !== null;
        $trocas = $buscaTrocas
            ? $trocaModel->buscar($cliente, $setorTroca, $situacao, $dataInicio, $dataFim, $revenda, $mesa, self::LIMITE_SOLICITACOES + 1)
            : [];
        // Notas em rota: filtro de Mesa (Comercial fica sempre na própria; demais escolhem ou "Todas").
        $mesas = $this->mesas();
        $mesaNotas = $this->mesaSelecionada($mesas, true);
        $notas = new TrocaNotaModel();
        $setoresComNota = $notas->setores($revenda, $mesaNotas);

        // Setor fora da lista permitida (outra Mesa/revenda) é ignorado.
        if ($setor !== null && !in_array($setor, $setoresComNota, true)) {
            $setor = null;
        }

        $ultimas = (new ImportacaoModel())->ultimasPorTipo();
        $revendasNaTela = $revenda !== null ? [$revenda] : $this->revendasPermitidas();
        $importacoes = [];
        foreach ($revendasNaTela as $sigla) {
            foreach (['trocas', 'notas_troca'] as $tipo) {
                $ultima = $ultimas[$sigla][$tipo] ?? null;
                if ($ultima && $ultima['situacao'] !== 'excluida') {
                    $importacoes[$tipo][$sigla] = $ultima;
                }
            }
        }

        $listaNotas = $notas->listar($revenda, $mesaNotas, $setor);

        // Faturado (aprovado) da Verba de troca, por revenda, nos setores da Mesa do filtro.
        $verbas = [];
        foreach ($revendasNaTela as $sigla) {
            $matriz = $this->verbaTroca($sigla, $mesaNotas)['matriz'];
            if ($matriz['setores']) {
                $verbas[$sigla] = $matriz;
            }
        }

        $this->view('troca/index', [
            'cliente' => $cliente,
            'mesa' => $mesa,
            'revenda' => $revenda,
            // Uma linha a mais que o limite indica que a lista foi cortada.
            'trocas' => array_slice($trocas, 0, self::LIMITE_SOLICITACOES),
            'trocasCortadas' => count($trocas) > self::LIMITE_SOLICITACOES,
            'buscaTrocas' => $buscaTrocas,
            'setorTroca' => $setorTroca,
            'situacao' => $situacao,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'opcoesTroca' => $opcoesTroca,
            'setor' => $setor,
            'setoresComNota' => $setoresComNota,
            'mesas' => $mesas,
            'mesaNotas' => $mesaNotas,
            'notas' => $listaNotas,
            'fotoNotas' => $this->modeloFoto($listaNotas, $revenda, $mesaNotas, $setor),
            'importacoes' => $importacoes,
            'verbas' => $verbas,
            // Upload direto na aba para quem também tem a aba Importações.
            'podeImportar' => isset($this->abasMenu()['importacoes']),
        ]);
    }

    /**
     * Dados do modelo de imagem para WhatsApp: notas agrupadas (uma por nota/série)
     * com os itens, no mesmo filtro da tela. A imagem é desenhada no navegador.
     */
    private function modeloFoto(array $itens, ?string $revenda, ?int $mesa, ?int $setor): array
    {
        $notas = [];

        foreach ($itens as $i) {
            $chave = $i['revenda'] . '|' . $i['nota'] . '|' . $i['serie'];
            $notas[$chave] ??= [
                'nota' => $i['nota'] . ' / ' . $i['serie'],
                'setor' => (string) ($i['setor'] ?? '—'),
                'cliente' => $i['cliente_codigo'] . ' - ' . $i['cliente_nome'],
                'mapa' => (string) $i['mapa'],
                'itens' => [],
            ];
            $notas[$chave]['itens'][] = number_format((float) $i['quantidade'], 0, ',', '.') . ' ' . $i['unidade'] . ' - ' . $i['produto_descricao'];
        }

        $filtros = array_filter([
            $revenda !== null ? 'Revenda ' . $revenda : 'Todas as revendas',
            $mesa !== null ? 'Sala ' . $mesa : null,
            $setor !== null ? 'Setor ' . $setor : null,
        ]);
        $emissoes = array_unique(array_filter(array_column($itens, 'data_emissao')));
        sort($emissoes);

        return [
            'titulo' => 'Notas de troca em rota',
            'filtros' => implode(' · ', $filtros),
            'emissao' => $emissoes ? 'Emissão ' . implode(', ', array_map('fmt_data', $emissoes)) : '',
            'gerado' => 'Gerado em ' . date('d/m/Y H:i') . ' por ' . ($this->usuario()['nome'] ?? ''),
            'arquivo' => 'notas-troca' . ($mesa !== null ? '-mesa-' . $mesa : '') . ($setor !== null ? '-setor-' . $setor : '') . '.png',
            'notas' => array_values($notas),
        ];
    }
}
