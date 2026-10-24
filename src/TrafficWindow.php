<?php
namespace Vnstat;

use DateTime;
use DateTimeZone;
use InvalidArgumentException;

class TrafficWindow
{
    /**
     * Returns the last $count hours of traffic, oldest first.
     *
     * @return Entry[]
     * @throws InvalidArgumentException if $count is less than one.
     */
    public static function hourly(Database $database, int $count = 24): array
    {
        $anchor = clone $database->getUpdatedAt();
        $anchor->setTime((int) $anchor->format('G'), 0, 0);

        return self::build($database->getHours(), $anchor, stepMinutes: 60, count: $count);
    }

    /**
     * Returns the last $count five minute slots of traffic, oldest first.
     *
     * @return Entry[]
     * @throws InvalidArgumentException if $count is less than one.
     */
    public static function fiveMinute(Database $database, int $count = 36): array
    {
        $anchor = clone $database->getUpdatedAt();
        $minute = (int) $anchor->format('i');
        $anchor->setTime((int) $anchor->format('G'), $minute - $minute % 5, 0);

        return self::build($database->getFiveMinutes(), $anchor, stepMinutes: 5, count: $count);
    }

    /**
     * Walks back $count slots from $anchor and matches each against $entries.
     *
     * A slot vnStat has no row for comes back as an unfilled zero entry, so
     * that a gap renders as a gap rather than shifting the series.
     *
     * $anchor has to come from vnStat's own update time. Both it and the
     * entries are then in the same local time, so the window stays where
     * vnStat put it even when PHP disagrees about the timezone.
     *
     * Slots are spaced in elapsed time rather than on the clock. Stepping by
     * wall clock walks into the hour that the spring transition removes,
     * which PHP resolves by moving an hour the other way.
     *
     * @param  iterable<Entry> $entries
     * @return Entry[]
     * @throws InvalidArgumentException if $count is less than one.
     */
    private static function build(iterable $entries, DateTime $anchor, int $stepMinutes, int $count): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException(sprintf('A window needs at least one slot, got %d.', $count));
        }

        $byTime = [];

        foreach ($entries as $entry) {
            $byTime[$entry->getDateTime()->getTimestamp()] = $entry;
        }

        $timezone = $anchor->getTimezone();
        $step = $stepMinutes * 60;
        $window = [];

        for ($position = $count - 1; $position >= 0; --$position) {
            $at = $anchor->getTimestamp() - $position * $step;
            $window[] = $byTime[$at] ?? new Entry(0, 0, false, self::slotAt($at, $timezone));
        }

        return $window;
    }

    private static function slotAt(int $timestamp, DateTimeZone $timezone): DateTime
    {
        $slot = new DateTime('@' . $timestamp);
        $slot->setTimezone($timezone);

        return $slot;
    }
}
