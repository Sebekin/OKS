<?php
/**
 * Пример конфигурации приложения «Учёт ОКС».
 * Веб-установщик создаёт config.php на основе этого шаблона.
 * ВНИМАНИЕ: config.php содержит реальные пароли БД и в открытый репозиторий не попадает (.gitignore).
 */
return [
    'db' => [
        'driver'  => 'mysql',            // mysql (хостинг) | sqlite (только для разработки/тестов)
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'oks_db',
        'user'    => 'oks_user',
        'pass'    => '',
        'charset' => 'utf8mb4',
        'sqlite_path' => __DIR__ . '/../data/dev.sqlite',
    ],
    'app' => [
        'name'      => 'Учёт ОКС — отдел строительного контроля',
        'timezone'  => 'Europe/Moscow',  // часовой пояс по умолчанию — Москва
        'installed' => true,
    ],
];
