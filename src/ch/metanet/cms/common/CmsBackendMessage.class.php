<?php

namespace ch\metanet\cms\common;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 * @version 1.0.0
 */
class CmsBackendMessage {
	const MSG_TYPE_SUCCESS = 'success';
	const MSG_TYPE_WARNING = 'warning';
	const MSG_TYPE_ERROR = 'error';
	const MSG_TYPE_INFO = 'info';

	private $message;
	private $type;

	public function __construct($message, $type) {
		$this->message = $message;
		$this->type = $type;
	}

	/**
	 * Returns the message text
	 * @return string The message text
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * Returns the type of the message (success, error, info, warning)
	 * @return string The message type
	 */
	public function getType() {
		return $this->type;
	}
}

/* EOF */ 