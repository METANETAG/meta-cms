<?php


namespace ch\timesplinter\mailer;

use \Swift_MailTransport;
use \Swift_Mailer;

class MailFactory {
	/**
	 * @return Swift_Mailer
	 */
	public static function getMailer() {
		// Create the Transport
		$transport = Swift_MailTransport::newInstance();

		// Create the Mailer using your created Transport
		return Swift_Mailer::newInstance($transport);
	}
}

/* EOF */