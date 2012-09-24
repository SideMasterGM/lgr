<?php
namespace rrd\connectors {
	class local {
		function file_get_contents($filename) {
			return file_get_contents($filename);
		}

		function exec($command) {
			return shell_exec($command);
		}
	}
}
?>