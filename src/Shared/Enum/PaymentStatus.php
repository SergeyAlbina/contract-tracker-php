<?php
declare(strict_types=1);
namespace App\Shared\Enum;

enum PaymentStatus: string
{
    case PLANNED     = 'planned';
    case IN_PROGRESS = 'in_progress';
    case PAID        = 'paid';
    case CANCELED    = 'canceled';

    public function label(): string
    {
        return match($this) {
            self::PLANNED => 'Запланирован', self::IN_PROGRESS => 'В процессе',
            self::PAID => 'Оплачен', self::CANCELED => 'Отменён',
        };
    }
}
