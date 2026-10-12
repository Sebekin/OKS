<?php
declare(strict_types=1);

/**
 * Аутентификация (раздел 5 ТЗ): вход по логину/паролю, TOTP 2FA, резервные коды,
 * защита от подбора (5.3), таймаут бездействия (5.4), ограничение по IP (5.6).
 */
class Auth
{
    private const MAX_ATTEMPTS = 5;      // попыток до блокировки
    private const LOCK_MINUTES = 15;     // минут блокировки

    /** Данные вошедшего пользователя или null. */
    public static function user(): ?array
    {
        if (!empty($_SESSION['uid'])) {
            return DB::queryOne('SELECT * FROM users WHERE id = :id AND is_active = 1', ['id' => $_SESSION['uid']]);
        }
        return null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
    }

    /** Пустое место входа — редирект на /login (п.5.5: ни одна страница без входа не отдаётся). */
    public static function requireLogin(): void
    {
        self::touchSession();
        if (self::user() === null) {
            redirect('/login');
        }
    }

    /** Автовыход после N минут бездействия (настройка session_timeout_min, по умолчанию 60 — п.5.4). */
    private static function touchSession(): void
    {
        $limit = (int)(Settings_value('session_timeout_min', '60')) * 60;
        if ($limit > 0 && !empty($_SESSION['last_activity'])
            && time() - (int)$_SESSION['last_activity'] > $limit) {
            self::logout();
            redirect('/login?timeout=1');
        }
        $_SESSION['last_activity'] = time();
    }

    /** Ограничение доступа по списку IP (п.5.6), выключено по умолчанию. */
    public static function checkIpRestriction(bool $adminArea = false): void
    {
        $scope = Settings_value('ip_restrict_mode', 'off'); // off | all | admin
        if ($scope === 'off') return;
        if ($adminArea && $scope !== 'admin') return;
        if (!$adminArea && $scope === 'admin') return;
        $list = array_filter(array_map('trim', explode(',', (string)Settings_value('ip_allow_list', ''))));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($list && !in_array($ip, $list, true)) {
            try { Audit::log('login_fail', 'Отказ по списку IP (' . $ip . ')'); } catch (Throwable $e) {}
            http_response_code(403);
            exit('Доступ к системе ограничен по IP-адресу.');
        }
    }

    /** Есть ли блокировка по логину/IP? Возвращает оставшиеся минуты или 0. */
    public static function lockMinutes(string $login): int
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $row = DB::queryOne(
            'SELECT COUNT(*) c FROM login_log
             WHERE (login = :l OR ip = :ip) AND success = 0
               AND created_at > datetime(:now, \'-' . self::LOCK_MINUTES . ' minutes\')',
            ['l' => $login, 'ip' => $ip, 'now' => date('Y-m-d H:i:s')]
        );
        return (($row['c'] ?? 0) >= self::MAX_ATTEMPTS) ? self::LOCK_MINUTES : 0;
    }

    /**
     * Проверка логина и пароля БЕЗ создания сессии (шаг 1 формы входа).
     * @return array ['ok'=>bool, 'error'=>string, 'needs_2fa'=>bool, 'user_id'=>int]
     */
    public static function attempt(string $login, string $password): array
    {
        $lock = self::lockMinutes($login);
        if ($lock > 0) {
            self::logAttempt($login, false, 'Блокировка: превышено число попыток');
            return ['ok' => false, 'error' => "Слишком много неудачных попыток. Вход заблокирован на {$lock} минут.", 'needs_2fa' => false, 'user_id' => 0];
        }

        $u = DB::queryOne('SELECT * FROM users WHERE login = :l', ['l' => $login]);
        $hashOk = $u && password_verify($password, $u['pass_hash']);
        if (!$u || !$hashOk) {
            self::logAttempt($login, false, $u ? 'Неверный пароль' : 'Нет такого логина');
            return ['ok' => false, 'error' => 'Неверный логин или пароль.', 'needs_2fa' => false, 'user_id' => (int)($u['id'] ?? 0)];
        }
        if ((int)$u['is_active'] !== 1) {
            self::logAttempt($login, false, 'Учётная запись заблокирована');
            return ['ok' => false, 'error' => 'Учётная запись заблокирована. Обратитесь к администратору.', 'needs_2fa' => false, 'user_id' => 0];
        }

        // Пароль верный. Если включена 2FA — шаг проверки кода.
        if ((int)$u['twofa_enabled'] === 1) {
            self::logAttempt($login, false, null); // промежуточный: пароль принят, ждём код
            return ['ok' => true, 'error' => '', 'needs_2fa' => true, 'user_id' => (int)$u['id']];
        }

        self::loginComplete((int)$u['id'], $login);
        return ['ok' => true, 'error' => '', 'needs_2fa' => false, 'user_id' => (int)$u['id']];
    }

    /** Шаг 2: проверка TOTP-кода (или резервного). */
    public static function verifyCode(int $userId, string $code): bool
    {
        $u = DB::queryOne('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        if (!$u || !$u['twofa_secret']) return false;
        $code = preg_replace('/\s+/', '', $code);

        if (Totp::verify((string)$u['twofa_secret'], $code)) {
            self::loginComplete($userId, (string)$u['login']);
            return true;
        }
        // Одноразовый резервный код (п.5.2)
        $rc = DB::queryOne('SELECT id, used FROM backup_codes WHERE user_id = :u AND code = :c', ['u' => $userId, 'c' => hash('sha256', $code)]);
        if ($rc && (int)$rc['used'] === 0) {
            DB::update('backup_codes', ['used' => 1], 'id = :id', ['id' => $rc['id']]);
            self::loginComplete($userId, (string)$u['login']);
            return true;
        }
        self::logAttempt((string)$u['login'], false, 'Ошибка 2FA');
        return false;
    }

    /** Завершение входа: создание сессии, запись в журнал входов. */
    private static function loginComplete(int $userId, string $login): void
    {
        session_regenerate_id(true); // защита от фиксации сессии
        $_SESSION['uid'] = $userId;
        $_SESSION['last_activity'] = time();
        unset($_SESSION['csrf']);
        self::logAttempt($login, true, null);
        Audit::log('login_ok', 'Вход выполнен', (string)$userId);
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['uid'])) {
            Audit::log('logout', 'Выход из системы', (string)$_SESSION['uid']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Запись в журнал входов (п.5.7): дата, пользователь, IP, результат, браузер. */
    private static function logAttempt(string $login, bool $success, ?string $note): void
    {
        try {
            DB::insert('login_log', [
                'created_at' => date('Y-m-d H:i:s'),
                'login'      => mb_substr($login, 0, 100),
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'success'    => $success ? 1 : 0,
                'note'       => $note,
            ]);
        } catch (Throwable $e) { /* журнал входов появится при установке */ }
    }

    /** Смена пароля текущего пользователя (с проверкой старого). */
    public static function changePassword(int $userId, string $oldPass, string $newPass): ?string
    {
        $minLen = (int)Settings_value('min_password_len', '8');
        if (mb_strlen($newPass) < $minLen) return "Минимальная длина пароля — {$minLen} символов.";
        $u = DB::queryOne('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        if (!$u || !password_verify($oldPass, $u['pass_hash'])) return 'Текущий пароль указан неверно.';
        DB::update('users', ['pass_hash' => self::hash($newPass)], 'id = :id', ['id' => $userId]);
        Audit::log('admin', 'Смена пароля', (string)$userId);
        return null;
    }

    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT); // bcrypt/argon2id — что поддержан хостинг
    }
}
