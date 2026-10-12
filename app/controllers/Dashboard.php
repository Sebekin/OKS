<?php
declare(strict_types=1);
require_once OKS_ROOT . '/app/controllers/Base.php';

/** Главная (дашборд): сводка по объектам, очереди, следующий ответственный. */
class DashboardPage extends BaseController
{
    public function handle(): void
    {
        $this->guard();

        $stats = ['total' => 0, 'work' => 0, 'done' => 0, 'mine' => 0];
        $queues = [];
        try {
            $u = Auth::user();
            $w = Rights::onlyOwnObjects($u['role']) ? ' AND responsible_id = ' . (int)$u['id'] : '';
            $row = DB::queryOne("SELECT COUNT(*) c FROM objects WHERE is_archived = 0{$w}");
            $stats['total'] = (int)($row['c'] ?? 0);
            $row = DB::queryOne("SELECT COUNT(*) c FROM objects WHERE is_archived = 0 AND status = 'work'{$w}");
            $stats['work'] = (int)($row['c'] ?? 0);
            $row = DB::queryOne("SELECT COUNT(*) c FROM objects WHERE is_archived = 0 AND status = 'done'{$w}");
            $stats['done'] = (int)($row['c'] ?? 0);
            $stats['mine'] = $stats['total'];

            foreach (DB::queryAll('SELECT * FROM queues ORDER BY sort_order') as $q) {
                $next = null;
                $members = DB::queryAll('SELECT u.full_name, q.position FROM queue_members q JOIN users u ON u.id = q.user_id WHERE q.queue_id = :qid ORDER BY q.position', ['qid' => $q['id']]);
                if ($members) {
                    $ptr = (int)$q['rotation_pos'];
                    foreach ($members as $m) {
                        if ((int)$m['position'] >= $ptr) { $next = $m['full_name']; break; }
                    }
                    $next ??= $members[0]['full_name'];
                }
                $queues[] = ['queue' => $q, 'next' => $next, 'members' => $members];
            }
        } catch (Throwable $e) { /* до установки таблицы могут отсутствовать */ }

        View::render('dashboard', [
            'pageTitle' => 'Рабочий стол',
            'stats'     => $stats,
            'queues'    => $queues,
        ]);
    }
}

$router->add('GET', '/', 'DashboardPage');
