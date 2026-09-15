<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function validate(?string $token): void
    {
        if (!is_string($token) || !hash_equals(self::token(), $token)) {
            http_response_code(419);
            exit('Sessão expirada. Atualize a página e tente novamente.');
        }
    }
}
