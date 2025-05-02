<?php
	include(__DIR__ . '/header.php');

	if($messages->dbInstalled())
		redirect('plugins/messages/settings.php');

	$ok = '<i class="glyphicon glyphicon-ok text-success" style="float: left; margin-right: .3em;"></i>';
	$failed = '<span class="text-danger text-bold">FAILED</span>';

	echo $messages->header_nav();
	echo $messages->breadcrumb([
		'../../' => $Translation["user's area"],
		'../../admin/' => $Translation['admin area'],
		'' => 'Installing Messages'
	]);

	$copyFile = function($src, $dest) {
		$dest = realpath($dest);
		echo "<li> Copying <code>{$src}</code> to <code>" . basename($dest) . "</code> ... ";
		if(!is_file("$dest/$src"))
			copy(__DIR__ . "/app-resources/{$src}", "$dest/$src");

		return is_file("$dest/$src");
	};

	ob_start();
		echo '<ul>';

		/***********************************************/
		echo '<li> Attempting to create tables ... ';
		$messages->createTables();
		echo ($messages->dbInstalled() ? $ok : $failed) . '</li>';

		/***********************************************/
		echo '<li> Enabling Messages ... ';
		$messages->enabled(true);
		echo ($messages->enabled() ? $ok : $failed) . '</li>';

		/***********************************************/
		if($messages->dbInstalled()) {
			echo '<li> Applying default settings ... ';
			$messages->setting('notify-by-email', 0);
			echo $ok . '</li>';
		}

		/***********************************************/
		echo ($copyFile(
			'MessagesDB.php',
			__DIR__ . '/../../resources/lib'
		) ? $ok : $failed) . '</li>';

		/***********************************************/
		echo '<li> Installing messages icon to <code>hooks/header-extras.php</code> ... ';
		$manualStep =  '<br>You can manually add this line to the file: ' .
						'<pre>&lt;?php ' . $messages->iconCode() . '</pre>';
		$messages->iconInstalled(true);
		echo ($messages->iconInstalled() ? $ok : $failed . $manualStep) . '</li>';

		/***********************************************/
		echo '</ul>';
	$log = ob_get_clean();
	echo $log;

	if(strpos($log, $failed) !== false) {
		?>
		<div class="alert alert-danger text-bold">
			Messages were NOT installed successfully.
			This might be due to incorrect permissions for the plugins folder.
			Please try setting the folder owner to the same user running the web server
			process, for example by running this command from a shell:
			<pre>chown www-data:www-data <?php echo __DIR__; ?></pre>
		</div>
		<?php
	} else {
		?>
		<div class="alert alert-success">
			Messages were installed successfully!
			You can configure which groups are allowed to send messages,
			plus other settings from the
			<a href="settings.php">messages settings page</a>.
		</div>

		<div class="btn-group">
			<a href="settings.php" class="btn btn-default">
				<i class="glyphicon glyphicon-cog"></i> 
				Messages settings
			</a>
			<a href="index.php" class="btn btn-default">
				<i class="glyphicon glyphicon-envelope"></i> 
				Inbox
			</a>
		</div>

		<div class="clearfix"></div>
		<?php
	}

	include(__DIR__ . '/footer.php');