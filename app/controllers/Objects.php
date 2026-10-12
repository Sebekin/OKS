<?php
declare(strict_types=1);
require_once OKS_ROOT . '/app/controllers/Base.php';

/**
 * Раздел «Объекты» (раздел 6 ТЗ): таблица с фильтрами/сортировкой,
 * добавление объекта/подобъекта, карточка, статусы и отметки кликом, удаление.
 */
class ObjectsPage extends BaseController
{
    public function handle(): void
    {
        $this->guard();
        Rights::require('objects.view');

        $action = $_GET['a'] ?? 'list';
        match ($action) {
            'list'   => $this->list(),
            'new'    => $this->formNew(),
            'create' => $this->create(),
            'card'   => $this->card(),
            'edit'   => $this->saveEdit(),
            'status' => $this->setStatus(),
            'mark'   => $this->toggleMark(),
            'delete' => $this->deleteObject(),
            default  => redirect('/objects'),
        };
    }

    /* ---------------- Таблица ---------------- */

    private function list(): void
    {
        $filters = [
            'q'        => trim((string)($_GET['q'] ?? '')),
            'status'   => trim((string)($_GET['status'] ?? '')),
            'queue'    => (int)($_GET['queue'] ?? 0),
            'engineer' => (int)($_GET['engineer'] ?? 0),
            'year'     => (int)($_GET['year'] ?? 0),
            'pto'      => !empty($_GET['pto']),
            'act'      => !empty($_GET['act']),
            'sort'     => trim((string)($_GET['sort'] ?? 'num')),
            'dir'      => ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ];
        $rows   = ObjectModel::listForCurrentUser($filters);
        $queues = DB::queryAll('SELECT * FROM queues WHERE is_subobject_color = 0 ORDER BY sort');
        $engs   = DB::queryAll("SELECT id, full_name FROM users WHERE role IN ('engineer','admin') AND is_active = 1 ORDER BY full_name");

        View::render('objects/list', [
            'pageTitle' => 'Объекты',
            'rows'      => $rows,
            'filters'   => $filters,
            'queues'    => $queues,
            'engineers' => $engs,
            'years'     => ObjectModel::years(),
            'canCreate' => Rights::can('objects.create'),
        ]);
    }

    /* ---------------- Добавление ---------------- */

    private function formNew(): void
    {
        Rights::require('objects.create');
        $parentId = isset($_GET['parent']) ? (int)$_GET['parent'] : 0;
        $parent = $parentId ? ObjectModel::find($parentId) : null;
        if ($parentId && (!$parent || !ObjectModel::canAccess($parent))) abort(403, 'Нет доступа к родительскому объекту');
        if ($parentId && !Rights::can('objects.add_sub')) abort(403, 'Добавление подобъектов недоступно');

        View::render('objects/form_new', [
            'pageTitle' => $parent ? 'Новый подобъект' : 'Новый объект',
            'parent'    => $parent,
            'queues'    => DB::queryAll('SELECT * FROM queues WHERE is_subobject_color = 0 ORDER BY sort'),
            'engineers' => DB::queryAll("SELECT id, full_name FROM users WHERE role IN ('engineer','admin') AND is_active = 1 ORDER BY full_name"),
        ]);
    }

    private function create(): void
    {
        Rights::require('objects.create');
        Csrf::checkRequest();
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $parent = $parentId ? ObjectModel::find($parentId) : null;
        if ($parentId) {
            if (!$parent || !ObjectModel::canAccess($parent)) abort(403, 'Нет доступа к родителю');
            Rights::require('objects.add_sub');
        }

        $address = trim((string)($_POST['address'] ?? ''));
        if ($address === '') { flash('error', 'Укажите адрес объекта'); redirect('/objects/new' . ($parentId ? '?parent=' . $parentId : '')); }

        $now = date('Y-m-d H:i:s');
        $data = [
            'parent_id'       => $parentId ?: null,
            'sub_no'          => null,
            'year'            => $parentId ? (int)$parent['year'] : (int)date('Y'),
            'number_in_year'  => 0,
            'queue_id'        => $parentId ? null : ((int)($_POST['queue_id'] ?? 0) ?: null),
            'letter_number'   => trim((string)($_POST['letter_number'] ?? '')),
            'letter_date'     => nullable_date($_POST['letter_date'] ?? ''),
            'address'         => $address,
            'customer'        => trim((string)($_POST['customer'] ?? '')),
            'contract_number' => trim((string)($_POST['contract_number'] ?? '')),
            'contract_date'   => nullable_date($_POST['contract_date'] ?? ''),
            'cost'            => parse_money((string)($_POST['cost'] ?? '')),
            'status'          => 'none',
            'engineer_id'     => $parentId
                ? (int)$parent['engineer_id']
                : ((int)($_POST['engineer_id'] ?? 0) ?: Auth::id()),
            'note'            => trim((string)($_POST['note'] ?? '')),
            'created_by'      => Auth::id(),
            'created_at'      => $now,
        ];

        if ($parentId) {
            $last = DB::queryOne('SELECT COALESCE(MAX(sub_no),0) m FROM objects WHERE parent_id = :p', ['p' => $parentId]);
            $data['sub_no'] = (int)$last['m'] + 1;
        } else {
            $data['number_in_year'] = ObjectModel::nextNumber((int)$data['year']);
        }

        $id = DB::insert('objects', $data);
        Audit::log('data', 'Создание объекта', (string)$id, null, null,
            $data['sub_no'] ? "подобъект №{$data['sub_no']} к #{$parentId}" : "объект №{$data['number_in_year']}/{$data['year']}: {$address}");

        // Автоназначение очереди по ротации (п.6.7): следующий инженер очереди получает объект
        flash('ok', 'Объект добавлен');
        redirect('/objects/card?id=' . $id);
    }

    /* ---------------- Карточка ---------------- */

    private function card(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $obj = ObjectModel::find($id);
        if (!$obj) abort(404, 'Объект не найден');
        if (!ObjectModel::canAccess($obj)) abort(403, 'Этот объект вам не доступен');

        $subs = DB::queryAll(
            'SELECT o.*, e.full_name AS engineer_name FROM objects o
             LEFT JOIN users e ON e.id = o.engineer_id
             WHERE o.parent_id = :p AND o.is_archived = 0 ORDER BY o.sub_no',
            ['p' => $id]);
        $docs = DB::queryAll(
            'SELECT d.*, t.name AS type_name FROM documents d
             LEFT JOIN doc_types t ON t.id = d.type_id WHERE d.object_id = :o ORDER BY d.id DESC',
            ['o' => $id]);
        $docFiles = [];
        foreach ($docs as $d) {
            $docFiles[$d['id']] = DB::queryAll('SELECT * FROM document_files WHERE document_id = :d', ['d' => $d['id']]);
        }
        $letters = DB::queryAll('SELECT * FROM letters WHERE object_id = :o ORDER BY ldate DESC, id DESC', ['o' => $id]);
        $invoices = DB::queryAll('SELECT * FROM invoices WHERE object_id = :o ORDER BY idate DESC, id DESC', ['o' => $id]);
        $history = Audit::historyFor($id);
        $parent = $obj['parent_id'] ? ObjectModel::find((int)$obj['parent_id']) : null;

        View::render('objects/card', [
            'pageTitle' => 'Объект ' . ($obj['sub_no']
                ? '#' . (int)$parent['number_in_year'] . '.' . (int)$obj['sub_no']
                : '#' . (int)$obj['number_in_year'] . '/' . (int)$obj['year']),
            'obj'       => $obj,
            'parent'    => $parent,
            'subs'      => $subs,
            'docs'      => $docs,
            'docFiles'  => $docFiles,
            'letters'   => $letters,
            'invoices'  => $invoices,
            'history'   => $history,
            'queues'    => DB::queryAll('SELECT * FROM queues WHERE is_subobject_color = 0 ORDER BY sort'),
            'engineers' => DB::queryAll("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name"),
            'docTypes'  => DB::queryAll('SELECT * FROM doc_types WHERE is_active = 1 ORDER BY sort'),
            'editable'  => Rights::can('objects.edit_fields') && !$obj['parent_id'] || Rights::can('objects.edit_fields'),
            'queueColor' => $obj['parent_id']
                ? (DB::queryOne('SELECT q.color FROM objects p JOIN queues q ON q.id = p.queue_id WHERE p.id = :i', ['i' => (int)$obj['parent_id']])['color'] ?? '#e0e0e0')
                : ($obj['queue_color'] ?? '#eee'),
        ]);
    }

    /* ---------------- Быстрые действия из таблицы/карточки ---------------- */

    /** Сохранение полей карточки (inline-редактирование формы). */
    private function saveEdit(): void
    {
        Rights::require('objects.edit_fields');
        Csrf::checkRequest();
        $id = (int)($_POST['id'] ?? 0);
        $obj = ObjectModel::find($id);
        if (!$obj || !ObjectModel::canAccess($obj)) abort(403, 'Нет доступа');

        $new = [];
        foreach (ObjectModel::EDITABLE_FIELDS as $f) {
            if (!array_key_exists($f, $_POST)) continue;
            $v = trim((string)$_POST[$f]);
            if (str_ends_with($f, '_date')) $v = nullable_date($v);
            elseif ($f === 'cost') $v = parse_money($v);
            elseif (in_array($f, ['queue_id', 'engineer_id'], true)) $v = ((int)$v) ?: null;
            $new[$f] = $v;
        }
        ObjectModel::updateFields($obj, $new);
        flash('ok', 'Изменения сохранены');
        redirect('/objects/card?id=' . $id);
    }

    /** Смена статуса кликом по бейджу (п.6.3). */
    private function setStatus(): void
    {
        Rights::require('objects.change_status');
        Csrf::checkRequest();
        $id = (int)($_POST['id'] ?? 0);
        $st = (string)($_POST['status'] ?? '');
        if (!isset(ObjectModel::STATUSES[$st])) redirect('/objects');
        $obj = ObjectModel::find($id);
        if (!$obj || !ObjectModel::canAccess($obj)) abort(403, 'Нет доступа');

        DB::update('objects', ['status' => $st, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        Audit::fieldChange($id, 'Объект', 'status',
            ObjectModel::displayValue('status', $obj['status']), ObjectModel::displayValue('status', $st));
        json_response(['ok' => true, 'status' => $st, 'label' => ObjectModel::STATUSES[$st][0]]);
    }

    /** Отметка «ИД в ПТО» / «Акт о подключении»: клик — поставить (с датой и автором), повторный — снять (п.6.3). */
    private function toggleMark(): void
    {
        $kind = (string)($_POST['kind'] ?? '');
        if ($kind === 'pto') Rights::require('objects.mark_pto');
        elseif ($kind === 'act') Rights::require('objects.mark_act');
        else redirect('/objects');
        Csrf::checkRequest();

        $id = (int)($_POST['id'] ?? 0);
        $obj = ObjectModel::find($id);
        if (!$obj || !ObjectModel::canAccess($obj)) abort(403, 'Нет доступа');

        $markCol = $kind . '_mark';
        $dateCol = $kind . '_date';
        $byCol   = $kind . '_by';
        $on = (int)$obj[$markCol] === 1;

        DB::update('objects', [
            $markCol => $on ? 0 : 1,
            $dateCol => $on ? null : date('Y-m-d'),
            $byCol   => $on ? null : Auth::id(),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);

        $label = $kind === 'pto' ? 'ИД в ПТО' : 'Акт о подключении';
        Audit::fieldChange($id, 'Объект', $markCol, $on ? 'Отмечено' : '—', $on ? '—' : 'Отмечено (' . date('d.m.Y') . ')');
        json_response(['ok' => true, 'on' => !$on, 'date' => $on ? null : date('d.m.Y')]);
    }

    /** Удаление с подтверждением (архивация — данные остаются в журнале и БД). */
    private function deleteObject(): void
    {
        Rights::require('objects.delete');
        Csrf::checkRequest();
        $id = (int)($_POST['id'] ?? 0);
        $obj = ObjectModel::find($id);
        if (!$obj || !ObjectModel::canAccess($obj)) abort(403, 'Нет доступа');

        DB::update('objects', ['is_archived' => 1, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        // Подобъекты тоже скрываем
        DB::run('UPDATE objects SET is_archived = 1 WHERE parent_id = :p', ['p' => $id]);
        Audit::log('data', 'Удаление объекта', (string)$id, null, $obj['address'], 'удалён (архив)');
        flash('ok', 'Объект удалён');
        redirect('/objects');
    }
}

$router->add('GET',  '/objects', 'ObjectsPage');
$router->add('POST', '/objects', 'ObjectsPage');
