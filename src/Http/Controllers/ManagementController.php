<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\EstablishmentClock;

final class ManagementController
{
    public function index(): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        $clock = new EstablishmentClock();
        $now = $clock->now($pdo, $establishmentId);
        [$monthStart, $monthEnd] = $clock->monthBounds($now);
        [$previousStart, $previousEnd] = $clock->previousMonthBounds($now);
        $trendStart = $monthStart->modify('-5 months');

        $summary = $pdo->prepare(
            'SELECT COUNT(*) total, '
            . 'SUM(status="completed") completed, SUM(status="cancelled") cancelled, SUM(status="no_show") no_show, '
            . 'COALESCE(SUM(CASE WHEN status="completed" THEN price ELSE 0 END),0) revenue '
            . 'FROM appointments WHERE establishment_id=:id AND starts_at>=:month_start '
            . 'AND starts_at<:month_end'
        );
        $summary->execute([
            'id'=>$establishmentId,
            'month_start'=>$clock->sql($monthStart),
            'month_end'=>$clock->sql($monthEnd),
        ]);
        $month = $summary->fetch() ?: [];

        $lastRevenue = $pdo->prepare('SELECT COALESCE(SUM(price),0) FROM appointments WHERE establishment_id=:id AND status="completed" AND starts_at>=:previous_start AND starts_at<:previous_end');
        $lastRevenue->execute([
            'id'=>$establishmentId,
            'previous_start'=>$clock->sql($previousStart),
            'previous_end'=>$clock->sql($previousEnd),
        ]);
        $previousRevenue = (float) $lastRevenue->fetchColumn();
        $currentRevenue = (float) ($month['revenue'] ?? 0);

        $newCustomers = $pdo->prepare(
            'SELECT COUNT(*) FROM (SELECT customer_id,MIN(starts_at) first_visit FROM appointments '
            . 'WHERE establishment_id=:id AND customer_id IS NOT NULL GROUP BY customer_id '
            . 'HAVING first_visit>=:month_start AND first_visit<:month_end) first_visits'
        );
        $newCustomers->execute([
            'id'=>$establishmentId,
            'month_start'=>$clock->sql($monthStart),
            'month_end'=>$clock->sql($monthEnd),
        ]);

        $waitlist = $pdo->prepare('SELECT COUNT(*) FROM waitlist_entries WHERE establishment_id=:id AND status="waiting"');
        $waitlist->execute(['id'=>$establishmentId]);
        $matches = $pdo->prepare('SELECT COUNT(DISTINCT waitlist_entry_id) FROM waitlist_matches WHERE establishment_id=:id AND status IN ("available","queued","notified") AND slot_start>=:now');
        $matches->execute(['id'=>$establishmentId,'now'=>$clock->sql($now)]);
        $notifications = $pdo->prepare('SELECT COUNT(*) FROM notification_outbox WHERE establishment_id=:id AND status="pending"');
        $notifications->execute(['id'=>$establishmentId]);

        $topServices = $pdo->prepare('SELECT s.name,COUNT(*) total,COALESCE(SUM(a.price),0) revenue FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.establishment_id=:id AND a.status="completed" AND a.starts_at>=:month_start AND a.starts_at<:month_end GROUP BY s.id,s.name ORDER BY total DESC,revenue DESC LIMIT 5');
        $topServices->execute(['id'=>$establishmentId,'month_start'=>$clock->sql($monthStart),'month_end'=>$clock->sql($monthEnd)]);
        $topProfessionals = $pdo->prepare('SELECT u.name,COUNT(*) total,COALESCE(SUM(a.price),0) revenue FROM appointments a JOIN users u ON u.id=a.employee_user_id WHERE a.establishment_id=:id AND a.status="completed" AND a.starts_at>=:month_start AND a.starts_at<:month_end GROUP BY u.id,u.name ORDER BY total DESC,revenue DESC LIMIT 5');
        $topProfessionals->execute(['id'=>$establishmentId,'month_start'=>$clock->sql($monthStart),'month_end'=>$clock->sql($monthEnd)]);

        $trend = $pdo->prepare('SELECT DATE_FORMAT(starts_at,"%Y-%m") period,COALESCE(SUM(price),0) revenue,COUNT(*) total FROM appointments WHERE establishment_id=:id AND status="completed" AND starts_at>=:trend_start AND starts_at<:trend_end GROUP BY DATE_FORMAT(starts_at,"%Y-%m") ORDER BY period');
        $trend->execute(['id'=>$establishmentId,'trend_start'=>$clock->sql($trendStart),'trend_end'=>$clock->sql($monthEnd)]);

        $completed = (int) ($month['completed'] ?? 0);
        $noShow = (int) ($month['no_show'] ?? 0);
        $cancelled = (int) ($month['cancelled'] ?? 0);
        $baseAttendance = max(1, $completed + $noShow);
        $baseBookings = max(1, $completed + $noShow + $cancelled);
        $revenueDelta = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : ($currentRevenue > 0 ? 100.0 : 0.0);

        View::render('panel/management', [
            'title'=>'Indicadores',
            'metrics'=>[
                'revenue'=>$currentRevenue,
                'revenue_delta'=>$revenueDelta,
                'completed'=>$completed,
                'new_customers'=>(int) $newCustomers->fetchColumn(),
                'no_show_rate'=>($noShow / $baseAttendance) * 100,
                'cancellation_rate'=>($cancelled / $baseBookings) * 100,
                'waitlist'=>(int) $waitlist->fetchColumn(),
                'matches'=>(int) $matches->fetchColumn(),
                'pending_notifications'=>(int) $notifications->fetchColumn(),
            ],
            'topServices'=>$topServices->fetchAll(),
            'topProfessionals'=>$topProfessionals->fetchAll(),
            'trend'=>$trend->fetchAll(),
        ]);
    }
}
