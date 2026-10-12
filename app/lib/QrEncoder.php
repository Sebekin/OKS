<?php
declare(strict_types=1);

/**
 * Минимальный генератор QR-кодов (ISO/IEC 18004), режим byte, уровень коррекции M.
 * Версии 1–10 (до ~213 байт данных) — достаточно для otpauth:// URI.
 * Без внешних библиотек и интернета (п.3.2 ТЗ).
 */
class QrEncoder
{
    // Уровень M: [всего data codewords, ecW на блок, G1 (кол-во, длина), G2 (кол-во, длина)]
    private const SPEC_M = [
        1  => [16,  10, 1, 16, 0,  0],
        2  => [28,  16, 1, 28, 0,  0],
        3  => [44,  26, 1, 44, 0,  0],
        4  => [64,  18, 2, 32, 0,  0],
        5  => [86,  24, 2, 43, 0,  0],
        6  => [108, 16, 4, 27, 0,  0],
        7  => [124, 18, 4, 31, 0,  0],
        8  => [154, 22, 2, 38, 2, 39],
        9  => [182, 22, 3, 36, 2, 37],
        10 => [216, 26, 4, 43, 1, 44],
    ];

    /** @return bool[][] квадратная матрица */
    public static function encode(string $data): array
    {
        $len = strlen($data);
        $version = 0;
        foreach (self::SPEC_M as $v => [$totalData]) {
            if ($len <= $totalData - 2) { $version = $v; break; } // заголовок: 4+8 бит
        }
        if ($version === 0) throw new RuntimeException('Данные слишком длинны для QR (макс. версия 10).');

        [$totalData, $ecPerBlock, $n1, $l1, $n2, $l2] = self::SPEC_M[$version];

        // --- Поток кодирования ---
        $bits = '0100' . str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
        for ($i = 0; $i < $len; $i++) $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        $bits .= '0000'; // terminator
        while (strlen($bits) % 8 !== 0) $bits .= '0';
        $bytes = [];
        foreach (str_split($bits, 8) as $b) $bytes[] = (int)bindec($b);
        $pad = [0xEC, 0x11]; $pi = 0;
        while (count($bytes) < $totalData) { $bytes[] = $pad[$pi++ % 2]; }

        // --- Блоки данных + Reed-Solomon ECC ---
        $blocks = []; $ecBlocks = []; $pos = 0;
        for ($b = 0; $b < $n1 + $n2; $b++) {
            $blen = $b < $n1 ? $l1 : $l2;
            $chunk = array_slice($bytes, $pos, $blen); $pos += $blen;
            $blocks[] = $chunk;
            $ecBlocks[] = self::reedSolomon($chunk, $ecPerBlock);
        }

        // --- Интерливинг ---
        $final = [];
        $maxLen = max(array_map('count', $blocks));
        for ($i = 0; $i < $maxLen; $i++)
            foreach ($blocks as $blk) if (isset($blk[$i])) $final[] = $blk[$i];
        for ($i = 0; $i < $ecPerBlock; $i++)
            foreach ($ecBlocks as $blk) if (isset($blk[$i])) $final[] = $blk[$i];

        return self::buildMatrix($version, $final);
    }

    /** Таблицы GF(256), полином 0x11D. */
    private static function gfTables(): array
    {
        static $log = null, $exp = null;
        if ($log === null) {
            $exp = array_fill(0, 512, 0); $log = array_fill(0, 256, 0);
            $x = 1;
            for ($i = 0; $i < 255; $i++) {
                $exp[$i] = $x; $log[$x] = $i;
                $x <<= 1; if ($x & 0x100) $x ^= 0x11D;
            }
            for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
        }
        return [$log, $exp];
    }

    /** Остаток от деления сообщения на генераторный многочлен степени $ecCount. */
    private static function reedSolomon(array $data, int $ecCount): array
    {
        [$log, $exp] = self::gfTables();
        // Генераторный многочлен: произведение (x - a^i), i = 0..ecCount-1
        // Коэффициенты хранятся по ВОЗРАСТАНИЮ степеней: gen[k] — коэффициент при x^k.
        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            for ($j = 0; $j < count($gen); $j++) {
                $next[$j] ^= $gen[$j];                                   // * 1
                $g = $gen[$j];                                           // * (-a^i) = a^i в GF(2)
                $next[$j + 1] ^= ($g === 0) ? 0 : $exp[($log[$g] + $i) % 255];
            }
            $gen = $next;
        }

        // Синтетическое деление (остаток по Горнеру).
        // rem[j] — текущий коэффициент при x^(ecCount-1-j) после обработки старших цифр.
        $rem = array_fill(0, $ecCount, 0);
        foreach ($data as $d) {
            $factor = $d ^ $rem[0];
            array_shift($rem); $rem[] = 0;
            if ($factor === 0) continue;
            $lf = $log[$factor];
            for ($j = 0; $j < $ecCount; $j++) {
                // gen-коэффициент при x^(ecCount-1-j) лежит в gen[ecCount-1-j]
                $gc = $gen[$ecCount - 1 - $j];
                if ($gc !== 0) $rem[$j] ^= $exp[($lf + $log[$gc]) % 255];
            }
        }
        return $rem;
    }

    private static function buildMatrix(int $version, array $codewords): array
    {
        $size = 17 + $version * 4;
        $m  = array_fill(0, $size, array_fill(0, $size, false));
        $fn = array_fill(0, $size, array_fill(0, $size, false)); // занятые ячейки

        // Finder patterns + сепараторы
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$oy, $ox]) {
            for ($y = 0; $y < 7; $y++) for ($x = 0; $x < 7; $x++) {
                $edge = $y === 0 || $y === 6 || $x === 0 || $x === 6;
                $core = $y >= 2 && $y <= 4 && $x >= 2 && $x <= 4;
                $m[$oy + $y][$ox + $x] = $edge || $core;
                $fn[$oy + $y][$ox + $x] = true;
            }
            for ($i = -1; $i <= 7; $i++) {
                self::setIfIn($m, $fn, $size, $oy - 1, $ox + $i, false);
                self::setIfIn($m, $fn, $size, $oy + 7, $ox + $i, false);
                self::setIfIn($m, $fn, $size, $oy + $i, $ox - 1, false);
                self::setIfIn($m, $fn, $size, $oy + $i, $ox + 7, false);
            }
        }
        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $m[6][$i] = $m[$i][6] = ($i % 2 === 0);
            $fn[6][$i] = $fn[$i][6] = true;
        }
        // Alignment patterns (пропускаются места пересечения с finder)
        if ($version >= 2) {
            $positions = self::alignmentPositions($version);
            foreach ($positions as $py) foreach ($positions as $px) {
                if (($py === 6 && $px === 6) || ($py === 6 && $px === $size - 7) || ($py === $size - 7 && $px === 6)) continue;
                for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) {
                    $a = max(abs($dy), abs($dx));
                    $m[$py + $dy][$px + $dx] = $a !== 1;
                    $fn[$py + $dy][$px + $dx] = true;
                }
            }
        }
        // Version info (версии 7–10): BCH(18,6), полином 0x1F25, позиции 6x3 у TR и BL
        if ($version >= 7) {
            $rem = $version << 12;
            for ($i = 17; $i >= 12; $i--) {
                if ((($rem >> $i) & 1) === 1) $rem ^= 0x1F25 << ($i - 12);
            }
            $vbits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($vbits >> $i) & 1) === 1;
                $vx = $size - 11 + ($i % 3);
                $vy = intdiv($i, 3);
                $m[$vy][$vx] = $bit; $fn[$vy][$vx] = true;      // у правого верхнего finder
                $m[$vx][$vy] = $bit; $fn[$vx][$vy] = true;      // у левого нижнего finder
            }
        }
        // Резерв зоны формата информации
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) { $fn[8][$i] = true; $fn[$i][8] = true; }
        }
        for ($i = 0; $i < 8; $i++) { $fn[8][$size - 1 - $i] = true; $fn[$size - 1 - $i][8] = true; }
        $m[$size - 8][8] = true; $fn[$size - 8][8] = true; // тёмный модуль

        // Размещение кодовых слов: зигзаг попарно, справа налево, снизу вверх, col 6 пропуск
        $bitStream = '';
        foreach ($codewords as $cw) $bitStream .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        $bi = 0; $up = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col--;
            for ($cnt = 0; $cnt < $size; $cnt++) {
                $row = $up ? $size - 1 - $cnt : $cnt;
                for ($c = 0; $c < 2; $c++) {
                    $cc = $col - $c;
                    if (!$fn[$row][$cc]) {
                        $m[$row][$cc] = ($bi < strlen($bitStream)) && ($bitStream[$bi++] === '1');
                    }
                }
            }
            $up = !$up;
        }

        // Выбор лучшей маски (штрафы правил 1–4)
        $best = null; $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $mm = self::applyMask($m, $fn, $size, $mask);
            self::drawFormat($mm, $size, $mask);
            $score = self::penalty($mm, $size);
            if ($score < $bestScore) { $bestScore = $score; $best = $mm; }
        }
        return $best;
    }

    private static function setIfIn(&$m, &$fn, int $size, int $y, int $x, bool $v): void
    {
        if ($y >= 0 && $y < $size && $x >= 0 && $x < $size) { $m[$y][$x] = $v; $fn[$y][$x] = true; }
    }

    /** Позиции центров выравнивающих паттернов (ISO 18004, Таблица E.2), версии 2..10. */
    private static function alignmentPositions(int $version): array
    {
        return match ($version) {
            2 => [6, 18],  3 => [6, 22],  4 => [6, 26],  5 => [6, 32],  6 => [6, 36],
            7 => [6, 22, 38],  8 => [6, 24, 42],  9 => [6, 26, 46], 10 => [6, 28, 50],
            default => [],
        };
    }

    private static function maskBit(int $mask, int $y, int $x): bool
    {
        return match ($mask) {
            0 => ($y + $x) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($y + $x) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => (($y * $x) % 2) + (($y * $x) % 3) === 0,
            6 => ((($y * $x) % 2) + (($y * $x) % 3)) % 2 === 0,
            default => (((($y + $x) % 2) + (($y * $x) % 3)) % 2) === 0,
        };
    }

    private static function applyMask(array $m, array $fn, int $size, int $mask): array
    {
        for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++) {
            if (!$fn[$y][$x] && self::maskBit($mask, $y, $x)) $m[$y][$x] = !$m[$y][$x];
        }
        return $m;
    }

    /** Формат информации: EC level M (00) + маска (3 бита), BCH(15,5), XOR 0x5412. */
    private static function drawFormat(array &$m, int $size, int $mask): void
    {
        $data = (0b00 << 3) | $mask;
        $rem = $data << 10;
        $gen = 0b10100110111;
        for ($i = 14; $i >= 10; $i--) {
            if ((($rem >> $i) & 1) === 1) $rem ^= $gen << ($i - 10);
        }
        $fmt = (($data << 10) | $rem) ^ 0x5412;
        for ($i = 0; $i < 15; $i++) {
            $bit = (($fmt >> $i) & 1) === 1;
            if ($i < 6) $m[8][$i] = $bit;
            elseif ($i === 6) $m[8][7] = $bit;
            elseif ($i === 7) $m[8][8] = $bit;
            elseif ($i === 8) $m[7][8] = $bit;
            else $m[14 - $i][8] = $bit;

            if ($i < 8) $m[$size - 1 - $i][8] = $bit;
            else $m[8][$size - 15 + $i] = $bit;
        }
        $m[$size - 8][8] = true; // тёмный модуль остаётся чёрным
    }

    /** Штраф маски, правила 1–4 ISO/IEC 18004. */
    private static function penalty(array $m, int $size): int
    {
        $p = 0;
        // Правило 1: серии ≥5 одинаковых модулей (строки и столбцы)
        for ($line = 0; $line < $size; $line++) {
            foreach ([true, false] as $horizontal) {
                $run = 1;
                for ($i = 1; $i < $size; $i++) {
                    $cur  = $horizontal ? $m[$line][$i] : $m[$i][$line];
                    $prev = $horizontal ? $m[$line][$i - 1] : $m[$i - 1][$line];
                    if ($cur === $prev) { $run++; if ($run === 5) $p += 3; elseif ($run > 5) $p++; }
                    else $run = 1;
                }
            }
        }
        // Правило 2: блоки 2x2 одного цвета
        for ($y = 0; $y < $size - 1; $y++) for ($x = 0; $x < $size - 1; $x++) {
            $v = $m[$y][$x];
            if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) $p += 3;
        }
        // Правило 3: паттерн 10111010000 / 00001011101 в строках и столбцах
        $patA = [true, false, true, true, true, false, true, false, false, false, false];
        $patB = [false, false, false, false, true, false, true, true, true, false, true];
        for ($line = 0; $line < $size; $line++) {
            for ($i = 0; $i + 11 <= $size; $i++) {
                $okA = $okB = $okAv = $okBv = true;
                for ($k = 0; $k < 11; $k++) {
                    $h = $m[$line][$i + $k]; $v = $m[$i + $k][$line];
                    if ($h !== $patA[$k]) $okA = false;
                    if ($h !== $patB[$k]) $okB = false;
                    if ($v !== $patA[$k]) $okAv = false;
                    if ($v !== $patB[$k]) $okBv = false;
                }
                if ($okA) $p += 40;
                if ($okB) $p += 40;
                if ($okAv) $p += 40;
                if ($okBv) $p += 40;
            }
        }
        // Правило 4: отклонение доли тёмных модулей от 50%
        $dark = 0;
        foreach ($m as $row) foreach ($row as $cell) if ($cell) $dark++;
        $ratio = $dark * 100 / ($size * $size);
        $p += (int)(abs($ratio - 50) / 5) * 10;
        return $p;
    }

    /** SVG-разметка QR (для страницы подключения 2FA и печати). */
    public static function svg(string $data, int $module = 4, int $quiet = 4): string
    {
        $m = self::encode($data);
        $n = count($m);
        $dim = ($n + $quiet * 2) * $module;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim
             . '" viewBox="0 0 ' . ($n + $quiet * 2) . ' ' . ($n + $quiet * 2) . '" shape-rendering="crispEdges">';
        $svg .= '<rect width="100%" height="100%" fill="#fff"/>';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if (!empty($m[$y][$x])) {
                    $svg .= '<rect x="' . ($x + $quiet) . '" y="' . ($y + $quiet) . '" width="1" height="1" fill="#000"/>';
                }
            }
        }
        return $svg . '</svg>';
    }
}
