<?php
declare(strict_types=1);
namespace App\Shared\Enum;

enum ContractStatus: string
{
    case DRAFT      = 'draft';
    case ACTIVE     = 'active';
    case EXECUTED   = 'executed';
    case TERMINATED = 'terminated';
    case CANCELLED  = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Черновик', self::ACTIVE => 'Действующий', self::EXECUTED => 'Исполнен',
            self::TERMINATED => 'Расторгнут', self::CANCELLED => 'Отменён',
        };
    }

    /** @return self[] допустимые переходы */
    public function transitions(): array
    {
        return match($this) {
            self::DRAFT     => [self::ACTIVE, self::CANCELLED],
            self::ACTIVE    => [self::EXECUTED, self::TERMINATED],
            self::CANCELLED => [self::DRAFT],
            default         => [],
        };
    }
}
