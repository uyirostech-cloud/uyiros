<?php
namespace App\Core;

/**
 * Money handled as integer minor units internally; DECIMAL(14,2) strings at the boundary.
 * No float arithmetic anywhere.
 */
final class Money
{
    public const SCALE = 2;

    /** Parse "1,234.50" | 1234.5 | "1234.505" into minor units (paise/cents), half-up. */
    public static function toMinor(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $s = is_string($value) ? trim(str_replace([',', ' '], '', $value)) : (string) $value;
        if (!preg_match('/^-?\d*(\.\d+)?$/', $s) || $s === '' || $s === '-') {
            throw new \InvalidArgumentException('Invalid money value');
        }
        $neg = str_starts_with($s, '-');
        if ($neg) {
            $s = substr($s, 1);
        }
        [$int, $frac] = array_pad(explode('.', $s, 2), 2, '');
        $int = $int === '' ? '0' : $int;
        $frac = substr($frac . '000', 0, self::SCALE + 1);          // one extra digit for rounding
        $minor = (int) $int * 100 + (int) substr($frac, 0, 2);
        if ((int) $frac[2] >= 5) {
            $minor++;
        }
        return $neg ? -$minor : $minor;
    }

    /** Minor units -> DECIMAL string suitable for MySQL, e.g. 50050 -> "500.50". */
    public static function toDecimal(int $minor): string
    {
        $neg = $minor < 0;
        $minor = abs($minor);
        return ($neg ? '-' : '') . intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Normalize any incoming value to a DECIMAL string. */
    public static function decimal(mixed $value): string
    {
        return self::toDecimal(self::toMinor($value));
    }

    /** quantity (DECIMAL(12,3) as string) * unit price -> minor units, half-up. */
    public static function multiplyQuantity(mixed $quantity, mixed $unitPrice): int
    {
        $qtyThousandths = self::scaledInt($quantity, 3);
        $priceMinor     = self::toMinor($unitPrice);
        $product        = $qtyThousandths * $priceMinor;            // scale 10^3 * 10^2
        $sign           = $product < 0 ? -1 : 1;
        $product        = abs($product);
        $result         = intdiv($product, 1000);
        if ($product % 1000 >= 500) {
            $result++;
        }
        return $sign * $result;
    }

    /** amount * percent / 100 in minor units, half-up. */
    public static function percentOf(int $minor, mixed $percent): int
    {
        $pct = self::scaledInt($percent, 3);                        // percent with 3 dp
        $product = $minor * $pct;                                   // scale 10^3 of (minor*percent)
        $sign = $product < 0 ? -1 : 1;
        $product = abs($product);
        $divisor = 100 * 1000;
        $result = intdiv($product, $divisor);
        if (($product % $divisor) * 2 >= $divisor) {
            $result++;
        }
        return $sign * $result;
    }

    private static function scaledInt(mixed $value, int $scale): int
    {
        $s = is_string($value) ? trim(str_replace([',', ' '], '', $value)) : (string) ($value ?? '0');
        if ($s === '') {
            return 0;
        }
        if (!preg_match('/^-?\d*(\.\d+)?$/', $s)) {
            throw new \InvalidArgumentException('Invalid numeric value');
        }
        $neg = str_starts_with($s, '-');
        if ($neg) {
            $s = substr($s, 1);
        }
        [$int, $frac] = array_pad(explode('.', $s, 2), 2, '');
        $int = $int === '' ? '0' : $int;
        $frac = substr($frac . str_repeat('0', $scale + 1), 0, $scale + 1);
        $v = (int) $int * (10 ** $scale) + (int) substr($frac, 0, $scale);
        if ((int) $frac[$scale] >= 5) {
            $v++;
        }
        return $neg ? -$v : $v;
    }
}
