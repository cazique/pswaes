<?php
	// this file is only callable from within header-extras.php
	if(!in_array('header-extras.php', array_map('basename', get_included_files())))
		die('Must be called from within header-extras.php');

	if(MessagesDB::hasAccess()) {

		?>
		<script>
			$j(() => {
				<?php
					$target = '';
					if(method_exists('MessagesDB', 'setting')) {
						if(MessagesDB::setting('open-inbox-in-new-page')) {
							$target = (strpos($_SERVER['REQUEST_URI'], 'plugins/messages/') === false ? '' : 'target="_blank"');
						}
					}
				?>
				
				const target = '<?php echo $target; ?>';

				// show icon
					const messagesTitle = "<?php echo html_attr($Translation['Messages']); ?>";
					const messagesUrl = '<?php echo application_url('plugins/messages/'); ?>';
					const emailIconUrl = '<?php echo application_url('plugins/plugins-resources/table_icons/email.png'); ?>';
					const icon = $j(`
						<a ${target}
							class="btn btn-default navbar-btn plugin-messages-icon"
							href="${messagesUrl}">
							<img style="height: 1.7em;" src="${emailIconUrl}">
						</a>
					`);
					const messagesCounter = $j(
						'<div class="label label-danger hidden plugin-messages-counter">0</div>'
					);

					messagesCounter.clone().appendTo(icon);

					// mobile
					icon
						.clone()
						.addClass('btn-lg visible-xs pull-right hspacer-md')
						.insertAfter('nav .navbar-toggle');

					// non-mobile, in case of AppGini 22.12 or less
					if(!$j('.profile-menu').length)
						icon
							.addClass('hidden-xs')
							.insertBefore('nav a[href$="index.php?signOut=1"]:not(.visible-xs)');

					// non-mobile, AppGini 22.13+ -- append to profile menu
					else {
						$j(
							`<li class="messages-menu-item" title="${messagesTitle}">
								<a ${target} href="${messagesUrl}">
									<img src="${emailIconUrl}">
									${messagesTitle}
								</a>
							</li>`
						).insertBefore('.profile-menu li.sign-out-menu-item');

						messagesCounter.clone().appendTo('.messages-menu-item > a');

						// TODO: make this optional via setting
						messagesCounter.clone().appendTo('.profile-menu-icon');
					}

				// poll unread messages periodically
					let data = {};
					
					// send csrf_token if defined
					try {
						data = { csrf_token: csrf_token.value }; 
					} catch(e) {
						try {
							data = { csrf_token: csrf_token.val() }
						} catch(e) {
							try {
								data = { csrf_token }
							} catch(e) { }
						}
					}
					
					const poll = () => {
						$j.ajax({
							url: '<?php echo MessagesDB::unreadUri(); ?>',
							data,
							timeout: 3000,
							success: (resp) => {
								let counter = $j('.plugin-messages-counter'),
									past = counter.eq(0).text();

								counter
									.toggleClass('hidden', !resp.length || resp == '0')
									.text(resp);

								// new messages? trigger new messages event
								if(!counter.hasClass('hidden') && parseInt(resp) > parseInt(past))
									$j('body').trigger('messages-new-unread');

								// trigger notifictions count changed if so
								if(parseInt(resp) != parseInt(past))
									$j('body').trigger('messages-unread-count-change');
							}
						});
					};
					poll();
					setInterval(poll, 10000);

					// play a notification sound on new messages
					// but not if this is the inbox page just opened
					let playNext = false;
					$j('body').on('messages-new-unread', () => {
						if($j('.messages-list').length && !playNext) {
							playNext = true;
							return; // skip first notification if inbox is open
						}

						try{
							new Audio('<?php echo application_url('plugins/messages/app-resources/notification01.mp3'); ?>').play();
						} catch {
							if(!localStorage.getItem('disabledAudioAlertShown')) {
								// TODO: apply language translation to the message below
								// when AppGini and plugins have a common way of loading lang files
								modal_window({
									title: 'Audio playback disabled',
									message: 'If you\'d like to hear an audio notification when you have new messages, you should allow audio playback for this site in your browser settings.',
									footer: [
										{ label: 'OK, got it!', bs_class: 'primary' },
									]
								});
								localStorage.setItem('disabledAudioAlertShown', 1);
							}
						}
					})
			})
		</script>

		<style>
			.plugin-messages-icon.hidden-xs {
				padding: 4px 6px 1px;
				margin-right: 0 !important;
				margin-left: 0 !important;
			}
			.plugin-messages-icon.visible-xs {
				padding-bottom: 3px;
			}
			.plugin-messages-icon.hidden-xs > .glyphicon {
				font-size: 16px;
			}
			.plugin-messages-icon.visible-xs > .glyphicon {
				font-size: 18px;
			}

			.plugin-messages-counter {
				border-radius: 50%;
				z-index: 1;
				position: absolute !important;
				opacity: .8;
				margin-left: -7px;
				padding: 2px 5px 3px;
				font-size: 10px;
			}

			.visible-xs.plugin-messages-icon > .plugin-messages-counter {
				top: 12px !important;
			}
			.hidden-xs.plugin-messages-icon > .plugin-messages-counter {
				top: 6px !important;
			}
			.messages-menu-item .plugin-messages-counter {
				position: initial !important;
				margin: 0 .33em !important;
			}
			.profile-menu-icon > .plugin-messages-counter {
				top: 2px !important;
				right: 2px !important;
			}
		</style>
		<?php
	}