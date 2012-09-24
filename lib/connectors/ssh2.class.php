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
			$this->ssh2 = ssh2_connect($cfg['host'], $cfg['port']) or die("can't connect trough ssh2\n");

			// authorize
			if (isset($cfg['key'])) {
				// private/public key authentication requested
				ssh2_auth_pubkey_file($this->ssh2, $cfg['user'], $cfg['key']['pub'], $cfg['key']['pvt'], isset($cfg['key']['pass']) ? $cfg['key']['pass'] : NULL) or die("can't authorize via key\n");
			} elseif (isset($cfg['pass'])) {
				// username & password authentication
				ssh2_auth_password($this->ssh2, $cfg['user'], $cfg['pass']) or die("can't authorize via user & pass\n");
			} else {
				die("not enough authentication information provided\n");
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
			$stdout = ssh2_exec($this->ssh2, $command) or die("can't execute {$command}\n");
			// set stdour stream to blocking as it is non-blocking by default
			stream_set_blocking($stdout, true);
			$out = stream_get_contents($stdout);
			fclose($stdout);
			return $out;
		}
	}
}
?>