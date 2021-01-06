<?php

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
$twoAxes= (array_key_exists('twoAxes', $_GET));
if (count($_GET) <= 1) {
	$showsent = $showrec = $twoAxes = True;
}

/* Set  graph formats */
$hourgraphtype = (array_key_exists('hourgraphtype', $_GET) ? $_GET['hourgraphtype'] : 'bar');
$daygraphtype = (array_key_exists('daygraphtype', $_GET) ? $_GET['daygraphtype'] : 'bar');
$monthgraphtype = (array_key_exists('monthgraphtype', $_GET) ? $_GET['monthgraphtype'] : 'bar');
$comparegraphtype = (array_key_exists('comparegraphtype', $_GET) ? $_GET['comparegraphtype'] : 'bar');

/* Get selected date/month if given */
$daytoshow = (array_key_exists('daytoshow', $_GET) ? $_GET['daytoshow'] : '');
$daygiven = (array_key_exists('daytoshow', $_GET) and $_GET['daytoshow'] != '') ? True : False;

$monthtoshow = (array_key_exists('monthtoshow', $_GET) ? $_GET['monthtoshow'] : '');
$monthgiven = (array_key_exists('monthtoshow', $_GET) and $_GET['monthtoshow'] != '') ? True : False;

$yeartoshow = (array_key_exists('yeartoshow', $_GET) ? $_GET['yeartoshow'] : '');
$yeargiven = (array_key_exists('yeartoshow', $_GET) and $_GET['yeartoshow'] != '') ? True : False;

$typetocompare = (array_key_exists('typetocompare', $_GET) ? $_GET['typetocompare'] : 'day');
$datetocompare1 = (array_key_exists('datetocompare1', $_GET) ? $_GET['datetocompare1'] : '');
$datetocompare2 = (array_key_exists('datetocompare2', $_GET) ? $_GET['datetocompare2'] : '');
$comparesgiven = ((array_key_exists('datetocompare1', $_GET) and $_GET['datetocompare1'] != '') and (array_key_exists('datetocompare2', $_GET) and $_GET['datetocompare2'] != '')) ? True : False;


/* Set tab to show */
$tabtoshow = (array_key_exists('tabtoshow', $_GET) ? $_GET['tabtoshow'] : 'hours');

$database = new Vnstat\Database($interface);

function formatBytes($bytes) {
	$units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
	$pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow   = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);

	return round($bytes) . ' ' . $units[$pow];
}

function formatBitrate($bytes, $seconds) {
	$units = ['bit', 'kbit', 'mbit', 'gbit', 'tbit'];
	$bits  = ($bytes * 8) / $seconds;
	$pow   = floor(($bits ? log($bits) : 0) / log(1024));
	$pow   = min($pow, count($units) - 1);
	$bits  /= (1 << (10 * $pow));

	return round($bits, 2) . ' ' . $units[$pow] . '/s';
}

function formatRatio($bytesReceived, $bytesSent) {
	$total = $bytesReceived + $bytesSent;
	$percentageReceived = ($bytesReceived / $total * 100);

	return sprintf(
		'<div title="%f%%" class="ratio"><div style="width: %f%%;"></div></div>',
		$percentageReceived,$percentageReceived
	);
}

/* Loops through a multidimensional array and checks if a key has a specific value */
function find_key_value($array, $key, $val) {
	foreach ($array as $item)
	{
		if (is_array($item) && find_key_value($item, $key, $val)) return true;
		if (isset($item[$key]) && $item[$key] == $val) return true;
	}
	return false;
}

/* 	Extracts the defiend time period, inserts missing records and sorts result 
		$data = THe hour/day/month data to handle
		$type = hour|day|month
		$fromStamp = timestamp for beginning of range
		$toStamp = timestamp for end of range
*/
function filterDataForTimePeriodandIntervalType($data, $type, $fromStamp, $toStamp) {
	/* Set configuration */
	switch ($type) {
		case "hour":
			$xFormat='G';
			$typeFormat='Y-m-d H:i:s';
			$intervalStep='1 hour';
			$legendFormat="M jS Y";	
			break;
		case "day":
			$xFormat='M jS';
			$typeFormat='Y-m-d';
			$legendFormat="M Y";
			$intervalStep='1 day';
			break;
		case "month":
			$xFormat='M Y';
			$typeFormat='Y-m-d';
			$legendFormat="Y";
			$intervalStep='1 month';
			break;
	}

	/* Prepare data storage */
	$receivedData = [
		'className' => '.received',
		'dataType' => 'received',
		'data'      => [],
	];

	$sentData = [
		'className' => '.sent',
		'dataType' => 'sent',
		'data'      => [],
	];

	/* Loop through fetched data */
	foreach ($data as $item) {
		$dateTime = date_timestamp_get($item->getDateTime()); 
		$xValue = $item->getDateTime()->format($xFormat);
		
		/* Pull out and use anything between (including) from and to time */
		/* Store xvalue, data value and timestamp to use for sorting */
		if ($dateTime >= $fromStamp and  $dateTime <= $toStamp  ) {
			$receivedData['data'][] = ['x' => $xValue, 'y' => $item->getBytesReceived(), 'timestamp' => $item->getDateTime()->getTimestamp(), 'label' => formatBytes($item->getBytesReceived())];
			$sentData['data'][] = ['x' => $xValue, 'y' => $item->getBytesSent(), 'timestamp' => $item->getDateTime()->getTimestamp(), 'label' => formatBytes($item->getBytesReceived())];

			$receivedData['legend'] = $item->getDateTime()->format($legendFormat);
			$sentData['legend'] = $item->getDateTime()->format($legendFormat);
		}
	}

	/* Loop through the time interval and add missing data points with correct timestamp for sorting */
	for ($i = $fromStamp; $i <= $toStamp; $i=strtotime(date($typeFormat,$i).' + '.$intervalStep)) {
		if (!find_key_value($receivedData,'timestamp',$i)) {
			$receivedData['data'][] = ['x' => date($xFormat,$i), 'y' => 0, 'timestamp' => $i, 'label' => formatBytes(0)];
			$sentData['data'][] = ['x' => date($xFormat,$i), 'y' => 0, 'timestamp' => $i, 'label' => formatBytes(0)];
		}
	}

	/* Sort the data */
	usort($receivedData['data'], fn($b, $a) => $b['timestamp'] <=> $a['timestamp']);
	usort($sentData['data'], fn($b, $a) => $b['timestamp'] <=> $a['timestamp']);
	
	return array($receivedData, $sentData);
}

/*	Renders the chart area for supplied data and type
		$receivedData = Array with dates and values for received data
		$sentData = Array with dates and values for sent data
		$charName = Name to use for the chart, must be unique
		$graphtype = line|bar
		$showrec = bool if received data should be rendered
		$showsent = bool if sent data should be rendered
		$twoAxes = bool to set if received and sent data should be rendered on separate Axes
*/
function renderChartJs($allData, $chartName, $graphtype, $showrec, $showsent,$twoAxes) {
	$backgroundColors=['#5cb85c','#222','#B85CB8','#5C87B8'];
	$jsonData = [];
	$labels= [];
	$datasets= [];
	$timestamps=[];
	foreach($allData as $oneData) {
		if(($showrec and $oneData['dataType']=='received') or ($showsent and $oneData['dataType']=='sent')) {
			$aSet= [];
			$aSet['label']=ucfirst($oneData['dataType']);
			if(count($allData) == 4) {
				$aSet['label'].=' '.$oneData['legend'];
			}
			$aSet[($graphtype=='line' ? 'borderColor' : 'backgroundColor')]=$backgroundColors[count($datasets)];
			$aSet['backgroundColor']=$backgroundColors[count($datasets)];
			$aSet['borderWidth']=($graphtype=='line'?3:0);
			$aSet['fill']=False;
			if($twoAxes) {
				$aSet['yAxisID']=$oneData['dataType'];
			}
			$dsTimestamps = [];
			$yData = [];
			foreach($oneData['data'] as $item) {
				if(!in_array($item['x'],$labels)) {
					$labels[]=$item['x'];
				}
				$yData[]=$item['y'];
				$dsTimestamps[]=$item['timestamp'];
			}
		$aSet['data'] = $yData;
		array_push($datasets,$aSet);
		array_push($timestamps,$dsTimestamps);
		}
	}
	?>
	<canvas id="<?=$chartName."-jsChart"?>"></canvas>

	<script type="text/javascript">
		function formatBytes(value, index, values) {
			var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
			var pow   = Math.floor((value ? Math.log(value) : 0) / Math.log(1024));
			pow = Math.min(pow, units.length - 1);
			return (Math.round(value / (1 << (10 * pow)) * 10) / 10) + ' ' + units[pow];
		}
	
		var ctx = document.getElementById(<?="'".$chartName."-jsChart'"?>).getContext('2d');
		var <?=$chartName."Chart"?> = new Chart(ctx, {
		// The type of chart we want to create
			type: <?="'".$graphtype."'"?>,

			// The data for our dataset
			data: {
				labels: <?=json_encode($labels)?>,
				datasets: <?=json_encode($datasets)?>,
				timestamps: <?=json_encode($timestamps)?>,
				
			},

			// Configuration options go here
			options: {
				legend: {
					position: 'bottom'
				},
				scales: {
					yAxes: [
						<?php if(!$twoAxes) { ?>
							{
								type: 'linear',
								position: 'left',
								scaleLabel: {
									labelString: 'Data',
									display: true
								},
								ticks: {
									callback: function(value, index, values) {
										return formatBytes(value, index, values);
									}
								}
							}
						<?php } ?>
						<?php if($twoAxes and $showrec) { ?>
							{
								id: 'received',
								type: 'linear',
								position: 'left',
								scaleLabel: {
									labelString: 'Received data',
									display: true
								},
								ticks: {
									callback: function(value, index, values) {
										return formatBytes(value, index, values);
									}
								}
							}, 
						<?php } ?>
						<?php if($twoAxes and $showsent) { ?>
							{
								id: 'sent',
								type: 'linear',
								position: 'right',
								scaleLabel: {
									labelString: 'Sent data',
									display: true
								},
								ticks: {
									callback: function(value, index, values) {
										return formatBytes(value, index, values);
									}
								}
							}
						<?php } ?>
					]
				},

				<?php 
				if(stripos($chartName,"daily") !== false or stripos($chartName,"monthly") !== false) {
					switch(true) {
						case stristr($chartName,"daily"):
							$itemType='day';
							$nexttab='hours';
							break;
						case stristr($chartName,"monthly"):
							$itemType='month';
							$nexttab='days';
							break;
					}?>
					onHover: function(e, elements) {
						$(e.currentTarget).css("cursor", elements[0] ? "pointer" : "default");         
					},
					onClick:function(e){
						var activeDataset = <?=$chartName."Chart"?>.getDatasetAtEvent(e);
						var activePoints = <?=$chartName."Chart"?>.getElementsAtEvent(e);
						var selectedIndex = activePoints[0]._index;
						timestamp=this.data.timestamps[activeDataset[0]._datasetIndex][selectedIndex];
						document.getElementById('tabtoshow').value=<?="'".$nexttab."'"?>;
						document.getElementById(<?="'".$itemType."toshow'"?>).value=timestamp;
						document.getElementById('dataForm').submit();
					}
				<?php 
				} ?>
			}
		});
	</script>
<?php
}

/*	Renders a table from supplied data
		$data = the data to render
		$type = day|month|top10
*/
function renderDataTable($data, $type) {
	$timezone = new DateTimeZone(date_default_timezone_get());
	
	/* Set parameters and sort data */
	switch($type) {
		case "day":
			$dateFormat='Y-m-d';
			$dateFormat2='l, d F Y';
			$classlink=$type;
			$tabName = 'hours';
			usort($data, fn($a, $b) => $b->getDateTime() <=> $a->getDateTime());
			break;
		case "month":
			$dateFormat='Y-m';
			$dateFormat2='F Y';			
			$classlink=$type;
			$tabName = 'days';
			usort($data, fn($a, $b) => $b->getDateTime() <=> $a->getDateTime());
			break;
		case "top10":
			$dateFormat='Y-m-d';
			$dateFormat2='l, d F Y';
			$classlink='day';
			$tabName = 'hours';
			break;
	}
	?>
	<table class="table table-bordered">
		<thead>
			<tr>
				<?=($type=='top10') ? '<th class="position">#</th>' : ''?>
				<th class="<?=($type == 'month' ? 'Month' : 'Day')?>"><?=($type == 'month' ? 'Month' : 'Day')?></th>
				<th class="received">Received</th>
				<th class="sent">Sent</th>
				<th class="total">Total</th>
				<th class="average-rate" title="Data divided by timeperiod.&#013;&#010;Not average transfer speed.">Average Rate</th>
				<th class="ratio">Ratio (rx/tx)</th>
			</tr>
		</thead>
		<tbody>
			<?php 
			$top10position=0; // For enumerating the top 10 positions
			
			foreach ($data as $id => $entry): 
				if (!$entry->isFilled()) {
					continue;
				}

				/* Wierd stuff from original code for calculating average */
				$diffDate = clone $entry->getDateTime();
				$diffDate->setTimezone($timezone);
				$diffDate->setTime(0, 0, 0);
				$startTimestamp = $diffDate->getTimestamp();
				if($type == 'month') {
						$entry->getDateTime()->setTimeZone($timezone);
						$diffDate->modify('first day of');
						$startTimestamp = $diffDate->getTimestamp();
						$diffDate->modify('last day of');
				}

				/* Fix range for current date */
				if(date('Ymd') == date('Ymd', $diffDate->getTimestamp())) {
					$diffDate->setTime(date('H'),date('i'),date('s'));
				} else {
					$diffDate->setTime(23, 59, 59);
				}

				$top10position+=1;
				$endTimestamp = $diffDate->getTimestamp();
				$range = $endTimestamp - $startTimestamp;				

				?>

				<tr>
					<?=($type=='top10') ? '<td class="position">'.$top10position.'</td>' : ''?>
					<td class="<?=$classlink?>">
						<a href="javascript:return(false);" onClick="document.getElementById('tabtoshow').value='<?=$tabName?>';document.getElementById('<?=$classlink?>toshow').value='<?=strtotime($entry->getDateTime()->format($dateFormat)); ?>';document.getElementById('dataForm').submit();">
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

/*	Render a dropdown list for selecting day/month to see 
	$data = the data to use for rendering
	$type = day|month defining what to show as options in the list
	$itemtoshow = timestamp of currently selected item (if any)
*/
function renderChartSelects($data,$selectListType,$itemtoshow,$graphType) {
	echo "<table width='100%'><tr><td>";
	echo "Show: <select name=\"{$selectListType}list\" onChange=\"document.getElementById('{$selectListType}toshow').value=this.value;this.form.submit();\">";
	
	/* Set parameters for rendering and print base option */
	switch($selectListType) {
		case 'day':
			$dateFormat = 'Y-m-d';
			$dateFormat2 = 'Y-m-d';
			$graphDataType='hour';
			echo '<option value="">Last 24 hours</option>';
			break;
		case 'month':
			$dateFormat = 'F Y';
			$dateFormat2 = 'Y-m';
			$graphDataType='day';
			echo '<option value="">Last month</option>';
			break;
		case 'year':
			$dateFormat = 'Y';
			$dateFormat2 = 'Y';
			$graphDataType='month';
			echo '<option value="">Last 12 months</option>';
			break;
	}

	$options = [];
	
	/* Loop data and pull out all viable options in correct format */
	foreach ($data as $datapoint) {
		if($datapoint->getBytesSent() > 0 or $datapoint->getBytesReceived() > 0) {
			$options[date($dateFormat,$datapoint->getDateTime()->getTimestamp())] = strtotime(date($dateFormat2,$datapoint->getDateTime()->getTimestamp()));
		}
	}
	
	arsort($options);
	
	/* Print data as options */
	foreach($options as $date => $time) {
		echo $time;
		echo '<option value="'.$time.'"';
		if($time == $itemtoshow) {
			echo ' selected';
		}
		echo '>'.$date.'</option>';
	}
	echo '</select>';
	echo "</td><td align='right'>"; ?>
		Graph type: <select name="<?=$graphDataType?>graphtype" onChange="this.form.submit();">
	<option value="bar" <?php if($graphType=='bar') { echo "selected"; } ?>>Bar</option>
		<option value="line" <?php if($graphType=='line') { echo "selected"; } ?>>Line</option>
	</select><?php
	echo "</td></tr></table>";
}

/* 	Renders a comparison chart 
	$database = The database object containing all data
	$showrec = bool if received data should be rendered
	$showsent = bool if sent data should be rendered
	$comparegraphtype = bar | line the tpye of gprah to draw
	$datetocompare1 = First date to compare (1st of month or 1st of year in month/year cases)
	$datetocompare2 = Second date to compare (1st of month or 1st of year in month/year cases)
	$typetocopmare = day | month | year which type of time range that is compared
*/

function renderCompareChart($database,$showrec,$showsent,$comparegraphtype,$datetocompare1,$datetocompare2,$typetocompare,$twoAxes) {
	switch($typetocompare) {
		case "day":
			$dateFormat="Y-m-d";
			$legendFormat="Y-m-d";
			$dataTypeOnXAxis="hour";
			$chartName='hourlyCompare';
			$dataX = $database->getHours(); //Fetch raw data
			break;
		case "month":
			$dateFormat="Y-m-01";
			$legendFormat="F Y";
			$dataTypeOnXAxis="day";
			$chartName='dailyCompare';
			$dataX = $database->getDays(); //Fetch raw data
			break;
		case "year":
			$dateFormat="Y-01-01";
			$legendFormat="Y";
			$dataTypeOnXAxis="month";
			$chartName='monthlyCompare';
			$dataX = $database->getMonths(); //Fetch raw data
			break;
	}

	$dataToCompare = [];
	$compares=[$datetocompare1,$datetocompare2];
	
	foreach($compares as $compare) {
		$toTime = strtotime(date($dateFormat,$compare). ' + 1 '.$typetocompare);
		$fromTime = strtotime(date($dateFormat,$toTime). ' - 1 '.$typetocompare);
		$toTime=strtotime(date($dateFormat,$toTime).' - 1 '.$dataTypeOnXAxis);

		$result = filterDataForTimePeriodandIntervalType($dataX, $dataTypeOnXAxis, $fromTime, $toTime); //Filter and pad the data
		for($i=0;$i<=1;$i++) {
			foreach($result[$i]['data'] as &$item) {
				if($dataTypeOnXAxis=='month') {
					$item['x'] = explode(' ', $item['x'])[0]; // Remove month from data so it can be rendered ins ame x postion.
				} elseif($dataTypeOnXAxis =='day') {
					$item['x'] = explode(' ', $item['x'])[1]; // Remove month from data so it can be rendered ins ame x postion.
				}
			}
		}
		$dataToCompare = array_merge($dataToCompare,$result);
	}
	for($i=0;$i<=3;$i++) {
		$dataToCompare[$i]['className']='.compare'.$i;
	}

	renderChartJs($dataToCompare, $dataTypeOnXAxis.$chartName, $comparegraphtype, $showrec, $showsent,$twoAxes); //Draw the diagram
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
		<script src="http://code.jquery.com/jquery-1.11.0.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/chart.js@2.8.0"></script>
		<link rel="stylesheet" href="styles.css">
	</head>
	<body>
		<form id="dataForm" action="index.php">
			<input type="hidden" id="daytoshow" name="daytoshow" value="<?php echo $daytoshow ?>"/>
			<input type="hidden" id="monthtoshow" name="monthtoshow" value="<?php echo $monthtoshow ?>"/>
			<input type="hidden" id="yeartoshow" name="yeartoshow" value="<?php echo $yeartoshow ?>"/>
			<input type="hidden" id="tabtoshow" name="tabtoshow" value="<?php echo $tabtoshow ?>"/>
			<input type="hidden" id="typetocompare" name="typetocompare" value="<?php echo $typetocompare ?>"/>
			<input type="hidden" id="datetocompare1" name="datetocompare1" value="<?php echo $datetocompare1 ?>"/>
			<input type="hidden" id="datetocompare2" name="datetocompare2" value="<?php echo $datetocompare2 ?>"/>
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
					<h1>Network traffic for <?php echo $database->getInterface()." - (" .$database->getNick().")" ?> </h1>
				</div>
				<div id="header-buttons">
					<table width="100%">
						<tr>
							<td>
								<button type="submit" title="Toggles showing received data in diagrams." class="normal <?=($showrec) ? "selected" :""; ?>" onClick="document.getElementById('showrec').checked=!document.getElementById('showrec').checked;">Show received</button>
								<button type="submit" title="Toggles showing sent data in diagrams." class="normal <?=($showsent) ? "selected" :""; ?>"  onClick="document.getElementById('showsent').checked=!document.getElementById('showsent').checked;">Show sent</button>
								<button type="submit" title="Show sent and received data on separate axes." class="normal <?=($twoAxes) ? "selected" :""; ?>"  onClick="document.getElementById('twoAxes').checked=!document.getElementById('twoAxes').checked;">Two Axes</button>
								<input style="display:none;" type="checkbox" id="showrec" name="showrec" <?php echo ($showrec) ? "checked" :""; ?>/>
								<input style="display:none;" type="checkbox" id="showsent" name="showsent" <?php echo ($showsent) ? "checked" :""; ?>/>
								<input style="display:none;" type="checkbox" id="twoAxes" name="twoAxes" <?php echo ($twoAxes) ? "checked" :""; ?>/>
							</td>
							<td align="right">
								<button type="button"  title="Resets all diagrams and selections" class="normal" onClick="document.location='index.php?tabtoshow='+document.getElementById('tabtoshow').value;">Reset all</button>
							</td>
						</tr>
					</table>
				</div>
				<BR/>
				<div id="tabs">
					<button type="button" class="tablink" onclick="openPage('hours',this);"<?=($tabtoshow=='hours') ? 'id="defaultOpen"' : '' ?>>Hours</button>
					<button type="button" class="tablink" onclick="openPage('days', this);"<?=($tabtoshow=='days') ? 'id="defaultOpen"' : '' ?>>Days</button>
					<button type="button" class="tablink" onclick="openPage('months', this);"<?=($tabtoshow=='months') ? 'id="defaultOpen"' : '' ?>>Months</button>
					<button type="button" class="tablink" onclick="openPage('top10', this);"<?=($tabtoshow=='top10') ? 'id="defaultOpen"' : '' ?>>Top 10 days</button>
					<button type="button" class="tablink" onclick="openPage('compare', this);"<?=($tabtoshow=='compare') ? 'id="defaultOpen"' : '' ?>>Compare</button>
				</div>
				<div id="hours" class="tabcontent">
					<?php
						/* Print header */
						if ($daygiven) {
							echo '<h2>Hourly traffic for '.date("l Y-m-d",$daytoshow).'</h2>';

						} else {
							echo '<h2> Hourly traffic for last 24 hours</h2>';
							
						}
						if ($showrec or $showsent) {
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

							$hours = $database->getHours(); //Fetch raw data
							
							list($receivedData, $sentData) = filterDataForTimePeriodandIntervalType($hours, 'hour', $fromTime, $toTime); //Filter and pad the data
							renderChartSelects($hours,'day',$daytoshow,$hourgraphtype);
							echo "<BR/>";
							renderChartJs([$receivedData, $sentData], 'hourly', $hourgraphtype, $showrec, $showsent,$twoAxes); //Draw the diagram

							
						} else {
							echo '<em>Select either received or sent data to show diagram.</em>';
						}
						?>
				</div>
				<div id="days" class="tabcontent">
					<?php
						/* Print header */
						if ($monthgiven) {
							echo '<h2>Daily traffic for '.date("F Y",$monthtoshow).'</h2>';
						
						} else {
							echo '<h2>Daily traffic for latest month</h2>';
						}

						if ($showrec or $showsent) {
							/* Set time range to render */
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
							
							list($receivedData, $sentData) = filterDataForTimePeriodandIntervalType($days, 'day', $fromDate, $toDate); //Filter and pad data
							renderChartSelects($days,'month',$monthtoshow,$daygraphtype);
							echo "<BR/>";
							renderChartJs([$receivedData, $sentData], 'daily', $daygraphtype, $showrec, $showsent,$twoAxes); //Draw the diagram

						} else {
							echo '<em>Select either received or sent data to show diagram.</em>';
						}
						
						echo "<h2>Days</h2>";
					
						renderDataTable($database->getDays(),'day');
					?>

				</div>
				<div id="months" class="tabcontent">
					<?php
					if ($yeargiven) {
							echo '<h2>Monthly traffic for '.date("Y",$yeartoshow).'</h2>';
						} else {
							echo '<h2>Monthly traffic for last 12 months</h2>';
						}
					
						if ($showrec or $showsent) {						
							/* Set time range to render */
							if($yeargiven) {
								$toMonth = strtotime(date("Y-01-01",$yeartoshow).' + 1 year' ); //
							} else {
								$toMonth = strtotime(date("Y-m-01"));
							}
							
							$fromMonth = strtotime(date("Y-m-01",$toMonth). ' - 1 year');
							
							if(!$yeargiven) {
								$fromMonth=strtotime(date("Y-m-01",$fromMonth).' + 1 month'); //
							} else {
								$toMonth=strtotime(date("Y-m-01",$toMonth).' - 1 month'); //
							}
											   
							$months = $database->getMonths(); //Fetch raw data
							
							list($receivedData, $sentData) = filterDataForTimePeriodandIntervalType($months, 'month', $fromMonth, $toMonth); //Filter and pad data
							renderChartSelects($months,'year',$yeartoshow,$monthgraphtype);
							echo "<BR/>";
							renderChartJs([$receivedData, $sentData], 'monthly', $monthgraphtype, $showrec, $showsent,$twoAxes); //Draw the diagram

					} else {
						echo '<em>Select either received or sent data to show diagram.</em>';
					}	
					echo "<h2>Months</h2>";
					renderDataTable($database->getMonths(),'month'); ?>
				</div>
				<div id="top10" class="tabcontent">
					<h2>Top 10 days (lifetime)</h2>
					<?php renderDataTable($database->getTop10(),'top10'); ?>
				</div>
				<div id="compare" class="tabcontent">
					<h2>
						<?php
						if($datetocompare1 != '' and $datetocompare2 != '' and $typetocompare!='') {
							switch($typetocompare) {
								case "day":
									echo "Comparing ".date("Y-m-d (l)",$datetocompare1)." with ".date("Y-m-d (l)",$datetocompare2);
									break;
								case "month":
									echo "Comparing ".date("F Y",$datetocompare1)." with ".date("F Y",$datetocompare2);
									break;
								case "year":
									echo "Comparing ".date("Y",$datetocompare1)." with ".date("Y",$datetocompare2);
									break;
							} 
						} else {
							echo "Select what to compare";
						}
						?>
					</h2>
					<strong>Compare</strong><br/>
					<input type="radio" name="comparetype" value="day" onChange="showCompares('day');" <?php if($typetocompare=='day') { echo "checked"; } ?>/> Days
					<input type="radio" name="comparetype" value="month" onChange="showCompares('month');" <?php if($typetocompare=='month') { echo "checked"; } ?>/> Months
					<input type="radio" name="comparetype" value="year" onChange="showCompares('year');" <?php if($typetocompare=='year') { echo "checked"; } ?>/> Years
					<?php			
					foreach(['day','month','year'] as $type) {
						echo "<div class=\"comparelist\"";
						echo "id='compare{$type}'";
						if($typetocompare==$type) { echo "style='display:block;'"; } else { echo "style='display:none;'"; }
						echo ">";
						
						/* Set parameters for rendering and print base option */
						switch($type) {
							case 'day':
								$dateFormat = 'Y-m-d';
								$dateFormat2 = 'Y-m-d';
								$graphDataType='hour';
								$data=$database->getHours();				
								break;
							case 'month':
								$dateFormat = 'F Y';
								$dateFormat2 = 'Y-m';
								$graphDataType='day';
							$data=$database->getDays();										
								break;
							case 'year':
								$dateFormat = 'Y';
								$dateFormat2 = 'Y-01-01 00:00:00';
								$graphDataType='month';
								$data=$database->getMonths();				
								break;
						}

						$options = [];

						/* Loop data and pull out all viable options in correct format */
						foreach ($data as $datapoint) {
							if($datapoint->getBytesSent() > 0 or $datapoint->getBytesReceived() > 0) {
								$options[date($dateFormat,$datapoint->getDateTime()->getTimestamp())] = strtotime(date($dateFormat2,$datapoint->getDateTime()->getTimestamp()));
							}
						}
						
						arsort($options);
						
						for($i=0;$i<=1;$i++) {
							echo ucfirst($type).' '.($i + 1).": <select class=\"comparetypelist\" id=\"comparelist{$type}{$i}\" >";
							/* Print data as options */
							foreach($options as $date => $time) {
								echo $time;
								echo '<option value="'.$time.'"';
								if($typetocompare==$type and ${'datetocompare'.($i + 1)} == $time) {
									echo ' selected';
								}
								echo '>'.$date.'</option>';
							}
							echo '</select><BR/>';
						}
						echo '</div>';
					}
					echo '<button type="button" name="compare" class="normal '. (($showrec or $showsent) ? '' : 'disabled'). ' " value="Compare"' . (($showrec or $showsent) ? 'onClick="doCompare();' : '').'">Compare</button></BR>';		
					if ($showrec or $showsent) {
						if($datetocompare1 != '' and $datetocompare2 != '' and $typetocompare!='') {
							echo "<table width='100%'><tr>";
							echo "<td align='right'>"; ?>
							Graph type: <select name="comparegraphtype" onChange="this.form.submit();">
								<option value="bar" <?php if($comparegraphtype=='bar') { echo "selected"; } ?>>Bar</option>
								<option value="line" <?php if($comparegraphtype=='line') { echo "selected"; } ?>>Line</option>
							</select><?php
							echo "</td></tr></table>";
							echo "<BR/>";
							renderCompareChart($database,$showrec,$showsent,$comparegraphtype,$datetocompare1,$datetocompare2,$typetocompare,$twoAxes); 
						} else {
							echo '<em>Select what to compare and press button.</em>';
						}
					} else {
						echo '<em>Select either received or sent data to enable comparison and show diagram.</em>';
					}
					?>
				</div>
			</div>
		</form>
		<script type="text/javascript"> // Comparison 
			function showCompares(typeToShow) {
				comparelist = document.getElementsByClassName("comparelist");

				// Hide all and then show the one sent in
				for (i = 0; i < comparelist.length; i++) {
					comparelist[i].style.display = "none";
				}
				document.getElementById('compare'+typeToShow).style.display = "block";
			}
			// Trigger a comparison
			function doCompare() {
				var compareType = document.querySelector('input[name="comparetype"]:checked').value;
				document.getElementById('datetocompare1').value = document.getElementById('comparelist'+compareType+'0').value;
				document.getElementById('datetocompare2').value = document.getElementById('comparelist'+compareType+'1').value;
				document.getElementById('typetocompare').value = compareType;
				document.getElementById('dataForm').submit();
			}
		</script>
		<script type="text/javascript"> // Tab support
			function openPage(pageName, elmnt) {
				document.getElementById('tabtoshow').value=pageName;

				// Hide all elements with class="tabcontent" by default */
				var i, tabcontent, tablinks;
				tabcontent = document.getElementsByClassName("tabcontent");
				for (i = 0; i < tabcontent.length; i++) {
					tabcontent[i].style.display = "none";
				}

				// Remove the background color of all tablinks/buttons
				tablinks = document.getElementsByClassName("tablink");
				for (i = 0; i < tablinks.length; i++) {
					tablinks[i].style.backgroundColor = "";
				}

				// Show the specific tab content
				document.getElementById(pageName).style.display = "block";

				// Add the specific color to the button used to open the tab content
				elmnt.style.backgroundColor = '#5cb85c';

			}

			// Get the element with id="defaultOpen" and click on it
			document.getElementById("defaultOpen").click();
		</script>
		<script type="text/javascript"> // Scroll to right place
			$(window).scroll(function () {
				sessionStorage.scrollTop = $(this).scrollTop();
			});
			$(document).ready(function () {
				if (sessionStorage.scrollTop != "undefined") {
					$(window).scrollTop(sessionStorage.scrollTop);
				}
			});
		</script>
	</body>
</html>
