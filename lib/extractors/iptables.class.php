<?php
namespace rrd\extractors {
	class iptables {
		private $iptables = array();
		
		function __construct($connector, $cfg) {
			foreach (preg_split('/\n/', $connector->exec('iptables-save -c')) as $line) {
				// process line by line
				if (preg_match('/--comment\s+"([^"]+)"/', $line, $matches)) {
					// line with comment found
					$comment = $matches[1];
					if (preg_match('/\[(\d+):(\d+)\]/', $line, $matches)) {
						// extract counters
						$this->iptables["{$comment}:packets"] = $matches[1];
						$this->iptables["{$comment}:bytes"] = $matches[2];
					}
				}
			}
		}
		
		function get($sensor) {
			if (empty($this->iptables)) {
				return FALSE;
			}
			$result = array();
			foreach(preg_split('/\s+/', $sensor) as $reading) {
				$r = 0;
				foreach($this->iptables as $k => $v) {
					if ((stripos($k, (string) $sensor->attributes()->id) !== FALSE) and (stripos($k, $reading) !== FALSE)) {
						$r = $v;
						break;
					}
				}
				$result[] = $r;
			}
			return $result;
		}
	}
}
?>