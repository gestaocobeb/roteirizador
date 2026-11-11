<?php

namespace App\Helpers;

/**
 * Cálculos do Farol por revenda (equivalente às abas "Plan1" e "R. 03.11.49.02"
 * da PLANILHA FAROL). Não acessa o banco: recebe os mapas, o buffer e os dados digitados.
 */
class Farol
{
    public const FF = 'ff';
    public const SPOT = 'spot';
    public const RECARGA = 'recarga';

    public const TIPOS = [
        self::FF => 'FF (frota fixa)',
        self::SPOT => 'Spot',
        self::RECARGA => 'Recarga',
    ];

    /**
     * Tipo do mapa: placa "REC..." ou placa repetida (2º mapa em diante da mesma
     * placa no arquivo) = recarga; carga "Freteiro" = Spot; demais = FF.
     */
    public static function tipoMapa(string $placa, string $carga, bool $placaRepetida = false): string
    {
        if ($placaRepetida || str_starts_with(strtoupper(trim($placa)), 'REC')) {
            return self::RECARGA;
        }

        return Importador::normalizar($carga) === 'freteiro' ? self::SPOT : self::FF;
    }

    /**
     * @param array      $mapas        Linhas de farol_mapas da revenda
     * @param array      $buffer       Pedidos REPROGRAMADO do Cora da revenda
     * @param float|null $roteirizado  HL do Cora em ENVIADO_HUB_FATURAMENTO + AGUARDANDO_ROTEIRIZACAO
     *                                 (null = arquivo do Cora não importado)
     */
    public static function calcular(array $mapas, array $buffer, ?float $roteirizado): array
    {
        $grupos = [];
        foreach (array_keys(self::TIPOS) as $tipo) {
            $grupos[$tipo] = ['mapas' => [], 'quantidade' => 0, 'entregas' => 0, 'caixas' => 0.0, 'peso' => 0.0];
        }

        $data = null;
        $placasVistas = [];
        // $mapas vem na ordem do arquivo: o 1º mapa de cada placa segue a regra normal; os seguintes são recarga.
        foreach ($mapas as $mapa) {
            $placa = strtoupper(trim((string) $mapa['placa']));
            $tipo = self::tipoMapa($placa, (string) $mapa['carga'], isset($placasVistas[$placa]));
            $placasVistas[$placa] = true;
            $grupos[$tipo]['mapas'][] = $mapa;
            $grupos[$tipo]['quantidade']++;
            $grupos[$tipo]['entregas'] += (int) $mapa['entregas'];
            $grupos[$tipo]['caixas'] += (float) $mapa['caixas'];
            $grupos[$tipo]['peso'] += (float) $mapa['peso'];
            if ($mapa['data_entrega'] && $mapa['data_entrega'] > (string) $data) {
                $data = $mapa['data_entrega'];
            }
        }

        $volumeBuffer = array_sum(array_map(fn ($p) => (float) $p['volume_hl'], $buffer));
        $clientesBuffer = count(array_unique(array_map(fn ($p) => (int) $p['cliente_codigo'], $buffer)));

        $cxViagem = fn (string $tipo) => $grupos[$tipo]['quantidade'] > 0
            ? $grupos[$tipo]['caixas'] / $grupos[$tipo]['quantidade']
            : null;

        return [
            'data_entrega' => $data,
            'grupos' => $grupos,
            // Vendido = roteirizado + buffer (ENVIADO_HUB_FATURAMENTO + AGUARDANDO_ROTEIRIZACAO + REPROGRAMADO).
            'volume_vendido' => $roteirizado !== null ? $roteirizado + $volumeBuffer : null,
            'volume_buffer' => $volumeBuffer,
            'volume_roteirizado' => $roteirizado,
            'clientes_roteirizados' => array_sum(array_column($grupos, 'entregas')),
            'clientes_buffer' => $clientesBuffer,
            'peso_roteirizado' => array_sum(array_column($grupos, 'peso')),
            'mapas_ff' => $grupos[self::FF]['quantidade'],
            'mapas_spot' => $grupos[self::SPOT]['quantidade'],
            'recargas' => $grupos[self::RECARGA]['quantidade'],
            'cx_viagem_ff' => $cxViagem(self::FF),
            'cx_viagem_spot' => $cxViagem(self::SPOT),
        ];
    }

    /** Ajustes manuais: chave do cálculo => coluna de farol_revendas. */
    public const AJUSTES = [
        'mapas_ff' => 'mapas_ff_ajuste',
        'mapas_spot' => 'mapas_spot_ajuste',
        'recargas' => 'recargas_ajuste',
    ];

    /**
     * Troca as quantidades de mapas FF, Spot e recarga pelas informadas na tela,
     * só se foram salvas para a mesma entrega dos mapas importados (os ajustes de
     * ontem não valem para o 03.11.49.02 de hoje). Guarda a quantidade do arquivo
     * em 'calculados' para mostrar ao lado. Cx/viagem continua pelo arquivo.
     */
    public static function aplicarAjustes(array $farol, ?array $digitados): array
    {
        $farol['calculados'] = [];
        if (!$digitados || $digitados['ajuste_data'] !== $farol['data_entrega']) {
            return $farol;
        }

        foreach (self::AJUSTES as $campo => $coluna) {
            if ($digitados[$coluna] !== null) {
                $farol['calculados'][$campo] = $farol[$campo];
                $farol[$campo] = (int) $digitados[$coluna];
            }
        }

        return $farol;
    }

    /**
     * Resumo do dia: soma das revendas.
     */
    public static function resumo(array $farois): array
    {
        $soma = fn (string $campo) => array_sum(array_map(fn ($f) => (float) ($f[$campo] ?? 0), $farois));

        return [
            'volume_vendido' => $soma('volume_vendido'),
            'volume_roteirizado' => $soma('volume_roteirizado'),
            'volume_buffer' => $soma('volume_buffer'),
            'clientes_roteirizados' => (int) $soma('clientes_roteirizados'),
            'clientes_buffer' => (int) $soma('clientes_buffer'),
        ];
    }
}
