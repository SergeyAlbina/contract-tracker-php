<?php
declare(strict_types=1);
namespace App\Shared\Enum;

enum LawType: string
{
    case FZ223 = '223';
    case FZ44  = '44';
    public function label(): string { return match($this) { self::FZ223 => '223-ФЗ', self::FZ44 => '44-ФЗ' }; }
}
