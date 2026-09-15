<?php

declare(strict_types=1);

namespace App\Core;

final class TenantContext
{
    public static function establishmentId(): ?int
    {
        if (!Auth::check() || in_array(Auth::role(), ['admin', 'client'], true)) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT establishment_id FROM establishment_users WHERE user_id = :user_id AND active = 1 ORDER BY id LIMIT 1'
        );
        $stmt->execute(['user_id' => Auth::id()]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    public static function requireEstablishmentId(): int
    {
        $id = self::establishmentId();
        if (!$id) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Estabelecimento não vinculado']);
            exit;
        }
        return $id;
    }
}
