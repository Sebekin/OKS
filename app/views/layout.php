<?php
/** Общий макет страниц (шапка + боковое меню). Ожидает: $pageTitle, $__content, переменные окружения. */
$me = Auth::user();
$nav = [
    '/'        => ['Рабочий стол', '⌂'],
    '/objects' => ['Объекты', '▦'],
];
if (Rights::visible('chat.use'))      { $nav['/chat']      = ['Чат', '✉']; }
if (Rights::visible('audit.view'))    { $nav['/audit']     = ['Журнал', '☰']; }
if (Rights::can('admin.users') || Rights::can('admin.settings')) { $nav['/admin'] = ['Админ-панель', '⚙']; }
$nav['/profile'] = ['Профиль', '👤'];

$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
// убрать префикс подпапки
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($base !== '' && $base !== '/' && str_starts_with($currentPath, $base)) {
    $currentPath = substr($currentPath, strlen($base)) ?: '/';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? '') ?> — <?= e(Config::get('app.name', 'Учёт ОКС')) ?></title>
<link rel="stylesheet" href="<?= e($base) ?>/css/app.css">
</head>
<body>
<header class="topbar">
  <div class="brand"><?= e(Config::get('app.name', 'Учёт ОКС')) ?></div>
  <div class="spacer"></div>
  <div class="userbox">
    <span><?= e($me['full_name'] ?? '') ?></span>
    <span class="role"><?= e(Rights::ROLES[$me['role'] ?? ''] ?? '') ?></span>
    <a class="logout" href="<?= e($base) ?>/logout">Выход</a>
  </div>
</header>
<nav class="sidebar">
  <?php foreach ($nav as $href => [$label, $icon]): ?>
    <a class="nav-item<?= $currentPath === rtrim($href, '/') ? ' active' : '' ?>" href="<?= e($base . $href) ?>">
      <span><?= $icon ?></span><span><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<main class="content">
  <h1 class="page-title"><?= e($pageTitle ?? '') ?></h1>
  <?= $__content ?>
</main>
<script src="<?= e($base) ?>/js/app.js"></script>
</body>
</html>
