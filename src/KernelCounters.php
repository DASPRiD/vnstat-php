<?php
namespace Vnstat;

use RuntimeException;

/**
 * Byte counters for one interface, as the kernel reports them.
 *
 * vnStat only writes its database every few minutes, and asking it for a
 * live rate means waiting out a sample window with the request held open.
 * The kernel exposes running totals that cost a file read, and a rate is the
 * difference between two of them.
 */
class KernelCounters
{
    private const DIRECTORY = '/sys/class/net';

    public function __construct(
        public readonly int $received,
        public readonly int $sent,
    ) {
    }

    /**
     * @throws RuntimeException if the interface is unknown or unreadable.
     */
    public static function read(string $interface): self
    {
        $interfaces = self::interfaces();

        // Linux always has at least a loopback here, so an empty list means
        // the directory could not be read rather than that nothing is up.
        if ([] === $interfaces) {
            throw new RuntimeException(
                sprintf('Cannot list %s. Check that PHP is allowed to read it.', self::DIRECTORY)
            );
        }

        if (!in_array($interface, $interfaces, true)) {
            throw new RuntimeException(sprintf('No interface named "%s".', $interface));
        }

        return new self(self::counter($interface, 'rx'), self::counter($interface, 'tx'));
    }

    private static function interfaces(): array
    {
        if (!is_readable(self::DIRECTORY)) {
            return [];
        }

        $names = scandir(self::DIRECTORY);

        if (false === $names) {
            return [];
        }

        // /sys/class/net also holds plain files such as bonding_masters,
        // which carry no statistics directory and are not interfaces.
        return array_filter(
            $names,
            static fn (string $name): bool => is_dir(sprintf('%s/%s/statistics', self::DIRECTORY, $name))
        );
    }

    private static function counter(string $interface, string $direction): int
    {
        $path = sprintf('%s/%s/statistics/%s_bytes', self::DIRECTORY, $interface, $direction);

        if (!is_readable($path)) {
            throw new RuntimeException(sprintf('Cannot read %s. Check that /sys is reachable.', $path));
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new RuntimeException(sprintf('Reading %s failed partway through.', $path));
        }

        return (int) trim($contents);
    }
}
