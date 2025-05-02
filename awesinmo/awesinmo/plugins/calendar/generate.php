<?php 
	include(__DIR__ . '/header.php');

	$app_path = realpath(__DIR__ . '/../../');

	$calendar = new calendar([
		'title' => 'Calendar',
		'name' => 'calendar',
		'logo' => 'calendar-logo-lg.png',
        'output_path' => $app_path
	]);
	
	$axp_md5 = Request::val('axp');
	$projectFile = '';
	$project = $calendar->get_xml_file($axp_md5 , $projectFile);

	// output generate page top content
	echo $calendar->header_nav();
	echo $calendar->breadcrumb([
		'index.php' => 'Projects',
		'project.php?axp=' . urlencode($axp_md5) => substr($projectFile, 0, -4),
		'' => 'Generating files'
	]);


	// validate provided path
	if(!$calendar->is_appgini_app($app_path)) {
		echo $calendar->error_message('Invalid application path!');
		include(__DIR__ . '/footer.php');
		exit;
	}

	if(!$cals = $calendar->calendars()) {
		echo $calendar->error_message('No calendars defined for this project. Nothing to output!');
		include(__DIR__ . '/footer.php');
		exit;
	}

	if(!$events = $calendar->events()) {
		echo $calendar->error_message('No events defined for this project. Nothing to output!');
		include(__DIR__ . '/footer.php');
		exit;
	}

	$calendar->progress_log->add('Generating calendar files into <i>' . $app_path . '</i>', 'info');

	$appHooksDir = "{$app_path}/hooks";
	// is $appHooksDir writable?
	// get a random test file name and try to touch it
	$testFile = "{$appHooksDir}/test-" . rand(1000, 9999);
	if(!is_writable($appHooksDir) || !@touch($testFile)) {
		echo $calendar->error_message("The directory '$appHooksDir' is not writable!");
		include(__DIR__ . '/footer.php');
		exit;
	}
	@unlink($testFile);

	// Generate hooks/calendar-* files (calendars)
	foreach($cals as $calId => $cal) {
		calendar_file($calendar, $appHooksDir, $cal);
	}

	$tables = []; // we'll use this to group events by table 

	// Generate hooks/calendar-events-* files (event json files)
	foreach($events as $evId => $ev) {
		event_json_file($calendar, $appHooksDir, $ev);
		event_update_file($calendar, $appHooksDir, $ev);
		
		if(!isset($tables[$ev->table])) $tables[$ev->table] = [];
		$tables[$ev->table][] = $ev;
	}
	
	// Generate hooks/tablename-dv.js files
	foreach($tables as $table => $events) {
		table_dvhook($calendar, $appHooksDir, $table, $events);
	}

	// Copy fullcalendar and plugin common files to resources
	copy_resources($calendar, $appHooksDir);

	// Create calendar links in links-home and links-navmenu
	create_links($calendar, $cals);

	echo $calendar->progress_log->show();

	?>
	<div class="text-center">
		<a href="../../" class="btn btn-default"><i class="glyphicon glyphicon-home"></i> App Homepage</a>
	</div>
	<?php

	include(__DIR__ . '/footer.php');
	######################################################################################

	function calendar_file($pl, $path, $cal) {
		$calId = $cal->id;

		$cal_file = "{$path}/calendar-{$calId}.php";
		$cal_url = "../../hooks/" . basename($cal_file);
		
		$pl->progress_log->add("Generating <a href=\"{$cal_url}\"><i class=\"glyphicon glyphicon-calendar\"></i> {$cal->title} calendar</a> ", 'text-info');

		$replace = [
			'[?php' => '<' . '?php',
			'?]' => '?>',
		];

		$calIds = [];
		$separateCalendars = $cal->{'events-in-separate-calendars'} && count($cal->events) > 1;
		if($separateCalendars) {
			foreach($cal->events as $evId) {
				$calIds[] = "$calId-$evId";
			}
		} else {
			$calIds[] = $calId;
		}

		$allDayEventsExist = $pl->calendarHasAllDayEvents($calId);
	
		ob_start();
		?>
			[?php
			﹣﹣define('PREPEND_PATH', '../');
			﹣﹣define('FULLCAL_PATH', PREPEND_PATH . 'resources/fullcalendar/');
			﹣﹣
			﹣﹣include(__DIR__ . "/../lib.php");
			﹣﹣include_once(__DIR__ . "/../header.php");
			﹣﹣
			﹣﹣/* check access */
			﹣﹣$mi = getMemberInfo();
			﹣﹣if(!in_array($mi['group'], ['<?php echo implode("', '", $cal->groups); ?>'])) {
			﹣﹣﹣﹣echo error_message("Access denied");
			﹣﹣﹣﹣include_once(__DIR__ . "/../footer.php");
			﹣﹣﹣﹣exit;
			﹣﹣}

			﹣﹣?]

			﹣﹣<link href="[?php echo FULLCAL_PATH; ?]core/main.min.css" rel="stylesheet" />
			﹣﹣<link href="[?php echo FULLCAL_PATH; ?]daygrid/main.min.css" rel="stylesheet" />
			﹣﹣<link href="[?php echo FULLCAL_PATH; ?]timegrid/main.min.css" rel="stylesheet" />
			﹣﹣<link href="[?php echo FULLCAL_PATH; ?]list/main.min.css" rel="stylesheet" />
			﹣﹣
			﹣﹣<script src="[?php echo FULLCAL_PATH; ?]core/main.min.js"></script>
			﹣﹣<script src="[?php echo FULLCAL_PATH; ?]core/locales-all.min.js"></script>
			﹣﹣<script src="[?php echo FULLCAL_PATH; ?]interaction/main.min.js"></script>
			﹣﹣<script src="[?php echo FULLCAL_PATH; ?]daygrid/main.min.js"></script>
			﹣﹣<script src="[?php echo FULLCAL_PATH; ?]timegrid/main.min.js"></script>
			﹣﹣<script src="[?php echo FULLCAL_PATH; ?]list/main.min.js"></script>
			﹣﹣<script src="[?php echo PREPEND_PATH; ?]resources/plugin-calendar/calendar-common.js"></script>

			﹣﹣<script>
			﹣﹣﹣﹣let csrfToken = [?php echo json_encode(csrf_token(false, true)); ?];

			﹣﹣﹣﹣const calHeight = () => {
			﹣﹣﹣﹣﹣﹣const vhEl = $j('<div>').css('height', '80vh').addClass('hidden').appendTo('body');
			﹣﹣﹣﹣﹣﹣const ch = vhEl.height() - $j('.page-header').outerHeight(true) - $j('.top-margin-adjuster').outerHeight(true);
			﹣﹣﹣﹣﹣﹣vhEl.remove();

			﹣﹣﹣﹣﹣﹣return Math.max(ch, 400);
			﹣﹣﹣﹣}

			﹣﹣﹣﹣const utcISO = (dt) => new Date(dt.setMinutes(dt.getMinutes() - dt.getTimezoneOffset())).toISOString();
			﹣﹣﹣﹣const undoWindow = 5000; // milliseconds to allow undoing an event update

			<?php if($separateCalendars): ?>
				﹣﹣﹣﹣const Calendars = [];

				﹣﹣﹣﹣$j(() => {
				﹣﹣﹣﹣﹣﹣$j('body')
				﹣﹣﹣﹣﹣﹣﹣﹣// Sync all calendars
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.cal-previous-year', () => Calendars.forEach(cal => cal.prevYear()))
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.cal-previous', () => Calendars.forEach(cal => cal.prev()))
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.cal-next', () => Calendars.forEach(cal => cal.next()))
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.cal-next-year', () => Calendars.forEach(cal => cal.nextYear()))
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.cal-reload', () => Calendars.forEach(cal => cal.refetchEvents()))
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.cal-today', () => Calendars.forEach(cal => cal.today()))
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.cal-month', () => Calendars.forEach(cal => cal.changeView('dayGridMonth')))
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.cal-week', () => Calendars.forEach(cal => cal.changeView('timeGridWeek')))
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.cal-day', () => Calendars.forEach(cal => cal.changeView('timeGridDay')))
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.cal-list', () => Calendars.forEach(cal => cal.changeView('listWeek')))
				﹣﹣﹣﹣﹣﹣﹣﹣// Disable buttons temporarily to prevent double clicks
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.calendars-toolbar button', function() {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$j('.calendars-toolbar button').prop('disabled', true);
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣setTimeout(() => $j('.calendars-toolbar button').prop('disabled', false), 400);
				﹣﹣﹣﹣﹣﹣﹣﹣})
				﹣﹣﹣﹣﹣﹣﹣﹣// Remove class 'active' from toggler when it's about to collapse its target
				﹣﹣﹣﹣﹣﹣﹣﹣.on('click', '.calendar-togglers > button', function() {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣const btn = $j(this);
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣btn.toggleClass('active', btn.hasClass('collapsed'));
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣btn.children('i').toggleClass('glyphicon-eye-open glyphicon-eye-close');
				﹣﹣﹣﹣﹣﹣﹣﹣})
				﹣﹣﹣﹣})

				﹣﹣﹣﹣// Keep calendars title updated
				﹣﹣﹣﹣setInterval(() => {
				﹣﹣﹣﹣﹣﹣const title = $j('div.fc-toolbar.fc-header-toolbar div.fc-center h2').first().text();
				﹣﹣﹣﹣﹣﹣const calsTitle = $j('.fc-header-title');

				﹣﹣﹣﹣﹣﹣if(calsTitle.text() == title) return;
				﹣﹣﹣﹣﹣﹣calsTitle.text(title);
				﹣﹣﹣﹣}, 200);
			<?php endif; ?>

			<?php foreach($calIds as $i => $calId): ?>
				﹣﹣﹣﹣// Calendar ID: <?php echo $calId; ?>

				﹣﹣﹣﹣$j(function() {
				﹣﹣﹣﹣﹣﹣const Cal = AppGini.Calendar;
				﹣﹣﹣﹣﹣﹣const calId = '<?php echo $calId; ?>';
				<?php if($separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣const calIndex = Calendars.length;
					<?php if($pl->eventIsAllDay($cal->events[$i])): ?>
						﹣﹣﹣﹣﹣﹣const scrollTime = '00:00:00';
						﹣﹣﹣﹣﹣﹣const snapDuration = '24:00:00';
					<?php else: ?>
						﹣﹣﹣﹣﹣﹣const scrollTime = '08:00:00';
						﹣﹣﹣﹣﹣﹣const snapDuration = '00:05:00';
					<?php endif; ?>
				<?php else: ?>
					<?php if($allDayEventsExist): ?>
						﹣﹣﹣﹣﹣﹣const scrollTime = '00:00:00';
						﹣﹣﹣﹣﹣﹣const snapDuration = '24:00:00';
					<?php else: ?>
						﹣﹣﹣﹣﹣﹣const scrollTime = '08:00:00';
						﹣﹣﹣﹣﹣﹣const snapDuration = '00:05:00';
					<?php endif; ?>
				<?php endif; ?>

				﹣﹣﹣﹣﹣﹣const lang = Cal.Translate.word;
				﹣﹣﹣﹣﹣﹣const langHtml = Cal.Translate.html;

				﹣﹣﹣﹣﹣﹣const eventUpdate = function(info, allowUndo = true) {
				﹣﹣﹣﹣﹣﹣﹣﹣// if an .event-updated-overlay is already shown (from a previous update), and `allowUndo` is true,
				﹣﹣﹣﹣﹣﹣﹣﹣// this means the user is trying to update this or another event, so we should remove the overlay
				﹣﹣﹣﹣﹣﹣﹣﹣if(allowUndo) $j('.event-updated-overlay').remove();

				﹣﹣﹣﹣﹣﹣﹣﹣const eventId = info?.event?._def?.publicId ?? null;
				﹣﹣﹣﹣﹣﹣﹣﹣const start = info?.event?.start ?? null;
				﹣﹣﹣﹣﹣﹣﹣﹣const end = info?.event?.end ?? null;
				﹣﹣﹣﹣﹣﹣﹣﹣const url = info?.event?.extendedProps?.updateUrl ?? null;
				﹣﹣﹣﹣﹣﹣﹣﹣if(!eventId || !start || !end || !url) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣info.revert();
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣return;
				﹣﹣﹣﹣﹣﹣﹣﹣}

				﹣﹣﹣﹣﹣﹣﹣﹣$j.ajax({
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣url,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣method: 'POST',
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣data: {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣id: eventId,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣start: utcISO(start),
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣end: utcISO(end),
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣csrf_token: csrfToken
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣success: function(data) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣// update csrf token
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣csrfToken = data?.csrfToken ?? csrfToken;

				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣// reload events in calendar(s)
				<?php if($separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Calendars.forEach(cal => cal.refetchEvents());
				<?php else: ?>
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal._fullCal.refetchEvents();
				<?php endif; ?>

				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣if(!allowUndo) return; // no need to show overlay or allow undo if this is an undo action

				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣// wait 1 second then show an overlay message that the event has been updated, and an undo button that calls revert()
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣// hide the overlay after 5 seconds
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣const overlay = $j(`
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣<div class="alert alert-success alert-dismissible event-updated-overlay">
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣<i class="glyphicon glyphicon-ok"></i> <span class="language" data-key="event_updated"></span>
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣</div>
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣`).appendTo('body');
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣const undo = $j('<button class="btn btn-link language" data-key="undo"></button>').appendTo(overlay);
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣overlay.fadeIn(1000);

				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣undo.click(() => {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣info.revert();
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣overlay.remove();
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣// info.prevEvent in case of eventResize, info.oldEvent in case of eventDrop!
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣info.event = info.prevEvent || info.oldEvent;
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$j(`a[href*="SelectedID=${eventId}"]`).remove();
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣eventUpdate(info, false);
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣});

				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣setTimeout(() => overlay.fadeOut(1000, () => overlay.remove()), undoWindow);
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣error: function(xhr, status, error) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣let error_message = xhr.responseText || error;
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣error_message = lang(error_message) || error_message;

				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣console.error(lang('failed_to_update_event', { error_message }));
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣info.revert();
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣AppGini.modalError(langHtml('failed_to_update_event', { error_message }));
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
				﹣﹣﹣﹣﹣﹣﹣﹣});
				﹣﹣﹣﹣﹣﹣};

				﹣﹣﹣﹣﹣﹣Cal._fullCal = new FullCalendar.Calendar($j('#' + calId).get(0), {
				﹣﹣﹣﹣﹣﹣﹣﹣plugins: ['interaction', 'dayGrid', 'timeGrid', 'list'],
				﹣﹣﹣﹣﹣﹣﹣﹣customButtons: {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣reload: {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣text: lang('refresh'),
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣icon: 'refresh',
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣click: function() {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal._fullCal.refetchEvents();
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
				﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣header: {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣left: 'prevYear,prev,next,nextYear reload today',
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣center: 'title',
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣eventLimit: true,
				﹣﹣﹣﹣﹣﹣﹣﹣},

				<?php if($separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣﹣﹣contentHeight: calHeight(), // https://fullcalendar.io/docs/contentHeight
				<?php else: ?>
					﹣﹣﹣﹣﹣﹣﹣﹣height: 'auto', // https://fullcalendar.io/docs/height
					﹣﹣﹣﹣﹣﹣﹣﹣contentHeight: 'auto', // https://fullcalendar.io/docs/contentHeight
					﹣﹣﹣﹣﹣﹣﹣﹣aspectRatio: 2.5, // https://fullcalendar.io/docs/aspectRatio
				<?php endif; ?>

				﹣﹣﹣﹣﹣﹣﹣﹣defaultDate: Cal.urlDate('<?php echo $cal->{'initial-date'}; ?>'),
				﹣﹣﹣﹣﹣﹣﹣﹣defaultView: Cal.urlView('<?php echo $cal->{'initial-view'}; ?>'),
				﹣﹣﹣﹣﹣﹣﹣﹣allDaySlot: false,
				﹣﹣﹣﹣﹣﹣﹣﹣views: {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣dayGridMonth: {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣eventLimit: 5,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣eventLimitClick: 'day'
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣timeGridWeek : {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣nowIndicator: true,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣scrollTime: scrollTime,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣snapDuration: snapDuration,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣timeGridDay: {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣nowIndicator: true,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣scrollTime: scrollTime,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣snapDuration: snapDuration,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
				﹣﹣﹣﹣﹣﹣﹣﹣},

				<?php if($separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣﹣﹣eventSources: [{
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣url: 'calendar-events-<?php echo $cal->events[$i]; ?>.json.php',
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣failure: function() {
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$j('#' + calId + '-events-loading-error').removeClass('hidden');
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
					﹣﹣﹣﹣﹣﹣﹣﹣}],
				<?php else: ?>
					﹣﹣﹣﹣﹣﹣﹣﹣eventSources: [
					<?php foreach($cal->events as $evId): ?>
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣{
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣url: 'calendar-events-<?php echo $evId; ?>.json.php',
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣failure: function() {
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$j('#' + calId + '-events-loading-error').removeClass('hidden');
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣},
					<?php endforeach; ?>
					﹣﹣﹣﹣﹣﹣﹣﹣],
				<?php endif; ?>
				﹣﹣﹣﹣﹣﹣﹣﹣eventRender: function (e) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣switch(e.view.type) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣case 'dayGridMonth':
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣case 'timeGridWeek':
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣case 'timeGridDay':
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣// this is necessary to render HTML titles, 
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣// https://github.com/fullcalendar/fullcalendar/issues/2919#issuecomment-459909185
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣e.el.firstChild.innerHTML = e.event.title;
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣break;
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣case 'listWeek':
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣e.el.lastChild.firstChild.innerHTML = e.event.title;
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣break;
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
				﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣eventClick: function(e) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣e.jsEvent.preventDefault();
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣if(e.event.url) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣// strip html from title, and shorten to 100 chars max
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣var title = $j('<span>' + e.event.title + '</span>').text(),
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣maxChars = 100;
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣if(title.length > maxChars) title = title.substr(0, maxChars - 3) + '...';

				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣modal_window({
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣url: e.event.url,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣size: 'full',
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣title: title,
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣// on closing modal, reload events in calendar(s)
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣close: function() {
				<?php if($separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Calendars.forEach(cal => cal.refetchEvents());
				<?php else: ?>
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal._fullCal.refetchEvents();
				<?php endif; ?>
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣});
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
				﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣eventDrop: eventUpdate,
				﹣﹣﹣﹣﹣﹣﹣﹣eventResize: eventUpdate,

				﹣﹣﹣﹣﹣﹣﹣﹣/* Adding new events */
				﹣﹣﹣﹣﹣﹣﹣﹣selectable: true,
				﹣﹣﹣﹣﹣﹣﹣﹣select: function(selection) {
				<?php if($separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal.newEventButtons.show(selection, calIndex);
				<?php else: ?>
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal.newEventButtons.show(selection);
				<?php endif; ?>
				﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣unselect: function(e) {
				<?php if($separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal.newEventButtons.hide(calIndex);
				<?php else: ?>
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal.newEventButtons.hide();
				<?php endif; ?>
				﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣
				﹣﹣﹣﹣﹣﹣﹣﹣fixedWeekCount: false,
				﹣﹣﹣﹣﹣﹣﹣﹣loading: function(isLoading) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣var viewCont = $j('.fc-view-container');
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣if(isLoading) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$j('#' + calId + '-loading')
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣.removeClass('hidden')
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣.offset({ top: viewCont.length ? viewCont.offset().top : null });
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$j('#' + calId + '-events-loading-error').addClass('hidden');
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣return;
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}

				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣// finished loading
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣(function(view) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣setTimeout(function() {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal.fullCalendarFixes('#' + calId, view);
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$j('#' + calId + '-loading').addClass('hidden');
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}, 100)
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣})(this.view);
				﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣datesRender: function(i) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal.fullCalendarFixes('#' + calId, i.view);
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal.updateUrlDate(moment(this.getDate()).format('YYYY-MM-DD'), i.view.type);
				<?php if(!$separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣switch(i.view.type) {
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣case 'dayGridMonth':
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣case 'listWeek':
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣setTimeout(Cal.fullHeight, 5);
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣break;
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣case 'timeGridWeek':
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣case 'timeGridDay':
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣setTimeout(() => { Cal.compactHeight(scrollTime); }, 5);
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣break;
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣}
				<?php endif; ?>
				﹣﹣﹣﹣﹣﹣﹣﹣},
				﹣﹣﹣﹣﹣﹣﹣﹣viewSkeletonRender: function(i) {
				﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣Cal.fullCalendarFixes('#' + calId, i.view);
				﹣﹣﹣﹣﹣﹣﹣﹣},
				<?php if($cal->locale) { ?>
				﹣﹣﹣﹣﹣﹣﹣﹣locale: '<?php echo $cal->locale; ?>',
				<?php } ?>
				<?php if($cal->customFullCalendarOptions) { ?>
				﹣﹣﹣﹣﹣﹣﹣﹣<?php echo $cal->customFullCalendarOptions; ?>
				<?php } ?>
				﹣﹣﹣﹣﹣﹣});
				﹣﹣﹣﹣﹣﹣Cal._fullCal.render();

				﹣﹣﹣﹣﹣﹣Cal.fullCalendarBootstrapize('#' + calId);
				﹣﹣﹣﹣﹣﹣Cal.Translate.ready(function() {
				﹣﹣﹣﹣﹣﹣﹣﹣Cal.newEventButtons.create([
				<?php if($separateCalendars): ?>
					<?php $evId = $cal->events[$i]; ?>
					<?php $ev = $pl->event($evId); ?>
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣{
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣type: '<?php echo $evId; ?>',
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣color: '<?php echo $ev->color; ?>',
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣title: lang('new_x', { event: '<?php echo str_replace('-', ' ', $evId); ?>' }),
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣table: '<?php echo $ev->table; ?>'
					﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣},
				<?php else: ?>
					<?php foreach($cal->events as $evId): ?>
						<?php $ev = $pl->event($evId); ?>
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣{
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣type: '<?php echo $evId; ?>',
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣color: '<?php echo $ev->color; ?>',
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣title: lang('new_x', { event: '<?php echo str_replace('-', ' ', $evId); ?>' }),
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣table: '<?php echo $ev->table; ?>'
						﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣},
					<?php endforeach; ?>
				<?php endif; ?>
				﹣﹣﹣﹣﹣﹣﹣﹣]);
				﹣﹣﹣﹣﹣﹣});

				﹣﹣﹣﹣﹣﹣$j(`#${calId}-events-loading-error`).click(function() {
				<?php if($separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣﹣﹣Calendars.forEach(cal => cal.refetchEvents());
				<?php else: ?>
					﹣﹣﹣﹣﹣﹣﹣﹣Cal._fullCal.refetchEvents();
				<?php endif; ?>
				﹣﹣﹣﹣﹣﹣});

				﹣﹣﹣﹣﹣﹣const hiddenPrint = $j(`#${calId} div.hidden-print`);
				﹣﹣﹣﹣﹣﹣if(hiddenPrint.length == 3) hiddenPrint.eq(1).remove();
				﹣﹣﹣﹣﹣﹣Cal.Translate.live();

				﹣﹣﹣﹣﹣﹣$j(`#${calId}-loading`).width($j(`#${calId}`).innerWidth());
				<?php if($separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣Calendars.push(Cal._fullCal);
				<?php endif; ?>
				﹣﹣﹣﹣})

			<?php endforeach; ?>
			﹣﹣</script>

			﹣﹣<style>
			﹣﹣﹣﹣.calendars-container {
			﹣﹣﹣﹣﹣﹣display: grid;
			﹣﹣﹣﹣﹣﹣grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
			﹣﹣﹣﹣﹣﹣gap: 2em;

			﹣﹣﹣﹣﹣﹣h3 {
			﹣﹣﹣﹣﹣﹣﹣﹣text-align: center;
			﹣﹣﹣﹣﹣﹣﹣﹣font-weight: bold;
			﹣﹣﹣﹣﹣﹣}
			﹣﹣﹣﹣}
			﹣﹣﹣﹣.calendars-toolbar {
			﹣﹣﹣﹣﹣﹣margin-bottom: 1em;
			﹣﹣﹣﹣﹣﹣display: grid;
			﹣﹣﹣﹣﹣﹣grid-template-columns: minmax(300px, 1fr) 1fr minmax(300px, 1fr);
			﹣﹣﹣﹣﹣﹣justify-content: space-between;
			﹣﹣﹣﹣﹣﹣align-items: center;
			﹣﹣﹣﹣﹣﹣> div {
			﹣﹣﹣﹣﹣﹣﹣﹣text-align: center;
			﹣﹣﹣﹣﹣﹣}
			﹣﹣﹣﹣﹣﹣> div:first-child {
			﹣﹣﹣﹣﹣﹣﹣﹣text-align: left;
			﹣﹣﹣﹣﹣﹣}
			﹣﹣﹣﹣﹣﹣> div:last-child {
			﹣﹣﹣﹣﹣﹣﹣﹣text-align: right;
			﹣﹣﹣﹣﹣﹣}
			﹣﹣﹣﹣}
			﹣﹣﹣﹣@media (max-width: 768px) {
			﹣﹣﹣﹣﹣﹣.calendars-toolbar {
			﹣﹣﹣﹣﹣﹣﹣﹣grid-template-columns: minmax(90vw, 1fr);
			﹣﹣﹣﹣﹣﹣﹣﹣justify-items: center;
			﹣﹣﹣﹣﹣﹣﹣﹣margin-top: 3em;
			﹣﹣﹣﹣﹣﹣﹣﹣> div {
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣text-align: center;
			﹣﹣﹣﹣﹣﹣﹣﹣}
			﹣﹣﹣﹣﹣﹣}
			﹣﹣﹣﹣﹣﹣.fc-toolbar {
			﹣﹣﹣﹣﹣﹣﹣﹣display: grid !important;
			﹣﹣﹣﹣﹣﹣}
			﹣﹣﹣﹣}
			<?php if($separateCalendars): ?>
				﹣﹣﹣﹣.fc-toolbar.fc-header-toolbar {
				﹣﹣﹣﹣﹣﹣display: none !important;
				﹣﹣﹣﹣}
				﹣﹣﹣﹣.calendar-togglers > button {
				﹣﹣﹣﹣﹣﹣/* no focus outline */
				﹣﹣﹣﹣﹣﹣outline: none !important;
				﹣﹣﹣﹣}
			<?php endif; ?>
			﹣﹣﹣﹣.event-updated-overlay {
			﹣﹣﹣﹣﹣﹣position: fixed;
			﹣﹣﹣﹣﹣﹣top: calc(min(10vh, 50px));
			﹣﹣﹣﹣﹣﹣left: 50%;
			﹣﹣﹣﹣﹣﹣transform: translate(-50%, 0);
			﹣﹣﹣﹣﹣﹣padding: 1em;
			﹣﹣﹣﹣﹣﹣border-radius: 5px;
			﹣﹣﹣﹣﹣﹣z-index: 1000;
			﹣﹣﹣﹣﹣﹣display: none;
			﹣﹣﹣﹣﹣﹣width: 300px;
			﹣﹣﹣﹣﹣﹣min-width: fit-content;
	
			﹣﹣﹣﹣﹣﹣.close {
			﹣﹣﹣﹣﹣﹣﹣﹣position: absolute;
			﹣﹣﹣﹣﹣﹣﹣﹣top: 0;
			﹣﹣﹣﹣﹣﹣﹣﹣right: 0;
			﹣﹣﹣﹣﹣﹣﹣﹣padding: 0.5em;
			﹣﹣﹣﹣﹣﹣}
			﹣﹣﹣﹣}
			﹣﹣</style>

			﹣﹣<div class="page-header"><h1>
			﹣﹣﹣﹣<img src="[?php echo PREPEND_PATH; ?]resources/table_icons/calendar.png">
			﹣﹣﹣﹣<?php echo $cal->title; ?>

			<?php if($separateCalendars): ?>
				﹣﹣﹣﹣<div class="btn-group pull-right hidden-print calendar-togglers">
				<?php foreach($calIds as $i => $calId): ?>
					<?php $calTitle = ucfirst(str_replace('-', ' ', $cal->events[$i])); ?>
					﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-default active" id="<?php echo $calId; ?>-toggle" data-toggle="collapse" data-target="#<?php echo $calId; ?>-container"><i class="glyphicon glyphicon-eye-open"></i> <?php echo $calTitle; ?></button>
				<?php endforeach; ?>
				﹣﹣﹣﹣</div>
			<?php endif; ?>
			﹣﹣</h1></div>

			<?php if($separateCalendars): ?>
				﹣﹣<div class="calendars-toolbar">
				﹣﹣﹣﹣<div class="fc-left">
				﹣﹣﹣﹣﹣﹣<div class="btn-group">
				﹣﹣﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-primary cal-previous-year"><i class="glyphicon glyphicon-fast-backward"></i></button>
				﹣﹣﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-primary cal-previous"><i class="glyphicon glyphicon-backward"></i></button>
				﹣﹣﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-primary cal-next"><i class="glyphicon glyphicon-forward"></i></button>
				﹣﹣﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-primary cal-next-year"><i class="glyphicon glyphicon-fast-forward"></i></button>
				﹣﹣﹣﹣﹣﹣</div>
				﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-primary cal-reload"><i class="glyphicon glyphicon-refresh"></i></button>
				﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-primary cal-today"><i class="glyphicon glyphicon-calendar"></i> <span class="language" data-key="today"></span></button>
				﹣﹣﹣﹣</div>
				﹣﹣﹣﹣<div class="fc-center"><h2 class="fc-header-title"></h2></div>
				﹣﹣﹣﹣<div class="fc-right">
				﹣﹣﹣﹣﹣﹣<div class="btn-group">
				﹣﹣﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-primary cal-month"><i class="glyphicon glyphicon-th"></i> <span class="language" data-key="month"></span></button>
				﹣﹣﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-primary cal-week"><i class="glyphicon glyphicon-th-list"></i> <span class="language" data-key="week"></span></button>
				﹣﹣﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-primary cal-day"><i class="glyphicon glyphicon-th-large"></i> <span class="language" data-key="day"></span></button>
				﹣﹣﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-primary cal-list"><i class="glyphicon glyphicon-list"></i> <span class="language" data-key="list"></span></button>
				﹣﹣﹣﹣﹣﹣</div>
				﹣﹣﹣﹣</div>
				﹣﹣</div>
			<?php endif; ?>

			﹣﹣<div class="calendars-container">
			<?php foreach($calIds as $i => $calId): ?>

				﹣﹣﹣﹣<div id="<?php echo $calId; ?>-container" class="collapse in">
				﹣﹣﹣﹣﹣﹣<div 
				﹣﹣﹣﹣﹣﹣﹣﹣id="<?php echo $calId; ?>-loading" 
				﹣﹣﹣﹣﹣﹣﹣﹣class="hidden alert alert-info text-center" 
				﹣﹣﹣﹣﹣﹣﹣﹣style="width: 88%; height: 70vh; position: fixed; z-index: 500; top: 26vh;"
				﹣﹣﹣﹣﹣﹣>
				﹣﹣﹣﹣﹣﹣﹣﹣<img src="[?php echo PREPEND_PATH; ?]loading.gif"> <span class="language" data-key="please_wait"></span>
				﹣﹣﹣﹣﹣﹣</div>
				﹣﹣﹣﹣﹣﹣<div id="<?php echo $calId; ?>-events-loading-error" class="hidden alert alert-warning text-center">
				﹣﹣﹣﹣﹣﹣﹣﹣[?php echo $Translation['Connection error']; ?]
				﹣﹣﹣﹣﹣﹣﹣﹣<button type="button" class="btn btn-warning reload-calendar"><i class="glyphicon glyphicon-refresh"></i></button>
				﹣﹣﹣﹣﹣﹣</div>
				<?php if($separateCalendars): ?>
					﹣﹣﹣﹣﹣﹣<h3 class="text-muted"><?php echo ucfirst(str_replace('-', ' ', $cal->events[$i])); ?></h3>
				<?php endif; ?>
				﹣﹣﹣﹣﹣﹣<div id="<?php echo $calId; ?>"></div>
				﹣﹣﹣﹣</div>
			<?php endforeach; ?>

			﹣﹣</div>

			﹣﹣[?php
			﹣﹣include_once(__DIR__ . "/../footer.php");
		<?php

		$code = ob_get_clean();
		$code = $pl->format_indents(
			str_replace(
				array_keys($replace), 
				array_values($replace), 
				$code
			)
		);

		/* Generating calendar file */
		if(!@file_put_contents($cal_file, $code)) {
			$pl->progress_log->failed();
			return;
		}

		$pl->progress_log->ok();
	}

	function event_json_file($pl, $path, $ev) {
		$evId = $ev->type;
		$ev_file = "{$path}/calendar-events-{$evId}.json.php";

		$pl->progress_log->add("Generating {$ev_file}  ", 'text-info');

		$replace = [
			'[?php' => '<' . '?php',
			'?]' => '?>',
		];

		ob_start();

		?>
			[?php
			﹣﹣/*
			﹣﹣ Returns an array of events according to the 
			﹣﹣ format specified here: https://fullcalendar.io/docs/event-object
			﹣﹣ */

			﹣﹣define('PREPEND_PATH', '../');
			﹣﹣@header('Content-type: application/json');

			﹣﹣include(__DIR__ . "/../lib.php");

			﹣﹣// event config
			﹣﹣$type = '<?php echo $evId; ?>';
			﹣﹣$color = '<?php echo $ev->color ?? 'info'; ?>';
			﹣﹣$textColor = '<?php echo $ev->textColor ?? 'info'; ?>';
			﹣﹣$defaultClasses = "text-{$textColor} bg-{$color}";
			﹣﹣$table = '<?php echo $ev->table; ?>';
			﹣﹣$customWhere = '<?php echo trim($ev->customWhere) ? addcslashes($ev->customWhere, "'\\") : '1 = 1'; ?>';
			﹣﹣$title = '<?php echo addcslashes($ev->title ?? $ev->table, '"'); ?>';
			﹣﹣$allDay = <?php echo $ev->allDay ? 'true' : 'false'; ?>;
			﹣﹣$startDateField = '<?php echo $ev->startDateField ?? ''; ?>';
			﹣﹣$startTimeField = '<?php echo $ev->startTimeField ?? ''; ?>';
			﹣﹣$endDateField = '<?php echo $ev->endDateField ?? ''; ?>';
			﹣﹣$endTimeField = '<?php echo $ev->endTimeField ?? ''; ?>';
			﹣﹣$pk = getPKFieldName($table);
			﹣﹣// end of event config
			﹣﹣
			﹣﹣/* return this on error */
			﹣﹣$nothing = json_encode([]);

			﹣﹣/* check access */
			﹣﹣$from = get_sql_from($table);
			﹣﹣if(!$from) { // no permission to access that table
			﹣﹣﹣﹣@header('HTTP/1.0 403 Forbidden');
			﹣﹣﹣﹣exit($nothing);
			﹣﹣}

			﹣﹣$date_handler = function($dt) {
			﹣﹣﹣﹣$dto = DateTime::createFromFormat(DateTime::ATOM, $dt);
			﹣﹣﹣﹣if($dto === false) return false;

			﹣﹣﹣﹣return date('Y-m-d H:i:s', $dto->format('U'));
			﹣﹣};

			﹣﹣$start = $date_handler(Request::val('start'));
			﹣﹣$end = $date_handler(Request::val('end'));
			﹣﹣if(!$start || !$end) exit($nothing);

			﹣﹣$events = [];
			﹣﹣$fields = get_sql_fields($table);

			﹣﹣/* 
			﹣﹣ * Build event start/end conditions:
			﹣﹣ * if event is configured with both a startDateField and endDateField,
			﹣﹣ *    get events where startDateField < end and endDateField > start and startDateField <= endDateField
			﹣﹣ * if event is configured with only a startDateField (default),
			﹣﹣ *    get events where startDateField < end and startDateField >= start

			﹣﹣ * Here, we apply date conditions only and ignore time.
			﹣﹣ * The reason is that the minimum interval for fullcalendar is 1 day.
			﹣﹣ * So, there is no need to build time filters using time fields.
			﹣﹣ */
			﹣﹣$eventDatesWhere = "`{$table}`.`{$startDateField}` >= '{$start}' AND 
			﹣﹣                    `{$table}`.`{$startDateField}` < '{$end}'";
			
			﹣﹣if($endDateField) $eventDatesWhere = "NOT (
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣`{$table}`.`{$startDateField}` < '{$start}' AND
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣`{$table}`.`{$endDateField}` < '{$start}'
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣) AND NOT (
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣`{$table}`.`{$startDateField}` > '{$end}' AND
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣`{$table}`.`{$endDateField}` > '{$end}'
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣)";

			﹣﹣$eo = ['silentErrors' => true];
			﹣﹣$res = sql(
			﹣﹣﹣﹣"SELECT {$fields} FROM {$from} AND 
			﹣﹣﹣﹣﹣﹣({$eventDatesWhere}) AND
			﹣﹣﹣﹣﹣﹣({$customWhere})", $eo
			﹣﹣﹣﹣);

			﹣﹣while($row = db_fetch_array($res)) {
			﹣﹣﹣﹣// preparing event title variables
			﹣﹣﹣﹣$replace = [];
			﹣﹣﹣﹣foreach($row as $key => $value)
			﹣﹣﹣﹣﹣﹣if(is_numeric($key)) $replace['{' . ($key + 1) . '}'] = $value;
			﹣﹣﹣﹣$currentTitle = to_utf8(str_replace(array_keys($replace), array_values($replace), $title));

			﹣﹣﹣﹣// if !$canEditAll and !$canEditNone, then we need to check on a per-record basis
			﹣﹣﹣﹣$editable = $canEditAll || ($canEditNone ? false : check_record_permission($table, $row[$pk], 'edit'));

			﹣﹣﹣﹣$events[] = [
			﹣﹣﹣﹣﹣﹣'id' => to_utf8($row[$pk]),
			﹣﹣﹣﹣﹣﹣'url' => PREPEND_PATH . $table . '_view.php?Embedded=1&SelectedID=' . urlencode($row[$pk]),
			﹣﹣﹣﹣﹣﹣'updateUrl' => PREPEND_PATH . 'hooks/calendar-events-' . $type . '.update.php', // accessible via extendedProps.updateUrl

			﹣﹣﹣﹣﹣﹣/*
			﹣﹣﹣﹣﹣﹣﹣﹣if a function named 'calendar_event_title' is defined
			﹣﹣﹣﹣﹣﹣﹣﹣(in hooks/__global.php for example), it will be called instead of using the title
			﹣﹣﹣﹣﹣﹣﹣﹣defined through the plugin. This is useful if you want to modify/append the
			﹣﹣﹣﹣﹣﹣﹣﹣default title defined for this event type based on some criteria in the data. For
			﹣﹣﹣﹣﹣﹣﹣﹣example to add some icon or extra info if specific criteria are met

			﹣﹣﹣﹣﹣﹣﹣﹣The calendar_event_title() function should:
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣1. Accept the following parameters:
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣(string) event_type (set to current event type)
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣(string) title (set to the default event title)
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣(associative array) event_data (contains the event data as retrieved from this event's table)

			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣2. Return a string containing the new/modified title to apply to the event, HTML is allowed
			﹣﹣﹣﹣﹣﹣*/
			﹣﹣﹣﹣﹣﹣'title' => safe_html(
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣function_exists('calendar_event_title') ? 
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣call_user_func_array('calendar_event_title', [$type, $currentTitle, $row]) :
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$currentTitle
			﹣﹣﹣﹣﹣﹣),

			﹣﹣﹣﹣﹣﹣/*
			﹣﹣﹣﹣﹣﹣﹣﹣if a function named 'calendar_event_classes' is defined
			﹣﹣﹣﹣﹣﹣﹣﹣(in hooks/__global.php for example), it will be called instead of using the color classes
			﹣﹣﹣﹣﹣﹣﹣﹣defined through the plugin. This is useful if you want to apply CSS classes other than the
			﹣﹣﹣﹣﹣﹣﹣﹣default ones defined for this event type based on some criteria in the data.

			﹣﹣﹣﹣﹣﹣﹣﹣The calendar_event_classes() function should:
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣1. Accept the following parameters:
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣(string) event_type (set to current event type)
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣(string) classes (set to the default classes)
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣(associative array) event_data (contains the event data as retrieved from this event's table)

			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣2. Return a string containing CSS class names (space-separated) to apply to the event.
			﹣﹣﹣﹣﹣﹣*/
			﹣﹣﹣﹣﹣﹣'classNames' => (
			﹣﹣﹣﹣﹣﹣﹣﹣function_exists('calendar_event_classes') ? 
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣call_user_func_array('calendar_event_classes', [$type, $defaultClasses, $row]) :
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣$defaultClasses
			﹣﹣﹣﹣﹣﹣),
			﹣﹣﹣﹣﹣﹣'startEditable' => $editable,
			﹣﹣﹣﹣﹣﹣'durationEditable' => $editable && $endDateField,
			﹣﹣﹣﹣];

			﹣﹣﹣﹣$lastEvent = &$events[count($events) - 1];

			﹣﹣﹣﹣// convert formatted start and end dates to ISO
			﹣﹣﹣﹣$lastEvent['start'] = iso_datetime($row[$startDateField]);
			﹣﹣﹣﹣$lastEvent['end'] = $endDateField ? iso_datetime($row[$endDateField]) : $lastEvent['start'];

			﹣﹣﹣﹣if($allDay) {
			﹣﹣﹣﹣﹣﹣// no start/end time
			﹣﹣﹣﹣﹣﹣$lastEvent['start'] = date_only($lastEvent['start']); 
			﹣﹣﹣﹣﹣﹣$lastEvent['end'] = append_time(date_only($lastEvent['end']));
			﹣﹣﹣﹣﹣﹣continue;
			﹣﹣﹣﹣}

			﹣﹣﹣﹣if($startTimeField)
			﹣﹣﹣﹣﹣﹣$lastEvent['start'] = iso_datetime(
			﹣﹣﹣﹣﹣﹣﹣﹣// take only the app-formatted date part of startDateField (in case it's a datetime)
			﹣﹣﹣﹣﹣﹣﹣﹣date_only($row[$startDateField]) . 
			﹣﹣﹣﹣﹣﹣﹣﹣// append a space then fomratted startTimeField
			﹣﹣﹣﹣﹣﹣﹣﹣' ' . $row[$startTimeField]
			﹣﹣﹣﹣﹣﹣);

			﹣﹣﹣﹣if($endTimeField)
			﹣﹣﹣﹣﹣﹣$lastEvent['end'] = iso_datetime(
			﹣﹣﹣﹣﹣﹣﹣﹣// take only the app-formatted date part of endDateField (in case it's a datetime)
			﹣﹣﹣﹣﹣﹣﹣﹣date_only($endDateField ? $row[$endDateField] : $row[$startDateField]) . 
			﹣﹣﹣﹣﹣﹣﹣﹣// and append a space then fomratted endTimeField
			﹣﹣﹣﹣﹣﹣﹣﹣' ' . $row[$endTimeField]
			﹣﹣﹣﹣﹣﹣);
			﹣﹣}

			﹣﹣/* 512: JSON_PARTIAL_OUTPUT_ON_ERROR */
			﹣﹣echo json_encode($events, 512);

			﹣﹣function date_only($dt) { return substr($dt, 0, 10); }

			﹣﹣function iso_datetime($dt) {
			﹣﹣﹣﹣// if date already in the format yyyy-mm-dd? do nothing
			﹣﹣﹣﹣if(preg_match('/^[0-9]{4}-/', $dt)) return $dt;

			﹣﹣﹣﹣// convert app-formatted date to iso (mysql)
			﹣﹣﹣﹣return mysql_datetime($dt);
			﹣﹣}

			﹣﹣function append_time($d, $t = '23:59:59') {
			﹣﹣﹣﹣// if date already has time appended, return as-is
			﹣﹣﹣﹣if(preg_match('/\d?\d:\d?\d(:\d?\d)?\s*$/', $d)) return $d;
			﹣﹣﹣﹣return "$d $t";
			﹣﹣}
		<?php

		$code = ob_get_clean();
		$code = $pl->format_indents(
			str_replace(
				array_keys($replace), 
				array_values($replace), 
				$code
			)
		);

		/* Generating calendar file */
		if(!@file_put_contents($ev_file, $code)) {
			$pl->progress_log->failed();
			return;
		}

		$pl->progress_log->ok();
	}

	function event_update_file($pl, $path, $ev) {
		$evId = $ev->type;
		$ev_file = "{$path}/calendar-events-{$evId}.update.php";

		$pl->progress_log->add("Generating {$ev_file}  ", 'text-info');

		$replace = [
			'[?php' => '<' . '?php',
			'?]' => '?>',
		];

		ob_start();

		?>
			[?php
			﹣﹣/*
			﹣﹣ Updates an event according to the provided start and end dates (and times)
			﹣﹣ if the user has permission to edit the event.
			﹣﹣ */

			﹣﹣define('PREPEND_PATH', '../');
			﹣﹣@header('Content-type: application/json');

			﹣﹣include(__DIR__ . "/../lib.php");

			﹣﹣// event config
			﹣﹣$type = '<?php echo $evId; ?>';
			﹣﹣$color = '<?php echo $ev->color ?? 'info'; ?>';
			﹣﹣$textColor = '<?php echo $ev->textColor ?? 'info'; ?>';
			﹣﹣$defaultClasses = "text-{$textColor} bg-{$color}";
			﹣﹣$table = '<?php echo $ev->table; ?>';
			﹣﹣$customWhere = '<?php echo trim($ev->customWhere) ? addcslashes($ev->customWhere, "'\\") : '1 = 1'; ?>';
			﹣﹣$title = '<?php echo addcslashes($ev->title ?? $ev->table, '"'); ?>';
			﹣﹣$allDay = <?php echo $ev->allDay ? 'true' : 'false'; ?>;
			﹣﹣$startDateField = '<?php echo $ev->startDateField ?? ''; ?>';
			﹣﹣$startTimeField = '<?php echo $ev->startTimeField ?? ''; ?>';
			﹣﹣$endDateField = '<?php echo $ev->endDateField ?? ''; ?>';
			﹣﹣$endTimeField = '<?php echo $ev->endTimeField ?? ''; ?>';
			﹣﹣$pk = getPKFieldName($table);
			﹣﹣// end of event config
			
			﹣﹣// check csrf token
			﹣﹣if(!csrf_token(true)) {
			﹣﹣﹣﹣@header('HTTP/1.0 403 Forbidden');
			﹣﹣﹣﹣exit('invalid_csrf_token');
			﹣﹣}

			﹣﹣// receive id of the event to update, start and end dates/times
			﹣﹣$id = Request::val('id');
			﹣﹣$start = Request::val('start');
			﹣﹣$end = Request::val('end');

			﹣﹣// exit if no id or start date
			﹣﹣if(!$id) {
			﹣﹣﹣﹣@header('HTTP/1.0 400 Bad Request');
			﹣﹣﹣﹣exit('missing_event_id');
			﹣﹣}

			﹣﹣if(!$start) {
			﹣﹣﹣﹣@header('HTTP/1.0 400 Bad Request');
			﹣﹣﹣﹣exit('missing_start_date');
			﹣﹣}

			﹣﹣// check if a string is a valid js ISOString datetime (YYYY-MM-DDTHH:MM:SS.sssZ)
			﹣﹣$validISOString = function($dt) {
			﹣﹣﹣﹣return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}.\d{3}Z$/', $dt);
			﹣﹣};

			﹣﹣// start must be an ISOString datetime
			﹣﹣if(!$validISOString($start)) {
			﹣﹣﹣﹣@header('HTTP/1.0 400 Bad Request');
			﹣﹣﹣﹣exit('invalid_start_date');
			﹣﹣}


			﹣﹣// if end date is provided, it must be an ISO8601 date or datetime
			﹣﹣if($end && !$validISOString($end)) {
			﹣﹣﹣﹣@header('HTTP/1.0 400 Bad Request');
			﹣﹣﹣﹣exit('invalid_end_date');
			﹣﹣}

			﹣﹣// user have access to update given event?
			﹣﹣$canEdit = check_record_permission($table, $id, 'edit');
			﹣﹣if(!$canEdit) {
			﹣﹣﹣﹣@header('HTTP/1.0 403 Forbidden');
			﹣﹣﹣﹣exit('event_update_forbidden');
			﹣﹣}

			﹣﹣$startDate = gmdate('Y-m-d', strtotime($start));
			﹣﹣$startTime = gmdate('H:i:s', strtotime($start));
			﹣﹣$endDate = $end ? gmdate('Y-m-d', strtotime($end)) : '';
			﹣﹣$endTime = $end ? gmdate('H:i:s', strtotime($end)) : '';

			﹣﹣$data = [$startDateField => $startDate];
			﹣﹣if($endDateField) $data[$endDateField] = $endDate;
			﹣﹣if(!$allDay) {
			﹣﹣﹣﹣if($startTimeField) $data[$startTimeField] = $startTime;
			﹣﹣﹣﹣if($endTimeField) $data[$endTimeField] = $endTime;
			﹣﹣}

			﹣﹣// TODO: running before_update and after_update hooks?
			﹣﹣$res = update($table, $data, [$pk => $id]);
			﹣﹣if(!$res) {
			﹣﹣﹣﹣@header('HTTP/1.0 500 Internal Server Error');
			﹣﹣﹣﹣exit('event_update_failed');
			﹣﹣}

			﹣﹣// return with an updated csrf token
			﹣﹣header('Content-Type: application/json');
			﹣﹣echo json_encode([
			﹣﹣﹣﹣'csrf' => csrf_token(false, true),
			﹣﹣﹣﹣'id' => $id,
			﹣﹣﹣﹣'start' => $start,
			﹣﹣﹣﹣'end' => $end,
			﹣﹣﹣﹣'startDate' => $startDate,
			﹣﹣﹣﹣'startTime' => $startTime,
			﹣﹣﹣﹣'endDate' => $endDate,
			﹣﹣﹣﹣'endTime' => $endTime,
			﹣﹣﹣﹣'data' => $data,
			﹣﹣﹣﹣'defaultTimezone' => date_default_timezone_get(),
			﹣﹣]);
		<?php

		$code = ob_get_clean();
		$code = $pl->format_indents(
			str_replace(
				array_keys($replace), 
				array_values($replace), 
				$code
			)
		);

		/* Generating calendar file */
		if(!@file_put_contents($ev_file, $code)) {
			$pl->progress_log->failed();
			return;
		}

		$pl->progress_log->ok();
	}

	function table_dvhook($pl, $path, $table, $events) {
		$dvhook_file = "{$path}/{$table}-dv.js";
		$pl->progress_log->add("Updating {$dvhook_file}  ", 'text-info');
	
		// create hook file if not already there
		if(!@touch($dvhook_file)) {
			$pl->progress_log->failed();
			return;
		}

		ob_start();
		?>

			/* Inserted by Calendar plugin */
			(function($j) {
			﹣﹣var urlParam = function(param) {
			﹣﹣﹣﹣var url = new URL(window.location.href);
			﹣﹣﹣﹣return url.searchParams.get(param);
			﹣﹣};

			﹣﹣var setDate = function(dateField, date, time) {
			﹣﹣﹣﹣var dateEl = $j('#' + dateField);
			﹣﹣﹣﹣if(!dateEl.length) return; // no date field present

			﹣﹣﹣﹣var d = date.split('-').map(parseFloat).map(Math.floor); // year-month-day
			﹣﹣﹣﹣
			﹣﹣﹣﹣// if we have a date field with day and month components
			﹣﹣﹣﹣if($j('#' + dateField + '-mm').length && $j('#' + dateField + '-dd').length) {
			﹣﹣﹣﹣﹣﹣dateEl.val(d[0]);
			﹣﹣﹣﹣﹣﹣$j('#' + dateField + '-mm').val(d[1]);
			﹣﹣﹣﹣﹣﹣$j('#' + dateField + '-dd').val(d[2]);
			﹣﹣﹣﹣﹣﹣return;
			﹣﹣﹣﹣}

			﹣﹣﹣﹣// for datetime fields that have datetime picker, populate with formatted date and time
			﹣﹣﹣﹣if(dateEl.parents('.datetimepicker').length == 1) {
			﹣﹣﹣﹣﹣﹣dateEl.val(
			﹣﹣﹣﹣﹣﹣﹣﹣moment(date + ' ' + time).format(AppGini.datetimeFormat('dt'))
			﹣﹣﹣﹣﹣﹣);
			﹣﹣﹣﹣﹣﹣return;
			﹣﹣﹣﹣}

			﹣﹣﹣﹣// otherwise, try to populate date and time as-is
			﹣﹣﹣﹣dateEl.val(date + ' ' + time);
			﹣﹣};

			﹣﹣$j(function() {
			﹣﹣﹣﹣// continue only if this a new record form
			﹣﹣﹣﹣if($j('[name=SelectedID]').val()) return;

			﹣﹣﹣﹣var params = ['newEventType', 'startDate', 'startTime', 'endDate', 'endTime', 'allDay'], evnt = {};
			﹣﹣﹣﹣for(var i = 0; i < params.length; i++)
			﹣﹣﹣﹣﹣﹣evnt[params[i]] = urlParam('calendar.' + params[i]);

			﹣﹣﹣﹣// continue only if we have a newEventType param
			﹣﹣﹣﹣if(evnt.newEventType === null) return;

			﹣﹣﹣﹣// continue only if event start and end specified
			﹣﹣﹣﹣if(evnt.startDate === null || evnt.endDate === null) return;

			﹣﹣﹣﹣// adapt event data types
			﹣﹣﹣﹣evnt.allDay = JSON.parse(evnt.allDay);
			﹣﹣﹣﹣evnt.start = new Date(evnt.startDate + ' ' + evnt.startTime);
			﹣﹣﹣﹣evnt.end = new Date(evnt.endDate + ' ' + evnt.endTime);

			﹣﹣﹣﹣// now handle various event types, populating the relevent fields
			﹣﹣﹣﹣switch(evnt.newEventType) {
					<?php foreach($events as $ev) { ?>
			﹣﹣﹣﹣﹣﹣case '<?php echo $ev->type; ?>':
						<?php if($ev->startDateField) { ?>
			﹣﹣﹣﹣﹣﹣﹣﹣setDate('<?php echo $ev->startDateField; ?>', evnt.startDate, evnt.startTime);
						<?php } ?>
						<?php if($ev->startTimeField) { ?>
			﹣﹣﹣﹣﹣﹣﹣﹣if(!evnt.allDay) $j('#<?php echo $ev->startTimeField; ?>').val(
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣moment(evnt.startDate + ' ' + evnt.startTime)
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣.format(AppGini.datetimeFormat('t'))
			﹣﹣﹣﹣﹣﹣﹣﹣);
						<?php } ?>
						<?php if($ev->endDateField && $ev->endDateField != $ev->startDateField) { ?>
			﹣﹣﹣﹣﹣﹣﹣﹣setDate('<?php echo $ev->endDateField; ?>', evnt.endDate, evnt.endTime);
						<?php } ?>
						<?php if($ev->endTimeField) { ?>
			﹣﹣﹣﹣﹣﹣﹣﹣if(!evnt.allDay) $j('#<?php echo $ev->endTimeField; ?>').val(
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣moment(evnt.endDate + ' ' + evnt.endTime)
			﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣﹣.format(AppGini.datetimeFormat('t'))
			﹣﹣﹣﹣﹣﹣﹣﹣);
						<?php } ?>
			﹣﹣﹣﹣﹣﹣﹣﹣break;
					<?php } ?>
			﹣﹣﹣﹣}

			﹣﹣﹣﹣// finally, trigger user-defined event handlers
			﹣﹣﹣﹣$j(function() { 
			﹣﹣﹣﹣﹣﹣$j(document).trigger('newCalendarEvent', [evnt]); 
			﹣﹣﹣﹣})
			﹣﹣});
			})(jQuery);
			/* End of Calendar plugin code */

		<?php

		$code = ob_get_clean();
		$code = $pl->format_indents("\n\n" . trim($code) . "\n\n");

		// remove existing calendar code if found
		$old_code = preg_replace(
			/* regex to match dv code from first line to last */
			"/\s*\/\* Inserted by Calendar plugin( on (\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2}))? \*\/" .
			"(.*)" .
			"\/\* End of Calendar plugin code \*\/\s*" .
			"/s", 
			
			/* and remove it */
			'', 
			
			/* from existing code */
			file_get_contents($dvhook_file)
		);

		// finally append new code to clean old one
		if(!@file_put_contents($dvhook_file, $old_code . $code)) {
			$pl->progress_log->failed();
			return;
		}

		$pl->progress_log->ok();
	}

	function copy_resources($pl, $appHooksDir) {
		$dest_resources_dir = realpath("{$appHooksDir}/../resources");
		$plugin_resources_dir = __DIR__ . '/app-resources';

		$pl->progress_log->add("<b>Copying resources</b>", 'text-info');

		// copy fullcalendar
		$pl->recurse_copy("{$plugin_resources_dir}/fullcalendar", "{$dest_resources_dir}/fullcalendar", true, 1);
		
		// copy plugin-calendar folder
		$pl->recurse_copy("{$plugin_resources_dir}/plugin-calendar", "{$dest_resources_dir}/plugin-calendar", true, 1);

		// copy calendar icon for use in links
		$pl->copy_file(
			realpath("{$plugin_resources_dir}/../../plugins-resources/table_icons/calendar.png"), 
			"{$dest_resources_dir}/table_icons/calendar.png", 
			true
		);

		$pl->progress_log->add("<b>Finished copying resources</b>", 'text-success');
	}

	function create_links($pl, $calendars) {
		// retrieve table groups as a 0-based numeric array
		$tableGroups = array_keys(get_table_groups());

		$linksHome = [];
		$linksNavmenu = [];
		foreach($calendars as $calId => $cal) {
			if(!empty($cal->{'links-home'})) 
				$linksHome[] = [
					'url' => "hooks/calendar-{$calId}.php",
					'icon' => 'resources/table_icons/calendar.png', 
					'title' => $cal->title,
					'description' => '',
					'groups' => $cal->groups,
					'grid_column_classes' => 'col-sm-6 col-md-4 col-lg-3',
					'panel_classes' => 'panel-info',
					'link_classes' => 'btn-info',
					// for links-home, pass the table group title
					'table_group'=> $tableGroups[$cal->{'links-home'} - 1],
				];

			if(!empty($cal->{'links-navmenu'})) 
				$linksNavmenu[] = [
					'url' => "hooks/calendar-{$calId}.php",
					'icon' => 'resources/table_icons/calendar.png', 
					'title' => $cal->title,
					'groups' => $cal->groups,
					// for links-navmenu, pass the table group 0-based index
					'table_group'=> $cal->{'links-navmenu'} - 1,
				];
		}

		$pl->add_links('links-home', $linksHome);
		$pl->add_links('links-navmenu', $linksNavmenu);
	}