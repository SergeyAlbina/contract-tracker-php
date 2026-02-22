<?php
use App\Shared\Utils\Html;
use App\Shared\Enum\{LawType, ContractStatus, PaymentStatus, StageStatus};
/** @var array $contract */
$c = $contract;

// Подгружаем этапы/платежи/документы (fault-tolerant: если модуль отключён — пусто)
$stages = $payments = $documents = [];
try { $stages    = $app->make(\App\Modules\Stages\StagesService::class)->getByContract((int)$c['id']); } catch (\Throwable) {}
try { $payments  = $app->make(\App\Modules\Payments\PaymentsService::class)->getByContract((int)$c['id']); } catch (\Throwable) {}
try { $documents = $app->make(\App\Modules\Documents\DocumentsService::class)->getByContract((int)$c['id']); } catch (\Throwable) {}

$law    = LawType::from($c['law_type']);
$status = ContractStatus::from($c['status']);
$canEdit = $session->hasRole('admin', 'manager');
?>

<div class="page-head">
  <h1>
    <?= Html::badge($c['law_type'], $law->label()) ?>
    <?= Html::badge($c['status'], $status->label()) ?>
    №<?= Html::e($c['number']) ?>
  </h1>
  <div class="flex gap-sm">
    <?php if ($canEdit): ?>
      <a href="/contracts/<?= (int)$c['id'] ?>/edit" class="btn btn--ghost btn--sm">✏️ Редактировать</a>
    <?php endif; ?>
    <a href="/contracts" class="btn btn--ghost btn--sm">← К списку</a>
  </div>
</div>

<!-- ФИНАНСОВЫЙ БЛОК -->
<div class="finance-row">
  <div class="fin-card">
    <div class="fin-card__label">Сумма контракта</div>
    <div class="fin-card__val"><?= Html::money($c['total_amount']) ?></div>
  </div>
  <?php if ($c['nmck_amount']): ?>
  <div class="fin-card">
    <div class="fin-card__label">НМЦК</div>
    <div class="fin-card__val"><?= Html::money($c['nmck_amount']) ?></div>
  </div>
  <?php endif; ?>
  <div class="fin-card">
    <div class="fin-card__label">Оплачено</div>
    <div class="fin-card__val fin-card__val--g"><?= Html::money($c['paid_sum']) ?></div>
  </div>
  <div class="fin-card">
    <div class="fin-card__label"><?= $c['overspend'] ? '⚠ Перерасход' : 'Остаток' ?></div>
    <div class="fin-card__val <?= $c['overspend'] ? 'fin-card__val--r' : 'fin-card__val--a' ?>">
      <?= Html::money(abs($c['remaining'])) ?>
    </div>
  </div>
</div>

<!-- ДЕТАЛИ -->
<div class="card">
  <div class="detail-grid">
    <div class="di"><div class="di__label">Предмет</div><div class="di__value"><?= Html::e($c['subject']) ?></div></div>
    <div class="di"><div class="di__label">Контрагент</div><div class="di__value"><?= Html::e($c['contractor_name']) ?></div></div>
    <div class="di"><div class="di__label">ИНН</div><div class="di__value"><?= Html::e($c['contractor_inn'] ?: '—') ?></div></div>
    <div class="di"><div class="di__label">Подписан</div><div class="di__value"><?= Html::date($c['signed_at']) ?></div></div>
    <div class="di"><div class="di__label">Окончание</div><div class="di__value"><?= Html::date($c['expires_at']) ?></div></div>
    <div class="di"><div class="di__label">Создал</div><div class="di__value"><?= Html::e($c['creator_name'] ?? '—') ?></div></div>
    <?php if ($c['notes']): ?>
    <div class="di" style="grid-column:1/-1"><div class="di__label">Примечания</div><div class="di__value"><?= nl2br(Html::e($c['notes'])) ?></div></div>
    <?php endif; ?>
  </div>
</div>

<!-- ЭТАПЫ -->
<h3 class="section-title">🧭 Этапы (<?= count($stages) ?>)</h3>

<?php if ($stages): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>#</th><th>Этап</th><th>План</th><th>Факт</th><th>Статус</th><th>Комментарий</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
    <tbody>
      <?php foreach ($stages as $s): ?>
      <?php $stageStatus = StageStatus::tryFrom((string)$s['status']); ?>
      <tr>
        <td class="td-num"><?= (int)$s['sort_order'] ?></td>
        <td><?= Html::e($s['title']) ?></td>
        <td class="text-muted"><?= Html::date($s['planned_date']) ?></td>
        <td class="text-muted"><?= Html::date($s['actual_date']) ?></td>
        <td><?= Html::badge((string)$s['status'], $stageStatus?->label() ?? (string)$s['status']) ?></td>
        <td><?= Html::e($s['description'] ? Html::truncate((string)$s['description'], 90) : '—') ?></td>
        <?php if ($canEdit): ?>
        <td style="text-align:right">
          <a href="#stage-edit-<?= (int)$s['id'] ?>" class="btn btn--ghost btn--sm">✏️</a>
          <?php if ($session->hasRole('admin')): ?>
          <form method="post" action="/stages/<?= (int)$s['id'] ?>/delete" style="display:inline">
            <?= $csrf->field() ?>
            <input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn--icon" data-confirm="Удалить этап?">🗑</button>
          </form>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="empty">
  <div class="empty__icon">🧭</div>
  <p>Этапы пока не добавлены</p>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Добавить этап</div></div>
  <form method="post" action="/contracts/<?= (int)$c['id'] ?>/stages">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <div class="fg">
        <label>Порядок</label>
        <input type="number" name="sort_order" min="0" step="1" value="0">
      </div>
      <div class="fg">
        <label>Название этапа *</label>
        <input type="text" name="title" required>
      </div>
      <div class="fg">
        <label>Статус</label>
        <select name="status">
          <?php foreach (StageStatus::cases() as $ss): ?>
            <option value="<?= $ss->value ?>"><?= $ss->label() ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>Плановая дата</label>
        <input type="date" name="planned_date">
      </div>
      <div class="fg">
        <label>Фактическая дата</label>
        <input type="date" name="actual_date">
      </div>
      <div class="fg form-grid--full">
        <label>Комментарий</label>
        <textarea name="description"></textarea>
      </div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">+ Добавить этап</button></div>
  </form>
</div>

<?php if ($stages): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Редактировать этапы</div></div>
  <?php foreach ($stages as $s): ?>
    <?php $currentStageStatus = StageStatus::tryFrom((string)$s['status']) ?? StageStatus::PLANNED; ?>
    <form method="post" action="/stages/<?= (int)$s['id'] ?>/update" id="stage-edit-<?= (int)$s['id'] ?>" class="mt-2">
      <?= $csrf->field() ?>
      <input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
      <div class="form-grid">
        <div class="fg">
          <label>Порядок</label>
          <input type="number" name="sort_order" min="0" step="1" value="<?= (int)$s['sort_order'] ?>">
        </div>
        <div class="fg">
          <label>Название этапа *</label>
          <input type="text" name="title" value="<?= Html::e($s['title']) ?>" required>
        </div>
        <div class="fg">
          <label>Статус</label>
          <select name="status">
            <?php foreach (StageStatus::cases() as $ss): ?>
              <option value="<?= $ss->value ?>" <?= $currentStageStatus === $ss ? 'selected' : '' ?>><?= $ss->label() ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label>Плановая дата</label>
          <input type="date" name="planned_date" value="<?= Html::e((string)($s['planned_date'] ?? '')) ?>">
        </div>
        <div class="fg">
          <label>Фактическая дата</label>
          <input type="date" name="actual_date" value="<?= Html::e((string)($s['actual_date'] ?? '')) ?>">
        </div>
        <div class="fg form-grid--full">
          <label>Комментарий</label>
          <textarea name="description"><?= Html::e((string)($s['description'] ?? '')) ?></textarea>
        </div>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn--ghost btn--sm">Сохранить этап</button></div>
    </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ПЛАТЕЖИ -->
<h3 class="section-title">💳 Платежи (<?= count($payments) ?>)</h3>

<?php if ($payments): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Дата</th><th>Назначение</th><th>Счёт</th><th>Статус</th><th style="text-align:right">Сумма</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
    <tbody>
      <?php foreach ($payments as $p): ?>
      <tr>
        <td class="text-muted"><?= Html::date($p['payment_date']) ?></td>
        <td><?= Html::e($p['purpose'] ?: '—') ?></td>
        <td class="td-num"><?= Html::e($p['invoice_number'] ?: '—') ?></td>
        <td><?= Html::badge($p['status'], PaymentStatus::from($p['status'])->label()) ?></td>
        <td class="td-num" style="text-align:right"><?= Html::money($p['amount']) ?></td>
        <?php if ($canEdit): ?>
        <td style="text-align:right">
          <form method="post" action="/payments/<?= (int)$p['id'] ?>/delete" style="display:inline">
            <?= $csrf->field() ?>
            <input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn--icon" data-confirm="Удалить платёж?">🗑</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Добавить платёж</div></div>
  <form method="post" action="/contracts/<?= (int)$c['id'] ?>/payments">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <div class="fg">
        <label>Сумма *</label>
        <input type="number" name="amount" step="0.01" min="0.01" required>
      </div>
      <div class="fg">
        <label>Статус</label>
        <select name="status">
          <?php foreach (PaymentStatus::cases() as $ps): ?>
            <option value="<?= $ps->value ?>"><?= $ps->label() ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>Дата</label>
        <input type="date" name="payment_date">
      </div>
      <div class="fg">
        <label>Назначение</label>
        <input type="text" name="purpose">
      </div>
      <div class="fg">
        <label>Номер счёта</label>
        <input type="text" name="invoice_number">
      </div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">+ Добавить</button></div>
  </form>
</div>
<?php endif; ?>

<!-- ДОКУМЕНТЫ -->
<h3 class="section-title">📎 Документы (<?= count($documents) ?>)</h3>

<?php if ($documents): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Файл</th><th>Тип</th><th>Размер</th><th>Загрузил</th><th>Дата</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
    <tbody>
      <?php foreach ($documents as $d): ?>
      <tr>
        <td><a href="/documents/<?= (int)$d['id'] ?>/download"><?= Html::e($d['original_name']) ?></a></td>
        <td><?= Html::badge('slate', $d['doc_type']) ?></td>
        <td class="text-muted"><?= Html::fileSize((int)$d['size_bytes']) ?></td>
        <td class="text-muted"><?= Html::e($d['uploader_name'] ?? '—') ?></td>
        <td class="text-muted"><?= Html::date($d['created_at']) ?></td>
        <?php if ($canEdit): ?>
        <td style="text-align:right">
          <form method="post" action="/documents/<?= (int)$d['id'] ?>/delete" style="display:inline">
            <?= $csrf->field() ?>
            <button type="submit" class="btn--icon" data-confirm="Удалить документ?">🗑</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="card mt-2">
  <form method="post" action="/contracts/<?= (int)$c['id'] ?>/documents" enctype="multipart/form-data">
    <?= $csrf->field() ?>
    <div class="form-grid" style="align-items:end">
      <div class="fg">
        <label>Тип документа</label>
        <select name="doc_type">
          <option value="contract">Контракт</option>
          <option value="supplement">Допсоглашение</option>
          <option value="act">Акт</option>
          <option value="invoice">Счёт</option>
          <option value="other" selected>Другое</option>
        </select>
      </div>
      <div class="upload-zone" style="flex:1">
        <input type="file" name="document" required>
        <div class="upload-zone__label">📁 Выберите файл или перетащите сюда</div>
      </div>
      <button type="submit" class="btn btn--primary btn--sm">📤 Загрузить</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($canEdit && $session->hasRole('admin')): ?>
<div style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border)">
  <form method="post" action="/contracts/<?= (int)$c['id'] ?>/delete">
    <?= $csrf->field() ?>
    <button type="submit" class="btn btn--danger btn--sm" data-confirm="Удалить контракт и все связанные документы/платежи?">🗑 Удалить контракт</button>
  </form>
</div>
<?php endif; ?>
