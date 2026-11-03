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
    (540, 'PM', 54, 5, 19), (541, 'PM', 54, 5, 19), (542, 'PM', 54, 5, 19), (543, 'PM', 54, 5, 19);

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
    cidade VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    dia_visita VARCHAR(40) NULL,
    status_cliente VARCHAR(10) NULL,
    segmento VARCHAR(60) NULL,
    UNIQUE KEY uk_clientes_revenda_codigo (revenda, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Volume vendido (HL) por setor (Promax 03.05.09), somado na importação.
-- Base da verba de troca do setor (aba Aprovação troca).
CREATE TABLE IF NOT EXISTS vendas_setores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revenda VARCHAR(2) NOT NULL,
    setor_codigo SMALLINT UNSIGNED NOT NULL,
    volume_hl DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'Soma de "Quantidade Hecto"',
    UNIQUE KEY uk_vendas_setores (revenda, setor_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
