<?php
declare(strict_types=1);
namespace App\Shared\Security;

final class Passwords
{
    private static function algo(): string|int { return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT; }
    public static function hash(string $pw): string        { return password_hash($pw, self::algo()); }
    public static function verify(string $pw, string $h): bool { return password_verify($pw, $h); }
    public static function needsRehash(string $h): bool    { return password_needs_rehash($h, self::algo()); }
}
