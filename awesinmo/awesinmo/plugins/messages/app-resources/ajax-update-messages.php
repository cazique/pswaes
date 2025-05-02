<?php
	include_once(__DIR__ . '/../../../lib.php');
	
	if(!csrf_token(true))
		MessagesDB::badRequest($Translation['csrf token expired or invalid']);

	$ids = MessagesDB::getRequestIds(true); // as array
	if(empty($ids)) MessagesDB::badRequest('No message(s) specified');

	$markedUnread = Request::val('markedUnread', null);
	$starred = Request::val('starred', null);

	if($markedUnread === null && $starred === null)
		exit('Nothing to do');

	// if a single message, marking as read, enforce business logic
	if(count($ids) == 1 && $markedUnread !== null && !$markedUnread) {
		MessagesDB::readMessage($ids[0]);
		include(__DIR__ . '/ajax-get-threads.php');
		exit(); // 200 OK
	}

	$res = null;

	// mark as read/unread?
	if($markedUnread !== null)
		$res = MessagesDB::massUpdate($ids, ['markedUnread' => $markedUnread]);

	// mark as starred/unstarred?
	if($starred !== null)
		$res = MessagesDB::massUpdate($ids, ['starred' => $starred]);

	if($res === false) MessagesDB::badRequest(MessagesDB::lastError());

	if($res) 
		// return updated threads
		include(__DIR__ . '/ajax-get-threads.php');