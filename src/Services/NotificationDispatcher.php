<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Notifications\EvolutionGoWhatsappGateway;

final class NotificationDispatcher
{
    public function dispatchEstablishment(int $establishmentId, int $limit = 50): array
    {
        $settings = (new NotificationService())->settings($establishmentId);
        if ((int) ($settings['whatsapp_enabled'] ?? 0) !== 1 || ($settings['provider'] ?? null) !== 'evolution_go') {
            return ['sent' => 0, 'failed' => 0];
        }

        $limit = max(1, min(200, $limit));
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM notification_outbox WHERE establishment_id=:establishment AND scheduled_at<=NOW() '
            . 'AND attempts<3 AND (status="pending" OR (status="failed" AND updated_at<=DATE_SUB(NOW(),INTERVAL 5 MINUTE))) '
            . 'ORDER BY scheduled_at,id LIMIT ' . $limit
        );
        $stmt->execute(['establishment' => $establishmentId]);

        $gateway = new EvolutionGoWhatsappGateway();
        $sent = 0;
        $failed = 0;
        foreach ($stmt->fetchAll() as $item) {
            $result = $gateway->send((string) $item['recipient'], (string) $item['message']);
            if (($result['success'] ?? false) === true) {
                $pdo->prepare('UPDATE notification_outbox SET status="sent",sent_at=NOW(),attempts=attempts+1,last_error=NULL,provider_message_id=:message_id WHERE id=:id AND establishment_id=:establishment')
                    ->execute([
                        'message_id' => ($result['message_id'] ?? '') !== '' ? $result['message_id'] : null,
                        'id' => $item['id'],
                        'establishment' => $establishmentId,
                    ]);
                $this->markWaitlistNotified($pdo, $item, $establishmentId);
                $sent++;
            } else {
                $error = substr((string) ($result['error'] ?? 'Falha desconhecida no envio.'), 0, 255);
                $pdo->prepare('UPDATE notification_outbox SET status="failed",attempts=attempts+1,last_error=:error WHERE id=:id AND establishment_id=:establishment')
                    ->execute(['error' => $error, 'id' => $item['id'], 'establishment' => $establishmentId]);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    private function markWaitlistNotified(\PDO $pdo, array $item, int $establishmentId): void
    {
        if (empty($item['waitlist_entry_id'])) return;
        $pdo->prepare('UPDATE waitlist_entries SET status="notified" WHERE id=:id AND establishment_id=:establishment AND status="waiting"')
            ->execute(['id' => $item['waitlist_entry_id'], 'establishment' => $establishmentId]);
        $pdo->prepare('UPDATE waitlist_matches SET status="notified" WHERE waitlist_entry_id=:id AND establishment_id=:establishment AND status="queued"')
            ->execute(['id' => $item['waitlist_entry_id'], 'establishment' => $establishmentId]);
    }
}
