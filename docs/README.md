# Roteirizador

Sistema interno da COBEB que substitui as planilhas **Crítica GOD Cobeb Matriz** e **Prioridade** no fluxo diário entre **Logística** e **Comercial**. Construído sobre o **Framework COBEB** (CDP v1.0).

> **Celular:** todas as telas funcionam no navegador do celular. No celular, o cabeçalho fica em duas linhas (seletores de Sala/Revenda embaixo), o menu abre no botão "Menu", as abas internas e as tabelas rolam para o lado (uma linha por registro), e os campos usam fonte de 16px (evita o zoom do iPhone). Ao "Adicionar à tela inicial", o atalho usa o ícone `public/assets/img/icone-app.png`. Ajustes em `app.css` (bloco "Celular").

> **Nomenclatura:** nas telas, a **Mesa** (código do gerente de vendas) aparece como **Sala** — ex.: Sala 54. Este documento, o código e o banco continuam usando "Mesa"/`mesa`.

## Revendas

O sistema é separado por revenda em todas as telas: **PM**, **LP** e **AB**.

| Revenda | Cora: Cód. unidade venda | 03.18.05: UNB | Mesas |
|---|---|---|---|
| PM | 772003 | 1 | 52, 53, 54 |
| LP | 1883003 | 2 | 22, 23 |
| AB | 984507 | 5 | 23, 24 |

- **Mesas**: a tabela `mesas` tem um registro por **Mesa + revenda**, porque uma Mesa pode atender mais de uma revenda (a **Mesa 23 atende LP e AB**). O Administrador cadastra os pares em *Setores*.
- **Setores**: cada setor tem a **sua revenda** (`setores.revenda`), escolhida junto com a Mesa no cadastro ("23 (AB)"). Na Mesa 23, os setores 223 e 230–232 são da LP e o 233 é da AB. Os códigos de setor não se repetem entre as revendas.
- **Usuários**:
  - **Comercial**: vê as revendas que a própria Mesa atende, sempre restrito à própria Mesa. Na Mesa 23, vê LP e AB juntas ou escolhe uma delas no menu.
  - **Logística**: tem uma revenda, ou "Todas", definida em *Usuários*.
  - **Administrador**: vê todas as revendas.
- Quem não está preso a uma revenda escolhe qual vai ver no seletor do menu superior ("Todas" mostra as três, com o selo da revenda em cada linha). A **Crítica GOD** também tem o filtro **Revenda** junto da data e da Mesa. Ele fica sincronizado com o menu, e a lista de Mesas passa a mostrar só as da revenda escolhida. Para quem está preso, o menu mostra só a revenda fixa.
- **Importação**: é feita sempre para uma revenda e substitui apenas os dados dela.
  - No Cora e no 03.18.05, o sistema confere a revenda pela coluna do arquivo (Cód. unidade venda / UNB). Linhas de outra revenda são ignoradas. Se o arquivo não tiver nenhuma linha da revenda escolhida, a importação é recusada.
   - **Todas (separar pelo arquivo)**: no Cora e no 03.18.05, quem acessa mais de uma revenda pode enviar **um único arquivo com PM, LP e AB**. O sistema lê uma vez, separa pela coluna de revenda e substitui cada revenda presente (no Cora, só a data escolhida), com um registro de importação por revenda (dá para excluir cada uma depois). Linhas sem código de revenda ou com código desconhecido são ignoradas e informadas na mensagem; revendas sem permissão do usuário não são gravadas. Para os demais relatórios, "Todas" é recusado.
  - Os cadastros (01.20.11, 03.02.37, Black List, Top Line, Travas) não trazem a revenda; vale a escolhida no formulário.
- **Prioridade em Mesa com mais de uma revenda**: o sistema procura o pedido do cliente nas revendas da Mesa. O setor precisa ser da mesma revenda do pedido. Se o mesmo código de cliente tiver pedido nas duas revendas, o sistema pede para escolher a revenda no menu. A transferência de saldo vale entre quaisquer setores da Mesa.
- **Isolamento**: a regra fica em `BaseController::revendasPermitidas()`, `revendaRestrita()`, `revendaSelecionada()` e `podeAcessarRevenda()`. Dados de outra revenda (importação, setor, decisão, prioridade) são tratados como inexistentes. Toda consulta nova deve filtrar por revenda e cruzar as tabelas por `revenda` + código do cliente.
- Os códigos das revendas nos relatórios ficam em `App\Helpers\Revenda::REVENDAS`.

## Fluxo diário

1. **Logística** importa os exports em *Importações*:
   - **Pedidos Cora** (aguardando roteirização), informando a data de entrega. É a base do dia.
   - Quando houver atualização: **01.20.11** (clientes), **03.02.37** (notas), **03.02.24** (Black List), **Top Line**, **Travas** e **03.18.05** (solicitações de troca).
2. **Logística** analisa a **Crítica GOD**: confere os indicadores de cada cliente e registra a decisão (Libera / Corta / Comprador pode liberar / Abaixo de R$100 corta tudo) com observação.
3. **Comercial** (cada gerente na própria Mesa):
   - **Crítica GOD**: consulta os indicadores e as decisões da Logística para os clientes da sua Mesa.
   - **Prioridade**: informa o código dos clientes priorizados. O sistema identifica o setor pelo pedido do Cora, calcula o HL e **bloqueia** quando o setor estoura o limite de clientes ou de HL.
4. **Logística** usa a **Consulta do dia** para roteirizar: prioridades de todas as Mesas com observações, decisões da Crítica, pendências por Mesa e clientes com trava.
5. **Troca** (qualquer perfil com a aba liberada):
   - **Solicitações por cliente**: filtre por **Código Cliente**, **Setor**, **Situação** (Status Solicitação) e/ou **período** (De/Até da Data Solicitação; pode usar só um dos dois, e datas invertidas são trocadas). Os filtros são opcionais e combináveis, e pelo menos um deve ser preenchido. As listas de setor e situação trazem só o que o usuário pode ver (o Comercial vê só os setores da própria Mesa). A lista mostra até 1000 itens, os mais recentes primeiro. A coluna **Justificativa da Logística** traz o motivo registrado na aba Aprovação troca. Colunas do 03.18.05: Nome Cliente, Data Solicitação, Status Solicitação, Nota Fiscal/Serie, Produto, Descrição Produto, Quantidade, U.M., Sistema Origem e Observação.
   - **Notas em rota**: itens das notas do **03.02.37** importado para a rota do dia, com filtros por **Mesa** e por **setor**. A lista de setores acompanha a Mesa escolhida; o Comercial fica sempre na própria Mesa.
     - **Imagem para WhatsApp**: gera uma imagem (PNG) com o logo e as notas do filtro atual, uma por nota, com cliente, mapa e itens. No celular, "Compartilhar" abre o WhatsApp direto (Web Share API). No computador, a imagem é baixada para anexar na conversa. A imagem é desenhada no navegador (canvas, JavaScript puro): nada é enviado a serviços externos. Mostra nota/série, emissão, operação, cliente, produto, quantidade, unidade, total, mapa e status NF-e.
   - **Faturado (aprovado)**: espelho da parte Faturado da Verba de troca (aba Aprovação troca), com Promax, Force, Verba, GAP, Volume (HL) e R$/HL por setor, mais o total. Mostra uma tabela por revenda e tem filtros de **Revenda** (para quem vê mais de uma) e de **Mesa** (a lista de Mesas segue a revenda escolhida). O Comercial vê só os setores da própria Mesa. O cálculo é o mesmo da Aprovação troca (`BaseController::verbaTroca()`).
   - **Importar relatórios**: quem também tem a aba Importações pode enviar o 03.18.05 e o 03.02.37 direto por aqui.
   - **Isolamento**: o Comercial só vê clientes e setores da própria Mesa. A Mesa vem do setor do relatório (Setor Venda / Setor); quando o setor vem vazio ou "000000", usa-se o setor do 01.20.11.
6. **Aprovação troca** (Logística e Administrador com a aba liberada):
   - **Filtro de mês** (no topo, ao lado da revenda): limita a tela toda às trocas com **Data Solicitação** no mês escolhido, ou seja, a Verba por setor, os motoristas, os ajudantes e as Solicitações. O "Cabe na verba" também é recalculado só com o mês. A lista traz apenas os meses que têm trocas na revenda, e o padrão é "Todos os meses", que é o comportamento anterior. O volume (HL) do 03.05.09 / 17.03 não tem data e continua sendo o do relatório importado, por isso ele deve ser do mesmo mês filtrado. O mês acompanha os filtros da aba Solicitações, o clique no setor e o salvar da justificativa.
   - **Verba de troca**: espelho da aba MATRIZ da planilha "Reposição Novo", sempre de uma revenda (seletor para quem vê mais de uma). Mostra por setor as trocas pendentes e aprovadas (Promax, Force e Customer), a verba, o GAP, o volume em HL e o R$/HL. Também mostra as trocas Promax aprovadas por motorista e por ajudante de entrega. As regras estão em "Verba de troca" abaixo.
   - **Solicitações 03.18.05**: lista das solicitações (uma linha por solicitação, com os produtos). Filtros: Status, Sistema Origem, "Na verba" (cabe / não cabe), Setor e Código Cliente. O padrão é Force pendente. Clicar no setor da Verba de troca abre esta lista já filtrada.
     - Só as pendências **Force** consomem a verba. Por setor, da mais antiga para a mais nova, uma solicitação **cabe** se o valor couber no saldo (GAP menos as pendências anteriores que couberam). A que não cabe é pulada, e as seguintes ainda podem caber. A coluna Saldo mostra o que sobra da verba depois de cada uma (`VerbaTroca::classificarPendentes`).
     - **Justificativa para o Comercial**: em cada solicitação, a Logística escreve o motivo (ex.: por que foi reprovada) e salva. Campo vazio + salvar apaga. Fica gravada com autor e data na tabela `trocas_justificativas` (revenda + solicitação + cliente), separada de `trocas`, por isso **não se perde ao reimportar o 03.18.05**.
   - **Verba extra (somente Administrador)**: no card "Verba extra" da aba Verba de troca, o Administrador escolhe o setor, o mês, o valor e o motivo (obrigatório). O valor **soma na verba do setor** (HL × 10% + extra), e com isso o GAP e o "Cabe na verba" passam a considerá-lo. É um valor por revenda + setor + mês: lançar de novo substitui o valor e o motivo. Cada lançamento pode ser removido na lista do card, que mostra quem lançou e quando. Com um mês filtrado, a matriz usa só a verba extra desse mês; em "Todos os meses", soma a de todos os meses. A verba extra aparece abaixo da Verba do setor ("+ R$ … extra") e também entra no Faturado da aba Troca. Fica na tabela `verbas_extras`, separada dos relatórios, por isso **não se perde ao reimportar**. Rotas `POST /aprovacao-troca/verba-extra` e `/aprovacao-troca/verba-extra/{id}/remover`, com `AdminMiddleware`; cada alteração fica no log.
   - **Importar relatórios**: 03.18.05 (o mesmo da aba Troca, com todos os status) e **03.05.09** (volume vendido por setor).

## Perfis

| Perfil | Pode |
|---|---|
| Administrador | Tudo, inclusive gerenciar usuários e **alterar os limites de prioridade** |
| Logística | Importar dados, **decidir a Crítica GOD**, cadastrar setores e Mesas, consultar tudo |
| Comercial | Consultar a Crítica e registrar prioridades, **somente da própria Mesa** |

### Abas liberadas por usuário

Na tela **Usuários**, o administrador marca quais abas (Crítica GOD, Prioridade, Consulta do dia, Troca, Aprovação troca, Importações, Setores, Bees Loat, Farol, Tirinha, Usuários) cada usuário acessa. Funciona assim:

- O perfil é o limite: Importações, Setores, Aprovação troca, Farol e Tirinha só podem ser liberados para Logística, e Usuários só para Administrador. Abas fora do perfil são descartadas ao salvar.
- O Administrador tem sempre todas as abas.
- Uma aba não liberada some do menu e do painel, e qualquer rota dela (GET ou POST) responde 403. O bloqueio é feito pelo `PerfilMiddleware`, que identifica a aba pelo primeiro segmento da URL.
- A lista de abas fica em `UsuarioModel::ABAS`. Uma tela nova vira aba quando é adicionada a essa lista.

### Isolamento por Mesa

O usuário do Comercial é vinculado a **uma ou mais Mesas** (tabela `usuarios_mesas`; o administrador marca as Salas na tela Usuários). Ele trabalha **uma Mesa por vez**: com mais de uma, escolhe a Sala em uso no seletor do topo (rota `POST /sala`, guardada na sessão; só aceita Mesas do próprio usuário; padrão = a menor). A revenda segue a Mesa em uso. Ele **não enxerga nada das outras Mesas** em nenhuma tela: Painel, Crítica GOD, Prioridade e Consulta do dia. Alterar `?mesa=` na URL ou no formulário não tem efeito, e um cliente de outra Mesa é tratado como "sem pedido", sem revelar setor nem Mesa. A regra fica centralizada em `BaseController::mesaRestrita()`. Toda nova consulta de dados por Mesa deve usar esse método.

## Regras de cálculo (equivalência com as planilhas)

| Indicador | Origem |
|---|---|
| Cliente, cidade, setor, dia de visita | 01.20.11 (se o cliente estiver em mais de um setor, prevalece o setor cadastrado no Roteirizador); na falta, dados do Cora |
| Top Line | Lista Top Line importada |
| Black List | Quantidade de ocorrências do cliente no 03.02.24 (coluna de cliente pelo cabeçalho; sem cabeçalho, coluna G) |
| Fora de rota | Coluna "Críticas 2" do Cora |
| Valor dos pedidos | Soma do "Valor total" do Cora **por pedido distinto** (o export itemizado repete o valor do pedido em cada item) |
| HL | Soma do "Volume hectolitro" do Cora |
| Já comprou? | Quantidade de linhas do cliente no 03.02.37 |
| Mesa | "Cód. gerente vendas" do Cora (Crítica) / Mesa do setor (Prioridade) |
| Mapa Sinergia | PDVs, HL e valor por cidade, calculados a partir da Crítica |

Limites padrão da Prioridade: 5 clientes e 15 HL por setor nas Mesas 52 e 53; 5 clientes e 19 HL na Mesa 54.

**Transferência de saldo entre setores (Comercial da Mesa e Administrador)**, na tela *Prioridade*:

- Passa vagas de cliente e/ou HL **ainda livres** de um setor para outro **da mesma Mesa**.
- Vale **somente para a data de entrega**. O limite cadastrado do setor não muda. Limite do dia = limite do setor − enviado + recebido. Os cartões dos setores mostram o ajuste (+/−).
- A transferência pode ser desfeita enquanto o setor de destino não estiver usando o saldo recebido.
- A Logística apenas consulta. Toda transferência fica no log (tabela `prioridades_transferencias`).

**Verba de troca** (aba *Aprovação troca*, equivalente à aba MATRIZ da planilha "Reposição Novo"; regras em `App\Helpers\VerbaTroca`):

| Coluna | Cálculo |
|---|---|
| Pendente: Promax / Force / Customer | Soma do "Valor" do 03.18.05 com Status Solicitação "Pendente", por "Setor Venda" e "Sistema Origem" |
| Faturado: Promax / Force | Mesma soma, com status "Aprovada" |
| Volume (HL) | Soma da "Quantidade Hecto" do 03.05.09 do setor |
| Verba | Volume (HL) × 10% |
| GAP | Verba − Force aprovado. Cor (`cor_gap()`): vermelho se negativo, amarelo de R$ 0,00 a R$ 10,00, verde acima de R$ 10,00 |
| Cabe na verba? | SIM quando GAP − Force pendente ≥ 0 |
| R$/HL do setor | Valor Force ÷ volume vendido da revenda × 10 (mesma fórmula da planilha) |
| Volume vendido da revenda | Total de HL do 03.05.09 da revenda (todas as linhas). O 17.03 não é mais importado pela Aprovação troca; se for importado em Importações, passa a valer o indicador 563 (saída) − 564 (devolução), como na planilha |
| R$/HL total | Soma do Force ÷ volume vendido da revenda (sem o × 10, como na planilha) |
| Motoristas / Ajudantes | Trocas Promax aprovadas por "Nome Motorista" e pelos dois "Nome Ajudante - Entrega". O item conta para os dois ajudantes. Listados do maior valor para o menor. |

- Como na planilha, só entram os setores cadastrados na revenda. Setores que aparecem nos relatórios sem cadastro (ex.: 991, 995) são listados num aviso abaixo da tabela.
- Diferença em relação à planilha: o total de Force pendente soma todos os setores. Na planilha, a fórmula somava só as 14 primeiras linhas.
- Conferido com os dados da planilha de exemplo: volume vendido, verba, GAP, R$/HL, motoristas e ajudantes batem nos centavos.

**Alteração dos limites (somente Administrador)**, na tela *Setores*:

- **Limites por Mesa**: aplica a mesma quantidade de clientes e de HL a todos os setores da Mesa.
- **Por setor**: os campos da tabela permitem um limite diferente para um setor específico.
- A Logística vê os limites apenas para leitura. Um setor novo cadastrado por ela recebe os limites já usados na Mesa (Mesa nova: 5 clientes / 15 HL). Valores de limite enviados por outro perfil são ignorados.
- Toda alteração de limite fica registrada no log (`storage/logs`) com o autor. O novo limite vale na hora para os próximos registros de prioridade; prioridades já registradas não são removidas.

## Importação de planilhas

- Formatos aceitos: `.xlsx` e `.csv` (`;` ou `,`, UTF-8 ou Windows-1252). O `.csv.inf` gerado pelo Promax (ex.: 03.18.05) é lido como CSV.
- No 03.18.05, as colunas "Descrição Produto" e "U.M." aparecem duas vezes. Vale a primeira ocorrência, que é o produto da troca; a segunda é o produto de origem. "Nome Ajudante - Entrega" também aparece duas vezes, e as duas são lidas (no layout, `cabeçalho#2` indica a 2ª ocorrência).
- O 03.05.09 é somado por setor na importação. Do 17.03 é gravada a coluna "Valor" de cada "Cod. ID".
- As colunas são localizadas **pelo nome do cabeçalho**, sem diferença de acento e maiúsculas. A ordem das colunas pode mudar.
- Em `.xlsx` com várias abas, o sistema procura a aba pelo nome (Cora, Cora Matriz, 01 20 11, 03 02 37, 03 02 24, TOP LINE, TRAVAS). Por isso as planilhas antigas também podem ser importadas.
- Reimportar o Cora para a mesma data substitui os pedidos dessa data. As prioridades e decisões já registradas são mantidas, e o HL é recalculado.
- Os cadastros (clientes, notas, Black List, Top Line, Travas, Trocas, Notas de troca) são substituídos integralmente, **na revenda da importação**, a cada importação.
- Nas **Notas de troca em rota** (03.02.37), as notas das operações **9, 23 e 33** são ignoradas (`Importador::OPERACOES_IGNORADAS_NOTAS_TROCA`). A mensagem da importação informa quantas notas de cada operação foram descartadas.
- O 03.02.37 serve a duas importações diferentes. **Histórico de notas** alimenta o "Já comprou?" da Crítica. **Notas de troca em rota** alimenta a aba Troca. Escolha o tipo certo.
- Pedidos do Cora com **Tipo pedido = REPOSICAO** são ignorados na importação (`Importador::TIPOS_PEDIDO_IGNORADOS`).
- **Excluir importação**: só a importação **vigente** pode ser excluída. Vigente é a última de cada tipo em cada revenda; no Cora, a última de cada data de entrega na revenda. A exclusão apaga os dados que ela trouxe: no Cora, os pedidos da data; nos cadastros, a base inteira. Prioridades e decisões da data são mantidas e voltam a valer se o Cora for reimportado. O registro continua no histórico como "Excluída", com autor e data.
- O leitor (`app/Helpers/PlanilhaLeitor.php`) lê em streaming com as extensões nativas `zip` e `xmlreader`, sem biblioteca externa.

## Farol (substitui a "PLANILHA FAROL")

Painel do dia por revenda (Logística), equivalente às abas "Plan1" e "R. 03.11.49.02" da planilha. Cada revenda tem um card com os indicadores e há um "Resumo do dia" com a soma das revendas. Os dados chegam por dois tipos de importação, enviados pela própria tela do Farol ou por Importações (aceitam "Todas"):

- **Farol - Mapas roteirizados (Promax 03.11.49.02)**: um registro por mapa. A revenda vem da coluna UNB.
- **Farol - Buffer do Cora**: o mesmo export do Cora, com a coluna "Tipo buffer". Grava um registro por pedido com o volume somado e o setor do cliente (Cód. setor e Desc. setor). As reposições são gravadas, mas não contam como buffer. A revenda vem do Cód. unidade venda.

Cada importação substitui os dados da revenda. O **volume vendido** (Promax 03.01.47.01, Quebra 1: Geral) e as observações de Spot e recarga são digitados no card da revenda (tabela `farol_revendas`).

**Imagem para WhatsApp**: o botão "Baixar imagem para WhatsApp" gera um PNG com os cards das revendas e o resumo do dia, no navegador. Em "Mapas e buffer" de cada revenda, o botão "Baixar imagem do buffer" gera a tabela dos pedidos reprogramados (pedido, cliente, nome fantasia, setor, tipo e volume) com o total, ordenada por cliente e, dentro do cliente, por volume (maior primeiro). Ao lado, "Baixar em Excel" (rota `/farol/buffer/{revenda}`) baixa a mesma lista em CSV para Excel (separador `;`, UTF-8 com BOM), com setor em duas colunas (código e descrição) e uma linha de total. O desenho é feito em `<canvas>` com JavaScript puro (`public/assets/js/farol.js`), sem biblioteca e sem processamento no servidor. No celular, quando o sistema é acessado por HTTPS, o botão abre direto o compartilhamento (WhatsApp); nos demais casos, baixa o arquivo `farol-DDMMAAAA.png`.

Regras (`app/Helpers/Farol.php`):

| Indicador | Cálculo |
|---|---|
| Tipo do mapa | Placa repetida no arquivo: o 1º mapa da placa segue a regra normal e os seguintes (2º, 3º, 4º...) = Recarga. Placa começando com `REC` = Recarga; carga "Freteiro" = Spot; demais = FF |
| Volume buffer (HL) | Soma do volume hectolitro dos pedidos com Tipo buffer = REPROGRAMADO, exceto Tipo pedido = REPOSIÇÃO (a planilha somava também as reposições) |
| Volume roteirizado (HL) | Volume vendido − volume buffer |
| Clientes roteirizados | Soma das "Entregas" de todos os mapas (FF + Spot + Recarga) |
| Clientes buffer | Clientes distintos com pedido REPROGRAMADO que não é reposição (na planilha era digitado) |
| Peso roteirizado | Soma do "Total peso" dos mapas |
| Mapas FF / Spot / Recarga programada | Quantidade de mapas de cada tipo |
| Cx/viagem FF e Spot | Total de caixas do tipo ÷ mapas do tipo |

Diferença da planilha: na aba "R. 03.11.49.02", as placas eram listadas à mão em FF, SPOT e RECARGA. No sistema, a classificação é automática. Uma placa com mais de um mapa no dia conta o 1º como FF (ou Spot, se for Freteiro) e os demais como recarga. Conferido com a planilha de 06/11/2026: volume vendido, clientes roteirizados, peso, mapas e cx/viagem idênticos nas três revendas. O buffer difere da planilha onde havia reposição reprogramada (LP: 21,19 HL no sistema e 21,41 HL na planilha).

## Bees Loat (CSV de dias de entrega do BEES)

A aba recebe o **Relatório Bees** (export do BEES em `.csv`, separado por `;`) e um ou mais arquivos da **rotina 0105070402** do Promax (`.inf`/`.csv`). Ela devolve o CSV pronto para importar de novo no BEES. Nada é gravado no banco; as regras ficam em `app/Helpers/BeesLoat.php`. Na ordem:

1. Exclui a coluna D (`Settings_Type`).
2. Esvazia a coluna `Reference_Date` (coluna AA do arquivo original) e mantém só o título.
3. Troca FREE por NO nas colunas de dia (`Mon` a `Sun`).
4. Marca FREE no dia de entrega de cada cliente. O cliente é localizado pelo `Cód PDV` da rotina 0105070402, que corresponde ao `ERP_POC_ID` do Relatório Bees. O dia de entrega é o dia seguinte à visita, lida nas colunas AY ("Dia de Visita do VDE") e BH ("Dia de Visita do VDI"):

   | Visita | Entrega |
   |---|---|
   | SEG | terça (Tue) |
   | TER | quarta (Wed) |
   | QUA | quinta (Thu) |
   | QUI | sexta (Fri) |
   | SEX | segunda (Mon) |

   - Visitas em SAB e DOM não geram entrega.
   - Um cliente com várias visitas (ex.: "QUA/SEX/") recebe FREE em todos os dias correspondentes.
   - Com vários arquivos da rotina 0105070402, os dias de um mesmo cliente se somam.

As colunas são localizadas pelo nome do cabeçalho. Se uma delas faltar, a tela avisa qual é. A saída mantém o formato do Relatório Bees: UTF-8, `;`, sem aspas.

## Tirinha (substitui a planilha "TIRINHA PM")

A aba monta uma tirinha por mapa para entregar ao motorista, igual à aba "Plan1" da planilha, e imprime pelo botão **Imprimir** (cada tirinha fica inteira na página, com as colunas alinhadas para recortar). Nada é gravado no banco; as regras ficam em `app/Helpers/Tirinha.php`. Acesso: Logística e Administrador.

| Coluna | Origem |
|---|---|
| Mapas, Placas, KM planejado, Tempo | **03.11.49.02** (arquivo .csv.inf do Promax, .csv ou .xlsx, o que ia na aba "Promax"): Nro do Mapa, Placa, KM Prev. e Tempo Prev. (+almoço). Define as tirinhas e a ordem. |
| Inicial, Final, Emitidas | **03.02.31 colado da tela**: nota inicial, nota final e a 8ª coluna a partir do mapa (o mesmo `PROCV(...;notas;8)` da planilha) |
| Boleto | **03.02.37**: notas do mapa com "Nr Tit. Bco" preenchido (aba "03.02.37 CTRL+T") |
| Cartão crédito | **03.02.37** com a coluna "Cond. pagt": notas do mapa com condição **400** (aba "03.02.37 Cond.pgt 400") |
| Pix | **03.02.37** com a coluna "Cond. pagt": notas do mapa com condição **850** (aba "03.02.37 Cond.pgt 850") |

- **Revenda:** escolhida na tela (PM, LP ou AB; o usuário da Logística restrito a uma revenda só vê a dele). Só os mapas do 03.11.49.02 com a UNB da revenda (PM = 1, LP = 2, AB = 5) viram tirinha; os de outras revendas são ignorados com aviso. O 03.02.31 e o 03.02.37 são cruzados pelo número do mapa, então ficam restritos aos mesmos mapas.
- **Imagem do cartão de crédito (WhatsApp):** depois de gerar, o botão verde baixa (ou compartilha, no celular com HTTPS) um PNG no padrão do Farol só com **mapa e placa** dos mapas que têm nota de Cond. pagt 400, um por linha, na ordem das tirinhas, e o total de mapas. Usa o mesmo desenho de tabela de `public/assets/js/farol.js`.
- O 03.02.37 aceita vários arquivos de uma vez (o de boleto e o de condição de pagamento). Uma nota que aparece em mais de um arquivo, ou em várias linhas, conta uma vez.
- Linhas coladas do 03.02.31 que não começam com o número do mapa (cabeçalho, total) são ignoradas. O mapa pode vir com ponto de milhar ("398.891").
- Tempo aceita "03:15", "03:15:00" ou a fração de dia do Excel.
- Sem o 03.02.31 ou o 03.02.37 (ou sem a coluna "Cond. pagt"), as colunas correspondentes ficam em branco e a tela avisa.
- Conferido com o exemplo de 09/11/2026 (31 mapas): boleto 362, cartão 5 e pix 106, iguais à planilha; 28 tirinhas idênticas. Diferenças: nos mapas 398918, 398919 e 398920 a planilha mostra `#N/A` em Inicial/Final/Emitidas (fórmula fora do intervalo "notas"), e o sistema mostra os valores do 03.02.31; no mapa 398918 o tempo foi digitado à mão na planilha (03:00) por cima do Promax (07:26), e o sistema usa sempre o 03.11.49.02.

## Estrutura específica do sistema

- `app/Controllers`: Auth, Home (painel), Importacao, Critica, Prioridade, Consulta, Troca, Setor, Usuario e o `BaseController` (usuário logado, flash, Mesa e data de referência)
- `app/Models`: um Model por tabela. `ImportavelModel` é a base para gravação em lote dentro de transação.
- `app/Middleware`: controle de perfil (`UsuarioAtivoMiddleware`, `LogisticaMiddleware`, `AdminMiddleware`). São usados **depois** do `AuthMiddleware` do Framework.
- `app/Helpers`: `PlanilhaLeitor`, `Importador` (mapeamento de colunas) e `formatacao.php` (funções de formatação para as Views)

## Primeiro acesso

Com a tabela `usuarios` vazia, o sistema abre a tela **/primeiro-acesso** para criar o administrador. A tela deixa de existir assim que o primeiro usuário é criado.

## Esqueci minha senha

Na tela de login, o link **Esqueci minha senha** (`/esqueci-senha`) pede o e-mail do usuário e envia um link para `/redefinir-senha/{token}`, onde ele define a nova senha.

- O link vale por **1 hora** e só pode ser usado **uma vez**. Um novo pedido invalida o link anterior. Pedidos repetidos em menos de 2 minutos são ignorados.
- A mensagem exibida é sempre a mesma, exista ou não o e-mail, para não revelar quais e-mails estão cadastrados. Usuários inativos não recebem o link.
- O banco (`redefinicoes_senha`) guarda só o hash SHA-256 do token. O token puro existe apenas no e-mail.
- O link é montado com `APP_URL` do `.env`, nunca com o Host da requisição. O envio usa SMTP (`MAIL_*` no `.env`) via PHPMailer (`app/Helpers/Email.php`). Sem `APP_URL` ou SMTP configurado, nada é enviado e o erro fica em `storage/logs`.
- Com `APP_ENV=development`, o link também é gravado em `storage/logs/app-AAAA-MM-DD.log` (linha `[DESENVOLVIMENTO]`), para testar sem SMTP. Em produção o link nunca vai para o log.
- O administrador continua podendo trocar a senha de qualquer usuário na tela **Usuários**.

Para instalação, ver `GUIA_IMPLANTACAO.md`.
