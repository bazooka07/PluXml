<?php
# http://php.net/manual/fr/session.upload-progress.php

const PREFIX = 'session.upload_progress.';
const DEBUG = false;

session_start();

if(filter_input(INPUT_GET, 'key', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '@^\w{16}$@')))) {
	# Ok pour le format de la clé
	$key = ini_get(PREFIX .'prefix') . $_GET['key'];
	if(empty($_SESSION[$key])) {
		# on a la bonne clé
		header('HTTP/1.0 404 Not Found');
		header('Content-Type: text/plain; charset=UTF-8');
		if(DEBUG) {
			print_r($_SESSION);
			echo "\n\n";
		}
	    exit;
	}
	$output = json_encode($_SESSION[$key], JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	header('Content-Type: application/json; charset=UTF-8');
	header('Content-Length: '.strlen($output));
	header('Expires: Tue, 01 May 2018 00:00:00 GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: post-check=0, pre-check=0', FALSE);
	header('Pragma: no-cache');
	echo $output;
	exit;
} elseif(DEBUG and isset($_GET['debug'])) {
	header('Content-Type: text/plain; charset=UTF-8');
	header('Expires: Tue, 01 May 2018 00:00:00 GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: post-check=0, pre-check=0', FALSE);
	header('Pragma: no-cache');

	# grep ';session.upload' /etc/php/7.2/fpm/php.ini | sed -E 's/^.*progress\.(\w+).*/\1/'
	$all_keys = explode("\n", trim('
enabled
cleanup
prefix
name
freq
min_freq
	'));
	echo 'Add this prefix to the following keys : "'.PREFIX."\"\n\n";
	foreach($all_keys as $key) {
		printf("%-10s : %s\n", $key, ini_get(PREFIX .$key));
	}
	echo "\n\n";
} else {
	header('HTTP/1.0 405 Method Not Allowed');
	header('Content-Type: text/plain; charset=UTF-8');
	echo "GoodBye\n\n";
}
?>
