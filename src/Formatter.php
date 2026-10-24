<?php
namespace Vnstat;

class Formatter
{
    private const BYTE_UNITS = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    // vnStat defaults to RateUnitMode 1, which is SI decimal prefixes over
    // powers of 1000. These are the strings its own output uses.
    private const BITRATE_UNITS = ['bit/s', 'kbit/s', 'Mbit/s', 'Gbit/s', 'Tbit/s'];

    public static function bytes(int $bytes): string
    {
        $index = self::unitIndex($bytes, count(self::BYTE_UNITS), 1024);

        // A count of bytes has no fractional part to show.
        $value = 0 === $index ? (string) $bytes : self::threeSignificantFigures($bytes / pow(1024, $index));

        return $value . ' ' . self::BYTE_UNITS[$index];
    }

    public static function bitrate(int $bytes, int $seconds): string
    {
        $bits = ($bytes * 8) / $seconds;
        $index = self::unitIndex($bits, count(self::BITRATE_UNITS), 1000);

        return self::threeSignificantFigures($bits / pow(1000, $index)) . ' ' . self::BITRATE_UNITS[$index];
    }

    public static function ratio(int $bytesReceived, int $bytesSent): string
    {
        $total = $bytesReceived + $bytesSent;

        // vnStat records an hour with no traffic at all, and a bar split
        // between two shares of nothing would claim it was all sent.
        if (0 === $total) {
            return '';
        }

        return sprintf('<div class="ratio"><div style="width: %f%%;"></div></div>', $bytesReceived / $total * 100);
    }

    /**
     * Values under ten keep two decimals, under a hundred one, and above that
     * none, so a figure carries the same information whatever unit it landed
     * in.
     *
     * Rounding can carry a value into the next band, which then wants one
     * decimal fewer: 99.96 is 100, not 100.0.
     */
    private static function threeSignificantFigures(float $value): string
    {
        $rounded = round($value, self::decimals($value));

        return number_format($rounded, self::decimals($rounded), '.', '');
    }

    private static function decimals(float $value): int
    {
        return match (true) {
            $value >= 100 => 0,
            $value >= 10 => 1,
            default => 2,
        };
    }

    private static function unitIndex(int|float $value, int $unitCount, int $base): int
    {
        // A rate below one bit per second gives a negative exponent, which
        // would index past the start of the unit list.
        return (int) max(0, min(floor(($value ? log($value) : 0) / log($base)), $unitCount - 1));
    }
}
