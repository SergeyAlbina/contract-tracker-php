<?php
declare(strict_types=1);

namespace App\Shared\Enum;

enum CaseEventType: string
{
    case NOTE = 'NOTE';
    case SENT_TO_ACCOUNTING = 'SENT_TO_ACCOUNTING';
    case GOODS_RECEIVED = 'GOODS_RECEIVED';
    case PENALTY_PAID = 'PENALTY_PAID';
    case AGREEMENT_SIGNED = 'AGREEMENT_SIGNED';
    case CLOSED = 'CLOSED';
    case STATUS_CHANGED = 'STATUS_CHANGED';

    public function label(): string
    {
        return match ($this) {
            self::NOTE => 'Комментарий',
            self::SENT_TO_ACCOUNTING => 'Передано в бухгалтерию',
            self::GOODS_RECEIVED => 'Товар получен',
            self::PENALTY_PAID => 'Пени оплачены',
            self::AGREEMENT_SIGNED => 'Соглашение подписано',
            self::CLOSED => 'Закрыто',
            self::STATUS_CHANGED => 'Статус изменён',
        };
    }
}
