<?php
/**
 * Таблица объектов (п.6.1–6.3 ТЗ). Ожидает: $rows, $filters, $queues, $engineers, $years, $canCreate.
 */
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$sortLink = function (string $key, string $label) use ($filters, $base): string {
    $dir = ($filters['sort'] === $key && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    $qs = http_build_query(array_merge($filters, ['sort' => $key, 'dir' => $dir]));
    $arrow = $filters['sort'] === $key ? ($filters['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a class="sort-link" href="' . e($base . '/objects?' . $qs) . '">' . e($label) . $arrow . '</a>';
};
$curUrl = fn(array $extra) => e($base . '/objects?' . http_build_query(array_merge(array_filter($filters, fn($v) => $v !== '' && $v !== 0 && $v !== false), $extra)));
?>
<?php foreach (flash_take() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['text']) ?></div>
<?php endforeach; ?>

<div class="toolbar">
  <form method="get" action="<?= e($base) ?>/objects" class="filter-form">
    <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Поиск: адрес, заказчик, № договора, № письма…" class="inp">
    <select name="status" class="inp">
      <option value="">Все статусы</option>
      <?php foreach (ObjectModel::STATUSES as $code => [$lbl, $c]): ?>
        <option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="queue" class="inp">
      <option value="">Все очереди</option>
      <?php foreach ($queues as $q): ?>
        <option value="<?= (int)$q['id'] ?>" <?= (int)$filters['queue'] === (int)$q['id'] ? 'selected' : '' ?>><?= e($q['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="engineer" class="inp">
      <option value="">Все инженеры</option>
      <?php foreach ($engineers as $en): ?>
        <option value="<?= (int)$en['id'] ?>" <?= (int)$filters['engineer'] === (int)$en['id'] ? 'selected' : '' ?>><?= e($en['full_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="year" class="inp">
      <option value="">Все годы</option>
      <?php foreach ($years as $y): ?>
        <option value="<?= (int)$y ?>" <?= (int)$filters['year'] === (int)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
      <?php endforeach; ?>
    </select>
    <label class="chk"><input type="checkbox" name="pto" value="1" <?= $filters['pto'] ? 'checked' : '' ?>> ИД в ПТО</label>
    <label class="chk"><input type="checkbox" name="act" value="1" <?= $filters['act'] ? 'checked' : '' ?>> Акт подключ.</label>
    <button class="btn btn-primary" type="submit">Применить</button>
    <a class="btn" href="<?= e($base) ?>/objects">Сброс</a>
  </form>
  <?php if ($canCreate): ?>
    <a class="btn btn-add" href="<?= e($base) ?>/objects/new">＋ Новый объект</a>
  <?php endif; ?>
</div>

<p class="muted">Найдено: <?= count($rows) ?>. Клик по статусу — смена статуса; клик по чекбоксам ПТО/акта — отметка (если есть право).</p>

<div class="table-wrap">
<table class="objs">
  <thead>
  <tr>
    <th><?= $sortLink('num', '№') ?></th>
    <th><?= $sortLink('cust', 'Заказчик') ?></th>
    <th>Вх. письмо</th>
    <th><?= $sortLink('addr', 'Адрес объекта') ?></th>
    <th>Договор / стоимость</th>
    <th><?= $sortLink('eng', 'Инженер') ?></th>
    <th>ИД в ПТО</th>
    <th>Акт о подкл.</th>
    <th><?= $sortLink('status', 'Статус') ?></th>
    <th></th>
  </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r):
      $isSub = !empty($r['parent_id']);
      $color = $isSub ? ($r['parent_queue_color'] ?? '#e0e0e0') : ($r['queue_color'] ?? '#eeeeee');
      $numLabel = $isSub
          ? '#' . (int)($r['parent_number'] ?? 0) . '.' . (int)$r['sub_no']
          : (int)$r['number_in_year'];
      [$stLabel, $stColor] = ObjectModel::STATUSES[$r['status']] ?? ['—', '#999'];
      $card = e($base . '/objects?a=card&id=' . (int)$r['id']);
  ?>
  <tr class="<?= $isSub ? 'sub-row' : '' ?>">
    <td class="num-cell" style="background:<?= e($color) ?>">
      <a href="<?= $card ?>"><b><?= $numLabel ?></b></a>
      <?php if (!$isSub && !empty($r['queue_name'])): ?><div class="queue-name"><?= e($r['queue_name']) ?></div><?php endif; ?>
      <?php if ($isSub): ?><div class="queue-name">подобъект</div><?php endif; ?>
    </td>
    <td><?= e($r['customer']) ?: '<span class="muted">—</span>' ?></td>
    <td class="small">
      <?= e($r['letter_number']) ?><?php if (!empty($r['letter_date'])): ?><br><span class="muted"><?= e(fmt_date($r['letter_date'])) ?></span><?php endif; ?>
    </td>
    <td><a href="<?= $card ?>" class="addr-link"><?= e($r['address']) ?></a>
      <?php if (!empty($r['note'])): ?><div class="note-line" title="<?= e($r['note']) ?>">📝 <?= e(mb_strimwidth($r['note'], 0, 60, '…')) ?></div><?php endif; ?>
    </td>
    <td class="small">
      <?= e($r['contract_number']) ?: '<span class="muted">нет</span>' ?>
      <?php if (!empty($r['contract_date'])): ?><br><span class="muted">от <?= e(fmt_date($r['contract_date'])) ?></span><?php endif; ?>
      <?php if (!empty($r['cost'])): ?><br><b><?= e(fmt_money($r['cost'])) ?> ₽</b><?php endif; ?>
    </td>
    <td class="small"><?= e($r['engineer_name'] ?? '') ?: '<span class="muted">—</span>' ?></td>
    <td class="center">
      <input type="checkbox" class="mark-toggle" data-id="<?= (int)$r['id'] ?>" data-kind="pto"
             <?= (int)$r['pto_mark'] === 1 ? 'checked' : '' ?>
             title="<?= !empty($r['pto_date']) ? 'От ' . e(fmt_date($r['pto_date'])) : 'Не отмечено' ?>">
    </td>
    <td class="center">
      <input type="checkbox" class="mark-toggle" data-id="<?= (int)$r['id'] ?>" data-kind="act"
             <?= (int)$r['act_mark'] === 1 ? 'checked' : '' ?>
             title="<?= !empty($r['act_date']) ? 'От ' . e(fmt_date($r['act_date'])) : 'Не отмечено' ?>">
    </td>
    <td class="center">
      <button class="status-badge" data-id="<?= (int)$r['id'] ?>"
              style="background:<?= e($stColor) ?>" title="Клик — следующий статус"><?= e($stLabel) ?></button>
    </td>
    <td class="small nowrap">
      <?php if (Rights::can('objects.add_sub') && !$isSub): ?>
        <a href="<?= e($base) ?>/objects/new?parent=<?= (int)$r['id'] ?>" title="Добавить подобъект">＋п/о</a>
      <?php endif; ?>
      <?php if (Rights::can('objects.delete')): ?>
        <form method="post" action="<?= e($base) ?>/objects?a=delete" class="inl"
              onsubmit="return confirm('Удалить объект <?= $numLabel ?> и его подобъекты? Действие фиксируется в журнале.')">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="link-btn danger" type="submit" title="Удалить">✕</button>
        </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="10" class="empty">Объектов не найдено. <?= $canCreate ? 'Добавьте первый объект.' : '' ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
