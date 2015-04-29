<?php

namespace ch\metanet\cms\common;

/**
 * A CMS backend message
 * 
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class CmsBackendMessage
{
	const MSG_TYPE_SUCCESS = 'success';
	const MSG_TYPE_WARNING = 'warning';
	const MSG_TYPE_ERROR = 'error';
	const MSG_TYPE_INFO = 'info';

	protected $message;
	protected $type;

	/**
	 * @param $message
	 * @param $type
	 */
	public function __construct($message, $type)
	{
		$this->message = $message;
		$this->type = $type;
	}

	/**
	 * Returns the message text
	 * 
	 * @return string The message text
	 */
	public function getMessage()
	{
		return $this->message;
	}

	/**
	 * Returns the type of the message (success, error, info, warning)
	 * 
	 * @return string The message type
	 */
	public function getType()
	{
		return $this->type;
	}
}

/* EOF */ 