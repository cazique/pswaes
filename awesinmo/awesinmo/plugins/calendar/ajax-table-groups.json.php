<?php
	/*
	 Returns an array of user groups defined in the current app
	 */

	define('PREPEND_PATH', '/../../');
	@header('Content-type: application/json');

	include(__DIR__ . PREPEND_PATH . 'lib.php');
	
	/* check access */
	$mi = getMemberInfo();
	if($mi['group'] != 'Admins') {
		@header('HTTP/1.0 403 Forbidden');
		die(json_encode([]));
	}

	$groups_tables = get_table_groups();
	$groups = [['id' => '', 'text' => $Translation['none']]];
	$i = 1;
	foreach($groups_tables as $title => $tables) $groups[] = [
		'id' => $i++, 
		'text' => ($title == 'None' ? 'Default' : $title)
	];

	exit(json_encode($groups));
