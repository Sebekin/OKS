<?php /** 2FA включена: показ резервных кодов (один раз!). */ ?>
<div class="panel" style="max-width:460px">
  <div class="alert success">Двухфакторная защита включена.</div>
  <h3>Резервные коды — сохраните их сейчас</h3>
  <p class="muted">Каждый код используется один раз, если нет доступа к приложению-аутентификатору.
     После закрытия этой страницы коды больше не показываются.</p>
  <ol style="font-family:monospace; font-size:15px; line-height:1.9">
    <?php foreach ($codes as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
  </ol>
  <a class="btn" href="/profile">Готово, перешёл в сохранённое место</a>
</div>
