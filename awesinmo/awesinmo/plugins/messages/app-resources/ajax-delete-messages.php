<?php
	include_once(__DIR__ . '/../../../lib.php');
	
	if(!csrf_token(true))
		MessagesDB::badRequest($Translation['csrf token expired or invalid']);

	MessagesDB::checkAccess();

	// pass ids (from request) as array to delete method
	if(!MessagesDB::delete(MessagesDB::getRequestIds(true)))
		MessagesDB::badRequest(MessagesDB::lastError() ?? "Can't delete message(s)");

	// return updated threads
	include(__DIR__ . '/ajax-get-threads.php');
