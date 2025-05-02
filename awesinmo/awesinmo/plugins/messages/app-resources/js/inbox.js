$j(() => {
	// if page has no messages list element, abort
	var messagesList = $j('.messages-list');
	if(!messagesList.length) return;

	var folders = ['inbox', 'starred', 'sent', 'drafts'];
	var msgRequest = null; // used to save ajax req for messages
	var msgRequestInProgress = false; // used to indicate if an ajax req for messages is running
	var me = $j('.username:first').text(); // current username
	var threads = []; // message threads cache, an array of arrays (threads) of messages
	var lastUpdateTS = 0;
	var word = AppGiniPlugin.Translate.word;

	// initializations on page ready
	var init = () => {
		// https://stackoverflow.com/a/19574076/1945185
		$j.fn.modal.Constructor.prototype.enforceFocus = () => {};

		// select inbox
		$j('.folders > li:nth-child(1)').addClass('active').children('a').focus();

		// translate UI and execute callback when done
		translateUI(() => {
			populateRecipientsList();

			// load threads, silently
			getThreads(true);

			// handle url params (to launch compose, with a prepopulated message)
			handleUrlParams();

			// periodically update message list if needed
			// it's ok to run this very frequently as actual DOM node won't be redrawn
			// unless 'shadow DOM' is different
			setInterval(() => {
				updateMessagesList();
				updateFolderCues();
				updateMassActions();
			}, 200);
		});

		// enable message debug button only in localhost environment
		// $j('.btn-debug').toggleClass('hidden', location.hostname != 'localhost');
	}

	var handleUrlParams = () => {
		// if compose=1 was not passed to url or no compose permission, abort
		if(
			!/\bcompose=1\b/.test(location.search)
			|| !$j('.compose').length
		) return;

		AppGiniPlugin._composeTriggeredFromUrl = true;
		$j('.compose:visible').click();
	}

	var translateUI = (callback) => {
		// run this only once
		if($j('#ui-language-settings').length) return;

		// add lanuage selector menu to navbar, hidden intially
		$j(`
			<form class="navbar-form navbar-right hidden" id="ui-language-settings">
				<div class="form-group">
					<select class="form-control" id="selected-language"></select>
				</div>
			</form>
		`).appendTo('.navbar-collapse');

		// load available languages into #selected-language drop-down (only if 2 or more found)
		AppGiniPlugin.Translate.ready(function() {
			$j.ajax({
				url: '../plugins-resources/language/',
				success: function(langs) {
					$j('#ui-language-settings').toggleClass('hidden', langs.length < 2);
					// english only? abort.
					if(langs.length < 2) return;

					// populate drop-down
					AppGiniPlugin.populate_select({
						select: '#selected-language',
						options: langs.map(function(l) { return { id: l, text: l.toUpperCase() }; }),
						selected_id: AppGiniPlugin.selectedLanguage
					});

					// handle changing language
					$j('#selected-language').change(function() {
						AppGiniPlugin.Translate.setLang($j(this).val());
						location.reload();
					})
				}
			});

			callback();
		});

		AppGiniPlugin.Translate.live();
	}

	var canSend = () => $j('.new-msg-recipients').length > 0;

	var mainArea = (view) => {
		let views = ['no-messages', 'loading-messages', 'messages-list', 'message-viewer'];
		if(views.indexOf(view) < 0) return;

		views.map((viewClass) => {
			$j(`.main-area > .${viewClass}`).toggleClass('hidden', viewClass != view);
		});
	}

	var ajaxError = (xhr, status, httpMsg) => {
		if(httpMsg == 'abort') return;
		if(xhr.responseText == AppGini.Translate._map['csrf token expired or invalid']) {
			slidingAlert(`${xhr.responseText} <a href="${pluginUrl()}" class="btn btn-default"><i class="glyphicon glyphicon-refresh"></i></a>`);
			return;
		}
		slidingAlert(xhr.responseText);
	}

	var updateMassActions = () => {
		// hide mass actions if no messages selected
		const checked = $j('input.message-selector:checked');
		if(!checked.length) {
			$j('.mass-action').addClass('hidden');
			return;
		}

		// loop through selected messages and show appropriate mass actions
		var unread, read; // all undefined
		checked.each(function() {
			let msgs = getCachedThread($j(this).parents('tr').data('id'));
			if(!msgs) return;

			for(let msg of msgs) {
				if(!msg.draft && msg.markedUnread) read = true;
				if(!msg.draft && !msg.markedUnread) unread = true;
			}
		});

		$j('.mass-action.unread').toggleClass('hidden', !unread);
		$j('.mass-action.read').toggleClass('hidden', !read);
		$j('.mass-action.delete').removeClass('hidden');
	}

	var getCachedMessage = (id, entireThread = false) => {
		if(!id) return null;

		for(thread of threads) {
			let i = thread.map((m) => m.id).indexOf(id);
			if(i > -1)
				return entireThread ? thread : thread[i];
		}

		return null;
	}

	var getCachedThread = (id) => getCachedMessage(id, true);

	var canSendTo = (recipient) => {
		const users = recipientUsers.map(r => r.username);
		return users.indexOf(recipient) > -1;
	}

	var populateRecipientsList = () => {
		if(!canSend()) return;

		if(canSendGlobalMessage)
			$j('<option></option>')
				.val(`__ALL_USERS__`)
				.text(word('ALL_USERS'))
				.appendTo('.new-msg-recipients');

		if(canSendGroupMessage && Object.keys(recipientGroups).length > 0)
			for(let gid in recipientGroups)
				$j('<option></option>')
					.val(`group:${gid}`)
					.text(`${word('group_')} ${recipientGroups[gid]}`)
					.appendTo('.new-msg-recipients');

		// add users to select
		for(let r of recipientUsers)
			$j('<option></option>')
				.val(r.username).text(recipientUserFormat(r))
				.appendTo('.new-msg-recipients');
	}

	var slidingAlert = (msg, colorClass = 'danger', duration = 10, location = 'bottom') => {
		if(!msg) return;
		let randId = `sliding-alert-${Math.round(Math.random() * 1234567890)}`;

		// hide existing notifications before showing new one
		$j(`.sliding-alert-${location}`).addClass('hidden');

		$j(`<div class="alert alert-${colorClass} sliding-alert-${location} alert-dismissible" id="${randId}"></div>`)
			.css('animation-duration', `${duration * 1.01}s`) // 1% FoS to avoid flicker
			.html(
				msg +
				'<button type="button" class="close" data-dismiss="alert">&times;</button>'
			)
			.prependTo('body')

		setTimeout(() => { $j(`#${randId}`).remove(); }, 1000 * duration);
	}

	var pluginUrl = (uri = '') => location.href.replace(/(\/plugins\/messages)\/.*/, `$1/${uri}`);

	var getThreads = (silent = false, search = '') => {
		// abort prev request if in progress
		if(msgRequestInProgress) msgRequest.abort();

		// show loading ..
		if(!silent) slidingAlert(word('loading_messages'), 'warning', 1);

		msgRequest = $j.ajax({
			url: pluginUrl('app-resources/ajax-get-threads.php'),
			data: {
				updatesTS: lastUpdateTS ?? null,
				search: search,
				csrf_token: csrf_token
			},
			timeout: 10000,
			success: (resp) => {
				threads = resp.threads; // TODO: handling updates since lastUpdateTS
			},
			// error: !silent ? ajaxError : () => {},
			error: ajaxError,
			complete: () => { msgRequestInProgress = false; }
		});

		return msgRequest;
	}

	var updateMessagesList = () => {
		// if a message is displayed, abort
		if($j('.main-area > .message-viewer:not(.hidden)').length) return;

		// current folder, or search if no active folder
		let folder = activeFolder() || 'search';

		// show loading, empty or messages list
		if(!threads.length && (msgRequest === null || msgRequest.readyState < 4))
			return mainArea('loading-messages');

		if(!folderThreads(folder).length)
			return mainArea('no-messages');

		mainArea('messages-list');

		// populate .messages-list
		listMessages(folder);
	}

	var countUnread = (folder) => {
		return folderThreads(folder).reduce((unread, thread) => (threadUnread(thread) ? unread + 1 : unread), 0);
	}

	var updateFolderCues = () => {
		for(let folder of folders) {
			// special case for drafts: consider all messages unread
			let unread = (folder == 'drafts' ? folderThreads(folder).length : countUnread(folder));

			$j(`.folders > li > a[data-folder="${folder}"]`)
				.toggleClass('text-bold', unread > 0)
				.children('.badge').text(unread || '')
		}
	}

	var timestampToLocaleDateTime = (ts) => {
		if(!ts) return '';

		let dt = new Date(ts * 1000);
		return `${dt.toLocaleDateString()} ${dt.toLocaleTimeString()}`;
	};

	var timestampToDateOrTime = (ts) => {
		if(!ts) return '';

		let dt = new Date(ts * 1000), now = new Date;

		// today? show time only
		if(dt.toDateString() == now.toDateString())
			return moment(dt).format(AppGini.datetimeFormat('t').replace(':ss', ''));

		// this year? show date only, without year
		if(dt.getFullYear() == now.getFullYear())
			return moment(dt).format(AppGini.datetimeFormat('d').replace(/\W?YYYY\W?/, ''));

		// show date only
		return moment(dt).format(AppGini.datetimeFormat('d'));
	}

	var folderThreads = (folder) => threads.filter((thread) => {
		switch(folder) {
			case 'inbox':
				return threadInbox(thread);
			case 'starred':
				return threadStarred(thread);
			case 'sent':
				return threadSent(thread);
			case 'drafts':
				return threadDrafts(thread);
		}
	});

	var threadUnread = (thread) => thread.reduce((lastChk, msg) => (lastChk || msg.markedUnread), false);
	var threadStarred = (thread) => thread.reduce((lastChk, msg) => (lastChk || msg.starred), false);
	var threadSent = (thread) => thread.reduce((lastChk, msg) => (lastChk || (msg.sender == me && !msg.draft && !msg.originalId)), false);
	var threadDrafts = (thread) => thread.reduce((lastChk, msg) => (lastChk || msg.draft), false);
	var threadInbox = (thread) => thread.reduce((lastChk, msg) => (lastChk || (msg.recipients == me && !msg.draft)), false);

	var listMessages = (folder) => {
		let msgListBody = messagesList.children('tbody'),
			newMsgListBody = msgListBody.clone().empty();
		//msgListBody.empty();

		let ft = folderThreads(folder);
		$j('.total-message-count').text(ft.length);

		for(thread of ft) {
			// id stored in row is that of the 1st (newest) message in thread
			let tr = $j(`<tr data-id="${thread[0].id}"></tr>`),
				unread = threadUnread(thread);
			
			tr.toggleClass('message-unread', unread);
			
			// to get message id (of 1st msg in thread) when an event is triggered on any of these elements:
			// $(this).parents('tr').data('id')
			$j('<td class="message-selector"><input type="checkbox" class="message-selector"></td>')
				.appendTo(tr);

			let isStarred = threadStarred(thread);
			$j(`<td class="message-starred ${isStarred ? 'unstar' : 'star'}">
				<i class="glyphicon glyphicon-star${isStarred ? ' text-warning' : '-empty'}"></i>
			</td>`).appendTo(tr);

			let fromTo = olderFirst(thread).reduce((prevList, msg) => {
				if(msg.draft)
					prevList.push('<span class="text-danger">DRAFT</span>');
				else if(/*folder == 'drafts' || */folder == 'sent')
					prevList.push(displayRecipients(msg.recipients));
				else
					prevList.push(displayRecipients(msg.sender));
				return prevList;
			}, []).uniq().join(', ');

			$j(`<td class="message-from ${unread ? 'text-bold' : ''}"></td>`)
				.html(`${fromTo} <span class="text-muted">${thread.length > 1 ? thread.length : ''}</span>`)
				.appendTo(tr);
			
			let subject = $j(`<span class="message-subject ${unread ? 'text-bold' : ''}"></span>`);
			subject.text(thread[0].subject.replace(/^(re: |fwd: )/i, ''));

			let preview = $j(`<span class="message-preview text-muted"></span>`);
			let msgPreview = htmlToText(renderMd(thread[0].message));
			preview.text(msgPreview.substr(0, 150));

			let tdMsg = $j('<td class="message-text"></td>');
			subject.appendTo(tdMsg);
			preview.appendTo(tdMsg);
			tdMsg.appendTo(tr);
			
			// format as time if today or date otherwise
			$j('<td class="message-datetime"></td>')
				.attr('title', timestampToLocaleDateTime(thread[0].sentTS || thread[0].createdTS))
				.text(timestampToDateOrTime(thread[0].sentTS || thread[0].createdTS))
				.appendTo(tr);

			tr.appendTo(newMsgListBody);
		}

		if(newMsgListBody.html() == msgListBody.html()) return; // nothing to change

		msgListBody.html(newMsgListBody.html());
		$j('.all-messages-selector').prop('checked', false);
	}

	var saveMessage = (send) => {
		var form = $j('.new-message-form:visible'),
			recipients = form.find('.new-msg-recipients').select2('val') || '',
			subject = form.find('.new-msg-subject').val() || '',
			message = form.find('.new-msg-text').val() || '',
			id = form.data('id'); // undefined if not saved before

		if(typeof(recipients) == 'object')
			recipients = recipients.map($j.trim).filter(r => r.length).join(',');

		// nothing to save?
		if(!id && !recipients.trim().length && !subject.trim().length && !message.trim().length)
			return console.info('Empty message. Nothing to save!');

		// default subject if empty
		if(!subject.trim().length) subject = word('no_subject');

		// if message not changed, abort
		const storedMsg = getCachedMessage(id);
		if(
			storedMsg
			&& storedMsg.recipients == recipients
			&& storedMsg.subject == subject
			&& storedMsg.message == message
			&& !send
		) return console.info('No changes to save!');

		return $j.ajax({
			url: pluginUrl('app-resources/ajax-save-message.php'),
			type: 'POST',
			data: {
				id,	recipients,	subject, message, send, // send is undefined or true
				inReplyTo: form.find('.in-reply-to-id').val(),
				folder: 'drafts',
				csrf_token: csrf_token,
			},
			timeout: 10000,
			success: (resp) => {
				if(send)
					slidingAlert(word('message_sent'), 'success', 2);
				else
					slidingAlert(word('message_saved_in_drafts'), 'info', 2);

				threads = resp.threads;
				// update thread if visible
				if($j('.single-message').length)
					viewThread($j('.single-message').data('id'));
			},
			error: ajaxError
		});
	}

	var discardDraft = () => {
		var draftId = $j('.new-message-form:visible').data('id');
		if(!draftId) return; // nothing to discard

		return $j.ajax({
			url: pluginUrl('app-resources/ajax-delete-messages.php'),
			data: { ids: draftId, folder: 'drafts', csrf_token: csrf_token },
			success: (resp) => {
				slidingAlert(word('draft_discarded'), 'info', 2);

				threads = resp.threads;

				// if the discarded draft is displayed
				const displayedThreadIds = $j('.single-message').map((i, e) => $j(e).data('id')).toArray();
				if(displayedThreadIds.indexOf(draftId) > -1)
					// update displayed thread if there are other messages
					if(displayedThreadIds.length > 1)
						viewThread(displayedThreadIds.filter((id) => id != draftId)[0]);
					// show messages list if no other messages in thread
					else
						mainArea('messages-list');
			},
			error: ajaxError
		})
	}

	var activeFolder = () => $j('.folders > li.active > a').data('folder');

	var deleteMessages = (ids) => {
		let plural = ids.toString().split(',').length > 1 ? 's' : '';
		
		return $j.ajax({
			url: pluginUrl('app-resources/ajax-delete-messages.php'),
			data: { ids: ids, csrf_token: csrf_token },
			success: (resp) => {
				slidingAlert(word(`message${plural}_deleted`), 'info', 2);
				threads = resp.threads;
			},
			error: ajaxError
		});
	}

	var launchNewMessage = (id, inReplyToId, fwdOfId) => {
		if(!canSend()) return; // can't send

		let prevMsg = getCachedMessage(inReplyToId) ?? null,
			msg = getCachedMessage(id) ?? null,
			fwdMsg = getCachedMessage(fwdOfId) ?? null,
			discarded = false,
			sent = false;

		let title = word('new_message');
		if(msg)
			title = msg.subject;
		else if(prevMsg)
			// remove existing 're: ' and 'fwd: ' before prepending 're: '
			title = `Re: ${prevMsg.subject.replace(/^(re: |fwd: )/i, '')}`;
		else if(fwdMsg)
			// remove existing 're: ' and 'fwd: ' before prepending 'fwd: '
			title = `Fwd: ${fwdMsg.subject.replace(/^(re: |fwd: )/i, '')}`;

		let formId = modal_window({
		    title: title,
		    size: 'full',
		    noAnimation: true,
		    message: $j('.new-message-modal').html(),
		    footer: [{
				label: '<i class="glyphicon glyphicon-send"></i> <span class="language" data-key="send">Send</span>',
				bs_class: 'primary',
				click: () => {
					sent = true;
					saveMessage(true); // save final message and send
				}
			}, {
				label: '<i class="glyphicon glyphicon-trash"></i> <span class="language" data-key="discard">Discard</span>',
				bs_class: 'danger',
				click: () => {
					discarded = true;
					discardDraft();
				}
	    	}]
		});
		
		// save as draft if not sent nor discarded
		$j(`#${formId}`)
			.on('hide.bs.modal', () => {
				if(!discarded && !sent) saveMessage();
			})

		// actions after showing modal
		AppGini.once({
		 	condition: () => $j('.new-msg-subject:visible').length,
		 	action: () => {
				let recipSelect = $j(`#${formId} .new-msg-recipients`);
				if(maxRecipients > 1) recipSelect.prop('multiple', true);

				// populate message if it's an existing draft
				if(msg) {
					$j('.new-message-form:visible')
						.data('id', id)
						.find('.in-reply-to-id').val(msg.inReplyTo);
					$j('.new-msg-subject:visible').val(msg.subject);
					if(maxRecipients > 1)
						recipSelect.val(msg.recipients.split(',').map($j.trim));
					else
						recipSelect.val(msg.recipients);
					$j('.new-msg-text:visible').val(msg.message);

				// populate message if it's a reply to a previous one
				} else if(prevMsg) {
					$j('.new-message-form:visible')
						.find('.in-reply-to-id').val(prevMsg.id);
					$j('.new-msg-subject:visible').val(title);
					// if message being replied to was sent by the current user, set recipients
					// of this reply to the same recipients as the message being replied to
					if(maxRecipients > 1)
						recipSelect.val(
							prevMsg.sender == me ? prevMsg.recipients.split(',').map($j.trim) : prevMsg.sender
						);
					else
						recipSelect.val(prevMsg.sender == me ? prevMsg.recipients : prevMsg.sender);
					$j('.new-msg-text:visible').val('');
					// TODO: show message to which we're replying?

				// populate message if it's a forward
				} else if(fwdMsg) {
					// should forwarded message have inReplyTo set??
					//$j('.new-message-form:visible')
					//	.find('.in-reply-to-id').val(fwdMsg.id);
					$j('.new-msg-subject:visible').val(title);
					$j('.new-msg-text:visible').val([
						'\n',
						'*Forwarded message below*\n',
						'---',
						`**From**: ${fwdMsg.sender}\n`,
						`**To:** ${fwdMsg.recipients}\n`,
						`**Subject:** ${fwdMsg.subject}\n\n`,
						// split message into lines and add '> ' to each line
						fwdMsg.message.split('\n').map((line) => `> ${line}`).join('\n')
					].join('\n'));
				}

				// populate message if triggered from url
				else if(AppGiniPlugin._composeTriggeredFromUrl) {
					// prevent retriggering
					AppGiniPlugin._composeTriggeredFromUrl = false;

					const url = new URL(location.href),
					      urlSubject = url.searchParams.get('subject'),
					      urlMessage = url.searchParams.get('message'),
					      urlTo = url.searchParams.get('to');

					// populate subject
					if(urlSubject)	$j('.new-msg-subject:visible').val(urlSubject);

					// populate message
					if(urlMessage) $j('.new-msg-text:visible').val(urlMessage);

					// populate recipients
					if(urlTo)
						if(maxRecipients > 1)
							recipSelect.val(urlTo.split(',').map($j.trim));
						else
							recipSelect.val(urlTo);
				}

				// recipients select2, setting a limit on multiple selection if needed
				if(!canSendGroupMessage && !canSendGlobalMessage)
					recipSelect.select2({
						maximumSelectionSize: maxRecipients,
						formatSelectionTooBig: (max) => `<span class="text-danger text-bold">${word('max_recipients_limit', { max })}</span>`
					});
				else
					recipSelect.select2();

				if(maxRecipients > 1) {
					// remove empty first recipient
					let rval = recipSelect.select2('val');
					if(rval[0] !== undefined && rval[0] == '')
						recipSelect.select2('val', rval.slice(1));
				}

				// new message => focus subject
				let focusEl;
				if(!msg && !prevMsg && !fwdMsg)
					focusEl = $j('.new-msg-subject:visible');

				// else focus the beginning of the message text
				else
					focusEl = $j('.new-msg-text:visible');

				setTimeout(() => {
					focusEl.focus().get(0).setSelectionRange(0, 0);
				}, 400); // set the focus after modal effects are done to avoid losing it to modal backdrop
		 	}
		})
	}

	var updateMessages = (update, silentUpdate = true) => {
		if(!update || !update.ids) return null;

		update.csrf_token = csrf_token;

		// show loading ..
		if(silentUpdate === false) slidingAlert(word('uddating_messages'), 'warning', 1);

		return $j.ajax({
			url: pluginUrl('app-resources/ajax-update-messages.php'),
			data: update,
			success: (resp) => {
				if(resp.threads)
					threads = resp.threads;
			},
			error: ajaxError
		})
	}

	var markAsRead = (ids, silentUpdate) => updateMessages({ ids: ids, markedUnread: 0 }, silentUpdate);
	var markAsUnread = (ids, silentUpdate) => updateMessages({ ids: ids, markedUnread: 1 }, silentUpdate);
	var unstarMessage = (ids, silentUpdate) => updateMessages({ ids: ids, starred: 0 }, silentUpdate);
	var starMessage = (ids, silentUpdate) => updateMessages({ ids: ids, starred: 1 }, silentUpdate);

	var olderFirst = (thread) => thread.clone().sort((a, b) => {
		return ((a.sentTS || a.createdTS) < (b.sentTS || b.createdTS) ? -1 : 1);
	});
	var newerFirst = (thread) => thread.clone().sort((a, b) => {
		return ((a.sentTS || a.createdTS) > (b.sentTS || b.createdTS) ? -1 : 1);
	});

	var displayRecipients = (rawRecip) => {
		if(rawRecip == me) return word('me');
		if(rawRecip == '__ALL_USERS__') return word('ALL_USERS');
		if(/group:\d+/.test(rawRecip))
			return recipientGroups[rawRecip.match(/group:(\d+)/)[1]] +
					' <span class="glyphicon glyphicon-th text-muted" title="Group"></span>';
		
		// strip potential html
		return $j(`<div></div>`).text(rawRecip).text();
	}

	var renderMd = (md) => DOMPurify.sanitize(marked.parse(md));

	var htmlToText = (html) => {
		let tmp = document.createElement('div');
		tmp.innerHTML = html;
		let text = tmp.textContent || tmp.innerText || '';
		return text;
	}

	var viewThread = (id) => {
		let msg, thread, view = $j('.message-viewer');
		if(!id || !(thread = getCachedThread(id))) return;

		thread = olderFirst(thread);

		view.find('.single-message').remove();

		mainArea('message-viewer');
		view.find('.message-subject').text(thread[0].subject);

		for(msg of thread) {
			let single = view.find('.single-message-template').clone();

			single
				.removeClass('hidden single-message-template')
				.addClass('single-message')
				.data('id', msg.id);

			single.find('.message-sender').text(msg.sender);
			single.find('.message-datetime').text(timestampToDateOrTime(
				msg.recipients == me ? msg.createdTS : msg.sentTS
			)).attr('title', timestampToLocaleDateTime(msg.recipients == me ? msg.createdTS : msg.sentTS));
			single.find('.message-recipients').html(displayRecipients(msg.recipients));
			single.find('.message-text').html(renderMd(msg.message));

			// show seen TS if all of the below conditions are met
			if(
				msg.sender == me
				&& msg.recipients != me
				&& !/\bgroup:/.test(msg.recipients)
				&& msg.recipients.indexOf('__ALL_USERS__') == -1
				&& msg.recipients.indexOf(',') == -1
			) single.find('.message-seen')
				.text(
					(msg.seenTS ? `${word('seen_at')} ${timestampToDateOrTime(msg.seenTS)}` : word('not_seen_yet_by_recipient'))
				)
				.attr('title', msg.seenTS ? timestampToLocaleDateTime(msg.seenTS) : '');

			single.find('.star').toggleClass('hidden', msg.starred)
			single.find('.unstar').toggleClass('hidden', !msg.starred)

			// if draft, edit on click
			if(msg.draft) {
				let draftId = msg.id;
				single
					.addClass('bg-warning')				
					.css({ cursor: 'pointer' })
					.on('click', () => { launchNewMessage(draftId) })
					.attr('title', word('draft_message_click_to_edit'));
				single.find('.message-seen').empty();
				single.find('.message-header .text-muted').css('margin-top', 'initial');
				single.find('.message-datetime')
					.html('<span class="text-danger text-bold hspacer-lg">DRAFT</span>')
					.attr('title', '');
				single.find('.reply, .forward, .star, .unstar, .read, .unread, .delete').remove();
			}

			// if current user can't reply to sender, disable reply with note
			if(!canSendTo(msg.sender))
				single.find('.reply')
					.addClass('text-muted')
					.removeClass('reply')
					.attr('title', word('no_reply_permission'))

			single.insertBefore('.messages-actions');

			if(!msg.seenTS || msg.markedUnread) markAsRead(msg.id);
		}
	}

	var updateSingleMessage = (icon, func) => {
		// find the DOM element for message
		let msgDom = icon.parents('.single-message');
		if(!msgDom.length) return;
		
		let id = msgDom.data('id');

		func(id)
			.done(() => {
				if(!msgDom.length) return; // msg thread no longer visible

				// if message still visible, refresh thread, unless the func is markAsUnread
				if(func != markAsUnread) viewThread(id);
			});
	}

	var threadIds = (id) => getCachedThread(id).map((msg) => msg.id).join(',');

	var selectedMessageIds = () => {
		// get ids of 1st messages in each selected thread
		let ids = $j('.message-selector:checked').map((i, e) =>  $j(e).parents('tr').data('id')).toArray();

		// retrieve ids of all messages in each selected thread
		return ids.map(threadIds).join(',').split(',');
	}

	// page events
		// clicking a folder > prevent default, make active
		$j('body')
			.on('click focus', '.folders a', function(e) {
				e.preventDefault();

				/* a click event also triggers a focus ... so, no need to continue to avoid a double request */
				if(e.type == 'click') return;

				// set all folder links as inactive except clicked folder
				// and its alternative mobile-hidden folder
				const clickedFolder = $j(this).data('folder');
				$j('.folders li').each((i, el) => {
					$j(el).toggleClass('active', $j(el).children('a').data('folder') == clickedFolder)
				})

				// get messages from server
				getThreads(true);

				// show loader very briefly
				mainArea('loading-messages');

				// meanwhile, show cached message list until server request done
				updateMessagesList();
			})
			// fetch inbox messages if count of unread messages changes
			.on('messages-unread-count-change', () => {
				AppGiniPlugin.Translate.ready(() => {
					getThreads();
				});
			})
			.on('click', '.compose', launchNewMessage)
			.on('click', '.mass-action.unread', () => {
				markAsUnread(selectedMessageIds().join(','), false);
			})
			.on('click', '.mass-action.read', () => {
				markAsRead(selectedMessageIds().join(','), false);
			})
			.on('click', '.mass-action.delete', () => {
				if(!confirm(word('confirm_delete_selected_messages'))) return;
				deleteMessages(selectedMessageIds().join(','), false);
			})
			.on('click', '.all-messages-selector', function(e) {
				e.stopPropagation();
				$j('.message-selector').prop('checked', $j(this).prop('checked'));
			})
			.on('click', '.message-selector, .message-starred', (e) => { e.stopPropagation(); })
			.on('click', '.messages-list .message-starred', function(e) {
				let cell = $j(this), id = cell.parents('tr').data('id');
				if(cell.hasClass('star'))
					starMessage(threadIds(id), false);
				else if(cell.hasClass('unstar'))
					unstarMessage(threadIds(id), false);
			})
			.on('click', '.messages-list > tbody > tr', function(e) {
				// open message viewer
				viewThread($j(this).data('id'));
			})
			.on('click', '.message-viewer .close-message', () => {
				$j('.single-message').remove();
				mainArea('messages-list');
			})
			.on('click', '.messages-actions .reply', function() {
				const lastMsgId = $j(this).parent('.messages-actions').prev('.single-message').data('id'),
					msg = getCachedMessage(lastMsgId);

				// if can't reply to sender, show error and abort
				if(!canSendTo(msg.sender))
					return slidingAlert(word('no_reply_permission'));

				// reply to last message in thread
				launchNewMessage(null, lastMsgId);
			})
			.on('click', '.single-message .reply', function() {
				launchNewMessage(null, $j(this).parents('.single-message').data('id'));
			})
			.on('click', '.messages-actions .forward', function() {
				// reply to last message in thread
				launchNewMessage(null, null, $j(this).parent('.messages-actions').prev('.single-message').data('id'));
			})
			.on('click', '.single-message .forward', function() {
				launchNewMessage(null, null, $j(this).parents('.single-message').data('id'));
			})
			.on('click', '.single-message .delete', function(e) {
				e.stopPropagation();
				if(!confirm(word('confirm_delete_message'))) return;

				let msgDom = $j(this).parents('.single-message');
				deleteMessages(msgDom.data('id'))
					.done(() => {
						// remove deleted message
						msgDom.remove();

						// if no other messages in thread, close message viewer
						if(!$j('.single-message').length)
							mainArea('messages-list');
					});
			})
			.on('click', '.single-message .star', function(e) {
				updateSingleMessage($j(this), starMessage);
			})
			.on('click', '.single-message .unstar', function(e) {
				updateSingleMessage($j(this), unstarMessage);
			})
			.on('click', '.single-message .read', function(e) {
				updateSingleMessage($j(this), markAsRead);
			})
			.on('click', '.single-message .unread', function(e) {
				updateSingleMessage($j(this), markAsUnread);
				// wait 2ms then show messages list to keep msg unread
				setTimeout(() => { $j('.message-viewer .close-message').click(); }, 2); 
			})
			.on('click', '.modal-dialog .toggle-preview', function() {
				// if active, hide preview
				const isActive = !$j(this).hasClass('active');
				const editBox = $j('.modal-dialog:visible .new-msg-text');
				const preview = $j('.modal-dialog:visible .new-msg-preview');

				$j(this).toggleClass('active', isActive);
				editBox.toggleClass('hidden', isActive);
				preview.toggleClass('hidden', !isActive);

				if(isActive) {
					preview.html(renderMd(editBox.val()));
				} else {
					editBox.focus();
				}
			})


	init();
})