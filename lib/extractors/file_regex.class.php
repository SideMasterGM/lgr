<?php
namespace rrd\extractors {
	class file_regex {
		private $connector;
		private $filename;
		private $regex;

		function __construct($connector, $cfg) {
			$this->connector = $connector;
			if (is_object($cfg)) {
				$cfg = json_decode(json_encode($cfg), 1);
			}
			$this->filename = $cfg['file'];
			$this->regex = $cfg['regex'];
		}
		
		function get($parameters = NULL) {
			if (preg_match($this->regex, $this->connector->file_get_contents($this->filename), $matches)) {
				return array_splice($matches, 1);
			} else {
				return FALSE;
			}
		}
	
	}
}
?>