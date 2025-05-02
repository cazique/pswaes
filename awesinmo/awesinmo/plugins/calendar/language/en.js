AppGiniPlugin.language = AppGiniPlugin.language || {};
AppGiniPlugin.language.en = $j.extend(AppGiniPlugin.language.en, {
	GENERATE_CALENDAR_FILES: 'GENERATE CALENDAR FILES',
	calendar_brief: 'A calendar can contain one or more types of events.',
	event_types: 'Event types',
	New: 'New',
	select_another_project: 'Select another project',
	events_brief: 'Events are items (records from a table) that occur at a specific date (and time). ' + 
	              'You can create multiple types of events. ' + 
	              'Each type can be retrieved from a different table ' + 
	              '(or same table with different criteria). ' + 
	              'You can then specify which event types can appear in each calendar.',
	selected_axp_doesnt_match_app: 'The selected project file doesn\'t match the current app ' +
	                               '<code>%app%</code>. ' +
	                               'Please make sure to upload the correct, up-to-date project file (That is, the project file you used to generate the current app).',
	project_couldnt_be_saved_retry: 'Project couldn\'t be saved due to connection error. ' +
	                                'Will automatically retry again.',
	you_havent_created_any_event_types: 'You haven\'t created any event types yet. You must create at least ' +
	                                    'one event type before you can create a calendar.',
	help_calendar_id: 'This is an identifier of the calendar. ' + 
	                  'For example <code>lab1-calendar</code>, <code>hr-interviews</code>, ... etc. ' +
	                  'Only alphanumeric characters and hyphens are allowed.',
	help_calendar_title: 'This is the calendar title, as displayed to users. ' + 
	                     'For example <code>Lab1 calendar</code>, <code>HR interviews</code>, ... etc. ' +
	                     '50 characters max.',
	Locale: 'Locale',
	translate_calendar: 'To fully translate the calendar pages, please read the instructions at ' +
	                    'plugins/calendar/app-resources/plugin-calendar/language/README.txt',



	/*************************************************************/
	end_place_holder: '--- Please keep this line at the end of the file! ---'
})