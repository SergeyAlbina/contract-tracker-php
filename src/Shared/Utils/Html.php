<?php
declare(strict_types=1);

namespace App\Shared\Utils;

/**
 * Всё для вывода — единая точка. Используется в КАЖДОМ шаблоне.
 * Ни одна из этих функций не дублируется нигде в проекте.
 */
final class Html
{
    /** XSS-safe escape */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Форматирование денег */
    public static function money(float|string|null $amount, string $currency = '₽'): string
    {
        if ($amount === null || $amount === '') return '—';
        return number_format((float) $amount, 2, ',', ' ') . ' ' . $currency;
    }

    /** Форматирование даты */
    public static function date(?string $date, string $format = 'd.m.Y'): string
    {
        if (!$date || $date === '' || $date === '0000-00-00') return '—';
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);
        return $dt ? $dt->format($format) : $date;
    }

    /** Обрезка с многоточием */
    public static function truncate(string $text, int $max = 80): string
    {
        return self::mbLen($text) <= $max ? $text : self::mbSubstr($text, 0, $max - 1) . '…';
    }

    /** Safe filename (убираем path traversal и спецсимволы) */
    public static function safeName(string $name): string
    {
        return self::mbSubstr(preg_replace('/[^\w.\-]/', '_', basename($name)) ?? '_', 0, 200);
    }

    /** Размер файла человекочитаемо */
    public static function fileSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' Б';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' КБ';
        return round($bytes / 1048576, 1) . ' МБ';
    }

    /** CSS-класс badge по enum-value */
    public static function badge(string $value, string $label): string
    {
        $map = [
            '223' => 'badge--cyan', '44' => 'badge--amber',
            'draft' => 'badge--slate', 'active' => 'badge--emerald', 'executed' => 'badge--sky',
            'terminated' => 'badge--rose', 'cancelled' => 'badge--rose',
            'planned' => 'badge--slate', 'in_progress' => 'badge--sky',
            'paid' => 'badge--emerald', 'canceled' => 'badge--rose',
            'admin' => 'badge--amber', 'manager' => 'badge--sky', 'viewer' => 'badge--slate',
        ];
        $class = $map[$value] ?? 'badge--slate';
        return '<span class="badge ' . $class . '">' . self::e($label) . '</span>';
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
