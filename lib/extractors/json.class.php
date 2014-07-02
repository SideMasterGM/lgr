<?php
namespace rrd\extractors {
	class json {
		private $data = array();

		function __construct($connector, $cfg) {
			foreach($cfg->children() as $cmd) {
				$this->data = array_merge_recursive($this->data, json_decode(call_user_func(array($connector, $cmd->getName()), "{$cmd}"), TRUE));
			}
		}

		function get($sensor) {
			$res = array();
			foreach($sensor->key as $key) {
				$index = explode('/', $key);
				$data = &$this->data;
				foreach($index as $i) {
					if (!array_key_exists($i, $data)) {
						echo "FATAL: key '{$key}' not found\n";
						return FALSE;
					}
					$data = &$data[$i];
				}
//				echo "{$key}: {$data}\n";
				$res[] = intval($data);
			}
//			print_r($res);
			return $res;
		}
	}
}
?>
