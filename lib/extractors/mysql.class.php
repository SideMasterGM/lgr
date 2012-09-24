<?php
namespace rrd\extractors {
	class mysql {
		private $connector;
		
		function __construct($connector, $cfg) {
			$this->connector = $connector;
		}
		
		function get($sensor) {
			$res = preg_split('/\n/', $this->connector->exec("mysql -e \"{$sensor}\""));
			if (preg_match('/(\d+).+?(\d+)/', $res[1], $matches)) {
				return array($matches[1], $matches[2]);
			} else {
				return FALSE;
			}
		}
	}
}
?>