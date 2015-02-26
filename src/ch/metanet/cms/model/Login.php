<?php

namespace ch\metanet\cms\model;

/**
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class Login
{
	protected $ID;
	protected $username;
	protected $email;
	protected $lastLogin;
	protected $wrongLogins;
	protected $confirmed;
	protected $token;
	protected $tokenTime;
	protected $active;
	protected $registered;
	protected $registeredBy;
	protected $password;
	protected $salt;
	
	/**
	 * @return int
	 */
	public function getID()
	{
		return $this->ID;
	}

	/**
	 * @param int $ID
	 */
	public function setID($ID)
	{
		$this->ID = $ID;
	}

	/**
	 * @return int
	 */
	public function getActive()
	{
		return $this->active;
	}

	/**
	 * @param int $active
	 */
	public function setActive($active)
	{
		$this->active = $active;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getConfirmed()
	{
		return $this->confirmed;
	}

	/**
	 * @param \DateTime|null $confirmed
	 */
	public function setConfirmed($confirmed)
	{
		$this->confirmed = $confirmed;
	}

	/**
	 * @return string
	 */
	public function getEmail()
	{
		return $this->email;
	}

	/**
	 * @param string $email
	 */
	public function setEmail($email)
	{
		$this->email = $email;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getLastLogin()
	{
		return $this->lastLogin;
	}

	/**
	 * @param \DateTime|null $lastLogin
	 */
	public function setLastLogin($lastLogin)
	{
		$this->lastLogin = $lastLogin;
	}

	/**
	 * @return \DateTime
	 */
	public function getRegistered()
	{
		return $this->registered;
	}

	/**
	 * @param \DateTime $registered
	 */
	public function setRegistered($registered)
	{
		$this->registered = $registered;
	}

	/**
	 * @return string
	 */
	public function getToken()
	{
		return $this->token;
	}

	/**
	 * @param string $token
	 */
	public function setToken($token) 
	{
		$this->token = $token;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getTokenTime()
	{
		return $this->tokenTime;
	}

	/**
	 * @param \DateTime|null $tokenTime
	 */
	public function setTokenTime($tokenTime)
	{
		$this->tokenTime = $tokenTime;
	}

	/**
	 * @return string
	 */
	public function getUsername()
	{
		return $this->username;
	}

	/**
	 * @param string $username
	 */
	public function setUsername($username)
	{
		$this->username = $username;
	}

	/**
	 * @return int
	 */
	public function getWrongLogins()
	{
		return $this->wrongLogins;
	}

	/**
	 * @param int $wrongLogins
	 */
	public function setWrongLogins($wrongLogins)
	{
		$this->wrongLogins = $wrongLogins;
	}

	/**
	 * @return string
	 */
	public function getPassword()
	{
		return $this->password;
	}

	/**
	 * @param string $password
	 */
	public function setPassword($password)
	{
		$this->password = $password;
	}

	/**
	 * @return string
	 */
	public function getSalt()
	{
		return $this->salt;
	}

	/**
	 * @param string $salt
	 */
	public function setSalt($salt)
	{
		$this->salt = $salt;
	}

	/**
	 * @return int|null
	 */
	public function getRegisteredBy()
	{
		return $this->registeredBy;
	}

	/**
	 * @param int|null $registeredBy
	 */
	public function setRegisteredBy($registeredBy)
	{
		$this->registeredBy = $registeredBy;
	}
}

/* EOF */ 