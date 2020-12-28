<?php
/* 
	TODO:
	Split the hours/days/month display into tabs for easier viewing.
*/

/* Print errors for debug purposes */
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require 'vendor/autoload.php';

/* Fetch config file specifying interfaces if it exists */
if (file_exists(__DIR__ . '/config.php')) {
    $config = require __DIR__ . '/config.php';
} else {
    $config = [];
}

/* Check if any interfaces are available and if one is selected */
if (array_key_exists('interfaces', $config) && !empty($config['interfaces'])) {
    if (array_key_exists('interface', $_GET) && in_array($_GET['interface'], array_keys($config['interfaces']))) {
        $interface = $_GET['interface'];
    } else {
        $interface = array_keys($config['interfaces'])[0];
    }
} else {
    $interface = null;
}

/* Check if sent/received data should be rendered */
$showsent = (array_key_exists('showsent', $_GET));
$showrec = (array_key_exists('showrec', $_GET));
if (!$showsent and !$showrec) {
    $showsent = $showrec = True;
}

/* Set default graph formats */
$hourgraphtype = (array_key_exists('hourgraphtype', $_GET) ? $_GET['hourgraphtype'] : 'bar');
$daygraphtype = (array_key_exists('daygraphtype', $_GET) ? $_GET['daygraphtype'] : 'line');
$monthgraphtype = (array_key_exists('monthgraphtype', $_GET) ? $_GET['monthgraphtype'] : 'bar');

/* Get selected date/month if given */
$daytoshow = (array_key_exists('daytoshow', $_GET) ? $_GET['daytoshow'] : '');
$daygiven = (array_key_exists('daytoshow', $_GET) and $_GET['daytoshow'] != '') ? True : False;

$monthtoshow = (array_key_exists('monthtoshow', $_GET) ? $_GET['monthtoshow'] : '');
$monthgiven = (array_key_exists('monthtoshow', $_GET) and $_GET['monthtoshow'] != '') ? True : False;

$database = new Vnstat\Database($interface);
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

/* Loops through a multidimensional array and checks if a key has a specific value */
function find_key_value($array, $key, $val)
{
    foreach ($array as $item)
    {
        if (is_array($item) && find_key_value($item, $key, $val)) return true;
        if (isset($item[$key]) && $item[$key] == $val) return true;
    }
    return false;
}

$dayFormatter = new IntlDateFormatter(
    'en-GB',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    date_default_timezone_get()
);

/* 	Extracts the defiend time period, inserts missing records and sorts result 
	$data = THe hour/day/month data to handle
	$type = hour|day|month
	$fromStamp = timestamp for beginning of range
	$toStamp = timestamp for end of range
*/
function getDataForTimePeriodandIntervalType($data, $type, $fromStamp, $toStamp) 
{
	/* Set configuration */
	switch ($type) {
		case "hour":
			$xFormat='G';
			$typeFormat='Y-m-d H:i:s';
			$intervalStep='1 hour';
			break;
		case "day":
			$xFormat='d/m';
			$typeFormat='Y-m-d';
			$intervalStep='1 day';
			break;
		case "month":
			$xFormat='M Y';
			$typeFormat='Y-m-d';
			$intervalStep='1 month';
			break;
	}

	/* Prepare data storage */
	$receivedData = [
		'className' => '.received',
		'data'      => [],
	];

	$sentData = [
		'className' => '.sent',
		'data'      => [],
	];

	/* Loop through fetched data */
	foreach ($data as $item) {
		$dateTime = date_timestamp_get($item->getDateTime()); 
		$xValue = $item->getDateTime()->format($xFormat);
		
		/* Pull out and use anything between (including) from and to time */
		/* Store xvalue, data value and timestamp to use for sorting */
		if ($dateTime >= $fromStamp and  $dateTime <= $toStamp  ) {
			$receivedData['data'][] = ['x' => $xValue, 'y' => $item->getBytesReceived(), 'timestamp' => $item->getDateTime()->getTimestamp()];
			$sentData['data'][] = ['x' => $xValue, 'y' => $item->getBytesSent(), 'timestamp' => $item->getDateTime()->getTimestamp()];
		}
	}

	/* Loop through the time interval and add missing data points with correct timestamp for sorting */
	for ($i = $fromStamp; $i <= $toStamp; $i=strtotime(date($typeFormat,$i).' + '.$intervalStep)) {
		if (!find_key_value($receivedData,'timestamp',$i)) {
			$receivedData['data'][] = ['x' => date($xFormat,$i), 'y' => 0, 'timestamp' => $i];
			$sentData['data'][] = ['x' => date($xFormat,$i), 'y' => 0, 'timestamp' => $i];
		}
	}

	/* Sort the data */
	usort($receivedData['data'], fn($b, $a) => $b['timestamp'] <=> $a['timestamp']);
	usort($sentData['data'], fn($b, $a) => $b['timestamp'] <=> $a['timestamp']);
	
	return array($receivedData, $sentData);
}

function renderChart($receivedData, $sentData, $chartName, $graphtype, $showrec, $showsent) {
?>
	<figure style="width: 100%; height: 400px;" id="<?php echo $chartName ?>-chart"></figure>
	<script type="text/javascript">
		var chart = new xChart(
			<?php echo "'".$graphtype."'"; ?>,
			{
				"xScale": "ordinal",
				"yScale": "linear",
				"type": <?php echo "'".$graphtype."'"; ?>,
				"main": [
					<?php 
						if ($showrec) { echo json_encode($receivedData); }
						if ($showrec and $showsent) { echo ","; }
						if ($showsent) { echo json_encode($sentData); }
					?>
				]
			},
			<?php echo '\'#'.$chartName.'-chart\'' ?>,
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
<?php
}

/*
$data = the data to render
$type = day|month|top10
*/
function renderDataTable($data, $type) {

	?>
	<table class="table table-bordered">
		<thead>
			<tr>
				<?=($type=='top10') ? '<th class="position">#</th>' : ''?>
				<th class="<?=($type == 'month' ? 'Month' : 'Day')?>"><?=($type == 'month' ? 'Month' : 'Day')?></th>
				<th class="received">Received</th>
				<th class="sent">Sent</th>
				<th class="total">Total</th>
				<th class="average-rate">Average Rate</th>
				<th class="ratio">Ratio</th>
			</tr>
		</thead>
		<tbody>
			<?php 
			$top10position=0;
			foreach ($data as $id => $entry): ?>
				<?php
				if (!$entry->isFilled()) {
					continue;
				}

				/* Setting parameters */
				$classlink=$type;
				$dateFormat='Y-m-d';
				$dateFormat2='l, d F Y';


				$diffDate = clone $entry->getDateTime();
				$diffDate->setTimezone($timezone);
				$diffDate->setTime(0, 0, 0);
				$startTimestamp = $diffDate->getTimestamp();
				
				switch($type) {
					case "day":
						break;
					case "month":
						$dateFormat='Y-m';
						$dateFormat2='F Y';			
						$entry->getDateTime()->setTimeZone($timezone);
						$diffDate->modify('first day of');
						$startTimestamp = $diffDate->getTimestamp();
						$diffDate->modify('last day of');
						break;
					case "top10":
						$classlink='day';
						$top10position+=1;
						break;
				}
				$diffDate->setTime(23, 59, 59);
				$endTimestamp = $diffDate->getTimestamp();
				$range = $endTimestamp - $startTimestamp;				

				?>

				<tr>
					<?=($type=='top10') ? '<td class="position">'.$top10position.'</td>' : ''?>
					<td class="<?=$classlink?>">
						<a href="javascript:return(false);" onClick="document.getElementById('<?=$classlink?>toshow').value='<?=strtotime($entry->getDateTime()->format($dateFormat)); ?>';document.getElementById('dataForm').submit();">
						<?=$entry->getDateTime()->format($dateFormat2); ?>
						</a>
					</td>
					<td class="received"><?php echo formatBytes($entry->getBytesReceived()); ?></td>
					<td class="sent"><?php echo formatBytes($entry->getBytesSent()); ?></td>
					<td class="total"><?php echo formatBytes($entry->getBytesReceived() + $entry->getBytesSent()); ?></td>
					<td class="average-rate"><?php echo formatBitrate($entry->getBytesReceived() + $entry->getBytesSent(), $range); ?></td>
					<td class="ratio"><?php echo formatRatio($entry->getBytesReceived(), $entry->getBytesSent()); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php	
}

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

            g.received > path {
                stroke: #5cb85c !important;
                fill: rgb(0,0,0,0) !important;
            }

            g.sent > path {
                stroke: #222 !important;
                fill: rgb(0,0,0,0) !important;
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
            
            table.datatype {
                width:150px;
            }
            
            td.recdata {
                background-color: #5cb85c;
            }

            td.sentdata {
                background-color: #222;
                color: white;
            }

        </style>
    </head>
    <body>
        <form id="dataForm" action="index.php">
			<input type="hidden" id="daytoshow" name="daytoshow" value="<?php echo $daytoshow ?>"/>
			<input type="hidden" id="monthtoshow" name="monthtoshow" value="<?php echo $monthtoshow ?>"/>
            <div class="container">
                <div class="page-header">
                    <?php if (array_key_exists('interfaces', $config) && count($config['interfaces']) > 1): ?>
                        <div class="pull-right">
                            <div class="input-group">
                                <span class="input-group-addon">Interface</span>
                                <select name="interface" class="form-control" onchange="this.form.submit();">
                                    <?php foreach ($config['interfaces'] as $option => $val): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>"<?php if ($option === $interface): ?> selected="selected"<?php endif; ?>>
                                            <?php echo htmlspecialchars($option)." - (".htmlspecialchars($val).")"; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                    
				<BR/><BR/>
				<h1>Network Traffic for <?php echo $database->getInterface()." - (" .$database->getNick().")" ?> </h1>
                </div>
				<a href="index.php">Reset selections</a><BR/>
                <div>
                    <table class="table table-bordered datatype">
                            <tr>
                                <td class="recdata"><input type="checkbox" name="showrec" <?php echo ($showrec) ? "checked" :""; ?> onchange="this.form.submit();"/></td>
                                <td class="recdata">Received data</td>
                            </tr>
                            <tr>
                                <td class="sentdata"><input type="checkbox" name="showsent" <?php echo ($showsent) ? "checked" :""; ?> onchange="this.form.submit();"/></td>
                                <td class="sentdata">Sent data</td>
                            </tr>
                    </table>
                </div>
                
                <BR/>

                <h2>Hourly 
                    <select name="hourgraphtype" onChange="this.form.submit();">
                        <option value="bar" <?php if($hourgraphtype=='bar') { echo "selected"; } ?>>Bar</option>
                        <option value="line" <?php if($hourgraphtype=='line') { echo "selected"; } ?>>Line</option>
                    </select>
                </h2>
				<?php
					/* Print header */
					if ($daygiven) {
						echo 'Showing traffic for '.date("D Y-m-d",$daytoshow);
						echo "<br/>";
					
					} else {
						echo 'Showing last 24 hours to '.date("l Y-m-d H:00");
						echo "<br/>";
					}

					/* Set time range to render */					
					if($daygiven) {
						$toTime = strtotime(date("Y-m-d H:00:00",$daytoshow). ' + 1 day');
					} else {
						$toTime = strtotime(date("Y-m-d H:00:00"));
					}

					$fromTime = strtotime(date("Y-m-d H:00:00",$toTime). ' - 1 day');

					if(!$daygiven) {
						$fromTime=strtotime(date("Y-m-d H:00:00",$fromTime).' + 1 hour');
					} else {
						$toTime=strtotime(date("Y-m-d H:00:00",$toTime).' - 1 hour');
					}

					/* Fetch all data */
					/* 
						TODO: 
							Could be improved in Database.php to only fetch the specified range. But I doubt it will have any impact
							since it already picked up the whole json object. It might be needed if the data amount is huge,
							which should only happen if vnstat logging is manually adjusted.
					*/
                    $hours = $database->getHours(); //Fetch raw data
					
					list($receivedData, $sentData) = getDataForTimePeriodandIntervalType($hours, 'hour', $fromTime, $toTime); //Filter and pad the data
					
					renderChart($receivedData, $sentData, 'hourly', $hourgraphtype, $showrec, $showsent); //Draw the diagram


                echo"<BR/>   		

				<h2>Days</h2>
				";

				renderDataTable($database->getDays(),'day');
				?>
				
				<BR/>
				
                <h2>Daily
                    <select name="daygraphtype" onChange="this.form.submit();">
                        <option value="bar" <?php if($daygraphtype=='bar') { echo "selected"; } ?>>Bar</option>
                        <option value="line" <?php if($daygraphtype=='line') { echo "selected"; } ?>>Line</option>
                    </select>
                </h2>

                <?php
                    if ($monthgiven) {
						echo 'Showing traffic for '.date("F Y",$monthtoshow);
						echo "<br/>";
					
					} else {
						echo 'Showing last month to '.date("l dS")." of ".date("F Y");
						echo "<br/>";
					}

					if($monthgiven) {
						$toDate = strtotime(date("Y-m-01",$monthtoshow). ' + 1 month');
					} else {
						$toDate = strtotime(date("Y-m-d"));
					}
					
					$fromDate = strtotime(date("Y-m-d",$toDate). ' - 1 month');
					
					if(!$monthgiven) {
						$fromDate=strtotime(date("Y-m-d",$fromDate).' + 1 day');
					} else {
						$toDate=strtotime(date("Y-m-d",$toDate).' - 1 day');
					}
                   
                    $days = $database->getDays(); //Fetch raw data
					
					list($receivedData, $sentData) = getDataForTimePeriodandIntervalType($days, 'day', $fromDate, $toDate); //Filter and pad data
					
					renderChart($receivedData, $sentData, 'daily', $daygraphtype, $showrec, $showsent); //Draw the diagram
				
				echo "<BR/>   
 				
                <h2>Months</h2>
				";
				renderDataTable($database->getMonths(),'month');
				?>
				
				<BR>

                <h2>Monthly
                    <select name="monthgraphtype" onChange="this.form.submit();">
                        <option value="bar" <?php if($monthgraphtype=='bar') { echo "selected"; } ?>>Bar</option>
                        <option value="line" <?php if($monthgraphtype=='line') { echo "selected"; } ?>>Line</option>
                    </select>
                </h2>

				<?php
					echo 'Showing last 12 months';
					echo "<br/>";
                    
					$toMonth = strtotime(date("Y-m-01"));
					$fromMonth = strtotime(date("Y-m-d",$toMonth). ' - 1 year');					
					
                   
                    $months = $database->getMonths();
                    

					list($receivedData, $sentData) = getDataForTimePeriodandIntervalType($months, 'month', $fromMonth, $toMonth);
					
					renderChart($receivedData, $sentData, 'monthly', $monthgraphtype, $showrec, $showsent);                 
                ?>

				<BR/>
				
                <h2>Top 10 days (lifetime)</h2>
				<?php

				renderDataTable($database->getTop10(),'top10');
				?>

            </div>
        </form>
    </body>
</html>
