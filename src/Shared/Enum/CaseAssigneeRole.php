<?php
declare(strict_types=1);

namespace App\Shared\Enum;

enum CaseAssigneeRole: string
{
    case RESPONSIBLE = 'RESPONSIBLE';
    case EXECUTOR = 'EXECUTOR';
    case APPROVER = 'APPROVER';
    case CONTROLLER = 'CONTROLLER';

    public function label(): string
    {
        return match ($this) {
            self::RESPONSIBLE => 'Ответственный',
            self::EXECUTOR => 'Исполнитель',
            self::APPROVER => 'Согласующий',
            self::CONTROLLER => 'Контролёр',
        };
    }
}
