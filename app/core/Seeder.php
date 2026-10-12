<?php
declare(strict_types=1);

/**
 * Начальное наполнение БД: настройки, типы документов, очереди, общий чат,
 * пользователи (админ + демо-сотрудники), демо-объекты.
 * Идемпотентность обеспечивается проверками «если пусто».
 */
class Seeder
{
    /**
     * @param array $admin ['login'=>..,'pass'=>..,'name'=>..,'email'=>..] — данные администратора из установщика.
     * @param bool  $demo  загружать ли демо-данные (пользователи/очереди/объекты).
     */
    public static function seed(array $admin, bool $demo = true): void
    {
        self::settings();
        self::docTypes();
        $adminId = self::user($admin['login'], $admin['pass'], $admin['name'] ?? 'Администратор', $admin['email'] ?? '', 'admin');
        if ($demo) {
            self::demoUsersAndQueues();
            self::demoObjects($adminId);
            self::demoChat($adminId);
        }
    }

    /* ---------- обязательные справочники ---------- */

    private static function settings(): void
    {
        $defaults = [
            'session_timeout_min' => '60',
            'max_file_mb'         => '20',
            'quota_object_mb'     => '500',
            'quota_subobject_mb'  => '500',
            'allowed_ext'         => 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            'ip_allow_enabled'    => '0',
            'ip_allow_list'       => '',
            'app_name'            => 'Учёт ОКС — отдел строительного контроля',
            'logo_file'           => '',
        ];
        foreach ($defaults as $k => $v) {
            if (!DB::queryOne('SELECT k FROM settings WHERE k = :k', ['k' => $k])) {
                DB::insert('settings', ['k' => $k, 'v' => $v]);
            }
        }
    }

    private static function docTypes(): void
    {
        if ((int)DB::queryOne('SELECT COUNT(*) c FROM doc_types')['c'] > 0) return;
        $types = [
            ['Акт опломбировки счётчика', 0],
            ['Заключение лаборатории', 0],
            ['План сети', 1],
            ['ТУ / Договор техприсоединения', 1],
            ['Прочее', 0],
        ];
        foreach ($types as $i => [$name, $multi]) {
            DB::insert('doc_types', ['name' => $name, 'multi_file' => $multi, 'sort' => $i, 'is_active' => 1]);
        }
    }

    /* ---------- пользователи ---------- */

    private static function user(string $login, string $pass, string $fullName, string $email, string $role): int
    {
        $ex = DB::queryOne('SELECT id FROM users WHERE login = :l', ['l' => $login]);
        if ($ex) return (int)$ex['id'];
        return DB::insert('users', [
            'login' => $login, 'pass_hash' => Auth::hash($pass),
            'full_name' => $fullName, 'email' => $email, 'role' => $role,
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function demoUsersAndQueues(): void
    {
        // Демо-пароли — только для тестовой проверки интерфейса; админ сменит их или заблокирует записи.
        $engineers = [
            ['ivanov', 'Иванов И. И.', 'e1'],
            ['petrov', 'Петров П. П.', 'e2'],
            ['sidorov', 'Сидоров С. С.', 'e3'],
        ];
        $ids = [];
        foreach ($engineers as [$login, $name, $_]) {
            $ids[$login] = self::user($login, 'Demo!2026', $name, '', 'engineer');
        }
        $acc  = self::user('buhgalter', 'Demo!2026', 'Бухгалтерия Тестова А. А.', '', 'account');
        $pto  = self::user('pto', 'Demo!2026', 'ПТО Сотрудник П. П.', '', 'pto');
        $conn = self::user('podklyuchenie', 'Demo!2026', 'Подключение Сотников В. В.', '', 'connect');
        $view = self::user('nablyudatel', 'Demo!2026', 'Наблюдатель Наумов Н. Н.', '', 'viewer');

        if ((int)DB::queryOne('SELECT COUNT(*) c FROM queues')['c'] > 0) return;

        // Цвета по умолчанию п.6.1.1: жёлтый — юрлица, синий — физлица, красный — без договора, серый — подобъекты.
        $q1 = DB::insert('queues', ['name' => 'Юридические лица', 'color' => '#ffe082', 'rotation_pos' => 0, 'sort' => 1]);
        $q2 = DB::insert('queues', ['name' => 'Физические лица', 'color' => '#bbdefb', 'rotation_pos' => 0, 'sort' => 2]);
        $q3 = DB::insert('queues', ['name' => 'Без договора на стройконтроль (капремонт и т.д.)', 'color' => '#ffcdd2', 'rotation_pos' => 0, 'sort' => 3]);
        DB::insert('queues', ['name' => 'Цвет подобъектов', 'color' => '#e0e0e0', 'rotation_pos' => 0, 'sort' => 9, 'is_subobject_color' => 1]);

        // Состав очередей: инженеры во всех трёх, порядок = ротация.
        $order = [$ids['ivanov'], $ids['petrov'], $ids['sidorov']];
        foreach ([$q1, $q2, $q3] as $qi => $qid) {
            foreach ($order as $si => $uid) {
                DB::insert('queue_members', ['queue_id' => $qid, 'user_id' => $uid, 'sort' => $si]);
            }
        }
    }

    /* ---------- демо-объекты ---------- */

    private static function demoObjects(int $adminId): void
    {
        if ((int)DB::queryOne('SELECT COUNT(*) c FROM objects')['c'] > 0) return;

        $year = (int)date('Y');
        $engIds = array_column(DB::queryAll("SELECT id FROM users WHERE role='engineer' ORDER BY id"), 'id');
        $qIds   = array_column(DB::queryAll('SELECT id FROM queues WHERE is_subobject_color=0 ORDER BY sort'), 'id');
        $now = date('Y-m-d H:i:s');

        $mk = function (array $d, int $queueIdx, int $engIdx) use ($year, $engIds, $qIds, $adminId, $now): int {
            $num = self::nextNumber($year);
            return DB::insert('objects', [
                'year' => $year, 'number_in_year' => $num,
                'queue_id' => $qIds[$queueIdx] ?? null,
                'letter_number' => $d[0], 'letter_date' => $d[1],
                'address' => $d[2], 'customer' => $d[3],
                'contract_number' => $d[4], 'contract_date' => $d[5],
                'cost' => $d[6], 'status' => $d[7],
                'pto_mark' => $d[8], 'pto_date' => $d[9],
                'act_mark' => $d[10], 'act_date' => $d[11],
                'engineer_id' => $engIds[$engIdx % max(1, count($engIds))] ?? $adminId,
                'note' => $d[12] ?? '',
                'created_by' => $adminId, 'created_at' => $now,
            ]);
        };

        $o1 = $mk(['ИСх-104 от 05.02.' . $year, $year . '-02-05',
            'г. Москва, ул. Строителей, д. 12, жил. дом с встроенными помещениями',
            'ООО «СтройИнвест»', 'ДИ-77/12 от 20.02.' . $year, $year . '-02-20',
            '350000.00', 'work', 1, $year . '-06-15', 1, $year . '-07-01', 'Требуется проверка разделов 1-4'], 0, 0);
        $o2 = $mk(['ИСх-118 от 12.03.' . $year, $year . '-03-12',
            'г. Москва, Кутузовский пр-т, д. 34, офисное здание',
            'АО «ГлавПроект»', 'ГП-05/3 от 01.04.' . $year, $year . '-04-01',
            '520000.00', 'done', 1, $year . '-08-10', 0, null, ''], 0, 1);
        $o3 = $mk(['Обр-15 от 20.05.' . $year, $year . '-05-20',
            'г. Москва, ул. Лесная, д. 7, кв. 45 (частный дом)',
            'Иванов А. С. (физ. лицо)', 'ФЗ-112 от 25.05.' . $year, $year . '-05-25',
            '85000.00', 'work', 0, null, 0, null, 'Договор ещё не подписан заказчиком'], 1, 2);
        $o4 = $mk(['Расп-9 от 02.06.' . $year, $year . '-06-02',
            'г. Москва, ул. Школьная, д. 15, капитальный ремонт фасада',
            'ГБУ «Жилищник района»', '', null,
            null, 'none', 0, null, 0, null, ''], 2, 0);

        // Подобъект к o1 (тот же заказчик, другой договор) — вне очереди, инженер родителя.
        $parent = DB::queryOne('SELECT engineer_id FROM objects WHERE id=:i', ['i' => $o1]);
        $sub1 = DB::insert('objects', [
            'parent_id' => $o1, 'sub_no' => 1, 'year' => $year, 'number_in_year' => 0,
            'letter_number' => 'ИСх-301 от 10.07.' . $year, 'letter_date' => $year . '-07-10',
            'address' => 'г. Москва, ул. Строителей, д. 12, сеть канализации',
            'customer' => 'ООО «СтройИнвест»',
            'contract_number' => 'СИ-Кан/3 от 15.07.' . $year, 'contract_date' => $year . '-07-15',
            'cost' => '180000.00', 'status' => 'work',
            'engineer_id' => (int)$parent['engineer_id'],
            'created_by' => $adminId, 'created_at' => $now,
        ]);
        $sub2 = DB::insert('objects', [
            'parent_id' => $o1, 'sub_no' => 2, 'year' => $year, 'number_in_year' => 0,
            'letter_number' => 'ИСх-344 от 01.09.' . $year, 'letter_date' => $year . '-09-01',
            'address' => 'г. Москва, ул. Строителей, д. 12, водопровод (2-й очередь)',
            'customer' => 'ООО «СтройИнвест»',
            'contract_number' => '', 'contract_date' => null,
            'cost' => null, 'status' => 'none',
            'engineer_id' => (int)$parent['engineer_id'],
            'created_by' => $adminId, 'created_at' => $now,
        ]);

        // Демо-письма (вх/исх) к o1
        DB::insert('letters', ['object_id' => $o1, 'direction' => 'in', 'number' => 'ИСх-104',
            'ldate' => $year . '-02-05', 'counterparty' => 'ООО «СтройИнвест»',
            'descr' => 'Заявка на строительный контроль', 'created_by' => $adminId, 'created_at' => $now]);
        DB::insert('letters', ['object_id' => $o1, 'direction' => 'out', 'number' => 'Исх-33',
            'ldate' => $year . '-03-01', 'counterparty' => 'ООО «СтройИнвест»',
            'descr' => 'Уведомление о начале работ', 'created_by' => $adminId, 'created_at' => $now]);

        // Демо-счета: o1 — оплачен, o2 — не оплачен, sub1 — не оплачен
        $acc2 = DB::queryOne("SELECT id FROM users WHERE role='account' LIMIT 1");
        $aid = (int)($acc2['id'] ?? $adminId);
        $inv = function (int $objId, string $num, string $date, string $sum, int $paid, ?string $payDate, ?string $paySum, string $comment) use ($aid, $now) {
            DB::insert('invoices', ['object_id' => $objId, 'number' => $num, 'idate' => $date, 'amount' => $sum,
                'paid' => $paid, 'pay_date' => $payDate, 'pay_amount' => $paySum, 'pay_comment' => $comment,
                'created_by' => $aid, 'created_at' => $now]);
        };
        $inv($o1, 'СП-114', $year . '-03-01', '350000.00', 1, $year . '-03-20', '350000.00', 'П/п 45 от 20.03.' . $year);
        $inv($o2, 'СП-140', $year . '-04-15', '520000.00', 0, null, null, '');
        $inv($sub1, 'СП-171', $year . '-08-01', '180000.00', 0, null, null, '');
    }

    private static function nextNumber(int $year): int
    {
        $row = DB::queryOne('SELECT last_num FROM object_seq WHERE year = :y', ['y' => $year]);
        $n = ($row ? (int)$row['last_num'] : 0) + 1;
        if ($row) {
            DB::update('object_seq', ['last_num' => $n], 'year = :y', ['y' => $year]);
        } else {
            DB::insert('object_seq', ['year' => $year, 'last_num' => $n]);
        }
        return $n;
    }

    private static function demoChat(int $adminId): void
    {
        if ((int)DB::queryOne('SELECT COUNT(*) c FROM chat_threads')['c'] > 0) return;
        $tid = DB::insert('chat_threads', ['kind' => 'dept', 'title' => 'Отдел', 'created_at' => date('Y-m-d H:i:s')]);
        foreach (DB::queryAll('SELECT id FROM users WHERE is_active=1') as $u) {
            DB::insert('chat_participants', ['thread_id' => $tid, 'user_id' => (int)$u['id'], 'last_read_msg_id' => 0]);
        }
        $msgs = [
            ['Коллеги, по объекту №1 ждём заключение лаборатории до конца недели.', -3],
            ['Принято, передам в лабораторию сегодня.', -2],
            ['По капремонту на Школьной, 15 договор пока не прислали — статус пустой.', -1],
        ];
        $eng = DB::queryAll("SELECT id, full_name FROM users WHERE role='engineer' ORDER BY id LIMIT 2");
        foreach ($msgs as $i => [$text, $dayOffset]) {
            $author = $i === 0 ? $adminId : (int)($eng[$i % count($eng)]['id'] ?? $adminId);
            DB::insert('chat_messages', ['thread_id' => $tid, 'user_id' => $author, 'body' => $text,
                'created_at' => date('Y-m-d H:i:s', strtotime($dayOffset . ' day'))]);
        }
    }
}
