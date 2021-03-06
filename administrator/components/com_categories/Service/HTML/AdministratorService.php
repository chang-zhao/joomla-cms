<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_categories
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Categories\Administrator\Service\HTML;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\Component\Categories\Administrator\Helper\CategoriesHelper;
use Joomla\Utilities\ArrayHelper;

/**
 * Administrator category HTML
 *
 * @since  3.2
 */
class AdministratorService
{
	/**
	 * Render the list of associated items
	 *
	 * @param   integer  $catid      Category identifier to search its associations
	 * @param   string   $extension  Category Extension
	 *
	 * @return  string   The language HTML
	 *
	 * @since   3.2
	 * @throws  \Exception
	 */
	public function association($catid, $extension = 'com_content')
	{
		// Defaults
		$html = '';

		// Get the associations
		if ($associations = CategoriesHelper::getAssociations($catid, $extension))
		{
			$associations = ArrayHelper::toInteger($associations);

			// Get the associated categories
			$db = Factory::getDbo();
			$query = $db->getQuery(true)
				->select('c.id, c.title')
				->select('l.sef as lang_sef')
				->select('l.lang_code')
				->from('#__categories as c')
				->where('c.id IN (' . implode(',', array_values($associations)) . ')')
				->where('c.id != ' . $catid)
				->join('LEFT', '#__languages as l ON c.language=l.lang_code')
				->select('l.image')
				->select('l.title as language_title');
			$db->setQuery($query);

			try
			{
				$items = $db->loadObjectList('id');
			}
			catch (\RuntimeException $e)
			{
				throw new \Exception($e->getMessage(), 500, $e);
			}

			if ($items)
			{
				$languages = LanguageHelper::getContentLanguages(array(0, 1));
				$content_languages = array_column($languages, 'lang_code');

				foreach ($items as &$item)
				{
					if (in_array($item->lang_code, $content_languages))
					{
						$text     = $item->lang_sef ? strtoupper($item->lang_sef) : 'XX';
						$url      = Route::_('index.php?option=com_categories&task=category.edit&id=' . (int) $item->id . '&extension=' . $extension);
						$tooltip  = '<strong>' . htmlspecialchars($item->language_title, ENT_QUOTES, 'UTF-8') . '</strong><br>'
							. htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8');
						$classes  = 'badge badge-secondary';

						$item->link = '<a href="' . $url . '" title="' . $item->language_title . '" class="' . $classes . '">' . $text . '</a>'
							. '<div role="tooltip" id="tip' . (int) $item->id . '">' . $tooltip . '</div>';
					}
					else
					{
						// Display warning if Content Language is trashed or deleted
						Factory::getApplication()->enqueueMessage(Text::sprintf('JGLOBAL_ASSOCIATIONS_CONTENTLANGUAGE_WARNING', $item->lang_code), 'warning');
					}
				}
			}

			$html = LayoutHelper::render('joomla.content.associations', $items);
		}

		return $html;
	}
}
