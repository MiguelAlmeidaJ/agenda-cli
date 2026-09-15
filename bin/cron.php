<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\NotificationService;
use App\Services\WaitlistAutomationService;

load_env(base_path('.env'));
date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));

$pdo = Database::connection();
$establishments = $pdo->query('SELECT id,name FROM establishments WHERE active=1 ORDER BY id')->fetchAll();
$waitlist = new WaitlistAutomationService();
$notifications = new NotificationService();

foreach ($establishments as $establishment) {
    $id = (int) $establishment['id'];
    try {
        $matches = $waitlist->scanEstablishment($id);
        $queued = $notifications->scheduleEstablishment($id);
        $notificationCount = array_sum($queued);
        echo sprintf("[%s] %s: %d match(es), %d mensagem(ns) de espera, %d notificacao(oes) agendada(s).\n", date('Y-m-d H:i:s'), $establishment['name'], $matches['matched'], $matches['queued'], $notificationCount);
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), $establishment['name'], $exception->getMessage()));
    }
}
