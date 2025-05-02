<?php
	define('UPLOAD_KEY', '8F3C4B57A0C72F7770475116CFB69F37');

	define('APPGINI_DIRS', [
		'',
		'admin',
		'admin/images',
		'hooks',
		'images',
		'templates',
		'resources',
		'resources/bootstrap-datetimepicker',
		'resources/csv',
		'resources/datepicker',
		'resources/datepicker/css',
		'resources/datepicker/js',
		'resources/datepicker/js/lang',
		'resources/datepicker/media',
		'resources/hotkeys',
		'resources/images',
		'resources/initializr',
		'resources/initializr/css',
		'resources/initializr/fonts',
		'resources/initializr/img',
		'resources/initializr/js',
		'resources/initializr/js/vendor',
		'resources/jquery',
		'resources/jquery/js',
		'resources/lib',
		'resources/lib/PHPMailer',
		'resources/lib/PHPMailer/PHPMailer',
		'resources/lightbox',
		'resources/lightbox/css',
		'resources/lightbox/images',
		'resources/lightbox/js',
		'resources/moment',
		'resources/select2',
		'resources/table_icons',
		'resources/timepicker',
	]);

	define('EXCLUDES', [
		'setup.md5',
		'config.php',
		'.git',
		'.gitignore',
		'.htaccess',
		'file-uploader.php',
		'import-csv/',
		'plugins',
		'admin/backups',
		'admin/csv',
	]);

##########################################################

	denyAccessOnError();

	define('INVALID_FOLDER', 'Invalid!');

	/*
	 * Sending maintenance=on in a POST request turns on maintenance mode.
	 * Sending maintenance=off in a POST request turns off maintenance mode.
	 */
	handleMaintenanceModeRequest();

	// if this is just an md5 check rather than an upload request,
	// return md5sum of every file
	if(empty($_FILES))
		die(retrieveMD5Sums());

	createNonExistentDirs();
	handleFileUpload();

##########################################################

	function getProcessOwner() {
		if (!function_exists('posix_getpwuid') || !function_exists('posix_geteuid')) return false;

		$userId = posix_geteuid();
		$userInfo = posix_getpwuid($userId);

		if ($userInfo === false) return false;

		return $userInfo['name'];
	}

	function getProcessOwnerGroup() {
		if (!function_exists('posix_getgrgid') || !function_exists('posix_getegid')) return false;

		$groupId = posix_getegid();
		$groupInfo = posix_getgrgid($groupId);

		if ($groupInfo === false) return false;

		return $groupInfo['name'];
	}

	function handleMaintenanceModeRequest() {
		if(!isset($_POST['maintenance'])) return;

		$state = $_POST['maintenance'] == 'on';
		$flag = __DIR__ . '/admin/.maintenance';

		// create admin dir if not already there
		if(!is_dir(dirname($flag))) @mkdir(dirname($flag));

		// turn maintenance mode on
		if($state) {
			@touch($flag);
			die();
		}

		// turn maintenance mode off
		@unlink($flag);
		die();
	}

	function denyAccessOnError() {
		$skipReason = null;

		$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
		$isLocalhost = $_SERVER['SERVER_NAME'] === 'localhost';
		$isSecureProxy = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && (
			$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
			|| $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on'
			|| $_SERVER['HTTP_X_FORWARDED_PORT'] === '443'
		);

		// Allow insecure connections from localhost or secure proxies
		if(!$isSecure && ($isLocalhost || $isSecureProxy)) $isSecure = true;

		// If connection is still not secure, abort with error
		if(!$isSecure) $skipReason = 'Not on a secure connection';

		// skip if connection UPLOAD_KEY is not provided or is incorrect
		if(empty($_POST['UPLOAD_KEY']) || $_POST['UPLOAD_KEY'] !== UPLOAD_KEY)
			$skipReason = 'Invalid or missing UPLOAD_KEY';

		if(!empty($skipReason)) {
			@http_response_code(403);
			die('Access denied! ' . $skipReason);
		}
	}

	function createNonExistentDirs() {
		foreach(APPGINI_DIRS as $dir) {
			if(!strlen($dir) || is_dir(__DIR__ . "/$dir")) continue;
			@mkdir(__DIR__ . "/$dir", 0777, true); // create dirs recursively
		}
	}

	function filesList() {
		static $list = [];
		if(!empty($list)) return $list;

		foreach(APPGINI_DIRS as $d)
			$list = array_merge($list, subdirList($d));

		return $list;
	}

	function subdirList($subdir = '') {
		// avoid endless loops
		if(
			in_array($subdir, ['.', '..'])
			|| substr($subdir, -2) == '/.'
			|| substr($subdir, -3) == '/..'
		) return [];

		$dir = __DIR__ . "/$subdir";
		if(!is_dir($dir)) return [];

		$list = array_diff(scandir($dir), ['.', '..']);

		// prepend $subdir to entries found
		if(strlen($subdir))
			$list = array_map(function($l) use ($subdir) { return "$subdir/$l"; }, $list);

		// exclude entries that start with one of EXCLUDES
		foreach($list as $i => $entry)
			foreach(EXCLUDES as $start) {
				if(strpos($entry, $start) !== 0) continue;

				unset($list[$i]);
				break; // no further regex checks for this entry
			}

		return $list;
	}

	function retrieveMD5Sums() {
		@header('Content-type: text/plain');

		$md5List = [];
		foreach(filesList() as $entry) {
			$path = __DIR__ . "/$entry";
			$md5List[] = "$entry: " . (is_dir($path) ? 'DIR' : md5_file($path));
		}

		if(!count($md5List)) $md5List = ['No files found!'];

		return implode("\n", $md5List);
	}

	function retrieveDirs() {
		$dirs = [];
		foreach(filesList() as $entry) {
			$path = __DIR__ . "/$entry";
			if(!is_dir($path)) continue;
			$dirs[] = $entry;
		}

		return $dirs;
	}

	function appFolder($folder) {
		return in_array($folder, retrieveDirs()) || $folder === '';
	}

	function allowedFileName($name) {
		$name = basename($name);

		$folder = getFolder();
		if($folder === INVALID_FOLDER) return false;

		$file = trim("$folder/$name", '/');

		return !in_array($file, EXCLUDES);
	}

	function getFolder() {
		$folder = isset($_POST['folder']) ? $_POST['folder'] : '';
		$folder = str_replace('\\', '/', $folder);
		return appFolder($folder) ? $folder : INVALID_FOLDER;
	}

	function toFullPath($folder, $name) {
		$name = basename($name);

		if(!strlen($folder))
			return __DIR__ . "/$name";

		return __DIR__ . '/' . trim($folder, '/') . "/$name";
	}

	function handleFileUpload() {
		$appFile = isset($_FILES['appFile']) ? $_FILES['appFile'] : false;
		$folder = getFolder();
		$name = isset($appFile['name']) ? $appFile['name'] : false;

		// skip on errors
		if(
			$name === false
			|| $appFile === false
			|| $folder === INVALID_FOLDER
			|| !isset($appFile['error'])
			|| $appFile['error'] != UPLOAD_ERR_OK
			|| !allowedFileName($name)
			|| empty($appFile['tmp_name'])
			|| !@move_uploaded_file($appFile['tmp_name'], toFullPath($folder, $name))
		) {
			http_response_code(403);
			$processOwner = getProcessOwner();
			$processOwnerGroup = getProcessOwnerGroup();
			$hint = '';
			if($processOwner && $processOwnerGroup)
				$hint = ". Try running this command on the server: sudo chown -R $processOwner:$processOwnerGroup " . __DIR__;
			die("Access denied! name: $name, folder: {$folder}{$hint}");
		}

		@header('Content-type: text/plain');
		echo 'OK';
	}
