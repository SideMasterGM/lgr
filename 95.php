#!/usr/bin/env php
<?php

$step = 300; // 5 mintes * 60 seconds = 300 seconds
$padding = 15;

$now = floor(time() / $step) * $step;
$debug = FALSE;

switch ($argc) {
	case 2:
		$samples = array('last');
		$periods = array($argv[1]);
		break;
	case 3:
		$samples = array($argv[1]);
		$periods = array($argv[2]);
		break;
	default:
		$samples = array('last');
		$periods = array('day', 'week', 'month');
}

$dates = array(
	'day' => array(
		'current' => 'today midnight',
		'last' => '1 day ago',
		'previous' => 'yesterday midnight',
	),
	'week' => array(
		'current' => 'this week midnight',
		'last' => '1 week ago',
		'previous' => 'last week midnight',
	),
	'month' => array(
		'current' => 'first day of this month midnight',
		'last' => '1 month ago',
		'previous' => 'first day of last month midnight',
	),
	'year' => array(
		'current' => '1 January this year',
		'last' => '1 year ago',
		'previous' => '1 January last year',
	),
);

foreach($periods as $period) {
	foreach($samples as $sample) {
		$start = floor(mkstamp($dates[$period][$sample]) / $step) * $step;
		switch($sample) {
			case 'current':
				$end = $now;
				break;
			case 'last':
				$end = $now;
				break;
			case 'previous':
				$end = floor(mkstamp($dates[$period]['current']) / $step) * $step;
				break;
			default:
				die("{$sample} is not supported sample for {$period} period\n");
		}
		$diff = $end - $start;
		$steps = floor($diff / $step);
		$end = $start + $steps * $step;
		$diff = $end - $start;
		$ninetyfive = floor($steps * 0.95);

		debug("\n{$sample} {$period}: " . date('d.m.Y/H:i:s', $start) . ' - ' . date('d.m.Y/H:i:s', $end) . ' (' . number_format($diff) . " seconds, {$steps} steps, {$ninetyfive}th is 95th percentile)\n");

		$export = <<<EXPORT
		/usr/bin/rrdtool \
			xport -m {$steps} -s {$start} -e {$end} --step {$step} \
			DEF:raw_inet1=/z/lgr/var/rrd/abb/95dpi1.all.inet.in.rrd:bytes:AVERAGE \
			DEF:raw_inet2=/z/lgr/var/rrd/abb/95dpi2.all.inet.in.rrd:bytes:AVERAGE \
			DEF:raw_imq21=/z/lgr/var/rrd/abb/95dpi1.imq2.rrd:in_bytes:AVERAGE \
			DEF:raw_imq41=/z/lgr/var/rrd/abb/95dpi1.imq4.rrd:in_bytes:AVERAGE \
			DEF:raw_imq22=/z/lgr/var/rrd/abb/95dpi2.imq2.rrd:in_bytes:AVERAGE \
			DEF:raw_imq42=/z/lgr/var/rrd/abb/95dpi2.imq4.rrd:in_bytes:AVERAGE \
			CDEF:inet1=raw_inet1,UN,0,raw_inet1,IF \
			CDEF:inet2=raw_inet2,UN,0,raw_inet2,IF \
			CDEF:imq21=raw_imq21,UN,0,raw_imq21,IF \
			CDEF:imq41=raw_imq41,UN,0,raw_imq41,IF \
			CDEF:imq22=raw_imq22,UN,0,raw_imq22,IF \
			CDEF:imq42=raw_imq42,UN,0,raw_imq42,IF \
			CDEF:local1=imq21,imq41,+ \
			CDEF:local2=imq22,imq42,+ \
			CDEF:inet=inet1,inet2,+ \
			CDEF:local=local1,local2,+ \
			CDEF:total=inet,local,+ \
			CDEF:dpi1=inet1,local1,+ \
			CDEF:dpi2=inet2,local2,+ \
			XPORT:inet1:"internet dpi1" \
			XPORT:inet2:"internet dpi2" \
			XPORT:inet:"internet total" \
			XPORT:local1:"local dpi1" \
			XPORT:local2:"local dpi2" \
			XPORT:local:"local total" \
			XPORT:dpi1:"total dpi1" \
			XPORT:dpi2:"total dpi2" \
			XPORT:total:"total total"
EXPORT;

		$output = array();
		debug('process: exporting from rrd... ');
		exec($export, $output, $err);
		if ($err) {
			die(" exit({$err}): {$export}\n");
		}

		debug('importing xml dump... ');
		// xml to array idea taken from http://www.php.net/manual/en/book.simplexml.php#105330
//		echo "SimpleXMLElement\n";
		$xml = new SimpleXMLElement(join("\n", $output));
//		echo "json_encode\n";
		$json = json_encode($xml);
//		echo "json_decode\n";
		$xml = json_decode($json, TRUE);
//		print_r($xml); exit;

		if ($xml['meta']['rows'] < $steps) {
			die(" {$xml['meta']['rows']} steps instead of {$steps}\n");
		}

		// extract data
		debug('extracting... ');
		$columns = $xml['meta']['columns'];
		$legend = $xml['meta']['legend']['entry'];
		$data = array();
		for ($s = 0; $s < $steps; $s++) {
			for ($i = 0; $i < $columns; $i++) {
				$data[$legend[$i]][$xml['data']['row'][$s]['t']] = $xml['data']['row'][$s]['v'][$i];
			}
		}

		// array sort
		debug('normalizing... ');
		foreach($data as $counter => $set) {
			normalize($set);
		}

		// array sort
		debug(' sorting...');
		foreach($data as $counter => $set) {
			asort($set, SORT_NUMERIC);
			$data[$counter] = $set;
		}

		debug(' beautifying...');
		$res = array(array(str_pad("{$sample} {$period}", $padding, ' ', STR_PAD_LEFT)));
		$cfg = array(
			array(
				'internet' => 1,
				'local' => 2,
				'total' => 3,
			),
			array(
				'dpi1' => 1,
				'dpi2' => 2,
				'total' => 3,
			)
		);
		foreach($legend as $i => $counter) {
			// percentile extraction and formatting
			list($percentile) = array_slice($data[$counter], $ninetyfive);
			$speed = number_format(($percentile * 8) / pow(1000, 2));

			// output table preparation
			$keys = explode(' ', $counter);
			$res[0][$cfg[1][$keys[1]]] = str_pad($keys[1], $padding, ' ', STR_PAD_LEFT);
			$res[$cfg[0][$keys[0]]][0] = str_pad($keys[0], $padding, ' ', STR_PAD_LEFT);
			$res[$cfg[0][$keys[0]]][$cfg[1][$keys[1]]] = str_pad("{$speed} MBps", $padding, ' ', STR_PAD_LEFT);
		}

		debug(" done. voila!\n");
		echo "\n";
		foreach($res as $col => $row) {
			echo join($row) . PHP_EOL;
		}
	}
}
exit;

function normalize(&$set) {
	// TODO
}

function mkstamp($when) {
	$timestamp = new DateTime($when);
	if (strpos($when, 'week') !== FALSE and $timestamp->format('N') == 1) {
		// fix PHP bug: Sunday-starting weeks
		$timestamp->modify('7 days ago');
	}
	return $timestamp->format('U');
}

function debug($msg) {
	if ($GLOBALS['debug']) {
		echo $msg;
	}
}
function bytes($a) {
	static $unim = array("B","KB","MB","GB","TB","PB");
	$c = 0;
	while ($a>=1024) {
		$c++;
		$a = $a/1024;
	}
	return number_format($a,($c ? 2 : 0),",",".").' '.$unim[$c].'bps';
}


?>
