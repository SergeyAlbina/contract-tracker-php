<?php
declare(strict_types=1);

namespace App\Shared\Enum;

enum StageStatus: string
{
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Запланирован',
            self::IN_PROGRESS => 'В работе',
            self::COMPLETED => 'Завершён',
            self::CANCELLED => 'Отменён',
        };
    }
}
