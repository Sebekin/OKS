<?php
declare(strict_types=1);

/** Базовый класс контроллера: доступ только после входа (п.5.5). */
abstract class BaseController
{
    abstract public function handle(): void;

    /** Вызывать в начале handle() для защищённых страниц. */
    protected function guard(bool $adminArea = false): void
    {
        Auth::checkIpRestriction($adminArea);
        Auth::requireLogin();
    }

    /** POST обязан быть с корректным CSRF-токеном. */
    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}
