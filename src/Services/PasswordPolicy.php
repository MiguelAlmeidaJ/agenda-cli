<?php

declare(strict_types=1);

namespace App\Services;

final class PasswordPolicy
{
    public function errors(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'A senha precisa ter ao menos 8 caracteres.';
        }
        if (strlen($password) > 255) {
            $errors[] = 'A senha é muito longa.';
        }

        return $errors;
    }
}
