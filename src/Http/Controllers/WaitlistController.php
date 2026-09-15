<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\CustomerService;
use App\Services\WaitlistAutomationService;
use DateTimeImmutable;

final class WaitlistController
{
    public function store(string $slug): void
    {
        Auth::requireRole(['client']);
        Csrf::validate($_POST['_csrf'] ?? null);

        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $providerId = (int) ($_POST['preferred_employee_user_id'] ?? 0);
        $date = trim((string) ($_POST['desired_date'] ?? ''));
        $period = (string) ($_POST['time_period'] ?? 'any');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (!in_array($period, ['any', 'morning', 'afternoon', 'evening'], true)) {
            $period = 'any';
        }

        $pdo = Database::connection();
        $establishmentStmt = $pdo->prepare('SELECT id, timezone FROM establishments WHERE slug = :slug AND active = 1 LIMIT 1');
        $establishmentStmt->execute(['slug' => $slug]);
        $establishment = $establishmentStmt->fetch();
        if (!$establishment || $serviceId <= 0 || $date === '') {
            flash('error', 'Escolha um serviço e uma data para entrar na lista de espera.');
            redirect('/estabelecimentos/' . $slug);
        }

        $settingsStmt = $pdo->prepare('SELECT allow_waitlist FROM booking_settings WHERE establishment_id = :establishment LIMIT 1');
        $settingsStmt->execute(['establishment' => $establishment['id']]);
        $allowWaitlist = $settingsStmt->fetchColumn();
        if ($allowWaitlist !== false && (int) $allowWaitlist !== 1) {
            flash('error', 'A lista de espera não está disponível para este estabelecimento.');
            redirect('/estabelecimentos/' . $slug);
        }

        try {
            $desiredDate = new DateTimeImmutable($date);
            $today = new DateTimeImmutable('today');
        } catch (\Throwable) {
            flash('error', 'Data inválida para lista de espera.');
            redirect('/estabelecimentos/' . $slug);
        }
        if ($desiredDate < $today) {
            flash('error', 'Escolha uma data futura para a lista de espera.');
            redirect('/estabelecimentos/' . $slug);
        }

        $service = $pdo->prepare('SELECT id FROM services WHERE id = :service AND establishment_id = :establishment AND active = 1 LIMIT 1');
        $service->execute(['service' => $serviceId, 'establishment' => $establishment['id']]);
        if (!$service->fetchColumn()) {
            flash('error', 'Serviço indisponível.');
            redirect('/estabelecimentos/' . $slug);
        }

        if ($providerId > 0) {
            $provider = $pdo->prepare(
                'SELECT 1 FROM employee_services es JOIN services s ON s.id = es.service_id '
                . 'WHERE es.employee_user_id = :provider AND es.service_id = :service AND s.establishment_id = :establishment LIMIT 1'
            );
            $provider->execute(['provider' => $providerId, 'service' => $serviceId, 'establishment' => $establishment['id']]);
            if (!$provider->fetchColumn()) $providerId = 0;
        }

        $customerId = (new CustomerService())->ensureForUser($pdo, (int) $establishment['id'], (int) Auth::id());
        $duplicate = $pdo->prepare(
            'SELECT id FROM waitlist_entries WHERE establishment_id = :establishment AND service_id = :service '
            . 'AND customer_id = :customer AND desired_date = :date AND status IN ("waiting", "notified") LIMIT 1'
        );
        $duplicate->execute(['establishment'=>$establishment['id'],'service'=>$serviceId,'customer'=>$customerId,'date'=>$date]);
        if ($duplicate->fetchColumn()) {
            flash('success', 'Você já está na lista de espera para esse serviço e data.');
            redirect('/estabelecimentos/' . $slug);
        }

        $insert = $pdo->prepare(
            'INSERT INTO waitlist_entries (establishment_id, service_id, customer_id, preferred_employee_user_id, desired_date, time_period, notes) '
            . 'VALUES (:establishment, :service, :customer, :provider, :date, :period, :notes)'
        );
        $insert->execute([
            'establishment'=>$establishment['id'],'service'=>$serviceId,'customer'=>$customerId,
            'provider'=>$providerId>0?$providerId:null,'date'=>$date,'period'=>$period,'notes'=>$notes!==''?$notes:null,
        ]);

        flash('success', 'Você entrou na lista de espera. O estabelecimento poderá entrar em contato quando surgir uma vaga.');
        redirect('/estabelecimentos/' . $slug);
    }

    public function index(): void
    {
        Auth::requireRole(['owner', 'employee']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $stmt = Database::connection()->prepare(
            'SELECT w.*, s.name AS service_name, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, '
            . 'u.name AS provider_name, wm.slot_start AS matched_slot, wm.status AS match_status, mu.name AS matched_provider_name '
            . 'FROM waitlist_entries w JOIN services s ON s.id=w.service_id JOIN customers c ON c.id=w.customer_id '
            . 'LEFT JOIN users u ON u.id=w.preferred_employee_user_id '
            . 'LEFT JOIN waitlist_matches wm ON wm.id=(SELECT wm2.id FROM waitlist_matches wm2 WHERE wm2.waitlist_entry_id=w.id AND wm2.status IN ("available","queued","notified") AND wm2.slot_start>=NOW() ORDER BY wm2.slot_start ASC LIMIT 1) '
            . 'LEFT JOIN users mu ON mu.id=wm.employee_user_id '
            . 'WHERE w.establishment_id=:establishment ORDER BY FIELD(w.status,"waiting","notified","converted","cancelled"),w.desired_date,w.created_at'
        );
        $stmt->execute(['establishment'=>$establishmentId]);
        View::render('panel/waitlist', ['title'=>'Lista de espera','entries'=>$stmt->fetchAll()]);
    }

    public function scan(): void
    {
        Auth::requireRole(['owner','employee']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $result = (new WaitlistAutomationService())->scanEstablishment(TenantContext::requireEstablishmentId());
        flash('success', $result['matched'] . ' compatibilidade(s) encontrada(s); ' . $result['queued'] . ' mensagem(ns) adicionada(s) à caixa de saída.');
        redirect('/painel/lista-espera');
    }

    public function status(string $id): void
    {
        Auth::requireRole(['owner', 'employee']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['waiting', 'notified', 'converted', 'cancelled'], true)) {
            flash('error', 'Status inválido para a lista de espera.');
            redirect('/painel/lista-espera');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE waitlist_entries SET status=:status WHERE id=:id AND establishment_id=:establishment');
        $stmt->execute(['status'=>$status,'id'=>(int)$id,'establishment'=>$establishmentId]);
        if ($stmt->rowCount() && $status === 'converted') {
            $pdo->prepare('UPDATE waitlist_matches SET status="converted" WHERE waitlist_entry_id=:id AND establishment_id=:establishment AND status IN ("available","queued","notified")')->execute(['id'=>(int)$id,'establishment'=>$establishmentId]);
        } elseif ($stmt->rowCount() && $status === 'cancelled') {
            $pdo->prepare('UPDATE waitlist_matches SET status="expired" WHERE waitlist_entry_id=:id AND establishment_id=:establishment AND status IN ("available","queued")')->execute(['id'=>(int)$id,'establishment'=>$establishmentId]);
        }
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Lista de espera atualizada.' : 'Registro não encontrado.');
        redirect('/painel/lista-espera');
    }
}
