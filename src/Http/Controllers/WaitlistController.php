<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\AvailabilityService;
use App\Services\CustomerService;
use App\Services\EstablishmentClock;
use App\Services\WaitlistAutomationService;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

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
            $clock = new EstablishmentClock();
            $desiredDate = $clock->inTimezone((string) $establishment['timezone'], $date . ' 00:00:00');
            $today = $clock->inTimezone((string) $establishment['timezone'])->setTime(0, 0, 0);
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


    public function mine(): void
    {
        Auth::requireRole(['client']);
        $pdo = Database::connection();

        $establishments = $pdo->prepare(
            'SELECT DISTINCT w.establishment_id FROM waitlist_entries w '
            . 'JOIN customers c ON c.id=w.customer_id WHERE c.user_id=:user AND w.status IN ("waiting","notified")'
        );
        $establishments->execute(['user' => Auth::id()]);
        $automation = new WaitlistAutomationService();
        foreach ($establishments->fetchAll() as $row) {
            $automation->expireOffers((int) $row['establishment_id']);
        }

        $stmt = $pdo->prepare(
            'SELECT w.*,e.name establishment_name,e.slug establishment_slug,s.name service_name,'
            . 'u.name preferred_provider_name,wm.id match_id,wm.slot_start,wm.status match_status,'
            . 'wm.offer_expires_at,mu.name matched_provider_name '
            . 'FROM waitlist_entries w JOIN customers c ON c.id=w.customer_id '
            . 'JOIN establishments e ON e.id=w.establishment_id JOIN services s ON s.id=w.service_id '
            . 'LEFT JOIN users u ON u.id=w.preferred_employee_user_id '
            . 'LEFT JOIN waitlist_matches wm ON wm.id=(SELECT wm2.id FROM waitlist_matches wm2 '
            . 'WHERE wm2.waitlist_entry_id=w.id AND wm2.status IN ("available","queued","notified") '
            . 'ORDER BY wm2.slot_start ASC LIMIT 1) '
            . 'LEFT JOIN users mu ON mu.id=wm.employee_user_id '
            . 'WHERE c.user_id=:user ORDER BY FIELD(w.status,"notified","waiting","converted","cancelled"),w.desired_date DESC,w.created_at DESC'
        );
        $stmt->execute(['user' => Auth::id()]);

        View::render('panel/my_waitlist', [
            'title' => 'Minha lista de espera',
            'entries' => $stmt->fetchAll(),
        ]);
    }

    public function cancelMine(string $id): void
    {
        Auth::requireRole(['client']);
        Csrf::validate($_POST['_csrf'] ?? null);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT w.id,w.status FROM waitlist_entries w JOIN customers c ON c.id=w.customer_id '
                . 'WHERE w.id=:id AND c.user_id=:user LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['id' => (int) $id, 'user' => Auth::id()]);
            $entry = $stmt->fetch();
            if (!$entry) {
                throw new \RuntimeException('Registro da lista de espera não encontrado.');
            }
            if (!in_array($entry['status'], ['waiting', 'notified'], true)) {
                throw new \RuntimeException('Esta entrada da lista de espera já foi finalizada.');
            }

            $pdo->prepare('UPDATE waitlist_entries SET status="cancelled" WHERE id=:id')
                ->execute(['id' => (int) $id]);
            $pdo->prepare(
                'UPDATE waitlist_matches SET status="expired" '
                . 'WHERE waitlist_entry_id=:id AND status IN ("available","queued","notified")'
            )->execute(['id' => (int) $id]);
            $pdo->prepare(
                'UPDATE notification_outbox SET status="cancelled" '
                . 'WHERE waitlist_entry_id=:id AND status="pending"'
            )->execute(['id' => (int) $id]);

            $pdo->commit();
            flash('success', 'Você saiu da lista de espera.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível atualizar a lista de espera.');
        }

        redirect('/painel/minha-lista-espera');
    }

    public function acceptMine(string $id): void
    {
        Auth::requireRole(['client']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $matchId = (int) ($_POST['match_id'] ?? 0);

        try {
            if ($matchId <= 0) {
                throw new \RuntimeException('A vaga selecionada não está mais disponível.');
            }
            $appointmentId = $this->convertMatch($matchId, (int) Auth::id(), null);
            flash('success', 'Vaga confirmada. Seu agendamento foi criado.');
            redirect('/painel/agendamentos');
        } catch (\Throwable $exception) {
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível confirmar a vaga.');
            redirect('/painel/minha-lista-espera');
        }
    }

    public function offer(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $this->offerNotFound();
            return;
        }

        if (!Auth::check()) {
            $_SESSION['after_login'] = '/lista-espera/oferta/' . $token;
            flash('error', 'Entre na sua conta para confirmar a vaga.');
            redirect('/login');
        }
        Auth::requireRole(['client']);

        $hash = hash('sha256', $token);
        $stmt = Database::connection()->prepare(
            'SELECT wm.id,wm.slot_start,wm.status,wm.offer_expires_at,w.status waitlist_status,'
            . 'e.name establishment_name,e.timezone,s.name service_name,u.name employee_name '
            . 'FROM waitlist_matches wm JOIN waitlist_entries w ON w.id=wm.waitlist_entry_id '
            . 'JOIN customers c ON c.id=w.customer_id JOIN establishments e ON e.id=wm.establishment_id '
            . 'JOIN services s ON s.id=w.service_id JOIN users u ON u.id=wm.employee_user_id '
            . 'WHERE wm.offer_token_hash=:hash AND c.user_id=:user LIMIT 1'
        );
        $stmt->execute(['hash' => $hash, 'user' => Auth::id()]);
        $offer = $stmt->fetch();

        $clock = new EstablishmentClock();
        $timezone = (string) ($offer['timezone'] ?? env('APP_TIMEZONE', 'America/Sao_Paulo'));
        $now = $clock->inTimezone($timezone);
        $slotStart = $offer ? $clock->inTimezone($timezone, (string) $offer['slot_start']) : null;
        $expiresAt = $offer && !empty($offer['offer_expires_at'])
            ? $clock->inTimezone($timezone, (string) $offer['offer_expires_at'])
            : null;

        if (!$offer || !in_array($offer['status'], ['queued', 'notified'], true)
            || !in_array($offer['waitlist_status'], ['waiting', 'notified'], true)
            || $slotStart === null || $slotStart <= $now
            || ($expiresAt !== null && $expiresAt <= $now)) {
            flash('error', 'Esta oferta expirou ou já não está disponível.');
            redirect('/painel/minha-lista-espera');
        }

        View::render('waitlist_offer', [
            'title' => 'Confirmar vaga',
            'offer' => $offer,
            'token' => $token,
        ]);
    }

    public function acceptOffer(string $token): void
    {
        Auth::requireRole(['client']);
        Csrf::validate($_POST['_csrf'] ?? null);

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $this->offerNotFound();
            return;
        }

        $hash = hash('sha256', $token);
        $stmt = Database::connection()->prepare(
            'SELECT wm.id FROM waitlist_matches wm JOIN waitlist_entries w ON w.id=wm.waitlist_entry_id '
            . 'JOIN customers c ON c.id=w.customer_id WHERE wm.offer_token_hash=:hash AND c.user_id=:user LIMIT 1'
        );
        $stmt->execute(['hash' => $hash, 'user' => Auth::id()]);
        $matchId = (int) ($stmt->fetchColumn() ?: 0);

        try {
            if ($matchId <= 0) {
                throw new \RuntimeException('Oferta não encontrada.');
            }
            $this->convertMatch($matchId, (int) Auth::id(), $hash);
            flash('success', 'Vaga confirmada. Seu agendamento foi criado.');
            redirect('/painel/agendamentos');
        } catch (\Throwable $exception) {
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível confirmar a vaga.');
            redirect('/painel/minha-lista-espera');
        }
    }

    public function index(): void
    {
        Auth::requireRole(['owner', 'employee']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        (new WaitlistAutomationService())->expireOffers($establishmentId);
        $stmt = $pdo->prepare(
            'SELECT w.*, s.name AS service_name, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, '
            . 'u.name AS provider_name, wm.slot_start AS matched_slot, wm.status AS match_status, mu.name AS matched_provider_name '
            . 'FROM waitlist_entries w JOIN services s ON s.id=w.service_id JOIN customers c ON c.id=w.customer_id '
            . 'LEFT JOIN users u ON u.id=w.preferred_employee_user_id '
            . 'LEFT JOIN waitlist_matches wm ON wm.id=(SELECT wm2.id FROM waitlist_matches wm2 WHERE wm2.waitlist_entry_id=w.id AND wm2.status IN ("available","queued","notified") ORDER BY wm2.slot_start ASC LIMIT 1) '
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
    private function convertMatch(int $matchId, int $userId, ?string $expectedTokenHash): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT wm.*,w.status waitlist_status,w.service_id,w.customer_id,c.user_id,'
                . 'e.timezone,e.active establishment_active,s.duration_minutes,s.price,s.active service_active,'
                . 'e.name establishment_name,s.name service_name,u.name employee_name '
                . 'FROM waitlist_matches wm JOIN waitlist_entries w ON w.id=wm.waitlist_entry_id '
                . 'JOIN customers c ON c.id=w.customer_id JOIN establishments e ON e.id=wm.establishment_id '
                . 'JOIN services s ON s.id=w.service_id JOIN users u ON u.id=wm.employee_user_id '
                . 'WHERE wm.id=:id LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['id' => $matchId]);
            $match = $stmt->fetch();

            if (!$match || (int) $match['user_id'] !== $userId) {
                throw new \RuntimeException('Esta vaga não pertence à sua lista de espera.');
            }
            if ($expectedTokenHash !== null && (!is_string($match['offer_token_hash']) || !hash_equals($match['offer_token_hash'], $expectedTokenHash))) {
                throw new \RuntimeException('O link desta oferta não é mais válido.');
            }
            if (!in_array($match['status'], ['available', 'queued', 'notified'], true)
                || !in_array($match['waitlist_status'], ['waiting', 'notified'], true)) {
                throw new \RuntimeException('Esta oferta já foi finalizada.');
            }
            if ((int) $match['establishment_active'] !== 1 || (int) $match['service_active'] !== 1) {
                throw new \RuntimeException('O estabelecimento ou serviço não está disponível.');
            }

            $clock = new EstablishmentClock();
            $timezone = new DateTimeZone((string) $match['timezone']);
            $slotStart = new DateTimeImmutable((string) $match['slot_start'], $timezone);
            $now = $clock->inTimezone((string) $match['timezone']);
            if ($slotStart <= $now || (!empty($match['offer_expires_at']) && new DateTimeImmutable((string) $match['offer_expires_at'], $timezone) <= $now)) {
                $this->expireMatchInsideTransaction($pdo, $match);
                $pdo->commit();
                throw new \RuntimeException('Esta oferta expirou. Você voltou para a lista de espera.');
            }

            $providerLock = $pdo->prepare('SELECT id FROM users WHERE id=:provider AND status="active" FOR UPDATE');
            $providerLock->execute(['provider' => $match['employee_user_id']]);
            if (!$providerLock->fetchColumn()) {
                $this->expireMatchInsideTransaction($pdo, $match);
                $pdo->commit();
                throw new \RuntimeException('O profissional não está mais disponível. Você voltou para a lista de espera.');
            }

            $date = $slotStart->format('Y-m-d');
            $time = $slotStart->format('H:i');
            $slots = (new AvailabilityService())->slots(
                (int) $match['establishment_id'],
                (int) $match['service_id'],
                (int) $match['employee_user_id'],
                $date
            );
            if (!in_array($time, $slots, true)) {
                $this->expireMatchInsideTransaction($pdo, $match);
                $pdo->commit();
                throw new \RuntimeException('Essa vaga acabou de ser ocupada. Você voltou para a lista de espera.');
            }

            $endsAt = $slotStart->add(new DateInterval('PT' . (int) $match['duration_minutes'] . 'M'));
            $insert = $pdo->prepare(
                'INSERT INTO appointments '
                . '(establishment_id,service_id,employee_user_id,client_user_id,customer_id,created_by_user_id,starts_at,ends_at,status,price,notes) '
                . 'VALUES (:establishment,:service,:employee,:client,:customer,:creator,:starts,:ends,"confirmed",:price,:notes)'
            );
            $insert->execute([
                'establishment' => $match['establishment_id'],
                'service' => $match['service_id'],
                'employee' => $match['employee_user_id'],
                'client' => $userId,
                'customer' => $match['customer_id'],
                'creator' => $userId,
                'starts' => $slotStart->format('Y-m-d H:i:s'),
                'ends' => $endsAt->format('Y-m-d H:i:s'),
                'price' => $match['price'],
                'notes' => 'Criado a partir da lista de espera.',
            ]);
            $appointmentId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO appointment_events (appointment_id,establishment_id,user_id,event_type,to_status,details) '
                . 'VALUES (:appointment,:establishment,:user,"created","confirmed",:details)'
            )->execute([
                'appointment' => $appointmentId,
                'establishment' => $match['establishment_id'],
                'user' => $userId,
                'details' => 'Agendamento confirmado pelo cliente a partir da lista de espera.',
            ]);

            $pdo->prepare(
                'UPDATE waitlist_matches SET status="converted",accepted_at=:accepted_at,appointment_id=:appointment '
                . 'WHERE id=:id'
            )->execute([
                'accepted_at' => $clock->sql($now),
                'appointment' => $appointmentId,
                'id' => $matchId,
            ]);
            $pdo->prepare('UPDATE waitlist_entries SET status="converted" WHERE id=:id')
                ->execute(['id' => $match['waitlist_entry_id']]);
            $pdo->prepare(
                'UPDATE waitlist_matches SET status="expired" WHERE waitlist_entry_id=:entry AND id<>:match '
                . 'AND status IN ("available","queued","notified")'
            )->execute(['entry' => $match['waitlist_entry_id'], 'match' => $matchId]);
            $pdo->prepare(
                'UPDATE notification_outbox SET status="cancelled" '
                . 'WHERE waitlist_entry_id=:entry AND status="pending"'
            )->execute(['entry' => $match['waitlist_entry_id']]);

            $pdo->commit();
            return $appointmentId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function expireMatchInsideTransaction(\PDO $pdo, array $match): void
    {
        $pdo->prepare('UPDATE waitlist_matches SET status="expired" WHERE id=:id')
            ->execute(['id' => $match['id']]);
        if (($match['waitlist_status'] ?? null) === 'notified') {
            $pdo->prepare('UPDATE waitlist_entries SET status="waiting" WHERE id=:id')
                ->execute(['id' => $match['waitlist_entry_id']]);
        }
        $pdo->prepare(
            'UPDATE notification_outbox SET status="cancelled" '
            . 'WHERE waitlist_entry_id=:entry AND event_type="waitlist_slot_available" AND status="pending"'
        )->execute(['entry' => $match['waitlist_entry_id']]);
    }

    private function offerNotFound(): void
    {
        http_response_code(404);
        View::render('errors/404', ['title' => 'Oferta não encontrada']);
    }

}
