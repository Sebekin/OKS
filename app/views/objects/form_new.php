<?php
/** Форма добавления объекта/подобъекта. Ожидает: $parent (?array), $queues, $engineers. */
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
?>
<div class="card-form">
<form method="post" action="<?= e($base) ?>/objects?a=create" class="obj-form">
  <?= Csrf::field() ?>
  <?php if ($parent): ?>
    <input type="hidden" name="parent_id" value="<?= (int)$parent['id'] ?>">
    <div class="parent-note">Подобъект к объекту <b>#<?= (int)$parent['number_in_year'] ?>/<?= (int)$parent['year'] ?></b>:
      «<?= e($parent['address']) ?>». Очередь и инженер наследуются от родителя.</div>
  <?php endif; ?>

  <h2>Исходные данные</h2>
  <div class="grid2">
    <label>Вх. письмо (№ / исх. №)<input class="inp" name="letter_number" maxlength="100"></label>
    <label>Дата письма<input class="inp" type="date" name="letter_date"></label>
    <label class="span2">Адрес объекта *<input class="inp" name="address" required maxlength="500"></label>
    <label>Заказчик<input class="inp" name="customer" maxlength="255"></label>
    <label>№ договора<input class="inp" name="contract_number" maxlength="100"></label>
    <label>Дата договора<input class="inp" type="date" name="contract_date"></label>
    <label>Стоимость по договору, ₽<input class="inp" name="cost" inputmode="decimal" placeholder="350000.00"></label>
    <?php if (!$parent): ?>
      <label>Очередь
        <select class="inp" name="queue_id">
          <option value="">— не назначена —</option>
          <?php foreach ($queues as $q): ?>
            <option value="<?= (int)$q['id'] ?>" style="background:<?= e($q['color']) ?>"><?= e($q['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Ответственный инженер
        <select class="inp" name="engineer_id">
          <option value="">— авто: тот, кто создаёт —</option>
          <?php foreach ($engineers as $en): ?>
            <option value="<?= (int)$en['id'] ?>"><?= e($en['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>
    <label class="span2">Примечание<textarea class="inp" name="note" rows="3"></textarea></label>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Сохранить объект</button>
    <a class="btn" href="<?= $parent ? e($base . '/objects?a=card&id=' . (int)$parent['id']) : e($base . '/objects') ?>">Отмена</a>
  </div>
</form>
</div>
