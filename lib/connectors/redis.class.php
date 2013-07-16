<?php
namespace rrd\connectors {

	class redis {
		protected $redis = NULL;
		function __construct($cfg) {
			$this->redis = new \Redis();
			$this->redis->connect((string) $cfg->host, (string) $cfg->port === '' ? NULL : (string) $cfg->port);
			if ((string) $cfg->password !== '') {
				$this->redis->auth((string) $cfg->password);
			}
			if ((string) $cfg->dbindex !== '') {
				$this->redis->select((string) $cfg->dbindex);
			}
		}

		function __destruct()  {
			$this->redis->close();
		}

		public function hGetAll($key) {
			return $this->redis->hGetAll($key);
		}

		public function hMGet($key, $values) {
			return $this->redis->hMGet($key, $values);
		}
	}
}
?>
