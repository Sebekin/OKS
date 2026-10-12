<?php
declare(strict_types=1);
require_once OKS_ROOT . '/app/controllers/Base.php';

/**
 * Экран входа: логин/пароль (шаг 1), код 2FA (шаг 2), выход.
 * Маршруты регистрируются в глобальном $router (см. public/index.php).
 */
class LoginPage extends BaseController
{
    public function handle(): void
    {
        global $router;
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $path = ($base !== '' && $base !== '/' && str_starts_with($path, $base)) ? substr($path, strlen($base)) : $path;
        $path = rtrim($path, '/') ?: '/login';

        // /logout — завершение сессии из любого состояния
        if (str_ends_with($path, '/logout')) {
            Auth::logout();
            redirect('/login');
        }

        // Уже вошёл — на главную
        if (Auth::user() !== null) redirect('/');

        $error = '';

        if ($this->isPost()) {
            Csrf::checkRequest();

            // Шаг 2: проверка кода 2FA
            if (!empty($_POST['pending_id'])) {
                $pid = (int)$_POST['pending_id'];
                $code = trim((string)($_POST['code'] ?? ''));
                if (Auth::verifyCode($pid, $code)) {
                    redirect('/');
                }
                $error = 'Неверный код подтверждения. Попробуйте ещё раз или используйте резервный код.';
                View::render('login_2fa', ['error' => $error, 'pendingId' => $pid], null);
                return;
            }

            // Шаг 1: логин + пароль
            $login = trim((string)($_POST['login'] ?? ''));
            $pass  = (string)($_POST['password'] ?? '');
            $res = Auth::attempt($login, $pass);
            if (!$res['ok']) {
                $error = $res['error'];
            } elseif ($res['needs_2fa']) {
                $_SESSION['pending_uid'] = $res['user_id'];
                View::render('login_2fa', ['error' => '', 'pendingId' => $res['user_id']], null);
                return;
            } else {
                redirect('/');
            }
        }

        View::render('login', [
            'error'   => $error,
            'timeout' => !empty($_GET['timeout']),
        ], null);
    }
}

/* Регистрация маршрутов (файл подключается до resolve()). */
$router->add('GET',  '/login',  'LoginPage');
$router->add('POST', '/login',  'LoginPage');
$router->add('GET',  '/logout', 'LoginPage');
