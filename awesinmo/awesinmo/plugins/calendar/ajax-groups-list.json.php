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

	$groups = [];
	$eo = ['silentErrors' => true];
	$res = sql("SELECT `name` FROM `membership_groups` ORDER BY `name`", $eo);
	while($row = db_fetch_assoc($res)) $groups[] = $row['name'];

	echo json_encode($groups);