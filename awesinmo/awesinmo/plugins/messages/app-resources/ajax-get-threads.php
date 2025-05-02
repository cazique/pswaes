<?php
	include_once(__DIR__ . '/../../../lib.php');
	
	if(!csrf_token(true))
		MessagesDB::badRequest($Translation['csrf token expired or invalid']);

	MessagesDB::checkAccess();

	$search = Request::val('search');
	$updateTS = Request::val('updateTS'); // TODO: retrieve updates since updateTS only

	header('Content-type: application/json');
	$threads = MessagesDB::getThreads();
	// $count = MessagesDB::count($folder, $search); --- TODO
	echo json_encode([
		'search' => $search,
		'threads' => $threads,
		'first' => 1, // TODO
		'last' => 1, // TODO
		'count' => 1, // TODO
	]);