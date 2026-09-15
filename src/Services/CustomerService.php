<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class CustomerService
{
    public function ensureForUser(PDO $pdo, int $establishmentId, int $userId): int
    {
        $userStmt = $pdo->prepare('SELECT id, name, email, phone, role, status FROM users WHERE id = :user LIMIT 1');
        $userStmt->execute(['user' => $userId]);
        $user = $userStmt->fetch();

        if (!$user || ($user['role'] ?? null) !== 'client' || ($user['status'] ?? null) !== 'active') {
            throw new \RuntimeException('Conta de cliente indisponível.');
        }

        $find = $pdo->prepare(
            'SELECT id, user_id FROM customers '
            . 'WHERE establishment_id = :establishment '
            . 'AND (user_id = :user OR (user_id IS NULL AND email = :email)) '
            . 'ORDER BY user_id IS NOT NULL DESC LIMIT 1 FOR UPDATE'
        );
        $find->execute([
            'establishment' => $establishmentId,
            'user' => $userId,
            'email' => strtolower((string) $user['email']),
        ]);
        $customer = $find->fetch();

        if ($customer) {
            $update = $pdo->prepare(
                'UPDATE customers SET user_id = :user, name = :name, email = :email, '
                . 'phone = COALESCE(:phone, phone) WHERE id = :customer AND establishment_id = :establishment'
            );
            $update->execute([
                'user' => $userId,
                'name' => $user['name'],
                'email' => strtolower((string) $user['email']),
                'phone' => $user['phone'] ?: null,
                'customer' => $customer['id'],
                'establishment' => $establishmentId,
            ]);
            return (int) $customer['id'];
        }

        $insert = $pdo->prepare(
            'INSERT INTO customers (establishment_id, user_id, name, email, phone) '
            . 'VALUES (:establishment, :user, :name, :email, :phone)'
        );
        $insert->execute([
            'establishment' => $establishmentId,
            'user' => $userId,
            'name' => $user['name'],
            'email' => strtolower((string) $user['email']),
            'phone' => $user['phone'] ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
