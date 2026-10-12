<?php /** Рабочий стол (п.7 ТЗ): приветствие; очереди «кто следующий»; чат + последние события в две колонки. */ ?>
<p class="page-sub">Сводка на <?= e(date('d.m.Y H:i')) ?></p>

<div class="cards-grid" style="margin-bottom:16px">
  <div class="stat-card"><div class="num"><?= (int)$stats['total'] ?></div><div class="lbl">Объектов в работе и сданных</div></div>
  <div class="stat-card"><div class="num" style="color:var(--warn)"><?= (int)$stats['work'] ?></div><div class="lbl">Статус «В работе»</div></div>
  <div class="stat-card"><div class="num" style="color:var(--ok)"><?= (int)$stats['done'] ?></div><div class="lbl">Статус «Сдан»</div></div>
</div>

<h3 class="section-h">Очереди заявок — кто следующий</h3>
<div class="queue-cards">
  <?php if (!$queues): ?>
    <p class="muted">Очереди ещё не настроены — добавьте их в админ-панели.</p>
  <?php else: foreach ($queues as $q): ?>
    <div class="queue-card">
      <div class="queue-head" style="background:<?= e($q['queue']['color'] ?? '#eceff2') ?>">
        <?= e($q['queue']['name']) ?>
      </div>
      <div class="queue-body">
        <div class="queue-next-label">Следующий по ротации</div>
        <div class="queue-next-name"><?= e($q['next'] ?? '— нет участников —') ?></div>
        <div class="queue-members muted">
          <?= e(implode(' · ', array_column($q['members'], 'full_name'))) ?: 'состав не назначен' ?>
        </div>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<div class="two-col">
  <div class="panel">
    <h3>Внутренний чат <?php if (($unreadTotal ?? 0) > 0): ?><span class="chip-unread"><?= (int)$unreadTotal ?></span><?php endif; ?></h3>
    <?php if (!empty($chatThreads)): ?>
      <ul class="thread-list">
        <?php foreach ($chatThreads as $t): ?>
          <li><a href="chat?thread=<?= (int)$t['id'] ?>">
            <?= e($t['title']) ?>
            <?php if ((int)$t['unread'] > 0): ?><span class="chip-unread"><?= (int)$t['unread'] ?></span><?php endif; ?>
          </a></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted mb8">Общий чат «Отдел» появится после установки.</p>
    <?php endif; ?>
    <a class="btn secondary sm" href="chat">Открыть чат →</a>
  </div>

  <div class="panel">
    <h3>Последние события</h3>
    <?php if (empty($events)): ?>
      <p class="muted mb0">Пока пусто.</p>
    <?php else: ?>
      <ul class="event-list">
        <?php foreach ($events as $ev): ?>
          <li>
            <span class="ev-time"><?= e(date('d.m H:i', strtotime((string)$ev['created_at']))) ?></span>
            <span class="ev-user"><?= e($ev['full_name'] ?? '—') ?></span>
            <span class="ev-text"><?= e($ev['action']) ?><?php if (!empty($ev['field'])) echo ' · ' . e($ev['field']); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <h3>Быстрые ссылки</h3>
  <a class="btn" href="objects">Перейти к объектам</a>
  <a class="btn secondary" href="profile">Профиль и безопасность</a>
</div>
