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
     * Tipo do mapa: placa "REC..." = recarga; carga "Freteiro" = Spot; demais = FF.
     */
    public static function tipoMapa(string $placa, string $carga): string
    {
        if (str_starts_with(strtoupper(trim($placa)), 'REC')) {
            return self::RECARGA;
        }

        return Importador::normalizar($carga) === 'freteiro' ? self::SPOT : self::FF;
    }

    /**
     * @param array      $mapas   Linhas de farol_mapas da revenda
     * @param array      $buffer  Pedidos reprogramados da revenda
     * @param float|null $vendido Volume vendido (HL) digitado
     */
    public static function calcular(array $mapas, array $buffer, ?float $vendido): array
    {
        $grupos = [];
        foreach (array_keys(self::TIPOS) as $tipo) {
            $grupos[$tipo] = ['mapas' => [], 'quantidade' => 0, 'entregas' => 0, 'caixas' => 0.0, 'peso' => 0.0];
        }

        $data = null;
        foreach ($mapas as $mapa) {
            $tipo = self::tipoMapa((string) $mapa['placa'], (string) $mapa['carga']);
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
            'volume_vendido' => $vendido,
            'volume_buffer' => $volumeBuffer,
            // Na planilha: vendido - buffer.
            'volume_roteirizado' => $vendido !== null ? $vendido - $volumeBuffer : null,
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
