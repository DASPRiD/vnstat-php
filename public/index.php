<?php
$configFile = dirname(__DIR__) . '/config/config.php';
$legacyConfigFile = dirname(__DIR__) . '/config.php';

// Drop this, the "/config.php" line in .gitignore and the upgrade section in
// the README once the v2.0.0 tag is a year old.
if (file_exists($legacyConfigFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');

    exit(sprintf(
        "The configuration file has moved.\n\n%s\n",
        file_exists($configFile)
            ? 'Delete config.php; config/config.php already exists.'
            : 'Move config.php to config/config.php.'
    ));
}

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($configFile)) {
    $config = require $configFile;
} else {
    $config = [];
}

if (array_key_exists('interfaces', $config) && !empty($config['interfaces'])) {
    if (array_key_exists('interface', $_GET) && in_array($_GET['interface'], $config['interfaces'])) {
        $interface = $_GET['interface'];
    } else {
        $interface = $config['interfaces'][0];
    }
} else {
    $interface = null;
}

$reader = new Vnstat\Reader();

try {
    $database = new Vnstat\Database($reader->read($interface));
} catch (Throwable $error) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');

    exit($error->getMessage() . "\n");
}

$timezone = new DateTimeZone(date_default_timezone_get());

function formatBytes($bytes)
{
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes) . ' ' . $units[$pow];
}

function formatBitrate($bytes, $seconds)
{
    $units = ['bit', 'kbit', 'mbit', 'gbit', 'tbit'];
    $bits  = ($bytes * 8) / $seconds;
    $pow   = floor(($bits ? log($bits) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    $bits  /= (1 << (10 * $pow));

    return round($bits, 2) . ' ' . $units[$pow] . '/s';
}

function formatRatio($bytesReceived, $bytesSent)
{
    $total = $bytesReceived + $bytesSent;
    $percentageReceived = ($bytesReceived / $total * 100);

    return sprintf(
        '<div class="ratio"><div style="width: %f%%;"></div></div>',
        $percentageReceived
    );
}

$dayFormatter = new IntlDateFormatter(
    'en-GB',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    date_default_timezone_get()
);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        <title>Network Traffic</title>
        <link href="style.css" rel="stylesheet" />
        <link href="xcharts/xcharts.min.css" rel="stylesheet" />
        <script src="xcharts/d3.min.js"></script>
        <script src="xcharts/xcharts.min.js"></script>

        <style>
            /* xcharts hardcodes its palette for a white page. Thrown away
               together with xcharts. */
            g.received > rect { fill: var(--received) !important; }
            g.sent > rect { fill: var(--sent) !important; }
            .xchart .axis text { fill: var(--text-muted) !important; }
            .xchart .axis path,
            .xchart .axis .tick line { stroke: var(--border) !important; }
        </style>
    </head>
    <body>
        <header>
            <h1>Network Traffic</h1>

            <?php if (array_key_exists('interfaces', $config) && count($config['interfaces']) > 1): ?>
                <label>
                    Interface
                    <select onchange="window.location.href = '?interface=' + this.value;">
                        <?php foreach ($config['interfaces'] as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>"<?php if ($option === $interface): ?> selected<?php endif; ?>>
                                <?php echo htmlspecialchars($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </header>

        <main>

            <section aria-labelledby="hourly-heading">
                <h2 id="hourly-heading">Hourly</h2>
                <figure id="hourly-chart"></figure>
                <script type="text/javascript">
                    <?php
                    $receivedData = [
                        'className' => '.received',
                        'data'      => [],
                    ];

                    $sentData = [
                        'className' => '.sent',
                        'data'      => [],
                    ];

                    // The hour of day is a label, not the x value. On the spring
                    // transition a 24 slot window spans 23 wall clock hours, so two
                    // slots would share a label and the ordinal scale would collapse
                    // them onto one band.
                    $hourLabels = [];

                    foreach (Vnstat\TrafficWindow::hourly($database) as $position => $hour) {
                        $hourLabels[] = (int) $hour->getDateTime()->format('G');
                        $receivedData['data'][] = ['x' => $position, 'y' => $hour->getBytesReceived()];
                        $sentData['data'][] = ['x' => $position, 'y' => $hour->getBytesSent()];
                    }
                    ?>
                    var hourLabels = <?php echo json_encode($hourLabels); ?>;

                    var chart = new xChart(
                        'bar',
                        {
                            "xScale": "ordinal",
                            "yScale": "linear",
                            "type": "bar",
                            "main": [
                                <?php echo json_encode($receivedData); ?>,
                                <?php echo json_encode($sentData); ?>
                            ]
                        },
                        '#hourly-chart',
                        {
                            "tickHintX": -25,
                            "tickFormatX": function (x) {
                                return hourLabels[x];
                            },
                            "tickFormatY": function (y) {
                                var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
                                var pow   = Math.floor((y ? Math.log(y) : 0) / Math.log(1024));
                                pow = Math.min(pow, units.length - 1);

                                return (Math.round(y / (1 << (10 * pow)) * 10) / 10) + ' ' + units[pow];
                            },
                            "sortX": function (a, b) {
                                // This actually only works because we hacked the
                                // source of xcharts.min.js
                                return 0;
                            }
                        }
                    );
                </script>
            </section>

            <section aria-labelledby="daily-heading">
                <h2 id="daily-heading">Daily</h2>

                <div>
                    <table>
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Received</th>
                                <th>Sent</th>
                                <th>Total</th>
                                <th>Average Rate</th>
                                <th>Ratio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($database->getDays() as $entry): ?>
                                <?php
                                if (!$entry->isFilled()) {
                                    continue;
                                }

                                // This calculation has to be done because a day may
                                // have more or less than 24 hours (DST or leapseconds).
                                $diffDate = clone $entry->getDateTime();
                                $diffDate->setTimezone($timezone);
                                $diffDate->setTime(0, 0, 0);
                                $startTimestamp = $diffDate->getTimestamp();
                                $diffDate->setTime(23, 59, 59);
                                $endTimestamp = $diffDate->getTimestamp();
                                $range = $endTimestamp - $startTimestamp;
                                ?>
                                <tr>
                                    <th scope="row"><?php echo $dayFormatter->format($entry->getDateTime()); ?></th>
                                    <td><?php echo formatBytes($entry->getBytesReceived()); ?></td>
                                    <td><?php echo formatBytes($entry->getBytesSent()); ?></td>
                                    <td><?php echo formatBytes($entry->getBytesReceived() + $entry->getBytesSent()); ?></td>
                                    <td><?php echo formatBitrate($entry->getBytesReceived() + $entry->getBytesSent(), $range); ?></td>
                                    <td><?php echo formatRatio($entry->getBytesReceived(), $entry->getBytesSent()); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="monthly-heading">
                <h2 id="monthly-heading">Monthly</h2>

                <div>
                    <table>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Received</th>
                                <th>Sent</th>
                                <th>Total</th>
                                <th>Average Rate</th>
                                <th>Ratio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($database->getMonths() as $entry): ?>
                                <?php
                                if (!$entry->isFilled()) {
                                    continue;
                                }

                                $entry->getDateTime()->setTimeZone($timezone);

                                // And again we can't just multiply the number of days
                                // by the number of normal seconds to be accurate.
                                $diffDate = clone $entry->getDateTime();
                                $diffDate->setTimezone($timezone);
                                $diffDate->setTime(0, 0, 0);
                                $diffDate->modify('first day of');
                                $startTimestamp = $diffDate->getTimestamp();
                                $diffDate->modify('last day of');
                                $diffDate->setTime(23, 59, 59);
                                $endTimestamp = $diffDate->getTimestamp();
                                $range = $endTimestamp - $startTimestamp;
                                ?>
                                <tr>
                                    <th scope="row"><?php echo $entry->getDateTime()->format('F Y'); ?></th>
                                    <td><?php echo formatBytes($entry->getBytesReceived()); ?></td>
                                    <td><?php echo formatBytes($entry->getBytesSent()); ?></td>
                                    <td><?php echo formatBytes($entry->getBytesReceived() + $entry->getBytesSent()); ?></td>
                                    <td><?php echo formatBitrate($entry->getBytesReceived() + $entry->getBytesSent(), $range); ?></td>
                                    <td><?php echo formatRatio($entry->getBytesReceived(), $entry->getBytesSent()); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="top-10-heading">
                <h2 id="top-10-heading">Top 10</h2>

                <div>
                    <table>
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Received</th>
                                <th>Sent</th>
                                <th>Total</th>
                                <th>Average Rate</th>
                                <th>Ratio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($database->getTop10() as $position => $entry): ?>
                                <?php
                                if (!$entry->isFilled()) {
                                    continue;
                                }

                                // This calculation has to be done because a day may
                                // have more or less than 24 hours (DST or leapseconds).
                                $diffDate = clone $entry->getDateTime();
                                $diffDate->setTimezone($timezone);
                                $diffDate->setTime(0, 0, 0);
                                $startTimestamp = $diffDate->getTimestamp();
                                $diffDate->setTime(23, 59, 59);
                                $endTimestamp = $diffDate->getTimestamp();
                                $range = $endTimestamp - $startTimestamp;
                                ?>
                                <tr>
                                    <th scope="row"><?php echo $position + 1; ?>. <?php echo $dayFormatter->format($entry->getDateTime()); ?></th>
                                    <td><?php echo formatBytes($entry->getBytesReceived()); ?></td>
                                    <td><?php echo formatBytes($entry->getBytesSent()); ?></td>
                                    <td><?php echo formatBytes($entry->getBytesReceived() + $entry->getBytesSent()); ?></td>
                                    <td><?php echo formatBitrate($entry->getBytesReceived() + $entry->getBytesSent(), $range); ?></td>
                                    <td><?php echo formatRatio($entry->getBytesReceived(), $entry->getBytesSent()); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </body>
</html>
