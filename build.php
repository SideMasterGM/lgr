#!/usr/bin/php
<?php

// do some sanity checks
if (!extension_loaded('phar')) {
	die("phar extension not loaded\n");
} else {
	echo 'phar v' . phpversion('phar') . " loaded\n";
}
if (ini_get('phar.readonly')) {
	die("phar.readonly shall be set to 0 in php.ini\n");
}

$base_name = 'lgr';
$src_name = $base_name . '.php.in';
$archive_name = $base_name . '.phar';
$executable_name = $base_name;

$phar = new Phar($archive_name, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $archive_name);
$phar->addFile($src_name, $executable_name);
$phar->buildFromDirectory('./', '/\.php$/');
$phar->setStub("#!/usr/bin/env php\n<?php Phar::mapPhar('lgr.phar'); require 'phar://lgr.phar/lgr'; __HALT_COMPILER();");
$phar->compressFiles(Phar::GZ);
unset($phar);
rename($archive_name, $executable_name);
chmod($executable_name, 755);
echo "{$executable_name} has been created\n";
?>