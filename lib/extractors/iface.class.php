<?php
namespace rrd\extractors {
	class iface {
		private $interfaces = array();
		
		function __construct($connector, $cfg) {
			$proc_net_dev = array_slice(preg_split('/\n/', $connector->file_get_contents('/proc/net/dev')), 1, -1);
			$meta = array_slice(preg_split('/\|(\s+)?/', array_shift($proc_net_dev)), 1);
			foreach($meta as $i => $m) {
				$meta[$i] = preg_split('/\s+/', $m);
			}
			foreach($proc_net_dev as $line) {
				if (preg_match('/^([^\:]+)\:\s+(.+)$/', trim($line), $matches)) {
					$stats = preg_split('/\s+/', $matches[2]);
					$this->interfaces[$matches[1]] = array(
						'in' => array_combine($meta[0], array_slice($stats, 0, count($meta[0]))),
						'out' => array_combine($meta[1], array_slice($stats, count($meta[0]))),
					);
				}
			}
		}
		
		function get($sensor = NULL) {
			if (empty($this->interfaces)) {
				return FALSE;
			}
			$result = array();
			foreach (preg_split('/\s+/', $sensor) as $i => $reading) {
				$reading = preg_split('/[\/:]/', $reading);
				$iface = (string) (!empty($sensor->attributes()->interface) ? $sensor->attributes()->interface : $sensor->attributes()->id);
				$result[$i] = bcadd((isset($result[$i]) ? $result[$i] : 0), $this->interfaces[$iface][$reading[0]][$reading[1]]);
			}
			return $result;
		}
	}
}
?>