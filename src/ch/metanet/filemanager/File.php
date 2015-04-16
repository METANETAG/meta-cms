<?php

namespace ch\metanet\filemanager;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2015, METANET AG
 */
class File
{
	protected $ID;
	protected $nameSys;
	protected $name;
	protected $type;
	protected $send;
	protected $size;
	protected $otherInfo;
	protected $category;

	/**
	 * @return int
	 */
	public function getID()
	{
		return $this->ID;
	}

	/**
	 * @return string
	 */
	public function getNameSys()
	{
		return $this->nameSys;
	}

	/**
	 * @param string $nameSys
	 */
	public function setNameSys($nameSys)
	{
		$this->nameSys = $nameSys;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName($name)
	{
		$this->name = $name;
	}

	/**
	 * @return mixed
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @param mixed $type
	 */
	public function setType($type)
	{
		$this->type = $type;
	}

	/**
	 * @return bool
	 */
	public function getSend()
	{
		return $this->send;
	}

	/**
	 * @param bool $send
	 */
	public function setSend($send)
	{
		$this->send = $send;
	}

	/**
	 * @return mixed
	 */
	public function getOtherInfo()
	{
		return $this->otherInfo;
	}

	/**
	 * @param mixed $otherInfo
	 */
	public function setOtherInfo($otherInfo)
	{
		$this->otherInfo = $otherInfo;
	}

	/**
	 * @return mixed
	 */
	public function getCategory()
	{
		return $this->category;
	}

	/**
	 * @param mixed $category
	 */
	public function setCategory($category)
	{
		$this->category = $category;
	}

	/**
	 * @return int
	 */
	public function getSize()
	{
		return $this->size;
	}

	/**
	 * @param int $size
	 */
	public function setSize($size)
	{
		$this->size = $size;
	}
}

/* EOF */