<?php
declare(strict_types=1);

namespace App\Shared\Enum;

enum CaseBlockType: string
{
    case TASKS = 'TASKS';
    case TERMINATIONS = 'TERMINATIONS';
    case CLAIMS = 'CLAIMS';
    case CONCLUDED = 'CONCLUDED';
    case APPROVED_FZ = 'APPROVED_FZ';

    public function label(): string
    {
        return match ($this) {
            self::TASKS => 'Задачи',
            self::TERMINATIONS => 'Расторжения',
            self::CLAIMS => 'Претензии',
            self::CONCLUDED => 'Заключённые',
            self::APPROVED_FZ => 'ФЗ утверждённые',
        };
    }
}
