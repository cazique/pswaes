<?php
	include(__DIR__ . '/mass_update.php');
	
	/*
		REQUEST includes the following:
			axp: md5 hash of project
			tableName: container table name
			hash: hash ID of current command
			label : label of editing command
			icon: name of command icon
			field: field name will be edited
			value: 'fixedValue', 'checked', 'unchecked', 'allowUserToSpecify', 'toggle', ...
			fixedValue: the value to set field to in case of value='fixedValue'
			group: names of groups allowed to access the command, one per line
			confirmation: 0 or 1, whether to ask for confirmation before executing command
			triggerUpdateHooks: 0 or 1, whether to trigger update hooks when executing command
	*/
	
	$mass_update = new mass_update([
		  'title' => 'Mass Update',
		  'name' => 'mass_update', 
		  'logo' => 'mass_update-logo-lg.png' 
	]);
	
	/* grant access to the groups 'Admins' only */
	$mass_update->reject_non_admin('Access denied');

	$axp_md5 = Request::val('axp');
	if(!$axp_md5) $mass_update->ajax_json_error('Missing axp parameter');

	$projectFile = '';
	$xmlFile = $mass_update->get_xml_file($axp_md5, $projectFile);
	if(!$projectFile) $mass_update->ajax_json_error('Project file invalid or not found');
	
	$table_index = $mass_update->table_index(Request::val('tableName'));
	if($table_index < 0) $mass_update->ajax_json_error('Invalid tableName parameter');
	$table = $mass_update->table(Request::val('tableName'));

	$field = $mass_update->field(Request::val('tableName'), Request::val('field'));
	if($field === false) $mass_update->ajax_json_error('Invalid field parameter');

	$hash = Request::val('hash');
 
 	// check if this is a new command or existing one
 	// and if new, or hash not found, append to commands array
	$new_command = false;
	$stored_commands = $mass_update->get_table_plugin_node(Request::val('tableName'));
	if($stored_commands === false) {
		$stored_commands = new stdClass();
		$new_command = true;
	}
	
	if(!isset($stored_commands->command_details)) {
		$stored_commands->command_details = '[]';
		$new_command = true;
	}
	
	$commands = json_decode($stored_commands->command_details, true);
	if(!is_array($commands)) {
		$commands = [];
		$new_command = true;
	}

	// re-structure commands as a 0-based numeric array
	$commands = array_values($commands);

	if(!$new_command) {
		// find the command to update by looking for command hash
		$new_command = true;
		$command_index = -1;
		for($i = 0; $i < count($commands); $i++) {
			if($commands[$i]['hash'] != $hash) continue;

			$new_command = false;
			$command_index = $i;
			break;
		}
	}

	if($new_command) $command_index = count($commands);

	$commands[$command_index] = [
		'label' => Request::val('label'),
		'icon' => Request::val('icon'),
		'field' => (string) $field->name,
		'value' => Request::val('value'),
		'fixedValue' => Request::val('fixedValue'),
		'confirmation' => intval(Request::val('confirmation')) == 0 ? 0 : 1,
		'triggerUpdateHooks' => intval(Request::val('triggerUpdateHooks')) == 0 ? 0 : 1,
		'groups' => preg_split('/[\r|\n|\r\n]+/', Request::val('groups'), NULL, PREG_SPLIT_NO_EMPTY),
		'hash' => Request::val('hash')
	];

	$mass_update->update_project_plugin_node([
		'projectName' => $projectFile,
		'tableIndex' => $table_index,
		'pluginName' => 'mass_update',
		'nodeName' => 'command_details',
		'data' => json_encode($commands)
	]);

	$mass_update->ajax_json_return([
		'tableName' => (string) $table->name,
		'tableIndex' => $table_index,
		'commands' => $commands
	]);