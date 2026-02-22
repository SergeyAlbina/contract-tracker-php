<?php
declare(strict_types=1);
namespace App\Modules\Contracts\Dto;

final class ContractCreateDto
{
    public function __construct(
        public readonly string  $number,
        public readonly string  $subject,
        public readonly string  $lawType,
        public readonly string  $contractorName,
        public readonly ?string $contractorInn,
        public readonly float   $totalAmount,
        public readonly ?float  $nmckAmount,
        public readonly string  $status,
        public readonly ?string $signedAt,
        public readonly ?string $expiresAt,
        public readonly ?string $notes,
        public readonly int     $createdBy,
    ) {}

    public static function fromRequest(array $d, int $uid): self
    {
        $e = fn(?string $v): ?string => ($v = trim($v ?? '')) === '' ? null : $v;
        return new self(
            number: trim($d['number'] ?? ''), subject: trim($d['subject'] ?? ''),
            lawType: $d['law_type'] ?? '', contractorName: trim($d['contractor_name'] ?? ''),
            contractorInn: $e($d['contractor_inn'] ?? ''), totalAmount: (float)($d['total_amount'] ?? 0),
            nmckAmount: ($v = trim($d['nmck_amount'] ?? '')) !== '' ? (float)$v : null,
            status: $d['status'] ?? 'draft', signedAt: $e($d['signed_at'] ?? ''),
            expiresAt: $e($d['expires_at'] ?? ''), notes: $e($d['notes'] ?? ''), createdBy: $uid,
        );
    }

    public function toArray(): array
    {
        return [
            'number' => $this->number, 'subject' => $this->subject, 'law_type' => $this->lawType,
            'contractor_name' => $this->contractorName, 'contractor_inn' => $this->contractorInn,
            'total_amount' => $this->totalAmount, 'nmck_amount' => $this->nmckAmount,
            'status' => $this->status, 'signed_at' => $this->signedAt,
            'expires_at' => $this->expiresAt, 'notes' => $this->notes, 'created_by' => $this->createdBy,
        ];
    }
}
