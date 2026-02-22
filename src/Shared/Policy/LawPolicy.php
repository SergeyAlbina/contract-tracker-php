<?php
declare(strict_types=1);
namespace App\Shared\Policy;

use App\Shared\Enum\LawType;

final class LawPolicy
{
    /** @return string[] ошибки (пустой = ОК) */
    public static function validateContract(array $d): array
    {
        $errors = [];
        $law = LawType::tryFrom($d['law_type'] ?? '');

        if (!$law) { $errors[] = 'Укажите тип закона.'; return $errors; }

        if ($law === LawType::FZ44 && (empty($d['nmck_amount']) || (float)$d['nmck_amount'] <= 0))
            $errors[] = 'Для 44-ФЗ обязательна НМЦК > 0.';

        if (empty(trim($d['number'] ?? '')))          $errors[] = 'Номер контракта обязателен.';
        if (empty(trim($d['subject'] ?? '')))          $errors[] = 'Предмет контракта обязателен.';
        if (empty(trim($d['contractor_name'] ?? '')))  $errors[] = 'Контрагент обязателен.';
        if (!isset($d['total_amount']) || (float)$d['total_amount'] < 0) $errors[] = 'Сумма >= 0.';

        return $errors;
    }
}
