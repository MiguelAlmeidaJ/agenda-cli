<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class LoginThrottleService
{
    private const WINDOW_MINUTES = 15;
    private const EMAIL_LIMIT = 5;
    private const IP_LIMIT = 25;

    public function retryAfter(string $email, string $ip): int
    {
        try {
            $pdo = Database::connection();
            $emailRetry = $this->retryAfterFor($pdo, 'email_hash', $this->hashEmail($email), self::EMAIL_LIMIT);
            $ipRetry = $this->retryAfterFor($pdo, 'ip_hash', $this->hashIp($ip), self::IP_LIMIT);
            return max($emailRetry, $ipRetry);
        } catch (\Throwable $exception) {
            error_log('Agenda CLI login throttle: ' . $exception->getMessage());
            return 0;
        }
    }

    public function recordFailure(string $email, string $ip): void
    {
        try {
            $pdo = Database::connection();
            $pdo->prepare('INSERT INTO login_attempts (email_hash, ip_hash) VALUES (:email_hash, :ip_hash)')
                ->execute([
                    'email_hash' => $this->hashEmail($email),
                    'ip_hash' => $this->hashIp($ip),
                ]);
            $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        } catch (\Throwable $exception) {
            error_log('Agenda CLI login throttle: ' . $exception->getMessage());
        }
    }

    public function clearEmail(string $email): void
    {
        try {
            Database::connection()->prepare('DELETE FROM login_attempts WHERE email_hash = :email_hash')
                ->execute(['email_hash' => $this->hashEmail($email)]);
        } catch (\Throwable $exception) {
            error_log('Agenda CLI login throttle: ' . $exception->getMessage());
        }
    }

    private function retryAfterFor(\PDO $pdo, string $column, string $hash, int $limit): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total, '
            . 'GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MIN(attempted_at), INTERVAL '
            . self::WINDOW_MINUTES . ' MINUTE))) AS retry_after '
            . 'FROM login_attempts WHERE ' . $column . ' = :hash '
            . 'AND attempted_at >= DATE_SUB(NOW(), INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)'
        );
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch() ?: ['total' => 0, 'retry_after' => 0];

        return (int) $row['total'] >= $limit ? max(1, (int) $row['retry_after']) : 0;
    }

    private function hashEmail(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    private function hashIp(string $ip): string
    {
        return hash('sha256', trim($ip) !== '' ? trim($ip) : 'unknown');
    }
}
