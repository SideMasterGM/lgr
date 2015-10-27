<?php
namespace rrd\extractors {
	class key_value {
		private $data = array();

		function __construct($connector, $cfg) {
			if ($cfg->file) {
				$cmd = "file";
				$param = "{$cfg->file}";
			} else if ($cfg->exec) {
				$cmd = "exec";
				$param = "{$cfg->exec}";
			} else {
				echo "FATAL: 'exec' or 'file' is required\n";
				return false;
			}
			$delim = $cfg->delimiter ? "{$cfg->delimiter}" : ",";
			$tmp = call_user_func(array($connector, $cmd), $param);
			$tmp = explode("\n",$tmp);
			foreach ($tmp as $line) {
				if(preg_match("/^([^{$delim}]+){$delim}(.+)$/", $line, $matches)) {
					$this->data[$matches[1]] = $matches[2];
				}
			}
		}

		function get($sensor) {
			$res = array();
			foreach($sensor->key as $key) {
				$key = "{$key}";
				if (!array_key_exists($key, $this->data)) {
					echo "FATAL: key '{$key}' not found\n";
					return FALSE;
				}
				$res[] = intval($this->data[$key]);
			}
			return $res;
		}
	}
}
?>
