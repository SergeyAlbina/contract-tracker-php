<?php

declare(strict_types=1);

namespace App\Shared\Utils;

final class Strings
{
    /**
     * Escape for HTML output.
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Format money for display.
     */
    public static function money(float|string|null $amount, string $currency = '₽'): string
    {
        if ($amount === null) {
            return '—';
        }
        return number_format((float) $amount, 2, ',', ' ') . ' ' . $currency;
    }

    /**
     * Format date for display.
     */
    public static function date(?string $date, string $format = 'd.m.Y'): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);
        return $dt ? $dt->format($format) : $date;
    }

    /**
     * Sanitize filename (remove path traversal, special chars).
     */
    public static function safeName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\w.\-]/', '_', $name) ?? $name;
        return self::mbSubstr($name, 0, 200);
    }

    /**
     * Truncate string with ellipsis.
     */
    public static function truncate(string $text, int $max = 100): string
    {
        if (self::mbLen($text) <= $max) {
            return $text;
        }
        return self::mbSubstr($text, 0, $max - 1) . '…';
    }

    private static function mbLen(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    private static function mbSubstr(string $text, int $start, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            return $length === null ? mb_substr($text, $start) : mb_substr($text, $start, $length);
        }
        return $length === null ? substr($text, $start) : substr($text, $start, $length);
    }
}
