<?php /** Рабочий стол: сводка + очереди. */ ?>
<p class="page-sub">Сводка на <?= e(date('d.m.Y H:i')) ?></p>

<div class="cards-grid" style="margin-bottom:16px">
  <div class="stat-card"><div class="num"><?= (int)$stats['total'] ?></div><div class="lbl">Объектов в работе и сданных</div></div>
  <div class="stat-card"><div class="num" style="color:var(--warn)"><?= (int)$stats['work'] ?></div><div class="lbl">Статус «В работе»</div></div>
  <div class="stat-card"><div class="num" style="color:var(--ok)"><?= (int)$stats['done'] ?></div><div class="lbl">Статус «Сдан»</div></div>
</div>

<div class="panel">
  <h3>Очереди назначения</h3>
  <?php if (!$queues): ?>
    <p class="muted mb0">Очереди ещё не настроены — они появятся после установки и заполнения демо-данных.</p>
  <?php else: ?>
    <table class="grid">
      <thead><tr><th>Очередь</th><th>Следующий ответственный</th><th>Участники</th></tr></thead>
      <tbody>
      <?php foreach ($queues as $q): ?>
        <tr>
          <td><span class="badge" style="background:<?= e($q['queue']['color'] ?? '#eceff2') ?>">&nbsp;</span> <?= e($q['queue']['name'] ?? '') ?></td>
          <td><?= e($q['next'] ?? '—') ?></td>
          <td class="muted"><?php foreach ($q['members'] as $m) echo e($m['full_name']) . ' · '; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="panel">
  <h3>Быстрые ссылки</h3>
  <a class="btn" href="objects">Перейти к объектам</a>
  <a class="btn secondary" href="profile">Профиль и безопасность</a>
</div>
