<?php
require 'vendor/autoload.php';
$database = new Vnstat\Database();
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
        <link href="bootstrap-3.2.0-dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="bootstrap-3.2.0-dist/css/bootstrap-theme.min.css" rel="stylesheet" />
        <link href="xcharts/xcharts.min.css" rel="stylesheet" />
        <script type="text/javascript" src="xcharts/d3.min.js"></script>
        <script type="text/javascript" src="xcharts/xcharts.min.js"></script>

        <style type="text/css">
            div.ratio {
                display: inline-block;
                width: 100px;
                height: 10px;
                border: 1px solid #ddd;
                background-color: #222;
                overflow: hidden;
            }

            div.ratio > div {
                height: 10px;
                background-color: #5cb85c;
            }

            g.received > rect {
                fill: #5cb85c !important;
            }

            g.sent > rect {
                fill: #222 !important;
            }

            th.position,
            td.position {
                width: 60px;
            }

            th.received,
            td.received,
            th.sent,
            td.sent,
            th.total,
            td.total,
            th.average-rate,
            td.average-rate {
                width: 120px;
                text-align: right;
            }

            th.ratio,
            td.ratio {
                width: 120px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="page-header">
                <h1>Network Traffic</h1>
            </div>

            <h2>Hourly</h2>
            <figure style="width: 100%; height: 300px;" id="hourly-chart"></figure>
            <script type="text/javascript">
                <?php
                $endHour   = (int) date('H');
                $startHour = ($endHour - 23);

                if ($startHour < 0) {
                    $startHour = 24 + $startHour;
                }

                $hours = $database->getHours();

                $receivedData = [
                    'className' => '.received',
                    'data'      => [],
                ];

                $sentData = [
                    'className' => '.sent',
                    'data'      => [],
                ];

                $maxBytes = 0;

                for ($i = $startHour; $i < 24; $i++) {
                    $hour = $hours[$i];
                    $receivedData['data'][] = ['x' => $i, 'y' => $hour->getBytesReceived()];
                    $sentData['data'][] = ['x' => $i, 'y' => $hour->getBytesSent()];

                    $maxBytes = max($maxBytes, $hour->getBytesReceived(), $hour->getBytesSent());
                }

                if ($endHour !== 23) {
                    for ($i = 0; $i <= $endHour; $i++) {
                        $hour = $hours[$i];
                        $receivedData['data'][] = ['x' => $i, 'y' => $hour->getBytesReceived()];
                        $sentData['data'][] = ['x' => $i, 'y' => $hour->getBytesSent()];

                        $maxBytes = max($maxBytes, $hour->getBytesReceived(), $hour->getBytesSent());
                    }
                }
                ?>
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

            <h2>Daily</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th class="day">Day</th>
                        <th class="received">Received</th>
                        <th class="sent">Sent</th>
                        <th class="total">Total</th>
                        <th class="average-rate">Average Rate</th>
                        <th class="ratio">Ratio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($database->getDays() as $id => $entry): ?>
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
                            <td class="day"><?php echo $dayFormatter->format($entry->getDateTime()); ?></td>
                            <td class="received"><?php echo formatBytes($entry->getBytesReceived()); ?></td>
                            <td class="sent"><?php echo formatBytes($entry->getBytesSent()); ?></td>
                            <td class="total"><?php echo formatBytes($entry->getBytesReceived() + $entry->getBytesSent()); ?></td>
                            <td class="average-rate"><?php echo formatBitrate($entry->getBytesReceived() + $entry->getBytesSent(), $range); ?></td>
                            <td class="ratio"><?php echo formatRatio($entry->getBytesReceived(), $entry->getBytesSent()); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Monthly</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th class="month">Month</th>
                        <th class="received">Received</th>
                        <th class="sent">Sent</th>
                        <th class="total">Total</th>
                        <th class="average-rate">Average Rate</th>
                        <th class="ratio">Ratio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($database->getMonths() as $id => $entry): ?>
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
                            <td class="month"><?php echo $entry->getDateTime()->format('F Y'); ?></td>
                            <td class="received"><?php echo formatBytes($entry->getBytesReceived()); ?></td>
                            <td class="sent"><?php echo formatBytes($entry->getBytesSent()); ?></td>
                            <td class="total"><?php echo formatBytes($entry->getBytesReceived() + $entry->getBytesSent()); ?></td>
                            <td class="average-rate"><?php echo formatBitrate($entry->getBytesReceived() + $entry->getBytesSent(), $range); ?></td>
                            <td class="ratio"><?php echo formatRatio($entry->getBytesReceived(), $entry->getBytesSent()); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Top 10</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th class="position">#</th>
                        <th class="day">Day</th>
                        <th class="received">Received</th>
                        <th class="sent">Sent</th>
                        <th class="total">Total</th>
                        <th class="average-rate">Average Rate</th>
                        <th class="ratio">Ratio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($database->getTop10() as $id => $entry): ?>
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
                            <td class="position"><?php echo ($id + 1); ?></td>
                            <td class="day"><?php echo $dayFormatter->format($entry->getDateTime()); ?></td>
                            <td class="received"><?php echo formatBytes($entry->getBytesReceived()); ?></td>
                            <td class="sent"><?php echo formatBytes($entry->getBytesSent()); ?></td>
                            <td class="total"><?php echo formatBytes($entry->getBytesReceived() + $entry->getBytesSent()); ?></td>
                            <td class="average-rate"><?php echo formatBitrate($entry->getBytesReceived() + $entry->getBytesSent(), $range); ?></td>
                            <td class="ratio"><?php echo formatRatio($entry->getBytesReceived(), $entry->getBytesSent()); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </body>
</html>
