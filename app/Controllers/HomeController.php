<?php

namespace App\Controllers;

use App\Helpers\Importador;
use App\Helpers\Revenda;
use App\Models\ImportacaoModel;
use App\Models\PedidoCoraModel;
use App\Models\PrioridadeModel;

/**
 * Painel inicial: situação dos dados da data de entrega em análise.
 */
class HomeController extends BaseController
{
    public function index(): void
    {
        $data = $this->dataReferencia();
        $revenda = $this->revendaSelecionada();
        $mesa = $this->mesaRestrita();
        $importacoes = new ImportacaoModel();
        $revendasNaTela = $revenda !== null ? [$revenda] : $this->revendasPermitidas();

        $this->view('home/index', [
            'dataRef' => $data,
            'mesa' => $mesa,
            'revendasNaTela' => array_values(array_filter($revendasNaTela, [Revenda::class, 'valida'])),
            'totalClientes' => (new PedidoCoraModel())->totalClientes($data, $revenda, $mesa),
            'totalPrioridades' => (new PrioridadeModel())->totalNaData($data, $revenda, $mesa),
            'corasDaData' => array_intersect_key($importacoes->corasVigentesDaData($data), array_flip($revendasNaTela)),
            'ultimasPorTipo' => $importacoes->ultimasPorTipo(),
            'tipos' => Importador::TIPOS,
        ]);
    }
}
