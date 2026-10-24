<?php
namespace Vnstat;

class ChartSeries
{
    /**
     * Reshapes entries into the three parallel arrays a chart needs.
     *
     * @param  iterable<Entry> $entries
     * @param  callable(Entry): string $label
     * @return array{labels: string[], received: int[], sent: int[]}
     */
    public static function from(iterable $entries, callable $label): array
    {
        $labels = [];
        $received = [];
        $sent = [];

        foreach ($entries as $entry) {
            $labels[] = $label($entry);
            $received[] = $entry->getBytesReceived();
            $sent[] = $entry->getBytesSent();
        }

        return ['labels' => $labels, 'received' => $received, 'sent' => $sent];
    }
}
