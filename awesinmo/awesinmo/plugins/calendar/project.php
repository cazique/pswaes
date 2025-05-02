<?php
	include(__DIR__ . '/header.php');

	// validate project name
	$axp_md5 = Request::val('axp');
	if(!preg_match('/^[a-f0-9]{32}$/i', $axp_md5))
		die('<br>' . $calendar->error_message('Project file not found.'));
	
	$projectFile = '';
	$xmlFile = $calendar->get_xml_file($axp_md5 , $projectFile);

	$sameAsApp = $calendar->is_project_of_app();
//-----------------------------------------------------------------------------------------
?>

<script src="../plugins-resources/itemsList.js"></script>
<script src="../plugins-resources/form.js"></script>
<script src="../plugins-resources/project.js"></script>
<script>
	if(window.AppGiniPlugin === undefined) window.AppGiniPlugin = {};

	AppGiniPlugin.prj = <?php echo json_encode($xmlFile); ?>;
	AppGiniPlugin.axp_md5 = <?php echo json_encode($axp_md5); ?>;
	AppGiniPlugin.prependPath = <?php echo json_encode(PREPEND_PATH); ?>;
	AppGiniPlugin.sameAsApp = <?php echo json_encode($sameAsApp); ?>;
	AppGiniPlugin.hostAppTitle = <?php echo json_encode(session_name()); ?>;
</script>
<script src="project.js?115"></script>

<?php
	echo $calendar->header_nav();

	echo $calendar->breadcrumb([
		'index.php' => 'Projects',
		'' => substr($projectFile, 0, -4)
	]);
?>

<a id="btn-output-folder" href="generate.php?axp=<?php echo $axp_md5; ?>" class="pull-right btn btn-primary btn-sm" style="padding: 0.25em 3em;"><span class="language" data-key="GENERATE_CALENDAR_FILES"></span> <span class="glyphicon glyphicon-chevron-right"></span></a>
<div class="clearfix"></div>

<div class="alert alert-danger hidden" id="error-not-same-app">
	<h3></h3>
	<div class="text-center">
		<a href="index.php" class="btn btn-danger"><i class="glyphicon glyphicon-chevron-left"></i>
			<span class="language" data-key="select_another_project"></span></a>
	</div>
</div>

<div class="alert alert-warning hidden language" id="error-saving-project" data-key="project_couldnt_be_saved_retry"></div>

<div class="row hidden" id="workarea">
	<div class="col-md-6" id="events">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h4 class="panel-title">
					<i class="glyphicon glyphicon-time"></i> <span class="language" data-key="event_types"></span>
					<button type="button" class="btn btn-success btn-sm pull-right new-event-launcher"><i class="glyphicon glyphicon-plus"></i> <span class="language" data-key="New"></span></button>
					<div class="clearfix"></div>
				</h4>
			</div>
			<div class="panel-body">
				<div class="no-events text-center text-muted">
					<i class="glyphicon glyphicon-exclamation-sign"></i> 
					No event types defined yet.<button type="button" class="btn btn-link new-event-launcher">Create a new event type</button>
				</div>
				<div class="events-list hidden"></div>
			</div>
			<div class="panel-footer text-small">
				<i class="glyphicon glyphicon-question-sign"></i> <span class="language" data-key="events_brief"></span>
			</div>
		</div>
	</div>
	<div class="col-md-6" id="calendars">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h4 class="panel-title">
					<i class="glyphicon glyphicon-calendar"></i> Calendars
					<button type="button" class="btn btn-success btn-sm pull-right new-calendar-launcher"><i class="glyphicon glyphicon-plus"></i> <span class="language" data-key="New"></span></button>
					<div class="clearfix"></div>
				</h4>
			</div>
			<div class="panel-body">
				<div class="no-calendars text-center text-muted">
					<i class="glyphicon glyphicon-exclamation-sign"></i> 
					No calendars defined yet.<button type="button" class="btn btn-link new-calendar-launcher">Create a new calendar</button>
				</div>
				<div class="calendars-list hidden"></div>
			</div>
			<div class="panel-footer text-small">
				<i class="glyphicon glyphicon-question-sign"></i> <span class="language" data-key="calendar_brief"></span>
			</div>
		</div>
	</div>
</div>

<div id="project-loading" class="text-center h1">
	<img src="loading.svg"> Please wait while project is loading ...
</div>


<form id="event-form-container" class="hidden">
	<div class="panel panel-default" id="event-form-panel">
		<div class="panel-heading">
			<h3 class="panel-title">
				<i class="glyphicon glyphicon-time"></i>
				<span class="new-event">Create a new event type</span>
				<span class="existing-event">Edit event <span class="label label-default">%TYPE%</span></span>
				<span class="actions pull-right"></span>
			</h3>
			<div class="clearfix"></div>
		</div>
		<div class="panel-body data-form" id="event-form"></div>
		<div class="panel-footer">
			<span class="actions pull-right"></span>
			<div class="clearfix"></div>
		</div>
	</div>
</form>

<form id="calendar-form-container" class="hidden">
	<div class="panel panel-default" id="calendar-form-panel">
		<div class="panel-heading">
			<h3 class="panel-title">
				<i class="glyphicon glyphicon-calendar"></i>
				<span class="new-calendar">Create a new calendar</span>
				<span class="existing-calendar">Edit calendar <span class="label label-default">%TITLE%</span></span>
				<span class="actions pull-right"></span>
			</h3>
			<div class="clearfix"></div>
		</div>
		<div class="panel-body data-form" id="calendar-form"></div>
		<div class="panel-footer">
			<span class="actions pull-right"></span>
			<div class="clearfix"></div>
		</div>
	</div>
</form>

<style>
	.btn.details-list:focus, .btn.summary-list:focus {
		outline: 0;
	}
	.items-list-header {
		margin-bottom: 1rem;
	}
	.panel tr:first-child th, .panel tr:first-child td {
		border-top: none !important;
	}
	.panel-title { font-weight: bold; }

	.pointer {
		cursor: pointer;
	}

	.item .item-details-key:last-child, .item .item-details-value:last-child {
		border-bottom: none;
	}
	.item .item-details-key, .item .item-details-value {
		padding: 1rem;
		border-bottom: solid 1px #ddd;
		height: 4.5rem !important;
		overflow: hidden;
	}
	.item .item-details-values {
		padding-left: 0 !important;
	}
	.item .item-details-keys {
		text-align: right;
		font-weight: bold;
		padding-right: 0 !important;
		width: 30% !important;
		min-width: 10em;
	}

	.panel-title .glyphicon-list-alt.hspacer-md { display: none; }

	.panel-body table { margin-bottom: 0; }

	.text-small { font-size: 0.75em; }

	.event-type, .calendar-title {
		font-weight: bold;
		margin: auto 0.5em;
	}
	.event-brief, .calendar-brief {
		cursor: pointer;
		padding: 0.6em;
		margin: 0 0.2em;
		border-radius: 0.2em;
	}

	.calendars-list .well.well-sm {
		font-size: 0.8em;
	}
</style>

<?php include(__DIR__ . '/footer.php');