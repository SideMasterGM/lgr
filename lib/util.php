<?php

// makes a relative path absolute
function absolute_path($filename, $base_path = NULL) {
	if ($filename[0] !== DIRECTORY_SEPARATOR) {
		// prepend base path to file name if it's name doesn't start with slash
		if (empty($base_path)) {
			// get current directory as default base path
			$base_path = getcwd() . DIRECTORY_SEPARATOR;
		}
		$filename = $base_path . DIRECTORY_SEPARATOR . $filename;
	}
	$pathinfo = pathinfo($filename);	
	$path = array();
	foreach(preg_split('/\\' . DIRECTORY_SEPARATOR . '/', $pathinfo['dirname'], NULL, PREG_SPLIT_NO_EMPTY) as $dir) {
		// cycle trough directories in the path
		switch($dir) {
			case '.':
				// do nothing
				break;
			case '..':
				// remove last directory from the stack
				array_pop($path);
				break;
			default:
				// add current directory to the stack
				$path[] = $dir;
		}
	}
	return DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $path) . DIRECTORY_SEPARATOR . $pathinfo['basename'];
}

//
function make_dir($dir) {
	$path = '';
	foreach(preg_split('/\\' . DIRECTORY_SEPARATOR . '/', $dir) as $sub_dir) {
		$path .= DIRECTORY_SEPARATOR . $sub_dir;
		if (!is_dir($path)) {
			mkdir($path);
		}
	}
}

?>