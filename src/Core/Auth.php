<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $userCache = null;

    public static function attempt(string $email, string $password): bool
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = :email AND status = "active" LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        self::$userCache = $user;
        return true;
    }

    public static function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        if (self::$userCache !== null && (int) self::$userCache['id'] === (int) $_SESSION['user_id']) {
            return self::$userCache;
        }

        $stmt = Database::connection()->prepare('SELECT id, name, email, role, status, avatar_url FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || ($user['status'] ?? null) !== 'active') {
            unset($_SESSION['user_id']);
            self::$userCache = null;
            return null;
        }

        self::$userCache = $user;
        return self::$userCache;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('error', 'Faça login para continuar.');
            redirect('/login');
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Acesso negado']);
            exit;
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        self::$userCache = null;
        session_regenerate_id(true);
    }
}
