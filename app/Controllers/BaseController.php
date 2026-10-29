<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Helpers\Revenda;
use App\Helpers\VerbaTroca;
use App\Middleware\PerfilMiddleware;
use App\Models\IndicadorVendaModel;
use App\Models\MesaModel;
use App\Models\PedidoCoraModel;
use App\Models\SetorModel;
use App\Models\TrocaModel;
use App\Models\UsuarioModel;
use App\Models\VendaSetorModel;

/**
 * Base dos Controllers do Roteirizador: usuário logado, mensagens flash,
 * data de referência e regras de acesso por perfil/Mesa.
 */
abstract class BaseController extends Controller
{
    private ?array $usuarioAtual = null;

    protected function usuario(): ?array
    {
        if ($this->usuarioAtual === null && Auth::check()) {
            $model = new UsuarioModel();
            $this->usuarioAtual = $model->buscar(Auth::id());
            if ($this->usuarioAtual) {
                $this->usuarioAtual['mesas'] = $this->usuarioAtual['perfil'] === 'comercial' ? $model->mesasDe((int) $this->usuarioAtual['id']) : [];
            }
        }

        return $this->usuarioAtual;
    }

    /**
     * Renderiza a View incluindo usuário logado e mensagem flash para o layout.
     */
    protected function view(string $view, array $data = []): void
    {
        $flash = Session::get('_flash');
        Session::remove('_flash');

        $usuario = $this->usuario();

        parent::view($view, $data + [
            'usuarioLogado' => $usuario,
            'flash' => $flash,
            'rotaAtual' => PerfilMiddleware::abaDaRota() ?? '',
            'abasMenu' => $this->abasMenu(),
            'revendaAtual' => $usuario ? $this->revendaSelecionada() : null,
            'revendaFixa' => $usuario ? $this->revendaRestrita() !== null : false,
            'revendasMenu' => $usuario ? $this->revendasPermitidas() : [],
            // Comercial: Sala em uso e as Salas do usuário (seletor quando há mais de uma).
            'salaAtual' => $usuario ? $this->mesaRestrita() : null,
            'salasMenu' => $usuario['mesas'] ?? [],
        ]);
    }

    /**
     * Abas liberadas ao usuário logado (chave => definição), na ordem do menu.
     */
    protected function abasMenu(): array
    {
        $usuario = $this->usuario();

        if (!$usuario) {
            return [];
        }

        $liberadas = (new UsuarioModel())->abasLiberadas($usuario);

        return array_intersect_key(UsuarioModel::ABAS, array_flip($liberadas));
    }

    protected function flash(string $tipo, string $mensagem): void
    {
        Session::set('_flash', ['tipo' => $tipo, 'mensagem' => $mensagem]);
    }

    /**
     * Redireciona para uma rota interna com mensagem flash.
     */
    protected function voltar(string $caminho, string $tipo, string $mensagem): void
    {
        $this->flash($tipo, $mensagem);
        $this->redirect(base_url($caminho));
    }

    /**
     * Responde em JSON para requisições assíncronas (fetch) ou redireciona com flash.
     */
    protected function responder(bool $ok, string $mensagem, string $caminho): void
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            $this->json(['ok' => $ok, 'mensagem' => $mensagem], $ok ? 200 : 422);
        }

        $this->voltar($caminho, $ok ? 'success' : 'danger', $mensagem);
    }

    /**
     * Data de entrega em análise: parâmetro ?data= ou a última importada do Cora.
     */
    protected function dataReferencia(): string
    {
        $data = (string) $this->input('data', '');

        if (data_valida($data)) {
            return $data;
        }

        return (new PedidoCoraModel())->ultimaData($this->revendaSelecionada()) ?? date('Y-m-d', strtotime('+1 day'));
    }

    /**
     * Revendas que o usuário pode ver. Comercial: as atendidas pela Mesa em uso
     * (vazio = Mesa sem revenda, não vê nada). Logística: a do cadastro ou todas.
     * Administrador: todas. Toda consulta de dados por revenda respeita esta lista.
     */
    protected function revendasPermitidas(): array
    {
        $usuario = $this->usuario();

        return match ($this->perfil()) {
            'comercial' => array_values(array_filter(
                (new MesaModel())->revendasDa((int) $this->mesaRestrita()),
                [Revenda::class, 'valida']
            )),
            'logistica' => Revenda::valida($usuario['revenda'] ?? null) ? [$usuario['revenda']] : Revenda::siglas(),
            default => Revenda::siglas(),
        };
    }

    /**
     * Revenda fixa do usuário quando ele só pode ver uma ('' = nenhuma), ou null
     * quando pode escolher entre várias (seletor do menu).
     */
    protected function revendaRestrita(): ?string
    {
        if ($this->perfil() === 'admin') {
            return null;
        }

        $permitidas = $this->revendasPermitidas();

        return match (count($permitidas)) {
            0 => '',
            1 => $permitidas[0],
            default => null,
        };
    }

    /**
     * Revenda em uso nas telas: a fixa do usuário ou a escolhida no menu
     * (guardada na sessão, dentro das permitidas); null = todas as permitidas.
     * Comercial de Mesa com várias revendas continua restrito à própria Mesa.
     */
    protected function revendaSelecionada(): ?string
    {
        $restrita = $this->revendaRestrita();

        if ($restrita !== null) {
            return $restrita;
        }

        // Filtro das telas (GET ?revenda=, vazio = todas) atualiza a escolha do menu.
        // Só em GET: formulários POST também enviam "revenda" (importação, decisão).
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && array_key_exists('revenda', $_GET)) {
            if (Revenda::valida((string) $_GET['revenda'])) {
                Session::set('revenda', (string) $_GET['revenda']);
            } else {
                Session::remove('revenda');
            }
        }

        $escolhida = Session::get('revenda');

        return in_array($escolhida, $this->revendasPermitidas(), true) ? $escolhida : null;
    }

    /**
     * O usuário pode ver/alterar dados desta revenda?
     */
    protected function podeAcessarRevenda(?string $revenda): bool
    {
        return Revenda::valida($revenda) && in_array($revenda, $this->revendasPermitidas(), true);
    }

    protected function perfil(): string
    {
        return (string) ($this->usuario()['perfil'] ?? '');
    }

    /**
     * Decisões da Crítica GOD: Logística e Administrador (o Comercial apenas consulta).
     */
    protected function podeDecidirCritica(): bool
    {
        return in_array($this->perfil(), ['admin', 'logistica'], true);
    }

    /**
     * Mesa à qual o usuário está restrito: para o Comercial, a Mesa em uso — a
     * escolhida no menu (sessão "sala"), se for uma das dele, senão a primeira
     * (0 = sem Mesa vinculada, não vê nada); null para quem vê todas as Mesas.
     * Toda consulta de dados por Mesa deve respeitar este valor.
     */
    protected function mesaRestrita(): ?int
    {
        if ($this->perfil() !== 'comercial') {
            return null;
        }

        $mesas = $this->mesasDoUsuario();
        $escolhida = (int) Session::get('sala', 0);

        return in_array($escolhida, $mesas, true) ? $escolhida : ($mesas[0] ?? 0);
    }

    /**
     * Mesas cadastradas para o usuário do Comercial (vazio para os demais perfis).
     *
     * @return int[]
     */
    protected function mesasDoUsuario(): array
    {
        return $this->usuario()['mesas'] ?? [];
    }

    /**
     * Mesa em uso na tela. Comercial fica sempre na própria Mesa; os demais
     * escolhem pelo parâmetro ?mesa= (null = todas, quando permitido).
     */
    protected function mesaSelecionada(array $mesas, bool $permiteTodas): ?int
    {
        $restrita = $this->mesaRestrita();

        if ($restrita !== null) {
            return $restrita;
        }

        $mesa = (string) $this->input('mesa', '');

        if ($mesa !== '' && in_array((int) $mesa, $mesas, true)) {
            return (int) $mesa;
        }

        return $permiteTodas ? null : ($mesas[0] ?? 0);
    }

    /**
     * Prioridades: Comercial (somente na própria Mesa) e Administrador.
     */
    protected function podeAlterarMesa(?int $mesa): bool
    {
        if ($this->perfil() === 'admin') {
            return true;
        }

        return $this->perfil() === 'comercial'
            && $mesa !== null
            && in_array($mesa, $this->mesasDoUsuario(), true);
    }

    /**
     * Verba de troca da revenda (abas Aprovação troca e Troca). Como na planilha,
     * só os setores cadastrados; com $mesa, só os setores dessa Mesa.
     * foraDoCadastro: setores dos relatórios sem cadastro (ex.: 991, 995).
     */
    protected function verbaTroca(string $revenda, ?int $mesa = null): array
    {
        $indicadores = new IndicadorVendaModel();
        $valores = (new TrocaModel())->valoresPorSetor($revenda);
        $volumes = (new VendaSetorModel())->volumesPorSetor($revenda);

        $cadastrados = (new SetorModel())->listar($revenda);
        $todos = array_map('intval', array_column($cadastrados, 'codigo'));
        $setores = $mesa === null
            ? $todos
            : array_map('intval', array_column(array_filter($cadastrados, fn ($s) => (int) $s['mesa'] === $mesa), 'codigo'));
        sort($setores);

        $foraDoCadastro = array_values(array_diff(array_unique(array_merge(array_keys($valores), array_keys($volumes))), $todos));
        sort($foraDoCadastro);

        // Volume vendido da revenda: 17.03 (saída - devolução) quando importado;
        // senão, o total de HL do 03.05.09 (todas as linhas da revenda).
        $volumeVendido = VerbaTroca::volumeVendido(
            $indicadores->valor($revenda, VerbaTroca::INDICADOR_SAIDA),
            $indicadores->valor($revenda, VerbaTroca::INDICADOR_DEVOLUCAO)
        );
        $origemVolume = $volumeVendido !== null ? '17.03' : null;
        if ($volumeVendido === null && $volumes !== []) {
            $volumeVendido = array_sum($volumes);
            $origemVolume = '03.05.09';
        }

        $matriz = VerbaTroca::calcular($setores, $valores, $volumes, $volumeVendido);
        $matriz['origem_volume'] = $origemVolume;

        return [
            'matriz' => $matriz,
            'foraDoCadastro' => $foraDoCadastro,
        ];
    }

    /**
     * Mesas da revenda em uso (todas, quando nenhuma revenda está selecionada).
     */
    protected function mesas(): array
    {
        return (new MesaModel())->codigos($this->revendaSelecionada());
    }
}
