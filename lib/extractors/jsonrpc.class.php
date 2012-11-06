<?php
namespace rrd\extractors {
	class jsonrpc {
		private $rpc;

		function __construct($connector, $cfg) {
			$this->rpc = $connector;
		}
		
		function get($sensor) {
			$method = (string) (isset($sensor->attributes()->method) ? $sensor->attributes()->method : $sensor->attributes()->id);
			try {
				// TODO: investigate the reason exceptions are still uncaught
				$data = call_user_func(array($this->rpc, $method));
			} catch (Exception $e) {
				return FALSE;
			}
			$result = array();
			foreach(preg_split('/\n|\r|\s|\t/', $sensor, NULL, PREG_SPLIT_NO_EMPTY) as $reading) {
				$tmp = $data;
				foreach(preg_split('/\//', $reading) as $key) {
					if (isset($tmp[$key])) {
						$tmp = $tmp[$key];
					} else {
						return FALSE;
					}
				}
				$result[] = $tmp;
			}
			return $result;
		}
	}
}
?>