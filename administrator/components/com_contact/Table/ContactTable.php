<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_contact
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Contact\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

/**
 * Contact Table class.
 *
 * @since  1.0
 */
class ContactTable extends Table
{
	/**
	 * Indicates that columns fully support the NULL value in the database
	 *
	 * @var    boolean
	 * @since  4.0.0
	 */
	protected $_supportNullValue = true;

	/**
	 * Ensure the params and metadata in json encoded in the bind method
	 *
	 * @var    array
	 * @since  3.3
	 */
	protected $_jsonEncode = array('params', 'metadata');

	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  Database connector object
	 *
	 * @since   1.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->typeAlias = 'com_contact.contact';

		parent::__construct('#__contact_details', 'id', $db);

		$this->setColumnAlias('title', 'name');
	}

	/**
	 * Stores a contact.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success, false on failure.
	 *
	 * @since   1.6
	 */
	public function store($updateNulls = true)
	{
		// Transform the params field
		if (is_array($this->params))
		{
			$registry = new Registry($this->params);
			$this->params = (string) $registry;
		}

		$date   = Factory::getDate()->toSql();
		$userId = Factory::getUser()->id;

		if ($this->id)
		{
			// Existing item
			$this->modified_by = $userId;
			$this->modified    = $date;
		}
		else
		{
			// New contact. A contact created and created_by field can be set by the user,
			// so we don't touch either of these if they are set.
			if (!(int) $this->created)
			{
				$this->created = $date;
			}

			if (empty($this->created_by))
			{
				$this->created_by = $userId;
			}

			if (!(int) $this->modified)
			{
				$this->modified = $date;
			}

			if (empty($this->modified_by))
			{
				$this->modified_by = $userId;
			}
		}

		// Store utf8 email as punycode
		$this->email_to = PunycodeHelper::emailToPunycode($this->email_to);

		// Convert IDN urls to punycode
		$this->webpage = PunycodeHelper::urlToPunycode($this->webpage);

		// Verify that the alias is unique
		$table = Table::getInstance('ContactTable', __NAMESPACE__ . '\\', array('dbo' => $this->getDbo()));

		if ($table->load(array('alias' => $this->alias, 'catid' => $this->catid)) && ($table->id != $this->id || $this->id == 0))
		{
			$this->setError(Text::_('COM_CONTACT_ERROR_UNIQUE_ALIAS'));

			return false;
		}

		return parent::store($updateNulls);
	}

	/**
	 * Overloaded check function
	 *
	 * @return  boolean  True on success, false on failure
	 *
	 * @see     \JTable::check
	 * @since   1.5
	 */
	public function check()
	{
		try
		{
			parent::check();
		}
		catch (\Exception $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		$this->default_con = (int) $this->default_con;

		if (\JFilterInput::checkAttribute(array('href', $this->webpage)))
		{
			$this->setError(Text::_('COM_CONTACT_WARNING_PROVIDE_VALID_URL'));

			return false;
		}

		// Check for valid name
		if (trim($this->name) == '')
		{
			$this->setError(Text::_('COM_CONTACT_WARNING_PROVIDE_VALID_NAME'));

			return false;
		}

		// Generate a valid alias
		$this->generateAlias();

		// Check for valid category
		if (trim($this->catid) == '')
		{
			$this->setError(Text::_('COM_CONTACT_WARNING_CATEGORY'));

			return false;
		}

		// Sanity check for user_id
		if (!$this->user_id)
		{
			$this->user_id = 0;
		}

		// Check the publish down date is not earlier than publish up.
		if ((int) $this->publish_down > 0 && $this->publish_down < $this->publish_up)
		{
			$this->setError(Text::_('JGLOBAL_START_PUBLISH_AFTER_FINISH'));

			return false;
		}

		if (!$this->id)
		{
			// Hits must be zero on a new item
			$this->hits = 0;
		}

		/*
		 * Clean up keywords -- eliminate extra spaces between phrases
		 * and cr (\r) and lf (\n) characters from string.
		 * Only process if not empty.
		 */
		if (!empty($this->metakey))
		{
			// Array of characters to remove.
			$badCharacters = array("\n", "\r", "\"", '<', '>');

			// Remove bad characters.
			$afterClean = StringHelper::str_ireplace($badCharacters, '', $this->metakey);

			// Create array using commas as delimiter.
			$keys = explode(',', $afterClean);
			$cleanKeys = array();

			foreach ($keys as $key)
			{
				// Ignore blank keywords.
				if (trim($key))
				{
					$cleanKeys[] = trim($key);
				}
			}

			// Put array back together delimited by ", "
			$this->metakey = implode(', ', $cleanKeys);
		}
		else
		{
			$this->metakey = '';
		}

		// Clean up description -- eliminate quotes and <> brackets
		if (!empty($this->metadesc))
		{
			// Only process if not empty
			$badCharacters = array("\"", '<', '>');
			$this->metadesc = StringHelper::str_ireplace($badCharacters, '', $this->metadesc);
		}
		else
		{
			$this->metadesc = '';
		}

		if (empty($this->params))
		{
			$this->params = '{}';
		}

		if (empty($this->metadata))
		{
			$this->metadata = '{}';
		}

		// Set publish_up, publish_down to null if not set
		if (!$this->publish_up)
		{
			$this->publish_up = null;
		}

		if (!$this->publish_down)
		{
			$this->publish_down = null;
		}

		if (!$this->modified)
		{
			$this->modified = $this->created;
		}

		if (empty($this->modified_by))
		{
			$this->modified_by = $this->created_by;
		}

		return true;
	}

	/**
	 * Generate a valid alias from title / date.
	 * Remains public to be able to check for duplicated alias before saving
	 *
	 * @return  string
	 */
	public function generateAlias()
	{
		if (empty($this->alias))
		{
			$this->alias = $this->name;
		}

		$this->alias = ApplicationHelper::stringURLSafe($this->alias, $this->language);

		if (trim(str_replace('-', '', $this->alias)) == '')
		{
			$this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
		}

		return $this->alias;
	}
}
