<?php
/**
 * @package     Joomla.Installation
 * @subpackage  Model
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Installation\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installation\Helper\DatabaseHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserHelper;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Configuration setup model for the Joomla Core Installer.
 *
 * @since  3.1
 */
class ConfigurationModel extends BaseInstallationModel
{
	/**
	 * Method to setup the configuration file
	 *
	 * @param   array  $options  The session options
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.1
	 */
	public function setup($options)
	{
		// Get the options as an object for easier handling.
		$options = ArrayHelper::toObject($options);

		// Attempt to create the configuration.
		if (!$this->createConfiguration($options))
		{
			return false;
		}

		// Do the database init/fill
		$databaseModel = new DatabaseModel;

		// Create Db
		if (!$databaseModel->createDatabase($options))
		{
			$this->deleteConfiguration();

			return false;
		}

		$options->db_select = true;
		$options->db_created = 1;

		// Handle old db if exists
		if (!$databaseModel->handleOldDatabase())
		{
			$this->deleteConfiguration();

			return false;
		}

		// Create tables
		if (!$databaseModel->createTables($options))
		{
			$this->deleteConfiguration();

			return false;
		}

		// Attempt to create the root user.
		if (!$this->createRootUser($options))
		{
			$this->deleteConfiguration();

			return false;
		}

		// Install CMS data
		if (!$databaseModel->installCmsData())
		{
			$this->deleteConfiguration();

			return false;
		}

		return true;
	}

	/**
	 * Method to create the configuration file
	 *
	 * @param   \stdClass  $options  The session options
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.1
	 */
	public function createConfiguration($options)
	{
		$saveFtp = isset($options->ftp_save) && $options->ftp_save;

		// Create a new registry to build the configuration options.
		$registry = new Registry;

		// Site settings.
		$registry->set('offline', false);
		$registry->set('offline_message', Text::_('INSTL_STD_OFFLINE_MSG'));
		$registry->set('display_offline_message', 1);
		$registry->set('offline_image', '');
		$registry->set('sitename', $options->site_name);
		$registry->set('editor', 'tinymce');
		$registry->set('captcha', '0');
		$registry->set('list_limit', 20);
		$registry->set('access', 1);

		// Debug settings.
		$registry->set('debug', false);
		$registry->set('debug_lang', false);
		$registry->set('debug_lang_const', true);

		// Database settings.
		$registry->set('dbtype', $options->db_type);
		$registry->set('host', $options->db_host);
		$registry->set('user', $options->db_user);
		$registry->set('password', $options->db_pass_plain);
		$registry->set('db', $options->db_name);
		$registry->set('dbprefix', $options->db_prefix);
		$registry->set('dbencryption', 0);
		$registry->set('dbsslverifyservercert', false);
		$registry->set('dbsslkey', '');
		$registry->set('dbsslcert', '');
		$registry->set('dbsslca', '');
		$registry->set('dbsslcapath', '');
		$registry->set('dbsslcipher', '');

		// Server settings.
		$registry->set('live_site', '');
		$registry->set('secret', UserHelper::genRandomPassword(16));
		$registry->set('gzip', false);
		$registry->set('error_reporting', 'default');
		$registry->set('helpurl', $options->helpurl);
		$registry->set('ftp_host', $options->ftp_host ?? '');
		$registry->set('ftp_port', isset($options->ftp_host) ? $options->ftp_port : '');
		$registry->set('ftp_user', ($saveFtp && isset($options->ftp_user)) ? $options->ftp_user : '');
		$registry->set('ftp_pass', ($saveFtp && isset($options->ftp_pass)) ? $options->ftp_pass : '');
		$registry->set('ftp_root', ($saveFtp && isset($options->ftp_root)) ? $options->ftp_root : '');
		$registry->set('ftp_enable', (isset($options->ftp_host) && null === $options->ftp_host) ? $options->ftp_enable : 0);

		// Locale settings.
		$registry->set('offset', 'UTC');

		// Mail settings.
		$registry->set('mailonline', true);
		$registry->set('mailer', 'mail');
		$registry->set('mailfrom', $options->admin_email);
		$registry->set('fromname', $options->site_name);
		$registry->set('sendmail', '/usr/sbin/sendmail');
		$registry->set('smtpauth', false);
		$registry->set('smtpuser', '');
		$registry->set('smtppass', '');
		$registry->set('smtphost', 'localhost');
		$registry->set('smtpsecure', 'none');
		$registry->set('smtpport', 25);

		// Cache settings.
		$registry->set('caching', 0);
		$registry->set('cache_handler', 'file');
		$registry->set('cachetime', 15);
		$registry->set('cache_platformprefix', false);

		// Meta settings.
		$registry->set('MetaDesc', '');
		$registry->set('MetaKeys', '');
		$registry->set('MetaTitle', true);
		$registry->set('MetaAuthor', true);
		$registry->set('MetaVersion', false);
		$registry->set('robots', '');

		// SEO settings.
		$registry->set('sef', true);
		$registry->set('sef_rewrite', false);
		$registry->set('sef_suffix', false);
		$registry->set('unicodeslugs', false);

		// Feed settings.
		$registry->set('feed_limit', 10);
		$registry->set('feed_email', 'none');

		$registry->set('log_path', JPATH_ADMINISTRATOR . '/logs');
		$registry->set('tmp_path', JPATH_ROOT . '/tmp');

		// Session setting.
		$registry->set('lifetime', 15);
		$registry->set('session_handler', 'database');
		$registry->set('shared_session', false);
		$registry->set('session_metadata', true);

		// Generate the configuration class string buffer.
		$buffer = $registry->toString('PHP', array('class' => 'JConfig', 'closingtag' => false));

		// Build the configuration file path.
		$path = JPATH_CONFIGURATION . '/configuration.php';

		// Determine if the configuration file path is writable.
		if (file_exists($path))
		{
			$canWrite = is_writable($path);
		}
		else
		{
			$canWrite = is_writable(JPATH_CONFIGURATION . '/');
		}

		/*
		 * If the file exists but isn't writable OR if the file doesn't exist and the parent directory
		 * is not writable we need to use FTP.
		 */
		$useFTP = false;

		if ((file_exists($path) && !is_writable($path)) || (!file_exists($path) && !is_writable(dirname($path) . '/')))
		{
			return false;

			// $useFTP = true;
		}

		// Enable/Disable override.
		if (!isset($options->ftpEnable) || ($options->ftpEnable != 1))
		{
			$useFTP = false;
		}

		// Get the session
		$session = Factory::getSession();

		if ($canWrite)
		{
			file_put_contents($path, $buffer);
			$session->set('setup.config', null);
		}
		else
		{
			// If we cannot write the configuration.php, setup fails!
			return false;
		}

		return true;
	}

	/**
	 * Method to create the root user for the site.
	 *
	 * @param   object  $options  The session options.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.1
	 */
	private function createRootUser($options)
	{
		// Get a database object.
		try
		{
			$db = DatabaseHelper::getDbo(
				$options->db_type,
				$options->db_host,
				$options->db_user,
				$options->db_pass_plain,
				$options->db_name,
				$options->db_prefix
			);
		}
		catch (\RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage(Text::sprintf('INSTL_ERROR_CONNECT_DB', $e->getMessage()), 'error');

			return false;
		}

		$cryptpass = UserHelper::hashPassword($options->admin_password_plain);

		// Take the admin user id - we'll need to leave this in the session for sample data install later on.
		$userId = DatabaseModel::getUserId();

		// Create the admin user.
		date_default_timezone_set('UTC');
		$installdate = date('Y-m-d H:i:s');

		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__users'))
			->where($db->quoteName('id') . ' = ' . $db->quote($userId));

		$db->setQuery($query);

		try
		{
			$result = $db->loadResult();
		}
		catch (\RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

			return false;
		}

		if ($result)
		{
			$query->clear()
				->update($db->quoteName('#__users'))
				->set($db->quoteName('name') . ' = ' . $db->quote(trim($options->admin_user)))
				->set($db->quoteName('username') . ' = ' . $db->quote(trim($options->admin_username)))
				->set($db->quoteName('email') . ' = ' . $db->quote($options->admin_email))
				->set($db->quoteName('password') . ' = ' . $db->quote($cryptpass))
				->set($db->quoteName('block') . ' = 0')
				->set($db->quoteName('sendEmail') . ' = 1')
				->set($db->quoteName('registerDate') . ' = ' . $db->quote($installdate))
				->set($db->quoteName('lastvisitDate') . ' = NULL')
				->set($db->quoteName('activation') . ' = ' . $db->quote('0'))
				->set($db->quoteName('params') . ' = ' . $db->quote(''))
				->where($db->quoteName('id') . ' = ' . $db->quote($userId));
		}
		else
		{
			$columns = array(
				$db->quoteName('id'),
				$db->quoteName('name'),
				$db->quoteName('username'),
				$db->quoteName('email'),
				$db->quoteName('password'),
				$db->quoteName('block'),
				$db->quoteName('sendEmail'),
				$db->quoteName('registerDate'),
				$db->quoteName('lastvisitDate'),
				$db->quoteName('activation'),
				$db->quoteName('params')
			);
			$query->clear()
				->insert('#__users', true)
				->columns($columns)
				->values(
					$db->quote($userId) . ', ' . $db->quote(trim($options->admin_user)) . ', ' . $db->quote(trim($options->admin_username)) . ', ' .
					$db->quote($options->admin_email) . ', ' . $db->quote($cryptpass) . ', ' .
					$db->quote('0') . ', ' . $db->quote('1') . ', ' . $db->quote($installdate) . ', NULL, ' .
					$db->quote('0') . ', ' . $db->quote('')
				);
		}

		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (\RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

			return false;
		}

		// Map the super user to the Super Users group
		$query->clear()
			->select($db->quoteName('user_id'))
			->from($db->quoteName('#__user_usergroup_map'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		$db->setQuery($query);

		if ($db->loadResult())
		{
			$query->clear()
				->update($db->quoteName('#__user_usergroup_map'))
				->set($db->quoteName('user_id') . ' = ' . $db->quote($userId))
				->set($db->quoteName('group_id') . ' = 8');
		}
		else
		{
			$query->clear()
				->insert($db->quoteName('#__user_usergroup_map'), false)
				->columns(array($db->quoteName('user_id'), $db->quoteName('group_id')))
				->values($db->quote($userId) . ', 8');
		}

		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (\RuntimeException $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

			return false;
		}

		return true;
	}

	/**
	 * Method to erase the configuration file.
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	private function deleteConfiguration()
	{
		// The configuration file path.
		$path = JPATH_CONFIGURATION . '/configuration.php';

		if (file_exists($path))
		{
			unlink($path);
		}
	}
}
