<?php
declare(strict_types=1);

/**
 * Файловое хранилище (раздел 8 ТЗ).
 * Файлы лежат ВНЕ public/ в data/uploads — доступ только через /file?i=ID,
 * при каждой отдаче проверяется авторизация и право видеть объект-владелец.
 */
class Files
{
    /** Разрешённые расширения (совпадает со store_upload). */
    private const ALLOWED = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];

    /** MIME-типы для отдачи браузеру. */
    private const MIMES = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];

    /**
     * Привязать загруженный файл к сущности.
     * @param string $entity document|letter|invoice
     */
    public static function attach(string $entity, int $entityId, array $stored, string $mime = ''): int
    {
        $col = match ($entity) {
            'document' => 'document_id',
            'letter'   => 'letter_id',
            'invoice'  => 'invoice_id',
            default    => throw new InvalidArgumentException('Неизвестный тип привязки'),
        };
        return DB::insert('document_files', [
            $col            => $entityId,
            'stored_name'   => $stored['path'],
            'orig_name'     => $stored['original_name'],
            'size_bytes'    => (int)$stored['size'],
            'mime'          => $mime ?: (self::MIMES[strtolower(pathinfo($stored['path'], PATHINFO_EXTENSION))] ?? 'application/octet-stream'),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /** Записи document_files по объекту, сгруппированные по сущностям. */
    public static function forObject(int $objectId): array
    {
        $rows = DB::queryAll(
            "SELECT f.* FROM document_files f
             LEFT JOIN documents d ON d.id = f.document_id AND d.object_id = :o1
             LEFT JOIN letters l   ON l.id = f.letter_id   AND l.object_id = :o2
             LEFT JOIN invoices v  ON v.id = f.invoice_id  AND v.object_id = :o3
             WHERE d.id IS NOT NULL OR l.id IS NOT NULL OR v.id IS NOT NULL
             ORDER BY f.id",
            ['o1' => $objectId, 'o2' => $objectId, 'o3' => $objectId]);
        $byDoc = $byLetter = $byInvoice = [];
        foreach ($rows as $r) {
            if ($r['document_id']) $byDoc[(int)$r['document_id']][] = $r;
            elseif ($r['letter_id']) $byLetter[(int)$r['letter_id']][] = $r;
            elseif ($r['invoice_id']) $byInvoice[(int)$r['invoice_id']][] = $r;
        }
        return ['document' => $byDoc, 'letter' => $byLetter, 'invoice' => $byInvoice];
    }

    /** Удалить файл записью + с диска. Право: edit_fields владельца. */
    public static function delete(int $fileId): void
    {
        $f = DB::queryOne('SELECT * FROM document_files WHERE id = :i', ['i' => $fileId]);
        if (!$f) abort(404, 'Файл не найден');
        self::requireAccessToFile($f);

        $path = self::realPath((string)$f['stored_name']);
        if ($path && is_file($path)) unlink($path);
        DB::run('DELETE FROM document_files WHERE id = :i', ['i' => $fileId]);

        $ownerId = self::ownerObjectId($f);
        Audit::log('data', 'Удаление файла', (string)($ownerId ?? 0), null,
            (string)$f['orig_name'], 'файл удалён из карточки');
    }

    /** Отдать файл клиенту (проверка прав обязательна). */
    public static function serve(int $fileId): never
    {
        $f = DB::queryOne('SELECT * FROM document_files WHERE id = :i', ['i' => $fileId]);
        if (!$f) abort(404, 'Файл не найден');
        self::requireAccessToFile($f);

        $path = self::realPath((string)$f['stored_name']);
        if (!$path || !is_file($path)) abort(404, 'Файл отсутствует на диске');

        $inline = in_array(strtolower(pathinfo($f['stored_name'], PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png'], true);
        header('Content-Type: ' . ($f['mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header("Content-Disposition: " . ($inline ? 'inline' : 'attachment') . '; filename="' .
               preg_replace('/[^\x20-\x7E]/', '_', (string)$f['orig_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /* ---------------- внутреннее ---------------- */

    /** Реальный путь к файлу с защитой от выхода за пределы хранилища. */
    private static function realPath(string $storedRel): ?string
    {
        $full = realpath(OKS_ROOT . '/data/uploads/' . $storedRel);
        $base = realpath(OKS_ROOT . '/data/uploads');
        if (!$full || !$base || !str_starts_with($full, $base)) return null;
        return $full;
    }

    private static function ownerObjectId(array $f): ?int
    {
        foreach ([['documents', 'document_id'], ['letters', 'letter_id'], ['invoices', 'invoice_id']] as [$table, $col]) {
            if (!empty($f[$col])) {
                $row = DB::queryOne("SELECT object_id FROM {$table} WHERE id = :i", ['i' => (int)$f[$col]]);
                return $row ? (int)$row['object_id'] : null;
            }
        }
        return null;
    }

    /** Проверка: пользователь залогинен и видит объект-владельца файла. */
    private static function requireAccessToFile(array $f): void
    {
        Auth::requireLogin();
        $objId = self::ownerObjectId($f);
        if (!$objId) abort(404, 'Файл не привязан к объекту');
        $obj = ObjectModel::find($objId);
        if (!$obj || !ObjectModel::canAccess($obj)) abort(403, 'Нет доступа к этому файлу');
    }
}
