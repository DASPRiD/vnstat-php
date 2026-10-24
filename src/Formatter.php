<?php
namespace Vnstat;

class Formatter
{
    private const BYTE_UNITS = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    private const BITRATE_UNITS = ['bit', 'kbit', 'mbit', 'gbit', 'tbit'];

    public static function bytes(int $bytes): string
    {
        $index = self::unitIndex($bytes, count(self::BYTE_UNITS));

        return round($bytes / pow(1024, $index)) . ' ' . self::BYTE_UNITS[$index];
    }

    public static function bitrate(int $bytes, int $seconds): string
    {
        $bits = ($bytes * 8) / $seconds;
        $index = self::unitIndex($bits, count(self::BITRATE_UNITS));

        return round($bits / pow(1024, $index), 2) . ' ' . self::BITRATE_UNITS[$index] . '/s';
    }

    public static function ratio(int $bytesReceived, int $bytesSent): string
    {
        $percentageReceived = $bytesReceived / ($bytesReceived + $bytesSent) * 100;

        return sprintf('<div class="ratio"><div style="width: %f%%;"></div></div>', $percentageReceived);
    }

    private static function unitIndex(int|float $value, int $unitCount): int
    {
        // A rate below one bit per second gives a negative exponent, which
        // would index past the start of the unit list.
        return (int) max(0, min(floor(($value ? log($value) : 0) / log(1024)), $unitCount - 1));
    }
}
