<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

final class Money
{
    public static function parse(string $raw, int $scale): int
    {
        $raw = trim($raw);
        if ($raw === '' || !preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException('INVALID_NUMBER');
        }
        if ($scale < 0 || $scale > 6) {
            throw new InvalidArgumentException('INVALID_SCALE');
        }
        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        if (strlen($whole) > 12) {
            throw new InvalidArgumentException('NUMBER_TOO_LARGE');
        }
        $fraction = str_pad($fraction, $scale + 1, '0');
        $keep = $scale === 0 ? '0' : substr($fraction, 0, $scale);
        $roundDigit = (int) $fraction[$scale];
        $unit = 10 ** $scale;
        $value = ((int) $whole) * $unit + (int) $keep;
        if ($roundDigit >= 5) {
            $value++;
        }
        if ($value > 2_000_000_000_000) {
            throw new InvalidArgumentException('NUMBER_TOO_LARGE');
        }
        return $negative ? -$value : $value;
    }

    public static function poisha(string $raw): int
    {
        return self::parse($raw, 2);
    }

    public static function milli(string $raw): int
    {
        return self::parse($raw, 3);
    }

    public static function formatPoisha(int $poisha): string
    {
        $negative = $poisha < 0;
        $poisha = abs($poisha);
        $whole = intdiv($poisha, 100);
        $fraction = $poisha % 100;
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', (string) $whole) ?? (string) $whole;
        return ($negative ? '-' : '') . $grouped . '.' . sprintf('%02d', $fraction);
    }

    public static function formatMilli(int $milli): string
    {
        $negative = $milli < 0;
        $milli = abs($milli);
        $whole = intdiv($milli, 1000);
        $fraction = $milli % 1000;
        $body = (string) $whole;
        if ($fraction !== 0) {
            $body .= '.' . rtrim(sprintf('%03d', $fraction), '0');
        }
        return ($negative ? '-' : '') . $body;
    }

    public static function plainPoisha(int $poisha): string
    {
        $negative = $poisha < 0;
        $poisha = abs($poisha);
        return ($negative ? '-' : '') . intdiv($poisha, 100) . '.' . sprintf('%02d', $poisha % 100);
    }

    public static function plainMilli(int $milli): string
    {
        $negative = $milli < 0;
        $milli = abs($milli);
        $whole = intdiv($milli, 1000);
        $fraction = $milli % 1000;
        if ($fraction === 0) {
            return ($negative ? '-' : '') . (string) $whole;
        }
        return ($negative ? '-' : '') . $whole . '.' . rtrim(sprintf('%03d', $fraction), '0');
    }

    /**
     * Largest-remainder friendly integer share: round(amount * part / whole).
     */
    public static function share(int $part, int $whole, int $amount): int
    {
        if ($whole <= 0 || $part <= 0 || $amount === 0) {
            return 0;
        }
        if ($part > $whole) {
            $part = $whole;
        }
        $negative = $amount < 0;
        $amount = abs($amount);
        $shared = intdiv($amount * $part + intdiv($whole, 2), $whole);
        return $negative ? -$shared : $shared;
    }
}
