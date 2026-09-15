<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

load_env(base_path('.env'));
date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => str_starts_with((string) env('APP_URL', ''), 'https://'),
]);
session_start();

use App\Core\Router;

$router = new Router();
require base_path('routes/web.php');
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
