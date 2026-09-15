<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingSettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceMediaController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WaitlistController;

/** @var Router $router */
$router->get('/', [HomeController::class, 'index']);
$router->get('/encontre', [HomeController::class, 'find']);
$router->get('/estabelecimentos/{slug}', [HomeController::class, 'establishment']);
$router->get('/estabelecimentos/{slug}/horarios', [BookingController::class, 'availability']);
$router->post('/estabelecimentos/{slug}/agendar', [BookingController::class, 'store']);
$router->post('/estabelecimentos/{slug}/lista-espera', [WaitlistController::class, 'store']);

$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'authenticate']);
$router->get('/cadastro', [AuthController::class, 'register']);
$router->post('/cadastro', [AuthController::class, 'storeRegistration']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/painel', [PanelController::class, 'index']);
$router->get('/painel/perfil', [ProfileController::class, 'index']);
$router->post('/painel/perfil', [ProfileController::class, 'update']);
$router->post('/painel/perfil/foto', [ProfileController::class, 'uploadAvatar']);
$router->post('/painel/perfil/foto/remover', [ProfileController::class, 'deleteAvatar']);
$router->get('/painel/agenda', [AgendaController::class, 'index']);
$router->get('/painel/indicadores', [ManagementController::class, 'index']);
$router->get('/painel/lista-espera', [WaitlistController::class, 'index']);
$router->post('/painel/lista-espera/verificar', [WaitlistController::class, 'scan']);
$router->post('/painel/lista-espera/{id}/status', [WaitlistController::class, 'status']);
$router->get('/painel/notificacoes', [NotificationController::class, 'index']);
$router->post('/painel/notificacoes/preparar', [NotificationController::class, 'prepare']);
$router->post('/painel/notificacoes/configuracoes', [NotificationController::class, 'storeSettings']);
$router->post('/painel/notificacoes/{id}/enviada', [NotificationController::class, 'markSent']);

$router->get('/painel/clientes', [CustomerController::class, 'index']);
$router->post('/painel/clientes', [CustomerController::class, 'store']);
$router->get('/painel/clientes/{id}', [CustomerController::class, 'show']);
$router->post('/painel/clientes/{id}', [CustomerController::class, 'update']);

$router->get('/painel/servicos', [PanelController::class, 'services']);
$router->post('/painel/servicos', [PanelController::class, 'storeService']);
$router->post('/painel/servicos/{id}', [PanelController::class, 'updateService']);
$router->post('/painel/servicos/{id}/imagem', [ServiceMediaController::class, 'upload']);
$router->post('/painel/servicos/{id}/imagem/remover', [ServiceMediaController::class, 'delete']);

$router->get('/painel/aparencia', [AppearanceController::class, 'index']);
$router->post('/painel/aparencia/logo', [AppearanceController::class, 'uploadLogo']);
$router->post('/painel/aparencia/logo/remover', [AppearanceController::class, 'deleteLogo']);
$router->post('/painel/aparencia/capa', [AppearanceController::class, 'uploadCover']);
$router->post('/painel/aparencia/capa/remover', [AppearanceController::class, 'deleteCover']);

$router->get('/painel/equipe', [TeamController::class, 'index']);
$router->post('/painel/equipe', [TeamController::class, 'store']);
$router->post('/painel/equipe/{id}/status', [TeamController::class, 'toggleStatus']);
$router->get('/painel/equipe/{id}/horarios', [TeamController::class, 'schedule']);
$router->post('/painel/equipe/{id}/horarios', [TeamController::class, 'storeSchedule']);
$router->post('/painel/equipe/{id}/bloqueios', [TeamController::class, 'storeBlock']);
$router->post('/painel/equipe/{id}/bloqueios/{blockId}/remover', [TeamController::class, 'deleteBlock']);

$router->get('/painel/horarios', [ScheduleController::class, 'index']);
$router->post('/painel/horarios', [ScheduleController::class, 'storeWeeklyHours']);
$router->post('/painel/horarios/especiais', [ScheduleController::class, 'storeSpecialHours']);
$router->post('/painel/horarios/especiais/{id}/remover', [ScheduleController::class, 'deleteSpecialHours']);

$router->get('/painel/configuracoes/agendamento', [BookingSettingsController::class, 'index']);
$router->post('/painel/configuracoes/agendamento', [BookingSettingsController::class, 'store']);

$router->get('/painel/agendamentos', [PanelController::class, 'appointments']);
$router->get('/painel/agendamentos/novo', [AppointmentController::class, 'create']);
$router->get('/painel/agendamentos/novo/horarios', [AppointmentController::class, 'newAvailability']);
$router->post('/painel/agendamentos/novo', [AppointmentController::class, 'store']);
$router->post('/painel/agendamentos/{id}/status', [AppointmentController::class, 'status']);
$router->post('/painel/agendamentos/{id}/cancelar', [BookingController::class, 'cancel']);
$router->get('/painel/agendamentos/{id}/editar', [AppointmentController::class, 'edit']);
$router->get('/painel/agendamentos/{id}/horarios', [AppointmentController::class, 'availability']);
$router->post('/painel/agendamentos/{id}/editar', [AppointmentController::class, 'update']);
