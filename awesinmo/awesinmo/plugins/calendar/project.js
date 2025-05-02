$j(function() {
	// the calling page should load the AppGini project object
	// into AppGiniPlugin.prj before loading this file
	if(AppGiniPlugin.prj == undefined) return;

	var _prj = {}, project = {}, _data = {}, tables = [];
	const bsColors = [
		{ id: 'default', text: 'Default' },
		{ id: 'success', text: 'Success' },
		{ id: 'warning', text: 'Warning' },
		{ id: 'danger', text: 'Danger' },
		{ id: 'primary', text: 'Primary' },
		{ id: 'info', text: 'Info' }
	];
	const views = [
		{ id: "dayGridMonth", text: "Month" },
		{ id: "timeGridWeek", text: "Week" },
		{ id: "timeGridDay", text: "Day" },
		{ id: "listWeek", text: "List" }
	];
	const locales = [
		{ id: "",		text: "Default (auto-detect)" },
		{ id: "af",		text: "Afrikaans" },
		{ id: "sq",		text: "Albanian" },
		{ id: "ar-dz",	text: "Arabic - Algeria" },
		{ id: "ar-kw",	text: "Arabic - Kuwait" },
		{ id: "ar-ly",	text: "Arabic - Libya" },
		{ id: "ar-ma",	text: "Arabic - Morocco" },
		{ id: "ar-sa",	text: "Arabic - Saudi Arabia" },
		{ id: "ar-tn",	text: "Arabic - Tunisia" },
		{ id: "ar",		text: "Arabic" },
		{ id: "eu",		text: "Basque" },
		{ id: "bs",		text: "Bosnian" },
		{ id: "bg",		text: "Bulgarian" },
		{ id: "ca",		text: "Catalan" },
		{ id: "zh-cn",	text: "Chinese - China" },
		{ id: "zh-tw",	text: "Chinese - Taiwan" },
		{ id: "hr",		text: "Croatian" },
		{ id: "cs",		text: "Czech" },
		{ id: "da",		text: "Danish" },
		{ id: "nl",		text: "Dutch" },
		{ id: "en-au",	text: "English - Australia" },
		{ id: "en-nz",	text: "English - New Zealand" },
		{ id: "en-gb",	text: "English - United Kingdom" },
		{ id: "et",		text: "Estonian" },
		{ id: "fa",		text: "Farsi" },
		{ id: "fi",		text: "Finnish" },
		{ id: "fr-ch",	text: "French - Switzerland" },
		{ id: "fr",		text: "French" },
		{ id: "gl",		text: "Galician" },
		{ id: "ka",		text: "Georgian" },
		{ id: "de",		text: "German" },
		{ id: "el",		text: "Greek" },
		{ id: "he",		text: "Hebrew" },
		{ id: "hi",		text: "Hindi" },
		{ id: "hu",		text: "Hungarian" },
		{ id: "is",		text: "Icelandic" },
		{ id: "id",		text: "Indonesian" },
		{ id: "it",		text: "Italian" },
		{ id: "ja",		text: "Japanese" },
		{ id: "kk",		text: "Kazakh" },
		{ id: "ko",		text: "Korean" },
		{ id: "lv",		text: "Latvian" },
		{ id: "lt",		text: "Lithuanian" },
		{ id: "lb",		text: "Luxembourg" },
		{ id: "mk",		text: "Macedonian" },
		{ id: "ms",		text: "Malay" },
		{ id: "nb",		text: "Norwegian Bokmål" },
		{ id: "nn",		text: "Norwegian Nynorsk" },
		{ id: "pl",		text: "Polish" },
		{ id: "pt-br",	text: "Portuguese - Brazil" },
		{ id: "pt",		text: "Portuguese" },
		{ id: "ro",		text: "Romanian" },
		{ id: "ru",		text: "Russian" },
		{ id: "sr-cyrl",text: "Serbian (Cyrillic)" },
		{ id: "sr",		text: "Serbian (Latin)" },
		{ id: "sk",		text: "Slovak" },
		{ id: "sl",		text: "Slovenian" },
		{ id: "es",		text: "Spanish" },
		{ id: "sv",		text: "Swedish" },
		{ id: "th",		text: "Thai" },
		{ id: "tr",		text: "Turkish" },
		{ id: "uk",		text: "Ukrainian" },
		{ id: "vi",		text: "Vietnamese" }
	];
	var minimalistKey = 'AppGiniPlugins.calendar.minimalist-view';

	const main = function() {
		// main should run only once
		if(AppGiniPlugin._mainAlreadyRun != undefined) return;

		prepCalendarData();

		// set tables to all tables that can contain events (those that have one or more date/datetime field)
		tables = _prj.table
			.filter(t => dateTimeFieldsOfTable(t).length > 0)
			.map(t => ({ id: t.name, text: t.caption }));

		/****** TODO: (Debugging) set to false on production *******/
		AppGiniPlugin.enableDebugging = false;

		// fix for non-searchable select2 inside modal, https://stackoverflow.com/a/19574076/1945185
		$j.fn.modal.Constructor.prototype.enforceFocus = () => {};

		/* place output folder button inside breadcrumb */
		$j('#btn-output-folder').appendTo('.breadcrumb:first');

		checkNotSameAsApp();

		buildEventForm();
		buildCalendarForm();

		handleJSEvents();

		listEvents();
		listCalendars();

		$j('[data-toggle="tooltip"]').tooltip();

		AppGiniPlugin._mainAlreadyRun = true;
	}

	// read project into a short var, _prj and prepare calendar data as a JSON object
	const prepCalendarData = function() {
		_prj = AppGiniPlugin.prj;
		project = AppGiniPlugin.project(_prj);
		
		// calling getProjectPlugin creates the calendar node in case it's not there,
		// so we can safely assume it's there in following code ...
		_data = project.getProjectPlugin('calendar').data || '{}';

		// in case _data is not already JSON-parsed, do so and update other project vars
		if(typeof(_data) != 'object') {
			_data = JSON.parse(_data);
			AppGiniPlugin.prj.plugins.calendar.data = _data;
			_prj = AppGiniPlugin.prj;
			project = AppGiniPlugin.project(_prj);
		}
	}

	// returns an array of all date/time fields of a given table object
	const dateTimeFieldsOfTable = function(t, type) {
		// type: 'd' = date-only, 't' = time-only, 'dt' = datetime/timestamp, else: all date/time fields
		if(type === undefined) type = '*';

		switch(type) {
			case  'd': dataTypes = [9, 10, 11]; break;
			case 'dt': dataTypes = [10, 11]; break;
			case  't': dataTypes = [12]; break;
			default  : dataTypes = [9, 10, 11, 12]; break;
		}

		return t.field.filter(f => dataTypes.indexOf(parseInt(f.dataType)) > -1);
	}

	const handleJSEvents = function() {
		// should run only once via main()
		if(AppGiniPlugin._mainAlreadyRun != undefined) return;

		// clicking new event/calendar should launch new event/calendar form
		$j('.new-event-launcher').on('click', displayNewEventForm);
		$j('.new-calendar-launcher').on('click', displayNewCalendarForm);
		
		// clicking cancel in event/calendar form should close it
		$j('.cancel-event'   ).on('click', () => { displayNewEventForm(false); });
		$j('.cancel-calendar').on('click', () => { displayNewCalendarForm(false); });
		
		// clicking save (submitting) in event/calendar form should save event/calendar to client and server
		$j('#event-form-container').on('submit', (e) => { e.preventDefault(); saveEvent(); });
		$j('#calendar-form-container').on('submit', (e) => { e.preventDefault(); saveCalendar(); });

		// clicking delete in event/calendar form should delete event/calendar on server and client
		$j('.delete-event').on('click', deleteEvent);
		$j('.delete-calendar').on('click', deleteCalendar);

		// event form: when user selects a field from fields list, insert it into title field
		$j('#event-title-fields-list').on('click', 'a', function(e) {
			e.preventDefault();
			insertTextToTitle('{' + $j(this).data('id') + '}'); 
		});
		$j('.minimalist').on('click', function() { minimalistView($j(this)); });

		// calendar form: when user selects an option for initial-date, insert to field
		$j('#calendar-initial-date-options').on('click', 'a', function(e) {
			e.preventDefault();
			insertInitialDate($j(this).data('id'));
		})

		// calendar form: when user selects an event from events dropdown, append to field
		$j('#calendar-events-list').on('click', 'a', function(e) {
			e.preventDefault();
			appendEventToCalendar($j(this).data('id'));
		})
		
		// calendar form: when user selects a group from groups dropdown, append to field
		$j('#calendar-user-groups-list').on('click', 'a', function(e) {
			e.preventDefault();
			appendGroupToCalendar($j(this).data('id'));
		})
		
		$j('.events-list')
			.on('mouseover', '.event-brief', function() { $j(this).addClass('bg-warning'); })
			.on('mouseout', '.event-brief', function() { $j(this).removeClass('bg-warning'); })
			.on('click', '.event-brief', function() {
				displayNewEventForm();
				populateEventForm($j(this).data('type'));
			})
		

		$j('.calendars-list')
			.on('mouseover', '.calendar-brief', function() { $j(this).addClass('bg-warning'); })
			.on('mouseout', '.calendar-brief', function() { $j(this).removeClass('bg-warning'); })
			.on('click', '.calendar-brief', function() {
				displayNewCalendarForm();
				populateCalendarForm($j(this).data('id')); 
			})

		// show project details after all initial ajax requests (currently 2) and language are loaded
		$j(document).on('ajaxComplete', function() {
			AppGiniPlugin._initialAjaxCounter = AppGiniPlugin._initialAjaxCounter || 0;
			AppGiniPlugin._initialAjaxCounter++;

			if(AppGiniPlugin._initialAjaxCounter != 2) return;

			AppGiniPlugin.Translate.ready(function() {
				$j('#project-loading, #workarea').toggleClass('hidden');
				eventsCalendarsSameHeight();
			});
		})
	}

	const strToCSV = function(str) {
		str = str.trim();

		// handle case where str is empty .split() returns an array like this [''] but we want 
		// an empty array []
		return (str == '' ? [] : str.split(', '));
	}

	const updateSeperateCalendarsUI = () => {
		const checkbox = $j('#calendar-form-events-in-separate-calendars');
		const calEvents = $j('#calendar-form-events').val();

		// uncheck and disable the checkbox if only one or no events are selected
		if(strToCSV(calEvents).length < 2) {
			checkbox
				.prop('checked', false)
				.prop('disabled', true)
				.parent().addClass('text-muted disabled');
			return;
		}

		checkbox
			.prop('disabled', false)
			.parent().removeClass('text-muted disabled');
	}

	const appendEventToCalendar = function(selection) {
		const eventsField = AppGiniPlugin.form('#calendar-form').get('events').jDom();
		if(selection == '[clear-events]') {
			eventsField.val('');
			updateSeperateCalendarsUI();
			return;
		}

		//get stored events as an array
		const events = strToCSV(eventsField.val());

		// append event only if it's not already there
		if(events.indexOf(selection) > -1) return;
		events.push(selection);
		eventsField.val(events.join(', '));
		updateSeperateCalendarsUI();
	}

	const appendGroupToCalendar = function(selection) {
		const groupsField = AppGiniPlugin.form('#calendar-form').get('groups').jDom();
		if(selection == '[clear-groups]') {
			groupsField.val('');
			return;
		}

		//get stored groups as an array
		const groups = strToCSV(groupsField.val());

		// append event only if it's not already there
		if(groups.indexOf(selection) > -1) return;
		groups.push(selection);
		groupsField.val(groups.join(', '));
	}

	const insertInitialDate = function(selection) {
		const initDate = AppGiniPlugin.form('#calendar-form').get('initial-date').jDom();
		
		if(selection == '[custom-date]') {
			let today = new Date;
			today = today.getFullYear() + '-' + (today.getMonth() + 1) + '-' + today.getDate();
			initDate.prop('readonly', false).removeAttr('tabindex').val(today).focus();
			return;
		}

		initDate.prop('readonly', true).attr('tabindex', -1).val(selection);
	}

	/**
	 * @param      {string} type='*' return only date fields if 'd', only time fields if 't', datetime only if 'dt', all otherwise
	 * @return     object  table names as keys, array of date/time fields ({ id: field-name, text: field-caption}) as values
	 */
	const dateTimeFields = function(type) {
		const fields = {};

		for(var i = 0; i < _prj.table.length; i++)
			fields[_prj.table[i].name] = dateTimeFieldsOfTable(_prj.table[i], type).map(f => ({ id: f.name, text: f.caption })); 

		return fields;
	}

	const checkNotSameAsApp = function() {
		if(AppGiniPlugin.sameAsApp) return; // all fine

		AppGiniPlugin.Translate.ready(function() {
			$j('#error-not-same-app')
				.removeClass('hidden')
				.find('h3')
				.html(AppGiniPlugin.Translate.word(
					'selected_axp_doesnt_match_app', { 
						app: AppGiniPlugin.hostAppTitle 
					}
				));
			})

		$j('#workarea, #btn-output-folder, #project-loading').remove();
	}

	const buildCalendarForm = function() {
		const form = AppGiniPlugin.form('#calendar-form', 'calendar-form'),
			word = AppGiniPlugin.Translate.word;

		form
			.addHtml('<div class="text-info h4">' + $j('#calendars .panel-footer').html() + '</div>')
			.addSeparator(12)

			.addHtml('<div class="alert alert-danger hidden no-events-for-calendar">' + 
				word('you_havent_created_any_event_types') +
			'</div>')
			
			.add({
				id: 'id',
				label: 'Calendar ID',
				groupClasses: 'col-xs-6',
				help: word('help_calendar_id'),
				required: true,
				maxLength: 20
			})
			.add({
				id: 'title',
				label: 'Title',
				groupClasses: 'col-xs-6',
				help: word('help_calendar_title'),
				required: true,
				maxLength: 50
			})
			.addHtml('<div class="clearfix"></div>')
			.add({ id: 'existing-id', type: 'hidden' })

			.add({
				id: 'initial-view', 
				type: 'select', 
				options: views, 
				label: 'Initial view', 
				groupClasses: 'col-xs-6',
				init: 'dayGridMonth',
				emptyFirstOption: false,
				required: true,
				help: 'Select the initial view users see when opening this calendar.'
			})
			.add({
				id: 'initial-date',
				label: 'Initial date',
				groupClasses: 'col-xs-6',
				init: '[today]',
				required: true,
				help: 'Select the initial date users see when opening this calendar.' +
				      'If choosing a specific date, enter it in the format <code>year-month-day</code>.'
			})
			.addHtml('<div class="clearfix"></div>')

			.add({
				id: 'events',
				label: 'Events',
				groupClasses: 'col-xs-12',
				required: true,
				help: 'This is the list of events displayed in the calendar. ' +
					  'Select the event(s) you want to include from the dropdown.'
			})
			.addHtml('<div class="clearfix"></div>')
			.add({
				id: 'events-in-separate-calendars',
				type: 'checkbox',
				label: 'Use separate calendars for each event type',
				groupClasses: 'col-xs-12',
				help: 'If checked, each event type will be displayed in a separate calendar. ' +
					  'If unchecked, all events will be displayed in the same calendar.'
			})
			.addHtml('<div class="clearfix"></div>')

			.add({
				id: 'locale', 
				type: 'select', 
				options: locales, 
				label: AppGiniPlugin.Translate.word('Locale') +
				       ' <i class="glyphicon glyphicon-info-sign" data-toggle="tooltip" title="' +
				       AppGiniPlugin.Translate.word('translate_calendar') + 
				       '"></i>', 
				groupClasses: 'col-xs-3',
				init: '',
				emptyFirstOption: false,
				required: false,
				help: 'You can force a specific locale for displaying this calendar. Default is based ' +
						'on user\'s browser settings. If you can\'t find the desired locale in the list, ' +
						'it means <a href="https://github.com/fullcalendar/fullcalendar">fullcalendar</a> ' +
						'hasn\'t provided support for it yet, but might be added in future ' +
						'releases of the fullcalendar component.'
			})
			.add({
				id: 'groups',
				label: 'Allowed groups',
				groupClasses: 'col-xs-9',
				required: true,
				help: 'Specify which user groups are allowed to access this calendar. ' +
					  'Each user will see only the events she has access to.'
			})
			.addHtml('<div class="clearfix"></div>')

			.add({
				id: 'links-home', 
				type: 'select', 
				options: [], 
				label: 'Add homepage link in', 
				groupClasses: 'col-xs-6',
				init: '',
				emptyFirstOption: false,
				required: false,
				help: 'Select a homepage panel where you want to place a link to this calendar.'
			})
			.add({
				id: 'links-navmenu', 
				type: 'select', 
				options: [], 
				label: 'Add navigation menu link in', 
				groupClasses: 'col-xs-6',
				init: '',
				emptyFirstOption: false,
				required: false,
				help: 'Select a navigation menu where you want to place a link to this calendar.'
			})
			.addHtml('<div class="clearfix"></div>')

			.add({
				id: 'customFullCalendarOptions',
				type: 'textarea',
				label: 'Custom fullCalendar options',
				rows: 5,
				groupClasses: 'col-xs-12',
				help: 'Optional. Enter custom options to pass to the fullCalendar during initialization. ' +
						'For example, you can set <code>hiddenDays: [0, 6]</code> to hide Saturdays and Sundays. ' +
						'For a full list of options, see the <a href="https://fullcalendar.io/docs/v4" target="_blank">' +
						'fullCalendar v4 documentation <i class="glyphicon glyphicon-share"></i></a>.',
			})


		// wrap initial-date inside an input-group that includes possible initial date options
		addLeftDropdownToTextField(
			form.get('initial-date').jDom(),
			'calendar-initial-date-options',
			'Options',
			[],
			true /* readonly */
		);

		// populate initial-date options
		populateDropdownMenu('calendar-initial-date-options', [
			{ id: '[today]', text: 'Today (default)' },
			{ id: '---', text: '' },
			{ id: '[yesterday]', text: 'Yesterday' },
			{ id: '[last-month]', text: 'Last month' },
			{ id: '[last-year]', text: 'Last year' },
			{ id: '---', text: '' },
			{ id: '[tomorrow]', text: 'Tomorrow' },
			{ id: '[next-month]', text: 'Next month' },
			{ id: '[next-year]', text: 'Next year' },
			{ id: '---', text: '' },
			{ id: '[custom-date]', text: 'Specific date (<code>yyyy-mm-dd</code>)' }
		]);

		// events dropdown is re-populated every time the form is displayed, so this is done in displayNewCalendarForm()

		// wrap events inside an input-group that includes an events list
		addLeftDropdownToTextField(
			form.get('events').jDom(),
			'calendar-events-list',
			'<i class="glyphicon glyphicon-time"></i> Events',
			[],
			true /* readonly */
		);

		// wrap groups inside an input-group that includes list of user groups
		addLeftDropdownToTextField(
			form.get('groups').jDom(),
			'calendar-user-groups-list',
			'Groups',
			[],
			true /* readonly */
		);

		// populate groups dropdown
		populateGroupsDropdown();

		// populate links dropdown
		populateLinksDropdowns();

		// action buttons for calendars
		$j(
			'<button type="button" class="btn btn-sm btn-default minimalist"><i class="glyphicon glyphicon-menu-hamburger"></i> Minimalist view</button>' +
			'<span class="hspacer-xs"></span>' +
			'<button type="button" class="btn btn-sm btn-warning cancel-calendar"><i class="glyphicon glyphicon-remove"></i> Cancel</button>' +
			'<span class="hspacer-xs"></span>' +
			'<button type="submit" class="btn btn-sm btn-success save-calendar"><i class="glyphicon glyphicon-ok"></i> Save</button>' +
			'<span class="hspacer-xs existing-calendar"></span>' +
			'<button type="button" class="btn btn-sm btn-danger delete-calendar existing-calendar"><i class="glyphicon glyphicon-trash"></i> Delete</button>'
		).appendTo('#calendar-form-container .actions');

		form.get('id').jDom().attr('pattern', '^[a-zA-Z][a-zA-Z0-9\\-]*[a-zA-Z0-9]$');

		// add example code as placeholder for customFullCalendarOptions
		form.get('customFullCalendarOptions').jDom()
			.attr('placeholder', `// Example: hide weekends and set time grid range to 9am-5pm\nhiddenDays: [0, 6],\nminTime: '09:00:00',\nmaxTime: '17:00:00',`)
			// set font to monospace
			.css('font-family', 'monospace')
	}

	const populateLinksDropdowns = function() {
		const form = AppGiniPlugin.form('#calendar-form');
		const loading = [{ id: '', text: 'Loading ...' }];

		// if links are cached, populate and return
		if(AppGiniPlugin._tableGroups !== undefined) {
			form.get('links-home').setOptions(AppGiniPlugin._tableGroups);
			form.get('links-navmenu').setOptions(AppGiniPlugin._tableGroups);
			return;
		}

		form.get('links-home').setOptions(loading);
		form.get('links-navmenu').setOptions(loading);

		$j.ajax({
			url: 'ajax-table-groups.json.php',
			success: function(data) {
				AppGiniPlugin._tableGroups = data;
				populateLinksDropdowns();
			},
			error: function() {
				const failed = [{ id: '', text: 'Loading groups failed. Retrying. Please wait ...' }];
				form.get('links-home').setOptions(failed);
				form.get('links-navmenu').setOptions(failed);

				// retry in 30 seconds
				setTimeout(populateLinksDropdowns, 30000);
			}
		})
	}

	const populateGroupsDropdown = function() {
		populateDropdownMenu(
			'calendar-user-groups-list', 
			[{ id: '', text: '<i class="text-info">Loading groups. Please wait ...</i>' }]
		);

		$j.ajax({
			url: 'ajax-groups-list.json.php',
			success: function(data) {
				const options = data.map(di => ({ id: di, text: di }));

				options.unshift({ id: '---' });
				options.unshift({
					id: '[clear-groups]',
					text: '<span class="text-danger"><i class="glyphicon glyphicon-trash"></i> Clear all</span>' 
				});

				populateDropdownMenu('calendar-user-groups-list', options);
			},
			error: function() {
				// retry in 30 seconds
				populateDropdownMenu(
					'calendar-user-groups-list', 
					[{ id: '', text: '<i class="text-danger">Loading groups failed. Retrying. Please wait ...</i>' }]
				);
				setTimeout(populateGroupsDropdown, 30000);
			}
		});
	}

	const addLeftDropdownToTextField = function(jqField, dropdownId, dropDownTitle, options, readonly) {
		if(readonly === undefined) readonly = false;

		// wrap initial-date inside an input-group that includes initial-date options
		if(readonly) jqField.attr('tabindex', '-1');
		jqField.prop('readonly', readonly).wrap('<div class="input-group"></div>');
		$j(
			'<div class="input-group-btn">' +
				'<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">' +
					dropDownTitle + ' <span class="caret"></span>' +
				'</button>' +
				'<ul class="dropdown-menu dropdown-menu-left" id="' + dropdownId + '"></ul>' +
			'</div>'
		).insertBefore(jqField);
	}

	const buildEventForm = function() {
		const form = AppGiniPlugin.form('#event-form', 'event-form')

		form
			.addHtml('<div class="text-info h4">' + $j('#events .panel-footer').html() + '</div>')
			.addSeparator(12)

			.add({
				id: 'type',
				label: 'Event type ID',
				groupClasses: 'col-xs-6',
				help: 'This is an identifier of the event type. ' + 
						'For example <code>planning-task</code>, <code>hotel-reservation</code>, ... etc. ' +
						'Only alphanumeric characters and hyphens are allowed.',
				required: true,
				maxLength: 20
			})
			.add({
				id: 'color', 
				type: 'select', 
				options: bsColors, 
				label: 'BG color', 
				groupClasses: 'col-xs-3',
				init: 'default',
				emptyFirstOption: false,
				required: true,
				help: 'Select the background color class for this event.'
			})
			.add({
				id: 'textColor', 
				type: 'select', 
				options: bsColors, 
				label: 'Text color', 
				groupClasses: 'col-xs-3',
				init: 'default',
				emptyFirstOption: false,
				required: true,
				help: 'Select the text color class for this event.'
			})
			.addHtml('<div class="clearfix"></div>')

			.add({ id: 'existing-type', type: 'hidden' })

			.add({
				id: 'table',
				type: 'select',
				label: 'Source table',
				options: tables,
				groupClasses: 'col-xs-6',
				required: true,
				help: 'Select the source table storing events of this type. Only tables that have date fields are listed.'
			})
			.add({
				id: 'customWhere',
				type: 'textarea',
				label: 'Custom WHERE condition(s)',
				rows: 1,
				groupClasses: 'col-xs-6',
				help: 'Optional. Enter conditions to use in the <code>WHERE</code> clause when retrieving events. ' +
						"For example: <code>`level` = 'important' AND `cancelled` = 'no'</code>.<br>" +
						'<a href="https://www.mysqltutorial.org/mysql-where/" target="_blank" tabindex="-1">' + 
						'WHERE clause help <i class="glyphicon glyphicon-share"></i></a>.'
			})
			.addHtml('<div class="clearfix"></div>')

			.add({
				id: 'title',
				label: 'Title',
				groupClasses: 'col-xs-12',
				required: true,
				help: 'This is the title of the event as displayed in the calendar. ' +
						'Enter/select the field numbers(s) you want to use as event title. ' + 
						'Field numbers must be between curly brackets, for example ' +
						'<code>{2}</code>. ' +
						'You can mix multiple fields and add other characters/spaces in between. ' +
						'Valid examples: <code>{3}, {2}</code>, <code>{1} (issued by {4})</code>, ... etc.'
			})

			.add({ id: 'allDay', type: 'checkbox', label: 'All-day event', groupClasses: 'col-xs-12' })
			.addHtml('<div class="clearfix"></div>')

			.add({ id: 'startDateField', type: 'select', label: 'Start date', groupClasses: 'col-xs-6', required: true })
			.add({ id: 'startTimeField', type: 'select', label: 'Start time', groupClasses: 'col-xs-6' })
			.add({ id: 'endDateField', type: 'select', label: 'End date', groupClasses: 'col-xs-6' })
			.add({ id: 'endTimeField', type: 'select', label: 'End time', groupClasses: 'col-xs-6' })
			.addHtml('<div class="clearfix"></div>')

			// add explanation for handling creation of new events
			.addHtml(
				'<div class="col-xs-12 col-lg-9">' +
					'<a data-toggle="collapse" href="#new-event-handler-hint" class="vspacer-lg">' +
						'Advanced: JavaScript handling of creation of new events' +
					'</a>' +
					'<div id="new-event-handler-hint" class="collapse">' +
						'You can specify a custom event handler to be triggered in the detail view form of the event ' +
						'when a user creates a new event from a calendar. ' +
						'To do so, you should attach the custom event handler <code>newCalendarEvent</code> ' +
						'to the <code>document</code> object. This can be done for example in the ' + 
						'<code>hooks/<i>tablename</i>-dv.js</code> hook file, ' +
						'<code><i>tablename</i>_dv()</code> hook function, ' +
						'<code><i>tablename</i>_header()</code> hook function, ... etc. Here is an example handler: ' +
						'<pre>' +
						'$j(function() {\n' +
						'  $j(document).on(\'newCalendarEvent\', function(e, data) {\n' +
						'    console.log(data.allDay);       // true if the event is an all-day event, false otherwise\n' +
						'    console.log(data.newEventType); // string, the event type ID\n' +
						'    console.log(data.start);        // Date object indicating the event start date/time\n' +
						'    console.log(data.end);          // Date object indicating the event end date/time\n' +
						'  })\n' +
						'})' +
						'</pre>' +
					'</div>' +
				'</div>' +
				'<div class="clearfix"></div>'
			)

		// wrap title inside an input-group that includes a fields list
		addLeftDropdownToTextField(
			form.get('title').jDom(),
			'event-title-fields-list',
			'Fields',
			[]
		);

		// action buttons for events
		$j(
			'<button type="button" class="btn btn-sm btn-default minimalist"><i class="glyphicon glyphicon-menu-hamburger"></i> Minimalist view</button>' +
			'<span class="hspacer-xs"></span>' +
			'<button type="button" class="btn btn-sm btn-warning cancel-event"><i class="glyphicon glyphicon-remove"></i> Cancel</button>' +
			'<span class="hspacer-xs"></span>' +
			'<button type="submit" class="btn btn-sm btn-success save-event"><i class="glyphicon glyphicon-ok"></i> Save</button>' +
			'<span class="hspacer-xs existing-event"></span>' +
			'<button type="button" class="btn btn-sm btn-danger delete-event existing-event"><i class="glyphicon glyphicon-trash"></i> Delete</button>'
		).appendTo('#event-form-container .actions');

		form.get('customWhere').jDom().before(`
			<a 
				class="pull-right" 
				href="../../admin/pageQueryLogs.php?type=error" 
				title="If this condition is not working as expected, check the query logs page for SQL errors"
				target="_blank"
			>Query logs</a>
		`);

		form.get('type').jDom().attr('pattern', '^[a-zA-Z][a-zA-Z0-9\\-]*[a-zA-Z0-9]$');

		form.get('allDay').jDom().on('change', function() {
			const checked = $j(this).prop('checked');
			form.get('startTimeField').jDom().prop('disabled', checked).val('').prev().toggleClass('text-muted', checked);
			form.get('endTimeField').jDom().prop('disabled', checked).val('').prev().toggleClass('text-muted', checked);
		});

		// populate date/time fields and fields list of the title box
		form.get('table').jDom().on('change', function() {
			const t = $j(this).val();

			const dateFields = t ? dateTimeFields('d')[t] : [],
				timeFields = t ? dateTimeFields('t')[t] : [],
				dtFields = t ? dateTimeFields('dt')[t] : [];

			// if the table has no time or datetime or timestamp fields, this should be false
			const noTimeFields = (!timeFields.length && !dtFields.length);

			form.get('startDateField').setOptions(dateFields);
			form.get('startTimeField').setOptions(timeFields);
			form.get('endDateField').setOptions(dateFields);
			form.get('endTimeField').setOptions(timeFields);

			// if no time fields present, check and disable all-day, else uncheck and undisable
			form.get('allDay').jDom()
				.prop('checked', noTimeFields)
				.prop('disabled', noTimeFields)
				.trigger('change')
				.parents('.form-group').toggleClass('text-muted', noTimeFields);

			// populate fields list of title input group
			const tbl = project.getTableByName(t);
			populateEventTitleFieldsList(tbl);
		}).trigger('change');
	}

	const applyMinimalistView = function(form) {
		minimalist = localStorage.getItem(minimalistKey);
		if(minimalist == undefined) minimalist = false;
		minimalist = JSON.parse(minimalist);

		if(form[0].tagName.toLowerCase() != 'form') form = form.parents('form');

		form.find('.minimalist').toggleClass('active', minimalist);
		form.find('.minimalist .glyphicon')
			.toggleClass('glyphicon-minus', minimalist)
			.toggleClass('glyphicon-menu-hamburger', !minimalist);
		form.find('.help-block, .h4, hr').toggleClass('hidden', minimalist);
	}

	const minimalistView = function(btn) {
		const active = !btn.hasClass('active'), form = btn.parents('form');

		// persist minimalist state into localStorage
		localStorage.setItem(minimalistKey, active);

		applyMinimalistView(form);

		// focus first element in current form
		AppGiniPlugin.form('#' + form.find('.data-form').attr('id')).first().jDom().focus();
	}

	/**
	 * populates options in a dropdown menu created previously by addLeftDropdownToTextField()
	 *
	 * @param      {string}  id       The id attribute of the dropdown
	 * @param      {array}   options  The options to populate the dropdown. An array of objects { id: '', text: '' }. the special id of '---' is handled as a divider.
	 */
	const populateDropdownMenu = function(id, options) {
		const menu = $j('#' + id);

		menu.empty();
		for(var i = 0; i < options.length; i++) {
			const li = $j('<li></li>');

			if(options[i].id == '---') {
				li.addClass('divider');
			} else {
				const a = $j('<a href="#"></a>')
					.data('id', options[i].id)
					.html(options[i].text);
				a.appendTo(li);
			}

			li.appendTo(menu);
		}
	}

	const populateEventTitleFieldsList = function(t) {
		var fi = 0;

		t = t || {}; t.field = t.field || [];
		populateDropdownMenu(
			'event-title-fields-list', 
			t.field.map( f => ({
				id: ++fi, 
				text: `${f.caption} <code>{${fi}}</code>` 
			}))
		);

		if(!t.field.length) {
			$j('<li class="text-muted text-italic">&nbsp; Please select <b>source table</b> above to see list of fields here. &nbsp;</li>')
				.appendTo('#event-title-fields-list');
		}
	}

	const insertTextToTitle = function(text) {
		var element = AppGiniPlugin.form('#event-form').get('title').jDom().get(0);
		element.setRangeText(text, element.selectionStart, element.selectionEnd, 'end');
		element.focus();
	}

	const displayNewCalendarForm = function(show) {
		const form = AppGiniPlugin.form('#calendar-form');

		if(show === undefined) show = true;
		show = (show ? true : false); // force show to be a strict boolean

		$j('#workarea, #btn-output-folder').toggleClass('hidden', show);
		$j('#calendar-form-container, .new-calendar').toggleClass('hidden', !show);
		$j('.existing-calendar').addClass('hidden');

		if(show) {
			form.reset();
			applyMinimalistView($j('#calendar-form'));

			// populate events dropdown with available events
			const eventIds = eventsArray();
			const options = eventIds.map(function(e) {
				_data.events[e].textColor = _data.events[e].textColor || 'default';
				return { 
					id: e, 
					text: '<span class="label label-' + _data.events[e].color + ' text-' + _data.events[e].textColor + '">' +
						  	'<i class="glyphicon glyphicon-time"></i> ' + e +
						  '</span>'
				}; 
			});

			options.unshift({ id: '---' });
			options.unshift({
				id: '[clear-events]',
				text: '<span class="text-danger"><i class="glyphicon glyphicon-trash"></i> Clear all</span>' 
			});
			
			populateDropdownMenu('calendar-events-list', options);
			// if no events defined, show warning
			$j('.no-events-for-calendar').toggleClass('hidden', eventIds.length > 0);

			populateLinksDropdowns();

			form.first().jDom().focus();
		}
	}

	const displayNewEventForm = function(show) {
		const form = AppGiniPlugin.form('#event-form');

		if(show === undefined) show = true;
		show = (show ? true : false); // force show to be a strict boolean

		$j('#workarea, #btn-output-folder').toggleClass('hidden', show);
		$j('#event-form-container, .new-event').toggleClass('hidden', !show);
		$j('.existing-event').addClass('hidden');

		if(show) {
			form.reset();
			applyMinimalistView($j('#event-form'));
			form.first().jDom().focus();
		}
	}

	const populateEventForm = function(type) {
		const form = AppGiniPlugin.form('#event-form'),
			ev = getEvent(type),
			vars = {
				/* var: default-value */
				color: 'default',
				textColor: 'default',
				table: '',
				customWhere: '',
				title: '',
				allDay: '',
				startDateField: '',
				startTimeField: '',
				endDateField: '',
				endTimeField: ''
			};

		if(ev === false) return;
		
		$j('.existing-event').removeClass('hidden').find('.label').html(type);
		$j('.new-event').addClass('hidden');

		form.get('existing-type').jDom().val(type);
		form.get('type').jDom().val(type);
		
		for(var i in vars) {
			if(!vars.hasOwnProperty(i)) continue;

			var el = form.get(i).jDom();

			if(el.prop('disabled')) continue;

			if(ev[i] === undefined) ev[i] = vars[i];

			if(el.attr('type') == 'checkbox') {
				el.prop('checked', ev[i]);
			} else {
				el.val(ev[i]);
			}
			
			el.trigger('change');
		}
	}

	const populateCalendarForm = function(id) {
		const form = AppGiniPlugin.form('#calendar-form'),
			cal = getCalendar(id),
			vars = ['title', 'initial-view', 'events', 'initial-date', 'locale', 'groups', 'links-home', 'links-navmenu', 'events-in-separate-calendars', 'customFullCalendarOptions'];

		if(cal === false) return;
		
		$j('.existing-calendar').removeClass('hidden').find('.label').html(cal.id);
		$j('.new-calendar').addClass('hidden');

		form.get('existing-id').jDom().val(id);
		form.get('id').jDom().val(id);

		for(var i = 0; i < vars.length; i++) {
			var el = form.get(vars[i]).jDom();

			//if(el.prop('disabled')) continue;

			if(el.attr('type') == 'checkbox') {
				el.prop('checked', cal[vars[i]]);
			} else if(['events', 'groups'].indexOf(el.attr('name')) != -1) {
				cal[vars[i]] = cal[vars[i]] || [];
				el.val(cal[vars[i]].join(', ').trim());
			} else {
				el.val(cal[vars[i]]);
			}
			
			el.trigger('change');
		}
	}

	const newEvent = function() {
		displayNewEventForm();
	}

	const validateCalendar = function() {
		const form = AppGiniPlugin.form('#calendar-form');
		const existingId = form.get('existing-id').jDom().val(),
			newId = form.get('id'),
			existingIdData = getCalendar(newId.jDom().val());

		if(
			// for existing calendars, if calendar ID is changed, make sure it's unique
			(existingId && newId.jDom().val() != existingId && existingIdData) ||

			// for new calendar, make sure calendar ID is unique
			(!existingId && existingIdData)
		) {
			newId.error('The specified calendar ID already exists. Please specify a different ID.');
			return false;
		}

		// make sure at least one event is specified
		const events = form.get('events');
		if(events.jDom().val().trim() == '') {
			events.error('You must specify one or more events to include in this calendar. ' +
				'You probably don\'t want to just display an empty calendar, do you? ;)');
			return false;
		}

		// make sure at least one user group is specified
		const groups = form.get('groups');
		if(groups.jDom().val().trim() == '') {
			groups.error('You must specify one or more user groups.');
			return false;
		}

		// if fullcalendar custom options are specified, make sure they are valid object properties
		const customOptions = form.get('customFullCalendarOptions');
		if(customOptions.jDom().val().trim() != '') {
			try {
				eval('var x = {' + customOptions.jDom().val() + '}');
			} catch(e) {
				customOptions.error('Invalid JavaScript object properties. ' + e.message);
				return false;
			}
		}

		// html5 validation already in place .. so we just need to send data to the server
		return true;
	}

	const validateEvent = function() {
		const form = AppGiniPlugin.form('#event-form');
		const existingType = form.get('existing-type').jDom().val(),
			newType = form.get('type'),
			existingTypeData = getEvent(newType.jDom().val());

		if(
			// for existing events, if event type ID is changed, make sure it's unique
			(existingType && newType.jDom().val() != existingType && existingTypeData) ||

			// for new type, make sure type ID is unique
			(!existingType && existingTypeData)
		) {
			newType.error('The specified event type ID already exists. Please specify a different ID.');
			return false;
		}


		// html5 validation already in place .. so we just need to send data to the server
		return true;
	}

	const getCalendar = function(id) {
		if(id === undefined) return false;
		if(_data.calendars === undefined) return false;
		if(_data.calendars.hasOwnProperty(id)) return _data.calendars[id];

		return false;
	}

	const getEvent = function(type) {
		if(type === undefined) return false;
		if(_data.events === undefined) return false;
		if(_data.events.hasOwnProperty(type)) return _data.events[type];

		return false;
	}

	const saveDataToProject = function() {
		project.setProjectPlugin('calendar', {data: _data});
	}

	const deleteEvent = function() {
		const form = AppGiniPlugin.form('#event-form');
		const existingType = form.get('existing-type').jDom().val();

		if(!confirm(
			'Are you sure you want to delete the event type "' + 
			existingType + 
			'"?\nThis would also delete it from any calendars.')
		) return;

		deleteEventClientSide(existingType);
		saveServerSide(function() {
			displayNewEventForm(false); 
			listEvents(); 
			listCalendars(); 
		});
	}

	const deleteCalendar = function() {
		const form = AppGiniPlugin.form('#calendar-form');
		const existingId = form.get('existing-id').jDom().val(), 
			title = form.get('title').jDom().val();

		if(!confirm('Are you sure you want to delete the calendar "' + title + '"?')) return;

		deleteCalendarClientSide(existingId);
		saveServerSide(function() {
			displayNewCalendarForm(false); 
			listCalendars(); 
		});
	}

	const deleteCalendarClientSide = function(id) {
		// delete calendar from project obj
		delete _data.calendars[id];
		saveDataToProject();
	}

	/**
	 * find each occurance of given event type in every defined calendar and performs specified action
	 *
	 * @param      {string}  eventType  The event type
	 * @param      {string}  action     'delete' or 'rename'
	 * @param      {string}  newType    The new type in case of 'rename' action
	 */
	const eventInCalendars = function(eventType, action, newType) {
		if(typeof(_data.calendars) != 'object') return;
		
		// loop through all calendars and find event in every calendar where it's included
		for(var id in _data.calendars) {
			if(!_data.calendars.hasOwnProperty(id)) continue;
			const index = _data.calendars[id].events.indexOf(eventType);
			if(index < 0) continue;

			switch(action) {
				case 'delete':
					_data.calendars[id].events.splice(index, 1);
					break;
				case 'rename':
					_data.calendars[id].events[index] = newType;
					break;
			}
		}
	}

	const deleteEventClientSide = function(type) {
		// delete event from project obj
		delete _data.events[type];
		eventInCalendars(type, 'delete');

		saveDataToProject();
	}

	const saveEventClientSide = function() {
		const form = AppGiniPlugin.form('#event-form');
		const existingType = form.get('existing-type').jDom().val(),
			newType = form.get('type').jDom().val(),
			event = form.toObject();
		
		delete event['existing-type'];
		_data.events = _data.events || {};


		// event exists and id changed? remove old type and rename in calendars
		if(existingType && existingType != newType) {
			delete _data.events[existingType];
			eventInCalendars(existingType, 'rename', newType);
		}

		_data.events[newType] = event;
		saveDataToProject();
	}

	const saveCalendarClientSide = function() {
		const form = AppGiniPlugin.form('#calendar-form');
		const existingId = form.get('existing-id').jDom().val(),
			newId = form.get('id').jDom().val(),
			calendar = form.toObject();

		// convert calendar.events and calendar.groups to an array rather than a string
		calendar.events = strToCSV(calendar.events);
		calendar.groups = strToCSV(calendar.groups);
		
		delete calendar['existing-id'];
		_data.calendars = _data.calendars || {};


		// calendar exists and id changed?
		if(existingId && existingId != newId) delete _data.calendars[existingId];

		_data.calendars[newId] = calendar;
		saveDataToProject();
	}

	const saveServerSide = function(onComplete) {
		$j('#error-saving-project').addClass('hidden');

		$j.ajax({
			url: 'ajax-update-project.php',
			type: 'POST',
			data: {
				axp: AppGiniPlugin.axp_md5,
				data: JSON.stringify(_data)
			},
			error: function() {
				(function(func, cb, sec) {
					$j('#error-saving-project').removeClass('hidden');
					setTimeout(function() { func(cb); }, sec * 1000);
				})(saveServerSide, onComplete, 30);
			},
			complete: onComplete
		});
	}

	/**
	 * @return {array} ids (types) of events defined in project
	 */
	const eventsArray = function() {
		if(_data.events == undefined) return [];
		return Object.keys(_data.events);
	}

	/**
	 * @return {array} ids of calendars defined in project
	 */
	const calendarsArray = function() {
		if(_data.calendars == undefined) return [];
		return Object.keys(_data.calendars);
	}

	const listCalendars = function() {
		const calendarIds = calendarsArray(), el = $j('.calendars-list');

		$j('.no-calendars').toggleClass('hidden', calendarIds.length > 0);
		el.toggleClass('hidden', calendarIds.length == 0);

		if(!calendarIds.length) {
			eventsCalendarsSameHeight();
			return;
		}

		el.empty();
		for(var id = 0; id < calendarIds.length; id++) {
			const cal = _data.calendars[calendarIds[id]];
			calendarHtml(cal).appendTo(el);
		}
		eventsCalendarsSameHeight();
	}

	const listEvents = function() {
		const eventIds = eventsArray(), el = $j('.events-list');

		$j('.no-events').toggleClass('hidden', eventIds.length > 0);
		el.toggleClass('hidden', eventIds.length == 0);

		if(!eventIds.length) {
			eventsCalendarsSameHeight();
			return;
		}

		el.empty();
		for(var id = 0; id < eventIds.length; id++) {
			const ev = _data.events[eventIds[id]];
			eventHtml(ev).appendTo(el);
		}
		eventsCalendarsSameHeight();
	}

	const calendarHtml = function(calendar) {
		// TODO
		calendar.events = calendar.events || [];
		return $j(
			'<div class="calendar-brief" data-id="' + calendar.id + '">' +
				/*
				'<span class="event-color label label-' + event.color + '">' +
					'<i class="glyphicon glyphicon-time"></i>' +
				'</span>' +
				*/
				'<i class="glyphicon glyphicon-calendar"></i>' +
				'<span class="calendar-title">' + 
					calendar.title + 
				'</span>' +
				'<span class="well well-sm" title="Events in this calendar">' + calendar.events.join(', ') + '</span>' +
			'</div>'
		);
	}

	const eventHtml = function(event) {
		return $j(
			'<div class="event-brief" data-type="' + event.type + '">' +
				'<span class="event-color label label-' + event.color + ' text-' + event.textColor + '">' +
					'<i class="glyphicon glyphicon-time"></i>' +
				'</span>' +
				'<span class="event-type">' + 
					event.type + 
				'</span>'+
			'</div>'
		);
	}

	const saveCalendar = function() {
		if(!validateCalendar()) return false;

		saveCalendarClientSide();
		saveServerSide(function() { 
			displayNewCalendarForm(false); 
			listCalendars();
		});

		return false; // prevent browser from launching a server-side request
	}

	const saveEvent = function() {
		if(!validateEvent()) return false;

		saveEventClientSide();
		saveServerSide(function() { 
			displayNewEventForm(false); 
			listEvents();
			listCalendars();
		});

		return false; // prevent browser from launching a server-side request
	}

	const eventsCalendarsSameHeight = function() {
		sameHeight(['#events .panel-body', '#calendars .panel-body']);
		sameHeight(['#events .panel-footer', '#calendars .panel-footer']);
	}

	const sameHeight = function(els) {
		// if els is not an array of at least 2 existing elements, return
		if(els.length == undefined) return;

		// remove elements that are not valid DOM nodes
		els.filter(function(e) { return $j(e).length; });
		if(els.length < 2) return;

		els = els.map(function(e) { return $j(e); });

		var maxHeight = 0;
		for(var i = 0; i < els.length; i++) {
			els[i].height('auto');
			maxHeight = Math.max(maxHeight, els[i].height());
		}

		for(var i = 0; i < els.length; i++) els[i].height(maxHeight);
	}

	AppGiniPlugin.Translate.ready(main);
})
