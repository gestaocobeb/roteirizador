<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Models\MesaModel;
use App\Models\PedidoCoraModel;
use App\Models\PrioridadeModel;
use App\Models\PrioridadeTransferenciaModel;
use App\Models\SetorModel;

/**
 * Prioridade de entrega por Mesa, respeitando os limites de clientes e de HL
 * de cada setor (ajustados pelas transferências de saldo da data).
 */
class PrioridadeController extends BaseController
{
    /** Tolerância de arredondamento nas casas decimais do HL. */
    private const TOLERANCIA_HL = 0.0001;

    public function index(): void
    {
        $data = $this->dataReferencia();
        $mesas = $this->mesas();
        $mesa = (int) $this->mesaSelecionada($mesas, false);
        // A Mesa pode atender mais de uma revenda: mostra a escolhida no menu ou todas as da Mesa.
        $revenda = $this->revendaSelecionada();
        $revendasMesa = $revenda !== null ? [$revenda] : $this->revendasDaMesa($mesa);
        $model = new PrioridadeModel();

        $this->view('prioridade/index', [
            'dataRef' => $data,
            'mesas' => $mesas,
            'mesa' => $mesa,
            'revendasMesa' => $revendasMesa,
            'setores' => array_values($this->setoresDoDia($data, $mesa, $revenda)),
            'transferencias' => (new PrioridadeTransferenciaModel())->listar($data, $mesa),
            'prioridades' => $model->listar($data, $revenda, $mesa),
            'podeAlterar' => $this->podeAlterarMesa($mesa),
            'perfil' => $this->perfil(),
        ]);
    }

    public function adicionar(): void
    {
        $data = (string) $this->input('data', '');
        $mesa = (int) $this->input('mesa', 0);
        $cliente = (int) $this->input('cliente', 0);
        $observacao = mb_substr((string) $this->input('observacao', ''), 0, 255);
        $voltarPara = 'prioridade?data=' . urlencode($data) . '&mesa=' . $mesa;

        if (!data_valida($data) || $cliente <= 0) {
            $this->voltar($voltarPara, 'danger', 'Informe o código do cliente.');
        }

        if (!$this->podeAlterarMesa($mesa) || $this->revendasDaMesa($mesa) === []) {
            $this->voltar($voltarPara, 'danger', 'Você não pode registrar prioridades nesta Sala.');
        }

        // Procura o pedido nas revendas atendidas pela Mesa (ou só na escolhida no menu).
        $revendaMenu = $this->revendaSelecionada();
        $candidatas = $revendaMenu !== null ? array_intersect([$revendaMenu], $this->revendasDaMesa($mesa)) : $this->revendasDaMesa($mesa);
        $encontrados = [];
        foreach ($candidatas as $sigla) {
            $achado = (new PedidoCoraModel())->dadosCliente($data, $sigla, $cliente);
            if ($achado) {
                $encontrados[] = $achado;
            }
        }

        if (count($encontrados) > 1) {
            $this->voltar($voltarPara, 'warning', "O cliente {$cliente} tem pedido em mais de uma revenda desta Sala (" . implode(', ', array_column($encontrados, 'revenda')) . '). Escolha a revenda no menu superior e priorize de novo.');
        }

        $dados = $encontrados[0] ?? null;
        $revenda = $dados['revenda'] ?? null;
        $setor = ($dados && $dados['setor_codigo'] !== null) ? (new SetorModel())->buscarPorCodigo((int) $dados['setor_codigo']) : null;
        $semPedidoNaMesa = "Cliente {$cliente} não tem pedido aguardando roteirização na Sala {$mesa} para " . fmt_data($data) . '.';

        // Para o Comercial, cliente de outra Mesa recebe a mesma resposta de "sem pedido":
        // nenhum dado de outra Mesa (setor, existência do pedido) é revelado.
        if ($this->mesaRestrita() !== null && (!$setor || (int) $setor['mesa'] !== $mesa)) {
            $this->voltar($voltarPara, 'danger', $semPedidoNaMesa);
        }

        if (!$dados) {
            $this->voltar($voltarPara, 'danger', $semPedidoNaMesa);
        }

        if (!$setor || (int) $setor['ativo'] !== 1) {
            $this->voltar($voltarPara, 'danger', "Setor {$dados['setor_codigo']} ({$revenda}) do cliente {$cliente} não está cadastrado no Roteirizador.");
        }

        if ((int) $setor['mesa'] !== $mesa) {
            $this->voltar($voltarPara, 'danger', "O cliente {$cliente} é do setor {$setor['codigo']}, que pertence à Sala {$setor['mesa']}.");
        }

        if ($setor['revenda'] !== $revenda) {
            $this->voltar($voltarPara, 'danger', "O pedido do cliente {$cliente} é da revenda {$revenda}, mas o setor {$setor['codigo']} está cadastrado na {$setor['revenda']}. Ajuste o setor em Setores.");
        }

        $model = new PrioridadeModel();

        if ($model->existe($data, $revenda, $cliente)) {
            $this->voltar($voltarPara, 'warning', "O cliente {$cliente} já está priorizado nesta data.");
        }

        // Limites do dia: limite do setor ajustado pelas transferências da data.
        $uso = $this->setoresDoDia($data, $mesa)[(int) $setor['codigo']];
        $hlCliente = (float) $dados['volume_hl'];

        if ((int) $uso['clientes'] + 1 > $uso['limite_clientes_dia']) {
            $this->voltar($voltarPara, 'danger', "Setor {$setor['codigo']} já atingiu o limite de {$uso['limite_clientes_dia']} clientes priorizados.");
        }

        // Tolerância de arredondamento nas casas decimais do HL.
        if ((float) $uso['hl_utilizado'] + $hlCliente > $uso['limite_hl_dia'] + self::TOLERANCIA_HL) {
            $this->voltar($voltarPara, 'danger', "O cliente {$cliente} tem " . fmt_hl($hlCliente) . " HL e o setor {$setor['codigo']} só tem " . fmt_hl($uso['hl_livre']) . ' HL disponível.');
        }

        $model->adicionar($data, $revenda, $cliente, (int) $setor['codigo'], $observacao !== '' ? $observacao : null, (int) $this->usuario()['id']);
        $this->voltar($voltarPara, 'success', "Cliente {$cliente} priorizado no setor {$setor['codigo']} (" . fmt_hl($hlCliente) . ' HL).');
    }

    public function remover(string $id): void
    {
        $model = new PrioridadeModel();
        $prioridade = $model->buscarComMesa((int) $id);

        if (!$prioridade) {
            $this->voltar('prioridade', 'danger', 'Prioridade não encontrada.');
        }

        $voltarPara = 'prioridade?data=' . urlencode($prioridade['data_referencia']) . '&mesa=' . (int) $prioridade['mesa'];

        if (!$this->podeAlterarMesa((int) $prioridade['mesa'])) {
            $this->voltar($voltarPara, 'danger', 'Você não pode alterar prioridades desta Sala.');
        }

        $model->delete((int) $id);
        $this->voltar($voltarPara, 'success', "Prioridade do cliente {$prioridade['cliente_codigo']} removida.");
    }

    /**
     * Transfere saldo livre (vagas de cliente e/ou HL) de um setor para outro
     * da mesma Mesa, somente na data de entrega.
     */
    public function transferir(): void
    {
        $data = (string) $this->input('data', '');
        $mesa = (int) $this->input('mesa', 0);
        $origem = (int) $this->input('origem', 0);
        $destino = (int) $this->input('destino', 0);
        $clientesTexto = trim((string) $this->input('clientes', '0'));
        $hlTexto = str_replace(',', '.', trim((string) $this->input('hl', '0')));
        $voltarPara = 'prioridade?data=' . urlencode($data) . '&mesa=' . $mesa;

        if (!data_valida($data) || !$this->podeAlterarMesa($mesa) || $this->revendasDaMesa($mesa) === []) {
            $this->voltar($voltarPara, 'danger', 'Você não pode transferir saldo nesta Sala.');
        }

        $setores = $this->setoresDoDia($data, $mesa);

        // Os dois setores precisam ser ativos e da mesma Mesa.
        if (!isset($setores[$origem], $setores[$destino]) || $origem === $destino) {
            $this->voltar($voltarPara, 'danger', 'Escolha dois setores diferentes da Sala ' . $mesa . '.');
        }

        $clientes = ctype_digit($clientesTexto === '' ? '0' : $clientesTexto) ? (int) $clientesTexto : -1;
        $hl = is_numeric($hlTexto === '' ? '0' : $hlTexto) ? round((float) $hlTexto, 2) : -1.0;

        if ($clientes < 0 || $clientes > 255 || $hl < 0 || ($clientes === 0 && $hl <= 0)) {
            $this->voltar($voltarPara, 'danger', 'Informe a quantidade de clientes e/ou de HL a transferir.');
        }

        $saldo = $setores[$origem];

        if ($clientes > $saldo['clientes_livres']) {
            $this->voltar($voltarPara, 'danger', "O setor {$origem} só tem {$saldo['clientes_livres']} vaga(s) de cliente livre(s).");
        }

        if ($hl > $saldo['hl_livre'] + self::TOLERANCIA_HL) {
            $this->voltar($voltarPara, 'danger', "O setor {$origem} só tem " . fmt_hl($saldo['hl_livre']) . ' HL livre.');
        }

        (new PrioridadeTransferenciaModel())->registrar($data, $mesa, $origem, $destino, $clientes, $hl, (int) $this->usuario()['id']);
        Logger::info("Transferência de saldo em " . fmt_data($data) . ": setor {$origem} -> {$destino} ({$clientes} cliente(s), {$hl} HL) por " . $this->usuario()['email']);

        $this->voltar($voltarPara, 'success', "Transferido do setor {$origem} para o {$destino}: " . self::descricaoSaldo($clientes, $hl) . ' (somente em ' . fmt_data($data) . ').');
    }

    /**
     * Desfaz uma transferência, desde que o setor de destino não esteja usando o saldo recebido.
     */
    public function removerTransferencia(string $id): void
    {
        $model = new PrioridadeTransferenciaModel();
        $transferencia = $model->find((int) $id);

        if (!$transferencia) {
            $this->voltar('prioridade', 'danger', 'Transferência não encontrada.');
        }

        $data = $transferencia['data_referencia'];
        $mesa = (int) $transferencia['mesa'];
        $voltarPara = 'prioridade?data=' . urlencode($data) . '&mesa=' . $mesa;

        if (!$this->podeAlterarMesa($mesa) || $this->revendasDaMesa($mesa) === []) {
            $this->voltar('prioridade', 'danger', 'Transferência não encontrada.');
        }

        $destino = $this->setoresDoDia($data, $mesa)[(int) $transferencia['setor_destino_codigo']] ?? null;

        if ($destino && ($destino['clientes_livres'] < (int) $transferencia['clientes'] || $destino['hl_livre'] + self::TOLERANCIA_HL < (float) $transferencia['hl'])) {
            $this->voltar($voltarPara, 'danger', "O setor {$transferencia['setor_destino_codigo']} já está usando o saldo recebido. Remova prioridades dele antes de desfazer a transferência.");
        }

        $model->delete((int) $id);
        Logger::info("Transferência {$id} desfeita (setor {$transferencia['setor_origem_codigo']} -> {$transferencia['setor_destino_codigo']}, " . fmt_data($data) . ') por ' . $this->usuario()['email']);

        $this->voltar($voltarPara, 'success', "Transferência do setor {$transferencia['setor_origem_codigo']} para o {$transferencia['setor_destino_codigo']} desfeita.");
    }

    /**
     * Revendas atendidas pela Mesa que o usuário pode acessar.
     */
    private function revendasDaMesa(int $mesa): array
    {
        return array_values(array_intersect((new MesaModel())->revendasDa($mesa), $this->revendasPermitidas()));
    }

    /**
     * Setores ativos da Mesa com uso e limites do dia (codigo => linha):
     * limite do dia = limite do setor - saldo enviado + saldo recebido na data.
     * Com $revenda, apenas os setores dessa revenda.
     */
    private function setoresDoDia(string $data, int $mesa, ?string $revenda = null): array
    {
        $ajustes = (new PrioridadeTransferenciaModel())->ajustesPorSetor($data, $mesa);
        $setores = [];

        foreach ((new PrioridadeModel())->usoPorSetor($data, $mesa, $revenda) as $s) {
            $ajuste = $ajustes[(int) $s['codigo']] ?? ['clientes' => 0, 'hl' => 0.0];
            $s['ajuste_clientes'] = $ajuste['clientes'];
            $s['ajuste_hl'] = round($ajuste['hl'], 2);
            $s['limite_clientes_dia'] = (int) $s['limite_clientes'] + $ajuste['clientes'];
            $s['limite_hl_dia'] = round((float) $s['limite_hl'] + $ajuste['hl'], 2);
            $s['clientes_livres'] = max(0, $s['limite_clientes_dia'] - (int) $s['clientes']);
            $s['hl_livre'] = max(0.0, round($s['limite_hl_dia'] - (float) $s['hl_utilizado'], 4));
            $setores[(int) $s['codigo']] = $s;
        }

        return $setores;
    }

    private static function descricaoSaldo(int $clientes, float $hl): string
    {
        $partes = [];
        if ($clientes > 0) {
            $partes[] = "{$clientes} vaga(s) de cliente";
        }
        if ($hl > 0) {
            $partes[] = fmt_hl($hl) . ' HL';
        }

        return implode(' e ', $partes);
    }
}
