<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;

final class ManagementController
{
    public function index(): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();

        $summary = $pdo->prepare(
            'SELECT COUNT(*) total, '
            . 'SUM(status="completed") completed, SUM(status="cancelled") cancelled, SUM(status="no_show") no_show, '
            . 'COALESCE(SUM(CASE WHEN status="completed" THEN price ELSE 0 END),0) revenue '
            . 'FROM appointments WHERE establishment_id=:id AND starts_at>=DATE_FORMAT(CURDATE(),"%Y-%m-01") '
            . 'AND starts_at<DATE_ADD(LAST_DAY(CURDATE()),INTERVAL 1 DAY)'
        );
        $summary->execute(['id'=>$establishmentId]);
        $month = $summary->fetch() ?: [];

        $lastRevenue = $pdo->prepare('SELECT COALESCE(SUM(price),0) FROM appointments WHERE establishment_id=:id AND status="completed" AND starts_at>=DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),"%Y-%m-01") AND starts_at<DATE_FORMAT(CURDATE(),"%Y-%m-01")');
        $lastRevenue->execute(['id'=>$establishmentId]);
        $previousRevenue = (float) $lastRevenue->fetchColumn();
        $currentRevenue = (float) ($month['revenue'] ?? 0);

        $newCustomers = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE establishment_id=:id AND created_at>=DATE_FORMAT(CURDATE(),"%Y-%m-01")');
        $newCustomers->execute(['id'=>$establishmentId]);

        $waitlist = $pdo->prepare('SELECT COUNT(*) FROM waitlist_entries WHERE establishment_id=:id AND status="waiting"');
        $waitlist->execute(['id'=>$establishmentId]);
        $matches = $pdo->prepare('SELECT COUNT(*) FROM waitlist_matches WHERE establishment_id=:id AND status IN ("available","queued","notified") AND slot_start>=NOW()');
        $matches->execute(['id'=>$establishmentId]);
        $notifications = $pdo->prepare('SELECT COUNT(*) FROM notification_outbox WHERE establishment_id=:id AND status="pending"');
        $notifications->execute(['id'=>$establishmentId]);

        $topServices = $pdo->prepare('SELECT s.name,COUNT(*) total,COALESCE(SUM(a.price),0) revenue FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.establishment_id=:id AND a.status="completed" AND a.starts_at>=DATE_FORMAT(CURDATE(),"%Y-%m-01") GROUP BY s.id,s.name ORDER BY total DESC,revenue DESC LIMIT 5');
        $topServices->execute(['id'=>$establishmentId]);
        $topProfessionals = $pdo->prepare('SELECT u.name,COUNT(*) total,COALESCE(SUM(a.price),0) revenue FROM appointments a JOIN users u ON u.id=a.employee_user_id WHERE a.establishment_id=:id AND a.status="completed" AND a.starts_at>=DATE_FORMAT(CURDATE(),"%Y-%m-01") GROUP BY u.id,u.name ORDER BY total DESC,revenue DESC LIMIT 5');
        $topProfessionals->execute(['id'=>$establishmentId]);

        $trend = $pdo->prepare('SELECT DATE_FORMAT(starts_at,"%Y-%m") period,COALESCE(SUM(price),0) revenue,COUNT(*) total FROM appointments WHERE establishment_id=:id AND status="completed" AND starts_at>=DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 5 MONTH),"%Y-%m-01") GROUP BY DATE_FORMAT(starts_at,"%Y-%m") ORDER BY period');
        $trend->execute(['id'=>$establishmentId]);

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
