<?php
declare(strict_types=1);
require_once OKS_ROOT . '/app/controllers/Base.php';

/** Главная (дашборд): сводка по объектам, очереди, следующий ответственный. */
class DashboardPage extends BaseController
{
    public function handle(): void
    {
        $this->guard();

        $stats  = ['total' => 0, 'work' => 0, 'done' => 0, 'mine' => 0];
        $queues = [];
        $events = [];
        $unreadTotal = 0;
        try {
            $u = Auth::user();
            // Инженер видит только свои объекты и подобъекты (п.4.1 ТЗ).
            $w = Rights::onlyOwnObjects($u['role']) ? ' AND engineer_id = ' . (int)$u['id'] : '';
            $row = DB::queryOne("SELECT COUNT(*) c FROM objects WHERE is_archived = 0{$w}");
            $stats['total'] = (int)($row['c'] ?? 0);
            $row = DB::queryOne("SELECT COUNT(*) c FROM objects WHERE is_archived = 0 AND status = 'work'{$w}");
            $stats['work'] = (int)($row['c'] ?? 0);
            $row = DB::queryOne("SELECT COUNT(*) c FROM objects WHERE is_archived = 0 AND status = 'done'{$w}");
            $stats['done'] = (int)($row['c'] ?? 0);
            $stats['mine'] = $stats['total'];

            // Очереди: карточки в цветах очереди + «кто следующий» (п.6.5, 6.7 интерфейса).
            foreach (DB::queryAll('SELECT * FROM queues WHERE is_subobject_color = 0 ORDER BY sort') as $q) {
                $members = DB::queryAll(
                    'SELECT u.id, u.full_name, qm.sort AS position
                     FROM queue_members qm JOIN users u ON u.id = qm.user_id
                     WHERE qm.queue_id = :qid AND u.is_active = 1 ORDER BY qm.sort',
                    ['qid' => $q['id']]);
                $next = null;
                if ($members) {
                    $n = count($members);
                    $ptr = ((int)$q['rotation_pos']) % max(1, $n);
                    $next = $members[$ptr]['full_name'];
                }
                $queues[] = ['queue' => $q, 'next' => $next, 'members' => $members];
            }

            // Последние события журнала (без ссылки на полный журнал — п.7 ТЗ).
            $events = DB::queryAll(
                'SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
                 ORDER BY a.id DESC LIMIT 12');

            // Непрочитанные сообщения чата (для счётчика на дашборде).
            $unreadTotal = (int)(DB::queryOne(
                'SELECT COUNT(*) c FROM chat_messages m
                 JOIN chat_participants p ON p.thread_id = m.thread_id AND p.user_id = :uid
                 WHERE m.id > p.last_read_msg_id', ['uid' => (int)$u['id']])['c'] ?? 0);
        } catch (Throwable $e) { /* до установки таблицы могут отсутствовать */ }

        View::render('dashboard', [
            'pageTitle'   => 'Рабочий стол',
            'stats'       => $stats,
            'queues'      => $queues,
            'events'      => $events,
            'unreadTotal' => $unreadTotal,
        ]);
    }
}

$router->add('GET', '/', 'DashboardPage');
