<?php
declare(strict_types=1);

namespace App\Shared\Enum;

enum CaseResultStatus: string
{
    case NEW = 'NEW';
    case IN_PROGRESS = 'IN_PROGRESS';
    case DONE = 'DONE';
    case NO_ACTION = 'NO_ACTION';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Новое',
            self::IN_PROGRESS => 'В работе',
            self::DONE => 'Исполнено',
            self::NO_ACTION => 'Без исполнения',
            self::CANCELLED => 'Отменено',
        };
    }
}
