<?php
use App\Shared\Utils\Html;
use App\Shared\Enum\{LawType, ContractStatus};
/** @var array|null $contract @var array $errors */
$c = $contract ?? [];
$isEdit = !empty($c['id']);
$v = fn(string $k) => Html::e($c[$k] ?? '');
?>

<div class="page-head">
  <h1><?= $isEdit ? '✏️ Редактировать' : '📝 Новый контракт' ?></h1>
  <a href="<?= $isEdit ? '/contracts/' . (int)$c['id'] : '/contracts' ?>" class="btn btn--ghost btn--sm">← Назад</a>
</div>

<?php if (!empty($errors)): ?>
<ul class="err-list"><?php foreach ($errors as $e): ?><li><?= Html::e($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<div class="card">
  <form method="post" action="<?= $isEdit ? '/contracts/' . (int)$c['id'] : '/contracts' ?>">
    <?= $csrf->field() ?>

    <div class="form-grid">
      <div class="fg">
        <label for="number">Номер контракта *</label>
        <input type="text" id="number" name="number" value="<?= $v('number') ?>" required>
      </div>

      <div class="fg">
        <label for="law_type">Закон *</label>
        <select id="law_type" name="law_type" required>
          <option value="">—</option>
          <?php foreach (LawType::cases() as $l): ?>
            <option value="<?= $l->value ?>" <?= ($c['law_type'] ?? '') === $l->value ? 'selected' : '' ?>><?= $l->label() ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="fg">
        <label for="status">Статус</label>
        <select id="status" name="status">
          <?php foreach (ContractStatus::cases() as $s): ?>
            <option value="<?= $s->value ?>" <?= ($c['status'] ?? 'draft') === $s->value ? 'selected' : '' ?>><?= $s->label() ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="fg">
        <label for="total_amount">Сумма, ₽ *</label>
        <input type="number" id="total_amount" name="total_amount" step="0.01" min="0" value="<?= $v('total_amount') ?>" required>
      </div>

      <div class="fg" id="nmck-group" style="<?= ($c['law_type'] ?? '') !== '44' ? 'display:none' : '' ?>">
        <label for="nmck_amount">НМЦК, ₽ (44-ФЗ)</label>
        <input type="number" id="nmck_amount" name="nmck_amount" step="0.01" min="0" value="<?= $v('nmck_amount') ?>">
      </div>

      <div class="fg" style="grid-column:1/-1">
        <label for="subject">Предмет контракта *</label>
        <textarea id="subject" name="subject" rows="2" required><?= $v('subject') ?></textarea>
      </div>

      <div class="fg">
        <label for="contractor_name">Контрагент *</label>
        <input type="text" id="contractor_name" name="contractor_name" value="<?= $v('contractor_name') ?>" required>
      </div>

      <div class="fg">
        <label for="contractor_inn">ИНН</label>
        <input type="text" id="contractor_inn" name="contractor_inn" maxlength="12" value="<?= $v('contractor_inn') ?>">
      </div>

      <div class="fg">
        <label for="signed_at">Дата подписания</label>
        <input type="date" id="signed_at" name="signed_at" value="<?= $v('signed_at') ?>">
      </div>

      <div class="fg">
        <label for="expires_at">Дата окончания</label>
        <input type="date" id="expires_at" name="expires_at" value="<?= $v('expires_at') ?>">
      </div>

      <div class="fg" style="grid-column:1/-1">
        <label for="notes">Примечания</label>
        <textarea id="notes" name="notes" rows="3"><?= $v('notes') ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn--primary"><?= $isEdit ? '💾 Сохранить' : '✓ Создать' ?></button>
      <a href="<?= $isEdit ? '/contracts/' . (int)$c['id'] : '/contracts' ?>" class="btn btn--ghost">Отмена</a>
    </div>
  </form>
</div>
