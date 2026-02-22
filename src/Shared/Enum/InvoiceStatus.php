<?php
declare(strict_types=1);

namespace App\Shared\Enum;

enum InvoiceStatus: string
{
    case ISSUED = 'issued';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Выставлен',
            self::PAID => 'Оплачен',
            self::CANCELLED => 'Отменён',
        };
    }
}
