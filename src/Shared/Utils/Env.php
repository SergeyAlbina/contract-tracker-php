<?php
declare(strict_types=1);

namespace App\Shared\Utils;

final class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) return;
        self::$loaded = true;
        if (!file_exists($path)) return;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) $value = $m[2];
            self::$vars[$name] = $value;
            $_ENV[$name] = $value;
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::$vars[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return in_array(strtolower(self::get($key, $default ? '1' : '0')), ['true','1','yes','on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, (string) $default);
    }
}
