<?php
	include_once(__DIR__ . '/../../../lib.php');
	
	if(!csrf_token(true))
		MessagesDB::badRequest($Translation['csrf token expired or invalid']);

	// no need to check if user can access notifications or send ... 
	// this is already done in createMessage/updateMessage/sendMessage below

	$inputs = ['id', 'recipients', 'subject', 'message', 'inReplyTo', 'send'];
	foreach($inputs as $var)
		${$var} = Request::val($var);

	$id = intval($id); // if 0 => create, else update message
	if(!is_bool($send)) $send = ($send == 'true');

	// create new message if necessary
	if(!$id && !$id = MessagesDB::createMessage(compact($inputs)))
		MessagesDB::badRequest(MessagesDB::lastError());
	// update message
	elseif(!MessagesDB::updateMessage($id, compact($inputs)))
		MessagesDB::badRequest(MessagesDB::lastError());

	// send requested?
	if($send && !MessagesDB::sendMessage($id))
		MessagesDB::badRequest(MessagesDB::lastError());

	// return messages of provided folder
	include(__DIR__ . '/ajax-get-threads.php');