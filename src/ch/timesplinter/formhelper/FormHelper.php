<?php
namespace ch\timesplinter\formhelper;

use ch\timesplinter\core\FrameworkLoggerFactory;
use ch\timesplinter\logger\LoggerFactory;
use \DateTime;

/**
 * handler for the environment settings
 *
 * @author Pascal Münst <entwicklung@metanet.ch>
 * @copyright (c) 2012, METANET AG, www.metanet.ch
 * @version 1.0
 * 
 * @deprecated Use FormHandler (metanet/form-handler) instead
 */
class FormHelper {

	const METHOD_POST = 1;
	const METHOD_GET = 2;
	
	const TYPE_STRING = 1;
	const TYPE_INTEGER = 2;
	const TYPE_FLOAT = 3;
	const TYPE_DOUBLE = 4;
	const TYPE_EMAIL = 5;
	const TYPE_DATE = 6;
	const TYPE_OPTION = 7;
	const TYPE_CHECKBOX = 8;
	const TYPE_MULTIOPTIONS = 9;
	const TYPE_DECIMAL = 10;
	const TYPE_URL = 11;
	const TYPE_TIME = 12;
	const TYPE_FILE = 13;
	const TYPE_IMAGE = 14;
	const TYPE_PDF = 15;
	const TYPE_YOUTUBE = 16;
	const TYPE_VIDEO = 17;
	const TYPE_RADIO = 18;
	const TYPE_CSV = 19

	private $logger;
	private $name;
	private $rsc;
	private $errors;
	private $fields;
	private $totalFields;
	private $totalRequired;
	private $sentVar;

	/**
	 * @param string $method
	 * @param array $sentVar
	 *
	 * @deprecated Use FormHandler (metanet/form-handler) instead
	 */
	public function __construct($method, $sentVar = array(self::METHOD_GET, 'send')/*, $name = null*/) {
		$this->logger = FrameworkLoggerFactory::getLogger($this);
		
		$this->rsc = ($method === self::METHOD_POST) ? $_POST : $_GET;
		$this->errors = null;
		$this->fields = array();
		$this->totalFields = 0;
		$this->totalRequired = 0;
		//$this->name = ($name === null) ? implode("-", $this->requestHandler->getLinkVars()) : $name;
		$this->sentVar = $sentVar;
	}

	public function addField($name, $value = '', $type = self::TYPE_STRING, $required = false, $options = array()) {
		$this->fields[$name] = array(
			'name' => $name
			, 'value' => $value
			, 'type' => $type
			, 'required' => $required
			, 'options' => $options
		);
		$this->totalFields++;
		if($required === true) {
			$this->totalRequired++;
		}
	}

	public function sent() {
		if($this->sentVar[0] === self::METHOD_GET) {
			return isset($_GET[$this->sentVar[1]]);
		} elseif($this->sentVar[0] === self::METHOD_POST) {
			return isset($_POST[$this->sentVar[1]]);
		}
		
		return false;
	}
	
	public function validate() {
		$this->errors = array();

		foreach($this->fields as $fieldName => $fieldData) {
			if(isset($fieldData['type']) === false) {
				throw new \Exception('No data type given for field ' . $fieldName);
			}
			
			switch($fieldData['type']) {
				case self::TYPE_STRING:
					self::validateString($fieldName);
					break;

				case self::TYPE_INTEGER:
					self::validateInteger($fieldName);
					break;

				case self::TYPE_FLOAT:
					self::validateFloat($fieldName);
					break;

				case self::TYPE_DOUBLE:
					self::validateDouble($fieldName);
					break;

				case self::TYPE_EMAIL:
					self::validateEmail($fieldName);
					break;

				case self::TYPE_DATE:
					self::validateDate($fieldName);
					break;

				case self::TYPE_TIME:
					self::validateTime($fieldName);
					break;

				case self::TYPE_OPTION:
					self::validateOption($fieldName);
					break;

				case self::TYPE_RADIO:
					self::validateOption($fieldName);
					break;

				case self::TYPE_CHECKBOX:
					self::validateCheckbox($fieldName);
					break;

				case self::TYPE_MULTIOPTIONS:
					self::validateMultioptions($fieldName);
					break;

				case self::TYPE_DECIMAL:
					self::validateDecimal($fieldName);
					break;

				case self::TYPE_URL:
					self::validateURL($fieldName);
					break;

				case self::TYPE_IMAGE:
					self::validateImage($fieldName);
					break;

				case self::TYPE_PDF:
					self::validatePDF($fieldName);
					break;
					
				case self::TYPE_CSV:
					self::validateCSV($fieldName);
					break;

				case self::TYPE_YOUTUBE:
					self::validateYoutube($fieldName);
					break;

				case self::TYPE_VIDEO:
					self::validateVideo($fieldName);
					break;
				case self::TYPE_FILE:
					self::validateFile($fieldName);
					break;
			}
		}
		
		if(count($this->errors) === 0) {
			$this->errors = null;
			return true;
		}
		
		return false;
	}

	private function validateString($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$checkAgainst = (isset($fieldData['options']['checkAgainst'])) ? $fieldData['options']['checkAgainst'] : null;
		$matchRegex = (isset($fieldData['options']['matchRegex'])) ? $fieldData['options']['matchRegex'] : null;
		$value = (isset($this->rsc[$fieldName]) && strlen(trim($this->rsc[$fieldName])) > 0)? trim($this->rsc[$fieldName]) : null;

		if($required === true && $value == null) {
			$this->addError($fieldName, $missingError);
		} elseif($value !== null && $checkAgainst !== null && $checkAgainst != $value) {
			$this->addError($fieldName, $invalidError);
		} elseif($value !== null && $matchRegex !== null && !preg_match($matchRegex, $value)) {
			$this->addError($fieldName, $invalidError);
		}

		$this->setFieldValue($fieldName, $value);
	}

	private function validateInteger($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$value = (isset($this->rsc[$fieldName]) && strlen(trim($this->rsc[$fieldName])) > 0)? trim($this->rsc[$fieldName]) : null;

		if($required === true && $value == null) {
			$this->addError($fieldName, $missingError);
		}

		if($value != null && filter_var($value, FILTER_VALIDATE_INT) === false) {
			$this->addError($fieldName, $invalidError);
		}

		$this->setFieldValue($fieldName, ($value != null && $this->getError($fieldName) == '')?(int)$value:$value);
	}

	private function validateDecimal($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$value = isset($this->rsc[$fieldName]) ? trim(str_replace(array(','), array('.'), $this->rsc[$fieldName])) : '';

		if($required === true && $value == '') {
			$this->addError($fieldName, $missingError);
		}

		if($value != '' && filter_var($value, FILTER_VALIDATE_FLOAT, array('options' => array('decimal' => true))) !== 0 && filter_var($value, FILTER_VALIDATE_FLOAT, array('options' => array('decimal' => true))) === false) {
			$this->addError($fieldName, $invalidError);
			$this->setFieldValue($fieldName, $value);
		} else {
			$this->setFieldValue($fieldName, (float)$value);
		}
	}

	private function validateDouble($fieldName) {
		echo $fieldName . ' function validateDouble to be written!!!';
		exit;
	}

	private function validateEmail($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$wildcardAllowed = (isset($fieldData['options']['wildcardAllowed'])) ? $fieldData['options']['wildcardAllowed'] : false;
		$value = isset($this->rsc[$fieldName]) ? trim($this->rsc[$fieldName]) : null;

		if($required === true && $value == null)
			$this->addError($fieldName, $missingError);

		if($value != null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
			$this->addError($fieldName, $invalidError);
		} elseif($value != null) {
			list($local, $domain) = explode('@', $value);

			if($wildcardAllowed === false && $local === '*')
				$this->addError($fieldName, $invalidError);
		}

		$this->setFieldValue($fieldName, $value);
	}

	private function validateURL($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$value = (isset($this->rsc[$fieldName]) && strlen($this->rsc[$fieldName]) > 0) ? trim($this->rsc[$fieldName]) : null;

		if($required === true && $value == null) {
			$this->addError($fieldName, $missingError);
		}

		if($value != null) {
			// see http://stackoverflow.com/questions/206059/php-validation-regex-for-url
			if(!preg_match("#((http|https)://(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|\"|'|:|\<|$|\.\s)#ie", $value)) {
				$value = 'http://'.$value;
			}

			if(!filter_var($value, FILTER_VALIDATE_URL)) {
				$this->addError($fieldName, $invalidError);
			}
		}

		$this->setFieldValue($fieldName, $value);
	}

	private function validateImage($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$allowedArr = (isset($fieldData['options']['allowed'])) ? $fieldData['options']['allowed'] : array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG);
		$pathToUpload = (isset($fieldData['options']['pathToUpload'])) ? $fieldData['options']['pathToUpload'] : '/tmp/';
		
		$sessionFieldName = 'formData_' . $this->name . '_' . $fieldName;

		$value = array('name' => '');
		if(isset($_FILES[$fieldName]) && isset($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['name'] != '') {
			$value = $_FILES[$fieldName];
			$value['uploaded'] = false;
		} elseif(isset($_SESSION[$sessionFieldName])) {
			$value = $_SESSION[$sessionFieldName];
		}

		if($required === true && $value['name'] == '') {
			$this->addError($fieldName, $missingError);
		}

		if($value['name'] != '') {
			$type = file_exists($value['tmp_name']) ? exif_imagetype($value['tmp_name']) : '';
			if(!in_array($type, $allowedArr)) {
				$this->addError($fieldName, $invalidError);
			} else {
				if($value['uploaded'] === false) {
					if(isset($_SESSION[$sessionFieldName]) && file_exists($_SESSION[$sessionFieldName]['tmp_name'])) {
						unlink($_SESSION[$sessionFieldName]['tmp_name']);
					}
					$newPath = $pathToUpload . uniqid();
					move_uploaded_file($value['tmp_name'], $newPath);
					$value['uploaded'] = true;
					$value['tmp_name'] = $newPath;
					$value['sessionFieldName'] = $sessionFieldName;
					$_SESSION[$sessionFieldName] = $value;
				}
			}
		}
		
		$this->setFieldValue($fieldName, $value);
	}
	
	private function validateFile($fieldName) {
		
		
		$fieldData = $this->fields[$fieldName];
		
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$allowedArr = (isset($fieldData['options']['allowed'])) ? $fieldData['options']['allowed'] : array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG);
		$pathToUpload = (isset($fieldData['options']['pathToUpload'])) ? $fieldData['options']['pathToUpload'] : '/tmp/';
		$callbacks = (isset($fieldData['options']['callbacks'])) ? $fieldData['options']['callbacks'] : array();
		
		$value = isset($_FILES[$fieldName]) ? $_FILES[$fieldName] : null;
		
		if($required === true && $value['name'] === '') {
			$this->addError($fieldName, $missingError);
			return;
		} elseif($value['name'] === '') {
			$this->setFieldValue($fieldName, null);
			return;
		}
		
		if($value['error'] !== 0) {
			$errorCode = $value['error'];
			$errorMsg = '';
			if($errorCode === 1) {
				$errorMsg = 'Datei überschreitet die maximal zugelassene Grösse'; // PHP
			} elseif($errorCode === 2) {
				$errorMsg = 'Datei überschreitet die maximal zugelassene Grösse'; // <form ...> (POST_MAX_SIZE, UPLOAD_MAX_FILESIZE)
			} elseif($errorCode === 3) {
				$errorMsg = 'Datei nicht vollständig hochgeladen';
			} elseif($errorCode === 6) {
				$errorMsg = 'kein tmp-Verzeichnis vorhanden';
			} elseif($errorCode === 7) {
				$errorMsg = 'keine Schreibrechte auf tmp-Verzeichnis';
			} elseif($errorCode === 8) {
				$errorMsg = 'unzulässige Dateiendung';
			} else {
				$errorMsg = 'unbekannter Fehler (' . $value['error'] . ')';
			}
			
			$this->addError($fieldName, 'Fehler beim Hochladen: ' . $errorMsg);
			return;
		}
		
		$value['gen_filename'] = uniqid();
		$value['upload_path'] = $pathToUpload;
		
		foreach($callbacks as $c) {
			$res = call_user_func($c, $value);
			
			if($res instanceof \Exception) {
				$this->addError($fieldName, print_r($res->getMessage(),true));
				return;
			}
			
			$value = $res;
		}
		
		$this->setFieldValue($fieldName, $value);
	}

	private function validatePDF($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$value = isset($_FILES[$fieldName]) ? $_FILES[$fieldName] : array('name' => '');

		$sessionFieldName = 'formData_' . $this->name . '_' . $fieldName;

		$value = array('name' => '');
		if(isset($_FILES[$fieldName]) && isset($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['name'] != '') {
			$value = $_FILES[$fieldName];
			$value['uploaded'] = false;
		} elseif(isset($_SESSION[$sessionFieldName])) {
			$value = $_SESSION[$sessionFieldName];
		}

		if($required === true && $value['name'] == '') {
			$this->addError($fieldName, $missingError);
		}

		if($value['name'] != '') {
			$extension = strtolower(substr($value['name'], strrpos($value['name'], '.') + 1));
			if($extension != 'pdf') {
				$this->addError($fieldName, $invalidError);
			} else {
				$fp = fopen($value['tmp_name'], 'r');
				if(!fgets($fp, 4) == '%PDF') {
					$this->addError($fieldName, $invalidError);
				} else {
					if($value['uploaded'] === false) {
						if(isset($_SESSION[$sessionFieldName]) && file_exists($_SESSION[$sessionFieldName]['tmp_name'])) {
							unlink($_SESSION[$sessionFieldName]['tmp_name']);
						}
						$newPath = '/tmp/' . uniqid();
						move_uploaded_file($value['tmp_name'], $newPath);
						$value['uploaded'] = true;
						$value['tmp_name'] = $newPath;
						$value['sessionFieldName'] = $sessionFieldName;
						$_SESSION[$sessionFieldName] = $value;
					}
				}
			}
		}

		$this->setFieldValue($fieldName, $value);
	}
	
	private function validateCSV($fieldName) {
		
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		
		$value = isset($_FILES[$fieldName]) ? $_FILES[$fieldName] : array('name' => '');

		$sessionFieldName = 'formData_' . $this->name . '_' . $fieldName;

		if(isset($_FILES[$fieldName]) && isset($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['name'] != '') {
			$value = $_FILES[$fieldName];
			$value['uploaded'] = false;
		} elseif(isset($_SESSION[$sessionFieldName])) {
			$value = $_SESSION[$sessionFieldName];
		}

		if($required === true && $value['name'] == '') {
			$this->addError($fieldName, $missingError);
		}

		if($value['name'] != '') {
			$extension = strtolower(substr($value['name'], strrpos($value['name'], '.') + 1));
			if($extension != 'csv') {
				$this->addError($fieldName, $invalidError);
			} else {
				if($value['uploaded'] === false) {
					if(isset($_SESSION[$sessionFieldName]) && file_exists($_SESSION[$sessionFieldName]['tmp_name'])) {
						unlink($_SESSION[$sessionFieldName]['tmp_name']);
					}
					$newPath = sys_get_temp_dir() . uniqid();
					move_uploaded_file($value['tmp_name'], $newPath);
					$value['uploaded'] = true;
					$value['tmp_name'] = $newPath;
					$value['sessionFieldName'] = $sessionFieldName;
					$_SESSION[$sessionFieldName] = $value;
				}
			}
		}

		$this->setFieldValue($fieldName, $value);
	}

	private function validateVideo($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$allowedArr = (isset($fieldData['options']['allowed'])) ? $fieldData['options']['allowed'] : array(
			'swf' => array('application/x-shockwave-flash')
			, 'mov' => array('video/quicktime', 'video/x-quicktime')
			);
		$value = isset($_FILES[$fieldName]) ? $_FILES[$fieldName] : array('name' => '');

		$sessionFieldName = 'formData_' . $this->name . '_' . $fieldName;

		$value = array('name' => '');
		if(isset($_FILES[$fieldName]) && isset($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['name'] != '') {
			$value = $_FILES[$fieldName];
			$value['uploaded'] = false;
		} elseif(isset($_SESSION[$sessionFieldName])) {
			$value = $_SESSION[$sessionFieldName];
		}

		if($required === true && $value['name'] == '') {
			$this->addError($fieldName, $missingError);
		}

		if($value['name'] != '') {
			$extension = strtolower(substr($value['name'], strrpos($value['name'], '.') + 1));
			if(!isset($allowedArr[$extension])) {
				$this->addError($fieldName, $invalidError);
			} elseif(!isset($value['type']) || !in_array($value['type'], $allowedArr[$extension])) {
				$this->addError($fieldName, $invalidError);
			} else {
				if($value['uploaded'] === false) {
					if(isset($_SESSION[$sessionFieldName])) {
						unlink($_SESSION[$sessionFieldName]['tmp_name']);
					}
					$newPath = fwRoot . '/savedata/tmp/' . uniqid();
					move_uploaded_file($value['tmp_name'], $newPath);
					$value['uploaded'] = true;
					$value['tmp_name'] = $newPath;
					$value['sessionFieldName'] = $sessionFieldName;
					$_SESSION[$sessionFieldName] = $value;
				}
			}
		}

		$this->setFieldValue($fieldName, $value);
	}

	private function validateDate($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$value = (isset($this->rsc[$fieldName]) && strlen(trim($this->rsc[$fieldName]))) > 0 ? trim($this->rsc[$fieldName]) : null;

		$this->setFieldValue($fieldName, $value);

		if($value == null) {
			if($required === true) $this->addError($fieldName, $missingError);
		} else {
			try {
				$dateTime = new DateTime($value);

				$dtErrors = $dateTime->getLastErrors();
				if($dtErrors['warning_count'] > 0 || $dtErrors['error_count'] > 0)
					$this->addError($fieldName, $invalidError);
			} catch(\Exception $e) {
				$this->addError($fieldName, $invalidError);
			}
		}
	}

	private function validateTime($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$value = isset($this->rsc[$fieldName]) ? trim($this->rsc[$fieldName]) : '';

		if($value == '') {
			if($required === true) {
				$this->addError($fieldName, $missingError);
			}
		} else {
			$strArr = explode(":", $value);
			$hour = (int) $strArr[0];
			$minute = isset($strArr[1]) ? (int) $strArr[1] : 0;
			$second = isset($strArr[2]) ? (int) $strArr[2] : 0;

			if(!in_array($hour, range(0, 23)) || !in_array($minute, range(0, 59)) || !in_array($second, range(0, 59))) {
				$this->addError($fieldName, $invalidError);
			}
			$value = "{$hour}:{$minute}:{$second}";
		}
		$this->setFieldValue($fieldName, $value);
	}

	private function validateOption($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$value = isset($this->rsc[$fieldName]) ? trim($this->rsc[$fieldName]) : null;
		$options = isset($fieldData['options']['options']) ? $fieldData['options']['options'] : array();

		if($required === true && ($value === null || strlen($value) === 0))
			$this->addError($fieldName, $missingError);

		if($value !== null && array_key_exists($value, $options) === false)
			$this->addError($fieldName, $invalidError);

		$this->setFieldValue($fieldName, $value);
	}

	private function validateCheckbox($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = $fieldData['required'];
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$value = isset($this->rsc[$fieldName]) ? $this->rsc[$fieldName] : false;

		if($required === true && $value === false)
			$this->addError($fieldName, $missingError);

		$this->setFieldValue($fieldName, $value);
	}

	private function validateMultioptions($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$value = (isset($this->rsc[$fieldName]) && is_array($this->rsc[$fieldName])) ? $this->rsc[$fieldName] : array();
		
		/*$options = isset($fieldData['options']['options']) ? $fieldData['options']['options'] : array();

		$rVal = array();
		foreach($options AS $key => $val) {
			if(in_array($key, $value)) {
				$rVal[] = $key;
			}
		}
		$this->setFieldValue($fieldName, $rVal);*/

		if($required === true && count($value) === 0) {
			$this->addError($fieldName, $missingError);
		}
		
		$this->setFieldValue($fieldName, $value);
		
		/*var_dump($value);
		exit;*/
	}

	private function validateYoutube($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$required = ($fieldData['required'] === true) ? true : false;
		$missingError = (isset($fieldData['options']['missingError'])) ? $fieldData['options']['missingError'] : "missingError for field {$fieldName} not defined";
		$invalidError = (isset($fieldData['options']['invalidError'])) ? $fieldData['options']['invalidError'] : "invalidError for field {$fieldName} not defined";
		$value = isset($this->rsc[$fieldName]) ? trim($this->rsc[$fieldName]) : '';

		if($required === true && $value == '') {
			$this->addError($fieldName, $missingError);
		}

		if($value != '') {
			$request = new RestRequest("http://gdata.youtube.com/feeds/api/videos/{$value}");
			$request->stdRequest(array(), array('Accept: text/xml'));
			$responseInfo = $request->getResponseInfo();
			if(!isset($responseInfo['http_code']) || $responseInfo['http_code'] != '200') {
				$this->addError($fieldName, $invalidError);
			}
		}

		$this->setFieldValue($fieldName, $value);
	}

	public function setFieldValue($fieldName, $value) {
		if(isset($this->fields[$fieldName]) === false)
			throw new \Exception('Field "' . $fieldName . '" does not exists!');
		
		$this->fields[$fieldName]['value'] = $value;
	}

	public function getFieldValue($fieldName) {
		return isset($this->fields[$fieldName]) ? $this->fields[$fieldName]['value'] : null;
	}
	
	public function getFieldValues() {
		$values = array();
		
		foreach($this->fields as $fldName => $fldOptions) {
			$values[$fldName] = $fldOptions['value'];
		}
		
		return $values;
	}
	
	public function resetFieldValues() {
		foreach($this->fields as $fldName => $fldOptions) {
			self::setFieldValue($fldName, null);
		}
	}

	public function getErrors() {
		return $this->errors;
	}

	public function displayErrors() {
		if(count($this->errors) == 0) {
			return '';
		}

		$html = '<ul class="form-error">' . PHP_EOL;

		foreach($this->errors as $error) {
			$html .= '<li>' . $error . '</li>' . PHP_EOL;
		}

		return $html . '</ul>' . PHP_EOL;
	}

	public function getError($fieldName) {
		return (isset($this->errors[$fieldName])) ? $this->errors[$fieldName] : '';
	}

	public function addError($fieldName, $errorMsg) {
		$this->errors[$fieldName] = $errorMsg;
	}

	public function hasErrors() {
		return (count($this->errors) > 0) ? true : false;
	}

	public function getChecked($fieldName) {
		return (isset($this->fields[$fieldName]) && $this->fields[$fieldName]['value'] == 1) ? ' checked' : '';
	}

	public function displayFieldValue($fieldName) {
		if(!isset($this->fields[$fieldName])) {
			return '';
		}
		return is_string($this->fields[$fieldName]['value']) ? htmlspecialchars($this->fields[$fieldName]['value'], ENT_COMPAT, 'UTF-8', false) : $this->fields[$fieldName]['value'];
	}

	public function getAllValues() {
		$rVal = array();
		foreach($this->fields AS $fieldName => $fieldData) {
			$rVal[$fieldName] = $fieldData['value'];
		}
		return $rVal;
	}

	public function displayFormField($fieldName) {
		$fieldData = $this->fields[$fieldName];

		switch($fieldData['type']) {
			case self::TYPE_OPTION:
				return self::displayOption($fieldName);
				break;

			case self::TYPE_RADIO:
				return self::displayRadio($fieldName);
				break;

			case self::TYPE_MULTIOPTIONS:
				return self::displayMultiOptions($fieldName);
				break;
		}
	}

	private function displayRadio($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$value = isset($fieldData['value']) ? $fieldData['value'] : '';
		$options = isset($fieldData['options']['options']) ? $fieldData['options']['options'] : array();

		$rVal = '';
		foreach($options AS $key => $val) {
			$checked = ($value == $key) ? ' checked' : '';
			$rVal .= "<li><label><input type=\"radio\" name=\"{$fieldName}\" value=\"{$key}\"{$checked}> {$val}</label>\n</li>\n";
		}
		return $rVal;
	}

	private function displayOption($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$value = isset($fieldData['value']) ? $fieldData['value'] : '';
		$options = isset($fieldData['options']['options']) ? $fieldData['options']['options'] : array();

		$rVal = '';
		foreach($options AS $key => $val) {
			$selected = ($value == $key) ? ' selected' : '';
			$rVal .= "<option value=\"{$key}\"{$selected}>{$val}</option>\n";
		}
		return $rVal;
	}

	private function displayMultiOptions($fieldName) {
		$fieldData = $this->fields[$fieldName];
		$value = isset($fieldData['value']) ? $fieldData['value'] : array();
		$options = isset($fieldData['options']['options']) ? $fieldData['options']['options'] : array();

		if(!is_array($value)) {
			$value[] = $value;
		}

		$rVal = '';
		foreach($options AS $key => $val) {
			$checked = in_array($key, $value) ? ' checked="checked"' : '';
			$rVal .= "<li><label><input type=\"checkbox\" name=\"{$fieldName}[]\" value=\"{$key}\"{$checked}> {$val}</label></li>\n";
		}

		return $rVal;
	}
	
	public function getFields() {
		return $this->fields;
	}

}

?>
