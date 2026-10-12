<?php
declare(strict_types=1);

/**
 * Веб-установщик «Учёт ОКС».
 * Размещается в public/install/index.php, запускается по адресу https://домен/oks/install/.
 * После успешной установки папку install/ следует удалить (кнопка на финальном экране
 * пишет instructions; автоудаление невозможно из-за прав shared-хостинга).
 */

define('OKS_ROOT', dirname(__DIR__, 2));

require OKS_ROOT . '/app/core/helpers.php';
require OKS_ROOT . '/app/core/Config.php';
require OKS_ROOT . '/app/core/DB.php';
require OKS_ROOT . '/app/core/Audit.php';
require OKS_ROOT . '/app/core/Auth.php';
require OKS_ROOT . '/app/core/Totp.php';
require OKS_ROOT . '/app/core/Csrf.php';
require OKS_ROOT . '/app/core/Rights.php';
require OKS_ROOT . '/app/core/Schema.php';
require OKS_ROOT . '/app/core/Seeder.php';

session_name('OKS_INSTALL');
session_start();

$errors = [];
$done   = false;

// Защита: если приложение уже установлено — не пускать.
$configPath = OKS_ROOT . '/app/config.php';
$configReal = is_file($configPath) && !str_contains((string)file_get_contents($configPath), 'INSTALL_PLACEHOLDER')
    && str_contains((string)@file_get_contents($configPath), "'installed' => true");

if ($configReal) {
    http_response_code(409);
    echo '<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif;padding:40px">'
       . 'Приложение уже установлено. Если нужна переустановка — удалите файл <b>app/config.php</b> на сервере и обновите страницу.'
       . '</p>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbDriver = $_POST['db_driver'] ?? 'mysql';
    $dbName   = trim($_POST['db_name'] ?? '');
    $dbHost   = trim($_POST['db_host'] ?? 'localhost');
    $dbPort   = (int)($_POST['db_port'] ?? 3306);
    $dbUser   = trim($_POST['db_user'] ?? '');
    $dbPass   = (string)($_POST['db_pass'] ?? '');
    $admLogin = trim($_POST['admin_login'] ?? '');
    $admPass  = (string)($_POST['admin_pass'] ?? '');
    $admPass2 = (string)($_POST['admin_pass2'] ?? '');
    $admName  = trim($_POST['admin_name'] ?? '');
    $admEmail = trim($_POST['admin_email'] ?? '');
    $withDemo = !empty($_POST['with_demo']);

    if ($admLogin === '' || !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $admLogin))
        $errors[] = 'Логин администратора: от 3 до 50 символов, латиница, цифры, точка, дефис, подчёркивание.';
    if (strlen($admPass) < 8) $errors[] = 'Пароль администратора — не короче 8 символов.';
    if ($admPass !== $admPass2) $errors[] = 'Пароли не совпадают.';
    if ($admName === '') $errors[] = 'Укажите ФИО администратора.';
    if ($dbDriver === 'mysql' && $dbName === '') $errors[] = 'Укажите имя базы данных MySQL.';

    if (!$errors) {
        try {
            // 1. Временный config.php (installed=false + маркер, чтобы не блокировать повторные попытки).
            $cfg = "<?php\nreturn [\n    'db' => [\n        'driver' => " . var_export($dbDriver, true)
                . ",\n        'host' => " . var_export($dbHost, true)
                . ",\n        'port' => " . var_export($dbPort, true)
                . ",\n        'name' => " . var_export($dbName, true)
                . ",\n        'user' => " . var_export($dbUser, true)
                . ",\n        'pass' => " . var_export($dbPass, true)
                . ",\n        'charset' => 'utf8mb4',\n        'sqlite_path' => __DIR__ . '/../data/dev.sqlite',\n    ],\n    'app' => [\n        'name' => 'Учёт ОКС — отдел строительного контроля',\n        'timezone' => 'Europe/Moscow',\n        'installed' => false, // INSTALL_PLACEHOLDER\n    ],\n];\n";
            if (file_put_contents($configPath, $cfg) === false) {
                throw new RuntimeException('Не удалось записать app/config.php — проверьте права на запись папки app/.');
            }
            @chmod($configPath, 0640);

            Config::load($configPath);
            date_default_timezone_set('Europe/Moscow');

            // 2. Соединение и схема.
            if ($dbDriver === 'sqlite') {
                Schema::createSqlite(DB::pdo());
            } else {
                $test = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $safe = str_replace(['`', '\\'], '', $dbName);
                $test->exec("CREATE DATABASE IF NOT EXISTS `{$safe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                Schema::createMySql(DB::pdo());
            }

            // 3. Наполнение.
            Seeder::seed(
                ['login' => $admLogin, 'pass' => $admPass, 'name' => $admName, 'email' => $admEmail],
                $withDemo
            );

            // 4. Готово — помечаем installed=true.
            file_put_contents($configPath, str_replace(
                "'installed' => false, // INSTALL_PLACEHOLDER", "'installed' => true,", $cfg));

            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'Ошибка установки: ' . $e->getMessage();
            if (is_file($configPath)) @unlink($configPath); // чтобы повторная попытка не блокировалась
        }
    }
}

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$v = fn(string $k, string $d = '') => esc($_POST[$k] ?? $d);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Установка — Учёт ОКС</title>
<style>
body{background:#f4f6f8;display:flex;justify-content:center;padding:40px 16px;font-family:system-ui,'Segoe UI',Roboto,sans-serif;color:#263238}
.inst-card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08);max-width:640px;width:100%;padding:32px}
h1{font-size:22px;margin:0 0 6px}
p.sub{color:#666;margin:0 0 24px;font-size:14px}
fieldset{border:1px solid #dde3ea;border-radius:8px;padding:16px;margin:0 0 18px}
legend{font-weight:600;font-size:14px;padding:0 8px}
label{display:block;font-size:13px;margin:10px 0 4px;color:#37474f}
input[type=text],input[type=password],input[type=number],input[type=email],select{
 width:100%;padding:8px 10px;border:1px solid #cfd8dc;border-radius:6px;font-size:14px;box-sizing:border-box}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.err{background:#fdecea;border:1px solid #f5c6cb;color:#b71c1c;border-radius:6px;padding:10px 14px;font-size:13px;margin-bottom:16px}
.err ul{margin:0;padding-left:18px}
.ok{background:#e8f5e9;border:1px solid #c8e6c9;color:#1b5e20;border-radius:6px;padding:14px 16px;font-size:14px;line-height:1.6}
button{background:#1565c0;color:#fff;border:0;border-radius:6px;padding:12px 24px;font-size:15px;cursor:pointer}
button:hover{background:#0d47a1}
.hint{font-size:12px;color:#78909c;margin-top:4px}
code{background:#eceff1;padding:2px 6px;border-radius:4px;font-size:13px}
a{color:#1565c0}
</style>
</head>
<body>
<div class="inst-card">
<?php if ($done): ?>
    <h1>✅ Установка завершена</h1>
    <div class="ok">
        Ба данных создана, справочники и <?= !empty($_POST['with_demo']) ? 'демо-данные загружены' : 'начальные данные инициализированы' ?>.<br><br>
        <b>Важно:</b> удалите папку <code>install/</code> с сервера — она больше не нужна,
        а пока существует, любой сможет сбросить базу (мы это ограничиваем проверкой config.php, но лучше убрать совсем).<br><br>
        Логин администратора: <b><?= esc($_POST['admin_login'] ?? '') ?></b> ·
        <a href="../index.php">перейти к странице входа →</a>
    </div>
<?php else: ?>
    <h1>Установка «Учёт ОКС»</h1>
    <p class="sub">PHP <?= PHP_VERSION ?> · шаг 1 из 1: данные базы MySQL и учётная запись администратора.</p>

    <?php if ($errors): ?>
        <div class="err"><ul><?php foreach ($errors as $e) echo '<li>' . esc($e) . '</li>'; ?></ul></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <fieldset>
            <legend>База данных</legend>
            <label>Драйвер
                <select name="db_driver">
                    <option value="mysql" <?= ($v('db_driver','mysql')==='mysql')?'selected':'' ?>>MySQL (боевой хостинг)</option>
                    <option value="sqlite" <?= ($v('db_driver')==='sqlite')?'selected':'' ?>>SQLite (только для тестов)</option>
                </select>
            </label>
            <div class="row2">
                <div><label>Хост</label><input type="text" name="db_host" value="<?= $v('db_host','localhost') ?>"></div>
                <div><label>Порт</label><input type="number" name="db_port" value="<?= $v('db_port','3306') ?>"></div>
            </div>
            <label>Имя базы данных</label><input type="text" name="db_name" value="<?= $v('db_name') ?>">
            <div class="row2">
                <div><label>Пользователь БД</label><input type="text" name="db_user" value="<?= $v('db_user') ?>"></div>
                <div><label>Пароль БД</label><input type="password" name="db_pass"></div>
            </div>
            <div class="hint">Если база не существует и у пользователя есть привилегии, установщик создаст её автоматически.</div>
        </fieldset>

        <fieldset>
            <legend>Администратор</legend>
            <div class="row2">
                <div><label>Логин</label><input type="text" name="admin_login" value="<?= $v('admin_login') ?>"></div>
                <div><label>ФИО</label><input type="text" name="admin_name" value="<?= $v('admin_name') ?>"></div>
            </div>
            <div class="row2">
                <div><label>Пароль (мин. 8 симв.)</label><input type="password" name="admin_pass"></div>
                <div><label>Повторите пароль</label><input type="password" name="admin_pass2"></div>
            </div>
            <label>E-mail (необязательно)</label><input type="email" name="admin_email" value="<?= $v('admin_email') ?>">
        </fieldset>

        <fieldset>
            <legend>Данные</legend>
            <label style="display:flex;align-items:center;gap:8px;margin-top:2px">
                <input type="checkbox" name="with_demo" value="1" style="width:auto" <?= !empty($_POST['with_demo'])?'checked':'' ?>>
                Загрузить демо-данные (пользователи, очереди, 4 объекта, письма и счета)
            </label>
            <div class="hint">Для боевого запуска снимите галочку — система начнётся пустой.</div>
        </fieldset>

        <button type="submit">Установить</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
