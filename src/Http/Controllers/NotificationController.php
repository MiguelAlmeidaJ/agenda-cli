<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\NotificationService;

final class NotificationController
{
    public function index(): void
    {
        Auth::requireRole(['owner', 'employee']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        $settings = (new NotificationService())->settings($establishmentId);

        $outbox = $pdo->prepare(
            'SELECT n.*,COALESCE(c.name,"Cliente") customer_name FROM notification_outbox n '
            . 'LEFT JOIN customers c ON c.id=n.customer_id '
            . 'WHERE n.establishment_id=:id ORDER BY FIELD(n.status,"pending","failed","sent","cancelled"),n.scheduled_at DESC LIMIT 120'
        );
        $outbox->execute(['id' => $establishmentId]);

        $counts = $pdo->prepare('SELECT status,COUNT(*) total FROM notification_outbox WHERE establishment_id=:id GROUP BY status');
        $counts->execute(['id' => $establishmentId]);
        $byStatus = [];
        foreach ($counts->fetchAll() as $row) {
            $byStatus[$row['status']] = (int) $row['total'];
        }

        View::render('panel/notifications', [
            'title' => 'Notificações',
            'settings' => $settings,
            'outbox' => $outbox->fetchAll(),
            'counts' => $byStatus,
            'role' => Auth::role(),
        ]);
    }

    public function storeSettings(): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $provider = trim((string) ($_POST['provider'] ?? 'manual'));
        if (!in_array($provider, ['manual', 'meta_cloud', 'custom'], true)) {
            $provider = 'manual';
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO notification_settings (establishment_id,whatsapp_enabled,provider,confirmation_enabled,cancellation_enabled,reminder_24h_enabled,reminder_2h_enabled,waitlist_enabled) '
            . 'VALUES (:id,:enabled,:provider,:confirmation,:cancellation,:r24,:r2,:waitlist) '
            . 'ON DUPLICATE KEY UPDATE whatsapp_enabled=VALUES(whatsapp_enabled),provider=VALUES(provider),confirmation_enabled=VALUES(confirmation_enabled),cancellation_enabled=VALUES(cancellation_enabled),reminder_24h_enabled=VALUES(reminder_24h_enabled),reminder_2h_enabled=VALUES(reminder_2h_enabled),waitlist_enabled=VALUES(waitlist_enabled)'
        );
        $stmt->execute([
            'id' => $establishmentId,
            'enabled' => isset($_POST['whatsapp_enabled']) ? 1 : 0,
            'provider' => $provider,
            'confirmation' => isset($_POST['confirmation_enabled']) ? 1 : 0,
            'cancellation' => isset($_POST['cancellation_enabled']) ? 1 : 0,
            'r24' => isset($_POST['reminder_24h_enabled']) ? 1 : 0,
            'r2' => isset($_POST['reminder_2h_enabled']) ? 1 : 0,
            'waitlist' => isset($_POST['waitlist_enabled']) ? 1 : 0,
        ]);
        flash('success', 'Preferências de notificação atualizadas.');
        redirect('/painel/notificacoes');
    }

    public function markSent(string $id): void
    {
        Auth::requireRole(['owner', 'employee']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare('SELECT id,waitlist_entry_id FROM notification_outbox WHERE id=:id AND establishment_id=:establishment LIMIT 1 FOR UPDATE');
            $find->execute(['id' => (int) $id, 'establishment' => $establishmentId]);
            $row = $find->fetch();
            if (!$row) {
                throw new \RuntimeException('Notificação não encontrada.');
            }

            $pdo->prepare('UPDATE notification_outbox SET status="sent",sent_at=NOW(),attempts=attempts+1,last_error=NULL WHERE id=:id')->execute(['id' => $row['id']]);
            if (!empty($row['waitlist_entry_id'])) {
                $pdo->prepare('UPDATE waitlist_entries SET status="notified" WHERE id=:id AND establishment_id=:establishment AND status="waiting"')->execute(['id' => $row['waitlist_entry_id'], 'establishment' => $establishmentId]);
                $pdo->prepare('UPDATE waitlist_matches SET status="notified" WHERE waitlist_entry_id=:id AND establishment_id=:establishment AND status="queued"')->execute(['id' => $row['waitlist_entry_id'], 'establishment' => $establishmentId]);
            }
            $pdo->commit();
            flash('success', 'Notificação marcada como enviada.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível atualizar a notificação.');
        }
        redirect('/painel/notificacoes');
    }
}
