<?php

namespace ch\metanet\cms\model;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2015, METANET AG
 */
class RightGroup
{
	protected $ID;
	protected $groupKey;
	protected $groupName;
	protected $root;
	protected $rights;

	/**
	 * @return string
	 */
	public function getID()
	{
		return $this->ID;
	}

	/**
	 * @param mixed $ID
	 */
	public function setID($ID)
	{
		$this->ID = $ID;
	}

	/**
	 * @return string
	 */
	public function getGroupKey()
	{
		return $this->groupKey;
	}

	/**
	 * @param string $groupKey
	 */
	public function setGroupKey($groupKey)
	{
		$this->groupKey = $groupKey;
	}

	/**
	 * @return string
	 */
	public function getGroupName()
	{
		return $this->groupName;
	}

	/**
	 * @param string $groupName
	 */
	public function setGroupName($groupName)
	{
		$this->groupName = $groupName;
	}

	/**
	 * @return bool
	 */
	public function isRoot()
	{
		return $this->root;
	}

	/**
	 * @param bool $root
	 */
	public function setRoot($root)
	{
		$this->root = $root;
	}

	/**
	 * @return array
	 */
	public function getRights()
	{
		return $this->rights;
	}

	/**
	 * @param array $rights
	 */
	public function setRights(array $rights)
	{
		$this->rights = $rights;
	}
}

/* EOF */