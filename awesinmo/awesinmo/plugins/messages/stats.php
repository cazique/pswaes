<?php
	include_once(__DIR__ . '/messages.php');

	handleFullPageRequest();

	/******************************************************/

	function handleFullPageRequest() {
		ob_start();
		include(__DIR__ . '/header.php');
		global $Translation;

		echo $messages->header_nav();
		echo $messages->breadcrumb([
			'../../' => $Translation["user's area"],
			'./' => '<span class="language" data-key="messages">Messages</span>',
			'settings.php' => '<span class="language" data-key="settings">Settings</span>',
			'' => '<span class="language" data-key="stats">Stats</span>'
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

		echo $header .
		     jsCode() .
		     filterForm() .
			 '<div class="row">
				<div class="col-sm-6">' . topSendersBySize() . '</div>
				<div class="col-sm-6">' . topSendersByCount() . '</div>
			 </div>' .
			 '<div class="row">
				<div class="col-sm-6">' . topRecipientsBySize() . '</div>
				<div class="col-sm-6">' . topRecipientsByCount() . '</div>
			 </div>';

		include(__DIR__ . '/footer.php');
		exit;
	}

	function jsCode() {
		ob_start(); ?>
		<link rel="stylesheet" type="text/css" href="<?php echo application_url('plugins/messages/app-resources/css/charts.css'); ?>">
		<script src="<?php echo application_url('plugins/messages/app-resources/js/charts.js'); ?>"></script>
		<?php
		return ob_get_clean();
	}

	function filterForm($data = []) {
		global $Translation;
		return '';
		// TODO

		ob_start(); ?>

		<form action="<?php echo $_SERVER['PHP_SELF']; ?>">
			<div class="well">
				TODO: Period selector. From [   ] to [   ] [APPLY]<br>
				Preselect dates in $data['from'] and $data['to']
			</div>
		</form>

		<?php		
		return ob_get_clean();
	}

	function formatTop($title, $data, $valType = 'count') {
		$id = bin2hex(random_bytes(5));
		if($valType != 'count') $valType = 'size';
		ob_start(); ?>

		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title text-bold"><?php echo $title; ?></h3>
			</div>
			<div class="panel-body" id="<?php echo $id; ?>"></div>
		</div>

		<script>$j(() => {
			AppGiniPlugin.charts.hbar(
				<?php echo json_encode(array_keys($data)); ?>,
				<?php echo json_encode(array_values($data)); ?>,
				<?php if($valType == 'count') { ?>
					(i) => parseInt(i).toLocaleString()
				<?php } else { ?>
					(i) => {
						const KB = Math.pow(2, 10),
						      MB = Math.pow(2, 20),
						      GB = Math.pow(2, 30);

						i = parseFloat(i);
						if(i > GB) return Math.round(i / GB).toLocaleString() + 'GB';
						if(i > MB) return Math.round(i / MB).toLocaleString() + 'MB';
						if(i > KB) return Math.round(i / KB).toLocaleString() + 'KB';
						return i.toLocaleString() + 'B';
					}
				<?php } ?>
			).appendTo('#<?php echo $id; ?>')
		})</script>

		<?php
		return ob_get_clean();
	}

	function topSendersByCount($from = '', $to ='') {
		$top = getTop('sender', $from, $to, 2);

		$data = [];
		foreach($top as $row)
			$data[$row['username']] = $row['count'];

		return formatTop('<span class="language" data-key="top_senders_count">Top senders by count</span>', $data, 'count');
	}

	function topSendersBySize($from = '', $to ='') {
		$top = getTop('sender', $from, $to, 3);

		$data = [];
		foreach($top as $row)
			$data[$row['username']] = $row['size'];

		return formatTop('<span class="language" data-key="top_senders_size">Top senders by size</span>', $data, 'size');
	}

	function topRecipientsByCount($from = '', $to ='') {
		$top = getTop('recipients', $from, $to, 2);

		$data = [];
		foreach($top as $row)
			$data[$row['username']] = $row['count'];

		return formatTop('<span class="language" data-key="top_recipients_count">Top recipients by count</span>', $data, 'count');
	}

	function topRecipientsBySize($from = '', $to ='') {
		$top = getTop('recipients', $from, $to, 3);

		$data = [];
		foreach($top as $row)
			$data[$row['username']] = $row['size'];

		return formatTop('<span class="language" data-key="top_recipients_size">Top recipients by size</span>', $data, 'size');
	}

	function getTop($type, $from = '', $to ='', $orderBy = 2) {
		if(!in_array($type, ['sender', 'recipients']))
			$type = 'sender';
		if($orderBy != 3) $orderBy = 2;

		$eo = ['silentErrors' => true];
		$top = [];

		$res = sql("
			SELECT
				`owner` AS 'username', 
				COUNT(1) AS 'count', 
				SUM(LENGTH(`subject`) + LENGTH(`message`) + LENGTH(`recipients`) + 75) AS 'size'
			FROM `appgini_messages`
			WHERE
				`owner` = `$type`
				/*AND `createdTS` BETWEEN (UNIX_TIMESTAMP() - 86400 * 7) AND (UNIX_TIMESTAMP() - 86400 * 0)*/
			GROUP BY `owner` ORDER BY $orderBy DESC LIMIT 20
		", $eo);
		while($row = db_fetch_assoc($res))
			$top[] = $row;

		return $top;
	}
