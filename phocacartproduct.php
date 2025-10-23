<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Database\DatabaseQuery;
use Joomla\Registry\Registry;
use Joomla\Component\Finder\Administrator\Indexer\Helper;


defined('_JEXEC') or die;
//require_once JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/adapter.php';

if (file_exists(JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/bootstrap.php')) {
	// Joomla 5 and newer
	require_once(JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/bootstrap.php');
} else {
	// Joomla 4
	JLoader::registerPrefix('Phocacart', JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/phocacart');
}

class PlgFinderPhocacartproduct extends Adapter
{
	protected $context 			= 'Phocacartitem';
	protected $extension 		= 'com_phocacart';
	protected $layout 			= 'item';
	protected $type_title 		= 'Phoca Cart';
	protected $table 			= '#__phocacart_products';
	protected $autoloadLanguage = true;


	/*public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}*/

	public function onFinderCategoryChangeState($extension, $pks, $value)
	{
		if ($extension == 'com_phocacart')
		{
			$this->categoryStateChange($pks, $value);
		}
	}

	public function onFinderAfterDelete($context, $table)
	{

		if ($context == 'com_phocacart.phocacartitem')
		{
			$id = $table->id;
		}
		elseif ($context == 'com_finder.index')
		{
			$id = $table->link_id;
		}
		else
		{
			return true;
		}
		// Remove the items.
		return $this->remove($id);
	}

	public function onFinderAfterSave($context, $row, $isNew)
	{
		// We only want to handle web links here. We need to handle front end and back end editing.
		if ($context == 'com_phocacart.phocacartitem' || $context == 'com_phocacart.item' || $context == 'com_phocacart.product' )
		{
			// Check if the access levels are different
			if (!$isNew && $this->old_access != $row->access)
			{
				// Process the change.
				$this->itemAccessChange($row);
			}

			// Reindex the item
			$this->reindex($row->id);
		}

		// Check for access changes in the category
		if ($context == 'com_phocacart.phocacartcategory')
		{
			// Check if the access levels are different
			if (!$isNew && $this->old_cataccess != $row->access)
			{
				$this->categoryAccessChange($row);
			}
		}

		return true;
	}


	public function onFinderBeforeSave($context, $row, $isNew)
	{
		// We only want to handle web links here
		if ($context == 'com_phocacart.phocacartproduct' || $context == 'com_phocacart.product' )
		{
			// Query the database for the old access level if the item isn't new
			if (!$isNew)
			{
				$this->checkItemAccess($row);
			}
		}

		// Check for access levels from the category
		if ($context == 'com_phocacart.phocacartcategory')
		{
			// Query the database for the old access level if the item isn't new
			if (!$isNew)
			{
				$this->checkCategoryAccess($row);
			}
		}

		return true;
	}

	public function onFinderChangeState($context, $pks, $value)
	{
		// We only want to handle web links here
		if ($context == 'com_phocacart.phocacartproduct' || $context == 'com_phocacart.product' )
		{
			$this->itemStateChange($pks, $value);
		}
		// Handle when the plugin is disabled
		if ($context == 'com_plugins.plugin' && $value === 0)
		{
			$this->pluginDisable($pks);
		}

	}

	protected function index(Joomla\Component\Finder\Administrator\Indexer\Result $item, $format = 'html')
	{
		// Check if the extension is enabled
		if (ComponentHelper::isEnabled($this->extension) == false)
		{
			return;
		}

		$item->setLanguage();

		// Initialize the item parameters.
        if (!empty($item->params)) {
            $registry = new Registry;
            $registry->loadString($item->params);
            $item->params = $registry;
        }

        if (!empty($item->metadata)) {
            $registry = new Registry;
            $registry->loadString($item->metadata);
            $item->metadata = $registry;
        }

		// Build the necessary route and path information.
		$item->url = $this->getURL($item->id, $this->extension, $this->layout);
        //$item->route = PhocacartRoute::getItemRoute($item->id, $item->categoryid, $item->alias, $item->categoryalias, $item->language);
		//$item->path = FinderIndexerHelper::getContentPath($item->route);
		//$item->url = $this->getURL($item->id, $this->extension, $this->layout);
        $p['search_link']			= $this->params->get( 'search_link', 0 );
		switch ($p['search_link'])
		{
            case 1:
                $item->route = PhocacartRoute::getCategoryRoute($item->categoryid, $item->categoryalias, $item->language);
                //$item->url = $item->route . '&productid='.$item->id;// We can have all items redirected to category view, so add unique item
				break;
			case 0:
			default:
                //$item->url = $this->getURL($item->id, $this->extension, $this->layout);
                $item->route = PhocacartRoute::getItemRoute($item->id, $item->categoryid, $item->alias, $item->categoryalias, $item->language);
                //$item->url = $item->route;
			break;
		}


		/*
		 * Add the meta-data processing instructions based on the newsfeeds
		 * configuration parameters.
		 */
		// Add the meta-author.
        if (!empty($item->metadata)) {
            $item->metaauthor = $item->metadata->get('author');
        }
		// Handle the link to the meta-data.
		$item->addInstruction(Indexer::META_CONTEXT, 'link');
		$item->addInstruction(Indexer::META_CONTEXT, 'metakey');
		$item->addInstruction(Indexer::META_CONTEXT, 'metadesc');
		$item->addInstruction(Indexer::META_CONTEXT, 'metaauthor');
		$item->addInstruction(Indexer::META_CONTEXT, 'author');
		$item->addInstruction(Indexer::META_CONTEXT, 'created_by_alias');

		// Add the type taxonomy data.
		$item->addTaxonomy('Type', 'Phoca Cart');

		// Add the category taxonomy data.

        if (isset($item->category) && $item->category != '') {
            $item->addTaxonomy('Category', $item->category, $item->cat_state, $item->cat_access);
        }

		// Add the language taxonomy data.
		$item->addTaxonomy('Language', $item->language);

		// Get content extras.
		Helper::getContentExtras($item);


        // Add the image.
        if (!empty($item->image)) {

            $path = PhocacartPath::getPath('productimage');
            $thumbLink	= PhocacartImage::getThumbnailName($path, $item->image, 'small');
            if ($thumbLink->rel != '') {
                $item->imageUrl = $thumbLink->rel;
                $item->imageAlt = addslashes(PhocacartText::filterValue($item->title, 'text')) ?? '';
                $item->images = json_encode([
                    'image_intro' => $item->imageUrl,
                    'image_intro_alt' => '',
                    'image_fulltext' => $item->imageUrl,
                    'image_fulltext_alt' => ''
                ]);
            }
        }

		// Index the item.
		$this->indexer->index($item);
	}

	protected function setup()
	{
		require_once JPATH_SITE . '/administrator/components/com_phocacart/libraries/phocacart/route/route.php';
		return true;
	}

	protected function getListQuery($query = null)
	{
		$db = Factory::getDbo();
		// Check if we can use the supplied SQL query.
		$query = $query instanceof DatabaseQuery ? $query : $db->getQuery(true)
			->select('a.id, a.catid, a.title, a.alias, "" AS link, a.description AS summary, a.image')
			->select('a.metakey, a.metadesc, a.metadata, a.language, a.access, a.ordering')
			->select('"" AS created_by_alias, "" AS modified, "" AS modified_by')
			->select('a.date AS publish_start_date, "" AS publish_end_date')
			->select('a.published AS state, a.date AS start_date, a.params, a.access, a.language')
			->select('c.id AS categoryid, c.title AS category, c.alias as categoryalias, c.published AS cat_state, c.access AS cat_access');

		// Handle the alias CASE WHEN portion of the query
		$case_when_item_alias = ' CASE WHEN ';
		$case_when_item_alias .= $query->charLength('a.alias', '!=', '0');
		$case_when_item_alias .= ' THEN ';
		//$a_id = $query->castAsChar('a.id');
		$a_id = $query->castAs('CHAR', 'a.id');
		$case_when_item_alias .= $query->concatenate(array($a_id, 'a.alias'), ':');
		$case_when_item_alias .= ' ELSE ';
		$case_when_item_alias .= $a_id.' END as slug';
		$query->select($case_when_item_alias);

		$case_when_category_alias = ' CASE WHEN ';
		$case_when_category_alias .= $query->charLength('c.alias', '!=', '0');
		$case_when_category_alias .= ' THEN ';
		//$c_id = $query->castAsChar('c.id');
		$c_id = $query->castAs('CHAR', 'c.id');
		$case_when_category_alias .= $query->concatenate(array($c_id, 'c.alias'), ':');
		$case_when_category_alias .= ' ELSE ';
		$case_when_category_alias .= $c_id.' END as catslug';
		$query->select($case_when_category_alias)

			->from('#__phocacart_products AS a')
			->join('LEFT', '#__phocacart_product_categories AS pc ON pc.product_id = a.id')
            ->join('LEFT', '#__phocacart_categories AS c ON c.id = pc.category_id');
		return $query;
	}

	protected function getUpdateQueryByTime($time)
	{
		// Build an SQL query based on the modified time.
		$query = $this->db->getQuery(true)
			->where('a.date >= ' . $this->db->quote($time));

		return $query;
	}

	protected function getStateQuery()
	{
		$query = $this->db->getQuery(true);

		// Item ID
		$query->select('a.id');

		// Item and category published state
		//$query->select('a.' . $this->state_field . ' AS state, c.published AS cat_state');
		$query->select('a.published AS state, c.published AS cat_state');
		// Item and category access levels
		//$query->select(' a.access, c.access AS cat_access')
		$query->select(' c.access AS cat_access')
			->from($this->table . ' AS a')
			->join('LEFT', '#__phocacart_categories AS c ON c.id = a.catid');

		return $query;
	}
}
