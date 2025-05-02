<?php
	class MessagesDB {
		// handling of folders below is done in where() method
		const FOLDERS = ['inbox', 'unread', 'sent', 'starred', 'drafts', 'search'];
		private static $lastError = null;

		public static function lastError() {
			return self::$lastError;
		}

		private static function error($msg) {
			self::$lastError = $msg;
			return false;
		}

		public static function badRequest($msg = null) {
			@header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
			die($msg);
		}

		public static function enabled() {
			return is_file(__DIR__ . '/../../plugins/messages/.messages-enabled');
		}

		public static function groupPermissions($group = null) {
			static $perm = [];
			if($group === null) $group = getMemberInfo()['group'];

			if(!empty($perm[$group])) return $perm[$group];

			$groupID = sqlValue("SELECT `groupID` FROM `membership_groups` WHERE `name`='" . makeSafe($group) . "'");
			if(!$groupID) return false;

			if($group == 'Admins') {
				$perm[$group] = [
					'groupID' => $groupID,
					'hasAccess' => 1,
					'allowedRecipientGroupIDs' => '*',
					'canSendGroupMessage' => 1,
					'canSendGlobalMessage' => 1,
					'maxRecipients' => 1000000,
				];

				return $perm[$group];
			}

			$res = sql("SELECT * FROM `appgini_messages_group_permissions` WHERE `groupID`='$groupID'", $eo);
			$perm[$group] = db_fetch_assoc($res);

			return $perm[$group];
		}

		public static function hasAccess() {
			if(!self::enabled()) return false;

			$mi = getMemberInfo();

			// admins group always allowed
			if($mi['group'] == 'Admins') return true;

			// check appgini_messages_group_permissions table
			return (bool) sqlValue("SELECT `hasAccess` FROM `appgini_messages_group_permissions` WHERE `groupID`='{$mi['groupID']}'");
		}

		public static function checkAccess($msg = '') {
			if(!self::hasAccess()) denyAccess($msg);
		}

		/**
		 * Determines if current user can send messages [to specified recipient [group]]
		 *
		 * @param      mixed  $recipients The recipients. If null, return value indicates if user can send at all.
		 *                                If a string, it should be in the format "user1,user2,group:group1,user3,..."
		 *                                If an array, it should be a list similar to:
		 *                                   ['user1', 'user2', 'group:group1', ...]
		 *
		 * @return     bool    True if current user is allowed to send, False otherwise.
		 */
		public static function canSend($recipients = null) {
			static $allowedRecipients = null;
			if($allowedRecipients === null) {
				$mi = getMemberInfo();

				// admins group always allowed
				if($mi['group'] == 'Admins') return true;

				$allowedRecipients = array_merge(
					array_map(
						function($u) { return $u['username']; }, 
						self::listAllowedRecipients('users')
					),
					array_map(
						function($g) { return "group:$g"; }, 
						array_keys(self::listAllowedRecipients('groups'))
					)
				);

				if(self::groupPermissions()['canSendGlobalMessage'])
					$allowedRecipients[] = '__ALL_USERS__';
			}

			// non-admins can send only if they have allowed recipients
			if(empty($allowedRecipients)) return false;

			if(!strlen($recipients)) $recipients = null;

			// if no recipient specified, then we're checking if current user can send at all
			// and since allowedRecipients is not empty by now, return true in this case
			if($recipients === null) return true;

			// a recipient string could be an array or a csv list of multiple recipients in the format:
			// user1,user2,group:group1,group:group2
			if(!is_array($recipients))
				$recipients = explode(',', trim($recipients, ', '));

			// trim each element of recipients and make sure 'group:groupId' is correctly formatted
			$recipients = array_map(function($r) {
				return trim(preg_replace('/^\s*group\s*:\s*(\d*)\s*/', 'group:$1', $r));
			}, $recipients);

			// if user can't send global nor group messages, make sure number of recipients is within limit
			$gp = self::groupPermissions();
			if(
				!$gp['canSendGlobalMessage']
				&& !$gp['canSendGroupMessage']
				&& $gp['maxRecipients'] < count($recipients)
			) return false;

			return !array_diff($recipients, $allowedRecipients);
		}

		private static function where($folder, $search) {
			$username = makeSafe(getMemberInfo()['username']);
			$where = ["`owner` = '{$username}'"];

			switch($folder) {
				case 'unread': /* only unread messages in inbox are counted */
					$where[] = "`recipients` = '{$username}'";
					$where[] = "`markedUnread` = 1";
					$where[] = "`draft` = 0";
					break;
				case 'sent':
					$where[] = "`sender` = '{$username}'";
					$where[] = "`draft` = 0";
					$where[] = "ISNULL(`originalId`)"; // to avoid a recipient seeing a message that they sent to group including them self in both 'sent' and 'inbox' folders
					break;
				case 'starred':
					$where[] = "`starred` = 1";
					$where[] = "`draft` = 0";
					break;
				case 'drafts':
					$where[] = "`draft` = 1";
					break;
				case 'search':
					// TODO: full text search (fts), https://www.digitalocean.com/community/tutorials/how-to-improve-database-searches-with-full-text-search-in-mysql-5-6-on-ubuntu-16-04
					// searching: recipient, sender, subject, message
					// sepcial words to be processed before fts and removed:
					// \bin:(inbox|sent|drafts)\b
					// \bis:(unread|starred)\b
					// \bfrom:\email\b
					// \bto:\email\b
					
					$where[] = "(`subject` LIKE '%{$search}%' OR `message` LIKE '%{$search}%')";
					break;
				case 'inbox':
				default:
					$where[] = "`recipients` = '{$username}'";
					$where[] = "`id` != `originalId`"; // to prevent a double list in case user sends messages to himself!
					break;
			}

			return implode(' AND ', $where);
		}

		private static function messageDataTypes($msg) {
			foreach($msg as $fn => $fv) {
				// any field in this array should be an int
				if(in_array($fn, ['id', 'originalId', 'createdTS', 'sentTS', 'inReplyTo']))
					$msg[$fn] = intval($fv);
				// any field in this array should be a bool
				elseif(in_array($fn, ['draft', 'markedUnread', 'starred']))
					$msg[$fn] = (bool) $fv;
			}

			return $msg;
		}

		public static function getThreads(int $startTS = null, int $start = null, int $limit = null) {
			$messages = [];
			$eo = ['silentErrors' => true];

			$username = makeSafe(getMemberInfo()['username']);
			$where = ["`owner` = '{$username}'"];

			// defaults
			if($start < 0 || $start === null) $start = 0;
			if($limit < 1) $limit = 500;

			if($startTS) $where[] = "`updateDT` >= '" . strtotime($startTS) . "'";

			$res = sql("SELECT *, if(`sender`=`owner`, `sentTS`, `createdTS`) as 'sorter'
				FROM `appgini_messages`	WHERE " . implode(' AND ', $where) . "
				ORDER BY `inReplyTo`, `sorter` LIMIT $start, $limit",
			$eo);
			while($row = db_fetch_assoc($res))
				$messages[] = self::messageDataTypes($row);

			return self::toThreadsMatrix($messages);
		}

		private static function toThreadsMatrix($messages, $newerFirst = true) {
			$matrix = $matrixIndex = [];

			while(count($messages)) {
				// take a message off the beginning of messages array
				$msg = array_splice($messages, 0, 1)[0];

				$found = false; // flag indicating if message to which current one is a reply was found
				
				// find msg to which current one is a reply
				if($msg['inReplyTo'])
					foreach($matrixIndex as $r => $thread) {
						foreach($thread as $c => $id) {
							if($id != $msg['inReplyTo']) continue;

							$matrix[$r][] = $msg;
							$matrixIndex[$r][] = $msg['id'];
							$found = true;
							break; // no need to keep looking
						}
						if($found) break;
					}

				// next iteration
				if($found) continue;

				// msg is not in reply to any other message
				// either because it's the beginning of a new thread, or because it's
				// a broken thread (with a deleted message in the middle of a thread)
				// in either case, start a new thread
				$matrix[] = [$msg];
				$matrixIndex[] = [$msg['id']];
			}

			// reverse threads?
			if($newerFirst)
				foreach($matrix as $i => $thread)
					$matrix[$i] = array_reverse($thread);

			// sort threads so that ones with newest messages appear first
			$newest = function ($thread) {
				return array_reduce($thread, function($max, $msg) {
					if($msg['sentTS'] > $max) return $msg['sentTS'];
					if($msg['createdTS'] > $max) return $msg['createdTS'];
					return $max;
				}, 0);
			};
			usort($matrix, function($t1, $t2) use ($newest) {
				return $newest($t1) > $newest($t2) ? -1 : 1;
			});

			return $matrix;
		}

		public static function get($folder = '', $search = '', $start = 0, $num = 200) {
			$messages = [];
			$eo = ['silentErrors' => true];

			$where = self::where($folder, $search);
			$orderBy = 'ORDER BY `sentTS` DESC, `createdTS` DESC';
				if($folder == 'search') $orderBy = '';

			$res = sql("SELECT * FROM `appgini_messages` WHERE $where $orderBy LIMIT $start, $num", $eo);
			while($row = db_fetch_assoc($res))
				$messages[] = self::messageDataTypes($row);

			return $messages;
		}

		public static function count($folder = '', $search = '') {
			$where = self::where($folder, $search);

			return intval(sqlValue("SELECT COUNT(1) FROM `appgini_messages` WHERE $where"));
		}

		public static function delete($ids = []) {
			if(empty($ids) || !is_array($ids))
				return self::error('Can\'t delete due to invalid message id(s)');

			$username = makeSafe(getMemberInfo()['username']);
			if(!strlen($username))
				return self::error('Can\'t delete due to invalid user');

			$safeIds = implode(',', array_map('intval', $ids));
			$eo = ['silentErrors' => true];
			sql("DELETE FROM `appgini_messages` WHERE `id` IN ($safeIds) AND `owner` = '$username'", $eo);

			return db_affected_rows() > 0;
		}

		/**
		 * Creates a draft message.
		 *
		 * @param      array  $msg    The message. Required keys: 'subject'. Possible other keys: 'recipients', 'message'
		 *
		 * @return     int|bool    new draft message id, or false on error
		 */
		public static function createMessage($msg) {
			// trim all data
			$msg = array_map('trim', $msg);

			// can user send messages (to provided recipients)?
			if(!self::canSend($msg['recipients'] ?? null))
				return self::error('Invalid recipient');

			if(!strlen($msg['subject'])) return self::error('Subject not specified');

			$username = Authentication::getUser()['username'];

			$msgFields = [
				'owner' => $username,
				'markedUnread' => 0,
				'recipients' => $msg['recipients'] ?? '',
				'createdTS' => time(),
				'draft' => 1,
				'sender' => $username,
				'subject' => $msg['subject'],
				'message' => $msg['message'] ?? '',
			];
			if($msg['inReplyTo']) $msgFields['inReplyTo'] = $msg['inReplyTo'];

			if(!insert('appgini_messages', $msgFields)) return self::error("Couldn't save draft");

			return db_insert_id();
		}

		public static function massUpdate($ids, $update) {
			// update given message ids if owned by current user
			// updates allowed: read/unread, starred/unstarred
			// seenTS business logic is NOT performed here .. see ::readMessage() for that purpose
			
			// get user
			$safeUser = makeSafe(getLoggedMemberID());
			if(!$safeUser) return self::error('Access denied');

			// prep ids
			if(!is_array($ids)) $ids = explode(',', $ids);
			$ids = array_filter(array_map('intval', array_map('trim', $ids)));
			$ids = implode(',', $ids);
			if(!strlen($ids)) return self::error('No messages to update');

			// validate and prep update
			$vUpdate = [];
			foreach($update as $key => $value)
				if(in_array($key, ['markedUnread', 'starred']))
					$vUpdate[] = "`$key`=" . ($value ? 1 : 0);
			if(!count($vUpdate)) return self::error('No changes were made');

			// apply update
			$eo = ['silentErrors' => true];
			if(!sql(
				"UPDATE `appgini_messages` SET " . 
				implode(', ', $vUpdate) . 
				" WHERE `owner`='$safeUser' AND `id` IN ($ids)",
			$eo)) return self::error('An error occurred while updating messages');

			return true;
		}

		public static function updateMessage($id, $update) {
			if(!self::getMyMessage($id)) return false;

			// trim all data
			$update = array_map('trim', $update);

			// can user send messages (to provided recipients)?
			if(!self::canSend($update['recipients'] ?? null))
				return false;

			if(!strlen($update['subject'])) return self::error('Subject not specified');

			if(!update('appgini_messages', [
				'recipients' => $update['recipients'] ?? '',
				'subject' => $update['subject'],
				'message' => $update['message'] ?? '',
			], [
				'id' => intval($id), 'draft' => 1
			])) return self::error("Couldn't save draft");

			return true;
		}

		private static function customAllowedRecipients($type, &$allowed) {
			if(!function_exists('plugin_messages_listAllowedRecipients'))
				return $allowed[$type];

			return $allowed[$type] = plugin_messages_listAllowedRecipients($type, $allowed[$type]);
		}

		/**
		 * Returns all users or groups that the current user can send messages to.
		 *
		 * @param      string  $type   optional, 'users' (default) or 'groups'
		 *
		 * @return     array   if $type is 'users', a numeric array of usernames. if $type is 'groups',
		 *                     an associative array of groupIDs as keys and group names as values.
		 */
		public static function listAllowedRecipients($type = 'users') {
			static $allowed = [];
			if($type != 'groups') $type = 'users';

			if(isset($allowed[$type])) return $allowed[$type];

			// Admins can send to any one
			$isAdmin = (getMemberInfo()['group'] == 'Admins');
			if($isAdmin) $groupIDs = '*';
			
			if(!$isAdmin) {
				$myGroupID = intval(getMemberInfo()['groupID']);
				$groupIDs = sqlValue("SELECT `allowedRecipientGroupIDs`
					FROM `appgini_messages_group_permissions`
					WHERE `groupID`='{$myGroupID}' AND `hasAccess`=1");
			}

			if(!$groupIDs) {
				$allowed['users'] = $allowed['groups'] = [];
				return self::customAllowedRecipients($type, $allowed);
			}

			$eo = ['silentErrors' => true];

			if($type == 'groups') {
				$groups = []; /* [ id => name, ... ]*/
				$anonGroup = makeSafe(config('adminConfig')['anonymousGroup']);

				// exclude anon group
				$where  = "WHERE `name`!='$anonGroup'";
				$where .= ($groupIDs == '*' ? '' : " AND `groupID` IN ({$groupIDs})");
				$res = sql("SELECT `groupID`, `name` FROM `membership_groups` {$where}", $eo);
				while($row = db_fetch_assoc($res))
					$groups[$row['groupID']] = $row['name'];

				$allowed['groups'] = $groups;
				return self::customAllowedRecipients($type, $allowed);
			}

			// type = users ... so expand groups into usernames
			$users = []; /* [ username1, username2, ... ]*/
			$anonUser = makeSafe(config('adminConfig')['anonymousMember']);

			// exclude anon user
			$where  = "WHERE `memberID` != '$anonUser'";
			$where .= ($groupIDs == '*' ? '' : " AND `groupID` IN ({$groupIDs})");
			$res = sql("SELECT `memberID`, `custom1`, `custom2`, `custom3`, `custom4` FROM `membership_users` {$where}", $eo);
			while($row = db_fetch_assoc($res))
				$users[] = [
					'username' => $row['memberID'],
					'custom' => [$row['custom1'], $row['custom2'], $row['custom3'], $row['custom4'], ],
				];

			$allowed['users'] = $users;
			return self::customAllowedRecipients($type, $allowed);
		}

		public static function recipientUserFormat() {
			$format = self::setting('recipient-user-format');
			if(!$format) $format = 'username';

			return str_replace(
				['username', 'custom1', 'custom2', 'custom3', 'custom4'],
				['${r.username}', '${r.custom[0] ?? \'\'}', '${r.custom[1] ?? \'\'}', '${r.custom[2] ?? \'\'}', '${r.custom[3] ?? \'\'}'],
				$format
			);
		}

		public static function getValidRecipients($strRecipients) {
			/* makes sure recipients are allowed by current user,
			 * and removes any non-allowed users,
			 * makes sure every user is a valid user,
			 * and removes invalid users
			 * makes sure every group is a valid group,
			 * and removes invalid groups
			 * expands groups into users 
			 * 
			 * finally returns whatever remaining recipients as an array of usernames
			 */
			$recipients = array_map('trim', explode(',', trim($strRecipients, ', ')));

			$valid = [];
			$eo = ['silentErrors' => true];

			// get anon user to exclude from recipients
			$anon = config('adminConfig')['anonymousMember'];

			// if recipients list includes __ALL_USERS__, get all users and skip further steps
			if(in_array('__ALL_USERS__', $recipients)) {
				$res = sql("SELECT `memberID` FROM `membership_users` WHERE `memberID` != '" . makeSafe($anon) . "'", $eo);
				while($row = db_fetch_assoc($res))
					$valid[] = $row['memberID'];

				return $valid;
			}

			foreach($recipients as $recipient)
				if(self::canSend($recipient)) {
					$m = [];
					if(preg_match('/group:(\d+)/', $recipient, $m)) {
						// get all users of given group id
						$res = sql("SELECT `memberID` FROM `membership_users` WHERE `groupID`='{$m[1]}'", $eo);
						while($row = db_fetch_assoc($res))
							$valid[] = $row['memberID'];
					} else
						$valid[] = $recipient;
				}

			// remove anon user if present and remove duplicates then return remaining
			$valid = array_diff($valid, [$anon]);
			return array_unique($valid);
		}

		public static function sendMessage($id) {
			$id = intval($id);
			$msg = self::getMyMessage($id);
			if(!$msg) return false;

			if(!strlen($msg['recipients']))
				return self::error("No recipients specified. Message saved in drafts.");

			if(!update('appgini_messages', [
				'draft' => 0,
				'sentTS' => time(), // we're setting sentTS here because we don't want
				                    // an email notification for the sender's copy
				                    // (email sender looks for messages with empty sentTS to notify recipients)
			], [
				'id' => $id, 'draft' => 1
			])) return self::error("Couldn't update message");

			// inReplyTo for recipient(s) should be set to the originalId of 
			// the previous message, whose id=inReplyTo
			$inReplyTo = 'NULL'; // by default
			if($sirt = intval($msg['inReplyTo']))
				$inReplyTo = sqlValue(
					"SELECT `originalId` FROM `appgini_messages`
					WHERE `id`='$sirt' AND `owner`='" . makeSafe($msg['owner']) . "'"
				// exception: if the sender is replying to a message that she's sent herself,
				// inReplyTo should be the id of the recipient's copy of the previous message
				) ?? sqlValue(
					"SELECT `id` FROM `appgini_messages`
					WHERE `originalId`='$sirt' AND `owner`='" . makeSafe($msg['recipients']) . "'"
				) ?? 'NULL'; // last resort of both above queries return nothing

			// for each recipient, create a recipient copy (recipients, owner)
			$copies = [];
			$fields = '(`originalId`,`createdTS`,`draft`,`inReplyTo`,`sender`,`subject`,`message`,`recipients`,`owner`)';
			$validRecipients = self::getValidRecipients($msg['recipients']);
			foreach($validRecipients as $recipient)
				$copies[] = '(' . implode(',', [
					$id, // originalId
					time(), // createdTS
					0, // draft
					$inReplyTo,
					"'" . makeSafe($msg['sender']) . "'",
					"'" . makeSafe($msg['subject']) . "'",
					"'" . makeSafe($msg['message']) . "'",
					"'" . makeSafe($recipient) . "'", // recipients
					"'" . makeSafe($recipient) . "'", // owner
				]) . ')';

			// insert recipient copies in batches of 200 records each
			$eo = ['silentErrors' => true];
			for($i = 0; $i < count($copies); $i += 200)
				sql("INSERT INTO `appgini_messages` {$fields} VALUES " .
					implode(', ', array_slice($copies, $i, 200)), $eo);

			// if message has only one recipient, and recipient is self, delete original
			// (sender's copy) to avoid seeing a duplicate message in the same inbox
			if($msg['sender'] == $msg['recipients']) self::delete([$id]);

			return true; // TODO: more comprehensive recipient copy checks?
		}

		public static function setting($key, $val = null) {
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

		/**
		 * Returns a message as given by provided id if it belongs to current user
		 */
		public static function getMyMessage($id) {
			$user = makeSafe(Authentication::getUser()['username']);
			$id = intval($id);

			// get message info
			$eo = ['silentErrors' => true];
			$res = sql("SELECT * FROM `appgini_messages` WHERE `id`='$id' AND `owner`='$user'", $eo);
			if(!($msg = db_fetch_assoc($res)))
				return self::error('Invalid message id');

			return $msg;
		}

		public static function readMessage($id) {
			// can be done only if owner of provided message id is current user
			$user = makeSafe(Authentication::getUser()['username']);
			$id = intval($id);

			// get message info
			$msg = self::getMyMessage($id);
			if(!$msg) return false; // error already reported by getMyMessage()

			// if seenTS already set, mark read if not already, then abort
			// also if sender is owner, meaning this is a 'sent' message
			if($msg['seenTS'] || $msg['sender'] == $msg['owner']) {
				if($msg['markedUnread'])
					update('appgini_messages', ['markedUnread' => 0], ['id' => $id]);
				return true;
			}

			// if this is sender's copy, don't update seenTS
			if($msg['originalId'] == $id || !$msg['originalId'])
				return self::error("Can't directly set sender's copy of a message as read");

			// set seenTS and mark read
			$now = time();
			$res = sql("UPDATE `appgini_messages` SET `seenTS`='$now', `markedUnread`=0 WHERE `id`='$id'", $eo);
			if(!$res) return self::error("Error updating seen timestamp");

			// also update seenTS of oringial message if current user is the only recipient
			$res = sql("UPDATE `appgini_messages` SET `seenTS`='$now'
				WHERE `id`='{$msg['originalId']}' AND `recipients`='$user' AND COALESCE(`seenTS`, 0) = 0", $eo);
			if(!$res) return self::error("Error updating seen timestamp for sender's copy of the message");

			return true;
		}

		public static function update($id, $data, $send = false) {
			// if message already sent, some data can't be updated so remove them

			return true;
		}

		public static function notifyByEmail($num = 5) {
			// if notify-by-email setting is off, abort
			if(!sqlValue("SELECT `value` FROM `appgini_messages_settings` WHERE `key`='notify-by-email'"))
				return;

			// get the next $num messages that have neither been seen by recipients
			// nor sent in email notifications
			$eo = ['silentErrors' => true];
			$messages = [];
			$res = sql("
				SELECT u.email, m.* FROM appgini_messages m
				LEFT JOIN membership_users u ON m.owner=u.memberID
				WHERE m.draft != 1 AND ISNULL(m.sentTS) AND ISNULL(m.seenTS) AND m.owner != m.sender
				ORDER BY m.createdTS LIMIT $num", $eo);
			while($row = db_fetch_assoc($res)) $messages[] = $row;

			// send email notification for each message
			$sentIds = [];
			foreach($messages as $msg) {
				$sent = sendmail([
					'to' => [[$msg['email'], $msg['owner']]],
					'subject' => "[New message] " . $msg['subject'],
					'message' => "A new message has arrived to your inbox from {$msg['sender']}\n\n" .
									application_url('plugins/messages')
				]);

				if($sent === true) $sentIds[] = $msg['id'];
			}

			if(!count($sentIds)) return;

			// flag sent messages to avoid duplicate email notifications
			$sentIds = implode(',', $sentIds);
			$ts = time();
			sql("UPDATE appgini_messages SET sentTS='$ts' WHERE id IN ($sentIds)", $eo);
		}

		/**
		 * Retrieves the value of 'ids' param from Request and makes sure it's a list of integers
		 *
		 * @param      bool  $asArray  if true, returns the processed ids as an array,
		 *                             else returns a string of ids, separated by commas, ready to use
		 *                             inside an `IN ()` SQL expression
		 *
		 * @return     mixed  array or string of processed ids (see @param above).
		 */
		public static function getRequestIds($asArray = false) {
			$ids = Request::val('ids');

			$ids = array_map('intval', array_map('trim', explode(',', $ids)));
			if($asArray) return $ids;

			return implode(',', $ids);
		}

		/**
		 * Returns the URI of the unread messages endpoint
		 * This is important to be able to send session cookies in ajax requests
		 */
		public static function unreadUri() {
			$url = application_url('plugins/messages/app-resources/ajax-unread.php');
			return preg_replace('/^https?:\/\/[^\/]+/', '', $url);
		}

		/**
		 * Determines if plugin tables are installed.
		 *
		 * @return     bool  True if installed, False otherwise.
		 */
		public static function dbInstalled() {
			$eo = ['silentErrors' => true, 'noErrorQueryLog' => true];
			$res1 = sql("SELECT COUNT(1) FROM `appgini_messages_settings`", $eo);
			$res2 = sql("SELECT COUNT(1) FROM `appgini_messages`", $eo);
			$res3 = sql("SELECT COUNT(1) FROM `appgini_messages_group_permissions`", $eo);
			return $res1 !== false
				&& $res2 !== false
				&& $res3 !== false;
		}
	}