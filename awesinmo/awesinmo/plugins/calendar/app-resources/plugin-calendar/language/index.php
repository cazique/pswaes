<?php
	// return a list of available languages as a json array
	$languages = [];
	$d = dir(__DIR__);
	while(false !== ($entry = $d->read())) {
		$m = [];
		if(!preg_match('/^([a-z]{2})\.js$/', $entry, $m)) continue;
		$languages[] = $m[1];
	}
	$d->close();

	@header('Content-type: application/json');
	echo json_encode($languages);