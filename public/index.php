<?php
$configFile = dirname(__DIR__) . '/config/config.php';
$legacyConfigFile = dirname(__DIR__) . '/config.php';

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

/**
 * Appends the file's modification time to an asset URL.
 *
 * The result is escaped for use in an attribute.
 */
function asset(string $path): string
{
    $absolutePath = __DIR__ . '/' . $path;

    return htmlspecialchars($path . '?v=' . (file_exists($absolutePath) ? filemtime($absolutePath) ?: 0 : 0));
}

$dayFormatter = new IntlDateFormatter(
    'en-GB',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    date_default_timezone_get()
);

$shortDayFormatter = new IntlDateFormatter(
    'en-GB',
    IntlDateFormatter::NONE,
    IntlDateFormatter::NONE,
    date_default_timezone_get(),
    null,
    'd MMM'
);

$shortMonthFormatter = new IntlDateFormatter(
    'en-GB',
    IntlDateFormatter::NONE,
    IntlDateFormatter::NONE,
    date_default_timezone_get(),
    null,
    'MMM yyyy'
);

$charts = [
    'hourly' => Vnstat\ChartSeries::from(
        Vnstat\TrafficWindow::hourly($database),
        static fn (Vnstat\Entry $entry): string => $entry->getDateTime()->format('G')
    ),
    'daily' => Vnstat\ChartSeries::from(
        $database->getDays(),
        static fn (Vnstat\Entry $entry): string => (string) $shortDayFormatter->format($entry->getDateTime())
    ),
    'monthly' => Vnstat\ChartSeries::from(
        $database->getMonths(),
        static fn (Vnstat\Entry $entry): string => (string) $shortMonthFormatter->format($entry->getDateTime())
    ),
];
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        <title>Network Traffic</title>

        <script>
            // Runs before the first paint, so a stored preference does not
            // show a frame of the other theme while app.js is still deferred.
            try {
                const theme = localStorage.getItem("vnstat-php-theme");

                if (theme === "light" || theme === "dark") {
                    document.documentElement.style.colorScheme = theme;
                }
            } catch {
                // Storage can be unavailable, in which case auto is right.
            }
        </script>

        <link href="<?php echo asset('style.css'); ?>" rel="stylesheet" />
        <script src="<?php echo asset('assets/chart.js'); ?>" defer></script>
        <script src="<?php echo asset('app.js'); ?>" defer></script>
    </head>
    <body>
        <header>
            <h1>Network Traffic</h1>

            <div>
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

                <label>
                    Theme
                    <select id="theme">
                        <option value="auto">Auto</option>
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                    </select>
                </label>
            </div>
        </header>

        <main>
            <script type="application/json" id="chart-data">
                <?php echo json_encode(
                    $charts,
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                ); ?>
            </script>

            <section aria-labelledby="hourly-heading">
                <h2 id="hourly-heading">Hourly</h2>
                <figure><canvas id="hourly-chart"></canvas></figure>
            </section>

            <section aria-labelledby="daily-heading">
                <h2 id="daily-heading">Daily</h2>
                <figure><canvas id="daily-chart"></canvas></figure>

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
                                    <td><?php echo Vnstat\Formatter::bytes($entry->getBytesReceived()); ?></td>
                                    <td><?php echo Vnstat\Formatter::bytes($entry->getBytesSent()); ?></td>
                                    <td><?php echo Vnstat\Formatter::bytes($entry->getBytesReceived() + $entry->getBytesSent()); ?></td>
                                    <td><?php echo Vnstat\Formatter::bitrate($entry->getBytesReceived() + $entry->getBytesSent(), $range); ?></td>
                                    <td><?php echo Vnstat\Formatter::ratio($entry->getBytesReceived(), $entry->getBytesSent()); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="monthly-heading">
                <h2 id="monthly-heading">Monthly</h2>
                <figure><canvas id="monthly-chart"></canvas></figure>

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
                                    <td><?php echo Vnstat\Formatter::bytes($entry->getBytesReceived()); ?></td>
                                    <td><?php echo Vnstat\Formatter::bytes($entry->getBytesSent()); ?></td>
                                    <td><?php echo Vnstat\Formatter::bytes($entry->getBytesReceived() + $entry->getBytesSent()); ?></td>
                                    <td><?php echo Vnstat\Formatter::bitrate($entry->getBytesReceived() + $entry->getBytesSent(), $range); ?></td>
                                    <td><?php echo Vnstat\Formatter::ratio($entry->getBytesReceived(), $entry->getBytesSent()); ?></td>
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
                                    <td><?php echo Vnstat\Formatter::bytes($entry->getBytesReceived()); ?></td>
                                    <td><?php echo Vnstat\Formatter::bytes($entry->getBytesSent()); ?></td>
                                    <td><?php echo Vnstat\Formatter::bytes($entry->getBytesReceived() + $entry->getBytesSent()); ?></td>
                                    <td><?php echo Vnstat\Formatter::bitrate($entry->getBytesReceived() + $entry->getBytesSent(), $range); ?></td>
                                    <td><?php echo Vnstat\Formatter::ratio($entry->getBytesReceived(), $entry->getBytesSent()); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </body>
</html>
