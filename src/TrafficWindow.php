<?php
namespace Vnstat;

use InvalidArgumentException;

class TrafficWindow
{
    /**
     * Returns the last $count hours of traffic, oldest first.
     *
     * The window ends with the hour of vnStat's last update. An hour vnStat
     * has no row for comes back as an unfilled zero entry, so that a gap
     * renders as a gap rather than shifting the series.
     *
     * @return Entry[]
     * @throws InvalidArgumentException if $count is less than one.
     */
    public static function hourly(Database $database, int $count = 24): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException(sprintf('A window needs at least one hour, got %d.', $count));
        }

        $byHour = [];

        foreach ($database->getHours() as $entry) {
            $byHour[$entry->getDateTime()->format('Y-m-d H')] = $entry;
        }

        // Both the update time and the entries come from vnStat in the same
        // local time, so anchoring here keeps the window where vnStat put it
        // even when PHP disagrees about the timezone.
        $slot = clone $database->getUpdatedAt();
        $slot->setTime((int) $slot->format('G'), 0, 0);
        $slot->modify(sprintf('-%d hours', $count - 1));

        $window = [];

        for ($position = 0; $position < $count; ++$position) {
            $key = $slot->format('Y-m-d H');
            $window[] = $byHour[$key] ?? new Entry(0, 0, false, clone $slot);
            $slot->modify('+1 hour');
        }

        return $window;
    }
}
