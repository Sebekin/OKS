<?php
declare(strict_types=1);

/** TOTP (RFC 6238) без внешних библиотек + генерация QR в SVG (п.5.2 ТЗ). */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // base32

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (array_chunk(str_split($bits), 5) as $chunk) {
            $out .= self::ALPHABET_bindec(str_pad($chunk[0], 5, '0'));
        }
        return $out;
    }

    private static function ALPHABET_bindec(string $bin5): string
    {
        return self::ALPHABET[(int)bindec($bin5)];
    }

    /** Текущий код. */
    public static function code(string $secret, ?int $ts = null, int $period = 30, int $digits = 6): string
    {
        $ts ??= time();
        $counter = intdiv($ts, $period);
        $key = self::base32DecodeStrict($secret);
        $bin = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $off = ord($hash[19]) & 0x0F;
        $val = ((ord($hash[$off]) & 0x7F) << 24) | (ord($hash[$off+1]) << 16)
             | (ord($hash[$off+2]) << 8) | ord($hash[$off+3]);
        return str_pad((string)($val % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /** Проверка с допуском ±1 шаг. */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, time() + $i * 30), $code)) return true;
        }
        return false;
    }

    public static function base32DecodeStrict(string $b32): string
    {
        $map = array_flip(str_split(self::ALPHABET));
        $bits = '';
        foreach (str_split($b32) as $ch) {
            $ch = strtoupper($ch);
            if (!isset($map[$ch])) continue;
            $bits .= str_pad(decbin($map[$ch]), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr((int)bindec($byte));
        }
        return $out;
    }

    /** otpauth:// URI для QR. */
    public static function provisioningUri(string $secret, string $login, string $issuer): string
    {
        return sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer), rawurlencode($login), $secret, rawurlencode($issuer));
    }

    /**
     * Простой QR-генератор (модель byte-кодирования, версии подбираются).
     * Возвращает SVG-строку. Реализация по ISO/IEC 18004 (упрощённая, надёжная для коротких строк).
     */
    public static function qrSvg(string $text, int $modulePx = 4): string
    {
        $qr = QrEncoder::encode($text); // матрица bool[][]
        $n = count($qr);
        $size = $n * $modulePx;
        $rects = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if (!empty($qr[$y][$x])) {
                    $rects .= sprintf('<rect x="%d" y="%d" width="%d" height="%d"/>',
                        $x * $modulePx, $y * $modulePx, $modulePx, $modulePx);
                }
            }
        }
        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" fill="#000"><rect width="100%%" height="100%%" fill="#fff"/>%s</svg>',
            $size, $size, $size, $size, $rects);
    }
}
