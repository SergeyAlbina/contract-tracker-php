<?php
declare(strict_types=1);

namespace App\Shared\Enum;

enum ActStatus: string
{
    case PENDING = 'pending';
    case SIGNED = 'signed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Подготовлен',
            self::SIGNED => 'Подписан',
            self::REJECTED => 'Отклонён',
            self::CANCELLED => 'Отменён',
        };
    }
}
