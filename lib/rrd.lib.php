<?php
namespace rrd {

	use Exception;
	spl_autoload_register(__NAMESPACE__ .'\__autoload');
	
	function __autoload($call) {
		$args = preg_split('/\\\/', $call);

		if ($args[0] === 'rrd' and count($args) === 3) {
			$file = __DIR__ . DIRECTORY_SEPARATOR . $args[1] . DIRECTORY_SEPARATOR . $args[2] . '.class.php';
		} elseif ($args[0] === 'rrd' and count($args) === 2) {
			$file = __DIR__ . DIRECTORY_SEPARATOR . $args[0] . '.' . $args[1] . '.class.php';
		} else {
			throw new Exception("Invalid call `{$call}`");
		}
		
		if (file_exists ($file)) {
			require_once $file;
		} else {
			throw new Exception("Can't load library file `{$file}`");
		}
	}
}
?>