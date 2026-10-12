<?php
declare(strict_types=1);

/** Экранирование вывода (защита от XSS). */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect + выход. */
function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

/** Текущий путь без домена. */
function current_uri(): string
{
    return $_SERVER['REQUEST_URI'] ?? '/';
}

/** Формат даты дд.мм.гггг. */
function fmt_date(?string $dt): string
{
    if (!$dt) return '';
    $t = strtotime($dt);
    return $t ? date('d.m.Y', $t) : '';
}

/** Формат даты-времени дд.мм.гггг ЧЧ:ММ. */
function fmt_datetime(?string $dt): string
{
    if (!$dt) return '';
    $t = strtotime($dt);
    return $t ? date('d.m.Y H:i', $t) : '';
}

/** Аварийное завершение с кодом ошибки и понятным текстом. */
function abort(int $code, string $message): never
{
    http_response_code($code);
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['Accept'] ?? '', 'json')) {
        json_response(['ok' => false, 'error' => $message], $code);
    }
    View::render('error', ['pageTitle' => "Ошибка {$code}", 'code' => $code, 'message' => $message]);
    exit;
}

/** Одноразовые сообщения пользователю (через сессию). */
function flash(string $type, string $text): void
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $text];
}

/** Достать и очистить накопленные сообщения. */
function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Пустая строка => null, иначе нормализованная дата Y-m-d (или null если не читается). */
function nullable_date(string $v): ?string
{
    $v = trim($v);
    if ($v === '') return null;
    $t = strtotime(str_replace('.', '-', $v));
    return $t ? date('Y-m-d', $t) : null;
}

/** Разбор денежного значения: «1 200,50 руб» => '1200.50', пустое => null. */
function parse_money(string $v): ?string
{
    $v = trim($v);
    if ($v === '' || $v === '—') return null;
    $v = preg_replace('/[^\d.,\-]/u', '', str_replace("\xc2\xa0", ' ', $v));
    $v = str_replace([' ', ','], ['', '.'], $v);
    // при двух разделителях оставляем последний как десятичный
    if (substr_count($v, '.') > 1) {
        $pos = strrpos($v, '.');
        $v = str_replace('.', '', substr($v, 0, $pos)) . substr($v, $pos);
    }
    return is_numeric($v) ? sprintf('%.2f', (float)$v) : null;
}

/** Формат суммы для вывода: 350000.00 => «350 000.00». */
function fmt_money(?string $v): string
{
    if ($v === null || $v === '') return '';
    return number_format((float)$v, 2, '.', ' ');
}

/** JSON-ответ (для AJAX). */
function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Загрузка POST-файла в закрытую директорию data/uploads со случайным именем. */
function store_upload(array $file, string $subdir = 'misc'): array
{
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'error' => 'Недопустимый тип файла (разрешены: PDF, DOC/DOCX, XLS/XLSX, JPG, PNG).'];
    }
    $maxSize = (int)(Settings_value('max_file_mb', '20')) * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        return ['ok' => false, 'error' => 'Файл превышает максимальный размер ' . round($maxSize / 1048576, 1) . ' МБ.'];
    }
    $dir = OKS_ROOT . '/data/uploads/' . preg_replace('/[^a-z0-9_]/', '', $subdir);
    is_dir($dir) || mkdir($dir, 0775, true);
    $rand = bin2hex(random_bytes(16));
    $stored = $rand . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        return ['ok' => false, 'error' => 'Не удалось сохранить файл на сервере.'];
    }
    return ['ok' => true, 'path' => $subdir . '/' . $stored, 'original_name' => $file['name'], 'size' => (int)$file['size']];
}
