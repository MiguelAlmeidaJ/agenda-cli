<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    #[DataProvider('invalidPasswords')]
    public function testRejectsInvalidPasswords(string $password): void
    {
        self::assertNotSame([], (new PasswordPolicy())->errors($password));
    }

    public function testAcceptsPasswordWithAtLeastEightCharacters(): void
    {
        self::assertSame([], (new PasswordPolicy())->errors('Agenda@123'));
    }

    public static function invalidPasswords(): array
    {
        return [
            'empty' => [''],
            'short' => ['1234567'],
            'too-long' => [str_repeat('a', 256)],
        ];
    }
}
