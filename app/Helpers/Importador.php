<?php

namespace App\Helpers;

use RuntimeException;

/**
 * Importador (cada importação pertence a uma revenda: PM, LP ou AB)
 *
 * Converte as planilhas exportadas do Cora/Promax nos registros que o
 * Roteirizador grava no banco. As colunas são localizadas pelo nome do
 * cabeçalho (sem acento, sem diferença de maiúsculas), então a ordem das
 * colunas no export pode mudar sem quebrar a importação.
 */
class Importador
{
    public const TIPOS = [
        'cora' => 'Pedidos Cora (aguardando roteirização)',
        'clientes' => 'Cadastro de clientes (Promax 01.20.11)',
        'notas' => 'Histórico de notas (Promax 03.02.37)',
        'black_list' => 'Black List (Promax 03.02.24)',
        'top_line' => 'Clientes Top Line',
        'travas' => 'Clientes com trava',
        'trocas' => 'Solicitações de troca (Promax 03.18.05)',
        'notas_troca' => 'Notas de troca em rota (Promax 03.02.37)',
        'vendas_setor' => 'Volume vendido por setor (Promax 03.05.09)',
        'indicadores' => 'Indicadores de vendas (Promax 17.03)',
        'farol_mapas' => 'Farol - Mapas roteirizados (Promax 03.11.49.02)',
        'farol_buffer' => 'Farol - Buffer do Cora (Tipo buffer)',
    ];

    /**
     * Coluna do relatório que identifica a revenda: campo do layout => código em Revenda::REVENDAS.
     * Linhas de outra revenda são descartadas; os demais tipos usam a revenda escolhida na importação.
     */
    private const COLUNA_REVENDA = [
        'cora' => ['unidade_venda', 'unidade_venda'],
        'trocas' => ['unb', 'unb'],
        'farol_mapas' => ['unb', 'unb'],
        'farol_buffer' => ['unidade_venda', 'unidade_venda'],
    ];

    /**
     * Layout esperado de cada tipo: abas aceitas (quando .xlsx com várias abas),
     * campo => cabeçalho normalizado e campos obrigatórios.
     */
    private const LAYOUTS = [
        'cora' => [
            'abas' => ['Cora', 'Cora Matriz'],
            'campos' => [
                'numero_pedido' => 'numero pedido',
                'tipo_pedido' => 'tipo pedido',
                'situacao_pedido' => 'situacao pedido',
                'situacao_atendimento' => 'situacao atend pedido',
                'municipio' => 'desc municipio',
                'cliente_codigo' => 'cod cliente',
                'cliente_nome' => 'nome cliente',
                'nome_fantasia' => 'nome fantasia',
                'setor_codigo' => 'cod setor',
                'setor_descricao' => 'desc setor',
                'gerente_codigo' => 'cod gerente vendas',
                'valor_total' => 'valor total',
                'volume_hl' => 'volume hectolitro',
                'produto_descricao' => 'desc produto',
                'critica_1' => 'criticas 1',
                'critica_2' => 'criticas 2',
                'critica_3' => 'criticas 3',
                'critica_4' => 'criticas 4',
                'critica_5' => 'criticas 5',
                'critica_6' => 'criticas 6',
                'unidade_venda' => 'cod unidade venda',
            ],
            // "tipo pedido" é obrigatório para garantir o descarte das reposições.
            'obrigatorios' => ['numero_pedido', 'tipo_pedido', 'cliente_codigo', 'valor_total'],
        ],
        'clientes' => [
            'abas' => ['01 20 11'],
            'campos' => [
                'codigo' => 'codigo cliente',
                'razao_social' => 'razao social',
                'nome_fantasia' => 'nome fantasia',
                'setor_codigo' => 'cod setor',
                'cidade' => 'cidade',
                'bairro' => 'bairro',
                'dia_visita' => 'frequencia visita',
                'status_cliente' => 'status do cliente',
                'segmento' => 'segmento',
            ],
            'obrigatorios' => ['codigo', 'setor_codigo'],
        ],
        'notas' => [
            'abas' => ['03 02 37'],
            'campos' => ['cliente_codigo' => 'cliente'],
            'obrigatorios' => ['cliente_codigo'],
        ],
        'black_list' => [
            'abas' => ['03 02 24'],
            'campos' => ['cliente_codigo' => ['cliente', 'cod cliente', 'codigo cliente']],
            'obrigatorios' => ['cliente_codigo'],
            // Sem cabeçalho reconhecível, usa a coluna G (mesma da planilha original).
            'coluna_padrao' => 6,
        ],
        'top_line' => [
            'abas' => ['TOP LINE'],
            'campos' => ['cliente_codigo' => 'cod', 'nome' => 'nome', 'observacao' => 'obs padrao'],
            'obrigatorios' => ['cliente_codigo'],
        ],
        'travas' => [
            'abas' => ['TRAVAS'],
            'campos' => [
                'cliente_codigo' => 'cod',
                'nome' => 'nome cliente',
                'setor_codigo' => 'setor',
                'condicao_entrega' => 'condicao de entrega',
                'motivo' => 'motivo',
            ],
            'obrigatorios' => ['cliente_codigo'],
        ],
        // O 03.18.05 repete "Descrição Produto" e "U.M." para o produto de origem;
        // vale a primeira ocorrência (produto da troca).
        'trocas' => [
            'abas' => ['03 18 05'],
            'campos' => [
                'cliente_codigo' => 'codigo cliente',
                'cliente_nome' => 'nome cliente',
                'solicitacao' => 'solicitacao reposicao',
                'data_solicitacao' => 'data solicitacao',
                'status_solicitacao' => 'status solicitacao',
                'nota_fiscal' => 'nota fiscal serie',
                'produto_codigo' => 'produto',
                'produto_descricao' => 'descricao produto',
                'quantidade' => 'quantidade',
                'unidade' => 'u m',
                'sistema_origem' => 'sistema origem',
                'observacao' => 'observacao',
                'setor_codigo' => 'setor venda',
                'unb' => 'unb',
                'valor' => 'valor',
                'motorista_nome' => 'nome motorista',
                // O relatório traz dois ajudantes com o mesmo cabeçalho ("#2" = 2ª ocorrência).
                'ajudante1_nome' => 'nome ajudante entrega',
                'ajudante2_nome' => 'nome ajudante entrega#2',
            ],
            'obrigatorios' => ['cliente_codigo', 'data_solicitacao', 'produto_codigo'],
        ],
        // 03.02.37 das notas de troca em rota (não confundir com o 03.02.37 de histórico).
        'notas_troca' => [
            'abas' => ['03 02 37'],
            'campos' => [
                'operacao' => 'operacao',
                'data_operacao' => 'dt operacao',
                'data_emissao' => 'emissao',
                'nota' => 'nota',
                'serie' => 'serie',
                'cliente_codigo' => 'cliente',
                'cliente_nome' => 'nome',
                'produto_codigo' => 'produto',
                'unidade' => 'unidade',
                'produto_descricao' => 'descricao',
                'quantidade' => 'qtde',
                'valor_total' => 'total',
                'mapa' => 'mapa',
                'numero_pedido' => 'nr pedido',
                'status_nfe' => 'status nf e',
                'setor_codigo' => 'setor',
            ],
            'obrigatorios' => ['nota', 'cliente_codigo', 'produto_codigo'],
        ],
        'vendas_setor' => [
            'abas' => ['03 05 09'],
            'campos' => ['setor_codigo' => 'setor', 'volume_hl' => 'quantidade hecto'],
            'obrigatorios' => ['setor_codigo', 'volume_hl'],
        ],
        'indicadores' => [
            'abas' => ['17 03'],
            'campos' => ['codigo' => 'cod id', 'descricao' => 'descricao id', 'valor' => 'valor'],
            'obrigatorios' => ['codigo', 'valor'],
        ],
        // 03.11.49.02: uma linha por mapa roteirizado (placa).
        'farol_mapas' => [
            'abas' => ['03 11 49 02'],
            'campos' => [
                'unb' => 'unb',
                'data_entrega' => 'data entrega',
                'numero_mapa' => 'nro do mapa',
                'veiculo' => 'veiculo',
                'placa' => 'placa',
                'carga' => 'carga',
                'entregas' => 'entregas',
                'caixas' => 'total de caixas',
                'peso' => 'total peso',
            ],
            'obrigatorios' => ['placa', 'carga', 'entregas', 'caixas', 'peso'],
        ],
        // Mesmo export do Cora, usado só pelo Farol (com a coluna "Tipo buffer"; reposições são gravadas, mas não contam no buffer).
        'farol_buffer' => [
            'abas' => ['CORA', 'Cora'],
            'campos' => [
                'unidade_venda' => 'cod unidade venda',
                'numero_pedido' => 'numero pedido',
                'tipo_pedido' => 'tipo pedido',
                'cliente_codigo' => 'cod cliente',
                'nome_fantasia' => 'nome fantasia',
                'tipo_buffer' => 'tipo buffer',
                'volume_hl' => 'volume hectolitro',
                'setor_codigo' => 'cod setor',
                'setor_descricao' => 'desc setor',
            ],
            'obrigatorios' => ['numero_pedido', 'cliente_codigo', 'tipo_buffer', 'volume_hl'],
        ],
    ];

    /** Linhas iniciais analisadas à procura do cabeçalho. */
    private const LIMITE_BUSCA_CABECALHO = 30;

    /**
     * Tipos de pedido do Cora (coluna "Tipo pedido", normalizada) descartados na
     * importação. Reposições não entram na Crítica GOD nem no cálculo de HL.
     */
    private const TIPOS_PEDIDO_IGNORADOS = ['reposicao'];

    /**
     * Operações do 03.02.37 (coluna "Operacao") descartadas na importação das
     * notas de troca em rota.
     */
    private const OPERACOES_IGNORADAS_NOTAS_TROCA = [9, 23, 33];

    /** Avisos da última leitura (ex.: linhas descartadas), para exibir ao usuário. */
    private array $avisos = [];

    public function avisos(): array
    {
        return $this->avisos;
    }

    /**
     * Lê o arquivo e devolve os registros prontos para gravação.
     *
     * @param string $revenda           Revenda escolhida na importação (PM, LP, AB)
     * @param array $setoresCadastrados Códigos de setor do Roteirizador, usados para
     *                                  escolher o setor certo quando o cliente aparece
     *                                  em mais de um setor no cadastro (ex.: setor digital).
     */
    public function ler(string $tipo, string $caminho, string $extensao, string $revenda, array $setoresCadastrados = []): array
    {
        $layout = self::LAYOUTS[$tipo] ?? null;

        if ($layout === null) {
            throw new RuntimeException('Tipo de importação inválido.');
        }

        $this->avisos = [];
        $brutos = $this->filtrarRevenda($tipo, $this->extrair($layout, $caminho, $extensao), $revenda);
        $registros = $this->transformar($tipo, $brutos, $setoresCadastrados);

        if ($registros === []) {
            throw new RuntimeException('Nenhuma linha válida encontrada no arquivo. Confira se o relatório escolhido corresponde ao tipo de importação.');
        }

        return $registros;
    }

    /**
     * O relatório informa a revenda em cada linha (pode ser importado com "Todas")?
     */
    public static function separaPorRevenda(string $tipo): bool
    {
        return isset(self::COLUNA_REVENDA[$tipo]);
    }

    /**
     * Lê um arquivo com várias revendas (Cora, 03.18.05) e separa os registros pela
     * coluna de revenda de cada linha. Linhas sem o código ou com código desconhecido
     * são ignoradas (com aviso), pois não há revenda escolhida para atribuí-las.
     *
     * @return array<string, array> sigla => registros, na ordem PM, LP, AB
     */
    public function lerTodasRevendas(string $tipo, string $caminho, string $extensao, array $setoresCadastrados = []): array
    {
        $layout = self::LAYOUTS[$tipo] ?? null;
        [$campo, $chaveCodigo] = self::COLUNA_REVENDA[$tipo] ?? [null, null];

        if ($layout === null || $campo === null) {
            throw new RuntimeException('Este relatório não informa a revenda em cada linha. Escolha a revenda na importação.');
        }

        $this->avisos = [];
        $brutos = $this->extrair($layout, $caminho, $extensao);

        if ($brutos !== [] && !array_key_exists($campo, $brutos[0])) {
            throw new RuntimeException('O arquivo não tem a coluna que identifica a revenda ("' . $layout['campos'][$campo] . '"). Escolha a revenda na importação.');
        }

        $grupos = [];
        $semCodigo = 0;
        $desconhecidos = [];

        foreach ($brutos as $b) {
            $codigo = self::codigo($b[$campo]);
            if ($codigo === null) {
                // Conta só linhas com conteúdo (ignora linhas em branco do export).
                $semCodigo += trim(implode('', $b)) !== '' ? 1 : 0;
                continue;
            }

            $sigla = Revenda::porCodigo($chaveCodigo, $codigo);
            if ($sigla === null) {
                $desconhecidos[$codigo] = true;
                continue;
            }

            $grupos[$sigla][] = $b;
        }

        $porRevenda = [];
        foreach (Revenda::siglas() as $sigla) {
            if (!isset($grupos[$sigla])) {
                continue;
            }

            // Avisos da transformação (ex.: reposições ignoradas) identificados pela revenda.
            $antes = count($this->avisos);
            $registros = $this->transformar($tipo, $grupos[$sigla], $setoresCadastrados);
            for ($i = $antes; $i < count($this->avisos); $i++) {
                $this->avisos[$i] = "{$sigla}: {$this->avisos[$i]}";
            }

            if ($registros !== []) {
                $porRevenda[$sigla] = $registros;
            }
        }

        if ($semCodigo > 0) {
            $this->avisos[] = "{$semCodigo} linha(s) sem código de revenda ignorada(s)";
        }
        if ($desconhecidos) {
            $this->avisos[] = 'código(s) de revenda desconhecido(s) ignorado(s): ' . implode(', ', array_keys($desconhecidos));
        }

        if ($porRevenda === []) {
            throw new RuntimeException('Nenhuma linha válida de PM, LP ou AB encontrada no arquivo. Confira se o relatório escolhido corresponde ao tipo de importação.');
        }

        return $porRevenda;
    }

    private function transformar(string $tipo, array $brutos, array $setoresCadastrados): array
    {
        return match ($tipo) {
            'cora' => $this->transformarCora($brutos),
            'clientes' => $this->transformarClientes($brutos, $setoresCadastrados),
            'notas' => $this->contarPorCliente($brutos, 'quantidade'),
            'black_list' => $this->contarPorCliente($brutos, 'ocorrencias'),
            'top_line' => $this->transformarTopLine($brutos),
            'travas' => $this->transformarTravas($brutos),
            'trocas' => $this->transformarTrocas($brutos),
            'notas_troca' => $this->transformarNotasTroca($brutos),
            'vendas_setor' => $this->somarVolumePorSetor($brutos),
            'indicadores' => $this->transformarIndicadores($brutos),
            'farol_mapas' => $this->transformarFarolMapas($brutos),
            'farol_buffer' => $this->transformarFarolBuffer($brutos),
        };
    }

    /**
     * Localiza o cabeçalho e devolve apenas as colunas mapeadas de cada linha.
     */
    private function extrair(array $layout, string $caminho, string $extensao): array
    {
        $indices = null;
        $lidas = 0;
        $brutos = [];

        foreach (PlanilhaLeitor::linhas($caminho, $extensao, $layout['abas']) as $linha) {
            if ($indices === null) {
                $indices = $this->localizarCabecalho($linha, $layout);
                if ($indices === null && ++$lidas >= self::LIMITE_BUSCA_CABECALHO) {
                    break;
                }
                continue;
            }

            $brutos[] = $this->mapear($linha, $indices);
        }

        if ($indices !== null) {
            return $brutos;
        }

        if (isset($layout['coluna_padrao'])) {
            $indices = ['cliente_codigo' => $layout['coluna_padrao']];
            foreach (PlanilhaLeitor::linhas($caminho, $extensao, $layout['abas']) as $linha) {
                $brutos[] = $this->mapear($linha, $indices);
            }
            return $brutos;
        }

        $esperadas = [];
        foreach ($layout['obrigatorios'] as $campo) {
            $esperadas[] = '"' . implode('" ou "', (array) $layout['campos'][$campo]) . '"';
        }

        throw new RuntimeException('Cabeçalho não encontrado. Colunas obrigatórias: ' . implode(', ', $esperadas) . '.');
    }

    /**
     * Mantém apenas as linhas da revenda escolhida, quando o relatório informa a revenda.
     * Arquivo sem nenhuma linha da revenda é recusado (provável escolha errada).
     */
    private function filtrarRevenda(string $tipo, array $brutos, string $revenda): array
    {
        [$campo, $chaveCodigo] = self::COLUNA_REVENDA[$tipo] ?? [null, null];

        if ($campo === null || $brutos === [] || !array_key_exists($campo, $brutos[0])) {
            return $brutos;
        }

        $daRevenda = [];
        $outras = [];
        $confirmadas = 0;

        foreach ($brutos as $b) {
            $codigo = self::codigo($b[$campo]);
            $siglaLinha = Revenda::porCodigo($chaveCodigo, $codigo);

            // Linha sem o código (célula vazia) segue a revenda escolhida.
            if ($codigo === null || $siglaLinha === $revenda) {
                $daRevenda[] = $b;
                $confirmadas += $codigo !== null ? 1 : 0;
            } else {
                $outras[$siglaLinha ?? ('código ' . $codigo)] = true;
            }
        }

        if ($confirmadas === 0 && $outras !== []) {
            throw new RuntimeException("O arquivo não tem linhas da revenda {$revenda} (encontrado: " . implode(', ', array_keys($outras)) . '). Confira a revenda escolhida.');
        }

        if ($outras !== []) {
            $this->avisos[] = (count($brutos) - count($daRevenda)) . ' linha(s) de outra revenda ignorada(s) (' . implode(', ', array_keys($outras)) . ')';
        }

        return $daRevenda;
    }

    private function localizarCabecalho(array $linha, array $layout): ?array
    {
        // Todas as ocorrências de cada cabeçalho; vale a primeira, salvo "nome#N" (N-ésima).
        $posicoes = [];
        foreach ($linha as $indice => $valor) {
            $chave = self::normalizar((string) $valor);
            if ($chave !== '') {
                $posicoes[$chave][] = $indice;
            }
        }

        $indices = [];
        foreach ($layout['campos'] as $campo => $cabecalhos) {
            foreach ((array) $cabecalhos as $cabecalho) {
                [$nome, $ocorrencia] = array_pad(explode('#', $cabecalho, 2), 2, '1');
                if (isset($posicoes[$nome][(int) $ocorrencia - 1])) {
                    $indices[$campo] = $posicoes[$nome][(int) $ocorrencia - 1];
                    break;
                }
            }
        }

        foreach ($layout['obrigatorios'] as $campo) {
            if (!isset($indices[$campo])) {
                return null;
            }
        }

        return $indices;
    }

    private function mapear(array $linha, array $indices): array
    {
        $registro = [];
        foreach ($indices as $campo => $indice) {
            $registro[$campo] = (string) ($linha[$indice] ?? '');
        }

        return $registro;
    }

    private function transformarCora(array $brutos): array
    {
        $registros = [];
        $ignorados = [];

        foreach ($brutos as $b) {
            $cliente = self::codigo($b['cliente_codigo'] ?? '');
            $pedido = self::texto($b['numero_pedido'] ?? '', 30);

            if ($cliente === null || $pedido === '') {
                continue;
            }

            $tipo = self::normalizar($b['tipo_pedido'] ?? '');
            if (in_array($tipo, self::TIPOS_PEDIDO_IGNORADOS, true)) {
                $ignorados[$pedido] = true;
                continue;
            }

            $criticas = [];
            for ($i = 1; $i <= 6; $i++) {
                $critica = self::texto($b["critica_{$i}"] ?? '', 150);
                if ($critica !== '') {
                    $criticas[] = $critica;
                }
            }

            $registros[] = [
                'numero_pedido' => is_numeric($pedido) ? (string) (int) $pedido : $pedido,
                'tipo_pedido' => self::texto($b['tipo_pedido'] ?? '', 40),
                'situacao_pedido' => self::texto($b['situacao_pedido'] ?? '', 60),
                'situacao_atendimento' => self::texto($b['situacao_atendimento'] ?? '', 60),
                'municipio' => self::texto($b['municipio'] ?? '', 100),
                'cliente_codigo' => $cliente,
                'cliente_nome' => self::texto($b['cliente_nome'] ?? '', 150),
                'nome_fantasia' => self::texto($b['nome_fantasia'] ?? '', 150),
                'setor_codigo' => self::codigo($b['setor_codigo'] ?? ''),
                'setor_descricao' => self::texto($b['setor_descricao'] ?? '', 100),
                'gerente_codigo' => self::codigo($b['gerente_codigo'] ?? ''),
                'valor_total' => self::numero($b['valor_total'] ?? ''),
                'volume_hl' => self::numero($b['volume_hl'] ?? ''),
                'produto_descricao' => self::texto($b['produto_descricao'] ?? '', 150) ?: null,
                'critica_fora_rota' => self::texto($b['critica_2'] ?? '', 150) ?: null,
                'criticas' => $criticas ? mb_substr(implode(' | ', $criticas), 0, 600) : null,
            ];
        }

        if ($ignorados) {
            $this->avisos[] = count($ignorados) . ' pedido(s) de REPOSIÇÃO ignorado(s)';
        }

        return $registros;
    }

    private function transformarClientes(array $brutos, array $setoresCadastrados): array
    {
        $clientes = [];

        foreach ($brutos as $b) {
            $codigo = self::codigo($b['codigo'] ?? '');
            if ($codigo === null) {
                continue;
            }

            $setor = self::codigo($b['setor_codigo'] ?? '');

            // O cliente pode aparecer em mais de um setor; prioriza um setor do Roteirizador.
            if (isset($clientes[$codigo])) {
                $atualCadastrado = in_array($clientes[$codigo]['setor_codigo'], $setoresCadastrados, true);
                $novoCadastrado = in_array($setor, $setoresCadastrados, true);
                if ($atualCadastrado || !$novoCadastrado) {
                    continue;
                }
            }

            $clientes[$codigo] = [
                'codigo' => $codigo,
                'razao_social' => self::texto($b['razao_social'] ?? '', 150),
                'nome_fantasia' => self::texto($b['nome_fantasia'] ?? '', 150),
                'setor_codigo' => $setor,
                'cidade' => self::texto($b['cidade'] ?? '', 100),
                'bairro' => self::texto($b['bairro'] ?? '', 100),
                'dia_visita' => self::texto($b['dia_visita'] ?? '', 40),
                'status_cliente' => self::texto($b['status_cliente'] ?? '', 10),
                'segmento' => self::texto($b['segmento'] ?? '', 60),
            ];
        }

        return array_values($clientes);
    }

    private function contarPorCliente(array $brutos, string $campoQuantidade): array
    {
        $contagem = [];

        foreach ($brutos as $b) {
            $codigo = self::codigo($b['cliente_codigo'] ?? '');
            if ($codigo !== null) {
                $contagem[$codigo] = ($contagem[$codigo] ?? 0) + 1;
            }
        }

        $registros = [];
        foreach ($contagem as $codigo => $quantidade) {
            $registros[] = ['cliente_codigo' => $codigo, $campoQuantidade => $quantidade];
        }

        return $registros;
    }

    private function transformarTopLine(array $brutos): array
    {
        $registros = [];

        foreach ($brutos as $b) {
            $codigo = self::codigo($b['cliente_codigo'] ?? '');
            if ($codigo === null || isset($registros[$codigo])) {
                continue;
            }

            $registros[$codigo] = [
                'cliente_codigo' => $codigo,
                'nome' => self::texto($b['nome'] ?? '', 150),
                'observacao' => self::texto($b['observacao'] ?? '', 150),
            ];
        }

        return array_values($registros);
    }

    private function transformarTravas(array $brutos): array
    {
        $registros = [];

        foreach ($brutos as $b) {
            $codigo = self::codigo($b['cliente_codigo'] ?? '');
            if ($codigo === null) {
                continue;
            }

            $condicao = self::texto($b['condicao_entrega'] ?? '', 255);
            $motivo = self::texto($b['motivo'] ?? '', 255);

            // Cliente repetido: junta as condições em um único registro.
            if (isset($registros[$codigo])) {
                $registros[$codigo]['condicao_entrega'] = self::juntar($registros[$codigo]['condicao_entrega'], $condicao);
                $registros[$codigo]['motivo'] = self::juntar($registros[$codigo]['motivo'], $motivo);
                continue;
            }

            $registros[$codigo] = [
                'cliente_codigo' => $codigo,
                'nome' => self::texto($b['nome'] ?? '', 150),
                'setor_codigo' => self::codigo($b['setor_codigo'] ?? ''),
                'condicao_entrega' => $condicao,
                'motivo' => $motivo,
            ];
        }

        return array_values($registros);
    }

    private function transformarTrocas(array $brutos): array
    {
        $registros = [];

        foreach ($brutos as $b) {
            $codigo = self::codigo($b['cliente_codigo'] ?? '');
            if ($codigo === null) {
                continue;
            }

            $solicitacao = self::texto($b['solicitacao'] ?? '', 30);

            $registros[] = [
                'cliente_codigo' => $codigo,
                'cliente_nome' => self::texto($b['cliente_nome'] ?? '', 150),
                'solicitacao' => is_numeric($solicitacao) ? (string) (int) $solicitacao : $solicitacao,
                'data_solicitacao' => self::data($b['data_solicitacao'] ?? ''),
                'status_solicitacao' => self::texto($b['status_solicitacao'] ?? '', 40),
                'nota_fiscal' => self::texto($b['nota_fiscal'] ?? '', 40) ?: null,
                'produto_codigo' => self::texto($b['produto_codigo'] ?? '', 30),
                'produto_descricao' => self::texto($b['produto_descricao'] ?? '', 150),
                'quantidade' => self::numero($b['quantidade'] ?? ''),
                'unidade' => self::texto($b['unidade'] ?? '', 10),
                'sistema_origem' => self::texto($b['sistema_origem'] ?? '', 40),
                'observacao' => self::texto($b['observacao'] ?? '', 255) ?: null,
                'setor_codigo' => self::codigo($b['setor_codigo'] ?? ''),
                'valor' => self::numero($b['valor'] ?? ''),
                'motorista_nome' => self::texto($b['motorista_nome'] ?? '', 60) ?: null,
                'ajudante1_nome' => self::texto($b['ajudante1_nome'] ?? '', 60) ?: null,
                'ajudante2_nome' => self::texto($b['ajudante2_nome'] ?? '', 60) ?: null,
            ];
        }

        return $registros;
    }

    /**
     * 03.05.09: soma a "Quantidade Hecto" de todas as linhas de cada setor
     * (equivale ao SOMASES por setor da planilha MATRIZ).
     */
    /**
     * 03.11.49.02: um registro por mapa (linhas sem placa são descartadas).
     */
    private function transformarFarolMapas(array $brutos): array
    {
        $registros = [];

        foreach ($brutos as $b) {
            $placa = strtoupper(self::texto($b['placa'] ?? '', 20));
            if ($placa === '') {
                continue;
            }

            $registros[] = [
                'data_entrega' => self::data($b['data_entrega'] ?? ''),
                'numero_mapa' => self::texto($b['numero_mapa'] ?? '', 20) ?: null,
                'veiculo' => self::texto($b['veiculo'] ?? '', 30) ?: null,
                'placa' => $placa,
                'carga' => self::texto($b['carga'] ?? '', 30),
                'entregas' => (int) round(self::numero($b['entregas'] ?? '')),
                'caixas' => round(self::numero($b['caixas'] ?? ''), 2),
                'peso' => round(self::numero($b['peso'] ?? ''), 2),
            ];
        }

        return $registros;
    }

    /**
     * Buffer do Cora para o Farol: um registro por pedido com o "Tipo buffer"
     * (PRESERVADO, REPROGRAMADO...) e o volume (HL) somado. Grava também as reposições;
     * o Farol considera só os REPROGRAMADO que não são reposição (FarolBufferModel).
     */
    private function transformarFarolBuffer(array $brutos): array
    {
        $pedidos = [];

        foreach ($brutos as $b) {
            $cliente = self::codigo($b['cliente_codigo'] ?? '');
            $pedido = self::texto($b['numero_pedido'] ?? '', 30);

            if ($cliente === null || $pedido === '') {
                continue;
            }

            $pedido = is_numeric($pedido) ? (string) (int) $pedido : $pedido;
            $pedidos[$pedido] ??= [
                'numero_pedido' => $pedido,
                'cliente_codigo' => $cliente,
                'nome_fantasia' => self::texto($b['nome_fantasia'] ?? '', 150) ?: null,
                'tipo_pedido' => self::texto($b['tipo_pedido'] ?? '', 40) ?: null,
                'tipo_buffer' => strtoupper(self::texto($b['tipo_buffer'] ?? '', 30)),
                'setor_codigo' => self::codigo($b['setor_codigo'] ?? ''),
                'setor_descricao' => self::texto($b['setor_descricao'] ?? '', 100) ?: null,
                'volume_hl' => 0.0,
            ];
            $pedidos[$pedido]['volume_hl'] += self::numero($b['volume_hl'] ?? '');
        }

        return array_values(array_map(function (array $p): array {
            $p['volume_hl'] = round($p['volume_hl'], 4);
            return $p;
        }, $pedidos));
    }

    private function somarVolumePorSetor(array $brutos): array
    {
        $volumes = [];

        foreach ($brutos as $b) {
            $setor = self::codigo($b['setor_codigo'] ?? '');
            if ($setor !== null) {
                $volumes[$setor] = ($volumes[$setor] ?? 0) + self::numero($b['volume_hl'] ?? '');
            }
        }

        $registros = [];
        foreach ($volumes as $setor => $volume) {
            $registros[] = ['setor_codigo' => $setor, 'volume_hl' => round($volume, 4)];
        }

        return $registros;
    }

    /**
     * 17.03: um registro por "Cod. ID" (vale a primeira linha de cada código).
     */
    private function transformarIndicadores(array $brutos): array
    {
        $registros = [];

        foreach ($brutos as $b) {
            $codigo = self::codigo($b['codigo'] ?? '');
            if ($codigo === null || isset($registros[$codigo])) {
                continue;
            }

            $registros[$codigo] = [
                'codigo' => $codigo,
                'descricao' => self::texto($b['descricao'] ?? '', 100),
                'valor' => self::numero($b['valor'] ?? ''),
            ];
        }

        return array_values($registros);
    }

    private function transformarNotasTroca(array $brutos): array
    {
        $registros = [];
        $ignoradas = [];

        foreach ($brutos as $b) {
            $cliente = self::codigo($b['cliente_codigo'] ?? '');
            $nota = self::codigo($b['nota'] ?? '');

            if ($cliente === null || $nota === null) {
                continue;
            }

            $operacao = self::codigo($b['operacao'] ?? '');
            if (in_array($operacao, self::OPERACOES_IGNORADAS_NOTAS_TROCA, true)) {
                $ignoradas[$operacao][$nota . '-' . ($b['serie'] ?? '')] = true;
                continue;
            }

            $registros[] = [
                'operacao' => $operacao,
                'data_operacao' => self::data($b['data_operacao'] ?? ''),
                'data_emissao' => self::data($b['data_emissao'] ?? ''),
                'nota' => $nota,
                'serie' => self::texto($b['serie'] ?? '', 10),
                'cliente_codigo' => $cliente,
                'cliente_nome' => self::texto($b['cliente_nome'] ?? '', 150),
                'produto_codigo' => self::texto($b['produto_codigo'] ?? '', 30),
                'produto_descricao' => self::texto($b['produto_descricao'] ?? '', 150),
                'unidade' => self::texto($b['unidade'] ?? '', 10),
                'quantidade' => self::numero($b['quantidade'] ?? ''),
                'valor_total' => self::numero($b['valor_total'] ?? ''),
                'mapa' => self::texto($b['mapa'] ?? '', 30) ?: null,
                'numero_pedido' => self::texto($b['numero_pedido'] ?? '', 30) ?: null,
                'status_nfe' => self::texto($b['status_nfe'] ?? '', 40),
                // "000000" = sem setor no relatório (usa o do cadastro 01.20.11 na consulta).
                'setor_codigo' => self::codigo($b['setor_codigo'] ?? ''),
            ];
        }

        if ($ignoradas) {
            ksort($ignoradas);
            $partes = [];
            foreach ($ignoradas as $operacao => $notas) {
                $partes[] = "operação {$operacao}: " . count($notas);
            }
            $this->avisos[] = 'nota(s) ignorada(s) - ' . implode(', ', $partes);
        }

        return $registros;
    }

    /**
     * Converte data dd/mm/aaaa, aaaa-mm-dd ou número de série do Excel em aaaa-mm-dd.
     */
    public static function data(string $valor): ?string
    {
        $valor = trim($valor);

        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $valor, $m)) {
            $valor = "{$m[3]}-{$m[2]}-{$m[1]}";
        } elseif (is_numeric($valor) && (float) $valor > 0) {
            // Série do Excel: dias desde 30/12/1899.
            $valor = gmdate('Y-m-d', (int) round(((float) $valor - 25569) * 86400));
        } else {
            $valor = substr($valor, 0, 10);
        }

        return data_valida($valor) ? $valor : null;
    }

    private static function juntar(string $atual, string $novo): string
    {
        if ($novo === '' || str_contains($atual, $novo)) {
            return $atual;
        }

        return mb_substr(trim($atual . ' / ' . $novo, ' /'), 0, 255);
    }

    /**
     * Normaliza um texto de cabeçalho/aba: minúsculo, sem acento, sem pontuação.
     */
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
        $texto = str_replace(['.', ':', '?', '/', '-', '_'], ' ', $texto);

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Converte números do Excel ("0.31", "7.0E-2") e do padrão brasileiro ("1.234,56").
     */
    public static function numero(string $valor): float
    {
        $valor = trim(str_replace(['R$', ' '], '', $valor));

        if ($valor === '') {
            return 0.0;
        }

        if (str_contains($valor, ',')) {
            $valor = str_replace(',', '.', str_replace('.', '', $valor));
        }

        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    /**
     * Converte um código (cliente, setor, mesa) em inteiro positivo, ou null.
     */
    public static function codigo(string $valor): ?int
    {
        $valor = str_replace(',', '.', trim($valor));

        if ($valor === '' || !is_numeric($valor)) {
            return null;
        }

        $codigo = (int) round((float) $valor);

        return $codigo > 0 ? $codigo : null;
    }

    private static function texto(string $valor, int $tamanho): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $valor)), 0, $tamanho);
    }
}
