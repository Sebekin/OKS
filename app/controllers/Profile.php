<?php
declare(strict_types=1);
require_once OKS_ROOT . '/app/controllers/Base.php';
require_once OKS_ROOT . '/app/lib/QrEncoder.php';

/**
 * Профиль: смена пароля, подключение/отключение 2FA (QR + резервные коды).
 */
class ProfilePage extends BaseController
{
    public function handle(): void
    {
        $this->guard();
        $u = Auth::user();
        $msg = ''; $err = '';

        if ($this->isPost()) {
            Csrf::checkRequest();
            $act = $_POST['action'] ?? '';

            if ($act === 'password') {
                $res = Auth::changePassword((int)$u['id'], (string)($_POST['old_pass'] ?? ''), (string)($_POST['new_pass'] ?? ''));
                $err = $res ?? '';
                $msg = $res === null ? 'Пароль изменён.' : '';
            }

            if ($act === '2fa_start') {
                // Генерируем секрет и показываем QR + поле для проверочного кода
                $secret = Totp::generateSecret();
                $_SESSION['2fa_setup_secret'] = $secret;
                View::render('profile_2fa_setup', [
                    'pageTitle' => 'Профиль — подключение 2FA',
                    'secret'    => $secret,
                    'qrSvg'     => Totp::qrSvg(Totp::provisioningUri($secret, (string)$u['login'], Config::get('app.name', 'Учёт ОКС'))),
                ]);
                return;
            }

            if ($act === '2fa_confirm') {
                $secret = (string)($_SESSION['2fa_setup_secret'] ?? '');
                $code = preg_replace('/\s+/', '', (string)($_POST['code'] ?? ''));
                if ($secret && Totp::verify($secret, $code)) {
                    unset($_SESSION['2fa_setup_secret']);
                    DB::update('users', ['twofa_secret' => $secret, 'twofa_enabled' => 1], 'id = :id', ['id' => $u['id']]);
                    // 10 одноразовых резервных кодов (п.5.2)
                    $plain = [];
                    for ($i = 0; $i < 10; $i++) {
                        $c = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
                        $plain[] = $c;
                        DB::insert('backup_codes', ['user_id' => (int)$u['id'], 'code' => hash('sha256', $c), 'used' => 0]);
                    }
                    Audit::log('admin', 'Подключена 2FA', (string)$u['id']);
                    View::render('profile_2fa_done', ['pageTitle' => 'Профиль — 2FA', 'codes' => $plain]);
                    return;
                }
                $err = 'Код не подтверждён. Отсканируйте QR ещё раз и введите актуальный код из приложения.';
            }

            if ($act === '2fa_cancel') {
                unset($_SESSION['2fa_setup_secret']);
                redirect('/profile');
            }

            if ($act === '2fa_off' && Rights::can('admin.users')) {
                // Отключить 2FA может только администратор (у себя — по согласованию с другим админом не предусмотрено ТЗ)
                DB::update('users', ['twofa_enabled' => 0, 'twofa_secret' => null], 'id = :id', ['id' => $u['id']]);
                DB::run('DELETE FROM backup_codes WHERE user_id = :u', ['u' => $u['id']]);
                Audit::log('admin', 'Отключена 2FA', (string)$u['id']);
                $msg = '2FA отключена.';
            }
        }

        $remainingCodes = [];
        try {
            $remainingCodes = DB::queryAll('SELECT id FROM backup_codes WHERE user_id = :u AND used = 0', ['u' => $u['id']]);
        } catch (Throwable $e) {}

        View::render('profile', [
            'pageTitle' => 'Профиль',
            'me'        => $u,
            'msg'       => $msg,
            'err'       => $err,
            'codesLeft' => count($remainingCodes),
        ]);
    }
}

$router->add('GET',  '/profile', 'ProfilePage');
$router->add('POST', '/profile', 'ProfilePage');
