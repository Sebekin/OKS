<?php
declare(strict_types=1);

/** Журнал аудита (п.5.7 ТЗ). */
class Audit
{
    /** Типы событий: login_ok, login_fail, logout, data, file, admin, chat_block */
    public static function log(string $type, string $action, string $entity = '', ?string $field = null,
                               ?string $oldValue = null, ?string $newValue = null, ?int $userId = null): void
    {
        DB::insert('audit_log', [
            'created_at' => date('Y-m-d H:i:s'),
            'user_id'    => $userId ?? Auth::id(),
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'type'       => $type,
            'action'     => $action,
            'entity'     => $entity,
            'field'      => $field,
            'old_value'  => $oldValue,
            'new_value'  => $newValue,
        ]);
    }

    /** Запись изменения поля объекта/подобъекта в историю карточки + общий журнал. */
    public static function fieldChange(int $objectId, string $entityLabel, string $field, ?string $old, ?string $new): void
    {
        self::log('data', "Изменение: {$entityLabel}", (string)$objectId, $field, $old, $new);
    }

    public static function historyFor(int $objectId): array
    {
        return DB::queryAll(
            'SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
             WHERE a.entity = :oid AND a.type IN (:t1, :t2) ORDER BY a.id DESC LIMIT 300',
            ['oid' => (string)$objectId, 't1' => 'data', 't2' => 'file']
        );
    }
}
