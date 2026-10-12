<?php /** Подключение 2FA: QR-код + ввод проверочного кода. */ ?>
<div class="panel" style="max-width:460px">
  <h3>Шаг 1. Отсканируйте QR-код</h3>
  <p class="muted">Google Authenticator, Яндекс.Ключ или любое TOTP-приложение.</p>
  <div style="text-align:center"><?= $qrSvg /* SVG из Totp::qrSvg, безопасен — генерируется системой */ ?></div>
  <p class="muted" style="word-break:break-all">Секрет вручную: <code><?= e($secret) ?></code></p>

  <h3 style="margin-top:18px">Шаг 2. Введите текущий код</h3>
  <form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="2fa_confirm">
    <div class="form-row"><input type="text" name="code" class="code-input" placeholder="000000" required autofocus></div>
    <button class="btn" type="submit">Подтвердить и включить 2FA</button>
    <button class="btn secondary" type="submit" name="action" value="2fa_cancel" formnovalidate>Отмена</button>
  </form>
</div>
