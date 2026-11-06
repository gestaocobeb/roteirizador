<?php

use App\Controllers\AprovacaoTrocaController;
use App\Controllers\AuthController;
use App\Controllers\BeesLoatController;
use App\Controllers\ConsultaController;
use App\Controllers\CriticaController;
use App\Controllers\FarolController;
use App\Controllers\HomeController;
use App\Controllers\ImportacaoController;
use App\Controllers\PrioridadeController;
use App\Controllers\RevendaController;
use App\Controllers\SetorController;
use App\Controllers\TrocaController;
use App\Controllers\UsuarioController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Router;
use App\Middleware\AdminMiddleware;
use App\Middleware\LogisticaMiddleware;
use App\Middleware\UsuarioAtivoMiddleware;

/** @var Router $router */

// Grupos de middlewares (a ordem importa: autenticação, CSRF, perfil).
$logado = [AuthMiddleware::class, UsuarioAtivoMiddleware::class];
$logadoPost = [AuthMiddleware::class, CsrfMiddleware::class, UsuarioAtivoMiddleware::class];
$logistica = [AuthMiddleware::class, LogisticaMiddleware::class];
$logisticaPost = [AuthMiddleware::class, CsrfMiddleware::class, LogisticaMiddleware::class];
$admin = [AuthMiddleware::class, AdminMiddleware::class];
$adminPost = [AuthMiddleware::class, CsrfMiddleware::class, AdminMiddleware::class];

// Autenticação
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->post('/logout', [AuthController::class, 'logout'], $logadoPost);
$router->get('/primeiro-acesso', [AuthController::class, 'primeiroAcessoForm']);
$router->post('/primeiro-acesso', [AuthController::class, 'primeiroAcesso'], [CsrfMiddleware::class]);
$router->get('/esqueci-senha', [AuthController::class, 'esqueciSenhaForm']);
$router->post('/esqueci-senha', [AuthController::class, 'esqueciSenha'], [CsrfMiddleware::class]);
$router->get('/redefinir-senha/{token}', [AuthController::class, 'redefinirSenhaForm']);
$router->post('/redefinir-senha', [AuthController::class, 'redefinirSenha'], [CsrfMiddleware::class]);

// Painel e consulta
$router->get('/', [HomeController::class, 'index'], $logado);
$router->post('/revenda', [RevendaController::class, 'trocar'], $logadoPost);
$router->post('/sala', [RevendaController::class, 'sala'], $logadoPost);
$router->get('/consulta', [ConsultaController::class, 'index'], $logado);
$router->get('/troca', [TrocaController::class, 'index'], $logado);
$router->get('/aprovacao-troca', [AprovacaoTrocaController::class, 'index'], $logistica);
$router->post('/aprovacao-troca/justificativa', [AprovacaoTrocaController::class, 'justificar'], $logisticaPost);
$router->post('/aprovacao-troca/verba-extra', [AprovacaoTrocaController::class, 'salvarVerbaExtra'], $adminPost);
$router->post('/aprovacao-troca/verba-extra/{id}/remover', [AprovacaoTrocaController::class, 'removerVerbaExtra'], $adminPost);

// Crítica GOD
$router->get('/critica', [CriticaController::class, 'index'], $logado);
$router->post('/critica/decisao', [CriticaController::class, 'decidir'], $logisticaPost);

// Prioridade
$router->get('/prioridade', [PrioridadeController::class, 'index'], $logado);
$router->post('/prioridade', [PrioridadeController::class, 'adicionar'], $logadoPost);
$router->post('/prioridade/{id}/remover', [PrioridadeController::class, 'remover'], $logadoPost);
$router->post('/prioridade/transferir', [PrioridadeController::class, 'transferir'], $logadoPost);
$router->post('/prioridade/transferencias/{id}/remover', [PrioridadeController::class, 'removerTransferencia'], $logadoPost);

// Logística: importações e setores
$router->get('/importacoes', [ImportacaoController::class, 'index'], $logistica);
$router->post('/importacoes', [ImportacaoController::class, 'store'], $logisticaPost);
$router->post('/importacoes/{id}/excluir', [ImportacaoController::class, 'excluir'], $logisticaPost);
$router->get('/setores', [SetorController::class, 'index'], $logistica);
$router->post('/setores', [SetorController::class, 'criar'], $logisticaPost);
// "/setores/mesas" antes de "/setores/{id}" (o Router usa a primeira rota que casar).
$router->post('/setores/mesas', [SetorController::class, 'criarMesa'], $adminPost);
$router->post('/setores/{id}', [SetorController::class, 'atualizar'], $logisticaPost);
$router->post('/setores/mesa/{mesa}/limites', [SetorController::class, 'limitesMesa'], $adminPost);

// Bees Loat: CSV de dias de entrega do BEES
$router->get('/bees-loat', [BeesLoatController::class, 'index'], $logado);
$router->post('/bees-loat/gerar', [BeesLoatController::class, 'gerar'], $logadoPost);

// Farol: painel do dia por revenda (Logística)
$router->get('/farol', [FarolController::class, 'index'], $logistica);
$router->post('/farol/dados', [FarolController::class, 'salvar'], $logisticaPost);
$router->get('/farol/buffer/{revenda}', [FarolController::class, 'exportarBuffer'], $logistica);

// Administração de usuários
$router->get('/usuarios', [UsuarioController::class, 'index'], $admin);
$router->post('/usuarios', [UsuarioController::class, 'criar'], $adminPost);
$router->post('/usuarios/{id}/status', [UsuarioController::class, 'status'], $adminPost);
$router->post('/usuarios/{id}/senha', [UsuarioController::class, 'senha'], $adminPost);
$router->post('/usuarios/{id}/abas', [UsuarioController::class, 'abas'], $adminPost);
$router->post('/usuarios/{id}/revenda', [UsuarioController::class, 'revenda'], $adminPost);
$router->post('/usuarios/{id}/salas', [UsuarioController::class, 'salas'], $adminPost);
