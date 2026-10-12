<?php /** Шаг 2 входа: код 2FA (TOTP или резервный). */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Подтверждение — <?= e(Config::get('app.name', 'Учёт ОКС')) ?></title>
<link rel="stylesheet" href="css/app.css">
</head>
<body>
<div class="login-screen">
  <form class="login-box" method="post" action="login">
    <h1>Подтверждение входа</h1>
    <div class="sub">Введите 6-значный код из приложения-аутентификатора<br>или один из резервных кодов.</div>

    <?php if (!empty($error)): ?>
      <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="form-row">
      <input type="text" name="code" class="code-input" required autofocus
             autocomplete="one-time-code" inputmode="text" placeholder="000000">
    </div>
    <input type="hidden" name="pending_id" value="<?= (int)$pendingId ?>">
    <?= Csrf::field() ?>
    <button class="btn" style="width:100%" type="submit">Подтвердить</button>
    <p style="text-align:center;margin:14px 0 0"><a href="login">← Другой пользователь</a></p>
  </form>
</div>
</body>
</html>
