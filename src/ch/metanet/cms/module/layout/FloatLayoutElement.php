<?php

namespace ch\metanet\cms\module\layout;

use ch\metanet\cms\common\CmsElement;
use ch\metanet\cms\common\CmsView;
use ch\metanet\cms\controller\common\FrontendController;
use timesplinter\tsfw\db\DB;
use timesplinter\tsfw\template\TemplateEngine;
use stdClass;

/**
 * A layout module which lets you define n other modules and floats them to the left or the right.
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class FloatLayoutElement extends LayoutElement
{
	public function __construct($ID, $pageID)
	{
		parent::__construct($ID, $pageID, 'mod_float_layout');
	}

	public function render(FrontendController $frontendController, CmsView $view)
	{
		$renderedModules = new \ArrayObject();

		foreach($this->elements as $mod) {
			$renderedModules->append($mod->render($frontendController, $view));
		}

		$this->tplVars->offsetSet('modules', $renderedModules);
		$this->tplVars->offsetSet('modules_count', $renderedModules->count());
		$this->tplVars->offsetSet('logged_in', $frontendController->getAuth()->isLoggedIn());

		$html = $view->render($this->identifier . '.html', (array)$this->tplVars);

		if(!$frontendController->getAuth()->isLoggedIn())
			return $html;

		$htmlAdmin = '<div id="mod-' . $this->ID . '-' . $this->pageID . '" class="mod-editable">' . $html . '</div>';

		return $htmlAdmin;
	}

	/**
	 * Sets module default settings if no settings in the DB exists, class this method
	 * @return mixed
	 */
	public function setDefaultSettings()
	{
		// TODO: Implement setDefaultSettings() method.
	}

	public function create(DB $db)
	{
		try {
			parent::create($db);


		} catch(\Exception $e) {
			return false;
		}

		return true;
	}

	public function remove(DB $db)
	{
		// Do all the things do cleanly remove the module (e.x. delete something in db, clean up cached files etc)
	}

	public function update(DB $db, stdClass $newSettings, $pageID)
	{
		// TODO: Implement update() method.
	}
}

/* EOF */