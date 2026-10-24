<?php
namespace Vnstat\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vnstat\Formatter;

class FormatterTest extends TestCase
{
    public static function byteValues(): array
    {
        return [
            'zero' => [0, '0 B'],
            'one byte' => [1, '1 B'],
            'the last value below a kibibyte' => [1023, '1023 B'],
            'the first kibibyte' => [1024, '1 KiB'],
            'one and a half kibibytes round up' => [1536, '2 KiB'],
            'rounding can reach the next unit' => [1048575, '1024 KiB'],
            'a mebibyte' => [1048576, '1 MiB'],
            'a gibibyte' => [1073741824, '1 GiB'],
            'a tebibyte' => [1099511627776, '1 TiB'],
            'beyond the largest unit it keeps counting' => [2251799813685248, '2048 TiB'],
        ];
    }

    #[DataProvider('byteValues')]
    public function testFormatsByteCounts(int $bytes, string $expected): void
    {
        self::assertSame($expected, Formatter::bytes($bytes));
    }

    public static function bitrateValues(): array
    {
        return [
            'no traffic over a day' => [0, 86400, '0 bit/s'],
            'a rate below one bit per second' => [1, 86400, '0 bit/s'],
            'a mebibyte in a second' => [1048576, 1, '8.39 Mbit/s'],
            'a mebibyte over a day' => [1048576, 86400, '97.09 bit/s'],
            'a gibibyte over a day' => [1073741824, 86400, '99.42 kbit/s'],
            'a tebibyte in a second' => [1099511627776, 1, '8.8 Tbit/s'],
            'the decimal boundary' => [125, 1, '1 kbit/s'],
        ];
    }

    #[DataProvider('bitrateValues')]
    public function testFormatsBitrates(int $bytes, int $seconds, string $expected): void
    {
        self::assertSame($expected, Formatter::bitrate($bytes, $seconds));
    }

    public function testSizesTheRatioBarByTheReceivedShare(): void
    {
        self::assertStringContainsString('width: 75.000000%', Formatter::ratio(3, 1));
    }

    public function testGivesTheRatioBarNoWidthWhenNothingWasReceived(): void
    {
        self::assertStringContainsString('width: 0.000000%', Formatter::ratio(0, 4));
    }

    public function testDrawsNoRatioBarForAnEntryWithNoTraffic(): void
    {
        self::assertSame('', Formatter::ratio(0, 0));
    }
}
