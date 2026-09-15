<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PanelController;

/** @var Router $router */
$router->get('/', [HomeController::class, 'index']);
$router->get('/encontre', [HomeController::class, 'find']);
$router->get('/estabelecimentos/{slug}', [HomeController::class, 'establishment']);
$router->get('/estabelecimentos/{slug}/horarios', [BookingController::class, 'availability']);
$router->post('/estabelecimentos/{slug}/agendar', [BookingController::class, 'store']);

$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'authenticate']);
$router->get('/cadastro', [AuthController::class, 'register']);
$router->post('/cadastro', [AuthController::class, 'storeRegistration']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/painel', [PanelController::class, 'index']);
$router->get('/painel/servicos', [PanelController::class, 'services']);
$router->post('/painel/servicos', [PanelController::class, 'storeService']);
$router->post('/painel/servicos/{id}', [PanelController::class, 'updateService']);
$router->get('/painel/horarios', [PanelController::class, 'hours']);
$router->post('/painel/horarios', [PanelController::class, 'storeHours']);
$router->get('/painel/agendamentos', [PanelController::class, 'appointments']);
