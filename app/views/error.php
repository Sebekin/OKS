<?php /** Страница ошибки. Ожидает: $code, $message */ ?>
<div class="error-page">
  <div class="error-code"><?= (int)$code ?></div>
  <p class="error-msg"><?= e($message) ?></p>
  <a class="btn" href="<?= e(dirname($_SERVER['SCRIPT_NAME'])) ?>/objects">К списку объектов</a>
</div>
