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
require_once JPATH_SITE . '/plugins/finder/phocacartproduct/phocacartproduct.php';

class PlgFinderPhocacartproductimages extends PlgFinderPhocacartproduct
{
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
		//$item->url = $this->getURL($item->id, $this->extension, $this->layout);
        //$item->route = PhocacartRoute::getItemRoute($item->id, $item->categoryid, $item->alias, $item->categoryalias, $item->language);
		//$item->path = FinderIndexerHelper::getContentPath($item->route);
		//$item->url = $this->getURL($item->id, $this->extension, $this->layout);
        $p['search_link']			= $this->params->get( 'search_link', 0 );
		switch ($p['search_link'])
		{
            case 1:
                $item->route = PhocacartRoute::getCategoryRoute($item->categoryid, $item->categoryalias, $item->language);
                $item->url = $item->route . '&productid='.$item->id;// We can have all items redirected to category view, so add unique item
				break;
			case 0:
			default:
                //$item->url = $this->getURL($item->id, $this->extension, $this->layout);
                $item->route = PhocacartRoute::getItemRoute($item->id, $item->categoryid, $item->alias, $item->categoryalias, $item->language);
                $item->url = $item->route;
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

		$image = false;
		$db = Factory::getDbo();
		$query = $db->getQuery(true);

		$query->select(
			$db->quoteName('image')
		);
		$query->from(
			$db->quoteName('#__phocacart_products')
		);
		$query->where([
			$db->quoteName('id') . ' = ' . $item->id,
		]);

		try {
			$image = $db->setQuery($query)->loadResult();
		}
		catch ( \Exception $e ) {
			$image = false;
		}

		$item->imageUrl = '';
		if ( $image !== false ) {
			$item->imageUrl = '/images/phocacartproducts/' . $image;
			$item->images = json_encode([
				'image_intro' => $item->imageUrl,
				'image_intro_alt' => '',
				'image_fulltext' => $item->imageUrl,
				'image_fulltext_alt' => ''
			]);
		}

		// Index the item.
		$this->indexer->index($item);
	}

	protected function getListQuery($query = null)
	{
		$query = parent::getListQuery($query);
		$query->group('a.id');

		return $query;
	}
}
