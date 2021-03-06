<?php

/**
 * =================================================================================
 *
 * VIZU CMS
 * Module: Admin API / User
 *
 * =================================================================================
 */

if (IN_ADMIN_API !== true) {
	die('This file can be loaded only in admin module');
}

switch ($router->getRequestChunk(2)) {

	/** ----------------------------------------------------------------------------
	 * Add user
	 */

	case 'login':
		try {
			$user->login($_POST['email'] ?? '', $_POST['password'] ?? '');
		}
		catch (Exception $e) {
			$rest_store->set('message', $e->getMessage());
		}
		$rest_store->set('user-access', $user->getAccess());

		break;


	/** ----------------------------------------------------------------------------
	 * Logout user
	 */

	case 'logout':
		$user->logout();
		$rest_store->set('user-access', $user->getAccess());

		break;


	/** ----------------------------------------------------------------------------
	 * List users
	 */

	case 'list':
		$admin_actions->requireAdminAccessRights();

		$query = $db->query('SELECT * FROM `users`');
		$result = $db->fetchAll($query);
		$users_list = [];

		foreach ($result as $user_data) {
			$users_list[] = [
				'id' => $user_data['id'],
				'email' => $user_data['email']
			];
		}

		$rest_store->set('users-list', $users_list);

		break;


	/** ----------------------------------------------------------------------------
	 * Add user
	 */

	case 'add':
		$admin_actions->requireAdminAccessRights();

		$email = $_POST['email'] ?? null;
		$contact_config = $theme_config['contact'];

		// Validate entered email address
		if (empty($email) || !User::verifyUsername($email)) {
			$rest_store->set('message', 'Provided email address is missing or incorrect');
			break;
		}

		// Check if email address already exists
		$result = $db->query("SELECT * FROM `users` WHERE `email` = '{$email}'");
		$user_found = $db->fetchAll($result);
		if ($user_found) {
			$rest_store->set('message', 'Account with provided email address already exists');
			break;
		}


		// Set sender email address as theme contact form main recipient
		if ($contact_config['default_recipient']) {
			$user_id = $contact_config['default_recipient'];

			// Get email addres of contact user that was set in configuration
			$result  = $db->query("SELECT `email` FROM `users` WHERE `id` = '{$user_id}'");
			$fetched = $db->fetchAll($result);

			if (!$fetched) {
				$rest_store->set('message', "Configured default sender/receiver '{$user_id}' does not exist. Admin acount creation failed.");
				break;
			}
			$contact_user_email = $fetched[0]['email'];
		}

		$generated_password = User::generatePassword();
		$content_fields = [
			'Message'      => 'Administrator account created. It is strongly recomended to change your password now.',
			'Page address' => $router->site_path,
			'Login'        => $email,
			'Password'     => $generated_password
		];

		// Send notification to user about account creation
		try {
			$notifier = new Notifier($contact_config);
			$notifier->notify(
				"[{$router->domain}] Account registration", // Subject
				$notifier->prepareBodyWithTable($content_fields, $lang->getActiveLangCode()), // Body
				$email // Recipient
			);
		}
		catch (Exception $e) {
			$rest_store->set('message', "Failed to send account creation notification. Error thrown: '{$e->getMessage()}'");
			$rest_store->set('trace', $e->getTrace());
			break;
		}

		// Add user to database
		$query = $db->query("INSERT INTO `users` (email, password) VALUES ('{$email}', '" . User::passwordEncode($generated_password). "')");
		$rest_store->set('message', "Administrator account with email address {$email} has been created.");

		break;


	/** ----------------------------------------------------------------------------
	 * Add user
	 */

	case 'delete':
		$rest_store->set('success', false);
		break;


	/** ----------------------------------------------------------------------------
	 * Password change
	 */

	case 'password-change':
		$admin_actions->requireAdminAccessRights();

		$rest_store->set('success', false);

		$error_msg        = null;
		$password_current = $_POST['password_current'] ?? '';
		$password_new_1   = $_POST['password_new_1'] ?? '';
		$password_new_2   = $_POST['password_new_2'] ?? '';

		if (empty($password_current)) {
			$error_msg = 'Current password not provided';
		}
		elseif (empty($password_new_1)) {
			$error_msg = 'New password not provided';
		}
		elseif ($password_new_1 !== $password_new_2) {
			$error_msg = 'New passwords does not match';
		}
		elseif ($password_current == $password_new_1) {
			$error_msg = 'New password should be different than previous one';
		}
		elseif (strlen($password_new_1) < 5) {
			$error_msg = 'New password should have at least 5 characters';
		}

		if ($error_msg) {
			$rest_store->set('message', $error_msg);
			return;
		}


		// Check if entered current password is correct

		$result = $db->query("SELECT `password` FROM `users` WHERE `id` = '{$user->getId()}' LIMIT 1");
		$user_data = $db->fetchAll($result);

		if (isset($user_data[0]['password']) && $user_data[0]['password'] !== User::passwordEncode($password_current)) {
			$rest_store->set('message', 'Provided current password is not correct');
			return;
		}


		// Save new password

		$new_password = User::passwordEncode($password_new_1);
		$result = $db->query("UPDATE `users` SET `password` = '{$new_password}' WHERE `id` = '{$user->getId()}' LIMIT 1");

		if ($result) {
			$rest_store->set('message', 'Password changed');
			$rest_store->set('success', true);
			$_SESSION['password'] = $new_password;
		}
		else {
			$rest_store->set('message', 'Password change failed');
		}

		break;


	/** ----------------------------------------------------------------------------
	 * Password recovery
	 */

	case 'password-recovery':
		$email = $_POST['email'] ?? '';

		if (!User::verifyUsername($_POST['email'])) {
			$rest_store->set('message', "Provided email address is not valid.");
			return;
		}

		$result = $db->query("SELECT `id`, `email` FROM `users` WHERE `email` = '{$email}'");
		$user_data = $db->fetchAll($result);

		if (count($user_data) != 1) {
			return;
		}

		$user_notified  = false;
		$new_password   = User::generatePassword();
		$content_fields = [
			'Message' => "You have requested password recovery to your administration panel. Here is your new password: <strong>{$new_password}</strong>. Please use it to log in and change it as soon as possible.",
			'Page address' => $router->site_path,
		];

		try {
			$notifier = new Notifier($theme_config['contact'] ?? []);
			$notifier->notify(
				'[' . Config::$SITE_NAME . '] Password recovery request', // Subject
				$notifier->prepareBodyWithTable($content_fields, $lang->getActiveLangCode()), // Body
				$user_data[0]['email'] // Recipient
			);
			$user_notified = true;
		}
		catch (Exception $e) {
			$rest_store->set('message', "Password recvery process failed - system could not send email notificatios. Returned error: {$e->getMessage()}");
		}

		if ($user_notified) {
			$result = $db->query("UPDATE `users` SET `password` = '{$new_password}' WHERE `id` = '{$user_data[0]['email']}' LIMIT 1");
		}

		/**
		 * Display message about password recovery process even if provided email
		 * address does not exist in database to prevent email sniffing.
		 */
		$rest_store->set('message', "Password recovery process started. If you have provided existing email address you will get further instructions.");

		break;
}