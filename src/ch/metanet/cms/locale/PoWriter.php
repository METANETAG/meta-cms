<?php

namespace ch\metanet\cms\locale;

use timesplinter\tsfw\i18n\gettext\PoWriterInterface;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class PoWriter implements PoWriterInterface
{
	public function write($filePath, array $entries)
	{
		$dirPath = dirname($filePath);

		if(is_dir($dirPath) === false)
			mkdir($dirPath, 0777, true);

		if(($f = @fopen($filePath, 'wb')) === false)
			return;
		
		$entriesCount = count($entries);
		$counter = 0;

		foreach($entries as $entry) {
			++$counter;

			$isObsolete = isset($entry['obsolete']) && $entry['obsolete'];
			$isPlural = isset($entry['msgid_plural']);

			if (isset($entry['tcomment'])) {
				fwrite($f, "# " . $entry['tcomment'] . "\n");
			}

			if (isset($entry['ccomment'])) {
				fwrite($f, '#. ' . $entry['ccomment'] . "\n");
			}

			if (isset($entry['reference'])) {
				foreach ($entry['reference'] as $ref) {
					fwrite($f, '#: ' . $ref . "\n");
				}
			}

			if (isset($entry['flags']) && !empty($entry['flags'])) {
				fwrite($f, "#, " . $entry['flags'] . "\n");
			}

			if (isset($entry['@'])) {
				fwrite($f, "#@ " . $entry['@'] . "\n");
			}

			if (isset($entry['msgctxt'])) {
				fwrite($f, 'msgctxt ' . $entry['msgctxt'] . "\n");
			}

			if ($isObsolete) {
				fwrite($f, '#~ ');
			}

			if (isset($entry['msgid'])) {
				if (is_array($entry['msgid'])) {
					$entry['msgid'] = implode('', $entry['msgid']);
				}

				// Special clean for msgid
				$msgid = explode("\n", $entry['msgid']);

				fwrite($f, 'msgid ');
				foreach ($msgid as $i => $id) {
					fwrite($f, $this->cleanExport($id) . "\n");
				}
			}

			if (isset($entry['msgid_plural'])) {
				if (is_array($entry['msgid_plural'])) {
					$entry['msgid_plural'] = implode('', $entry['msgid_plural']);
				}
				fwrite($f, 'msgid_plural ' . $this->cleanExport($entry['msgid_plural']) . "\n");
			}

			if (isset($entry['msgstr'])) {
				if ($isPlural) {
					foreach ($entry['msgstr'] as $i => $t) {
						if ($isObsolete) {
							fwrite($f, '#~ ');
						}
						fwrite($f, "msgstr[$i] " . $this->cleanExport($t) . "\n");
					}
				} else {
					foreach ($entry['msgstr'] as $i => $t) {
						if ($i == 0) {
							if ($isObsolete) {
								fwrite($f, '#~ ');
							}
							fwrite($f, 'msgstr ' . $this->cleanExport($t) . "\n");
						} else {
							fwrite($f, $this->cleanExport($t) . "\n");
						}
					}
				}
			}

			if ($counter != $entriesCount) {
				fwrite($f, "\n");
			}
		}

		fclose($f);
	}

	/**
	 * @param $string
	 *
	 * @return string
	 */
	protected function cleanExport($string)
	{
		$quote = '"';
		$slash = '\\';
		$newline = "\n";

		$replaces = array(
			"$slash" => "$slash$slash",
			"$quote" => "$slash$quote",
			"\t"     => '\t',
		);

		$string = str_replace(array_keys($replaces), array_values($replaces), $string);

		$po = $quote . implode("${slash}n$quote$newline$quote", explode($newline, $string)) . $quote;

		// remove empty strings
		return str_replace("$newline$quote$quote", '', $po);
	}
}

/* EOF */ 