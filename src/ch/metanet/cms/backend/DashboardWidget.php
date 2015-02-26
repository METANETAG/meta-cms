<?php

namespace ch\metanet\cms\backend;

use ch\metanet\cms\controller\common\BackendController;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
abstract class DashboardWidget
{
	protected $backendController;
	protected $name;
	protected $title;
	protected $size;

	public function __construct(BackendController $backendController, $name, $widgetTitle)
	{
		$this->name = $name;
		$this->backendController = $backendController;
		$this->title = $widgetTitle;
		$this->size = 1;
	}

	abstract protected function renderContent();

	public function render()
	{
		$widgetHtml = '<div class="backendlet backendlet-size-' . $this->size . ' clearfix">
			<h4>' . $this->title . '</h4>
			' . $this->renderContent() . '
		</div>';

		return $widgetHtml;
	}

	/**
	 * Sets the size of this widget
	 * @param int $size The size as grid width
	 */
	public function setSize($size)
	{
		$this->size = $size;
	}
}

/* EOF */ 