<?php
declare(strict_types=1);

namespace App\Modules\Audit;

use App\App;

final class AuditService
{
    private const PER_PAGE = 20;

    private AuditRepository $repo;

    public function __construct(App $app)
    {
        $this->repo = $app->make(AuditRepository::class);
    }

    /** @return array{items:array, total:int, filters:array<string,mixed>} */
    public function list(int $page, array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $result = $this->repo->paginate($page, self::PER_PAGE, $normalized);
        $result['filters'] = $normalized;
        return $result;
    }

    /** @return string[] */
    public function actionOptions(): array
    {
        return $this->repo->actions();
    }

    /** @return string[] */
    public function entityTypeOptions(): array
    {
        return $this->repo->entityTypes();
    }

    /** @return array<int,array{id:int,login:string,full_name:string}> */
    public function userOptions(): array
    {
        return $this->repo->usersForFilter();
    }

    /** @return array<string,mixed> */
    private function normalizeFilters(array $filters): array
    {
        $userIdRaw = trim((string) ($filters['user_id'] ?? ''));
        $userId = (ctype_digit($userIdRaw) && (int) $userIdRaw > 0) ? (int) $userIdRaw : null;

        return [
            'q' => $this->limitText(trim((string) ($filters['q'] ?? '')), 120),
            'action' => $this->limitText(trim((string) ($filters['action'] ?? '')), 100),
            'entity_type' => $this->limitText(trim((string) ($filters['entity_type'] ?? '')), 50),
            'user_id' => $userId,
            'date_from' => $this->normalizeDate((string) ($filters['date_from'] ?? '')),
            'date_to' => $this->normalizeDate((string) ($filters['date_to'] ?? '')),
        ];
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            return '';
        }

        return $value;
    }

    private function limitText(string $value, int $limit): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }
        return substr($value, 0, $limit);
    }
}
