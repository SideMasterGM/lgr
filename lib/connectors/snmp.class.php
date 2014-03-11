<?php
namespace rrd\connectors {

	class snmp {
		protected $host = 'localhost';
		protected $port = 161;
		protected $community = 'public';
		function __construct($cfg) {
			snmp_set_quick_print(TRUE);
			if ((string) $cfg->host !== '') {
				$this->host = (string) $cfg->host;
			}
			if ((string) $cfg->port !== '') {
				$this->port = intval((string) $cfg->port);
			}
			if ((string) $cfg->community !== '') {
				$this->community = (string) $cfg->community;
			}
			foreach($cfg->mib as $mib) {
				snmp_read_mib((string) $mib);
			}
		}

		function get($object_id, $timeout = 1000000, $retries = 5) {
			return snmpget("{$this->host}:{$this->port}", $this->community, $object_id, $timeout, $retries);
		}
	}
}
?>
