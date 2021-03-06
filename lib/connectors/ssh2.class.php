<?php
namespace rrd\connectors {

	class ssh2 {
		// requires libssh2-php package under ubuntu
		private $ssh2;
		private $sftp;

		function __construct($cfg) {

			// set default options if missing
			static $defaults = array(
				'port' => 22,
				'user' => 'root',
			);
			if (is_object($cfg)) {
				$cfg = json_decode(json_encode($cfg), 1);
			}
			foreach($defaults as $k => $v) {
				if (!isset($cfg[$k])) {
					$cfg[$k] = $v;
				}
			}

			// connect ssh2
			$this->ssh2 = ssh2_connect($cfg['host'], $cfg['port']);
			if (!$this->ssh2) {
				throw new \Exception("can't connect trough ssh2\n");
			}

			// authorize
			if (isset($cfg['key'])) {
				// private/public key authentication requested
				if (!ssh2_auth_pubkey_file($this->ssh2, $cfg['user'], $cfg['key']['pub'], $cfg['key']['pvt'], isset($cfg['key']['pass']) ? $cfg['key']['pass'] : NULL)) {
					throw new \Exception("can't authorize via key");
				}
			} elseif (isset($cfg['pass'])) {
				// username & password authentication
				if (!ssh2_auth_password($this->ssh2, $cfg['user'], $cfg['pass'])) {
					throw new \Exception("can't authorize via user & pass");
				}
			} else {
				throw new \Exception("not enough authentication information provided");
			}
			$this->sftp = ssh2_sftp($this->ssh2);
		}

		function __destruct() {
			// TODO: find way how to free up $this->ssh2 and $this->sftp resources. according to fclose() "supplied resource is not a valid stream resource"
		}

		function file_get_contents($filename) {
			return file_get_contents("ssh2.sftp://{$this->sftp}{$filename}");
		}

		function exec($command) {
			$stdout = ssh2_exec($this->ssh2, $command);
			if (!$stdout) {
				throw new \Exception("can't execute {$command}");
			}
			// set stdour stream to blocking as it is non-blocking by default
			stream_set_blocking($stdout, true);
			$out = stream_get_contents($stdout);
			fclose($stdout);
			return $out;
		}
	}
}
?>
