<?php
declare(strict_types=1);

/**
 * Простой шаблонизатор: View::render('page', ['data'=>...]).
 * Шаблоны лежат в app/views/, макет — layout.php.
 */
class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        $file = OKS_ROOT . '/app/views/' . $template . '.php';
        if (!is_file($file)) { http_response_code(500); exit('Шаблон не найден: ' . e($template)); }

        extract($data, EXTR_SKIP);
        $__content = (function () use ($file, $data) {
            extract($data, EXTR_SKIP);
            ob_start();
            include $file;
            return ob_get_clean();
        })();

        if ($layout !== null) {
            include OKS_ROOT . '/app/views/' . $layout . '.php';
        } else {
            echo $__content;
        }
    }
}
