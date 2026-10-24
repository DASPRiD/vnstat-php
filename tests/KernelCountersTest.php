<?php
namespace Vnstat\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vnstat\KernelCounters;

class KernelCountersTest extends TestCase
{
    public function testReadsTheCountersTheKernelReportsForAnInterface(): void
    {
        $interface = $this->anInterface();
        $expected = $this->counterFile($interface, 'rx');

        $counters = KernelCounters::read($interface);

        // Traffic can arrive between the two reads, so the counter can only
        // be compared as a floor.
        self::assertGreaterThanOrEqual($expected, $counters->received);
        self::assertGreaterThanOrEqual($this->counterFile($interface, 'tx'), $counters->sent);
    }

    public static function unacceptableNames(): array
    {
        return [
            'one that does not exist' => ['nosuchif'],
            'empty' => [''],
            'the current directory' => ['.'],
            'the parent directory' => ['..'],
            'a traversal out of /sys' => ['../../../etc/passwd'],
            'a traversal back into a real interface' => ['lo/../lo'],
        ];
    }

    #[DataProvider('unacceptableNames')]
    public function testRefusesAnythingThatIsNotAListedInterface(string $name): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('No interface named "%s".', $name));

        KernelCounters::read($name);
    }

    private function anInterface(): string
    {
        $entries = is_readable('/sys/class/net') ? scandir('/sys/class/net') : false;

        if (false === $entries) {
            self::markTestSkipped('This system has no /sys/class/net to read.');
        }

        $names = array_values(
            array_filter($entries, static fn (string $name): bool => !str_starts_with($name, '.'))
        );

        if ([] === $names) {
            self::markTestSkipped('This system exposes no interfaces under /sys/class/net.');
        }

        return $names[0];
    }

    private function counterFile(string $interface, string $direction): int
    {
        return (int) trim(
            (string) file_get_contents(sprintf('/sys/class/net/%s/statistics/%s_bytes', $interface, $direction))
        );
    }
}
