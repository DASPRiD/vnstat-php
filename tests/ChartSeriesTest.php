<?php
namespace Vnstat\Test;

use DateTime;
use PHPUnit\Framework\TestCase;
use Vnstat\ChartSeries;
use Vnstat\Entry;

class ChartSeriesTest extends TestCase
{
    public function testSplitsEntriesIntoParallelArrays(): void
    {
        $series = ChartSeries::from($this->entries(), static fn (Entry $entry): string => $entry->getDateTime()->format('G'));

        self::assertSame(['9', '10'], $series['labels']);
        self::assertSame([100, 300], $series['received']);
        self::assertSame([200, 400], $series['sent']);
    }

    public function testKeepsTheOrderItWasGiven(): void
    {
        $series = ChartSeries::from(array_reverse($this->entries()), static fn (Entry $entry): string => $entry->getDateTime()->format('G'));

        self::assertSame(['10', '9'], $series['labels']);
        self::assertSame([300, 100], $series['received']);
    }

    public function testReturnsEmptyArraysForNoEntries(): void
    {
        $series = ChartSeries::from([], static fn (Entry $entry): string => '');

        self::assertSame(['labels' => [], 'received' => [], 'sent' => []], $series);
    }

    private function entries(): array
    {
        return [
            new Entry(100, 200, true, new DateTime('2026-03-15 09:00:00')),
            new Entry(300, 400, true, new DateTime('2026-03-15 10:00:00')),
        ];
    }
}
