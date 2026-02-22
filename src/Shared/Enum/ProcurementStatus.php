<?php
declare(strict_types=1);

namespace App\Shared\Enum;

enum ProcurementStatus: string
{
    case DRAFT = 'draft';
    case RFQ = 'rfq';
    case EVALUATION = 'evaluation';
    case AWARDED = 'awarded';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Черновик',
            self::RFQ => 'Сбор КП',
            self::EVALUATION => 'Оценка КП',
            self::AWARDED => 'Победитель выбран',
            self::CANCELLED => 'Отменена',
        };
    }
}
