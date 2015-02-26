<?php

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

namespace ch\metanet\cms\module\mod_search\model;


use ch\metanet\cms\model\Model;

class SearchModel extends Model {
	/**
	 * @param Document $document
	 * @return int The document ID
	 */
	public function saveDocument(Document $document) {
		$stmntDoc = $this->db->prepare("
			INSERT INTO mod_search_data
				SET ID = ?, internal_ID = ?, type = ?, title = ?, description = ?, path = ?, lang = ?
			ON DUPLICATE KEY
				UPDATE internal_ID = ?, title = ?, description = ?, path = ?, lang = ?
		");

		$newDocID = $this->db->insert($stmntDoc, array(
			// INSERT
			$document->getID(),
			$document->getInternalID(),
			$document->getType(),
			$document->getTitle(),
			$document->getDescription(),
			$document->getPath(),
			$document->getLanguage(),

			// UPDATE
			$document->getInternalID(),
			$document->getTitle(),
			$document->getDescription(),
			$document->getPath(),
			$document->getLanguage(),
		));

		if($document->getID() === null)
			return $newDocID;

		return $document->getID();
	}

	/**
	 * @param $internalID
	 * @param $type
	 */
	public function deleteDocument($internalID, $type) {
		$stmntDelDoc = $this->db->prepare("
			DELETE FROM mod_search_data WHERE internal_ID = ? AND type = ?
		");

		$this->db->delete($stmntDelDoc, array($internalID, $type));
	}

	/**
	 * @param int $internalID
	 * @param string $type
	 * @return Document
	 */
	public function getDocument($internalID, $type) {
		$stmntDoc = $this->db->prepare("
			SELECT ID, internal_ID, type, title, description, path, lang
			FROM mod_search_data
			WHERE internal_ID = ? AND type = ?
		");

		$resDoc = $this->db->select($stmntDoc, array($internalID, $type));

		$doc = new Document();

		if(count($resDoc) <= 0)
			return $doc;

		$docData = $resDoc[0];

		$doc->setID($docData->ID);
		$doc->setType($docData->type);
		$doc->setTitle($docData->title);
		$doc->setDescription($docData->description);
		$doc->setPath($docData->path);
		$doc->setLanguage($docData->lang);

		return $doc;
	}

	/**
	 * @param $ID
	 * @return Document
	 */
	public function getDocumentByID($ID) {
		$stmntDoc = $this->db->prepare("
			SELECT ID, internal_ID, type, title, description, path, lang
			FROM mod_search_data
			WHERE ID = ?
		");

		$resDoc = $this->db->select($stmntDoc, array($ID));

		$doc = new Document();

		if(count($resDoc) <= 0)
			return $doc;

		$docData = $resDoc[0];

		$doc->setID($docData->ID);
		$doc->setType($docData->type);
		$doc->setTitle($docData->title);
		$doc->setDescription($docData->description);
		$doc->setPath($docData->path);
		$doc->setLanguage($docData->lang);

		return $doc;
	}
}

/* EOF */