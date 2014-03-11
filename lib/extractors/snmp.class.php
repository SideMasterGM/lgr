<?php
namespace rrd\extractors {
	class snmp {
		protected $snmp = NULL;
		function __construct($connector, $cfg) {
			$this->snmp = $connector;
		}

		function get($sensor) {
			foreach ($sensor->oid as $oid) {
				if (($v = $this->snmp->get((string) $oid)) === FALSE) {
					return FALSE;
				}
				$res[] = $v;
			}
			return $res;
		}
	}
}
?>
