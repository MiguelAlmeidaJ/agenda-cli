<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\NotificationDispatcher;
use App\Services\NotificationService;
use App\Services\WaitlistAutomationService;

load_env(base_path('.env'));
date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));

$pdo = Database::connection();
$establishments = $pdo->query('SELECT id,name FROM establishments WHERE active=1 ORDER BY id')->fetchAll();
$waitlist = new WaitlistAutomationService();
$notifications = new NotificationService();
$dispatcher = new NotificationDispatcher();

foreach ($establishments as $establishment) {
    $id = (int) $establishment['id'];
    try {
        $matches = $waitlist->scanEstablishment($id);
        $queued = $notifications->scheduleEstablishment($id);
        $delivery = $dispatcher->dispatchEstablishment($id);
        echo sprintf(
            "[%s] %s: %d match(es), %d mensagem(ns) de espera, %d preparada(s), %d enviada(s), %d falha(s).\n",
            date('Y-m-d H:i:s'),
            $establishment['name'],
            $matches['matched'],
            $matches['queued'],
            array_sum($queued),
            $delivery['sent'],
            $delivery['failed']
        );
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), $establishment['name'], $exception->getMessage()));
    }
}
