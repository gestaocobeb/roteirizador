<?php

namespace App\Controllers;

use App\Models\CriticaDecisaoModel;
use App\Models\PedidoCoraModel;
use App\Models\PrioridadeModel;

/**
 * Consolidado do dia para a Logística montar a roteirização:
 * prioridades de todas as Mesas, decisões da Crítica e clientes com trava.
 */
class ConsultaController extends BaseController
{
    public function index(): void
    {
        $data = $this->dataReferencia();
        $mesas = $this->mesas();
        // Comercial vê apenas a própria Mesa; Administrador e Logística podem filtrar ou ver todas.
        $mesa = $this->mesaSelecionada($mesas, true);
        $revenda = $this->revendaSelecionada();
        $resumo = (new PedidoCoraModel())->resumoCritica($data, $revenda, $mesa);

        $decididos = array_values(array_filter($resumo, fn ($l) => $l['decisao'] !== null));
        $comTrava = array_values(array_filter($resumo, fn ($l) => $l['trava_condicao'] !== null));

        $pendentesPorMesa = [];
        foreach ($resumo as $linha) {
            $mesaLinha = $linha['gerente_codigo'] ?? '—';
            $pendentesPorMesa[$mesaLinha] ??= ['total' => 0, 'pendentes' => 0];
            $pendentesPorMesa[$mesaLinha]['total']++;
            if ($linha['decisao'] === null) {
                $pendentesPorMesa[$mesaLinha]['pendentes']++;
            }
        }
        ksort($pendentesPorMesa);

        $this->view('consulta/index', [
            'dataRef' => $data,
            'mesas' => $mesas,
            'mesa' => $mesa,
            'perfil' => $this->perfil(),
            'totalClientes' => count($resumo),
            'revenda' => $revenda,
            'prioridades' => (new PrioridadeModel())->listar($data, $revenda, $mesa),
            'decididos' => $decididos,
            'comTrava' => $comTrava,
            'pendentesPorMesa' => $pendentesPorMesa,
            'decisoes' => CriticaDecisaoModel::DECISOES,
        ]);
    }
}
