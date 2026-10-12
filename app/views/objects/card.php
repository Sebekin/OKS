<?php
/**
 * Карточка объекта (п.6.4–6.6 ТЗ). Ожидает: $obj, $parent, $subs, $docs, $files,
 * $letters, $invoices, $history, $queues, $engineers, $docTypes, $queueColor.
 */
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$isSub = !empty($obj['parent_id']);
$canEdit = Rights::can('objects.edit_fields') && ObjectModel::canAccess($obj);
$stLabel = ObjectModel::STATUSES[$obj['status']][0] ?? '—';
$stColor = ObjectModel::STATUSES[$obj['status']][1] ?? '#999';
?>
<?php foreach (flash_take() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['text']) ?></div>
<?php endforeach; ?>

<div class="card-head" style="border-left:8px solid <?= e($queueColor) ?>">
  <div>
    <?php if ($parent): ?>
      <a href="<?= e($base) ?>/objects?a=card&id=<?= (int)$parent['id'] ?>">← объект #<?= (int)$parent['number_in_year'] ?>/<?= (int)$parent['year'] ?></a>
      <span class="muted"> / подобъект №<?= (int)$obj['sub_no'] ?></span>
    <?php else: ?>
      <span class="big-num" style="background:<?= e($queueColor) ?>">#<?= (int)$obj['number_in_year'] ?>/<?= (int)$obj['year'] ?></span>
      <?php if (!empty($obj['queue_name'])): ?><span class="muted">очередь: <?= e($obj['queue_name']) ?></span><?php endif; ?>
    <?php endif; ?>
    <h2 class="addr-title"><?= e($obj['address']) ?></h2>
  </div>
  <div class="head-actions">
    <button class="status-badge big" data-id="<?= (int)$obj['id'] ?>" style="background:<?= e($stColor) ?>"><?= e($stLabel) ?></button>
    <?php if (Rights::can('objects.add_sub') && !$isSub): ?>
      <a class="btn" href="<?= e($base) ?>/objects/new?parent=<?= (int)$obj['id'] ?>">＋ Подобъект</a>
    <?php endif; ?>
    <?php if (Rights::can('objects.delete')): ?>
      <form method="post" action="<?= e($base) ?>/objects?a=delete" class="inl"
            onsubmit="return confirm('Удалить этот объект<?= $isSub ? '' : ' со всеми подобъектами' ?>?')">
        <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$obj['id'] ?>">
        <button class="btn btn-danger" type="submit">Удалить</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="tabs" data-tabs>
  <nav class="tab-nav">
    <button class="tab-btn active" data-tab="tab-data">Реквизиты</button>
    <button class="tab-btn" data-tab="tab-subs">Подобъекты (<?= count($subs) ?>)</button>
    <button class="tab-btn" data-tab="tab-docs">Документы (<?= count($docs) ?>)</button>
    <button class="tab-btn" data-tab="tab-letters">Письма (<?= count($letters) ?>)</button>
    <button class="tab-btn" data-tab="tab-invoices">Счета (<?= count($invoices) ?>)</button>
    <button class="tab-btn" data-tab="tab-history">История (<?= count($history) ?>)</button>
  </nav>

  <section id="tab-data" class="tab-pane active">
    <?php if ($canEdit): ?>
    <form method="post" action="<?= e($base) ?>/objects?a=edit" class="obj-form">
      <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$obj['id'] ?>">
    <?php else: ?><div class="obj-form"><?php endif; ?>
      <div class="grid2">
        <label>Вх. письмо<input class="inp" name="letter_number" value="<?= e($obj['letter_number']) ?>" <?= $canEdit ? '' : 'readonly' ?>></label>
        <label>Дата письма<input class="inp" type="date" name="letter_date" value="<?= e($obj['letter_date'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
        <label class="span2">Адрес<input class="inp" name="address" value="<?= e($obj['address']) ?>" <?= $canEdit ? '' : 'readonly' ?>></label>
        <label>Заказчик<input class="inp" name="customer" value="<?= e($obj['customer']) ?>" <?= $canEdit ? '' : 'readonly' ?>></label>
        <label>№ договора<input class="inp" name="contract_number" value="<?= e($obj['contract_number']) ?>" <?= $canEdit ? '' : 'readonly' ?>></label>
        <label>Дата договора<input class="inp" type="date" name="contract_date" value="<?= e($obj['contract_date'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
        <label>Стоимость, ₽<input class="inp" name="cost" value="<?= e($obj['cost'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>></label>
        <?php if (!$isSub): ?>
        <label>Очередь
          <select class="inp" name="queue_id" <?= $canEdit ? '' : 'disabled' ?>>
            <option value="">—</option>
            <?php foreach ($queues as $q): ?>
              <option value="<?= (int)$q['id'] ?>" <?= (int)$obj['queue_id'] === (int)$q['id'] ? 'selected' : '' ?>><?= e($q['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endif; ?>
        <label>Инженер
          <select class="inp" name="engineer_id" <?= $canEdit && Rights::can('objects.reassign') ? '' : 'disabled' ?>>
            <option value="">—</option>
            <?php foreach ($engineers as $en): ?>
              <option value="<?= (int)$en['id'] ?>" <?= (int)$obj['engineer_id'] === (int)$en['id'] ? 'selected' : '' ?>><?= e($en['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="span2">Примечание<textarea class="inp" name="note" rows="3" <?= $canEdit ? '' : 'readonly' ?>><?= e($obj['note'] ?? '') ?></textarea></label>
      </div>

      <div class="marks-row">
        <label class="mark-box"><input type="checkbox" class="mark-toggle" data-id="<?= (int)$obj['id'] ?>" data-kind="pto"
          <?= (int)$obj['pto_mark'] === 1 ? 'checked' : '' ?>> ИД в ПТО
          <span class="muted"><?= $obj['pto_date'] ? 'от ' . e(fmt_date($obj['pto_date'])) : '—' ?></span></label>
        <label class="mark-box"><input type="checkbox" class="mark-toggle" data-id="<?= (int)$obj['id'] ?>" data-kind="act"
          <?= (int)$obj['act_mark'] === 1 ? 'checked' : '' ?>> Акт о подключении
          <span class="muted"><?= $obj['act_date'] ? 'от ' . e(fmt_date($obj['act_date'])) : '—' ?></span></label>
      </div>

      <?php if ($canEdit): ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Сохранить</button></div>
      <?php else: ?></div><?php endif; ?>
      <p class="muted small">Создан: <?= e(fmt_datetime($obj['created_at'])) ?> · Изменён: <?= e(fmt_datetime($obj['updated_at'])) ?></p>
  </section>

  <section id="tab-subs" class="tab-pane">
    <?php if ($subs): ?>
    <table class="objs compact">
      <thead><tr><th>№</th><th>Адрес / сети</th><th>Договор</th><th>Стоимость</th><th>Инженер</th><th>Статус</th></tr></thead>
      <tbody>
      <?php foreach ($subs as $s): [$sl, $sc] = ObjectModel::STATUSES[$s['status']] ?? ['—', '#999']; ?>
        <tr>
          <td><a href="<?= e($base) ?>/objects?a=card&id=<?= (int)$s['id'] ?>"><b><?= (int)$s['sub_no'] ?></b></a></td>
          <td><?= e($s['address']) ?></td>
          <td class="small"><?= e($s['contract_number']) ?: '—' ?></td>
          <td class="small"><?= $s['cost'] ? e(fmt_money($s['cost'])) . ' ₽' : '—' ?></td>
          <td class="small"><?= e($s['engineer_name'] ?? '') ?></td>
          <td><button class="status-badge" data-id="<?= (int)$s['id'] ?>" style="background:<?= e($sc) ?>"><?= e($sl) ?></button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="muted">Подобъектов нет.</p><?php endif; ?>
    <?php if (!$isSub && Rights::can('objects.add_sub')): ?>
      <a class="btn" href="<?= e($base) ?>/objects/new?parent=<?= (int)$obj['id'] ?>">＋ Добавить подобъект</a>
    <?php endif; ?>
  </section>

  <section id="tab-docs" class="tab-pane">
    <?php if ($canEdit): ?>
    <form method="post" action="<?= e($base) ?>/objects?a=doc_add" enctype="multipart/form-data" class="mini-form">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int)$obj['id'] ?>">
      <select name="type_id" required>
        <option value="">— тип документа —</option>
        <?php foreach ($docTypes as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="comment" placeholder="Комментарий (необязательно)" maxlength="500">
      <label class="file-btn">📎 файл <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png"></label>
      <button class="btn btn-primary" type="submit">Добавить</button>
    </form>
    <?php endif; ?>
    <?php if ($docs): ?>
    <ul class="plain-list">
      <?php foreach ($docs as $d): ?>
        <li><?= e($d['type_name'] ?? 'Без типа') ?> — <?= e($d['comment'] ?? '') ?>
            <span class="muted">(<?= e(fmt_datetime($d['created_at'])) ?>)</span>
          <?php foreach (($files['document'][$d['id']] ?? []) as $f): ?>
            <a class="file-link" href="<?= e($base) ?>/file?i=<?= (int)$f['id'] ?>" target="_blank" rel="noopener">
              📄 <?= e($f['orig_name']) ?> <span class="muted small">(<?= e(fmt_bytes((int)$f['size_bytes'])) ?>)</span></a>
            <?php if ($canEdit): ?>
            <form method="post" action="<?= e($base) ?>/objects?a=file_del" class="inl" onsubmit="return confirm('Удалить файл?')">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$obj['id'] ?>">
              <input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
              <button class="link-btn danger" title="Удалить файл">✕</button>
            </form>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if ($canEdit): ?>
          <form method="post" action="<?= e($base) ?>/objects?a=doc_del" class="inl" onsubmit="return confirm('Удалить документ со всеми файлами?')">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$obj['id'] ?>">
            <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
            <button class="link-btn danger" title="Удалить документ">🗑</button>
          </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="muted">Документов пока нет.</p><?php endif; ?>
  </section>

  <section id="tab-letters" class="tab-pane">
    <?php if ($canEdit): ?>
    <form method="post" action="<?= e($base) ?>/objects?a=letter_add" enctype="multipart/form-data" class="mini-form">
      <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$obj['id'] ?>">
      <select name="direction"><option value="in">входящее</option><option value="out">исходящее</option></select>
      <input type="text" name="number" placeholder="№ письма" maxlength="100">
      <input type="date" name="ldate">
      <input type="text" name="counterparty" placeholder="Контрагент" maxlength="255">
      <input type="text" name="descr" placeholder="Суть" maxlength="500">
      <label class="file-btn">📎 скан <input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.png"></label>
      <button class="btn btn-primary" type="submit">Добавить</button>
    </form>
    <?php endif; ?>
    <?php if ($letters): ?>
    <table class="objs compact">
      <thead><tr><th>Напр.</th><th>№</th><th>Дата</th><th>Контрагент</th><th>Суть</th><th>Файл</th><?php if ($canEdit)?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($letters as $l): ?>
        <tr>
          <td><?= $l['direction'] === 'in' ? 'вх.' : 'исх.' ?></td>
          <td><?= e($l['number']) ?></td>
          <td><?= e(fmt_date($l['ldate'])) ?></td>
          <td><?= e($l['counterparty']) ?></td>
          <td class="small"><?= e($l['descr']) ?></td>
          <td><?php foreach (($files['letter'][$l['id']] ?? []) as $f): ?>
                <a class="file-link" href="<?= e($base) ?>/file?i=<?= (int)$f['id'] ?>" target="_blank" rel="noopener">📎</a>
              <?php endforeach; ?></td>
          <?php if ($canEdit): ?>
          <td><form method="post" action="<?= e($base) ?>/objects?a=letter_del" class="inl" onsubmit="return confirm('Удалить письмо?')">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$obj['id'] ?>">
              <input type="hidden" name="letter_id" value="<?= (int)$l['id'] ?>">
              <button class="link-btn danger">🗑</button></form></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$letters && !$canEdit): ?><p class="muted">Писем нет.</p><?php endif; ?>
  </section>

  <section id="tab-invoices" class="tab-pane">
    <?php if ($canEdit): ?>
    <form method="post" action="<?= e($base) ?>/objects?a=invoice_add" enctype="multipart/form-data" class="mini-form">
      <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$obj['id'] ?>">
      <input type="text" name="number" placeholder="№ счёта" maxlength="100" required>
      <input type="date" name="idate">
      <input type="text" name="amount" placeholder="Сумма, ₽">
      <label class="file-btn">📎 скан <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png"></label>
      <button class="btn btn-primary" type="submit">Добавить</button>
    </form>
    <?php endif; ?>
    <?php if ($invoices): ?>
    <table class="objs compact">
      <thead><tr><th>№</th><th>Дата</th><th>Сумма</th><th>Оплата</th><th>Файл</th><?php if ($canEdit)?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($invoices as $v): ?>
        <tr>
          <td><?= e($v['number']) ?></td>
          <td><?= e(fmt_date($v['idate'])) ?></td>
          <td><?= e(fmt_money($v['amount'])) ?> ₽</td>
          <td><?= (int)$v['paid'] === 1
              ? '<span class="paid">оплачен ' . e(fmt_date($v['pay_date'])) . ($v['pay_amount'] ? ' — ' . e(fmt_money($v['pay_amount'])) . ' ₽' : '') . '</span>'
              : '<span class="unpaid">не оплачен</span>' ?>
              <?php if ($canEdit): ?>
              <form method="post" action="<?= e($base) ?>/objects?a=invoice_pay" class="inl" id="pay-<?= (int)$v['id'] ?>">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$obj['id'] ?>">
                <input type="hidden" name="invoice_id" value="<?= (int)$v['id'] ?>">
                <?php if ((int)$v['paid'] === 1): ?>
                  <button class="link-btn" name="paid" value="" title="Снять отметку">↺</button>
                <?php else: ?>
                  <input type="date" name="pay_date" class="tiny-date" title="Дата оплаты">
                  <input type="text" name="pay_amount" class="tiny-money" placeholder="Сумма" value="<?= e((string)($v['amount'] ?? '')) ?>">
                  <button class="link-btn ok" name="paid" value="1" title="Отметить оплату">✓</button>
                <?php endif; ?>
              </form>
              <?php endif; ?>
          </td>
          <td><?php foreach (($files['invoice'][$v['id']] ?? []) as $f): ?>
                <a class="file-link" href="<?= e($base) ?>/file?i=<?= (int)$f['id'] ?>" target="_blank" rel="noopener">📎</a>
              <?php endforeach; ?></td>
          <?php if ($canEdit): ?>
          <td><form method="post" action="<?= e($base) ?>/objects?a=invoice_del" class="inl" onsubmit="return confirm('Удалить счёт?')">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$obj['id'] ?>">
              <input type="hidden" name="invoice_id" value="<?= (int)$v['id'] ?>">
              <button class="link-btn danger">🗑</button></form></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$invoices && !$canEdit): ?><p class="muted">Счетов нет.</p><?php endif; ?>
  </section>

  <section id="tab-history" class="tab-pane">
    <?php if ($history): ?>
    <table class="objs compact">
      <thead><tr><th>Когда</th><th>Кто</th><th>Что</th><th>Поле</th><th>Было → стало</th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr>
          <td class="small nowrap"><?= e(fmt_datetime($h['created_at'])) ?></td>
          <td class="small"><?= e($h['full_name'] ?? 'система') ?></td>
          <td class="small"><?= e($h['action']) ?></td>
          <td class="small"><?= e($h['field'] ?? '') ?></td>
          <td class="small"><?= e($h['old_value'] ?? '—') ?> → <b><?= e($h['new_value'] ?? '—') ?></b></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="muted">Изменений пока не зафиксировано.</p><?php endif; ?>
  </section>
</div>
