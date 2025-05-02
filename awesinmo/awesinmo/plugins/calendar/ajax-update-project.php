<?php
	include(__DIR__ . '/calendar.php');
	
	/*
		REQUEST includes the following:
			axp: md5 hash of project
			data: the json string of data to update the plugins/calendar node

		Structure of AXP:
		/ (database node)
		|-- plugins
		      |-- calendar
		            |-- data (this is where a JSON string of plugin data is stored)
		                     the json object should include 2 keys: 'events' and 'calendars'
		                     each key's value is in turn an object that contains 0 or more
		                     event/calendar objects (resp). Each event/calendar object is
		                     identified by its key.
	*/
	
	$calendar = new calendar([
		  'title' => 'Calendar',
		  'name' => 'calendar', 
		  'logo' => 'calendar-logo-lg.png' 
	]);
	
	/* grant access to the groups 'Admins' only */
	$calendar->reject_non_admin('Access denied');

	$axp_md5 = Request::val('axp');
	if(!$axp_md5) $calendar->ajax_json_error('Missing axp parameter');

	$data_json = Request::val('data');
	if(!is_string($data_json) || !strlen($data_json))
		$calendar->ajax_json_error('Invalid data');

	$data = json_decode($data_json, true);
	if(!isset($data['events']) && !isset($data['calendars']))
		$calendar->ajax_json_error('Invalid data');

	$projectFile = '';
	$xmlFile = $calendar->get_xml_file($axp_md5, $projectFile);
	if(!$projectFile) $calendar->ajax_json_error('Project file invalid or not found');
	
	$calendar->update_project_plugin_node([
		'projectName' => $projectFile,
		'pluginName' => 'calendar',
		'nodeName' => 'data',
		'data' => $data_json
	]);

	// this is a hack for AppGini 5.82 and lower to avoid losing
	// plugin settings when AppGini is saving the AXP :/
	$calendar->update_project_plugin_node([
		'projectName' => $projectFile,
		'pluginName' => 'calendar_dup',
		'nodeName' => 'data',
		'data' => 'dummy'
	]);

	$calendar->ajax_json_return('OK');