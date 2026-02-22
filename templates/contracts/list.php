<?php
use App\Shared\Utils\Html;
use App\Shared\Enum\{LawType, ContractStatus};
/** @var array $items @var int $total @var int $page @var int $pages @var array $filters */
?>

<div class="page-head">
  <h1>📄 Контракты <span class="text-muted" style="font-size:.7em;font-weight:400">(<?= $total ?>)</span></h1>
  <a href="/contracts/new" class="btn btn--primary">+ Новый контракт</a>
</div>

<form class="filters" method="get" action="/contracts">
  <input type="text" name="search" placeholder="Поиск по номеру, предмету, контрагенту…" value="<?= Html::e($filters['search']) ?>" style="flex:1;min-width:200px">
  <select name="law_type">
    <option value="">Все законы</option>
    <?php foreach (LawType::cases() as $l): ?>
      <option value="<?= $l->value ?>" <?= $filters['law_type'] === $l->value ? 'selected' : '' ?>><?= $l->label() ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status">
    <option value="">Все статусы</option>
    <?php foreach (ContractStatus::cases() as $s): ?>
      <option value="<?= $s->value ?>" <?= $filters['status'] === $s->value ? 'selected' : '' ?>><?= $s->label() ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn--ghost btn--sm">🔍 Найти</button>
</form>

<?php if (empty($items)): ?>
  <div class="empty">
    <div class="empty__icon">📂</div>
    <p>Контрактов не найдено</p>
    <a href="/contracts/new" class="btn btn--primary mt-2" style="display:inline-flex">+ Создать первый</a>
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
