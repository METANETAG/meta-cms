<?php

namespace ch\metanet\cms\controller\backend;

use ch\metanet\cms\common\PHPDocParser;
use ch\metanet\cms\controller\common\BackendController;
use ch\timesplinter\core\Core;
use ch\timesplinter\core\FrameworkLoggerFactory;
use ch\timesplinter\core\HttpRequest;
use ch\timesplinter\core\HttpResponse;
use ch\timesplinter\core\Route;
use ch\timesplinter\formhelper\FormHelper;

/**
 * The module controller handles installed modules
 *
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */
class ElementController extends BackendController
{
	/** @var $formHelper FormHelper */
	protected $formHelper;
	private $logger;

	public function __construct(Core $core, HttpRequest $httpRequest, Route $route) {
		parent::__construct($core, $httpRequest, $route);

		$this->logger = FrameworkLoggerFactory::getLogger($this);
		$this->markHtmlIdAsActive('elements');
	}

	/**
	 * Shows all the pages and their dependencies
	 * @return HttpResponse
	 */
	public function getElementsOverview()
	{
		if($this->httpRequest->getVar('deactivate') !== null)
			$this->setActivationOfElement($this->httpRequest->getVar('deactivate'), false);

		if($this->httpRequest->getVar('activate') !== null)
			$this->setActivationOfElement($this->httpRequest->getVar('activate'), true);

		$stmntElements = $this->db->prepare("
			SELECT ea.ID, ea.name element_name, ea.active, class, ma.name mod_name
			FROM cms_element_available ea
			LEFT JOIN cms_mod_available ma ON ma.ID = ea.mod_IDFK
			ORDER BY ea.name
		");

		$resElements = $this->db->select($stmntElements);

		foreach($resElements as $mod) {
			$modRef = new \ReflectionClass($mod->class);
			$resDoc = PHPDocParser::parse($modRef->getDocComment());
			$mod->description = $resDoc->comment;
			$mod->author = isset($resDoc->author) ? $resDoc->author : 'unknown';
			$mod->version = isset($resDoc->version) ? $resDoc->version : 'n/a';
			$mod->active_link = ($mod->active == 1) ? 'yes [<a href="?deactivate=' . $mod->ID . '">deactivate</a>]' : 'no [<a href="?activate=' . $mod->ID . '">activate</a>]';
		}

		$tplVars = array(
			'modules' => $resElements,
			'siteTitle' => 'Elements'
		);

		return $this->generatePageFromTemplate('backend-elements-overview', $tplVars);
	}

	public function setActivationOfElement($elementID, $active)
	{
		$stmntActivate = $this->db->prepare("
			UPDATE cms_element_available SET active = ? WHERE ID = ?
		");

		$this->db->update($stmntActivate, array(
			($active)?1:0,
			$elementID
		));
	}
}

/* EOF */