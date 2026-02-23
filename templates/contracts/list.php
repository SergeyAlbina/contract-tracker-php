<?php
use App\Shared\Utils\Html;
use App\Shared\Enum\{LawType, ContractStatus};
/** @var array $items @var int $total @var int $page @var int $pages @var array $filters */
/** @var array<string,int> $statusCounts @var int $totalWithoutStatus */
$canEdit = $session->hasRole('admin', 'manager');
$exportQuery = http_build_query(array_filter($filters, static fn($v) => $v !== '' && $v !== null));
$exportUrl = '/contracts/export.csv' . ($exportQuery ? '?' . $exportQuery : '');
$statusCounts = $statusCounts ?? [];
$totalWithoutStatus = isset($totalWithoutStatus) ? (int) $totalWithoutStatus : (int) array_sum($statusCounts);

$queryBase = [
    'search' => (string) ($filters['search'] ?? ''),
    'law_type' => (string) ($filters['law_type'] ?? ''),
    'status' => (string) ($filters['status'] ?? ''),
];
$tabsQueryBase = array_merge($queryBase, ['page' => 1]);
$tabHref = static function (string $status) use ($tabsQueryBase): string {
    $query = array_merge($tabsQueryBase, ['status' => $status]);
    return '/contracts?' . http_build_query($query);
};
$statusFlow = [
    ContractStatus::DRAFT,
    ContractStatus::ACTIVE,
    ContractStatus::EXECUTED,
    ContractStatus::TERMINATED,
    ContractStatus::CANCELLED,
];
?>

<div class="page-head">
  <h1>
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
      <path d="M14 2v6h6"></path>
      <path d="M16 13H8"></path>
      <path d="M16 17H8"></path>
      <path d="M10 9H8"></path>
    </svg>
    Контракты <span class="page-head__count">(<?= (int) $total ?>)</span>
  </h1>
  <div class="flex gap-sm">
    <a href="<?= Html::e($exportUrl) ?>" class="btn btn--ghost">⬇ Экспорт</a>
    <?php if ($canEdit): ?>
      <a href="/contracts/new" class="btn btn--primary">+ Новый контракт</a>
    <?php endif; ?>
  </div>
</div>

<nav class="flow-tabs" aria-label="Этапы контрактов">
  <a href="<?= Html::e($tabHref('')) ?>" class="flow-tab <?= $queryBase['status'] === '' ? 'active' : '' ?>">
    Все
    <span class="flow-tab__count"><?= $totalWithoutStatus ?></span>
  </a>
  <?php foreach ($statusFlow as $status): ?>
    <?php
      $value = $status->value;
      $count = (int) ($statusCounts[$value] ?? 0);
    ?>
    <a href="<?= Html::e($tabHref($value)) ?>" class="flow-tab <?= $queryBase['status'] === $value ? 'active' : '' ?>">
      <?= Html::e($status->label()) ?>
      <span class="flow-tab__count"><?= $count ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<form class="filters" method="get" action="/contracts">
  <input type="text" name="search" placeholder="Поиск по номеру, предмету, контрагенту…" value="<?= Html::e($queryBase['search']) ?>" style="flex:1;min-width:220px">
  <select name="law_type">
    <option value="">Все законы</option>
    <?php foreach (LawType::cases() as $l): ?>
      <option value="<?= $l->value ?>" <?= $queryBase['law_type'] === $l->value ? 'selected' : '' ?>><?= $l->label() ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status">
    <option value="">Все статусы</option>
    <?php foreach (ContractStatus::cases() as $s): ?>
      <option value="<?= $s->value ?>" <?= $queryBase['status'] === $s->value ? 'selected' : '' ?>><?= $s->label() ?></option>
    <?php endforeach; ?>
  </select>
  <input type="hidden" name="page" value="1">
  <button type="submit" class="btn btn--ghost btn--sm">🔍 Найти</button>
  <a href="/contracts" class="btn btn--ghost btn--sm">Сброс</a>
</form>

<?php if (empty($items)): ?>
  <div class="empty">
    <div class="empty__icon"><span class="emoji">📂</span></div>
    <p>Контрактов не найдено</p>
    <?php if ($canEdit): ?>
      <a href="/contracts/new" class="btn btn--primary mt-2" style="display:inline-flex">+ Создать первый</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Номер</th>
          <th>Предмет</th>
          <th>Контрагент</th>
          <th>Закон</th>
          <th>Статус</th>
          <th style="text-align:right">Сумма</th>
          <th>Срок</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $c): ?>
        <tr>
          <td class="td-link"><a href="/contracts/<?= (int)$c['id'] ?>"><?= Html::e($c['number']) ?></a></td>
          <td><?= Html::e(Html::truncate($c['subject'], 60)) ?></td>
          <td><?= Html::e(Html::truncate($c['contractor_name'], 40)) ?></td>
          <td><?= Html::badge($c['law_type'], LawType::from($c['law_type'])->label()) ?></td>
          <td><?= Html::badge($c['status'], ContractStatus::from($c['status'])->label()) ?></td>
          <td class="td-num" style="text-align:right"><?= Html::money($c['total_amount']) ?></td>
          <td class="text-muted"><?= Html::date($c['expires_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="pgn">
    <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($filters, ['page'=>$page-1])) ?>">←</a><?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span class="cur"><?= $i ?></span>
      <?php elseif (abs($i - $page) < 3 || $i === 1 || $i === $pages): ?><a href="?<?= http_build_query(array_merge($filters, ['page'=>$i])) ?>"><?= $i ?></a>
      <?php elseif (abs($i - $page) === 3): ?><span class="text-muted">…</span>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?<?= http_build_query(array_merge($filters, ['page'=>$page+1])) ?>">→</a><?php endif; ?>
  </nav>
  <?php endif; ?>
<?php endif; ?>
