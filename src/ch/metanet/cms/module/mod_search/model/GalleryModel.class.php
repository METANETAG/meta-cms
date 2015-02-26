<?php

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2013, METANET AG
 * @version 1.0.0
 */

namespace ch\metanet\cms\module\mod_search\model;


use ch\metanet\cms\model\Model;

class GalleryModel extends Model {
	public function getHighlightByID($highlightID) {
		$stmntHighlight = $this->db->prepare("
			SELECT ID, image
			FROM mod_highlights_highlight
			WHERE ID = ?
		");

		$resHighlightEntry = $this->db->select($stmntHighlight, array($highlightID));

		if(count($resHighlightEntry) <= 0)
			return null;

		$stmntHighlightLang = $this->db->prepare("
			SELECT language_codeFK, title, description
			FROM mod_highlights_highlight_lang
			WHERE highlight_IDFK = ?
		");

		$langEntries = new \stdClass();

		foreach($this->db->select($stmntHighlightLang, array($resHighlightEntry[0]->ID)) as $le) {
			$langEntries->{$le->language_codeFK} = $le;
		}

		$resHighlightEntry[0]->lang = $langEntries;

		return $resHighlightEntry[0];
	}

	public function getAllGalleriesLocalized($lang) {
		$stmntGalleries = $this->db->prepare("
			SELECT g.ID, gl.title
			FROM mod_gallery_gallery g
			LEFT JOIN mod_gallery_gallery_lang gl ON gl.gallery_IDFK = g.ID AND gl.language_codeFK = ?
			ORDER BY gl.title
		");

		$resGalleries = $this->db->select($stmntGalleries, array($lang));

		return $resGalleries;
	}

	public function getGalleryByID($galleryID) {
		$stmntGallery = $this->db->prepare("
			SELECT ID FROM mod_gallery_gallery WHERE ID = ?
		");

		$resGallery = $this->db->select($stmntGallery, array($galleryID));

		if(count($resGallery) <= 0)
			return null;

		$stmntGalleryLang = $this->db->prepare("
			SELECT title, description, language_codeFK
			FROM mod_gallery_gallery_lang
			WHERE gallery_IDFK = ?
		");

		$resGalleryLang = $this->db->select($stmntGalleryLang, array($galleryID));

		$gallery = $resGallery[0];

		foreach($resGalleryLang as $nel) {
			$gallery->{$nel->language_codeFK} = (object)array(
				'title' => $nel->title,
				'description' => $nel->description
			);
		}

		return $gallery;
	}

	public function getImageByID($imageID) {
		$stmntImage = $this->db->prepare("
			SELECT ID, uniqid, filename, gallery_IDFK FROM mod_gallery_image WHERE ID = ?
		");

		$resImage = $this->db->select($stmntImage, array($imageID));

		if(count($resImage) <= 0)
			return null;

		$stmntImageLang = $this->db->prepare("
			SELECT title, description, language_codeFK
			FROM mod_gallery_image_lang
			WHERE image_IDFK = ?
		");

		$resImageLang = $this->db->select($stmntImageLang, array($imageID));

		$image = $resImage[0];
		$image->lang = new \stdClass();

		foreach($resImageLang as $nel) {
			$image->lang->{$nel->language_codeFK} = (object)array(
				'title' => $nel->title,
				'description' => $nel->description
			);
		}

		return $image;
	}

	public function deleteGallery($galleryID) {
		$stmntDelete = $this->db->prepare("
				DELETE FROM mod_gallery_gallery WHERE ID = ?
			");

		$this->cmsController->getDB()->delete($stmntDelete, array(
			$galleryID
		));

		$stmntDeleteLang = $this->db->prepare("DELETE FROM mod_gallery_gallery_lang WHERE gallery_IDFK = ?");
		$this->db->delete($stmntDeleteLang, array($galleryID));
	}

	public function getHighlightsByCollection($collectionID) {
		$stmntHighlights = $this->db->prepare("
			SELECT h.ID, h.image
			FROM mod_highlights_collection_has_highlight chs
			LEFT JOIN mod_highlights_highlight h ON h.ID = chs.highlight_IDFK
			WHERE collection_IDFK = ?
		");

		$resHighlights = $this->db->select($stmntHighlights, array($collectionID));

		$stmntHighlightLang = $this->db->prepare("
			SELECT language_codeFK, title, description
			FROM mod_highlights_highlight_lang
			WHERE highlight_IDFK = ?
		");

		foreach($resHighlights as $h) {
			$langEntries = new \stdClass();

			foreach($this->db->select($stmntHighlightLang, array($h->ID)) as $le) {
				$langEntries->{$le->language_codeFK} = $le;
			}

			$h->lang = $langEntries;
		}

		return $resHighlights;
	}

	public function getAllCollections() {
		$stmntHighlight = $this->db->prepare("
			SELECT ID, title
			FROM mod_highlights_collection
			ORDER BY title
		");

		$resHighlightEntry = $this->db->select($stmntHighlight);

		return $resHighlightEntry;
	}

	public function getImagesByGalleryLocalized($galleryID, $lang) {
		$stmntImages = $this->db->prepare("
			SELECT i.ID, filename, uniqid, il.title
			FROM mod_gallery_image i
			LEFT JOIN mod_gallery_image_lang il ON il.image_IDFK = i.ID AND il.language_codeFK = ?
			WHERE i.gallery_IDFK = ?
			ORDER BY sort
		");

		return $this->db->select($stmntImages, array($lang, $galleryID));
	}
}

/* EOF */