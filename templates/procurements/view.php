<?php
use App\Shared\Enum\{LawType, ProcurementStatus};
use App\Shared\Utils\Html;

/** @var array $procurement */
$p = $procurement;
$proposals = $p['proposals'] ?? [];
$winner = $p['winner'] ?? null;
$canEdit = $session->hasRole('admin', 'manager');
?>

<div class="page-head">
  <h1>
    <?= Html::badge((string) $p['law_type'], LawType::from((string) $p['law_type'])->label()) ?>
    <?= Html::badge((string) $p['status'], ProcurementStatus::from((string) $p['status'])->label()) ?>
    №<?= Html::e($p['number']) ?>
  </h1>
  <div class="flex gap-sm">
    <?php if ($canEdit): ?>
      <a href="/procurements/<?= (int) $p['id'] ?>/edit" class="btn btn--ghost btn--sm">✏️ Редактировать</a>
    <?php endif; ?>
    <a href="/procurements" class="btn btn--ghost btn--sm">← К списку</a>
  </div>
</div>

<div class="finance-row">
  <div class="fin-card">
    <div class="fin-card__label">НМЦК</div>
    <div class="fin-card__val"><?= Html::money((float) $p['nmck_amount']) ?></div>
  </div>
  <div class="fin-card">
    <div class="fin-card__label">Получено КП</div>
    <div class="fin-card__val"><?= (int) ($p['proposals_count'] ?? 0) ?></div>
  </div>
  <div class="fin-card">
    <div class="fin-card__label">Минимальное КП</div>
    <div class="fin-card__val fin-card__val--g"><?= Html::money($p['min_quote_amount'] !== null ? (float) $p['min_quote_amount'] : null) ?></div>
  </div>
  <div class="fin-card">
    <div class="fin-card__label">Экономия к НМЦК</div>
    <div class="fin-card__val fin-card__val--a">
      <?php
        $saving = ($p['min_quote_amount'] !== null)
            ? (float) $p['nmck_amount'] - (float) $p['min_quote_amount']
            : null;
      ?>
      <?= Html::money($saving) ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="detail-grid">
    <div class="di">
      <div class="di__label">Предмет</div>
      <div class="di__value"><?= Html::e($p['subject']) ?></div>
    </div>
    <div class="di">
      <div class="di__label">Дедлайн КП</div>
      <div class="di__value"><?= Html::date($p['deadline_at'] ?? null) ?></div>
    </div>
    <div class="di">
      <div class="di__label">Создал</div>
      <div class="di__value"><?= Html::e($p['creator_name'] ?? '—') ?></div>
    </div>
    <?php if (!empty($p['notes'])): ?>
      <div class="di" style="grid-column:1/-1">
        <div class="di__label">Примечания</div>
        <div class="di__value"><?= nl2br(Html::e((string) $p['notes'])) ?></div>
      </div>
    <?php endif; ?>
  </div>
</div>

<h3 class="section-title">📨 Коммерческие предложения (<?= count($proposals) ?>)</h3>

<?php if ($proposals): ?>
  <div class="table-wrap">
    <table>
      <thead>
      <tr>
        <th>Поставщик</th>
        <th>ИНН</th>
        <th style="text-align:right">Сумма</th>
        <th>Валюта</th>
        <th>Дата</th>
        <th>Статус</th>
        <?php if ($canEdit): ?><th style="text-align:right">Действия</th><?php endif; ?>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($proposals as $proposal): ?>
        <tr>
          <td><?= Html::e($proposal['supplier_name']) ?></td>
          <td class="td-num"><?= Html::e($proposal['supplier_inn'] ?: '—') ?></td>
          <td class="td-num" style="text-align:right"><?= Html::money((float) $proposal['amount'], (string) $proposal['currency']) ?></td>
          <td><?= Html::e($proposal['currency']) ?></td>
          <td class="text-muted"><?= Html::date($proposal['submitted_at'] ?? null) ?></td>
          <td>
            <?php if ((int) $proposal['is_winner'] === 1): ?>
              <span class="badge badge--emerald">Победитель</span>
            <?php else: ?>
              <span class="badge badge--slate">Оценка</span>
            <?php endif; ?>
          </td>
          <?php if ($canEdit): ?>
          <td style="text-align:right">
            <?php if ((int) $proposal['is_winner'] !== 1): ?>
            <form method="post" action="/procurements/<?= (int) $p['id'] ?>/winner" style="display:inline">
              <?= $csrf->field() ?>
              <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>">
              <button type="submit" class="btn btn--ghost btn--sm">🏆</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/procurement-proposals/<?= (int) $proposal['id'] ?>/delete" style="display:inline">
              <?= $csrf->field() ?>
              <input type="hidden" name="procurement_id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn--icon" data-confirm="Удалить КП?">🗑</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="empty" style="padding:1.5rem 1rem">
    <p>КП пока нет</p>
  </div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Добавить КП</div></div>
  <form method="post" action="/procurements/<?= (int) $p['id'] ?>/proposals">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <div class="fg">
        <label>Поставщик *</label>
        <input type="text" name="supplier_name" required>
      </div>
      <div class="fg">
        <label>ИНН</label>
        <input type="text" name="supplier_inn" maxlength="12" pattern="\d{10,12}">
      </div>
      <div class="fg">
        <label>Сумма *</label>
        <input type="number" name="amount" required step="0.01" min="0.01">
      </div>
      <div class="fg">
        <label>Валюта</label>
        <input type="text" name="currency" value="RUB" maxlength="3" pattern="[A-Za-z]{3}">
      </div>
      <div class="fg">
        <label>Дата предложения</label>
        <input type="date" name="submitted_at">
      </div>
      <div class="fg">
        <label>Победитель</label>
        <input type="hidden" name="is_winner" value="0">
        <label style="display:flex;align-items:center;gap:.5rem;text-transform:none;letter-spacing:normal;font-size:.88rem;color:var(--text-1);font-weight:500">
          <input type="checkbox" name="is_winner" value="1" style="width:16px;height:16px">
          Отметить как победителя
        </label>
      </div>
      <div class="fg" style="grid-column:1/-1">
        <label>Комментарий</label>
        <textarea name="comment" rows="2"></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn--primary btn--sm">+ Добавить КП</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($canEdit && $session->hasRole('admin')): ?>
  <div style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border)">
    <form method="post" action="/procurements/<?= (int) $p['id'] ?>/delete">
      <?= $csrf->field() ?>
      <button type="submit" class="btn btn--danger btn--sm" data-confirm="Удалить закупку вместе со всеми КП?">🗑 Удалить закупку</button>
    </form>
  </div>
<?php endif; ?>
