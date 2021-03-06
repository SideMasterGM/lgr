<?php
namespace rrd\extractors {
	class redis {
		private $redis;

		function __construct($connector, $cfg) {
			$this->redis = $connector;
		}

		function get($sensor) {
			$params = preg_split('/\n|\r|\s|\t/', $sensor, NULL, PREG_SPLIT_NO_EMPTY);
			try {
				return array_values(call_user_func(array($this->redis, (string) $sensor->attributes()->method), (string) $sensor->attributes()->id, empty($params) ? NULL : $params));
			} catch (Exception $e) {
				return FALSE;
			}
		}
	}
}
?>
