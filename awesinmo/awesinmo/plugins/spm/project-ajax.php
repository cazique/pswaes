<?php
@include(__DIR__ . "/../plugins-resources/loader.php");

$spm = new AppGiniPlugin([
	'title' => 'Search Page Maker for AppGini',
	'name' => 'spm',
	'logo' => 'spm-logo-lg.png'
]);

$spm->reject_non_admin();

//filter input data
$spm->filter_inputs($_REQUEST);

/**
  *  Save project modifications in project file
  */
if(Request::has('data') && Request::has('tableNumber') && Request::has('projFile')) {
	//validate data 
	if(!preg_match('/^[0-9:]*$/i', Request::val('data'))) exit;

	$nodeData = [
		'projectName'=> Request::val('projFile'),
		'tableIndex' => intval(Request::val('tableNumber')),
		'pluginName' => 'spm',
		'nodeName'   => 'spm_fields',
		'data'		 => Request::val('data')
	];

	//update node with new data after validating it
	if($spm->update_project_plugin_node($nodeData)) echo "ok";
}

/* do we have a path validation request? */
if(Request::val('actionName') != 'validatePath') die(); // nothing else to do

$path = Request::val('path');

if(!is_dir($path)) die('Invalid path.');

if(
	!file_exists("$path/lib.php") 
	|| !file_exists("$path/db.php")
	|| !file_exists("$path/index.php")
) die('The given path is not a valid AppGini application path.');

if(!is_writable("$path/hooks"))
	die('The hooks folder is not writable. Please set its permissions to allow writing -- try "chmod 755" or "chmod 777".');

if(!is_writable("$path/resources"))
	die('The resources folder is not writable. Please set its permissions to allow writing -- try "chmod 755" or "chmod 777".');

echo "ok";