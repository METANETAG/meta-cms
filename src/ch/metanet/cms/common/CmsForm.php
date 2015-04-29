<?php

namespace ch\metanet\cms\common;

use ch\metanet\formHandler\component\Form;
use ch\metanet\formHandler\component\Component;
use ch\metanet\formHandler\field\Field;

/**
 * An extension of the form handler form component to make it suitable for the CMS. Concrete it overrides the default
 * field renderer with a basic CMS form component renderer.
 * 
 * @see Form
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class CmsForm extends Form
{
	protected $defaultFieldComponentRenderer;

	public function __construct()
	{
		parent::__construct();

		$this->defaultFieldComponentRenderer = new CmsFormComponentRenderer();
	}

	public function addComponent(Component $component)
	{
		if($component instanceof Field)
			$component->setFieldComponentRenderer($this->defaultFieldComponentRenderer);

		parent::addComponent($component);
	}
}