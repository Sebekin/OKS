<?php
declare(strict_types=1);

/**
 * CSRF-токены во всех формах (п.5.5 ТЗ).
 * Токен хранится в сессии, сверяется по всем POST-запросам.
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** Скрытое поле для форм. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(self::token()) . '">';
    }

    /** Проверка: POST обязан содержать корректный токен. */
    public static function validate(?string $sent): bool
    {
        return !empty($sent) && !empty($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $sent);
    }

    /** Жёсткая проверка для обработчиков форм (403 при несовпадении). */
    public static function checkRequest(): void
    {
        if (!self::validate($_POST['csrf'] ?? null)) {
            http_response_code(403);
            exit('Ошибка проверки безопасности (CSRF). Обновите страницу и повторите действие.');
        }
    }
}
