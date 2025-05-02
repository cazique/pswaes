<?php
	include(__DIR__ . '/../plugins-resources/loader.php');

	class messages extends AppGiniPlugin {
		/* add any plugin-specific properties here */
		
		public function __construct($config = []) {
			parent::__construct($config);
			
			/* add any further plugin-specific initialization here */
		}
		
		/**
		 * Determines if plugin tables are installed.
		 *
		 * @return     bool  True if installed, False otherwise.
		 */
		public function dbInstalled() {
			$eo = ['silentErrors' => true, 'noErrorQueryLog' => true];
			$res1 = sql("SELECT COUNT(1) FROM `appgini_messages_settings`", $eo);
			$res2 = sql("SELECT COUNT(1) FROM `appgini_messages`", $eo);
			$res3 = sql("SELECT COUNT(1) FROM `appgini_messages_group_permissions`", $eo);
			return $res1 !== false
				&& $res2 !== false
				&& $res3 !== false;
		}

		public function enabled($status = null) {
			$flager = __DIR__ . '/.messages-enabled';

			if($status === null)
				return is_file($flager);

			if($status === false) {
				@unlink($flager);
				return false;
			}

			return @touch($flager);
		}

		public function iconCode() {
			return "include_once(__DIR__ . '/../plugins/messages/app-resources/icon.php');";
		}

		public function iconInstalled($status = null) {
			$extrasFile = realpath(__DIR__ . '/../../hooks/header-extras.php');
			$code = $this->iconCode();

			// if file not present, attempt to create it before aborting
			if(!is_file($extrasFile)) {
				@touch($extrasFile);
				if(!is_file($extrasFile)) return false;
			}

			// if file not readable, abort
			if(!is_readable($extrasFile)) return false;

			$extras = @file_get_contents($extrasFile);

			// iconInstalled() => return whether code exists or not
			if($status === null)
				return (strpos($extras, $code) !== false);

			// after this point, if unable to write to file, abort
			if(!is_writable($extrasFile)) return false;

			// iconInstalled(false) => remove icon code and return
			if($status === false) {
				$extras = str_replace($code, '', $extras);
				// also remove 'inserted by' wrapper comments
				$extras = preg_replace('/\s*\/\*\s*inserted by messages plugin\s*\*\/\s*/i', '', $extras);
				$extras = preg_replace('/\s*\/\*\s*end of messages plugin code\s*\*\/\s*/i', '', $extras);
				@file_put_contents($extrasFile, $extras);
				return false;
			}

			// iconInstalled(true) => install icon code if not already there
			if(strpos($extras, $code) === false) {
				// wrap code with 'inserted by' comments
				$code = "\t/* Inserted by Messages plugin */\n\t$code\n\t/* End of Messages plugin code */";
				$extras = $this->appendPHPCode($extras, $code);
			}

			// save updated code to header-extras.php
			return @file_put_contents($extrasFile, $extras) > 0;
		}

		private function appendPHPCode($initialCode, $append) {
			// if count of closing php tags == count of opening tags, open a php tag
			// this also includes the case where the file is empty
			// and the case where the file has client-side code only
			$numOpenTags = substr_count($initialCode, '<' . '?php');
			$numCloseTags = substr_count($initialCode, '?>');
			$empty = !strlen(trim($initialCode));

			/*
			 The trimming stuff below aims to prevent unnecessary white space before
			 inserted code. If the file has no initial code, there is no need to insert
			 a new line before the opening php tag. If there are white spaces before the
			 insertion point, trim them.
			 */
			return rtrim($initialCode) .
				($numOpenTags == $numCloseTags ? ($empty ? '<' : "\n<") . '?php' : '') .
				"\n{$append}";
		}

		public function createTables() {
			$eo = ['silentErrors' => true];

			sql("CREATE TABLE IF NOT EXISTS `appgini_messages_settings` (
				`key` VARCHAR(100) NOT NULL,
				PRIMARY KEY (`key`),
				`value` TEXT NULL
			) charset " . mysql_charset, $eo);

			// TODO: full text search index, and other indexes
			sql("CREATE TABLE IF NOT EXISTS `appgini_messages` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				PRIMARY KEY (`id`),
				`originalId` BIGINT UNSIGNED DEFAULT NULL, /* if this is the recipient's copy, id will not be the same as originalId */
				`createdTS` INT UNSIGNED NOT NULL,
				`draft` TINYINT NOT NULL DEFAULT '1', /* on creating a message, it's a draft by default until user clicks 'Send' */
				`sentTS` INT UNSIGNED DEFAULT NULL, /* this is the timestamp for sending an email notification to recipient. initially NULL. used to get a queue of emails to send: ISNULL(sentTS) AND NOT ISNULL(recipient) AND id=originalId AND draft=0 */
				`seenTS` INT UNSIGNED DEFAULT NULL, /* timestamp when recipient first opened the message */
				`inReplyTo` BIGINT UNSIGNED DEFAULT NULL, /* this is the id of the initial message to which this one is a reply -- Challenge: this should point to the correct copy of the message: for sender => the originalId, for recipient => the copy id :/ */
				`sender` VARCHAR(200) NOT NULL, /* memberId */
				`owner` VARCHAR(200) DEFAULT NULL, /* memberId -- username to which this message belongs. When message is still being created, it has one corresponding record, with the owner being the sender. After sending, another copy is created, with the owner being the recipient ... this allows each of the sender and recipient to separately mark message as unread, star them and delete them, without affecting the other end's copy */
				`recipients` TEXT DEFAULT NULL, /* for sender's copy: multiple comma-separated recipients allowed, also 'group:groupname' allowed. for recipient's copy: one recipient username only .. for multiple recipients or groups, each recipient gets a separate copy */
				`subject` VARCHAR(100) NOT NULL,
				`message` TEXT NULL,
				`markedUnread` TINYINT NOT NULL DEFAULT '1', /* The seenTS above is the real indicator of whether the msg has been opened by recipient .. this field is just a flag for recipient (or sender) to use in filters, .. etc. */
				`starred` TINYINT NOT NULL DEFAULT '0',
				`updateDT` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP /* this would be updated automatically on insertion, and whenever any field in the record is updated */
			) charset " . mysql_charset, $eo);

			sql("CREATE TABLE IF NOT EXISTS `appgini_messages_group_permissions` (
				`groupID` INT UNSIGNED NOT NULL, /* to have a single record for every group */
				PRIMARY KEY (`groupID`),
				`hasAccess` TINYINT NOT NULL DEFAULT '0',
				`allowedRecipientGroupIDs` TEXT NULL, /* comma-separated list of allowed recipient group ids. if set to *, this group can send to all groups */
				`canSendGroupMessage` TINYINT NOT NULL DEFAULT '0',
				`canSendGlobalMessage` TINYINT NOT NULL DEFAULT '0',
				`maxRecipients` INT UNSIGNED DEFAULT '1' /* this is applied only if canSendGroupMessage=0 AND canSendGlobalMessage=0 */
			) charset " . mysql_charset, $eo);
		}

		public function dropTables() {
			$eo = ['silentErrors' => true, 'noErrorQueryLog' => true];
			sql("DROP TABLE `appgini_messages_settings`;", $eo);			
			sql("DROP TABLE `appgini_messages_group_permissions`;", $eo);			
			sql("DROP TABLE `appgini_messages`;", $eo);			
		}

		public function setting($key, $val = null) {
			static $settings = null;
			$eo = ['silentErrors' => true];

			// retrieve existing settings if not already retrieved
			if($settings === null) {
				$res = sql("SELECT * FROM `appgini_messages_settings`", $eo);
				while($row = db_fetch_assoc($res))
					$settings[$row['key']] = $row['value'];
			}
			
			// set new value for given key if provided
			// or save key/value pair if new key
			if($val !== null || !isset($settings[$key])) {
				$settings[$key] = $val;
				$safeKey = makeSafe($key);
				$safeVal = makeSafe($val);
				sql("REPLACE INTO `appgini_messages_settings` (`key`, `value`)
					VALUES ('$safeKey', '$safeVal')", $eo);
			}

			return $settings[$key];
		}

		public function groupIdByName($group) {
			return sqlValue("SELECT `groupID` FROM `membership_groups` WHERE `name`='" . makeSafe($group) . "'");
		}

		public function groupNameById($id) {
			return sqlValue("SELECT `name` FROM `membership_groups` WHERE `groupID`='" . intval($id) . "'");
		}

		private function validGroupPermissions(&$perm) {
			if(!is_array($perm)) return false;
			if(!isset($perm['allowedRecipientGroupIDs'])) return false;
			return true;
		}

		public function groupPermissions($group, $newPerm = null) {
			static $perm = [];
			$eo = ['silentErrors' => true];

			if($this->validGroupPermissions($newPerm)) {
				$groupID = $this->groupIdByName($group);
				if(!$groupID) return false;

				if(!sql("REPLACE INTO `appgini_messages_group_permissions` SET " . 
					prepare_sql_set($newPerm) . ", groupID='$groupID'",
				$eo)) return false;

				$perm[$group] = $newPerm;
				$perm[$group]['groupID'] = $groupID;
				$perm[$group]['groupName'] = $group;
			}

			if(!empty($perm[$group])) return $perm[$group];

			$groupID = $this->groupIdByName($group);
			if(!$groupID) return false;

			$res = sql("SELECT * FROM `appgini_messages_group_permissions` WHERE `groupID`='$groupID'", $eo);
			$perm[$group] = db_fetch_assoc($res);

			if(!$perm[$group])
				return $this->groupPermissions($group, [
					'hasAccess' => 0,
					'allowedRecipientGroupIDs' => '',
					'canSendGroupMessage' => 0,
					'canSendGlobalMessage' => 0,
					'maxRecipients' => 0,
				]);

			$perm[$group]['groupName'] = $group;
			return $perm[$group];
		}
	}
