<?php
namespace Vnstat\Test;

use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vnstat\Database;
use Vnstat\Entry;

class DatabaseTest extends TestCase
{
    private const MIB = 1048576;

    /**
     * Both fixtures hold the same synthetic traffic, so every assertion below
     * must hold for either one. vnStat 2.6 predates the "timestamp" field that
     * 2.10 added, which is the difference the pairing exists to cover.
     */
    public static function fixtures(): array
    {
        return [
            'vnStat 2.13, entries carry a timestamp' => ['vnstat-2.13.json', '2.13'],
            'vnStat 2.6, entries carry no timestamp' => ['vnstat-2.6.json', '2.6'],
        ];
    }

    #[DataProvider('fixtures')]
    public function testParsesInterfaceMetadata(string $fixture, string $version): void
    {
        $database = $this->loadFixture($fixture);

        self::assertSame($version, $database->getVersion());
        self::assertSame('eth0', $database->getInterface());
        self::assertSame('Uplink', $database->getNick());
        self::assertSame('2025-09-02 00:00', $database->getCreatedAt()->format('Y-m-d H:i'));
        self::assertSame('2026-03-15 14:37', $database->getUpdatedAt()->format('Y-m-d H:i'));
    }

    #[DataProvider('fixtures')]
    public function testParsesLifetimeTotals(string $fixture): void
    {
        $database = $this->loadFixture($fixture);

        self::assertSame(4294967296000, $database->getTotalBytesReceived());
        self::assertSame(1073741824000, $database->getTotalBytesSent());
    }

    #[DataProvider('fixtures')]
    public function testParsesDailyEntries(string $fixture): void
    {
        $days = $this->filledEntriesOldestFirst($this->loadFixture($fixture)->getDays());

        self::assertCount(30, $days);

        $first = array_shift($days);
        self::assertSame('2026-02-14 00:00', $first->getDateTime()->format('Y-m-d H:i'));
        self::assertSame(400 * self::MIB, $first->getBytesReceived());
        self::assertSame(90 * self::MIB, $first->getBytesSent());

        $last = array_pop($days);
        self::assertSame('2026-03-15 00:00', $last->getDateTime()->format('Y-m-d H:i'));
    }

    #[DataProvider('fixtures')]
    public function testNormalizesMonthlyEntriesToTheFirstOfTheMonth(string $fixture): void
    {
        $months = $this->filledEntriesOldestFirst($this->loadFixture($fixture)->getMonths());

        self::assertCount(6, $months);

        $first = array_shift($months);
        self::assertSame('2025-10-01 00:00', $first->getDateTime()->format('Y-m-d H:i'));
        self::assertSame(18000 * self::MIB, $first->getBytesReceived());
        self::assertSame(4200 * self::MIB, $first->getBytesSent());
    }

    #[DataProvider('fixtures')]
    public function testParsesTopEntriesInRankOrder(string $fixture): void
    {
        $top = $this->filledEntries($this->loadFixture($fixture)->getTop10());

        self::assertCount(10, $top);

        $first = array_shift($top);
        self::assertSame('2026-03-12 00:00', $first->getDateTime()->format('Y-m-d H:i'));
        self::assertSame(2600 * self::MIB, $first->getBytesReceived());
        self::assertSame(560 * self::MIB, $first->getBytesSent());
    }

    #[DataProvider('fixtures')]
    public function testParsesHourlyEntries(string $fixture): void
    {
        $hours = $this->filledEntriesOldestFirst($this->loadFixture($fixture)->getHours());

        self::assertCount(24, $hours);

        $first = array_shift($hours);
        self::assertSame('2026-03-14 15:00', $first->getDateTime()->format('Y-m-d H:i'));
        self::assertSame(12 * self::MIB, $first->getBytesReceived());
        self::assertSame(3 * self::MIB, $first->getBytesSent());

        $last = array_pop($hours);
        self::assertSame('2026-03-15 14:00', $last->getDateTime()->format('Y-m-d H:i'));
    }

    public function testRejectsOutputThatIsNotJson(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error: Failed to open database');

        new Database("Error: Failed to open database \"/var/lib/vnstat/vnstat.db\"\n");
    }

    public function testTruncatesOverlongOutputInTheErrorMessage(): void
    {
        try {
            new Database(str_repeat('x', 3000));
            self::fail('Expected an exception');
        } catch (Exception $error) {
            self::assertLessThan(600, strlen($error->getMessage()));
            self::assertStringEndsWith('...', $error->getMessage());
        }
    }

    public function testRejectsTheVnStat1Schema(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported vnStat JSON schema version 1');

        new Database('{"jsonversion":"1","vnstatversion":"1.18","interfaces":[]}');
    }

    public function testRejectsANewerSchemaVersion(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported vnStat JSON schema version 3');

        new Database('{"jsonversion":"3","vnstatversion":"3.0","interfaces":[]}');
    }

    public function testRejectsANonScalarSchemaVersionWithoutWarning(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported vnStat JSON schema version array');

        new Database('{"jsonversion":["2"],"interfaces":[]}');
    }

    public function testRejectsADatabaseWithoutInterfaces(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no interfaces');

        new Database('{"jsonversion":"2","vnstatversion":"2.13","interfaces":[]}');
    }

    private function loadFixture(string $fixture): Database
    {
        return new Database(file_get_contents(__DIR__ . '/fixtures/' . $fixture));
    }

    private function filledEntries(array $entries): array
    {
        return array_values(array_filter($entries, static fn (Entry $entry): bool => $entry->isFilled()));
    }

    private function filledEntriesOldestFirst(array $entries): array
    {
        $filled = $this->filledEntries($entries);

        usort(
            $filled,
            static fn (Entry $left, Entry $right): int => $left->getDateTime() <=> $right->getDateTime()
        );

        return $filled;
    }
}
