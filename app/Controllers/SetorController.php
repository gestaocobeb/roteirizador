<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Helpers\Revenda;
use App\Models\MesaModel;
use App\Models\SetorModel;

/**
 * Cadastro de setores (Logística, na própria revenda). Mesas e limites de
 * prioridade (clientes e HL por setor) só podem ser alterados pelo Administrador.
 */
class SetorController extends BaseController
{
    // Limites para o primeiro setor de uma Mesa nova cadastrado pela Logística.
    private const LIMITES_PADRAO = ['limite_clientes' => 5, 'limite_hl' => 15.0];

    public function index(): void
    {
        $revenda = $this->revendaSelecionada();
        $setores = (new SetorModel())->listar($revenda);
        $mesas = [];

        foreach ($setores as $s) {
            $mesas[(int) $s['mesa']] ??= ['revendas' => [], 'limite_clientes' => $s['limite_clientes'], 'limite_hl' => $s['limite_hl'], 'setores' => 0];
            $mesas[(int) $s['mesa']]['revendas'][$s['revenda']] = $s['revenda'];
            $mesas[(int) $s['mesa']]['setores']++;
        }

        // Pares Mesa + revenda que o usuário pode usar no cadastro de setores.
        $permitidas = $revenda !== null ? [$revenda] : $this->revendasPermitidas();
        $mesasCadastradas = array_values(array_filter(
            (new MesaModel())->listar(),
            fn ($m) => in_array($m['revenda'], $permitidas, true)
        ));

        $this->view('setores/index', [
            'setores' => $setores,
            'mesasLimites' => $mesas,
            'mesasCadastradas' => $mesasCadastradas,
            'podeAlterarLimites' => $this->podeAlterarLimites(),
        ]);
    }

    /**
     * Administrador: cadastra uma Mesa numa revenda. A mesma Mesa pode ser
     * cadastrada em mais de uma revenda (ex.: Mesa 23 atende LP e AB).
     */
    public function criarMesa(): void
    {
        $codigo = (int) $this->input('codigo', 0);
        $revenda = (string) $this->input('revenda', '');
        $model = new MesaModel();

        if ($codigo <= 0 || $codigo > 65535 || !Revenda::valida($revenda)) {
            $this->voltar('setores', 'danger', 'Informe o código da Sala e a revenda.');
        }

        if ($model->atende($codigo, $revenda)) {
            $this->voltar('setores', 'warning', "A Sala {$codigo} já está cadastrada na revenda {$revenda}.");
        }

        $outras = $model->revendasDa($codigo);
        $model->create(['codigo' => $codigo, 'revenda' => $revenda]);
        Logger::info("Sala {$codigo} cadastrada na revenda {$revenda} por " . $this->usuario()['email']);
        $this->voltar('setores', 'success', "Sala {$codigo} cadastrada na revenda {$revenda}" . ($outras ? ' (também atende ' . implode(', ', $outras) . ').' : '.'));
    }

    public function criar(): void
    {
        $dados = $this->dadosSetor();
        $codigo = (int) $this->input('codigo', 0);
        $model = new SetorModel();

        if ($codigo <= 0 || $codigo > 65535 || $dados === null) {
            $this->voltar('setores', 'danger', 'Informe o código do setor e uma Sala cadastrada' . ($this->revendaRestrita() !== null ? ' da sua revenda.' : '.'));
        }

        if ($model->buscarPorCodigo($codigo)) {
            $this->voltar('setores', 'warning', "O setor {$codigo} já está cadastrado.");
        }

        if ($this->podeAlterarLimites()) {
            $limites = $this->limitesFormulario();

            if ($limites === null) {
                $this->voltar('setores', 'danger', 'Informe limites de clientes (0 a 255) e de HL válidos.');
            }
        } else {
            // Logística: o novo setor herda os limites da Mesa definidos pelo Administrador.
            $limites = $model->limitesDaMesa($dados['mesa']) ?? self::LIMITES_PADRAO;
        }

        $model->create(['codigo' => $codigo] + $dados + $limites);
        Logger::info("Setor {$codigo} cadastrado na Sala {$dados['mesa']} / {$dados['revenda']} ({$limites['limite_clientes']} clientes / {$limites['limite_hl']} HL) por " . $this->usuario()['email']);
        $this->voltar('setores', 'success', "Setor {$codigo} cadastrado.");
    }

    public function atualizar(string $id): void
    {
        $model = new SetorModel();
        $setor = $model->find((int) $id);
        $dados = $this->dadosSetor();

        // Setor de outra revenda é tratado como inexistente.
        if (!$setor || !$this->podeAcessarRevenda($setor['revenda'])) {
            $this->voltar('setores', 'danger', 'Setor não encontrado.');
        }

        if ($dados === null) {
            $this->voltar('setores', 'danger', 'Informe uma Sala cadastrada' . ($this->revendaRestrita() !== null ? ' da sua revenda.' : '.'));
        }

        if ($this->podeAlterarLimites()) {
            $limites = $this->limitesFormulario();

            if ($limites === null) {
                $this->voltar('setores', 'danger', 'Informe limites de clientes (0 a 255) e de HL válidos.');
            }

            $dados += $limites;
        }

        $model->update((int) $id, $dados);

        if (isset($dados['limite_clientes']) && ((int) $setor['limite_clientes'] !== $dados['limite_clientes'] || (float) $setor['limite_hl'] !== $dados['limite_hl'])) {
            Logger::info("Limites do setor {$setor['codigo']} alterados de {$setor['limite_clientes']} clientes / {$setor['limite_hl']} HL para {$dados['limite_clientes']} / {$dados['limite_hl']} por " . $this->usuario()['email']);
        }

        $this->voltar('setores', 'success', "Setor {$setor['codigo']} atualizado.");
    }

    /**
     * Administrador: aplica os mesmos limites a todos os setores de uma Mesa.
     */
    public function limitesMesa(string $mesa): void
    {
        $mesa = (int) $mesa;
        $model = new SetorModel();
        $limites = $this->limitesFormulario();

        if ($model->limitesDaMesa($mesa) === null) {
            $this->voltar('setores', 'danger', 'Sala não encontrada.');
        }

        if ($limites === null) {
            $this->voltar('setores', 'danger', 'Informe limites de clientes (0 a 255) e de HL válidos.');
        }

        $total = $model->atualizarLimitesDaMesa($mesa, $limites['limite_clientes'], $limites['limite_hl']);
        Logger::info("Limites da Sala {$mesa} alterados para {$limites['limite_clientes']} clientes / {$limites['limite_hl']} HL por setor por " . $this->usuario()['email']);
        $this->voltar('setores', 'success', "Sala {$mesa}: limite de {$limites['limite_clientes']} clientes e " . fmt_hl($limites['limite_hl']) . " HL aplicado aos setores ({$total} alterado(s)).");
    }

    private function podeAlterarLimites(): bool
    {
        return $this->perfil() === 'admin';
    }

    /**
     * Mesa e revenda do setor, enviadas juntas no formato "mesa|revenda" (ex.: "23|AB").
     * O par precisa estar cadastrado em Mesas e a revenda ser acessível ao usuário.
     */
    private function dadosSetor(): ?array
    {
        [$mesa, $revenda] = array_pad(explode('|', (string) $this->input('mesa', '')), 2, '');
        $mesa = (int) $mesa;

        if (!$this->podeAcessarRevenda($revenda) || !(new MesaModel())->atende($mesa, $revenda)) {
            return null;
        }

        return [
            'mesa' => $mesa,
            'revenda' => $revenda,
            'ativo' => $this->input('ativo') ? 1 : 0,
        ];
    }

    private function limitesFormulario(): ?array
    {
        $limiteClientes = (string) $this->input('limite_clientes', '');
        $limiteHl = str_replace(',', '.', (string) $this->input('limite_hl', ''));

        if (!ctype_digit($limiteClientes) || (int) $limiteClientes > 255 || !is_numeric($limiteHl) || (float) $limiteHl < 0 || (float) $limiteHl > 999999) {
            return null;
        }

        return [
            'limite_clientes' => (int) $limiteClientes,
            'limite_hl' => round((float) $limiteHl, 2),
        ];
    }
}
