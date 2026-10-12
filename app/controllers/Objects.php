<?php
declare(strict_types=1);
require_once OKS_ROOT . '/app/controllers/Base.php';

/** Заглушка раздела «Объекты» до этапа 2 (таблица + карточки). */
class ObjectsPage extends BaseController
{
    public function handle(): void
    {
        $this->guard();
        Rights::require('objects.view');
        View::render('coming_soon', [
            'pageTitle' => 'Объекты',
            'note'      => 'Раздел «Объекты» (таблица, карточки, статусы) будет доступен на следующем этапе разработки.',
        ]);
    }
}

$router->add('GET', '/objects', 'ObjectsPage');
