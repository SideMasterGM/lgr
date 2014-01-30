<?php
namespace rrd\extractors {
	class tc {
		private $connector;

		function __construct($connector, $cfg) {
			$this->connector = $connector;
		}

		function get($sensor) {
			$tc = preg_split('/\n/', $this->connector->exec("/sbin/tc -s -d class show dev {$sensor->attributes()->interface} |grep -A5 \"{$sensor}\""));
			if (preg_match('/^\s+Sent\s(\d+)\s+bytes\s+(\d+)\spkt/', $tc[1], $matches)) {
				return array($matches[1], $matches[2]);
			} else {
				return FALSE;
			}
		}
	}
}
?>
