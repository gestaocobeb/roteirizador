<?php

namespace App\Controllers;

use App\Core\Session;
use App\Helpers\Revenda;
use App\Models\UsuarioModel;

/**
 * Escolha da revenda e da Sala em uso (menu superior), para quem não está restrito a uma.
 */
class RevendaController extends BaseController
{
    public function trocar(): void
    {
        $revenda = (string) $this->input('revenda', '');
        $voltar = (string) $this->input('voltar', '');

        // Volta apenas para uma aba conhecida (evita redirecionamento arbitrário).
        $caminho = array_key_exists($voltar, UsuarioModel::ABAS) ? $voltar : '';

        if ($this->revendaRestrita() === null) {
            if (Revenda::valida($revenda)) {
                Session::set('revenda', $revenda);
            } else {
                Session::remove('revenda');
            }
        }

        $this->redirect(base_url($caminho));
    }

    /**
     * Comercial com mais de uma Sala (Mesa): escolhe a Sala em uso. Só aceita Salas do próprio usuário.
     */
    public function sala(): void
    {
        $sala = (int) $this->input('sala', 0);
        $voltar = (string) $this->input('voltar', '');
        $caminho = array_key_exists($voltar, UsuarioModel::ABAS) ? $voltar : '';

        if (in_array($sala, $this->mesasDoUsuario(), true)) {
            Session::set('sala', $sala);
        }

        $this->redirect(base_url($caminho));
    }
}
