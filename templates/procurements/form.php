<?php
use App\Shared\Enum\{LawType, ProcurementStatus};
use App\Shared\Utils\Html;

/** @var array|null $procurement @var array $errors */
$p = $procurement ?? [];
$isEdit = !empty($p['id']);
$v = static fn(string $key, string $default = ''): string => Html::e((string) ($p[$key] ?? $default));
?>

<div class="page-head">
  <h1><?= $isEdit ? '✏️ Редактирование закупки' : '📝 Новая закупка' ?></h1>
  <a href="<?= $isEdit ? '/procurements/' . (int) $p['id'] : '/procurements' ?>" class="btn btn--ghost btn--sm">← Назад</a>
</div>

<?php if (!empty($errors)): ?>
  <ul class="err-list"><?php foreach ($errors as $e): ?><li><?= Html::e($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<div class="card">
  <form method="post" action="<?= $isEdit ? '/procurements/' . (int) $p['id'] : '/procurements' ?>">
    <?= $csrf->field() ?>

    <div class="form-grid">
      <div class="fg">
        <label for="number">Номер закупки *</label>
        <input type="text" id="number" name="number" value="<?= $v('number') ?>" required>
      </div>

      <div class="fg">
        <label for="law_type">Закон *</label>
        <select id="law_type" name="law_type" required>
          <option value="">—</option>
          <?php foreach (LawType::cases() as $law): ?>
            <option value="<?= $law->value ?>" <?= ($p['law_type'] ?? '') === $law->value ? 'selected' : '' ?>><?= $law->label() ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="fg">
        <label for="status">Статус *</label>
        <select id="status" name="status" required>
          <?php foreach (ProcurementStatus::cases() as $status): ?>
            <option value="<?= $status->value ?>" <?= ($p['status'] ?? ProcurementStatus::DRAFT->value) === $status->value ? 'selected' : '' ?>><?= $status->label() ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="fg">
        <label for="nmck_amount">НМЦК, ₽ *</label>
        <input type="number" id="nmck_amount" name="nmck_amount" value="<?= $v('nmck_amount') ?>" step="0.01" min="0.01" required>
      </div>

      <div class="fg">
        <label for="deadline_at">Дедлайн КП</label>
        <input type="date" id="deadline_at" name="deadline_at" value="<?= $v('deadline_at') ?>">
      </div>

      <div class="fg" style="grid-column:1/-1">
        <label for="subject">Предмет закупки *</label>
        <textarea id="subject" name="subject" rows="3" required><?= $v('subject') ?></textarea>
      </div>

      <div class="fg" style="grid-column:1/-1">
        <label for="notes">Примечания</label>
        <textarea id="notes" name="notes" rows="4"><?= $v('notes') ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn--primary"><?= $isEdit ? '💾 Сохранить' : '✓ Создать' ?></button>
      <a href="<?= $isEdit ? '/procurements/' . (int) $p['id'] : '/procurements' ?>" class="btn btn--ghost">Отмена</a>
    </div>
  </form>
</div>
