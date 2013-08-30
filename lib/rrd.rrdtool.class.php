<?php
namespace rrd {
	class rrdtool {

		protected $cfg; // global config
		protected $rrd_process; // proc_open resource id
		protected $rrd_pipes; // rrdtool stdout and stdin pipes

		function __construct($cfg) {
			$this->cfg = $cfg;
			// create a pipe in order to communicate with rrdtool over its stdin/stdout
			$descriptorspec = array(
				array('pipe', 'r'),
				array('pipe', 'w'),
			);
			$this->rrd_process = proc_open('/usr/bin/rrdtool -', $descriptorspec, $this->rrd_pipes);
			if (!is_resource($this->rrd_process)) {
				throw new Exception('Unable to start rrdtool');
			}
		}

		function __destruct() {
			foreach($this->rrd_pipes as $pipe) {
				fclose($pipe);
			}
			proc_close($this->rrd_process);
		}

		function exec($command) {
			// the method provides option to supply multiple commands at once. if signle command make it array in order to preserve the multi-command logic
			if (is_string($command)) {
				$command = array($command);
			}

			// cycle trough commands and execute them
			foreach($command as $rrdcmd) {
//				echo "\t\t\trrdtool {$rrdcmd}\n";
				if (!fwrite($this->rrd_pipes[0], "{$rrdcmd}\n")) {
					die("failed command: {$rrdcmd}\n");
					return FALSE;
				}
				while ($res = fgets($this->rrd_pipes[1])) {
					if (preg_match('/^(OK)\s|(ERROR):/', $res, $matches)) {
						break;
					}
				}
				if (isset($res)) {
//					echo "\t\t\t{$res}";
					if (!preg_match('/^OK/', $res))
						echo "RRD {$res}\n";
				}
			}

			return TRUE;
		}

		function create($filename, $schema_id) {
			$cmd = array();

			if (pathinfo($filename, PATHINFO_DIRNAME) === '.') {
				$filename = $this->cfg->paths->db . DIRECTORY_SEPARATOR . $filename;
			}

			$schema = array_pop($this->cfg->xpath("schemas/schema[@id='{$schema_id}']"));
			$archive = array_pop($this->cfg->xpath("archives/archive[@id='{$schema->attributes()->archive}']"));

			$cmd[] = "create {$filename}";
			if (!empty($schema->attributes()->step)) {
				$cmd[] = '--step ' . $schema->attributes()->step;
			}

			// set DS
			static $ds_defaults = array(
				'type' => 'COUNTER',
				'heartbeat' => '300',
				'min' => 'U',
				'max' => 'U',
			);
			foreach($schema as $data_sources) {
				foreach($data_sources as $ds) {
					$rec = array();
					foreach($ds_defaults as $k => $v) {
						$rec[$k] = !empty($ds->attributes()->$k) ? (string) $ds->attributes()->$k : $v;
					}
					$cmd[] = 'DS:' . $ds->attributes()->id . ':' . join(':',$rec);
				}
			}

			// set RRA
			foreach($archive as $rra) {
				$cmd[] = 'RRA:' . $rra->attributes()->cf . ':' . $rra->attributes()->xff . ':' . $rra->attributes()->steps . ':' . $rra->attributes()->rows;
			}

			// execute the command
			return $this->exec(join(' ', $cmd));
		}

		function update($filename, &$data, $schema_id = NULL) {
			if (pathinfo($filename, PATHINFO_DIRNAME) === '.') {
				$filename = $this->cfg->paths->db . DIRECTORY_SEPARATOR . $filename;
			}
			if (!file_exists($filename)) {
				$this->create($filename, $schema_id);
			}
			return $this->exec("update {$filename} N:" . join(':', $data));
		}

		function graph($file) {
			return $this->exec("graph {$file} ");
		}
	}
}
?>
