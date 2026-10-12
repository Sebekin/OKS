<?php
declare(strict_types=1);

/**
 * Матрица прав (п.4.1–4.3 ТЗ).
 * Уровень доступа к действию: hidden < view < edit.
 * Приоритет: персональная настройка пользователя > роль > значение по умолчанию роли.
 */
class Rights
{
    /** Все действия в системе (для матрицы в админ-панели). */
    public const ACTIONS = [
        'objects.view'          => 'Раздел «Объекты»: просмотр',
        'objects.create'        => 'Добавление объектов',
        'objects.edit_fields'   => 'Редактирование полей карточки',
        'objects.change_status' => 'Изменение статуса',
        'objects.mark_pto'      => 'Отметка «ИД в ПТО»',
        'objects.mark_act'      => 'Отметка и дата «Акт о подключении»',
        'objects.reassign'      => 'Переназначение ответственных',
        'objects.add_sub'       => 'Добавление подобъектов',
        'objects.delete'        => 'Удаление объектов',
        'docs.view'             => 'Документы: просмотр/скачивание',
        'docs.manage'           => 'Документы: загрузка/удаление',
        'letters.view'          => 'Письма: просмотр',
        'letters.manage'        => 'Письма: добавление/удаление',
        'invoices.view'         => 'Счета: просмотр',
        'invoices.add'          => 'Счета: добавление',
        'invoices.pay'          => 'Счета: редактирование и отметка оплаты',
        'chat.use'              => 'Внутренний чат',
        'audit.view'            => 'Журнал аудита',
        'export'                => 'Экспорт/выгрузки',
        'admin.users'           => 'Управление пользователями',
        'admin.queues'          => 'Управление очередями и цветами',
        'admin.settings'        => 'Настройки системы',
    ];

    /** Роли (код => название). */
    public const ROLES = [
        'admin'     => 'Администратор',
        'engineer'  => 'Инженер',
        'account'   => 'Бухгалтерия',
        'pto'       => 'Сотрудник ПТО',
        'connect'   => 'Сотрудник подключения',
        'viewer'    => 'Наблюдатель',
    ];

    /** Права по умолчанию (п.4.1). '*' — все действия. */
    private const DEFAULTS = [
        'admin'   => ['*' => 'edit'],
        'engineer' => [
            'objects.view' => 'edit', 'objects.create' => 'edit', 'objects.edit_fields' => 'edit',
            'objects.change_status' => 'edit', 'objects.mark_act' => 'edit', 'objects.add_sub' => 'edit',
            'docs.view' => 'edit', 'docs.manage' => 'edit',
            'letters.view' => 'edit', 'letters.manage' => 'edit',
            'invoices.view' => 'view', 'invoices.pay' => 'edit',
            'chat.use' => 'edit', 'export' => 'view',
        ],
        'account' => [
            'objects.view' => 'view', 'docs.view' => 'view', 'letters.view' => 'view',
            'invoices.view' => 'edit', 'invoices.add' => 'edit', 'invoices.pay' => 'edit',
            'chat.use' => 'edit', 'export' => 'view',
        ],
        'pto' => [
            'objects.view' => 'view', 'objects.mark_pto' => 'edit', 'docs.view' => 'view',
            'letters.view' => 'view', 'invoices.view' => 'view', 'chat.use' => 'edit', 'export' => 'view',
        ],
        'connect' => [
            'objects.view' => 'view', 'objects.mark_act' => 'edit', 'docs.view' => 'view',
            'letters.view' => 'view', 'invoices.view' => 'view', 'chat.use' => 'edit', 'export' => 'view',
        ],
        'viewer' => [
            'objects.view' => 'view', 'docs.view' => 'view', 'letters.view' => 'view',
            'invoices.view' => 'view', 'chat.use' => 'edit', 'export' => 'view',
        ],
    ];

    /** Инженер видит и редактирует только свои объекты (п.4.1). */
    public static function onlyOwnObjects(string $role): bool
    {
        return $role === 'engineer';
    }

    /**
     * Уровень доступа текущего пользователя к действию.
     * Учитывает точечную настройку rights_user (user_id + action), затем роль.
     */
    public static function level(string $action): string
    {
        $u = Auth::user();
        if (!$u) return 'hidden';
        static $cache = [];
        $key = $u['id'] . '|' . $action;
        if (isset($cache[$key])) return $cache[$key];

        $level = self::DEFAULTS[$u['role']][$action]
              ?? self::DEFAULTS[$u['role']]['*'] ?? null
              ?? 'hidden';
        if ($u['role'] === 'admin') { $level = 'edit'; }

        try {
            $row = DB::queryOne(
                'SELECT level FROM rights_user WHERE user_id = :uid AND action = :a',
                ['uid' => $u['id'], 'a' => $action]
            );
            if ($row) $level = $row['level'];
            $rrow = DB::queryOne(
                'SELECT level FROM rights_role WHERE role = :r AND action = :a',
                ['r' => $u['role'], 'a' => $action]
            );
            if ($rrow && !$row) $level = $rrow['level'];
        } catch (Throwable $e) { /* таблицы прав появятся на этапе установки */ }

        return $cache[$key] = $level;
    }

    public static function can(string $action): bool
    {
        return self::level($action) === 'edit';
    }

    public static function visible(string $action): bool
    {
        return self::level($action) !== 'hidden';
    }

    /** Запрет → 403. */
    public static function require(string $action): void
    {
        if (!self::can($action)) {
            http_response_code(403);
            exit('<h1>Доступ запрещён</h1><p>Недостаточно прав для действия «' . e(self::ACTIONS[$action] ?? $action) . '». <a href="/">Вернуться</a></p>');
        }
    }
}
