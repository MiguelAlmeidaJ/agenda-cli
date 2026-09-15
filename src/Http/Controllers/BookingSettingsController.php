<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;

final class BookingSettingsController
{
    public function index(): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $stmt = Database::connection()->prepare('SELECT * FROM booking_settings WHERE establishment_id = :establishment LIMIT 1');
        $stmt->execute(['establishment' => $establishmentId]);
        $settings = $stmt->fetch() ?: [
            'min_notice_minutes' => 0,
            'max_advance_days' => 90,
            'buffer_minutes' => 0,
            'cancellation_notice_minutes' => 0,
            'allow_waitlist' => 1,
            'cancellation_policy' => '',
        ];
        View::render('panel/booking_settings', ['title' => 'Regras de agendamento', 'settings' => $settings]);
    }

    public function store(): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $values = [
            'establishment' => $establishmentId,
            'min_notice' => max(0, min(10080, (int) ($_POST['min_notice_minutes'] ?? 0))),
            'max_advance' => max(1, min(365, (int) ($_POST['max_advance_days'] ?? 90))),
            'buffer' => max(0, min(180, (int) ($_POST['buffer_minutes'] ?? 0))),
            'cancel_notice' => max(0, min(10080, (int) ($_POST['cancellation_notice_minutes'] ?? 0))),
            'waitlist' => isset($_POST['allow_waitlist']) ? 1 : 0,
            'policy' => trim((string) ($_POST['cancellation_policy'] ?? '')) ?: null,
        ];
        $stmt = Database::connection()->prepare(
            'INSERT INTO booking_settings (establishment_id, min_notice_minutes, max_advance_days, buffer_minutes, cancellation_notice_minutes, allow_waitlist, cancellation_policy) '
            . 'VALUES (:establishment, :min_notice, :max_advance, :buffer, :cancel_notice, :waitlist, :policy) '
            . 'ON DUPLICATE KEY UPDATE min_notice_minutes = VALUES(min_notice_minutes), max_advance_days = VALUES(max_advance_days), '
            . 'buffer_minutes = VALUES(buffer_minutes), cancellation_notice_minutes = VALUES(cancellation_notice_minutes), '
            . 'allow_waitlist = VALUES(allow_waitlist), cancellation_policy = VALUES(cancellation_policy)'
        );
        $stmt->execute($values);
        flash('success', 'Regras de agendamento atualizadas.');
        redirect('/painel/configuracoes/agendamento');
    }
}
