<?php
/**
 * Точка входа приложения «Учёт ОКС».
 * Все запросы идут через index.php (фронт-контроллер).
 */
declare(strict_types=1);

define('OKS_ROOT', dirname(__DIR__));

require OKS_ROOT . '/app/core/helpers.php';
require OKS_ROOT . '/app/core/Config.php';
require OKS_ROOT . '/app/core/DB.php';
require OKS_ROOT . '/app/core/Audit.php';
require OKS_ROOT . '/app/core/Auth.php';
require OKS_ROOT . '/app/core/Totp.php';
require OKS_ROOT . '/app/core/Csrf.php';
require OKS_ROOT . '/app/core/Router.php';
require OKS_ROOT . '/app/core/View.php';
require OKS_ROOT . '/app/core/Rights.php';

Config::load(OKS_ROOT . '/app/config.php');
date_default_timezone_set(Config::get('app.timezone', 'Europe/Moscow'));

// Любая страница требует авторизации (кроме login/logout/install) — п.5.5 ТЗ
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

$router = new Router();

// --- Маршруты приложения (контроллеры регистрируют себя сами) ---
require OKS_ROOT . '/app/controllers/Auth.php';      // /login, /logout, /2fa
require OKS_ROOT . '/app/controllers/Dashboard.php'; // / — главная
require OKS_ROOT . '/app/controllers/Objects.php';   // /objects (будет на этапе 2)
require OKS_ROOT . '/app/controllers/Profile.php';   // /profile (смена пароля, 2FA)

foreach (glob(OKS_ROOT . '/app/modules/*.php') as $f) {
    require_once $f;
}

(call_user_func($router->resolve($_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET')))->handle();
