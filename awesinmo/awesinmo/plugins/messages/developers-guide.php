<?php
	include_once(__DIR__ . '/messages.php');
	include(__DIR__ . '/header.php');

	echo $messages->header_nav();
	echo $messages->breadcrumb([
		'../../' => $Translation["user's area"],
		'./' => 'Messages',
		'settings.php' => '<span class="language" data-key="settings">Settings</span>',
		'' => 'Developers\' guide'
	]); ?>

<div class="row">
	<div class="col-sm-6 col-md-4 toc hidden-xs">
		<div class="well" style="position: fixed;">
			<a href="#sending-messages-from-hooks">Sending messages from hooks</a>
			<a href="#including-data">Including data in the message</a>
			<a href="#sending-message-to-entire-group">Sending a message to an entire group</a>
			<a href="#sending-message-to-multiple-users">Sending a message to multiple users/groups</a>
			<a href="#removing-sent-message">Removing sent message from sender's Sent folder</a>
			<a href="#error-handling">Error handling</a>
			<a href="#starting-new-message-from-link">Starting a new message from a link</a>
			<hr>
			<a href="#customize-list-of-allowed-recipients">Customize the list of allowed recipients</a>
		</div>
	</div>
	<div class="col-sm-6 col-md-8 col-lg-7">
		<h2 id="sending-messages-from-hooks">Sending messages from hooks</h2>
			<p>
				The messages plugin provides a PHP API for automating messages.
				You can use <a href="https://bigprof.com/appgini/help/advanced-topics/hooks/">hook functions</a>
				to programmatically send messages to users through the messages plugin, and have those messages
				devliverd to their inboxes, where they can view, reply and/or interact with it just
				like manually-sent messages.
			</p>
			<p>
				To send a message, you need to first save it as a draft by calling <code>MessagesDB::createMessage()</code>.
				Then, you should call <code>MessagesDB::sendMessage()</code> to actually send the message.
				Here is a basic example:
			</p>

			<pre data-tabs="4">
				/* Step 1: draft a message */
				$id = MessagesDB::createMessage([
					'recipients' => 'jack',
					'subject' => 'Automated message',
					'message' => 'Hello! This is a test automated message!'
				]);

				/* Step 2: send it! */
				MessagesDB::sendMessage($id);
			</pre>

			<p>
				The above code would send a message to the user (recipient) named <i>jack</i>,
				with the specified subject line and message content.
			</p>
			
			<div class="alert alert-warning">
				Note that the sender
				of the message is set as the currently signed-in user. If that user has
				no permission to send messages to the specified recipients, the message will
				not be delivered. Allowed recipients can be configured through the
				<a href="settings.php" class="alert-link">messages settings page</a>
				(in the <i>Messaging permissions</i> section).
			</div>

		<h2 id="including-data">Including data in the message</h2>
			<p>
				A typical use case of automated messages is to notify users when a record is added
				or changed, in order for the recipient user to take some action. For example,
				we might want to notify 'jack' from inventory to pack items of a new order when the
				order is added to the database.
			</p>
			<p>
				We can do this by adding the code for sending the message into the 
				<a href="https://bigprof.com/appgini/help/advanced-topics/hooks/table-specific-hooks#tablename_after_insert">after_insert hook</a>
				for the orders table. This hook is triggered whenever a new record is added
				to the orders table. We can retrieve the new record data from the 
				<code>$data</code> array and include it in the message. In the example below,
				we retrieve the order ID from <code>$data['OrderID']</code>
				and include it in the message: 
			</p>

			<pre data-tabs="4">
				function orders_after_insert($data, $memberInfo, &$args) {
					/* Step 1: draft a message */
					$id = MessagesDB::createMessage([
						'recipients' => 'jack',
						'subject' => "Please prepare the package for order {$data['OrderID']}",
						'message' => "Hi Jack!\n" .
						             "Order {$data['OrderID']} has just been placed.\n" .
						             "Please prepare and package it, then update its status to 'Packaged'"
					]);

					/* Step 2: send it! */
					MessagesDB::sendMessage($id);

					return true;
				}
			</pre>

			<p>
				The above code assumes that the table name is 'orders'. This hook function should be
				placed into the generated <code>hooks/orders.php</code> file. The file already includes
				an empty <code>orders_after_insert()</code> function, so the first and last line in
				the example above should not be copied to the file as they already exist in it.
				Copy only the lines between the first and last line.
			</p>
			
		<h2 id="sending-message-to-entire-group">Sending a message to an entire group</h2>
			<p>
				The messages plugin allows authorized users to send a message to entire group
				rather than a single user. To do so, set the <code>recipients</code> parameter to
				<code>group:{group-name}</code>. For example:
			</p>

			<pre data-tabs="4">
				/* Step 1: draft a message */
				$id = MessagesDB::createMessage([
					/* send the message to all users of the accountants group */
					<b>'recipients' => 'group:accountants',</b>
					'subject' => 'New order',
					'message' => 'Please process the new order!'
				]);

				/* Step 2: send it! */
				MessagesDB::sendMessage($id);
			</pre>

		<h2 id="sending-message-to-multiple-users">Sending a message to multiple users/groups</h2>
			<p>
				To send a message to multiple recipients (which can be multiple users and/or groups),
				set the <code>recipients</code> parameter to an array of usernames/groups,
				like so:
			</p>

			<pre data-tabs="4">
				/* Step 1: draft a message */
				$id = MessagesDB::createMessage([
					/* send the message to all users of the accountants group,
					the sales group, plus jack and dorothy */
					<b>'recipients' => [
						'group:accountants',
						'group:sales',
						'jack',
						'dorothy'
					],</b>
					'subject' => 'New order',
					'message' => 'Please process the new order!'
				]);

				/* Step 2: send it! */
				MessagesDB::sendMessage($id);
			</pre>

		<h2 id="removing-sent-message">Removing sent message from sender's Sent folder</h2>
			<p>
				When you send a message programmatically, you're sending it on behalf of the
				currently signed-in user. This means that the message would appear in that
				user's Sent folder. This might be confusing to the user since she didn't
				actually send that message (she merely triggered sending it programmatically).
				In that case, it might be a good idea to remove the message from the user's
				Sent folder after sending it. This can be done by calling
				<code>MessagesDB::delete()</code>, like so:
			</p>

			<pre data-tabs="4">
				/* Step 1: draft a message */
				$id = MessagesDB::createMessage([
					'recipients' => 'jack',
					'subject' => 'Test message',
					'message' => 'Jack can see this message, but the sender can not!'
				]);

				/* Step 2: send it! */
				MessagesDB::sendMessage($id);

				/* Step 3: delete the sender's copy.
				Note the square brackets around $id. This is necessary
				because this function accepts an array of ids. */
				<b>MessagesDB::delete([$id]);</b>
			</pre>

		<h2 id="error-handling">Error handling</h2>
			<p>
				When sending a message programatically, you should check for errors
				and handle them appropriately. Errors can occur for many reasons. For example:
			</p>

			<ul>
				<li> Currently signed-in user can't send messages to specified recipient(s).</li>
				<li> Subject line is empty.</li>
				<li> Can't save draft message to the database.</li>
			</ul>

			<p>
				So, we highly recommend that you check for errors and handle them during each step
				of sending messages. When an error occurs in any step, the called function returns
				false. In that case, you can retriebe the error message from
				<code>MessagesDB::lastError()</code> and handle the error appropriately.
			</p>

			<p>
				The example below is similar to the code we used in the previous section,
				except that we've added error checking to it:
			</p>

			<pre data-tabs="4">
				/* Step 1: draft a message */
				$id = MessagesDB::createMessage([
					'recipients' => 'jack',
					'subject' => 'Test message',
					'message' => 'Jack can see this message, but the sender can not!'
				]);

				// check for and handle errors while creating a draft
				if(!$id) {
					$error = MessagesDB::lastError();

					// handle the error somehow

					// abort the current hook function
					return true;
				}

				/* Step 2: send message and check for erros */
				if(!MessagesDB::sendMessage($id)) {
					$error = MessagesDB::lastError();

					// handle the error somehow

					// abort the current hook function
					return true;
				}

				/* Step 3: delete the sender's copy and check for errors */
				if(!MessagesDB::delete([$id])) {
					$error = MessagesDB::lastError();

					// handle the error somehow

					// abort the current hook function
					return true;
				}
			</pre>

		<h2 id="starting-new-message-from-link">Starting a new message from a link</h2>
			<p>
				Instead of sending a message in the background on behalf of a user,
				sometimes you might want the user to trigger sending manually by clicking
				a link or a button.
			</p><p>
				For example, if you want users to be able to share
				details of a record, you could add a 'share' button in the detail view of
				the concerned table. When a user clicks the button, a new message window
				is opened, with the message pre-populated with the record details. The user
				can then specify a recipient and click 'Send'.
			</p>
			<p>
				You can do this by linking to <a href="<?php echo application_url('plugins/messages/'); ?>?compose=1" target="_blank">plugins/messages/?compose=1</a>.
			</p>
			
			<h3>Supported parameters</h3>
				<p>
					There following optional parameters can be included in the new message link
					to pre-populate the message body, the subject and/or the recipients list:
				</p>
				<ul>
					<li> <code>subject=example</code> to prepopulate the subject line. Example: <a href="<?php echo application_url('plugins/messages/'); ?>?compose=1&subject=Example%20subject" target="_blank">plugins/messages/?compose=1&subject=Example+subject</a>.</li>

					<li> <code>message=example</code> to prepopulate the message body. Example: <a href="<?php echo application_url('plugins/messages/'); ?>?compose=1&message=Example%20message" target="_blank">plugins/messages/?compose=1&message=Example+message</a>.</li>

					<li> <code>to=recipient</code> to prepopulate the recipients list. To send the message to multiple recipients, separate recipients with a comma. Example: <a href="<?php echo application_url('plugins/messages/'); ?>?compose=1&to=jack,mary" target="_blank">plugins/messages/?compose=1&to=jack,mary</a>. This will set recipients to jack and mary, assuming these are valid usernames.</li>
				</ul>
				<p>
					You can include multiple parameters in the same link. For example, this link would set the subject, message and recipients:
					<a href="<?php echo application_url('plugins/messages/'); ?>?compose=1&to=jack,mary&subject=Example+subject&message=Example+message" target="_blank">plugins/messages/?compose=1&to=jack,mary&subject=Example+subject&message=Example+message</a>
				</p>

			<h3>Example: Adding a 'Share record' button in the detail view</h3>
				<p>
					Let's assume you have an orders table, and you'd like your application
					users to be able to click a button in the orders form to share the details
					of the order in a message and discuss it.
				</p>
				<p>
					To add the button, we can use the <code>tablename-dv.js</code> hook file.
					For the orders table, the exact file name would be <code>orders-dv.js</code>
					and we should create it inside the <code>hooks</code> if it doesn't already exist.
					Using any text editor, add this code to the file:
				</p>
				<pre data-tabs="5">
					$j(() => {
					  const orderId = $j('[name=SelectedID]').val();

					  // show share button only if this is an existing order
					  if(!orderId) return;

					  $j('&lt;a class="btn btn-info hspacer-lg">&lt;/a>')
					    .html('&lt;i class="glyphicon glyphicon-share">&lt;/i> Share')
					    .attr('href', 'plugins/messages/?compose=1&subject=Order+Num+' + orderId)
					    .attr('target', '_blank')
					    .appendTo('.page-header h1')
					})
				</pre>
				<p>And here is a video showing the above example in action (note the 'Share' button that opens a new message when clicked, with the subject line pre-populated with the order number):</p>

				<video controls style="width: 100%; max-width: 638px;">
					<source src="app-resources/appgini-messages-plugin-link-to-share-record.mp4" type="video/mp4">
					Your browser doesn't support embedded videos :/
				</video>

		<h2 id="customize-list-of-allowed-recipients">Customize the list of allowed recipients</h2>
			<div class="alert alert-warning">This was added in Messages plugin 1.2</div>

			<h3>The format of displayed recipients</h3>
			<p>
				When you start composing a new message, the default recipients list
				displays the usernames of available recipients. This might not be very
				useful in some cases. Displaying recipient name for example would be
				more helpful. The <a href="settings.php">Settings page</a> includes
				a <b>Format of recipients list</b> option that allows you to customize
				how recipients are displayed in the recipients list.
			</p>

			<p><figure>
				<img src="https://cdn.bigprof.com/images/messages/settings-format-of-recipients-list.png" class="img-thumbnail">
				<figcaption>The <b>Format of recipients list</b> setting in the Settings page.</figcaption>
			</figure></p>

			<p>
				The recipient data is obtained from the users' custom fields that you can configure
				in the <a href="../../admin/pageSettings.php" target="_blank">admin settings page</a>
				(under the Signup tab). Setting the format similar to the screenshot above would
				display a recipients drop-down like the figure below
				(assuming <code>custom1</code> is the full name of the user, which is the default config):
			</p>

			<p><figure>
				<img src="https://cdn.bigprof.com/images/messages/compose-message-custom-recipients-list.png" class="img-thumbnail">
				<figcaption>The recipients list after configuring it to display the full name of each recipient.</figcaption>
			</figure></p>
			
			<h3>The content of the recipients list</h3>
			<p>
				You can control the content of the recipients list that users
				see when composing a new message. This is very useful when you
				want to limit the recipients a user can select to send messages
				to. This can be done using the <code>plugin_messages_listAllowedRecipients()</code>
				hook function, which you can define in the <code>hooks/__global.php</code> hook
				file. This is the basic defintion of the function:
			</p>

			<pre data-tabs="4">
				function plugin_messages_listAllowedRecipients($type, $defaultRecipients) {
				    return $defaultRecipients;
				}
			</pre>

			<h4>Usage:</h4>

			<ul>
				<li> <code>$type</code> is a string that contains either <code>groups</code> or <code>users</code>.</li>
				<li>
					In case <code>$type</code> is groups, the format of <code>$defaultRecipients</code> is <code>['groupID' => 'group name', ...]</code>. For example:
					<pre data-tabs="6">
						[
						    '2' => 'Admins',
						    '4' => 'Sales',
						    '5' => 'Customer support'
						]
					</pre>
				</li>
				<li>
					In case <code>$type</code> is users, <code>$defaultRecipients</code> looks like this example:
					<pre data-tabs="6">
						[
						   [
						     'username' => 'john.doe',
						     'custom' => ['John Doe', '10 Wall St.', 'New York', 'NY']
						   ],
						   [
						     'username' => 'jane.doe',
						     'custom' => ['Jane Doe', '120 Grapes Ave.', 'Austin', 'TX']
						   ],
						   ...
						]
					</pre>
				</li>
				<li>
					In both cases, <code>plugin_messages_listAllowedRecipients</code> should return a transformed array of the same format of the provided <code>$defaultRecipients</code> array.
				</li>
				<li>
					The transformation would usually be removing recipients from the <code>$defaultRecipients</code> array, or simply returning an empty array (meaning no recipients allowed). The returned array is used to populate the allowed recipients list.
				</li>
				<li>
					Returning <code>$defaultRecipients</code> as-is without any modification,
					like in the above example hook, would display the default recipients list
					for the current user without any changes.
				</li>
				<li>
					<span class="label label-danger">Important!</span> you must return an array (either
					empty or populated) if using this hook function. Otherwise, users would
					see errors.
				</li>
			</ul>

			<h4 class="text-warning">Warning!</h4>
			<p>
				This hook function controls the <i>displayed</i> recipients list that
				a user can see. It <i>doesn't</i> change the server-side validation
				of recipients. If a user is not allowed to send a message to some recipient
				but this function causes displaying that recipient to the current user, the
				user will still be unable to send a message to that recipient.
			</p>
			<p>
				Similarly, if a user is allowed to send a message to some recipient
				and this function hides that recipient from the list, the user might
				still be able to send a message to that recipient if she manipulates
				the message data (for example via the browser console).
			</p>
	</div>
</div>

<script>
	$j(function() {
		$j('pre').each((i, e) => {
			const pre = $j(e), tabs = pre.data('tabs');
			if(!tabs) return;

			const regex = new RegExp(`(\n?)\t{${tabs}}`, 'g');
			pre.html(pre.html().replace(regex, '$1').trim());
		})

		// prevent toc from overlapping with content
		const resizeToc = () => { $j('.well').css({maxWidth: $j('.toc').innerWidth() - 20 }) }
		$j(window).resize(resizeToc);
		resizeToc();
	})
</script>

<style>
	.toc { padding: 2em 0; }
	.toc a { display: block; }
	/* offset anchors to compensate for fixed header  */
	h2:before {
		content: '';
		display: block;
		position: relative;
		width: 0;
		height: 2.5em;
		margin-top: -1.5em;
	}
</style>

<?php include(__DIR__ . '/footer.php');
