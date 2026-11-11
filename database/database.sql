-- Script único de criação do banco de dados — Roteirizador COBEB.
-- Padrão COBEB: charset utf8mb4 / collation utf8mb4_unicode_ci (Manual de Desenvolvimento, Seção 3 e 10.2).

-- ---------------------------------------------------------------------------
-- Usuários e perfis de acesso
-- perfil: admin (tudo), logistica (importa dados e decide a Crítica GOD),
-- comercial (consulta a Crítica e registra prioridades apenas da própria Mesa).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL COMMENT 'Hash gerado com password_hash() - Bcrypt',
    perfil ENUM('admin', 'logistica', 'comercial') NOT NULL DEFAULT 'comercial',
    revenda VARCHAR(2) NULL COMMENT 'Revenda da Logística (PM, LP, AB); NULL = todas. Comercial: revenda da Mesa',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O primeiro administrador é criado pela tela /primeiro-acesso,
-- disponível somente enquanto esta tabela estiver vazia.

-- Links de "Esqueci minha senha": guarda só o hash SHA-256 do token enviado por e-mail.
-- Uso único, validade de 1 hora; um novo pedido apaga os anteriores do usuário.
CREATE TABLE IF NOT EXISTS redefinicoes_senha (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuarios_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL COMMENT 'hash(sha256) do token; o token puro só existe no e-mail',
    expira_em DATETIME NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_redefinicoes_senha_token (token_hash),
    CONSTRAINT fk_redefinicoes_senha_usuarios FOREIGN KEY (usuarios_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Abas (telas) liberadas para cada usuário, dentro do que o perfil permite.
-- Administradores têm sempre todas as abas e não precisam de registros aqui.
CREATE TABLE IF NOT EXISTS usuarios_abas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuarios_id INT UNSIGNED NOT NULL,
    aba VARCHAR(30) NOT NULL,
    UNIQUE KEY uk_usuarios_abas (usuarios_id, aba),
    CONSTRAINT fk_usuarios_abas_usuarios FOREIGN KEY (usuarios_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mesas (exibidas como "Sala") de cada usuário do Comercial; obrigatória ao menos uma.
-- O usuário trabalha uma Mesa por vez, escolhida no menu superior.
CREATE TABLE IF NOT EXISTS usuarios_mesas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuarios_id INT UNSIGNED NOT NULL,
    mesa SMALLINT UNSIGNED NOT NULL COMMENT 'Código do gerente de vendas',
    UNIQUE KEY uk_usuarios_mesas (usuarios_id, mesa),
    CONSTRAINT fk_usuarios_mesas_usuarios FOREIGN KEY (usuarios_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Mesas (código do gerente de vendas) e as revendas que cada uma atende:
-- um registro por Mesa + revenda (a Mesa 23 atende LP e AB).
-- Revendas: PM, LP e AB (mapeamento dos códigos em App\Helpers\Revenda).
-- Códigos de setor são únicos entre as revendas; cada setor tem a sua revenda.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mesas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo SMALLINT UNSIGNED NOT NULL,
    revenda VARCHAR(2) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_mesas_codigo_revenda (codigo, revenda),
    INDEX idx_mesas_revenda (revenda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO mesas (codigo, revenda) VALUES
    (52, 'PM'), (53, 'PM'), (54, 'PM'),
    (22, 'LP'), (23, 'LP'),
    (23, 'AB'), (24, 'AB');

-- ---------------------------------------------------------------------------
-- Setores e limites de prioridade por Mesa (antiga planilha "Prioridade")
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS setores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo SMALLINT UNSIGNED NOT NULL UNIQUE,
    revenda VARCHAR(2) NOT NULL COMMENT 'Revenda do setor (a Mesa pode atender mais de uma)',
    mesa SMALLINT UNSIGNED NOT NULL COMMENT 'Código do gerente de vendas',
    limite_clientes TINYINT UNSIGNED NOT NULL DEFAULT 5,
    limite_hl DECIMAL(8,2) NOT NULL DEFAULT 15.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setores_mesa (mesa),
    INDEX idx_setores_revenda (revenda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO setores (codigo, revenda, mesa, limite_clientes, limite_hl) VALUES
    (520, 'PM', 52, 5, 15), (521, 'PM', 52, 5, 15), (522, 'PM', 52, 5, 15), (523, 'PM', 52, 5, 15),
    (524, 'PM', 52, 5, 15), (525, 'PM', 52, 5, 15), (526, 'PM', 52, 5, 15), (527, 'PM', 52, 5, 15),
    (530, 'PM', 53, 5, 15), (531, 'PM', 53, 5, 15), (532, 'PM', 53, 5, 15), (533, 'PM', 53, 5, 15),
    (534, 'PM', 53, 5, 15), (535, 'PM', 53, 5, 15), (536, 'PM', 53, 5, 15), (537, 'PM', 53, 5, 15),
    (540, 'PM', 54, 5, 19), (541, 'PM', 54, 5, 19), (542, 'PM', 54, 5, 19), (543, 'PM', 54, 5, 19),
    -- LP: Mesa 22 e Mesa 23 (a 23 também atende AB)
    (221, 'LP', 22, 5, 15), (222, 'LP', 22, 5, 15), (224, 'LP', 22, 5, 15), (225, 'LP', 22, 5, 15),
    (226, 'LP', 22, 5, 15), (227, 'LP', 22, 5, 15), (228, 'LP', 22, 5, 15), (229, 'LP', 22, 5, 15),
    (223, 'LP', 23, 5, 19), (230, 'LP', 23, 5, 19), (231, 'LP', 23, 5, 19), (232, 'LP', 23, 5, 19),
    -- AB: Mesa 23 e Mesa 24
    (233, 'AB', 23, 5, 19),
    (240, 'AB', 24, 5, 15), (241, 'AB', 24, 5, 15), (242, 'AB', 24, 5, 15), (243, 'AB', 24, 5, 15),
    (244, 'AB', 24, 5, 15), (245, 'AB', 24, 5, 15), (246, 'AB', 24, 5, 15);

-- ---------------------------------------------------------------------------
-- Registro das importações de planilhas
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS importacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(30) NOT NULL,
    revenda VARCHAR(2) NOT NULL COMMENT 'Cada importação substitui apenas os dados da sua revenda',
    data_referencia DATE NULL COMMENT 'Data de entrega (apenas para pedidos do Cora)',
    arquivo_nome VARCHAR(255) NOT NULL,
    total_linhas INT UNSIGNED NOT NULL DEFAULT 0,
    usuarios_id INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    excluida_em DATETIME NULL COMMENT 'Exclusão lógica: os dados importados foram apagados',
    excluida_por_usuarios_id INT UNSIGNED NULL,
    INDEX idx_importacoes_tipo (tipo, revenda, criado_em),
    CONSTRAINT fk_importacoes_usuarios FOREIGN KEY (usuarios_id) REFERENCES usuarios (id),
    CONSTRAINT fk_importacoes_excluida_por FOREIGN KEY (excluida_por_usuarios_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Pedidos do Cora aguardando roteirização (um registro por item do export)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedidos_cora (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_referencia DATE NOT NULL COMMENT 'Data de entrega',
    revenda VARCHAR(2) NOT NULL COMMENT 'Pelo Cód. unidade venda',
    numero_pedido VARCHAR(30) NOT NULL,
    tipo_pedido VARCHAR(40) NULL,
    situacao_pedido VARCHAR(60) NULL,
    situacao_atendimento VARCHAR(60) NULL,
    municipio VARCHAR(100) NULL,
    cliente_codigo INT UNSIGNED NOT NULL,
    cliente_nome VARCHAR(150) NULL,
    nome_fantasia VARCHAR(150) NULL,
    setor_codigo SMALLINT UNSIGNED NULL,
    setor_descricao VARCHAR(100) NULL,
    gerente_codigo SMALLINT UNSIGNED NULL COMMENT 'Mesa',
    valor_total DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Valor total do pedido (repetido em cada item)',
    volume_hl DECIMAL(12,4) NOT NULL DEFAULT 0 COMMENT 'Volume hectolitro do item',
    produto_descricao VARCHAR(150) NULL,
    critica_fora_rota VARCHAR(150) NULL COMMENT 'Coluna "Críticas 2" do Cora',
    criticas VARCHAR(600) NULL COMMENT 'Críticas 1 a 6 concatenadas',
    INDEX idx_pedidos_cora_cliente (data_referencia, revenda, cliente_codigo),
    INDEX idx_pedidos_cora_mesa (data_referencia, gerente_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Cadastros de apoio (substituídos integralmente, por revenda, a cada importação)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    codigo INT UNSIGNED NOT NULL,
    razao_social VARCHAR(150) NULL,
    nome_fantasia VARCHAR(150) NULL,
    setor_codigo SMALLINT UNSIGNED NULL,
    setor_descricao VARCHAR(100) NULL COMMENT 'Descricao Setor do 01.20.11 (setor do buffer no Farol)',
    cidade VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    dia_visita VARCHAR(40) NULL,
    status_cliente VARCHAR(10) NULL,
    segmento VARCHAR(60) NULL,
    UNIQUE KEY uk_clientes_revenda_codigo (revenda, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Instalações anteriores à descrição do setor no cadastro de clientes (versão 1.8.0).
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS setor_descricao VARCHAR(100) NULL COMMENT 'Descricao Setor do 01.20.11 (setor do buffer no Farol)' AFTER setor_codigo;

CREATE TABLE IF NOT EXISTS historico_compras (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    cliente_codigo INT UNSIGNED NOT NULL,
    quantidade INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Linhas de nota no relatório 03.02.37',
    UNIQUE KEY uk_historico_compras_cliente (revenda, cliente_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes_black_list (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    cliente_codigo INT UNSIGNED NOT NULL,
    ocorrencias INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uk_clientes_black_list_cliente (revenda, cliente_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes_top_line (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    cliente_codigo INT UNSIGNED NOT NULL,
    nome VARCHAR(150) NULL,
    observacao VARCHAR(150) NULL,
    UNIQUE KEY uk_clientes_top_line_cliente (revenda, cliente_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes_travas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    cliente_codigo INT UNSIGNED NOT NULL,
    nome VARCHAR(150) NULL,
    setor_codigo SMALLINT UNSIGNED NULL,
    condicao_entrega VARCHAR(255) NULL,
    motivo VARCHAR(255) NULL,
    UNIQUE KEY uk_clientes_travas_cliente (revenda, cliente_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solicitações de troca/reposição (Promax 03.18.05), um registro por produto.
-- Revenda pelo UNB; Mesa do cliente: setor_codigo -> setores.mesa.
CREATE TABLE IF NOT EXISTS trocas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    cliente_codigo INT UNSIGNED NOT NULL,
    cliente_nome VARCHAR(150) NULL,
    solicitacao VARCHAR(30) NULL COMMENT 'Número da solicitação de reposição',
    data_solicitacao DATE NULL,
    status_solicitacao VARCHAR(40) NULL,
    nota_fiscal VARCHAR(40) NULL COMMENT 'Nota Fiscal/Serie',
    produto_codigo VARCHAR(30) NULL,
    produto_descricao VARCHAR(150) NULL,
    quantidade DECIMAL(12,4) NOT NULL DEFAULT 0,
    unidade VARCHAR(10) NULL COMMENT 'U.M.',
    sistema_origem VARCHAR(40) NULL,
    observacao VARCHAR(255) NULL,
    setor_codigo SMALLINT UNSIGNED NULL COMMENT 'Setor Venda',
    valor DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Valor (R$) do item, usado na Verba de troca',
    motorista_nome VARCHAR(60) NULL COMMENT 'Nome Motorista',
    ajudante1_nome VARCHAR(60) NULL COMMENT '1º "Nome Ajudante - Entrega"',
    ajudante2_nome VARCHAR(60) NULL COMMENT '2º "Nome Ajudante - Entrega"',
    INDEX idx_trocas_cliente (revenda, cliente_codigo, data_solicitacao),
    INDEX idx_trocas_setor (revenda, setor_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Justificativa da Logística por solicitação de troca (aba Aprovação troca), exibida ao
-- Comercial na aba Troca. Fica fora da tabela trocas para sobreviver às reimportações do 03.18.05.
CREATE TABLE IF NOT EXISTS trocas_justificativas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    solicitacao VARCHAR(30) NOT NULL COMMENT 'Número da solicitação de reposição',
    cliente_codigo INT UNSIGNED NOT NULL,
    justificativa VARCHAR(500) NOT NULL,
    usuarios_id INT UNSIGNED NOT NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_trocas_justificativas (revenda, solicitacao, cliente_codigo),
    CONSTRAINT fk_trocas_justificativas_usuarios FOREIGN KEY (usuarios_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verba de troca extra concedida pelo Administrador a um setor em um mês (aba Aprovação troca).
-- Soma na verba do setor (HL x 10% + extra). Um valor por revenda + setor + mês; fora de
-- trocas/vendas_setores para sobreviver às reimportações.
CREATE TABLE IF NOT EXISTS verbas_extras (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    setor_codigo SMALLINT UNSIGNED NOT NULL,
    mes CHAR(7) NOT NULL COMMENT 'AAAA-MM (mês da Data Solicitação das trocas)',
    valor DECIMAL(14,2) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    usuarios_id INT UNSIGNED NOT NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_verbas_extras (revenda, setor_codigo, mes),
    CONSTRAINT fk_verbas_extras_usuarios FOREIGN KEY (usuarios_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volume vendido (HL) por setor (Promax 03.05.09), somado na importação.
-- Base da verba de troca do setor (aba Aprovação troca).
CREATE TABLE IF NOT EXISTS vendas_setores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    setor_codigo SMALLINT UNSIGNED NOT NULL,
    volume_hl DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'Soma de "Quantidade Hecto"',
    UNIQUE KEY uk_vendas_setores (revenda, setor_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Farol (painel do dia por revenda), substitui a "PLANILHA FAROL".
-- Mapas roteirizados (Promax 03.11.49.02), um por linha do relatório. O tipo do mapa
-- (FF, Spot, Recarga) é derivado da placa e da carga em App\Helpers\Farol.
CREATE TABLE IF NOT EXISTS farol_mapas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL COMMENT 'Pela coluna UNB',
    data_entrega DATE NULL,
    numero_mapa VARCHAR(20) NULL,
    veiculo VARCHAR(30) NULL,
    placa VARCHAR(20) NOT NULL,
    carga VARCHAR(30) NOT NULL COMMENT 'Fixa ou Freteiro',
    entregas INT UNSIGNED NOT NULL DEFAULT 0,
    caixas DECIMAL(12,2) NOT NULL DEFAULT 0,
    peso DECIMAL(12,2) NOT NULL DEFAULT 0,
    INDEX idx_farol_mapas_revenda (revenda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pedidos do Cora (pedidos_roteirizacao), volume somado por pedido e situação.
-- Farol: roteirizado = ENVIADO_HUB_FATURAMENTO + AGUARDANDO_ROTEIRIZACAO; buffer = REPROGRAMADO.
CREATE TABLE IF NOT EXISTS farol_buffer (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL COMMENT 'Pelo CodigoFilialVenda',
    numero_pedido VARCHAR(30) NOT NULL,
    cliente_codigo INT UNSIGNED NOT NULL,
    nome_fantasia VARCHAR(150) NULL,
    tipo_pedido VARCHAR(40) NULL,
    situacao_pedido VARCHAR(40) NULL COMMENT 'SituacaoPedido (coluna K)',
    tipo_buffer VARCHAR(30) NOT NULL,
    setor_codigo SMALLINT UNSIGNED NULL,
    setor_descricao VARCHAR(100) NULL,
    volume_hl DECIMAL(12,4) NOT NULL DEFAULT 0,
    INDEX idx_farol_buffer_revenda (revenda, tipo_buffer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Instalações criadas antes do setor no buffer (versão 1.6.0).
ALTER TABLE farol_buffer ADD COLUMN IF NOT EXISTS setor_codigo SMALLINT UNSIGNED NULL AFTER tipo_buffer;
ALTER TABLE farol_buffer ADD COLUMN IF NOT EXISTS setor_descricao VARCHAR(100) NULL AFTER setor_codigo;
-- Novo arquivo do Cora com a situação do pedido (versão 1.8.0).
ALTER TABLE farol_buffer ADD COLUMN IF NOT EXISTS situacao_pedido VARCHAR(40) NULL COMMENT 'SituacaoPedido (coluna K)' AFTER tipo_pedido;

-- Dados digitados no Farol por revenda (não vêm de relatório): volume vendido do
-- Promax 03.01.47.01 (Quebra 1: Geral) e observações dos mapas Spot e da recarga.
CREATE TABLE IF NOT EXISTS farol_revendas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    volume_vendido_hl DECIMAL(12,2) NULL,
    observacao_spot VARCHAR(150) NULL,
    observacao_recarga VARCHAR(150) NULL,
    mapas_ff_ajuste SMALLINT UNSIGNED NULL COMMENT 'Mapas FF informados (NULL = do 03.11.49.02)',
    mapas_spot_ajuste SMALLINT UNSIGNED NULL COMMENT 'Mapas Spot informados (NULL = do 03.11.49.02)',
    recargas_ajuste SMALLINT UNSIGNED NULL COMMENT 'Recargas informadas (NULL = do 03.11.49.02)',
    ajuste_data DATE NULL COMMENT 'Entrega dos mapas quando os ajustes foram salvos',
    usuarios_id INT UNSIGNED NOT NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_farol_revendas (revenda),
    CONSTRAINT fk_farol_revendas_usuarios FOREIGN KEY (usuarios_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajuste manual das quantidades de mapas FF, Spot e recarga (valem só para a entrega de ajuste_data).
ALTER TABLE farol_revendas ADD COLUMN IF NOT EXISTS mapas_ff_ajuste SMALLINT UNSIGNED NULL COMMENT 'Mapas FF informados (NULL = do 03.11.49.02)' AFTER observacao_recarga;
ALTER TABLE farol_revendas ADD COLUMN IF NOT EXISTS mapas_spot_ajuste SMALLINT UNSIGNED NULL COMMENT 'Mapas Spot informados (NULL = do 03.11.49.02)' AFTER mapas_ff_ajuste;
ALTER TABLE farol_revendas ADD COLUMN IF NOT EXISTS recargas_ajuste SMALLINT UNSIGNED NULL COMMENT 'Recargas informadas (NULL = do 03.11.49.02)' AFTER mapas_spot_ajuste;
ALTER TABLE farol_revendas ADD COLUMN IF NOT EXISTS ajuste_data DATE NULL COMMENT 'Entrega dos mapas quando os ajustes foram salvos' AFTER recargas_ajuste;

-- CTE (substitui a planilha "Ct'e PM"): uma linha por placa, com o 1º mapa da placa
-- no 03.11.49.02 (enviado pela aba CTE ou pela Tirinha). Valor do 03.03.12 (colado);
-- Ct'e, motorista e código são digitados na tela. Cada importação substitui a revenda,
-- mantendo o que foi digitado nos mapas que continuam no arquivo.
CREATE TABLE IF NOT EXISTS cte_mapas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL COMMENT 'Pela coluna UNB',
    data_entrega DATE NULL,
    numero_mapa VARCHAR(20) NOT NULL,
    placa VARCHAR(20) NOT NULL,
    motorista_promax INT UNSIGNED NULL COMMENT 'Código do motorista no 03.11.49.02',
    entregas INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Coluna PEDIDO da planilha',
    peso DECIMAL(12,2) NOT NULL DEFAULT 0,
    cidades VARCHAR(255) NULL COMMENT 'Cidades +Entregas sem as quantidades',
    valor DECIMAL(14,2) NULL COMMENT 'Valor do mapa no 03.03.12',
    cte_numero VARCHAR(30) NULL,
    motorista_nome VARCHAR(100) NULL,
    motorista_codigo VARCHAR(20) NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cte_mapas (revenda, numero_mapa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de custos de frete do CTE por revenda (aba "TABELA CUSTOS FRETES"):
-- cidades do mapa, na ordem do 03.11.49.02 ("NOVA SERRANA / IGARATINGA") => valor e código.
CREATE TABLE IF NOT EXISTS cte_fretes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    cidades VARCHAR(255) NOT NULL COMMENT 'Maiúsculas, sem acento, separadas por " / "',
    valor DECIMAL(10,2) NOT NULL,
    codigo VARCHAR(20) NOT NULL COMMENT 'Código da cidade no CT-e',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cte_fretes (revenda, cidades)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fretes de PM copiados da planilha "Ct'e PM" (rotas repetidas: vale a 1ª, como no PROCV).
INSERT IGNORE INTO cte_fretes (revenda, cidades, valor, codigo) VALUES
    ('PM', 'PITANGUI / CONCEICAO DO PARA', 1300.00, '2557'),
    ('PM', 'NOVA SERRANA', 1303.56, '2743'),
    ('PM', 'ITAUNA / PARA DE MINAS', 1249.25, '2742'),
    ('PM', 'NOVA SERRANA / IGARATINGA / PARA DE MINAS', 1303.56, '2743'),
    ('PM', 'ITAUNA', 1249.25, '2742'),
    ('PM', 'PARA DE MINAS / JUATUBA / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'PARA DE MINAS / ONCA DE PITANGUI', 1412.19, '2557'),
    ('PM', 'PARA DE MINAS', 1053.71, '2556'),
    ('PM', 'ARAUJOS / PERDIGAO', 1412.19, '2509'),
    ('PM', 'MATEUS LEME', 1303.56, '2571'),
    ('PM', 'MATEUS LEME / JUATUBA', 1303.56, '2571'),
    ('PM', 'ITATIAIUCU', 1303.56, '2605'),
    ('PM', 'ITAUNA / ITATIAIUCU / CARMO DO CAJURU', 1303.56, '2605'),
    ('PM', 'ARAUJOS', 1412.19, '2509'),
    ('PM', 'PERDIGAO / ARAUJOS', 1412.19, '2509'),
    ('PM', 'PERDIGAO', 1412.19, '2744'),
    ('PM', 'PITANGUI', 1412.19, '2557'),
    ('PM', 'PITANGUI / ONCA DE PITANGUI / CONCEICAO DO PARA', 1412.19, '2557'),
    ('PM', 'CONCEICAO DO PARA / PITANGUI', 1412.19, '2557'),
    ('PM', 'PARA DE MINAS / PITANGUI', 1412.19, '2557'),
    ('PM', 'NOVA SERRANA / ARAUJOS / PARA DE MINAS / PERDIGAO', 1412.19, '2509'),
    ('PM', 'SAO GONCALO DO PARA', 1412.19, '2746'),
    ('PM', 'MARAVILHAS', 1357.88, '2532'),
    ('PM', 'PAPAGAIOS / MARAVILHAS', 1412.19, '2534'),
    ('PM', 'FLORESTAL / PARA DE MINAS', 1173.20, '2554'),
    ('PM', 'JUATUBA', 597.47, '2568'),
    ('PM', 'JUATUBA / FLORESTAL / PARA DE MINAS', 597.47, '2568'),
    ('PM', 'JUATUBA / MATEUS LEME / FLORESTAL', 1303.56, '2571'),
    ('PM', 'PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'ITAUNA / IGARATINGA', 1249.25, '2742'),
    ('PM', 'PARA DE MINAS / PEQUI / SAO JOSE DA VARGINHA', 1129.75, '2536'),
    ('PM', 'PARA DE MINAS / ITAUNA', 1249.25, '2742'),
    ('PM', 'IGARATINGA / PARA DE MINAS', 1173.20, '2741'),
    ('PM', 'NOVA SERRANA / SAO GONCALO DO PARA', 1303.56, '2743'),
    ('PM', 'SAO GONCALO DO PARA / IGARATINGA', 1412.19, '2746'),
    ('PM', 'PARA DE MINAS / IGARATINGA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'MATEUS LEME / JUATUBA / PARA DE MINAS', 1303.56, '2571'),
    ('PM', 'PAPAGAIOS / MARAVILHAS / PARA DE MINAS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / PERDIGAO / IGARATINGA', 1412.19, '2744'),
    ('PM', 'LEANDRO FERREIRA / NOVA SERRANA', 1357.88, '2515'),
    ('PM', 'NOVA SERRANA / CONCEICAO DO PARA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'PEQUI / PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'SAO JOSE DA VARGINHA / PEQUI', 1129.75, '2536'),
    ('PM', 'FLORESTAL / JUATUBA / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'ITATIAIUCU / RIO MANSO', 1303.56, '2605'),
    ('PM', 'ITATIAIUCU / ITAUNA', 1303.56, '2605'),
    ('PM', 'PARA DE MINAS / SAO JOSE DA VARGINHA', 1075.44, '2558'),
    ('PM', 'ITAUNA / CARMO DO CAJURU / ITATIAIUCU', 1303.56, '2605'),
    ('PM', 'JUATUBA / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'NOVA SERRANA / PERDIGAO', 1412.19, '2744'),
    ('PM', 'MARAVILHAS / PEQUI / SAO JOSE DA VARGINHA', 1357.88, '2532'),
    ('PM', 'IGARATINGA', 1173.20, '2741'),
    ('PM', 'NOVA SERRANA / ARAUJOS / PERDIGAO', 1412.19, '2509'),
    ('PM', 'IGARATINGA / PARA DE MINAS / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'PAPAGAIOS / PEQUI', 1412.19, '2534'),
    ('PM', 'ITAUNA / ITATIAIUCU', 1303.56, '2605'),
    ('PM', 'JUATUBA / PARA DE MINAS / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'SAO JOSE DA VARGINHA / PEQUI / MARAVILHAS', 1357.88, '2532'),
    ('PM', 'PARA DE MINAS / SAO JOSE DA VARGINHA / PEQUI', 1129.75, '2536'),
    ('PM', 'PARA DE MINAS / FLORESTAL', 1173.20, '2554'),
    ('PM', 'ITAUNA / JUATUBA / MATEUS LEME', 1249.25, '2742'),
    ('PM', 'NOVA SERRANA / IGARATINGA', 1303.56, '2743'),
    ('PM', 'NOVA SERRANA / PARA DE MINAS', 1303.56, '2743'),
    ('PM', 'JUATUBA / MATEUS LEME / PARA DE MINAS', 1303.56, '2571'),
    ('PM', 'PERDIGAO / NOVA SERRANA', 1412.19, '2744'),
    ('PM', 'FLORESTAL / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'MARAVILHAS / PAPAGAIOS / PARA DE MINAS', 1412.19, '2534'),
    ('PM', 'SAO GONCALO DO PARA / IGARATINGA / PARA DE MINAS', 1412.19, '2746'),
    ('PM', 'CONCEICAO DO PARA / ONCA DE PITANGUI / PITANGUI', 1412.19, '2557'),
    ('PM', 'PAPAGAIOS / MARAVILHAS / PEQUI / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'MATEUS LEME / JUATUBA / FLORESTAL', 1303.56, '2571'),
    ('PM', 'MARAVILHAS / PARA DE MINAS / PEQUI / SAO JOSE DA VARGINHA', 1357.88, '2532'),
    ('PM', 'PERDIGAO / NOVA SERRANA / ARAUJOS', 1412.19, '2509'),
    ('PM', 'NOVA SERRANA / ARAUJOS', 1412.19, '2509'),
    ('PM', 'FLORESTAL', 1173.20, '2554'),
    ('PM', 'IGARATINGA / NOVA SERRANA / PARA DE MINAS / SAO GONCALO DO PARA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / IGARATINGA', 1173.20, '2741'),
    ('PM', 'PAPAGAIOS / PEQUI / MARAVILHAS', 1412.19, '2534'),
    ('PM', 'SAO JOSE DA VARGINHA / PARA DE MINAS', 1075.44, '2558'),
    ('PM', 'PEQUI / SAO JOSE DA VARGINHA / PARA DE MINAS', 1129.75, '2536'),
    ('PM', 'NOVA SERRANA / LEANDRO FERREIRA', 1357.88, '2515'),
    ('PM', 'PARA DE MINAS / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / MATEUS LEME / JUATUBA', 1303.56, '2571'),
    ('PM', 'ITAUNA / CARMO DO CAJURU / PARA DE MINAS / ITATIAIUCU', 1303.56, '2605'),
    ('PM', 'ONCA DE PITANGUI / PITANGUI / CONCEICAO DO PARA', 1412.19, '2557'),
    ('PM', 'MATEUS LEME / FLORESTAL', 1303.56, '2571'),
    ('PM', 'PITANGUI / PARA DE MINAS', 1412.19, '2557'),
    ('PM', 'MARAVILHAS / PEQUI', 1357.88, '2532'),
    ('PM', 'PAPAGAIOS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'MARAVILHAS / PAPAGAIOS / PARA DE MINAS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / IGARATINGA / SAO GONCALO DO PARA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'IGARATINGA / SAO GONCALO DO PARA', 1412.19, '2746'),
    ('PM', 'MATEUS LEME / JUATUBA / PARA DE MINAS / FLORESTAL', 1303.56, '2571'),
    ('PM', 'JUATUBA / PARA DE MINAS / FLORESTAL / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'PAPAGAIOS / PEQUI / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / IGARATINGA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'MATEUS LEME / ITAUNA / PARA DE MINAS', 1249.25, '2742'),
    ('PM', 'CONCEICAO DO PARA / ONCA DE PITANGUI', 1412.19, '2557'),
    ('PM', 'PITANGUI / ONCA DE PITANGUI / PARA DE MINAS', 1412.19, '2557'),
    ('PM', 'CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'PITANGUI / ONCA DE PITANGUI', 1412.19, '2557'),
    ('PM', 'ONCA DE PITANGUI / PITANGUI', 1412.19, '2557'),
    ('PM', 'MARAVILHAS / PEQUI / SAO JOSE DA VARGINHA / PARA DE MINAS', 1357.88, '2532'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA / PARA DE MINAS / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / NOVA SERRANA / SAO GONCALO DO PARA', 1303.56, '2743'),
    ('PM', 'IGARATINGA / PARA DE MINAS / NOVA SERRANA', 1303.56, '2743'),
    ('PM', 'MARAVILHAS / PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'PEQUI / SAO JOSE DA VARGINHA / MARAVILHAS', 1357.88, '2532'),
    ('PM', 'JUATUBA / ITAUNA / MATEUS LEME', 1249.25, '2742'),
    ('PM', 'PITANGUI / CONCEICAO DO PARA / PARA DE MINAS', 1412.19, '2557'),
    ('PM', 'PAPAGAIOS / PARA DE MINAS', 1412.19, '2534'),
    ('PM', 'ITAUNA / CARMO DO CAJURU', 1249.25, '2742'),
    ('PM', 'JUATUBA / MATEUS LEME / FLORESTAL / PARA DE MINAS', 1303.56, '2571'),
    ('PM', 'PAPAGAIOS / PARA DE MINAS / PEQUI', 1412.19, '2534'),
    ('PM', 'PAPAGAIOS / PARA DE MINAS / MARAVILHAS / PEQUI', 1412.19, '2534'),
    ('PM', 'PAPAGAIOS / MARAVILHAS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / IGARATINGA / PARA DE MINAS / SAO GONCALO DO PARA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA / NOVA SERRANA', 1629.45, '2739'),
    ('PM', 'PEQUI / PAPAGAIOS / MARAVILHAS', 1412.19, '2534'),
    ('PM', 'SAO JOSE DA VARGINHA / PEQUI / PARA DE MINAS', 1129.75, '2536'),
    ('PM', 'PARA DE MINAS / CONCEICAO DO PARA / PITANGUI', 1412.19, '2557'),
    ('PM', 'ITATIAIUCU / ITAUNA / RIO MANSO', 1303.56, '2605'),
    ('PM', 'MATEUS LEME / PARA DE MINAS', 1303.56, '2571'),
    ('PM', 'MARAVILHAS / PARA DE MINAS', 1357.88, '2532'),
    ('PM', 'JUATUBA / FLORESTAL', 597.47, '2568'),
    ('PM', 'PAPAGAIOS / MARAVILHAS / PEQUI', 1412.19, '2534'),
    ('PM', 'ONCA DE PITANGUI / PITANGUI / PARA DE MINAS', 1412.19, '2557'),
    ('PM', 'PITANGUI / CONCEICAO DO PARA / ONCA DE PITANGUI', 1412.19, '2557'),
    ('PM', 'MARAVILHAS / PARA DE MINAS / SAO JOSE DA VARGINHA', 1357.88, '2532'),
    ('PM', 'CONCEICAO DO PARA / IGARATINGA / PARA DE MINAS / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'MARAVILHAS / PAPAGAIOS / PEQUI', 1412.19, '2534'),
    ('PM', 'IGARATINGA / PARA DE MINAS / NOVA SERRANA / SAO GONCALO DO PARA', 1303.56, '2743'),
    ('PM', 'ITAUNA / ITATIAIUCU / PARA DE MINAS', 1303.56, '2605'),
    ('PM', 'PEQUI / PAPAGAIOS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'PARA DE MINAS / PITANGUI / CONCEICAO DO PARA', 1412.19, '2557'),
    ('PM', 'JUATUBA / FLORESTAL / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'NOVA SERRANA / IGARATINGA / PARA DE MINAS / PERDIGAO', 1412.19, '2744'),
    ('PM', 'ONCA DE PITANGUI / PARA DE MINAS / PITANGUI', 1412.19, '2557'),
    ('PM', 'FLORESTAL / JUATUBA', 597.47, '2568'),
    ('PM', 'PARA DE MINAS / JUATUBA / MATEUS LEME / FLORESTAL', 1303.56, '2571'),
    ('PM', 'PEQUI / SAO JOSE DA VARGINHA', 1129.75, '2536'),
    ('PM', 'PAPAGAIOS / PARA DE MINAS / SAO JOSE DA VARGINHA / PEQUI / MARAVILHAS', 1412.19, '2534'),
    ('PM', 'PERDIGAO / PARA DE MINAS', 1412.19, '2744'),
    ('PM', 'PARA DE MINAS / MARAVILHAS / PEQUI', 1357.88, '2532'),
    ('PM', 'PARA DE MINAS / PITANGUI / ONCA DE PITANGUI', 1412.19, '2557'),
    ('PM', 'PARA DE MINAS / FLORESTAL / JUATUBA / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'SAO JOSE DA VARGINHA / PAPAGAIOS / PARA DE MINAS', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / SAO GONCALO DO PARA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'MATEUS LEME / FLORESTAL / ITAUNA', 1249.25, '2742'),
    ('PM', 'PARA DE MINAS / IGARATINGA / NOVA SERRANA / SAO GONCALO DO PARA', 1303.56, '2743'),
    ('PM', 'SAO JOSE DA VARGINHA', 1075.44, '2558'),
    ('PM', 'PAPAGAIOS / PARA DE MINAS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'MARAVILHAS / PAPAGAIOS / PEQUI / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'JUATUBA / PARA DE MINAS', 597.47, '2568'),
    ('PM', 'SAO GONCALO DO PARA / PARA DE MINAS', 1412.19, '2746'),
    ('PM', 'NOVA SERRANA / ITAUNA', 1249.25, '2742'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA / NOVA SERRANA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'MATEUS LEME / ITAUNA', 1249.25, '2742'),
    ('PM', 'MATEUS LEME / PARA DE MINAS / JUATUBA', 1303.56, '2571'),
    ('PM', 'PERDIGAO / PARA DE MINAS / NOVA SERRANA', 1412.19, '2744'),
    ('PM', 'ARAUJOS / NOVA SERRANA', 1412.19, '2509'),
    ('PM', 'ITAUNA / CARMO DO CAJURU / PARA DE MINAS', 1249.25, '2742'),
    ('PM', 'MARAVILHAS / PEQUI / PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'SAO GONCALO DO PARA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'MATEUS LEME / FLORESTAL / JUATUBA', 1303.56, '2571'),
    ('PM', 'MARAVILHAS / PAPAGAIOS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'PARA DE MINAS / JUATUBA', 597.47, '2568'),
    ('PM', 'PEQUI / PARA DE MINAS', 1129.75, '2536'),
    ('PM', 'MARAVILHAS / SAO JOSE DA VARGINHA / PAPAGAIOS / PEQUI', 1412.19, '2534'),
    ('PM', 'IGARATINGA / NOVA SERRANA / SAO GONCALO DO PARA', 1303.56, '2743'),
    ('PM', 'JUATUBA / MATEUS LEME / PARA DE MINAS / FLORESTAL', 1303.56, '2571'),
    ('PM', 'PARA DE MINAS / NOVA SERRANA', 1303.56, '2743'),
    ('PM', 'JUATUBA / FLORESTAL / MATEUS LEME / PARA DE MINAS', 1303.56, '2571'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA / SAO GONCALO DO PARA / PARA DE MINAS', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / MARAVILHAS', 1357.88, '2532'),
    ('PM', 'SAO JOSE DA VARGINHA / MARAVILHAS / PAPAGAIOS / PEQUI', 1412.19, '2534'),
    ('PM', 'FLORESTAL / PARA DE MINAS / JUATUBA / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'SAO GONCALO DO PARA / PARA DE MINAS / IGARATINGA', 1412.19, '2746'),
    ('PM', 'PAPAGAIOS / PEQUI / PARA DE MINAS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / CONCEICAO DO PARA / PARA DE MINAS', 1629.45, '2739'),
    ('PM', 'PAPAGAIOS / PEQUI / PARA DE MINAS / MARAVILHAS', 1412.19, '2534'),
    ('PM', 'SAO JOSE DA VARGINHA / MARAVILHAS / PEQUI', 1357.88, '2532'),
    ('PM', 'PITANGUI / CONCEICAO DO PARA / NOVA SERRANA / SAO GONCALO DO PARA', 1412.19, '2557'),
    ('PM', 'ITAUNA / CARMO DO CAJURU / ITATIAIUCU / PARA DE MINAS', 1303.56, '2605'),
    ('PM', 'NOVA SERRANA / SAO GONCALO DO PARA / PARA DE MINAS / CONCEICAO DO PARA / IGARATINGA', 1629.45, '2739'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA / PARA DE MINAS', 1629.45, '2739'),
    ('PM', 'FLORESTAL / JUATUBA / PARA DE MINAS / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'LEANDRO FERREIRA', 1357.88, '2515'),
    ('PM', 'MATEUS LEME / FLORESTAL / ITATIAIUCU / PARA DE MINAS', 1303.56, '2605'),
    ('PM', 'PEQUI / MARAVILHAS', 1357.88, '2532'),
    ('PM', 'PITANGUI / PARA DE MINAS / CONCEICAO DO PARA', 1412.19, '2557'),
    ('PM', 'NOVA SERRANA / PARA DE MINAS / IGARATINGA', 1303.56, '2743'),
    ('PM', 'MARAVILHAS / PARA DE MINAS / PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'PEQUI / SAO JOSE DA VARGINHA / PAPAGAIOS / MARAVILHAS', 1412.19, '2534'),
    ('PM', 'PARA DE MINAS / NOVA SERRANA / IGARATINGA', 1303.56, '2743'),
    ('PM', 'JUATUBA / FLORESTAL / PARA DE MINAS / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'IGARATINGA / PARA DE MINAS / CONCEICAO DO PARA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / FLORESTAL / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'PEQUI / PAPAGAIOS / MARAVILHAS / PARA DE MINAS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'ITAUNA / MATEUS LEME', 1249.25, '2742'),
    ('PM', 'PARA DE MINAS / CONCEICAO DO PARA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / CONCEICAO DO PARA / IGARATINGA', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / SAO GONCALO DO PARA', 1412.19, '2746'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA / SAO GONCALO DO PARA / NOVA SERRANA', 1629.45, '2739'),
    ('PM', 'IGARATINGA / SAO GONCALO DO PARA / PARA DE MINAS', 1412.19, '2746'),
    ('PM', 'PEQUI / MARAVILHAS / PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'SAO JOSE DA VARGINHA / PARA DE MINAS / PEQUI', 1129.75, '2536'),
    ('PM', 'PAPAGAIOS / SAO JOSE DA VARGINHA / MARAVILHAS', 1412.19, '2534'),
    ('PM', 'PEQUI / SAO JOSE DA VARGINHA / PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'PERDIGAO / ARAUJOS / IGARATINGA / PARA DE MINAS', 1412.19, '2509'),
    ('PM', 'PARA DE MINAS / PITANGUI / ONCA DE PITANGUI / CONCEICAO DO PARA', 1412.19, '2557'),
    ('PM', 'PARA DE MINAS / PAPAGAIOS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'MARAVILHAS / SAO JOSE DA VARGINHA', 1357.88, '2532'),
    ('PM', 'PAPAGAIOS / MARAVILHAS / PARA DE MINAS', 1412.19, '2534'),
    ('PM', 'CONCEICAO DO PARA / ONCA DE PITANGUI / PARA DE MINAS / PITANGUI', 1412.19, '2557'),
    ('PM', 'PARA DE MINAS / PEQUI', 1129.75, '2536'),
    ('PM', 'FLORESTAL / JUATUBA / PARA DE MINAS', 597.47, '2568'),
    ('PM', 'ONCA DE PITANGUI / PITANGUI / PARA DE MINAS / CONCEICAO DO PARA', 1412.19, '2557'),
    ('PM', 'PEQUI / PAPAGAIOS / MARAVILHAS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'IGARATINGA / PARA DE MINAS / SAO GONCALO DO PARA', 1412.19, '2746'),
    ('PM', 'PARA DE MINAS / SAO GONCALO DO PARA / IGARATINGA', 1412.19, '2746'),
    ('PM', 'CONCEICAO DO PARA / PARA DE MINAS', 1629.45, '2739'),
    ('PM', 'MATEUS LEME / FLORESTAL / PARA DE MINAS / JUATUBA', 1303.56, '2571'),
    ('PM', 'SAO GONCALO DO PARA / CONCEICAO DO PARA / IGARATINGA', 1629.45, '2739'),
    ('PM', 'PERDIGAO / ARAUJOS / NOVA SERRANA', 1412.19, '2509'),
    ('PM', 'ITATIAIUCU / ITAUNA / CARMO DO CAJURU', 1303.56, '2605'),
    ('PM', 'ARAUJOS / IGARATINGA / PERDIGAO', 1412.19, '2509'),
    ('PM', 'MARAVILHAS / PEQUI / PARA DE MINAS / SAO JOSE DA VARGINHA', 1357.88, '2532'),
    ('PM', 'NOVA SERRANA / IGARATINGA / CONCEICAO DO PARA / PARA DE MINAS', 1629.45, '2739'),
    ('PM', 'MATEUS LEME / JUATUBA / ITAUNA', 1249.25, '2742'),
    ('PM', 'NOVA SERRANA / PARA DE MINAS / LEANDRO FERREIRA / SAO GONCALO DO PARA', 1357.88, '2515'),
    ('PM', 'FLORESTAL / MATEUS LEME / JUATUBA / PARA DE MINAS', 1303.56, '2571'),
    ('PM', 'PITANGUI / ONCA DE PITANGUI / PARA DE MINAS / CONCEICAO DO PARA', 1412.19, '2557'),
    ('PM', 'NOVA SERRANA / IGARATINGA / CONCEICAO DO PARA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / MARAVILHAS / SAO JOSE DA VARGINHA', 1357.88, '2532'),
    ('PM', 'PARA DE MINAS / ONCA DE PITANGUI / PITANGUI', 1412.19, '2557'),
    ('PM', 'IGARATINGA / SAO GONCALO DO PARA / PARA DE MINAS / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'SAO JOSE DA VARGINHA / PARA DE MINAS / PEQUI / MARAVILHAS', 1357.88, '2532'),
    ('PM', 'NOVA SERRANA / PERDIGAO / ARAUJOS', 1412.19, '2509'),
    ('PM', 'FLORESTAL / PARA DE MINAS / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'NOVA SERRANA / PARA DE MINAS / CONCEICAO DO PARA / IGARATINGA', 1629.45, '2739'),
    ('PM', 'ARAUJOS / NOVA SERRANA / PERDIGAO', 1412.19, '2509'),
    ('PM', 'JUATUBA / PARA DE MINAS / MATEUS LEME / FLORESTAL', 1303.56, '2571'),
    ('PM', 'MARAVILHAS / PAPAGAIOS / SAO JOSE DA VARGINHA / PEQUI', 1412.19, '2534'),
    ('PM', 'ITAUNA / PARA DE MINAS / IGARATINGA', 1249.25, '2742'),
    ('PM', 'PARA DE MINAS / IGARATINGA / CONCEICAO DO PARA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'PAPAGAIOS / PARA DE MINAS / PEQUI / MARAVILHAS', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / IGARATINGA / ARAUJOS', 1412.19, '2509'),
    ('PM', 'PAPAGAIOS / PARA DE MINAS / PEQUI / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'MARAVILHAS / PAPAGAIOS / PARA DE MINAS / PEQUI', 1412.19, '2534'),
    ('PM', 'PARA DE MINAS / JUATUBA / FLORESTAL', 597.47, '2568'),
    ('PM', 'IGARATINGA / NOVA SERRANA', 1303.56, '2743'),
    ('PM', 'PARA DE MINAS / CONCEICAO DO PARA / NOVA SERRANA', 1629.45, '2739'),
    ('PM', 'SAO JOSE DA VARGINHA / PARA DE MINAS / MARAVILHAS / PEQUI', 1357.88, '2532'),
    ('PM', 'ITATIAIUCU / ITAUNA / PARA DE MINAS', 1303.56, '2605'),
    ('PM', 'PARA DE MINAS / PEQUI / MARAVILHAS / SAO JOSE DA VARGINHA', 1357.88, '2532'),
    ('PM', 'NOVA SERRANA / LEANDRO FERREIRA / PARA DE MINAS', 1357.88, '2515'),
    ('PM', 'MARAVILHAS / PARA DE MINAS / PAPAGAIOS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'MARAVILHAS / PARA DE MINAS / SAO JOSE DA VARGINHA / PEQUI', 1357.88, '2532'),
    ('PM', 'NOVA SERRANA / PARA DE MINAS / CONCEICAO DO PARA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'JUATUBA / PARA DE MINAS / FLORESTAL', 597.47, '2568'),
    ('PM', 'ITAUNA / IGARATINGA / PARA DE MINAS', 1249.25, '2742'),
    ('PM', 'PERDIGAO / NOVA SERRANA / PARA DE MINAS', 1412.19, '2744'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA / NOVA SERRANA / PARA DE MINAS / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'PITANGUI / PARA DE MINAS / CONCEICAO DO PARA / ONCA DE PITANGUI', 1412.19, '2557'),
    ('PM', 'PAPAGAIOS / MARAVILHAS / PARA DE MINAS / PEQUI', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / PARA DE MINAS / IGARATINGA / SAO GONCALO DO PARA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / PAPAGAIOS / MARAVILHAS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'SAO JOSE DA VARGINHA / MARAVILHAS', 1357.88, '2532'),
    ('PM', 'NOVA SERRANA / IGARATINGA / SAO GONCALO DO PARA', 1303.56, '2743'),
    ('PM', 'MARAVILHAS / PAPAGAIOS / SAO JOSE DA VARGINHA / PARA DE MINAS / PEQUI', 1412.19, '2534'),
    ('PM', 'PAPAGAIOS / PARA DE MINAS / MARAVILHAS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'JUATUBA / MATEUS LEME / NOVA SERRANA', 1303.56, '2571'),
    ('PM', 'MARAVILHAS / PEQUI / PARA DE MINAS', 1357.88, '2532'),
    ('PM', 'PAPAGAIOS / PARA DE MINAS / MARAVILHAS', 1412.19, '2534'),
    ('PM', 'PARA DE MINAS / CONCEICAO DO PARA / IGARATINGA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'PAPAGAIOS / PARA DE MINAS / MARAVILHAS / PEQUI / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'ITAUNA / SAO JOSE DA VARGINHA', 1249.25, '2742'),
    ('PM', 'PARA DE MINAS / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'CONCEICAO DO PARA / NOVA SERRANA', 1629.45, '2739'),
    ('PM', 'ARAUJOS / PERDIGAO / NOVA SERRANA', 1412.19, '2509'),
    ('PM', 'PEQUI / MARAVILHAS / SAO JOSE DA VARGINHA', 1357.88, '2532'),
    ('PM', 'PAPAGAIOS / MARAVILHAS / PEQUI / PARA DE MINAS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / PERDIGAO / PARA DE MINAS', 1412.19, '2744'),
    ('PM', 'PEQUI / MARAVILHAS / PAPAGAIOS / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'ONCA DE PITANGUI / PARA DE MINAS', 1412.19, '2557'),
    ('PM', 'SAO GONCALO DO PARA / NOVA SERRANA', 1303.56, '2743'),
    ('PM', 'SAO GONCALO DO PARA / IGARATINGA / NOVA SERRANA', 1303.56, '2743'),
    ('PM', 'PARA DE MINAS / NOVA SERRANA / PERDIGAO', 1412.19, '2744'),
    ('PM', 'MARAVILHAS / PARA DE MINAS / PEQUI', 1357.88, '2532'),
    ('PM', 'FLORESTAL / JUATUBA / MATEUS LEME / PARA DE MINAS', 1303.56, '2571'),
    ('PM', 'PARA DE MINAS / JUATUBA / FLORESTAL / MATEUS LEME', 1303.56, '2571'),
    ('PM', 'MARAVILHAS / SAO JOSE DA VARGINHA / PEQUI', 1357.88, '2532'),
    ('PM', 'JUATUBA / PARA DE MINAS / MATEUS LEME / ITAUNA', 1249.25, '2742'),
    ('PM', 'PARA DE MINAS / PAPAGAIOS / SAO JOSE DA VARGINHA / MARAVILHAS', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / IGARATINGA / PARA DE MINAS / CONCEICAO DO PARA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'NOVA SERRANA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / PERDIGAO / IGARATINGA / NOVA SERRANA', 1412.19, '2744'),
    ('PM', 'PARA DE MINAS / PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'PARA DE MINAS / MARAVILHAS / PEQUI / SAO JOSE DA VARGINHA', 1357.88, '2532'),
    ('PM', 'JUATUBA / MATEUS LEME / ITAUNA / PARA DE MINAS', 1249.25, '2742'),
    ('PM', 'IGARATINGA / NOVA SERRANA / SAO GONCALO DO PARA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'MARAVILHAS / SAO JOSE DA VARGINHA / PEQUI / PARA DE MINAS', 1357.88, '2532'),
    ('PM', 'SAO GONCALO DO PARA / PARA DE MINAS / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'ARAUJOS / NOVA SERRANA / PERDIGAO / IGARATINGA', 1412.19, '2509'),
    ('PM', 'PAPAGAIOS / MARAVILHAS / PARA DE MINAS / PEQUI / SAO JOSE DA VARGINHA', 1412.19, '2534'),
    ('PM', 'PARA DE MINAS / MARAVILHAS / PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'PERDIGAO / IGARATINGA / NOVA SERRANA / CONCEICAO DO PARA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'NOVA SERRANA / CONCEICAO DO PARA / PARA DE MINAS / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA / NOVA SERRANA / PARA DE MINAS', 1629.45, '2739'),
    ('PM', 'PEQUI / PARA DE MINAS / SAO JOSE DA VARGINHA', 1129.75, '2536'),
    ('PM', 'IGARATINGA / NOVA SERRANA / PARA DE MINAS', 1303.56, '2743'),
    ('PM', 'SAO JOSE DA VARGINHA / PARA DE MINAS / MARAVILHAS', 1357.88, '2532'),
    ('PM', 'PITANGUI / NOVA SERRANA', 1412.19, '2557'),
    ('PM', 'IGARATINGA / PERDIGAO / SAO GONCALO DO PARA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'CONCEICAO DO PARA / IGARATINGA / PARA DE MINAS', 1629.45, '2739'),
    ('PM', 'SAO JOSE DA VARGINHA / PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'IGARATINGA / SAO GONCALO DO PARA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'ONCA DE PITANGUI / CONCEICAO DO PARA', 1412.19, '2557'),
    ('PM', 'ONCA DE PITANGUI / CONCEICAO DO PARA / PITANGUI', 1412.19, '2557'),
    ('PM', 'MARAVILHAS / SAO JOSE DA VARGINHA / PAPAGAIOS', 1412.19, '2534'),
    ('PM', 'NOVA SERRANA / SAO GONCALO DO PARA / IGARATINGA', 1303.56, '2743'),
    ('PM', 'PITANGUI / PARA DE MINAS / ONCA DE PITANGUI', 1412.19, '2557'),
    ('PM', 'PERDIGAO / NOVA SERRANA / SAO GONCALO DO PARA', 1412.19, '2744'),
    ('PM', 'NOVA SERRANA / PITANGUI', 1412.19, '2557'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA / PARA DE MINAS / NOVA SERRANA', 1629.45, '2739'),
    ('PM', 'ONCA DE PITANGUI / PARA DE MINAS / CONCEICAO DO PARA / PITANGUI', 1412.19, '2557'),
    ('PM', 'SAO GONCALO DO PARA / NOVA SERRANA / IGARATINGA', 1303.56, '2743'),
    ('PM', 'ONCA DE PITANGUI', 1412.19, '2557'),
    ('PM', 'PEQUI', 1129.75, '2536'),
    ('PM', 'MATEUS LEME / JUATUBA / PERDIGAO', 1249.25, '2742'),
    ('PM', 'PARA DE MINAS / IGARATINGA / SAO GONCALO DO PARA', 1629.45, '2739'),
    ('PM', 'CONCEICAO DO PARA / PARA DE MINAS / IGARATINGA', 1629.45, '2739'),
    ('PM', 'MARAVILHAS / SAO JOSE DA VARGINHA / PARA DE MINAS / PEQUI', 1129.75, '2536'),
    ('PM', 'SAO JOSE DA VARGINHA / MARAVILHAS / PARA DE MINAS / PEQUI', 1129.75, '2536'),
    ('PM', 'PARA DE MINAS / MARAVILHAS / SAO JOSE DA VARGINHA / PEQUI', 1129.75, '2536'),
    ('PM', 'NOVA SERRANA / PAPAGAIOS', 1412.19, '2744'),
    ('PM', 'JUATUBA / MATEUS LEME / PERDIGAO', 1249.25, '2742'),
    ('PM', 'JUATUBA / PERDIGAO', 1249.25, '2742'),
    ('PM', 'SAO JOSE DA VARGINHA / PARA DE MINAS / PAPAGAIOS', 1129.75, '2536'),
    ('PM', 'PARA DE MINAS / MATEUS LEME / FLORESTAL', 1303.56, '2571'),
    ('PM', 'ONCA DE PITANGUI / CONCEICAO DO PARA / PARA DE MINAS', 1412.19, '2557'),
    ('PM', 'ONCA DE PITANGUI / PARA DE MINAS / CONCEICAO DO PARA', 1412.19, '2557'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'IGARATINGA / NOVA SERRANA / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'NOVA SERRANA / PARA DE MINAS / CONCEICAO DO PARA / PERDIGAO', 1629.45, '2739'),
    ('PM', 'MATEUS LEME / PARA DE MINAS / FLORESTAL', 1303.56, '2571'),
    ('PM', 'ITAUNA / MATEUS LEME / JUATUBA', 1249.25, '2742'),
    ('PM', 'NOVA SERRANA / IGARATINGA / PARA DE MINAS / SAO GONCALO DO PARA', 1303.56, '2743'),
    ('PM', 'IGARATINGA / PARA DE MINAS / SAO GONCALO DO PARA / NOVA SERRANA', 1629.45, '2739'),
    ('PM', 'PARA DE MINAS / PAPAGAIOS / PEQUI / SAO JOSE DA VARGINHA', 1129.75, '2536'),
    ('PM', 'IGARATINGA / ANTUNES', 1303.56, '2743'),
    ('PM', 'PAPAGAIOS / SAO JOSE DA VARGINHA / PARA DE MINAS', 1412.19, '2534'),
    ('PM', 'PITANGUI / ONCA DE PITANGUI / CONCEICAO DO PARA / PARA DE MINAS', 1412.19, '2557'),
    ('PM', 'MATEUS LEME / JUATUBA / FLORESTAL / PARA DE MINAS', 1303.56, '2571'),
    ('PM', 'PAPAGAIOS / BELO HORIZONTE', 1412.19, '2534'),
    ('PM', 'PARA DE MINAS / IGARATINGA / NOVA SERRANA', 1303.56, '2743'),
    ('PM', 'IGARATINGA / PARA DE MINAS / SAO JOSE DA VARGINHA', 1129.75, '2536'),
    ('PM', 'PARA DE MINAS / IGARATINGA / TORNEIROS', 1629.45, '2739'),
    ('PM', 'ITATIAIUCU / MATEUS LEME / RIO MANSO', 1303.56, '2605'),
    ('PM', 'MATEUS LEME / AZURITA', 1303.56, '2605'),
    ('PM', 'PITANGUI / PARA DE MINAS / CONCEICAO DO PARA / LEANDRO FERREIRA', 1412.19, '2557'),
    ('PM', 'ONCA DE PITANGUI / CONCEICAO DO PARA / PARA DE MINAS / PITANGUI', 1412.19, '2557'),
    ('PM', 'JUATUBA / ESMERALDAS', 1303.56, '2571'),
    ('PM', 'PITANGUI / CONCEICAO DO PARA / LEANDRO FERREIRA', 1412.19, '2557'),
    ('PM', 'NOVA SERRANA / IGARATINGA / SAO GONCALO DO PARA / PARA DE MINAS', 1629.45, '2739'),
    ('PM', 'JUATUBA / NOVA SERRANA', 1303.56, '2571'),
    ('PM', 'MATEUS LEME / ITAUNA / JUATUBA', 1249.25, '2742'),
    ('PM', 'ITATIAIUCU / ITAUNA / MATEUS LEME / RIO MANSO', 1303.56, '2605'),
    ('PM', 'ITATIAIUCU / NOVA SERRANA / RIO MANSO', 1303.56, '2605'),
    ('PM', 'IGARATINGA / CONCEICAO DO PARA / NOVA SERRANA / LEANDRO FERREIRA', 1629.45, '2739'),
    ('PM', 'ITATIAIUCU / CARMO DO CAJURU / RIO MANSO', 1303.56, '2605'),
    ('PM', 'NOVA SERRANA / PARA DE MINAS / CONCEICAO DO PARA', 1629.45, '2739'),
    ('PM', 'NOVA SERRANA / IGARATINGA / LEANDRO FERREIRA', 1412.19, '2744'),
    ('PM', 'CONCEICAO DO PARA / PITANGUI / LEANDRO FERREIRA', 1412.19, '2557'),
    ('PM', 'MARAVILHAS / PAPAGAIOS / PARA DE MINAS / PEQUI / SAO JOSE DA VARGINHA', 1129.75, '2536'),
    ('PM', 'ITATIAIUCU / MATEUS LEME', 1303.56, '2605'),
    ('PM', 'PAPAGAIOS / JUATUBA', 1303.56, '2571'),
    ('PM', 'PAPAGAIOS / PEQUI / MARAVILHAS / SAO JOSE DA VARGINHA', 1129.75, '2536'),
    ('PM', 'LEANDRO FERREIRA / IGARATINGA / NOVA SERRANA / SAO GONCALO DO PARA', 1412.19, '2744'),
    ('PM', 'PEQUI / MARAVILHAS / PAPAGAIOS / PARA DE MINAS / SAO JOSE DA VARGINHA', 1129.75, '2536'),
    ('PM', 'ITAUNA / ITATIAIUCU / RIO MANSO', 1303.56, '2605'),
    ('PM', 'PAPAGAIOS / PITANGUI', 1303.56, '2571'),
    ('PM', 'SAO GONCALO DO PARA / IGARATINGA / PARA DE MINAS / CONCEICAO DO PARA', 1412.19, '2557'),
    ('PM', 'PAPAGAIOS / SAO JOSE DA VARGINHA / MARAVILHAS / PEQUI', 1129.75, '2536'),
    ('PM', 'PARA DE MINAS / ONCA DE PITANGUI / CONCEICAO DO PARA / PITANGUI', 1412.19, '2557'),
    ('PM', 'PARA DE MINAS / IGARATINGA / NOVA SERRANA / CONCEICAO DO PARA / SAO GONCALO DO PARA', 1412.19, '2557'),
    ('PM', 'ITATIAIUCU / ITAGUARA / RIO MANSO', 1303.56, '2605'),
    ('PM', 'NOVA SERRANA / ARAUJOS / IGARATINGA / PERDIGAO', 1412.19, '2744'),
    ('PM', 'ONCA DE PITANGUI / PITANGUI / CONCEICAO DO PARA / PARA DE MINAS', 1412.19, '2744'),
    ('PM', 'CONCEICAO DO PARA / IGARATINGA', 1412.19, '2557'),
    ('PM', 'PITANGUI / COROACI', 1303.56, '2571'),
    ('PM', 'JUATUBA / MATEUS LEME / ITAUNA', 1249.25, '2742'),
    ('PM', 'NOVA SERRANA / FRUTAL', 1412.19, '2744'),
    ('PM', 'IGARATINGA / NOVA SERRANA / PARA DE MINAS / SAO GONCALO DO PARA', 1412.19, '2557'),
    ('PM', 'JUATUBA / PARA DE MINAS / NOVA SERRANA', 1249.25, '2742'),
    ('PM', 'PARA DE MINAS / MATEUS LEME / FLORESTAL / JUATUBA', 1303.56, '2571'),
    ('PM', 'MARAVILHAS / PARA DE MINAS / PAPAGAIOS / PEQUI', 1129.75, '2536'),
    ('PM', 'PEQUI / MARAVILHAS / SAO JOSE DA VARGINHA / PARA DE MINAS', 1129.75, '2536'),
    ('PM', 'NOVA SERRANA / PARA DE MINAS / PERDIGAO', 1412.19, '2744'),
    ('PM', 'MARAVILHAS / PEQUI / PAPAGAIOS / SAO JOSE DA VARGINHA', 1129.75, '2536'),
    ('PM', 'IGARATINGA / NOVA SERRANA / CONCEICAO DO PARA / PARA DE MINAS', 1412.19, '2557'),
    ('PM', 'ITATIAIUCU / RIO MANSO / ITAUNA', 1303.56, '2605'),
    ('PM', 'PEQUI / PARA DE MINAS / MARAVILHAS / SAO JOSE DA VARGINHA', 1129.75, '2536');

-- Indicadores de vendas (Promax 17.03), coluna "Valor" de cada "Cod. ID".
-- Verba de troca usa 563 (volume HL saída) - 564 (volume HL devolução).
CREATE TABLE IF NOT EXISTS indicadores_vendas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    codigo INT UNSIGNED NOT NULL COMMENT 'Cod. ID',
    descricao VARCHAR(100) NULL,
    valor DECIMAL(16,4) NOT NULL DEFAULT 0,
    UNIQUE KEY uk_indicadores_vendas (revenda, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notas de troca em rota (Promax 03.02.37), um registro por item da nota.
-- Substituídas por revenda a cada importação (a última importação é a rota do dia).
CREATE TABLE IF NOT EXISTS trocas_notas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    operacao SMALLINT UNSIGNED NULL,
    data_operacao DATE NULL,
    data_emissao DATE NULL,
    nota INT UNSIGNED NOT NULL,
    serie VARCHAR(10) NULL,
    cliente_codigo INT UNSIGNED NOT NULL,
    cliente_nome VARCHAR(150) NULL,
    produto_codigo VARCHAR(30) NULL,
    produto_descricao VARCHAR(150) NULL,
    unidade VARCHAR(10) NULL,
    quantidade DECIMAL(12,4) NOT NULL DEFAULT 0,
    valor_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    mapa VARCHAR(30) NULL,
    numero_pedido VARCHAR(30) NULL,
    status_nfe VARCHAR(40) NULL,
    setor_codigo SMALLINT UNSIGNED NULL COMMENT 'NULL quando o relatório traz 000000',
    INDEX idx_trocas_notas_setor (revenda, setor_codigo),
    INDEX idx_trocas_notas_cliente (revenda, cliente_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Trabalho diário do Comercial
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prioridades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_referencia DATE NOT NULL,
    revenda VARCHAR(2) NOT NULL,
    cliente_codigo INT UNSIGNED NOT NULL,
    setor_codigo SMALLINT UNSIGNED NOT NULL,
    observacao VARCHAR(255) NULL,
    usuarios_id INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_prioridades_data_cliente (data_referencia, revenda, cliente_codigo),
    INDEX idx_prioridades_setor (data_referencia, setor_codigo),
    CONSTRAINT fk_prioridades_usuarios FOREIGN KEY (usuarios_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transferência de saldo de prioridade entre setores da mesma Mesa, válida
-- somente para a data de entrega (o limite cadastrado em setores não muda).
-- Limite do dia = limite do setor - enviado + recebido.
CREATE TABLE IF NOT EXISTS prioridades_transferencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_referencia DATE NOT NULL,
    mesa SMALLINT UNSIGNED NOT NULL,
    setor_origem_codigo SMALLINT UNSIGNED NOT NULL,
    setor_destino_codigo SMALLINT UNSIGNED NOT NULL,
    clientes TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Vagas de cliente transferidas',
    hl DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT 'HL transferido',
    usuarios_id INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prioridades_transferencias_mesa (data_referencia, mesa),
    CONSTRAINT fk_prioridades_transferencias_usuarios FOREIGN KEY (usuarios_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS criticas_decisoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_referencia DATE NOT NULL,
    revenda VARCHAR(2) NOT NULL,
    cliente_codigo INT UNSIGNED NOT NULL,
    decisao VARCHAR(30) NOT NULL,
    observacao VARCHAR(255) NULL,
    usuarios_id INT UNSIGNED NOT NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_criticas_decisoes_data_cliente (data_referencia, revenda, cliente_codigo),
    CONSTRAINT fk_criticas_decisoes_usuarios FOREIGN KEY (usuarios_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
