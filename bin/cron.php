<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\MediaCleanupService;
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

try {
    $mediaCleanup = (new MediaCleanupService())->processPending(50);
    if ($mediaCleanup['skipped'] === 0) {
        echo sprintf(
            "[%s] Cloudinary: %d arquivo(s) removido(s), %d falha(s) para tentar novamente.\n",
            date('Y-m-d H:i:s'),
            $mediaCleanup['deleted'],
            $mediaCleanup['failed']
        );
    }
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("[%s] Limpeza Cloudinary: %s\n", date('Y-m-d H:i:s'), $exception->getMessage()));
}
