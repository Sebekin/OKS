<?php /** Экран входа (без общего макета — доступ не авторизован). */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Вход — <?= e(Config::get('app.name', 'Учёт ОКС')) ?></title>
<link rel="stylesheet" href="css/app.css">
</head>
<body>
<div class="login-screen">
  <form class="login-box" method="post" action="login">
    <h1><?= e(Config::get('app.name', 'Учёт ОКС')) ?></h1>
    <div class="sub">Вход в систему</div>

    <?php if (!empty($timeout)): ?>
      <div class="alert info">Сессия завершена из-за бездействия. Войдите снова.</div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="form-row">
      <label for="login">Логин</label>
      <input type="text" id="login" name="login" required autofocus autocomplete="username">
    </div>
    <div class="form-row">
      <label for="password">Пароль</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <?= Csrf::field() ?>
    <button class="btn" style="width:100%" type="submit">Войти</button>
  </form>
</div>
</body>
</html>
