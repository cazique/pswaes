<?php
	define('PREPEND_PATH', '../../');
	include(__DIR__ . '/../../lib.php');
	
	// if messages not enabled, redirect to settings
	if(
		!class_exists('MessagesDB') ||
		(!MessagesDB::enabled() && getLoggedAdmin())
	) {
		header('Location: settings.php');
		exit();
	}

	include_once(__DIR__ . '/../../header.php');

	/* check if current group can access messages */
	if(!MessagesDB::hasAccess()) {
		echo error_message('Access denied');
		include_once(__DIR__ . '/../footer.php');
		exit;
	}
?>

<script src="<?php echo PREPEND_PATH; ?>plugins/plugins-resources/plugins-common.js"></script>

<?php if(MessagesDB::setting('show-table-menus')) { ?>
	<script>
		$j(() => {
			// show table menus in navbar
			$j('nav > .navbar-collapse').prepend(`<ul class="nav navbar-nav"><?php echo NavMenus(); ?></ul>`);
		})
	</script>
<?php } ?>

<?php if(is_file(__DIR__ . '/../../.git/HEAD')) { ?>
	<script src="<?php echo PREPEND_PATH; ?>plugins/messages/tests.js"></script>
<?php } ?>

<?php
	if(!MessagesDB::dbInstalled()) {
		if(getLoggedAdmin()) {
			echo error_message('Messages database not installed. <a href="install.php">Click here to install it</a>.');
		} else {
			echo error_message('Messages database not installed. Please contact your administrator.');
		}

		include_once(__DIR__ . '/../footer.php');
		exit;
	}
?>

<script>
	var csrf_token = <?php echo json_encode(csrf_token(false, true)); ?>;

	const recipientUsers = <?php echo json_encode(MessagesDB::listAllowedRecipients('users')); ?>;
	const recipientUserFormat = r => `<?php echo MessagesDB::recipientUserFormat(); ?>`;
	const recipientGroups = <?php echo json_encode(MessagesDB::listAllowedRecipients('groups')); ?>;
	const canSendGroupMessage = <?php echo MessagesDB::groupPermissions()['canSendGroupMessage'] ? 'true' : 'false'; ?>;
	const canSendGlobalMessage = <?php echo MessagesDB::groupPermissions()['canSendGlobalMessage'] ? 'true' : 'false'; ?>;
	const maxRecipients = <?php echo max(1, intval(MessagesDB::groupPermissions()['maxRecipients'])); ?>;
</script>

<?php if(MessagesDB::canSend()) { ?>
	<div class="new-message-modal hidden">
		<div class="form-horizontal new-message-form">
			<input type="hidden" class="in-reply-to-id" value="">
			<div class="form-group bspacer-lg">
				<label for="new-msg-recipients" class="col-sm-2 control-label language" data-key="to">To</label>
				<div class="col-sm-10">
					<select class="new-msg-recipients" style="width: 100%;">
						<option></option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label for="new-msg-subject" class="col-sm-2 control-label language" data-key="subject">Subject</label>
				<div class="col-sm-10">
					<input type="text" class="form-control new-msg-subject" maxlength="100">
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<div class="text-right">
						<a href="https://www.markdownguide.org/cheat-sheet/" class="language text-muted text-italic hspacer-lg" data-key="markdown_supported" target="_blank">Markdown is supported</a>
						<!-- toggler button for preview -->
						<button type="button" class="btn btn-default toggle-preview bspacer-lg">
							<i class="glyphicon glyphicon-eye-open"></i>
							<span class="language" data-key="preview">Preview</span>
						</button>
					</div>
					<textarea class="form-control new-msg-text" style="height: 45vh;"></textarea>
					<div class="new-msg-preview hidden"></div>
				</div>
			</div>
		</div>
	</div>
<?php } ?>

<div class="row main-page">
	<div class="col-xs-12 visible-xs topbar">
		<?php
			// calculate number of nav buttons to show
			$topBarNumButtons = 4; // initial 4 folders 
			if(MessagesDB::canSend()) $topBarNumButtons++; // compose button
			if(getLoggedAdmin() !== false) $topBarNumButtons++; // settings button
		?>

		<?php if(MessagesDB::canSend()) { ?>
			<button type="button" class="btn btn-default compose">
				<span class="text-danger text-bold">
					<i class="glyphicon glyphicon-pencil"></i>
					<span class="language" data-key="compose">Compose</span>
				</span>
			</button>
		<?php } ?>

		<ul class="nav nav-pills folders">
			<li><a data-folder="inbox" href="#" class="">
				<i class="glyphicon glyphicon-inbox"></i>
				<span class="language" data-key="inbox">Inbox</span>
				<span class="badge"></span>
			</a></li>
			<li><a data-folder="starred" href="#" class="">
				<i class="glyphicon glyphicon-star"></i>
				<span class="language" data-key="starred">Starred</span>
				<span class="badge"></span>
			</a></li>
			<li><a data-folder="sent" href="#" class="">
				<i class="glyphicon glyphicon-send"></i>
				<span class="language" data-key="sent">Sent</span>
				<span class="badge"></span>
			</a></li>
			<li><a data-folder="drafts" href="#" class="">
				<i class="glyphicon glyphicon-file rspacer-lg"></i>
				<span class="language" data-key="drafts">Drafts</span>
				<span class="badge"></span>
			</a></li>
		</ul>

		<?php if(getLoggedAdmin() !== false) { ?>
			<a href="settings.php" class="btn btn-default settings">
				<span class="text-danger">
					<i class="glyphicon glyphicon-cog"></i>
					<span class="language" data-key="settings">Settings</span>
				</span>
			</a>
		<?php } ?>
	</div>
	<div class="col-sm-4 col-md-3 col-lg-2 left-sidebar hidden-xs" style="min-width: 15em;">
		<?php if(MessagesDB::canSend()) { ?>
			<button type="button" class="btn btn-default btn-lg compose vspacer-lg" style="padding: 1em 2em;">
				<span class="text-danger text-bold">
					<i class="glyphicon glyphicon-pencil"></i> <span class="language" data-key="compose">Compose</span>
				</span>
			</button>
		<?php } else { ?>
			<div style="height: 6em"></div>
		<?php }?>

		<ul class="nav nav-pills nav-stacked folders">
			<li><a data-folder="inbox" href="#">
				<i class="glyphicon glyphicon-inbox rspacer-lg"></i> <span class="language" data-key="inbox">Inbox</span> <span class="badge pull-right"></span>
			</a></li>
			<li><a data-folder="starred" href="#">
				<i class="glyphicon glyphicon-star rspacer-lg"></i> <span class="language" data-key="starred">Starred</span> <span class="badge pull-right"></span>
			</a></li>
			<li><a data-folder="sent" href="#">
				<i class="glyphicon glyphicon-send rspacer-lg"></i> <span class="language" data-key="sent">Sent</span> <span class="badge pull-right"></span>
			</a></li>
			<li><a data-folder="drafts" href="#">
				<i class="glyphicon glyphicon-file rspacer-lg"></i> <span class="language" data-key="drafts">Drafts</span> <span class="badge pull-right"></span>
			</a></li>
		</ul>

		<?php if(getLoggedAdmin() !== false) { /* show settings button */ ?>
			<hr>
			<a href="settings.php" class="btn btn-default btn-block btn-lg settings vspacer-lg">
				<span class="text-danger">
					<i class="glyphicon glyphicon-cog"></i> <span class="language" data-key="settings">Settings</span>
				</span>
			</a>
		<?php } ?>
	</div>
	<div class="col-xs-12 col-sm-8 col-md-9 col-lg-10 main-area" style="min-width: 15em;">
		<div class="page-header"><h1>
			<img src="<?php echo PREPEND_PATH; ?>plugins/plugins-resources/table_icons/email.png" style="height: 1.1em; vertical-align: text-bottom;"> <span class="language" data-key="messages">Messages</span>
			<div class="input-group pull-right hidden">
				<span class="input-group-addon">
					<i class="glyphicon glyphicon-search"></i> 
				</span>
				<input id="message-search" type="text" class="form-control" placeholder="Search messages">
			</div>
			<script>AppGiniPlugin.Translate.ready(
				() => $j('#message-search').attr('placeholder', AppGiniPlugin.Translate.word('search_messages'))
			)</script>
			<div class="clearfix"></div>
		</h1></div>

		<?php if(!MessagesDB::canSend()) { ?>
			<div class="alert alert-warning">
				<i class="glyphicon glyphicon-info-sign"></i>
				<span class="language" data-key="messages_view_only">
					You can only view messages sent to you, but you don't have permission to send or reply to messages.
				</span>
			</div>
		<?php } ?>


		<div class="well no-messages text-muted text-bold text-center hidden language" data-key="no_conversations">No conversations here!</div>
		
		<div class="well loading-messages text-muted text-bold text-center">
			<i class="glyphicon glyphicon-refresh loop-rotate"></i>
			<span class="language" data-key="loading_messages">Loading messages ...</span>
		</div>

		<table class="table table-hover messages-list hidden">
			<thead>
				<tr>
					<th class="text-center"><input type="checkbox" class="all-messages-selector"></th>
					<th colspan="3">
						<i class="hidden glyphicon glyphicon-envelope icon-action mass-action unread"></i> 
						<i class="hidden glyphicon glyphicon-envelope text-muted icon-action mass-action read"></i> 
						<i class="hidden glyphicon glyphicon-trash icon-action mass-action delete"></i> 
					</th>
					<th class="text-right" style="white-space: nowrap;">
						<span class="total-message-count"></span> <span class="language" data-key="messages_num">messages</span>
					</th>
				</tr>
			</thead>
			<tbody>
			</tbody>
		</table>

		<div class="message-viewer hidden">
			<h2 style="margin-left: -18px;">
				<i class="glyphicon glyphicon-chevron-left icon-action close-message" style="font-size: .7em;"></i> 
				<span class="message-subject" style="margin-left: -15px;"></span>
			</h2>

			<div class="single-message-template hidden">
				<div class="message-header">
					<span class="message-sender pull-left text-bold"></span>
					<small class="message-actions pull-right">
						<i class="glyphicon glyphicon-star icon-action unstar text-warning"></i>
						<i class="glyphicon glyphicon-star-empty icon-action star text-warning"></i>
						<i class="glyphicon glyphicon-envelope icon-action unread"></i>
						<?php if(MessagesDB::canSend()) { ?>
							<i class="glyphicon glyphicon-share-alt flip-horizontal icon-action reply"></i>
							<i class="glyphicon glyphicon-share-alt icon-action forward"></i>
						<?php } ?>
						<i class="glyphicon glyphicon-trash icon-action delete"></i>
						<span class="message-datetime"></span>
					</small>
					<div class="clearfix"></div>
					<div style="font-size: smaller; margin-top: -1em;" class="text-muted">
						<span class="language" data-key="to">to</span>
						<span class="message-recipients"></span>
					</div>
				</div>
				<div class="message-seen text-muted"></div>

				<p class="message-text" style="white-space: pre-wrap;"></p>

				 <!-- <pre class="collapse message-debug"></pre> -->
			</div>

			<div class="messages-actions" style="margin: 2em 0;">
				<?php if(MessagesDB::canSend()) { ?>
					<button type="button" class="btn btn-default rspacer-lg reply">
						<i class="glyphicon glyphicon-share-alt flip-horizontal"></i>
						<span class="language" data-key="reply">Reply</span>
					</button>
					<button type="button" class="btn btn-default rspacer-lg forward">
						<i class="glyphicon glyphicon-share-alt"></i>
						<span class="language" data-key="forward">Forward</span>
					</button>
					<!-- <button type="button" class="btn btn-default rspacer-lg hidden btn-debug" data-toggle="collapse" data-target=".message-debug">
						<i class="glyphicon glyphicon-info-sign"></i> Debug
					</button> -->
				<?php } ?>
			</div>
		</div>
	</div>
</div>

<style>
	.topbar > .btn, .folders > li, .folders > li > a {
		font-size: 1.4rem;
	}

	.page-header { margin-top: 0 !important; }
	.page-header > h1 { margin-top: 0 !important; }
	.page-header > h1 .input-group { /* search box */
		width: 50% !important;
		min-width: 240px;
		margin-top: 9px;
	}
	.page-header > h1 .input-group-addon {
		padding-left: 15px;
		padding-right: 15px;
	}
	.page-header > h1 .input-group * {
		font-size: 18px;
	}
	.left-sidebar > .btn {
		text-align: left;
	}

	.icon-action {
		cursor: pointer;
		margin: 1em;
	}
	.icon-action:hover {
		background-color: #eee;
		border-radius: 50%;
		border: solid 8px #eee;
		margin: calc(1em - 8px);
	}

	/* messages-list  */
		.messages-list thead th {
			vertical-align: middle !important;
			height: 4em;
		}
		.messages-list td {
			height: 2em;
			cursor: pointer;
		}
		.messages-list td * {
			cursor: pointer;
		}
		.messages-list td.message-selector,
		.messages-list td.message-starred {
			width: 3em !important;
			text-align: center;
			vertical-align: top;
		}
		.messages-list td.message-from {
			width: 18em !important;
		}
		.messages-list td.message-selector {
			padding-top: 0 !important;
		}
		.messages-list .message-preview::before {
			content: " - ";
		}
		.messages-list td.message-text {
			overflow: hidden;
			display: inline-block;
			margin-top: -1px !important;
			width: 100%;
		}
		.messages-list td.message-datetime {
			width: 7em;
			text-align: right;
		}

	/* message viewer */
		.message-header {
			font-size: 1.2em;
			border-bottom: dotted 1px #ccc;
		}
		.message-seen {
			text-align: right;
			margin-bottom: 1.25em;
			font-style: italic;
		}
		.message-header .message-datetime {
			min-width: 5em;
			display: inline-block;
			text-align: right;
		}
		.single-message {
			border-bottom: solid 1px #ccc;
			padding: 1.25em 0;
		}

	.modal-footer .btn-primary {
		width: 33vw;
		max-width: 15em;
	}

	.flip-horizontal {
		transform: scaleX(-1);
	}

	/* sliding alerts */
		@keyframes landing {
			  0% { margin-top: -100px; opacity: 0; }
			 10% { margin-top: 0; opacity: .96; }
			 90% { opacity: .96; }
			100% { opacity: 0; }
		}

		@keyframes rising {
			  0% { margin-bottom: -100px; opacity: 0; }
			 10% { margin-bottom: 0; opacity: .96; }
			 90% { opacity: .96; }
			100% { opacity: 0; }
		}
		.sliding-alert-top {
			position: fixed;
			z-index: 100;
			top: 0;
			left: .5em;
			width: calc(100% - 1em);
			text-align: center;
			border-top-left-radius: 0;
			border-top-right-radius: 0;
			margin: 0;
			animation: landing;
			animation-duration: 10s;
		}
		.sliding-alert-bottom {
			position: fixed;
			z-index: 100;
			bottom: 0;
			left: .5em;
			width: calc(100% - 1em);
			text-align: center;
			border-bottom-left-radius: 0;
			border-bottom-right-radius: 0;
			margin: 0;
			animation: rising;
			animation-duration: 10s;
		}

		/* rtl styles  */
		[style*="direction: rtl"] .glyphicon-chevron-left,
		[style*="direction: rtl"] .glyphicon-chevron-right {
			transform: rotate(180deg);
		}
		[style*="direction: rtl"] .message-header .pull-left {
			float: right !important;
		}
		[style*="direction: rtl"] .message-header .pull-right {
			float: left !important;
		}
		[style*="direction: rtl"] .modal-header .close {
			float: left !important;
		}
		[style*="direction: rtl"] .modal-footer .btn + .btn {
			margin-right: 5px;
			margin-left: 0;
		}
		[style*="direction: rtl"] .left-sidebar .folders {
			padding-right: 0;
		}
		[style*="direction: rtl"] .left-sidebar .settings {
			text-align: right;
		}

		/* mobile topbar */
		.topbar > .nav-pills {
			width: calc(100% * 4 / <?php echo intval($topBarNumButtons ?? 4); ?> - 6px);
			display: inline-block;
		}
		.topbar > .nav-pills > li {
			width: 25%;
		}
		.topbar > .nav-pills > li + li {
			margin-left: 0;
		}
		.topbar > .btn {
			width: calc(100% / <?php echo intval($topBarNumButtons ?? 1); ?>);
			overflow: hidden;
			padding: 1em 0;
			vertical-align: top;
		}
		.topbar > .nav-pills > li > a {
			width: 100%;
			padding: 1em 0;
			text-align: center;
			display: inline-block;
			overflow: hidden;
		}
		.topbar > .btn .glyphicon,
		.topbar li > a .glyphicon {
			display: block;
			margin-bottom: 1em;
		}
		.new-msg-text {
			font-family: monospace;
		}
		.new-msg-preview {
			border: dashed 1px; 
			padding: 1em; 
			height: 45vh; 
			overflow-y: auto; 
			border-radius: 5px;
		}
</style>

<script src="<?php echo application_url('plugins/messages/app-resources/js/purify.min.js'); ?>"></script>
<script src="<?php echo application_url('plugins/messages/app-resources/js/marked.min.js'); ?>"></script>
<script src="<?php echo application_url('plugins/messages/app-resources/js/inbox.js'); ?>"></script>
	
<?php include_once(__DIR__ . '/../footer.php');