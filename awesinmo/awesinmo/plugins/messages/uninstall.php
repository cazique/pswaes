<?php
	include(__DIR__ . '/header.php');

	$ok = '<i class="glyphicon glyphicon-ok text-success" style="float: left; margin-right: .3em;"></i>';
	$failed = '<span class="text-danger text-bold language" data-key="FAILED">FAILED</span>';

	echo $messages->header_nav();
	echo $messages->breadcrumb([
		'../../' => $Translation["user's area"],
		'./' => '<span class="language" data-key="messages">Messages</span>',
		'settings.php' => '<span class="language" data-key="settings">Settings</span>',
		'' => '<span class="language" data-key="uninstall_messages">Uninstall</span>'
	]);

	if(Request::val('confirm') != 1) {
		?>
		<div class="alert alert-danger" style="font-size: 1.5em;">
			<i class="glyphicon glyphicon-exclamation-sign"></i>
			<span class="language" data-key="uninstall_warning"></span>
			<br><br>
			
			<span class="language" data-key="uninstall_proceed"></span><br><br>

			<a href="./" class="btn btn-success btn-lg">
				<i class="glyphicon glyphicon-ok"></i>
				<span class="language" data-key="dont_uninstall"></span>
			</a><br><br>
			<a href="uninstall.php?confirm=1" class="btn btn-danger">
				<i class="glyphicon glyphicon-trash"></i>
				<span class="language" data-key="yes_uninstall"></span>
			</a>
		</div>
		<?php
		
		include(__DIR__ . '/footer.php');
		exit;
	}

	$removeFile = function($file, $dest) {
		$dest = realpath($dest);
		echo "<li> Removing <code>{$file}</code> from <code>" . basename($dest) . "</code> ... ";
		@unlink("$dest/$file");

		return !is_file("$dest/$file");
	};

	ob_start();
		echo '<ul>';

		/***********************************************/
		echo '<li> Attempting to drop tables ... ';
		$messages->dropTables();
		echo (!$messages->dbInstalled() ? $ok : $failed) . '</li>';

		/***********************************************/
		echo '<li> Disabling Messages ... ';
		$messages->enabled(false);
		echo (!$messages->enabled() ? $ok : $failed) . '</li>';

		/***********************************************/
		echo ($removeFile(
			'MessagesDB.php',
			__DIR__ . '/../../resources/lib'
		) ? $ok : $failed) . '</li>';

		/***********************************************/
		echo '<li> Removing messages icon from <code>hooks/header-extras.php</code> ... ';
		$messages->iconInstalled(false);
		echo (!$messages->iconInstalled() ? $ok : $failed) . '</li>';

		/***********************************************/
		echo '</ul>';
	$log = ob_get_clean();
	echo $log;

	if(strpos($log, $failed) !== false) {
		?>
		<div class="alert alert-danger text-bold">
			<span class="language" data-key="uninstall_failed"></span>
			<pre>chown www-data:www-data <?php echo __DIR__; ?></pre>
		</div>
		<?php
	} else {
		?>
		<div class="alert alert-success">
			<p class="language" data-key="uninstall_success"></p>
			<a href="settings.php" class="language" data-key="settings"></a>.
		</div>
		<?php
	}

	include(__DIR__ . '/footer.php');