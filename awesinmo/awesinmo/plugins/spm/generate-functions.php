<?php

	function getFieldType(&$fileContent, $field, $fieldNum, &$filterCounter , $tableName) {
		$currentType = $field->dataType;

		if (!empty($field->parentTable)) {                      //lookup

			$GLOBALS['includesArray']['dropDown'] = true;
			getLookupFilter($fileContent, $field, $fieldNum, $filterCounter , $tableName);

		} else if ($field->CSValueList != '') {      //options list

			$GLOBALS['includesArray']['dropDown'] = true;
			getOptionsFilter($fileContent, $field, $fieldNum, $filterCounter);

		} else if ($field->checkBox == "True") {                //checkbox

			getCheckboxFilter($fileContent, $field, $fieldNum, $filterCounter);

		} elseif ($currentType < 9) {                           //number

			getNumberFilter($fileContent, $field, $fieldNum, $filterCounter);
			$filterCounter++;

		} else if ($currentType == 9){                          //date
		   
			$GLOBALS['includesArray']['datetimePicker'] = true;
			getDatePreFilter($fileContent, $field, $fieldNum, $filterCounter);
			getDateFilter($fileContent, $field, $fieldNum, $filterCounter);
			$filterCounter++;

		} else if ($currentType < 12) {                         //dateTime

			$GLOBALS['includesArray']['datetimePicker'] = true;
			getDatePreFilter($fileContent, $field, $fieldNum, $filterCounter);
			getDateTimeFilter($fileContent, $field, $fieldNum, $filterCounter);
			$filterCounter++;  

		} else if ($currentType == 12) {                        //time
			$GLOBALS['includesArray']['datetimePicker'] = true;
			getDatePreFilter($fileContent, $field, $fieldNum, $filterCounter);
			getTimeFilter($fileContent, $field, $fieldNum, $filterCounter);
			$filterCounter++;  

		} else if ($currentType == 13) {                        //year
			$GLOBALS['includesArray']['datetimePicker'] = true;
			getDatePreFilter($fileContent, $field, $fieldNum, $filterCounter);
			getYearFilter($fileContent, $field, $fieldNum, $filterCounter);
			$filterCounter++;  

		} else {                                                //text
			getTextFilter($fileContent, $field, $fieldNum, $filterCounter);
		}

	}

	function getOptionsFilter(&$fileContent, $field, $fieldNum, $filterCounter) {

		$options = explode('||', entitiesToUTF8(convertLegacyOptions($field->CSValueList)));
		
		$operator = 'equal-to';
		if($field->CSValueListType == 3) $operator = 'like';

		//check data length
		if (count($options) > 6) {     //DROPDOWN
		
			
			$fileContent .= '<' . '?php
				$options = [' . substr(json_encode($options, JSON_UNESCAPED_UNICODE), 1, -1) . '];
			
				//convert options to select2 format
				$optionsList = [];
				for ($i = 0; $i < count($options); $i++) {
					$optionsList[] = (object) [
						"id" => $i,
						"text" => $options[$i]
					];
				}
				$optionsList = json_encode($optionsList);

			
				//convert value to select2 format
				if ($FilterValue[' . $filterCounter . ']) {
					$filtervalueObj = new stdClass();
					$text = htmlspecialchars($FilterValue[' . $filterCounter . ']);
					$filtervalueObj->text = $text;
					$filtervalueObj->id = array_search($text, $options);

					$filtervalueObj = json_encode($filtervalueObj);
				}

			?>';
			ob_start();
			?>
	<div class="row vspacer-lg" style="border-bottom: dotted 2px #DDD;" >
		<?php echo filterLabel($field->caption); ?>
		
		<div class="col-md-8 col-lg-6 vspacer-md">	
			<div id="<?php echo $fieldNum; ?>_DropDown"><span></span></div>
		</div>
		<input type="hidden" class="populatedOptionsData" name="<?php echo $filterCounter; ?>" value="<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>" >
		<input type="hidden" name="FilterAnd[<?php echo $filterCounter; ?>]" value="and">
		<input type="hidden" name="FilterField[<?php echo $filterCounter; ?>]" value="<?php echo $fieldNum; ?>">
		<input type="hidden" name="FilterOperator[<?php echo $filterCounter; ?>]" value="<?php echo $operator; ?>">
		<input type="hidden" name="FilterValue[<?php echo $filterCounter; ?>]" id="<?php echo $fieldNum; ?>_currValue" value="<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>" size="3">

		<?php echo clearButton(); ?>
	</div>

	<script>
		var populate_<?php echo $fieldNum; ?> = <?php echo '<'.'?php echo $filtervalueObj ;?>'; ?>
		
		$j(function () {
			$j("#<?php echo $fieldNum; ?>_DropDown").select2({
				data: <?php echo "<".'?php echo $optionsList; ?>'; ?>}).on('change', function (e) {
				$j("#<?php echo $fieldNum; ?>_currValue").val(e.added.text);
			});


			/* preserve the applied filter and show it when re-opening the filters page */
			if ($j("#<?php echo $fieldNum; ?>_currValue").val().length) {
				$j("#<?php echo $fieldNum; ?>_DropDown").select2('data', populate_<?php echo $fieldNum; ?> );
			}
		});
	</script>
			<?php
			$retVal = ob_get_contents();
			ob_end_clean();
			$fileContent .= $retVal;
		} else {                  //Radio buttons
			ob_start();
			?>

	<div class="row" style="border-bottom: dotted 2px #DDD;">
		<?php echo filterLabel($field->caption); ?>

		<input type="hidden" class="optionsData" name="FilterField[<?php echo $filterCounter; ?>]" value="<?php echo $fieldNum; ?>">
		<div class="col-xs-10 col-sm-11 col-md-8 col-lg-6">

			<input type="hidden" name="FilterAnd[<?php echo $filterCounter; ?>]" value="and">
			<input type="hidden" name="FilterOperator[<?php echo $filterCounter; ?>]" value="<?php echo $operator; ?>">
			<input type="hidden" name="FilterValue[<?php echo $filterCounter; ?>]" id="<?php echo $fieldNum; ?>_currValue" value="<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>" size="3">

	<?php foreach ($options as $option) { ?>

			<div class="radio">
				<label>
					 <input type="radio" name="FilterValue[<?php echo $filterCounter; ?>]" class="filter_<?php echo $fieldNum; ?>" value='<?php echo $option; ?>'><?php echo $option; ?>
				</label>
			</div>
	 <?php } ?>
		</div>

		<?php echo clearButton(); ?>
	</div>
	<script>
		//for population
		var filterValue_<?php echo $fieldNum; ?> = '<?php echo "<".'?php echo htmlspecialchars($FilterValue[ '. $filterCounter .' ]); ?>';?>';
		$j(function () {
			if (filterValue_<?php echo $fieldNum; ?>) {
				$j("input[class =filter_<?php echo $fieldNum; ?>][value ='" + filterValue_<?php echo $fieldNum; ?> + "']").prop("checked", true);
			}
		})
	</script>
			<?php
			$retVal = ob_get_contents();
			ob_end_clean();
			$fileContent .= $retVal;
		}
	}

	function getCheckboxFilter(&$fileContent, $field, $fieldNum, $filterCounter) {

		ob_start();
		?>

	<div class="row" style="border-bottom: dotted 2px #DDD;">
		<?php echo filterLabel($field->caption); ?>
		
		<div class="col-xs-10 col-sm-11 col-md-8 col-lg-6">
			<div class="radio">
				<label><input type="radio" name="FilterValue[<?php echo $filterCounter; ?>]" class="filter_<?php echo $fieldNum; ?>" onclick="checkboxFilter(this)" value="1" > Checked</label>
			</div>
			<div class="radio">
				<label><input type="radio" name="FilterValue[<?php echo $filterCounter; ?>]" class="filter_<?php echo $fieldNum; ?>" onclick="checkboxFilter(this)" value="null"> Unchecked</label>
			</div>
			<div class="radio">
				<label><input type="radio" name="FilterValue[<?php echo $filterCounter; ?>]" class="filter_<?php echo $fieldNum; ?>" onclick="checkboxFilter(this)" value="" checked> Any</label>
			</div>
		</div>
		<input type="hidden" name="FilterAnd[<?php echo $filterCounter; ?>]" value="and">
		<input type="hidden" class='checkboxData' name="FilterField[<?php echo $filterCounter; ?>]" value="<?php echo $fieldNum; ?>">   
		<input type="hidden" name="FilterOperator[<?php echo $filterCounter; ?>]" id="filter_<?php echo $fieldNum; ?>" value="equal-to">

		<?php echo clearButton(); ?>
	</div>

	<script>
		//for population
		var filterValue_<?php echo $fieldNum; ?> = '<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>';
		$j(function () {
			if (filterValue_<?php echo $fieldNum; ?>) {
				$j("input[class =filter_<?php echo $fieldNum; ?>][value =" + filterValue_<?php echo $fieldNum; ?> + "]").prop("checked", true).click();
			}
		})
	</script>
		<?php
		$retVal = ob_get_contents();
		ob_end_clean();
		$fileContent .= $retVal;
	}


	function getNumberFilter( &$fileContent, $field, $fieldNum, &$filterCounter){


		ob_start();
		?>

	<div class="row vspacer-lg" style="border-bottom: dotted 2px #DDD;" >
		<?php echo filterLabel($field->caption); ?>
			
		<div class="col-xs-3 col-md-1 vspacer-lg text-center">Between</div>
		<input type="hidden" name="FilterAnd[<?php echo $filterCounter; ?>]" value="and">
		<input type="hidden" name="FilterField[<?php echo $filterCounter; ?>]" value="<?php echo $fieldNum; ?>">   
		<input type="hidden" name="FilterOperator[<?php echo $filterCounter; ?>]" value="greater-than-or-equal-to">
		<div class="col-xs-9 col-md-3 col-lg-2 vspacer-md">
			<input type="text" class="numeric form-control" name="FilterValue[<?php echo $filterCounter; ?>]" value="<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>" size="3">
		</div>

		<?php $filterCounter++; ?>
		<div class="col-xs-3 col-md-1 text-center vspacer-lg and"> and </div>
		<input type="hidden" name="FilterAnd[<?php echo $filterCounter; ?>]" value="and">
		<input type="hidden" name="FilterField[<?php echo $filterCounter; ?>]" value="<?php echo $fieldNum; ?>">  
		<input type="hidden" name="FilterOperator[<?php echo $filterCounter; ?>]" value="less-than-or-equal-to">
		<div class="col-xs-9 col-md-3 col-lg-2 vspacer-md">
			<input type="text" class="numeric form-control" name="FilterValue[<?php echo $filterCounter; ?>]" value="<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>" size="3">
		</div>

		<?php echo clearButton(); ?>
	</div>

		<?php
		$retVal = ob_get_contents();
		ob_end_clean();
		$fileContent.=$retVal;
	}


	function getLookupFilter(&$fileContent, $field, $fieldNum, $filterCounter , $tableName) {
		$responseIndex = $field->notNull == 'True' ? '0' : '1';
		ob_start();
		?>
		

	<div class="row vspacer-lg" style="border-bottom: dotted 2px #DDD;">
		<?php echo filterLabel($field->caption); ?>

		<div class="col-md-8 col-lg-6 vspacer-md">
			<div id="filter_<?php echo $fieldNum; ?>" class="vspacer-lg"><span></span></div>

			<input type="hidden" class="populatedLookupData" name="<?php echo $filterCounter; ?>" value="<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>" >
			<input type="hidden" name="FilterAnd[<?php echo $filterCounter; ?>]" value="and">
			<input type="hidden" name="FilterField[<?php echo $filterCounter; ?>]" value="<?php echo $fieldNum; ?>">  
			<input type="hidden" id="lookupoperator_<?php echo $fieldNum; ?>" name="FilterOperator[<?php echo $filterCounter; ?>]" value="equal-to">
			<input type="hidden" id="filterfield_<?php echo $fieldNum; ?>" name="FilterValue[<?php echo $filterCounter; ?>]" value="<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>" size="3">
		</div>
		
		<?php echo clearButton(); ?>
	</div>

	<script>

		$j(function() {
			/* display a drop-down of categories that populates its content from ajax_combo.php */
			$j("#filter_<?php echo $fieldNum; ?>").select2({
				ajax: {
					url: 'ajax_combo.php',
					dataType: 'json',
					cache: true,
					data: function(term, page) {
						return {
							s: term,
							p: page,
							t: "<?php echo $tableName; ?>",
							f: "<?php echo $field->name; ?>",
							ut: 1,
							json: 1
						}; 
					},
					results: function (resp, page) { return resp; }
				}
			}).on('change', function(e){
				$j("#filterfield_<?php echo $fieldNum; ?>").val(e.added.text);
				$j("#lookupoperator_<?php echo $fieldNum; ?>").val('equal-to');
				if (e.added.id=='{empty_value}'){
					$j("#lookupoperator_<?php echo $fieldNum; ?>").val('is-empty');
				}
			});


			/* preserve the applied category filter and show it when re-opening the filters page */
			if ($j("#filterfield_<?php echo $fieldNum; ?>").val().length){
				
				//None case 
				if ($j("#filterfield_<?php echo $fieldNum; ?>").val() == '<None>'){
					$j("#filter_<?php echo $fieldNum; ?>").select2( 'data' , {
						id: '{empty-value}',
						text: '<None>'
					});
					$j("#lookupoperator_<?php echo $fieldNum; ?>").val('is-empty');
					return;
				}
				$j.ajax({
					url: 'ajax_combo.php',
					dataType: 'json',
					data: {
						s: $j("#filterfield_<?php echo $fieldNum; ?>").val(),  //search term
						p: 1,                                         //page number
						t: "<?php echo $tableName; ?>",                //table name
						f: "<?php echo $field->name; ?>",               //field name
						json: 1
					}
				}).done(function(response){
					if (response.results.length){
						$j("#filter_<?php echo $fieldNum; ?>").select2('data' , {
							id: response.results[<?php echo $responseIndex; ?>].id,
							text: response.results[<?php echo $responseIndex; ?>].text
						});
					}
				});
			}

		});
	</script>

		<?php
		$retVal = ob_get_contents();
		ob_end_clean();
		$fileContent .= $retVal;
	}


	function getTextFilter(&$fileContent, $field, $fieldNum, $filterCounter) {


		ob_start();
		?>
		
	<div class="row vspacer-lg" style="border-bottom: dotted 2px #DDD;" >
		<?php echo filterLabel($field->caption); ?>
		
		<input type="hidden" name="FilterAnd[<?php echo $filterCounter; ?>]" value="and">
		<input type="hidden" name="FilterField[<?php echo $filterCounter; ?>]" value="<?php echo $fieldNum; ?>">  
		<input type="hidden" name="FilterOperator[<?php echo $filterCounter; ?>]" value="like">
		<div class="col-md-8 col-lg-6 vspacer-md">
			<input type="text" class="form-control" name="FilterValue[<?php echo $filterCounter; ?>]" value="<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>" size="3">
		</div>
		
		<?php echo clearButton(); ?>
	</div>


		<?php
		$retVal = ob_get_contents();
		ob_end_clean();
		$fileContent .= $retVal;
	}


	function getDatePreFilter (&$fileContent, $field, $fieldNum, $filterCounter){
		ob_start();
		?>

	<div class="row vspacer-lg" style="border-bottom: dotted 2px #DDD;" >
		<?php echo filterLabel($field->caption); ?>

		<div class="col-xs-3 col-md-1 vspacer-lg text-center">Between</div>
		
		<input type="hidden" name="FilterAnd[<?php echo $filterCounter; ?>]" value="and">
		<input type="hidden" name="FilterField[<?php echo $filterCounter; ?>]" value="<?php echo $fieldNum; ?>">   
		<input type="hidden" name="FilterOperator[<?php echo $filterCounter; ?>]" value="greater-than-or-equal-to">
		
		<div class="col-xs-9 col-md-3 col-lg-2 vspacer-md">
			<input type="text"  class="form-control" id="from-date_<?php echo $fieldNum; ?>"  name="FilterValue[<?php echo $filterCounter; ?>]" value="<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>" size="10">
		</div>

		<?php $filterCounter++; ?>
		<div class="col-xs-3 col-md-1 text-center vspacer-lg"> and </div>
		
		<input type="hidden" name="FilterAnd[<?php echo $filterCounter; ?>]" value="and">
		<input type="hidden" name="FilterField[<?php echo $filterCounter; ?>]" value="<?php echo $fieldNum; ?>">  
		<input type="hidden" name="FilterOperator[<?php echo $filterCounter; ?>]" value="less-than-or-equal-to">
		
		<div class="col-xs-9 col-md-3 col-lg-2 vspacer-md">
			<input type="text" class="form-control" id="to-date_<?php echo $fieldNum; ?>" name="FilterValue[<?php echo $filterCounter; ?>]" value="<?php echo '<'.'?php echo htmlspecialchars($FilterValue[' . $filterCounter . ']); ?>'; ?>" size="10">
		</div>
		
		<?php echo clearButton(); ?>
	</div>

		<?php
		$retVal = ob_get_contents();
		ob_end_clean();
		$fileContent .= $retVal;
	}

	function js_date_format($time = false) {
		global $xmlFile;
		
		$date_format = [
			1 => ['YYYY', 'MM', 'DD'], 
			2 => ['DD', 'MM', 'YYYY'], 
			3 => ['MM', 'DD', 'YYYY']
		];

		$date_separator = [
			1 => '-', 
			2 => ' ', 
			3 => '.', 
			4 => '/', 
			5 => ','
		];

		$time_format = ($time ? (intval($xmlFile->timeFormat) == 12 ? ' HH:mm:ss' : ' hh:mm:ss a') : '');

		return implode(
			$date_separator[intval($xmlFile->dateSeparator)], 
			$date_format[intval($xmlFile->dateFormat)]
		) . $time_format;
	}
	
	function getDateFilter(&$fileContent, $field, $fieldNum, $filterCounter) {
		$js_date_format = js_date_format();
		ob_start();
		?>
		
	<script>
		$j(function(){
			//date
			$j("#from-date_<?php echo $fieldNum; ?> , #to-date_<?php echo $fieldNum; ?> ").datetimepicker({
				format: '<?php echo $js_date_format; ?>'
			});

			$j("#from-date_<?php echo $fieldNum; ?>" ).on('dp.change' , function(e){
				date = moment(e.date).add(1, 'month');  
				$j("#to-date_<?php echo $fieldNum; ?> ").val(date.format('<?php echo $js_date_format; ?>')).data("DateTimePicker").minDate(e.date);
			});
		});
	</script>

		<?php
		$retVal = ob_get_contents();
		ob_end_clean();
		$fileContent .= $retVal;
	}


	function getDateTimeFilter(&$fileContent, $field, $fieldNum, $filterCounter) {
		$js_datetime_format = js_date_format(true);
		ob_start();?>

	<script>
		$j(function(){
			//date
			$j("#from-date_<?php echo $fieldNum; ?> , #to-date_<?php echo $fieldNum; ?> ").datetimepicker({
				format: '<?php echo $js_datetime_format; ?>'   //config
			});

			$j("#from-date_<?php echo $fieldNum; ?>" ).on('dp.change' , function(e){
				date = moment(e.date).add(1, 'day');  
				$j("#to-date_<?php echo $fieldNum; ?> ")
					.val(date.format('<?php echo $js_datetime_format; ?>'))
					.data("DateTimePicker")
					.minDate(e.date);
			});
		});
	</script>

		<?php
		$retVal = ob_get_contents();
		ob_end_clean();
		$fileContent .= $retVal;
	}



	function getTimeFilter(&$fileContent, $field, $fieldNum, $filterCounter) {

		ob_start();?>

	<script>
		$j(function(){
			$j("#from-date_<?php echo $fieldNum; ?> , #to-date_<?php echo $fieldNum; ?> ").datetimepicker({
				format: 'HH:mm:ss'   //config
			});

			$j("#from-date_<?php echo $fieldNum; ?>").on('dp.change' , function(e){
				date = moment(e.date).add(1, 'hour');  
				$j("#to-date_<?php echo $fieldNum; ?>").val(date.format('HH:mm:ss')).data("DateTimePicker").minDate(e.date);
			});
		});
	</script>

		<?php
		$retVal = ob_get_contents();
		ob_end_clean();
		$fileContent .= $retVal;
	}


	function getYearFilter(&$fileContent, $field, $fieldNum, $filterCounter) {

		ob_start();?>

	<script>
		$j(function(){
			$j("#from-date_<?php echo $fieldNum; ?> , #to-date_<?php echo $fieldNum; ?> ").datetimepicker({
				format: 'YYYY' ,  //config
				viewMode: 'years'
			});

			$j("#from-date_<?php echo $fieldNum; ?>" ).on('dp.change' , function(e){
				date = moment(e.date).add(1, 'year');  
				$j("#to-date_<?php echo $fieldNum; ?> ").val(date.format('YYYY')).data("DateTimePicker").minDate(e.date);
			});
		});
	</script>

		<?php
		$retVal = ob_get_contents();
		ob_end_clean();
		$fileContent .= $retVal;
	}


	function includeAdvancedFilters($num_spm_filters) {
		$max_filter_index = 12;
		$num_spm_filters = intval($num_spm_filters);
		if($num_spm_filters >= $max_filter_index || $num_spm_filters <= 0) return '';

		ob_start();
		?>

		[?php $si = <?php echo $num_spm_filters + 1; ?>; ?]
		[?php for($afi = $si; $afi <= <?php echo $max_filter_index; ?>; $afi++) { ?]
			<!-- advanced filter [?php echo $afi; ?] -->
			<input type="hidden" name="FilterAnd[[?php echo $afi; ?]]" value="[?php echo html_attr(Request::val('FilterAnd')[$afi]); ?]">
			<input type="hidden" name="FilterField[[?php echo $afi; ?]]" value="[?php echo html_attr(Request::val('FilterField')[$afi]); ?]">
			<input type="hidden" name="FilterOperator[[?php echo $afi; ?]]" value="[?php echo html_attr(Request::val('FilterOperator')[$afi]); ?]">
			<input type="hidden" name="FilterValue[[?php echo $afi; ?]]" value="[?php echo html_attr(Request::val('FilterValue')[$afi]); ?]">
		[?php } ?]

		<?php
		return replaceSpecialTags(ob_get_clean());
	}

	function replaceSpecialTags($content) {
		return str_replace(
			['[?php', '?]'], 
			['<'.'?php', '?>'], 
			$content
		);
	}

	function includeNeededParts( &$fileContent , $includesArray, $spm ){
		

		if ( $includesArray['orderBy']){
			$spm->progress_log->add("'Order by' section included: ", 'spacer');
			$fileContent.='    

			<!-- sorting header  -->   
			<div class="row" style="border-bottom: solid 2px #DDD;">
				<div class="col-md-offset-2 col-md-8 vspacer-lg"><strong>Order by</strong></div>
			</div>
			
			<!-- sorting rules -->
			<' . '?php
			// Fields list
			$sortFields = new Combo;
			$sortFields->ListItem = $this->ColCaption;
			$sortFields->ListData = $this->ColNumber;

			// sort direction
			$sortDirs = new Combo;
			$sortDirs->ListItem = ["ascending" , "descending" ];
			$sortDirs->ListData = ["asc", "desc"];
			$num_rules = min(maxSortBy, count($this->ColCaption));

			for($i = 0; $i < $num_rules; $i++){
				$sfi = $sd = "";
				if(isset($orderBy[$i])) foreach($orderBy[$i] as $sfi => $sd);

				$sortFields->SelectName = "OrderByField$i";
				$sortFields->SelectID = "OrderByField$i";
				$sortFields->SelectedData = $sfi;
				$sortFields->SelectedText = "";
				$sortFields->Render();

				$sortDirs->SelectName = "OrderDir$i";
				$sortDirs->SelectID = "OrderDir$i";
				$sortDirs->SelectedData = $sd;
				$sortDirs->SelectedText = "";
				$sortDirs->Render();

				$border_style = ($i == $num_rules - 1 ? "solid 2px #DDD" : "dotted 1px #DDD");
				?>
				
				<!-- sorting rule -->
				<div class="row" style="border-bottom: <' . '?php echo $border_style; ?>;">
					<div class="col-xs-2 vspacer-md hidden-md hidden-lg"><strong><' . '?php echo ($i ? "then by" : "order by"); ?></strong></div>
					<div class="col-md-2 col-md-offset-2 vspacer-md hidden-xs hidden-sm text-right"><strong><' . '?php echo ($i ? "then by" : "order by"); ?></strong></div>
					<div class="col-xs-6 col-md-4 vspacer-md"><' . '?php echo $sortFields->HTML; ?></div>
					<div class="col-xs-4 col-md-2 vspacer-md"><' . '?php echo $sortDirs->HTML; ?></div>
				</div>
				<' . '?php
			}
			?>';
			$spm->progress_log->ok();
		}
		//-----------------------------------------------------------------------------------

		if ($includesArray['groups']){
			$spm->progress_log->add("'User/group/all' section included: ", 'spacer');
			
			$fileContent .= '    
				<' . '?php
					// ownership options
					$mi = getMemberInfo();
					$adminConfig = config("adminConfig");
					$isAnonymous = ($mi["group"] == $adminConfig["anonymousGroup"]);

					if(!$isAnonymous){
						?>
						<!-- ownership header  --> 
						<div class="row filterByOwnership" style="border-bottom: solid 2px #DDD;">
							<div class="col-md-offset-2 col-md-8 vspacer-lg"><strong>Records to display</strong></div>
						</div>

						<!-- ownership options -->
						<div class="row" style="border-bottom: dotted 2px #DDD;">
							<div class="col-md-8 col-md-offset-2">
								<div class="radio filterByOwnership">
									<label>
										<input type="radio" name="DisplayRecords" id="DisplayRecordsUser" value="user"/>
										Only your own records
									</label>
								</div>
								<div class="radio filterByOwnership">
									<label>
										<input type="radio" name="DisplayRecords" id="DisplayRecordsGroup" value="group"/>
										All records owned by your group
									</label>
								</div>
								<div class="radio filterByOwnership">
									<label>
										<input type="radio" name="DisplayRecords" id="DisplayRecordsAll" value="all"/>
										All records
									</label>
								</div>
							</div>
						</div>
						<' . '?php
					}
				?>


				<script>
					$j(function(){
						<' . '?php $disp_rec = Request::val("DisplayRecords"); ?>
						<' . '?php if($disp_rec != "user" && $disp_rec != "group") $disp_rec = "all"; ?>
						var DisplayRecords = "<' . '?php echo $disp_rec; ?>";

						switch(DisplayRecords){
							case "user":
								$j("#DisplayRecordsUser").prop("checked", true);
								break;
							case "group":
								$j("#DisplayRecordsGroup").prop("checked", true);
								break;
							default:
								$j("#DisplayRecordsAll").prop("checked", true);
						}
					});
				</script>
				';
				
			$spm->progress_log->ok();
		}
		//-----------------------------------------------------------------------------------
		
		if ($includesArray['datetimePicker']){
			$fileContent = str_replace('<!-- %datetimePicker% -->', '
			<!-- load bootstrap datetime-picker -->
			<link rel="stylesheet" href="resources/bootstrap-datetimepicker/bootstrap-datetimepicker.css">
			<script type="text/javascript" src="resources/moment/moment-with-locales.min.js"></script>
			<script type="text/javascript" src="resources/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
			', $fileContent);
		}
		//-----------------------------------------------------------------------------------
		
		if ($includesArray['dropDown']){
		}

	}



	function includeDefaultParts(&$fileContent, $saveFiltersFlag){
		//add clear filters function
		ob_start() 
		?>
			<!-- filter actions -->
			<div class="row">
				<div class="col-md-3 col-md-offset-3 col-lg-offset-4 col-lg-2 vspacer-lg">
					<input type="hidden" name="apply_sorting" value="1">
					<button type="submit" id="applyFilters" onclick="beforeApplyFilters(event);return true;" class="btn btn-success btn-block btn-lg"><i class="glyphicon glyphicon-ok"></i> Apply filters</button>
				</div>
				<?php if($saveFiltersFlag == "True"){ ?>
					<div class="col-md-3 col-lg-2 vspacer-lg">
						<button type="submit" onclick="beforeApplyFilters(event);return true;" class="btn btn-default btn-block btn-lg" id="SaveFilter" name="SaveFilter_x" value="1"><i class="glyphicon glyphicon-align-left"></i> Save &amp; apply filters</button>
					</div>
				<?php } ?>
				<div class="col-md-3 col-lg-2 vspacer-lg">
					<button onclick="beforeCancelFilters();" type="submit" id="cancelFilters" class="btn btn-warning btn-block btn-lg"><i class="glyphicon glyphicon-remove"></i> Cancel</button>
				</div>
			</div>

			<script>
				$j(function(){
					//stop event if it is already bound
					$j(".numeric").off("keydown").on("keydown", function (e) {
						// Allow: backspace, delete, tab, escape, enter and .
						if ($j.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
							// Allow: Ctrl+A, Command+A
							(e.keyCode == 65 && ( e.ctrlKey === true || e.metaKey === true ) ) || 
							// Allow: home, end, left, right, down, up
							(e.keyCode >= 35 && e.keyCode <= 40)) {
								// let it happen, don't do anything
								return;
						}
						// Ensure that it is a number and stop the keypress
						if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
							e.preventDefault();
						}
					});                
				});
				
				/* function to handle the action of the clear field button */
				function clearFilters(elm){
					var parentDiv = $j(elm).parent(".row ");
					//get all input nodes
					inputValueChildren = parentDiv.find("input[type!=radio][name^=FilterValue]");
					inputRadioChildren = parentDiv.find("input[type=radio][name^=FilterValue]");
					
					//default input nodes ( text, hidden )
					inputValueChildren.each(function( index ) {
						$j( this ).val('');
					});
					
					//radio buttons
					inputRadioChildren.each(function(index) {
						$j(this).prop('checked', false);

						//checkbox case
						if($j(this).val() == '') $j(this).prop("checked", true).click();
					});
					
					//lookup and select dropdown
					parentDiv.find("div[id$=DropDown],div[id^=filter_]").select2("val", "");

					//for lookup
					parentDiv.find("input[id^=lookupoperator_]").val('equal-to');

				}
				
				function checkboxFilter(elm) {
					if (elm.value == "null") {
						$j("#" + elm.className).val("is-empty");
					} else {
						$j("#" + elm.className).val("equal-to");
					}
				}
				
				/* funtion to remove unsupplied fields */
				function beforeApplyFilters(event){
				
					// get all field submitted values
					$j(":input[type=text][name^=FilterValue],:input[type=hidden][name^=FilterValue],:input[type=radio][name^=FilterValue]:checked").each(function(index) {
						  
						// if type=hidden  and options radio fields with the same name are checked, supply its value
						if($j(this).attr('type') == 'hidden' &&  $j(":input[type=radio][name='" + $j(this).attr('name') + "']:checked").length > 0) {
							return;
						}
						  
						// do not submit fields with empty values
						if(!$j(this).val()) {
							var fieldNum = $j(this).attr('name').match(/(\d+)/)[0];
							$j(":input[name='FilterField[" + fieldNum + "]']").val('');
						};
					});

				}
				
				function beforeCancelFilters(){
					

					//other fields
					$j('form')[0].reset();

					//lookup case ( populate with initial data)
					$j(":input[class='populatedLookupData']").each(function(){
					  

						$j(":input[name='FilterValue["+$j(this).attr('name')+"]']").val($j(this).val());
						if ($j(this).val()== '<None>'){
							$j(this).parent(".row ").find('input[id^="lookupoperator"]').val('is-empty');
						}else{
							$j(this).parent(".row ").find('input[id^="lookupoperator"]').val('equal-to');
						}
							
					})

					//options case ( populate with initial data)
					$j(":input[class='populatedOptionsData']").each(function(){
					   
						$j(":input[name='FilterValue["+$j(this).attr('name')+"]']").val($j(this).val());
					})


					//checkbox, radio options case
					$j(":input[class='checkboxData'],:input[class='optionsData'] ").each(function(){
						var filterNum = $j(this).val();
						var populatedValue = eval("filterValue_"+filterNum);                  
						var parentDiv = $j(this).parent(".row ");

						//check old value
						parentDiv.find("input[type=radio][value='"+populatedValue+"']").prop('checked', true).click();
					
					})

					//remove unsuplied fields
					beforeApplyFilters();

					return true;
				}
			</script>
			
			<style>
				.form-control{ width: 100% !important; }
				.select2-container, .select2-container.vspacer-lg{ max-width: unset !important; width: 100%; margin-top: 0 !important; }
			</style>


		<?php
		$fileContent.= ob_get_contents();
		ob_end_clean();
		
	}

	/* returns the code for the filter clear button */
	function clearButton(){
		ob_start();
		?>

		<div class="col-xs-3 col-xs-offset-9 col-md-offset-0 col-md-1">
			<button type="button" class="btn btn-default vspacer-md btn-block" title='Clear fields' onclick="clearFilters($j(this).parent());" ><span class="glyphicon glyphicon-trash text-danger"></button>
		</div>

		<?php
		$out = ob_get_contents();
		ob_end_clean();
		
		return $out;
	}

	/* returns the code for the filter label */
	function filterLabel($label, $for = ''){
		ob_start();
		?>

		<div class="hidden-xs hidden-sm col-md-3 vspacer-lg text-right"><label for="<?php echo $for; ?>"><?php echo $label; ?></label></div>
		<div class="hidden-md hidden-lg col-xs-12 vspacer-lg"><label for="<?php echo $for; ?>"><?php echo $label; ?></label></div>
		
		<?php
		$out = ob_get_contents();
		ob_end_clean();
		
		return $out;
	}

	function mapIndex ( $fields ){

		$idx = 1;
		$mapper = [];
		for ( $i = 0 ; $i < count ($fields); $i++ ){


			$field = $fields[$i];
			if ( ( $field->notFiltered == "True") || ($field->tableImage=="True") || ($field->detailImage=="True") ){
				//those indexes will not be considered
				continue;
			}
			$mapper[$i] = $idx;
			$idx++;
		}
		return $mapper;

	}
