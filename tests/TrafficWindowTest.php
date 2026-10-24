<?php
namespace Vnstat\Test;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vnstat\Database;
use Vnstat\Entry;
use Vnstat\TrafficWindow;

class TrafficWindowTest extends TestCase
{
    private const MIB = 1048576;

    public function testReturnsTwentyFourHoursOldestFirstEndingAtTheLastUpdate(): void
    {
        // The fixture was last updated at 2026-03-15 14:37.
        $window = TrafficWindow::hourly($this->databaseFromFixture());

        self::assertCount(24, $window);
        self::assertSame('2026-03-14 15:00', $this->times($window)[0]);
        self::assertSame('2026-03-15 14:00', $this->times($window)[23]);
    }

    public function testCarriesTheTrafficOfEveryMatchedHour(): void
    {
        $window = TrafficWindow::hourly($this->databaseFromFixture());

        self::assertSame(12 * self::MIB, $window[0]->getBytesReceived());
        self::assertSame(3 * self::MIB, $window[0]->getBytesSent());
        self::assertTrue($window[0]->isFilled());
    }

    public function testMarksAnHourVnStatHasNoRowForAsUnfilled(): void
    {
        $window = TrafficWindow::hourly($this->databaseWithHours([10 => 5, 12 => 7], '2026-03-15 12:37'), 4);

        self::assertSame([false, true, false, true], $this->filledFlags($window));
    }

    public function testReportsZeroTrafficForAnHourVnStatHasNoRowFor(): void
    {
        $window = TrafficWindow::hourly($this->databaseWithHours([10 => 5, 12 => 7], '2026-03-15 12:37'), 4);

        self::assertSame([0, 5 * self::MIB, 0, 7 * self::MIB], $this->receivedBytes($window));
    }

    public function testDropsEntriesOlderThanTheWindow(): void
    {
        $database = $this->databaseWithHours([9 => 1, 10 => 2, 11 => 3, 12 => 4], '2026-03-15 12:00');

        $window = TrafficWindow::hourly($database, 2);

        self::assertCount(2, $window);
        self::assertSame([3 * self::MIB, 4 * self::MIB], $this->receivedBytes($window));
    }

    public function testCrossesMidnightWithoutReorderingTheWindow(): void
    {
        $database = $this->databaseWithHours([22 => 1, 23 => 2], '2026-03-14 23:10');

        $window = TrafficWindow::hourly($database, 4);

        self::assertSame(
            ['2026-03-14 20:00', '2026-03-14 21:00', '2026-03-14 22:00', '2026-03-14 23:00'],
            $this->times($window)
        );
    }

    public function testRefusesAWindowWithNoHoursInIt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A window needs at least one hour, got 0.');

        TrafficWindow::hourly($this->databaseFromFixture(), 0);
    }

    private function databaseFromFixture(): Database
    {
        return new Database((string) file_get_contents(__DIR__ . '/fixtures/vnstat-2.13.json'));
    }

    /**
     * @param array<int, int> $hours Hour of day mapped to received MiB.
     */
    private function databaseWithHours(array $hours, string $updated): Database
    {
        $updatedAt = new DateTimeImmutable($updated);
        $date = [
            'year' => (int) $updatedAt->format('Y'),
            'month' => (int) $updatedAt->format('n'),
            'day' => (int) $updatedAt->format('j'),
        ];
        $entries = [];
        $rowId = 5000;

        foreach ($hours as $hour => $received) {
            $entries[] = [
                'id' => ++$rowId,
                'date' => $date,
                'time' => ['hour' => $hour, 'minute' => 0],
                'rx' => $received * self::MIB,
                'tx' => $received * self::MIB,
            ];
        }

        return new Database((string) json_encode([
            'vnstatversion' => '2.13',
            'jsonversion' => '2',
            'interfaces' => [[
                'name' => 'eth0',
                'alias' => '',
                'created' => ['date' => $date],
                'updated' => [
                    'date' => $date,
                    'time' => [
                        'hour' => (int) $updatedAt->format('G'),
                        'minute' => (int) $updatedAt->format('i'),
                    ],
                ],
                'traffic' => [
                    'total' => ['rx' => 0, 'tx' => 0],
                    'day' => [],
                    'month' => [],
                    'top' => [],
                    'hour' => $entries,
                ],
            ]],
        ]));
    }

    /**
     * @param  Entry[] $window
     * @return string[]
     */
    private function times(array $window): array
    {
        return array_map(static fn (Entry $entry): string => $entry->getDateTime()->format('Y-m-d H:i'), $window);
    }

    /**
     * @param  Entry[] $window
     * @return bool[]
     */
    private function filledFlags(array $window): array
    {
        return array_map(static fn (Entry $entry): bool => $entry->isFilled(), $window);
    }

    /**
     * @param  Entry[] $window
     * @return int[]
     */
    private function receivedBytes(array $window): array
    {
        return array_map(static fn (Entry $entry): int => $entry->getBytesReceived(), $window);
    }
}
