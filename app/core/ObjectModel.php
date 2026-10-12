<?php
declare(strict_types=1);

/**
 * Модель объекта: номера по году, права доступа к конкретному объекту,
 * выборка таблицы объектов с фильтрами (раздел 6 ТЗ).
 */
class ObjectModel
{
    /** Статусы ОКС (п.6.3 ТЗ): код => [название, цвет бейджа]. */
    public const STATUSES = [
        'none'   => ['Не назначен', '#9e9e9e'],
        'work'   => ['В работе',    '#fb8c00'],
        'done'   => ['Выполнен',    '#43a047'],
        'paused' => ['Приостановлен', '#1e88e5'],
        'cancel' => ['Отменён',     '#e53935'],
    ];

    /** Следующий порядковый номер объекта за год (с блокировкой через object_seq). */
    public static function nextNumber(int $year): int
    {
        $row = DB::queryOne('SELECT last_num FROM object_seq WHERE year = :y', ['y' => $year]);
        if ($row === null) {
            DB::run('INSERT INTO object_seq (year, last_num) VALUES (:y, 1)', ['y' => $year]);
            return 1;
        }
        $n = (int)$row['last_num'] + 1;
        DB::run('UPDATE object_seq SET last_num = :n WHERE year = :y', ['n' => $n, 'y' => $year]);
        return $n;
    }

    /** Объект доступен пользователю? Инженер — только свои (п.4.1). */
    public static function canAccess(array $obj, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();
        if ($user === null) return false;
        if (!Rights::visible('objects.view')) return false;
        if (!Rights::onlyOwnObjects($user['role'])) return true;
        // Инженер: свои объекты + подобъекты своих родителей
        if ((int)($obj['engineer_id'] ?? 0) === (int)$user['id']) return true;
        if (!empty($obj['parent_id'])) {
            $p = DB::queryOne('SELECT engineer_id FROM objects WHERE id = :i', ['i' => (int)$obj['parent_id']]);
            if ($p !== null && (int)$p['engineer_id'] === (int)$user['id']) return true;
        }
        return false;
    }

    /** Загрузка объекта по id (включая удалённые из архива — решение принимает вызывающий). */
    public static function find(int $id): ?array
    {
        return DB::queryOne('SELECT * FROM objects WHERE id = :i', ['i' => $id]);
    }

    /**
     * Таблица объектов с фильтрами и сортировкой (п.6.1–6.2 ТЗ).
     * Возвращает строки с присоединёнными очередями/инженерами; подобъекты идут сразу за родителем.
     */
    public static function listForCurrentUser(array $filters): array
    {
        $u = Auth::user();
        $where = ['o.is_archived = 0'];
        $params = [];

        if (Rights::onlyOwnObjects($u['role'])) {
            $where[] = '(o.engineer_id = :me OR p.engineer_id = :me)';
            $params['me'] = (int)$u['id'];
        }

        if (!empty($filters['q'])) {
            $q = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['q']) . '%';
            $where[] = "(o.address LIKE :q1 OR o.customer LIKE :q2 OR o.contract_number LIKE :q3
                         OR o.letter_number LIKE :q4 OR CAST(o.number_in_year AS CHAR) LIKE :q5)";
            $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = $params['q5'] = $q;
        }
        if (!empty($filters['status']) && isset(self::STATUSES[$filters['status']])) {
            $where[] = 'o.status = :st';
            $params['st'] = $filters['status'];
        }
        if (!empty($filters['queue'])) {
            $where[] = 'COALESCE(o.queue_id, p.queue_id) = :qu';
            $params['qu'] = (int)$filters['queue'];
        }
        if (!empty($filters['engineer'])) {
            $where[] = 'COALESCE(o.engineer_id, p.engineer_id) = :en';
            $params['en'] = (int)$filters['engineer'];
        }
        if (!empty($filters['year'])) {
            $where[] = 'o.year = :yr';
            $params['yr'] = (int)$filters['year'];
        }
        if (!empty($filters['pto'])) { // только с отметкой ИД в ПТО
            $where[] = 'o.pto_mark = 1';
        }
        if (!empty($filters['act'])) { // только с актом о подключении
            $where[] = 'o.act_mark = 1';
        }

        $sortMap = [
            'num'    => 'o.year DESC, o.number_in_year ASC',
            'cust'   => 'o.customer ASC',
            'addr'   => 'o.address ASC',
            'status' => "CASE o.status WHEN 'work' THEN 0 WHEN 'paused' THEN 1 WHEN 'none' THEN 2 WHEN 'done' THEN 3 ELSE 4 END",
            'cost'   => 'o.cost DESC',
            'eng'    => 'COALESCE(e.full_name, pe.full_name) ASC',
        ];
        $sortDir = ($filters['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $orderBy = ($sortMap[$filters['sort'] ?? 'num'] ?? $sortMap['num']) . ' ' . $sortDir;
        // Подобъекты всегда группой после родителя
        $orderBy = "COALESCE(o.parent_id, o.id) {$sortDir}, o.sub_no IS NOT NULL, o.sub_no ASC, " . $orderBy;

        $sql = "SELECT o.*, q.name AS queue_name, q.color AS queue_color, e.full_name AS engineer_name,
                       p.customer AS parent_customer, p.queue_id AS parent_queue_id,
                       p.number_in_year AS parent_number, pq.color AS parent_queue_color
                FROM objects o
                LEFT JOIN objects p ON p.id = o.parent_id
                LEFT JOIN queues q ON q.id = o.queue_id
                LEFT JOIN queues pq ON pq.id = p.queue_id
                LEFT JOIN users e ON e.id = o.engineer_id
                LEFT JOIN users pe ON pe.id = p.engineer_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$orderBy}";
        return DB::queryAll($sql, $params);
    }

    /** Список лет, доступных в данных (для фильтра). */
    public static function years(): array
    {
        return array_column(DB::queryAll('SELECT DISTINCT year FROM objects ORDER BY year DESC'), 'year');
    }

    /** Человекочитаемое представление значения поля для журнала изменений. */
    public static function displayValue(string $field, $val): string
    {
        if ($val === null || $val === '') return '—';
        if ($field === 'status') return self::STATUSES[$val][0] ?? (string)$val;
        if ($field === 'cost') return number_format((float)$val, 2, '.', ' ') . ' ₽';
        if (str_ends_with($field, '_date')) return fmt_date((string)$val);
        if (in_array($field, ['pto_mark', 'act_mark'], true)) return ((int)$val) > 0 ? 'Да' : 'Нет';
        return (string)$val;
    }

    /** Поля карточки, доступные для редактирования (кроме special-полей). */
    public const EDITABLE_FIELDS = [
        'letter_number', 'letter_date', 'address', 'customer', 'contract_number',
        'contract_date', 'cost', 'note', 'queue_id', 'engineer_id',
    ];

    /** Обновление набора полей с аудитом каждого изменения (п.6.4, 5.7 ТЗ). */
    public static function updateFields(array $old, array $new): void
    {
        $diff = [];
        foreach ($new as $k => $v) {
            $ov = $old[$k] ?? null;
            // нормализация пустых дат/чисел для сравнения
            $norm = static fn($x) => ($x === null || $x === '') ? null : (is_float($x) ? sprintf('%.2f', $x) : (string)$x);
            if ($norm($ov) !== $norm($v)) $diff[$k] = [$ov, $v];
        }
        if (!$diff) return;
        $id = (int)$old['id'];
        $diff['updated_at'] = date('Y-m-d H:i:s');
        DB::update('objects', $diff, 'id = :id', ['id' => $id]);
        foreach ($diff as $k => [$ov, $nv]) {
            if ($k === 'updated_at') continue;
            Audit::fieldChange($id, 'Объект', $k, self::displayValue($k, $ov), self::displayValue($k, $nv));
        }
    }
}
