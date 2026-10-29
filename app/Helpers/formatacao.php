<?php

/**
 * Funções de formatação e validação usadas pelo Roteirizador.
 * Carregadas pelo autoload "files" do Composer.
 */

if (!function_exists('fmt_moeda')) {
    function fmt_moeda($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}

if (!function_exists('fmt_hl')) {
    function fmt_hl($valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }
}

if (!function_exists('fmt_data')) {
    function fmt_data(?string $data): string
    {
        $timestamp = $data ? strtotime($data) : false;
        return $timestamp ? date('d/m/Y', $timestamp) : '';
    }
}

if (!function_exists('fmt_data_hora')) {
    function fmt_data_hora(?string $data): string
    {
        $timestamp = $data ? strtotime($data) : false;
        return $timestamp ? date('d/m/Y H:i', $timestamp) : '';
    }
}

if (!function_exists('data_valida')) {
    /**
     * Valida uma data no formato Y-m-d (formato do <input type="date">).
     */
    function data_valida(?string $data): bool
    {
        $objeto = \DateTime::createFromFormat('Y-m-d', (string) $data);
        return $objeto !== false && $objeto->format('Y-m-d') === $data;
    }
}

if (!function_exists('cor_gap')) {
    /**
     * Cor Bootstrap do GAP da Verba de troca: negativo = danger (vermelho),
     * de R$ 0,00 a R$ 10,00 = warning (amarelo), acima de R$ 10,00 = success (verde).
     */
    function cor_gap($valor): string
    {
        $valor = round((float) $valor, 2);

        if ($valor < 0) {
            return 'danger';
        }

        return $valor <= 10 ? 'warning' : 'success';
    }
}

if (!function_exists('percentual')) {
    /**
     * Percentual de uso limitado a 0-100, para barras de progresso.
     */
    function percentual($usado, $limite): int
    {
        if ((float) $limite <= 0) {
            return 0;
        }

        return (int) max(0, min(100, round(((float) $usado / (float) $limite) * 100)));
    }
}
