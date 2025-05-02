<?php
	include(__DIR__ . '/../../../lib.php');
	@ignore_user_abort(true);
	@set_time_limit(0);

	// compare md5sum of MessagesDB in app-resources to that in lib and update if needed
	$destFile = __DIR__ . '/../../../resources/lib/MessagesDB.php';
	$srcFile = __DIR__ . '/MessagesDB.php';
	if(!is_file($destFile)) die(); // messages not installed
	if(md5_file($srcFile) != md5_file($destFile))
		@copy($srcFile, $destFile);
	
	MessagesDB::checkAccess();

	// if csrf token provided and valid, 
	// extend csrf token for 20 more minutes if it's about to expire 
	// (but not if already expired)
	$expiry = $_SESSION['csrf_token_expiry'] ?? false;
	if($expiry && $expiry < (time() + 300) && $expiry > time() && csrf_token(true))
		$_SESSION['csrf_token_expiry'] = time() + 1200;

	echo MessagesDB::count('unread');
	@session_write_close(); // to prevent session locking if upcoming commands take too long

	MessagesDB::notifyByEmail();