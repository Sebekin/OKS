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
