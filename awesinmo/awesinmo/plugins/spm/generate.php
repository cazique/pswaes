<?php
	include(__DIR__ . "/header.php");
	include(__DIR__ . "/generate-functions.php");

	// validate project name
	$axp_md5 = Request::val('axp');
	if(!$axp_md5 || !preg_match('/^[a-f0-9]{32}$/i', $axp_md5))
		die("<br>" . $spm->error_message('Project file not found.'));

	$projectFile = '';
	$xmlFile = $spm->get_xml_file($axp_md5, $projectFile);

	//-------------------------------------------------------------------------------------
	//path check 
	try {
		if(!Request::val('path')) throw new RuntimeException('No path provided');
		
		$path = rtrim(trim(Request::val('path')), '\\/');

		if(!is_dir($path)) throw new RuntimeException('Invalid path');

		if(
			!file_exists("$path/lib.php")
			|| !file_exists("$path/db.php")
			|| !file_exists("$path/index.php")
		) throw new RuntimeException('The given path is not a valid AppGini project path');
		
		if(!is_writable("$path/hooks"))
			throw new RuntimeException('The hooks folder of the given path is not writable');
		
		if(!is_writable("$path/resources"))
			throw new RuntimeException('The resources folder of the given path is not writable');

	} catch (RuntimeException $e) {
		die("<br>" . $spm->error_message($e->getMessage()));
	}
	//-------------------------------------------------------------------------------------

	$write_to_hooks = !Request::val('dont_write_to_hooks');

	echo $spm->header_nav();

	echo $spm->breadcrumb([
		'index.php' => 'Projects',
		'project.php?axp=' . urlencode($axp_md5) => substr($projectFile, 0, -4),
		'output-folder.php?axp=' . urlencode($axp_md5) => 'Output folder',
		'' => 'Generating search pages'
	]);

?>

<h4>Progress log</h4>

<?php

	$spm->progress_log->add("Output folder: $path", 'text-info');

	//coping resources folders
	$spm->progress_log->add("Creating required resources' folders: ");
	
	if(!is_dir("$path/resources/bootstrap-datetimepicker")) 
		 $spm->recurse_copy("./app-resources/bootstrap-datetimepicker", "$path/resources/bootstrap-datetimepicker");

	if(!is_dir("$path/resources/moment"))
		$spm->recurse_copy("./app-resources/moment", "$path/resources/moment");
	
	$spm->progress_log->ok();
	$spm->progress_log->line();

	//creating files
	for($i = 0; $i < count($xmlFile->table); $i++) {
		
		//if no spm node found, skip table
		if(
			!isset($xmlFile->table[$i]->plugins)
			|| !isset($xmlFile->table[$i]->plugins->spm)
			|| !isset($xmlFile->table[$i]->plugins->spm->spm_fields)
		) continue;
		
		if($i) $spm->progress_log->line();
		$spm->progress_log->add("<b>Generating search page code for '{$xmlFile->table[$i]->caption}' table:</b>");

		//mapping fields indexes to match filter Values
		$filterIdxArray = mapIndex($xmlFile->table[$i]->field);

		ob_start();
		?>
[?php 
	if(!isset($Translation)) { @header('Location: index.php'); exit; }

	$advanced_search_mode = 0;
	$search_mode_session_key = substr('spm_' . basename(__FILE__), 0, -4);
	if(Request::has('advanced_search_mode')) {
		/* if user explicitly sets search mode by clicking Filter_x from the filters page, 
		 * apply requested mode, and store into session */
		$advanced_search_mode = intval(Request::val('advanced_search_mode')) ? 1 : 0;
		$_SESSION[$search_mode_session_key] = $advanced_search_mode;

	} elseif(isset($_SESSION[$search_mode_session_key])) {
		/* otherwise, check if search mode for given table is specified in user's 
		 * session and apply it */
		$advanced_search_mode = intval($_SESSION[$search_mode_session_key]) ? 1 : 0;
	}
?]

	<input type="hidden" name="advanced_search_mode" value="[?php echo $advanced_search_mode; ?]" id="advanced_search_mode">
	<script>
		$j(function(){
			$j('.btn.search_mode').appendTo('.page-header h1');
			$j('.btn.search_mode').click(function(){
				var mode = parseInt($j('#advanced_search_mode').val());
				$j('#advanced_search_mode').val(1 - mode);
				if(typeof(beforeApplyFilters) == 'function') beforeApplyFilters();
				return true;
			});
		})
	</script>

[?php if($advanced_search_mode) { ?]
	<button class="btn btn-lg btn-success pull-right search_mode" id="simple_search_mode" type="submit" name="Filter_x" value="1">Switch to simple search mode</button>
	<script>
		$j(function() {
			$j('#simple_search_mode').click(function() {
				if(!confirm('If you switch to simple search mode, any filters defined here will be lost! Do you still which to proceed?')) return false;
				$j('.clear_filter').click();
			})		
		})
	</script>
	[?php include(__DIR__ . '/../defaultFilters.php'); ?]
	
[?php } else { ?]

	<button class="btn btn-lg btn-default pull-right search_mode" type="submit" name="Filter_x" value="1">Switch to advanced search mode</button>
			<!-- %datetimePicker% -->
			
			<div class="page-header"><h1>
				<a href="<?php echo $xmlFile->table[$i]->name; ?>_view.php" style="text-decoration: none; color: inherit;">
					<?php echo (!empty($xmlFile->table[$i]->tableIcon) ? '<img src="resources/table_icons/' . $xmlFile->table[$i]->tableIcon . '"> ' : ''); ?>
					<?php echo $xmlFile->table[$i]->caption; ?> Filters</a>
			</h1></div>

		<?php
		$fileContent = replaceSpecialTags(ob_get_clean());
		
		$filterCounter = 0;
		$includesArray  = [
			'datetimePicker' => false,
			'dropDown' => false,
			'orderBy' => false,
			'groups' => false,
		];
			

		$fieldIdxArray = explode(":", $xmlFile->table[$i]->plugins->spm->spm_fields);
		array_pop($fieldIdxArray); //remove last element (empty)

		for ($j = 0; $j < count($fieldIdxArray); $j++) {

			$fieldNum = (int)$fieldIdxArray[$j];

			//sections 
			if($fieldNum > 9000) {
				if($fieldNum == 9001)
					$includesArray['orderBy'] = true;
				else
					$includesArray['groups'] = true;
				continue;
			}

			$filterCounter++;   //number of filter fields

			$field = $xmlFile->table[$i]->field[$fieldNum]; 
			$spm->progress_log->add("'{$field->caption}' field : ", 'spacer');
			getFieldType($fileContent, $field, $filterIdxArray[$fieldNum] , $filterCounter , $xmlFile->table[$i]->name);

			$fileContent .= "\n\t\t\t<!-- ########################################################## -->\n\t\t\t";
			$spm->progress_log->ok();
		}

		// include additional advanced filters outside ones defined here
		$fileContent .= includeAdvancedFilters($filterCounter);

		//includes
		includeNeededParts($fileContent , $includesArray, $spm);
		
		//Default filter page requirments
		includeDefaultParts($fileContent, $xmlFile->table[$i]->allowSavingFilters);
		
		/* finally, close the PHP if block */
		$fileContent .= "\n\n<" . "?php } ?>";

		$tableName = $xmlFile->table[$i]->name;
		$fileName = $xmlFile->table[$i]->name."_filter.php";

		if(@file_put_contents("$path/hooks/$fileName", $fileContent)) {
			$spm->progress_log->add("'{$fileName}' added to the hooks folder Successfully.", 'text-success spacer');

			/* manual install instructions */
			ob_start();
			?>
				To install, open the <span class="text-info">hooks/<?php echo $tableName; ?>.php</span>
				file and add this code (if it's not already there) to the
				<span class="text-info"><?php echo $tableName; ?>_init()</span> hook before the return statement:
				<br><code class="bg-info">	$options->FilterPage = "hooks/<?php echo $fileName; ?>";</code>
			<?php
			$install_instructions = ob_get_clean();
			
			/* automatic or manual install? */
			$hook_file = "{$path}/hooks/{$tableName}.php";
			
			if($write_to_hooks) { /* attempt automatic install */
				$res = $spm->add_to_hook(
					$hook_file,
					"{$tableName}_init",
					"\$options->FilterPage = 'hooks/{$tableName}_filter.php';"
				);
				
				if($res) {
					$spm->progress_log->add("Installed search page to 'hooks/{$tableName}.php'.", 'text-success spacer');
				} else {
					$error = $spm->last_error();
					
					if($error == 'Code already exists') {
						$spm->progress_log->add("Skipped installing to 'hooks/{$tableName}.php' as a search page is already installed.", 'text-warning spacer');
					} else {
						$spm->progress_log->add("Failed to install search page to 'hooks/{$tableName}.php': {$error}", 'text-danger spacer');
						$spm->progress_log->add($install_instructions, 'spacer');
					}
				}
			} else { /* manual install */
				$spm->progress_log->add($install_instructions, 'spacer');
			}
		} else {
			$spm->progress_log->add("<b>Error: Couldn't save 'hooks/{$fileName}': Check the permissions.</b>", 'text-danger spacer');
		}
	}
	
	echo $spm->progress_log->show();
?>

<center>
	<a style="margin:20px;" href="index.php" class="btn btn-success btn-lg"><span class="glyphicon glyphicon-home" ></span>   Start page</a>
</center>

<script>	
	$j(function() {
		//add resize event
		$j(window).resize(function() {
			$j("#progress")
				.height($j(window).height() - $j("#progress").offset().top - $j(".btn-success").height() - 100);
		});

		$j(window).resize();
	});
</script>

<?php include(__DIR__ . '/footer.php');