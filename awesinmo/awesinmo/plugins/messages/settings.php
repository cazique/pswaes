<?php
	include_once(__DIR__ . '/messages.php');

	$messages = new messages([
		'title' => 'Messages',
		'name' => 'messages',
		'logo' => 'messages-logo-lg.png',
		'version' => 1.5,
	]);

	handleFullPageRequest();
	if(!$messages->dbInstalled()) die('Messages plugin is not yet installed in this app.');

	handleEnableRequest();
	handleBooleanSetting('notify-by-email');
	handleBooleanSetting('open-inbox-in-new-page');
	handleBooleanSetting('show-table-menus');
	handleSetting('recipient-user-format');
	handleGetGroupPermissions();
	handleUpdateGroupPermissions();

	/******************************************************/

	function handleFullPageRequest() {
		if(is_ajax()) return;

		ob_start();
		include(__DIR__ . '/header.php');
		global $Translation;

		echo $messages->header_nav();
		echo $messages->breadcrumb([
			'../../' => $Translation["user's area"],
			'./' => '<span class="language" data-key="messages">Messages</span>',
			'' => '<span class="language" data-key="settings">Settings</span>'
		]);
		$header = ob_get_clean();

		if(!$messages->dbInstalled()) {
			echo $header;
			?>
				<div class="alert alert-danger text-center text-bold">
					<span class="language" data-key="messages_plugin_not_installed">Messages plugin is not yet installed in this app.</span>
					<a href="install.php" class="btn btn-default language" data-key="install_messages">Install Messages plugin</a>
				</div>
			<?php
			include(__DIR__ . '/footer.php');
			exit;
		}

		$groups = getGroups();

		echo $header . 
			settingsForm([
				'messagesEnabled' => $messages->enabled(),
				'notify-by-email' => $messages->setting('notify-by-email'),
				'open-inbox-in-new-page' => $messages->setting('open-inbox-in-new-page'),
				'show-table-menus' => $messages->setting('show-table-menus'),
				'group-permissions' => array_map([$messages, 'groupPermissions'], array_values($groups)),
				'groupName' => $groups,
				'recipient-user-format' => $messages->setting('recipient-user-format'),
				'adminConfig' => config('adminConfig'),
			]) . 
			links() .
			jsCode();

		include(__DIR__ . '/footer.php');
		exit;
	}

	function handleEnableRequest() {
		if(!Request::has('enabled')) return;

		global $Translation;

		if(!csrf_token(true))
			MessagesDB::badRequest($Translation['csrf token expired or invalid']);

		global $messages;
		$messages->enabled(Request::val('enabled') != 'false');
	}

	function handleBooleanSetting($setting) {
		if(!Request::has($setting)) return;

		$_REQUEST[$setting] = (intval(Request::val($setting) != 'false'));
		handleSetting($setting);
	}

	function handleSetting($setting) {
		if(!Request::has($setting)) return;

		global $Translation;

		if(!csrf_token(true))
			MessagesDB::badRequest($Translation['csrf token expired or invalid']);

		global $messages;
		$messages->setting($setting, Request::val($setting));
	}

	function handleGetGroupPermissions() {
		if(!Request::val('getGroupPermissions')) return;

		global $messages;
		$groups = getGroups();

		@header('Content-type: application/json');
		$groupPermissions = array_map([$messages, 'groupPermissions'], array_values($groups));
		echo json_encode($groupPermissions);
	}

	function handleUpdateGroupPermissions() {
		if(!Request::val('updateGroupPermissions')) return;
		if(!Request::val('groupID')) return;

		global $Translation;

		if(!csrf_token(true))
			MessagesDB::badRequest($Translation['csrf token expired or invalid']);

		global $messages;
		$groups = getGroups();

		update(
			'appgini_messages_group_permissions',
			[
				'hasAccess' => Request::val('hasAccess') ? 1 : 0,
				'allowedRecipientGroupIDs' => Request::val('allowedRecipientGroupIDs'),
				'canSendGroupMessage' => Request::val('canSendGroupMessage') ? 1 : 0,
				'canSendGlobalMessage' => Request::val('canSendGlobalMessage') ? 1 : 0,
				'maxRecipients' => max(1, intval(Request::val('maxRecipients')))
			],
			['groupID' => Request::val('groupID')]
		);

		@header('Content-type: application/json');
		$groupPermissions = array_map([$messages, 'groupPermissions'], array_values($groups));
		echo json_encode($groupPermissions);
	}

	function jsCode() {
		global $Translation;

		ob_start(); ?>

		<link rel="stylesheet" type="text/css" href="../../resources/select2/select2.css">
		<script src="../../resources/select2/select2.min.js"></script>

		<script>
			csrf_token = <?php echo json_encode(csrf_token(false, true)); ?>;

			$j(() => {
				const handleCheckboxProp = (prop, success) => {
					$j(`input[name="${prop}"]`).on('click', function() {
						let chkbox = $j(this),
							data = { csrf_token };

						data[prop] = chkbox.prop('checked');

						chkbox.prop('disabled', true).parent().addClass('text-muted');
						$j.ajax({
							url: 'settings.php',
							data,
							success: (resp) => {
								chkbox.prop('disabled', false).parent().removeClass('text-muted');
								if(typeof(success) == 'function') success(data[prop]);
							}
						})
					})
				}

				const handleTextProp = (prop, success) => {
					$j(`input[name="${prop}"]`).on('change', function() {
						let txtbox = $j(this),
							data = { csrf_token };

						data[prop] = txtbox.val();

						txtbox.prop('disabled', true).parent().addClass('text-muted');
						$j.ajax({
							url: 'settings.php',
							data,
							success: (resp) => {
								txtbox.prop('disabled', false).parent().removeClass('text-muted');
								if(typeof(success) == 'function') success(data[prop]);
							}
						})
					})
				}

				const renderGroupPermissions = (data) => {
					let yes = '<i class="glyphicon glyphicon-ok text-success"></i>',
						no = '<i class="glyphicon glyphicon-remove text-danger"></i>',
						tbody = $j('.table-group-permissions tbody'),
						groupNames = (ids) => {
							if(!ids || !ids.trim().length) return '';

							let idsArr = ids.split(',').map($j.trim);
							if(idsArr.indexOf('*') > -1) return '*'; // * overrides any specified groups

							let groups = <?php echo json_encode(getGroups(false, true)); ?>;
							return idsArr.reduce((ns, id) => {
								for(g of groups)
									if(g.id == id && ns.indexOf(g.name) == -1)
										return ns.concat(g.name);
								return ns; // current item in idsArr already added, or doesn't match any groupID
							}, []).join(', ');
						};
					tbody.empty();
					for(gp of data) {
						let tr = $j(`<tr class="group-permissions" style="cursor: pointer;">
								<th>${gp.groupName}</th>
								<td class="text-center">${parseInt(gp.hasAccess) ? yes : no}</td>
								<td class="text-center">${(gp.allowedRecipientGroupIDs == '*' ? AppGiniPlugin.Translate.word('ALL') : groupNames(gp.allowedRecipientGroupIDs)) || no}</td>
								<td class="text-center">${parseInt(gp.canSendGroupMessage) ? yes : no}</td>
								<td class="text-center">${parseInt(gp.canSendGlobalMessage) ? yes : no}</td>
								<td class="text-right">${Math.max(1, parseInt(gp.maxRecipients ?? 0))}</td>
							</tr>`);

						tr.data('id', gp.groupID).data('permissions', gp);
						tr.appendTo(tbody);
					}

					// if data is empty, show a message
					if(!data.length) {
						tbody.html('<tr><td colspan="6" class="text-center"><i class="glyphicon glyphicon-info-sign"></i> No groups other than Admins found.</td></tr>');
					}
				}

				const getGroupPermissions = () => {
					let tbody = $j('.table-group-permissions tbody'),
						oldHtml = tbody.html();
					
					// show loader
					tbody.html('<tr><td colspan="6" class="text-center"><i class="glyphicon glyphicon-refresh loop-rotate"></i> Loading messaging permissions ...</td></tr>');

					$j.ajax({
						url: 'settings.php?getGroupPermissions=1',
						success: renderGroupPermissions,
						error: () => { tbody.html(oldHtml); }
					})
				}

				const groupPermissionsForm = {
					placeholder: () => $j('.group-permissions-form'),
					request: (data) => {
						data.updateGroupPermissions = 1;
						data.csrf_token = csrf_token;
						$j.ajax({
							url: 'settings.php',
							data,
							success: groupPermissionsForm.response,
							complete: () => {
								$j('.modal').modal('hide');
								// when modal is hidden, remove it from DOM
								$j('.modal').on('hidden.bs.modal', function() {
									$j(this).remove();
								})
							}
						})
					},
					response: (data) => {
						renderGroupPermissions(data)
					},
					render: (data) => {
						$j('.modal').remove();
						modal_window({
							message: `
								<div class="group-permissions-form">
									<div class="checkbox">
										<label>
											<input type="checkbox" value="1" ${parseInt(data.hasAccess) ? 'checked' : ''} class="hasAccess">
											<b class="language" data-key="can_access_messages">Can access messages</b>
										</label>
									</div>
									<hr>
									<div class="form-group">
										<label for="name" class="control-label language" data-key="allowed_recipient_groups">Allowed recipient groups</label>
										<select multiple class="allowedRecipientGroupIDs" style="width: 100%;"></select>
									</div>
									<hr>
									<div class="checkbox">
										<label>
											<input type="checkbox" value="1" ${parseInt(data.canSendGroupMessage) ? 'checked' : ''} class="canSendGroupMessage">
											<span class="language" data-key="can_send_to_entire_group">Can send messages to an entire group</span>
										</label>
									</div>
									<div class="checkbox">
										<label>
											<input type="checkbox" value="1" ${parseInt(data.canSendGlobalMessage) ? 'checked' : ''} class="canSendGlobalMessage">
											<span class="language" data-key="can_send_global_message">Can send global messages (to all users)</span>
										</label>
									</div>
									<hr>
									<div class="form-group">
										<label class="control-label language" data-key="max_recipients">Maximum number of recipients allowed in each message</label>
										<input type="number" style="width: 7em;" class="form-control maxRecipients" value="${Math.max(1, data.maxRecipients ?? 0)}">
										<span class="help-block language" data-key="applicable_if_no_group_global">Applicable only if user can't send group or global messages.</span>
									</div>
								</div>`,
							title: `<span class="language" data-key="group_permissions_for" data-group="&lt;b&gt;${data.groupName}&lt;/b&gt;">Messaging permissions for %group% group</span>`,
							footer: [{
								label: '<i class="glyphicon glyphicon-ok"></i> <?php echo makeSafe($Translation['save changes']); ?>',
								bs_class: 'success',
								click: () => { 
									let allowedRecipientGroupIDs;
									if($j('.allowedRecipientGroupIDs').select2('val').indexOf('*') > -1)
										allowedRecipientGroupIDs = '*';
									else
										allowedRecipientGroupIDs = $j('.allowedRecipientGroupIDs').select2('val').join(',');

									groupPermissionsForm.request({
										groupID: data.groupID,
										hasAccess: $j('.modal .hasAccess').prop('checked') ? 1 : 0,
										allowedRecipientGroupIDs,
										canSendGroupMessage: $j('.modal .canSendGroupMessage').prop('checked') ? 1 : 0,
										canSendGlobalMessage: $j('.modal .canSendGlobalMessage').prop('checked') ? 1 : 0,
										maxRecipients: Math.max(1, parseInt($j('.modal .maxRecipients').val()))
									})
								}
							}]
						})

						// when modal is displayed, apply select2 to allowed-recipient-groups
						let checkUntil = setInterval(() => {
							let form = groupPermissionsForm.placeholder();
							if(!form.length) return;

							let groups = <?php echo json_encode(getGroups(false, true)); ?>,
								recipientsSelect = form.find('.allowedRecipientGroupIDs'),
								recipients = (data.allowedRecipientGroupIDs ?? '').split(',').map($j.trim);
							groups.push({ id: '*', name: AppGiniPlugin.Translate.word('ALL_GROUPS')});

							for(let group of groups)
								$j('<option></option>')
									.val(group.id)
									.text(group.name)
									.prop('selected', recipients.indexOf(String(group.id)) > -1)
									.appendTo(recipientsSelect);

							recipientsSelect.select2();

							clearInterval(checkUntil);
						}, 50);
					}
				}

				AppGiniPlugin.Translate.ready(() => {
					handleCheckboxProp('enabled', (enabled) => {
						$j('.messages-disabled').toggleClass('hidden', enabled);
					});
					handleCheckboxProp('notify-by-email');
					handleCheckboxProp('open-inbox-in-new-page');
					handleCheckboxProp('show-table-menus');
					handleTextProp('recipient-user-format')
	
					$j('.table-group-permissions'). on('click', '.group-permissions', function() {
						let tr = $j(this);
						groupPermissionsForm.render(tr.data('permissions'));
					})
	
					getGroupPermissions();
				})
			})
		</script>

		<?php return ob_get_clean();
	}

	function settingsForm($data = []) {
		global $Translation;

		$yes = '<i class="glyphicon glyphicon-ok text-success"></i>';
		$no  = '<i class="glyphicon glyphicon-remove text-danger"></i>';
		ob_start(); ?>

		<div class="alert alert-danger messages-disabled <?php echo $data['messagesEnabled'] ? 'hidden' : ''; ?>">
			<i class="glyphicon glyphicon-info-sign"></i> 
			<span class="language" data-key="messages_plugin_disabled">Messages plugin disabled.</span>
		</div>

		<form>
			<div class="row">
				<div class="col-sm-6 col-lg-4">

					<div style="height: 2em;"></div>
					<div class="checkbox">
						<label>
							<input type="checkbox" name="enabled" value="1" <?php echo $data['messagesEnabled'] ? 'checked' : ''; ?>>
							<span class="text-bold language" data-key="messages_plugin_enabled">Messages plugin enabled</span>
							<span class="help-block language" data-key="messages_plugin_enabled_help">If you disable messages, users won't be able to access their messages or see message notifications until you re-enable this setting. Existing messages will be preserved.</span>
						</label>
					</div>

					<div class="checkbox">
						<label>
							<input type="checkbox" name="notify-by-email" value="1" <?php echo $data['notify-by-email'] ? 'checked' : ''; ?>>
							<span class="text-bold language" data-key="send_email_to_recipeients">Send email notification to recipients</span>
							<span class="help-block language">
								<span class="language" data-key="send_email_to_recipeients_help">When this setting is on, recipients will receive an email notification everytime someone sends a message.</span>
								<a target="_blank" href="../../admin/pageSettings.php?search-settings=smtp"><?php echo $Translation['configure mail settings']; ?></a></span>
						</label>
					</div>

					<div class="checkbox">
						<label>
							<input type="checkbox" name="open-inbox-in-new-page" value="1" <?php echo $data['open-inbox-in-new-page'] ? 'checked' : ''; ?>>
							<span class="text-bold language" data-key="open_inbox_in_new_page">Open inbox in new page</span>
							<span class="help-block"></span>
						</label>
					</div>
					
					<div class="checkbox">
						<label>
							<input type="checkbox" name="show-table-menus" value="1" <?php echo $data['show-table-menus'] ? 'checked' : ''; ?>>
							<span class="text-bold language" data-key="show_table_menus">Show tables navigation in inbox page</span>
							<span class="help-block"></span>
						</label>
					</div>

					<div class="form-group tspacer-lg">
						<label class="language" for="recipient-user-format" data-key="recipient_user_format"></label>
						<input
							id="recipient-user-format"
							name="recipient-user-format"
							value="<?php echo html_attr($data['recipient-user-format']); ?>"
							class="form-control"
						>
						<span class="help-block">
							<span class="language" data-key="valid_variables"></span>:<br>
							<code>username</code>
							<?php for($i = 1; $i < 5; $i++) { ?>
								<code title="<?php
									echo html_attr($data['adminConfig']["custom{$i}"]);
								?>">custom<?php echo $i; ?></code>
							<?php } ?>
						</span>
					</div>
				</div>

				<div class="col-sm-6 col-lg-8">
					<div class="table-responsive">
						<table class="table table-striped table-hover table-group-permissions">
							<caption class="h3">
								<i class="glyphicon glyphicon-th-list"></i>
								<span class="language" data-key="group_permissions">Messaging permissions</span>
							</caption>
							<thead>
								<tr>
									<th class="language" data-key="group">Group</th>
									<th class="text-center language" data-key="access?">Access?</th>
									<th class="text-center language" data-key="allowed_recipient_groups">Allowed recipient groups</th>
									<th class="text-center language" data-key="group_messages?">Group messages?</th>
									<th class="text-center language" data-key="global_messages?">Global messages?</th>
									<th class="text-right language" data-key="max_recip">Max recipients</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>

		</form>

		<?php		
		return ob_get_clean();
	}

	function links() {
		ob_start(); ?>

		<div class="row tspacer-lg">
			<div class="col-xs-4">
				<a href="developers-guide.php" class="btn btn-default btn-block">
					<i class="glyphicon glyphicon-book text-primary"></i>
					<span class="language text-primary" data-key="dev_guide">Developers' guide</span>
				</a>
			</div>
			<div class="col-xs-4">
				<a href="stats.php" class="btn btn-default btn-block">
					<i class="glyphicon glyphicon-stats text-primary"></i>
					<span class="language text-primary" data-key="stats">Stats</span>
				</a>
			</div>
			<div class="col-xs-4">
				<a href="uninstall.php" class="btn btn-default btn-block">
					<i class="glyphicon glyphicon-remove text-danger"></i>
					<span class="language text-danger" data-key="uninstall_messages">Uninstall</span>
				</a>
			</div>
		</div>

		<?php return ob_get_clean();
	}

	function getGroups($exceptAdmins = true, $asIdName = false) {
		$eo = ['silentErrors' => true];
		$groups = []; $anonGroup = makeSafe(config('adminConfig')['anonymousGroup']);
		$exceptions = ($exceptAdmins ? "'Admins', '$anonGroup'" : "'$anonGroup'");

		$res = sql("SELECT `groupID`, `name` FROM `membership_groups` 
				WHERE `name` NOT IN ($exceptions) 
				ORDER BY `name`", $eo);
		while($row = db_fetch_assoc($res))
			if($asIdName)
				$groups[] = ['id' => $row['groupID'], 'name' => $row['name']];
			else
				$groups[$row['groupID']] = $row['name'];

		return $groups;
	}

