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
            // Документы / письма / счета / файлы (разделы 7–8 ТЗ)
            'doc_add'      => $this->docAdd(),
            'doc_del'      => $this->docDelete(),
            'letter_add'   => $this->letterAdd(),
            'letter_del'   => $this->letterDelete(),
            'invoice_add'  => $this->invoiceAdd(),
            'invoice_pay'  => $this->invoicePay(),
            'invoice_del'  => $this->invoiceDelete(),
            'file_del'     => $this->fileDelete(),
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
        $letters = DB::queryAll('SELECT * FROM letters WHERE object_id = :o ORDER BY ldate DESC, id DESC', ['o' => $id]);
        $invoices = DB::queryAll('SELECT * FROM invoices WHERE object_id = :o ORDER BY idate DESC, id DESC', ['o' => $id]);
        $files = Files::forObject($id);
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
            'files'     => $files,
            'letters'   => $letters,
            'invoices'  => $invoices,
            'history'   => $history,
            'queues'    => DB::queryAll('SELECT * FROM queues WHERE is_subobject_color = 0 ORDER BY sort'),
            'engineers' => DB::queryAll("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name"),
            'docTypes'  => DB::queryAll('SELECT * FROM doc_types WHERE is_active = 1 ORDER BY sort'),
            'canEdit'   => Rights::can('objects.edit_fields') && ObjectModel::canAccess($obj),
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

    /* ---------------- Документы / письма / счета / файлы (разделы 7–8 ТЗ) ---------------- */

    /** Общая проверка: POST + CSRF + объект доступен + право на редактирование полей. */
    private function requireCardPost(int $id): array
    {
        Csrf::checkRequest();
        Rights::require('objects.edit_fields');
        $obj = ObjectModel::find($id);
        if (!$obj || !ObjectModel::canAccess($obj)) abort(403, 'Нет доступа к объекту');
        return $obj;
    }

    /** Сохранить загруженный файл из $_FILES['file'] и вернуть id записи document_files. */
    private function saveUploaded(string $entity, int $entityId, string $subdir): ?int
    {
        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null; // файл не передан — это допустимо
        }
        $res = store_upload($_FILES['file'], $subdir);
        if (!$res['ok']) { flash('err', $res['error']); redirect('/objects/card?id=' . $entityId); }
        return Files::attach($entity, $entityId, $res);
    }

    private function docAdd(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->requireCardPost($id);

        $docId = DB::insert('documents', [
            'object_id'  => $id,
            'type_id'    => ((int)($_POST['type_id'] ?? 0)) ?: null,
            'comment'    => trim((string)($_POST['comment'] ?? '')),
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $nFiles = $this->saveUploaded('document', $docId, 'docs') !== null ? 1 : 0;

        $typeName = DB::queryOne('SELECT name FROM doc_types WHERE id = :i', ['i' => (int)($_POST['type_id'] ?? 0)])['name'] ?? 'без типа';
        Audit::log('data', 'Добавление документа', (string)$id, null, $typeName, ($nFiles ? "с файлом ({$nFiles})" : 'без файла'));
        flash('ok', 'Документ добавлен');
        redirect('/objects/card?id=' . $id . '#tab-docs');
    }

    private function docDelete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $docId = (int)($_POST['doc_id'] ?? 0);
        $this->requireCardPost($id);

        $doc = DB::queryOne('SELECT d.*, t.name AS type_name FROM documents d LEFT JOIN doc_types t ON t.id = d.type_id WHERE d.id = :i AND d.object_id = :o',
            ['i' => $docId, 'o' => $id]);
        if (!$doc) abort(404, 'Документ не найден');
        // Сначала удаляем файлы с диска
        foreach (DB::queryAll('SELECT * FROM document_files WHERE document_id = :d', ['d' => $docId]) as $f) {
            $path = realpath(OKS_ROOT . '/data/uploads/' . $f['stored_name']);
            if ($path && is_file($path)) unlink($path);
        }
        DB::run('DELETE FROM documents WHERE id = :i', ['i' => $docId]); // document_files — через ON DELETE CASCADE
        Audit::log('data', 'Удаление документа', (string)$id, null, $doc['type_name'] ?? '', 'удалён');
        flash('ok', 'Документ удалён');
        redirect('/objects/card?id=' . $id . '#tab-docs');
    }

    private function letterAdd(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->requireCardPost($id);

        $dir = ($_POST['direction'] ?? 'in') === 'out' ? 'out' : 'in';
        $letterId = DB::insert('letters', [
            'object_id'    => $id,
            'direction'    => $dir,
            'number'       => mb_substr(trim((string)($_POST['number'] ?? '')), 0, 100),
            'ldate'        => nullable_date(trim((string)($_POST['ldate'] ?? ''))),
            'counterparty' => mb_substr(trim((string)($_POST['counterparty'] ?? '')), 0, 255),
            'descr'        => trim((string)($_POST['descr'] ?? '')),
            'created_by'   => Auth::id(),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        $this->saveUploaded('letter', $letterId, 'letters');

        Audit::log('data', 'Добавление письма', (string)$id, null,
            ($dir === 'in' ? 'вх. ' : 'исх. ') . trim((string)($_POST['number'] ?? '')), trim((string)($_POST['descr'] ?? '')));
        flash('ok', 'Письмо добавлено');
        redirect('/objects/card?id=' . $id . '#tab-letters');
    }

    private function letterDelete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $lid = (int)($_POST['letter_id'] ?? 0);
        $this->requireCardPost($id);

        $l = DB::queryOne('SELECT * FROM letters WHERE id = :i AND object_id = :o', ['i' => $lid, 'o' => $id]);
        if (!$l) abort(404, 'Письмо не найдено');
        foreach (DB::queryAll('SELECT * FROM document_files WHERE letter_id = :l', ['l' => $lid]) as $f) {
            $path = realpath(OKS_ROOT . '/data/uploads/' . $f['stored_name']);
            if ($path && is_file($path)) unlink($path);
        }
        DB::run('DELETE FROM letters WHERE id = :i', ['i' => $lid]);
        Audit::log('data', 'Удаление письма', (string)$id, null, (string)$l['number'], 'удалено');
        flash('ok', 'Письмо удалено');
        redirect('/objects/card?id=' . $id . '#tab-letters');
    }

    private function invoiceAdd(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->requireCardPost($id);

        $invId = DB::insert('invoices', [
            'object_id'  => $id,
            'number'     => mb_substr(trim((string)($_POST['number'] ?? '')), 0, 100),
            'idate'      => nullable_date(trim((string)($_POST['idate'] ?? ''))),
            'amount'     => parse_money(trim((string)($_POST['amount'] ?? ''))),
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->saveUploaded('invoice', $invId, 'invoices');

        Audit::log('data', 'Добавление счёта', (string)$id, null,
            'счёт ' . trim((string)($_POST['number'] ?? '')), 'на сумму ' . fmt_money(parse_money(trim((string)($_POST['amount'] ?? '')))) . ' ₽');
        flash('ok', 'Счёт добавлен');
        redirect('/objects/card?id=' . $id . '#tab-invoices');
    }

    /** Отметка об оплате счёта (п.7.4): дата, сумма, комментарий. */
    private function invoicePay(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $invId = (int)($_POST['invoice_id'] ?? 0);
        $this->requireCardPost($id);

        $inv = DB::queryOne('SELECT * FROM invoices WHERE id = :i AND object_id = :o', ['i' => $invId, 'o' => $id]);
        if (!$inv) abort(404, 'Счёт не найден');

        $paid = !empty($_POST['paid']) ? 1 : 0;
        DB::update('invoices', [
            'paid'         => $paid,
            'pay_date'     => $paid ? nullable_date(trim((string)($_POST['pay_date'] ?? ''))) ?: date('Y-m-d') : null,
            'pay_amount'   => $paid ? parse_money(trim((string)($_POST['pay_amount'] ?? ''))) : null,
            'pay_comment'  => $paid ? mb_substr(trim((string)($_POST['pay_comment'] ?? '')), 0, 255) : '',
        ], 'id = :i', ['i' => $invId]);

        Audit::fieldChange($id, 'Счёт #' . $inv['number'], 'paid',
            (int)$inv['paid'] === 1 ? 'оплачен' : 'не оплачен',
            $paid ? 'оплачен ' . date('d.m.Y') : 'не оплачен');
        flash('ok', $paid ? 'Оплата зафиксирована' : 'Отметка об оплате снята');
        redirect('/objects/card?id=' . $id . '#tab-invoices');
    }

    private function invoiceDelete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $invId = (int)($_POST['invoice_id'] ?? 0);
        $this->requireCardPost($id);

        $inv = DB::queryOne('SELECT * FROM invoices WHERE id = :i AND object_id = :o', ['i' => $invId, 'o' => $id]);
        if (!$inv) abort(404, 'Счёт не найден');
        foreach (DB::queryAll('SELECT * FROM document_files WHERE invoice_id = :v', ['v' => $invId]) as $f) {
            $path = realpath(OKS_ROOT . '/data/uploads/' . $f['stored_name']);
            if ($path && is_file($path)) unlink($path);
        }
        DB::run('DELETE FROM invoices WHERE id = :i', ['i' => $invId]);
        Audit::log('data', 'Удаление счёта', (string)$id, null, (string)$inv['number'], 'удалён');
        flash('ok', 'Счёт удалён');
        redirect('/objects/card?id=' . $id . '#tab-invoices');
    }

    private function fileDelete(): void
    {
        Csrf::checkRequest();
        Rights::require('objects.edit_fields');
        $fileId = (int)($_POST['file_id'] ?? 0);
        $backId = (int)($_POST['id'] ?? 0);
        Files::delete($fileId);
        flash('ok', 'Файл удалён');
        redirect('/objects/card?id=' . $backId);
    }
}

$router->add('GET',  '/objects', 'ObjectsPage');
$router->add('POST', '/objects', 'ObjectsPage');
$router->add('GET',  '/file', 'FilePage');

/** Отдача файлов из закрытого хранилища data/uploads (раздел 8 ТЗ). */
class FilePage extends BaseController
{
    public function handle(): void
    {
        $this->guard();
        Files::serve((int)($_GET['i'] ?? 0));
    }
}
