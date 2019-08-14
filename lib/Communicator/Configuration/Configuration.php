<?php


/**.
 * Description of Configuration
 */
class Configuration {

	/**
	 * A passphrase for the keyFile
	 * @var string 
	 */
	public $passphrase;

	/**
	 * A string which specifies the path to the private key file used by the crtFile to sign messages to the creditor bank.
	 * @var string 
	 */
	public $keyFile;

	/**
	 * A string which specifies the path to the certificate to use to sign messages to the creditor bank.
	 * @var string 
	 */
	public $crtFile;

	/**
	 * A string which specifies the path to the certificate to use to validate messages from the creditor bank.
	 * @var string 
	 */
	public $crtFileAquirer;

	/**
	 * eMandate.ContractID as supplied to you by the creditor bank.
	 * If the eMandate.ContractID has less than 9 digits, use leading zeros to fill out the field.
	 * @var string 
	 */
	public $contractID;

	/**
	 * eMandate.ContractSubId as supplied to you by the creditor bank.
	 * If you do not have a ContractSubId, use 0 for this field.        
	 * @var string 
	 */
	public $contractSubID;

	/**
	 * A valid URL to which the debtor banks redirects to, after the debtor has authorized a transaction.
	 * @var string
	 */
	public $merchantReturnURL;

	/**
	 * The URL to which the library sends Directory request messages
	 * @var string
	 */
	public $AcquirerUrl_DirectoryReq;

	/**
	 * The URL to which the library sends Transaction messages (including eMandates messages).
	 * @var string 
	 */
	public $AcquirerUrl_TransactionReq;
	
	/**
	 * The URL to which the library sends Status request messages.
	 * @var string 
	 */
	public $AcquirerUrl_StatusReq;

	/**
	 * Enables or Disables the xml logs
	 * @var bool
	 */
	public $enableXMLLogs;

	/**
	 * Path to the logs folder
	 * eg: logs/
	 * @var string 
	 */
	public $logPath;

	/**
	 * Folder name pattern for date() function
	 * eg: "Y-m-d" will produce: "2015-03-11"
	 * eg: end result will be: "logs/2015-03-11/115423.321-DirectoryRes.xml"
	 * @var string 
	 */
	public $folderNamePattern;

	/**
	 * File name prefix pattern for date() function
	 * eg: "His.u" will produce: "115423.321"
	 * eg: end result will be: "logs/2015-03-11/115423.321-DirectoryRes.xml"
	 * @var string 
	 */
	public $fileNamePrefix;

	/**
	 * Enables or Disables the internal logs
	 * @var bool 
	 */
	public $enableInternalLogs;

	/**
	 * The file name for internal logs
	 * This file will be saved in the same path as the xml logs
	 * eg: "emandates.txt" will produce: "logs/2015-03-11/emandates.txt"
	 * @var type 
	 */
	public $fileName;
	
	/**
	 * Constructor that highlights all required fields for this object
	 * 
	 * @param string $passphrase
	 * @param string $keyFile
	 * @param string $crtFile
	 * @param string $crtFileAquirer
	 * @param string $contractID
	 * @param string $contractSubID
	 * @param string $merchantReturnURL
	 * @param string $AcquirerUrl_DirectoryReq
	 * @param string $AcquirerUrl_TransactionReq
	 * @param bool $enableXMLLogs
	 * @param string $logPath
	 * @param string $folderNamePattern
	 * @param string $fileNamePrefix
	 * @param bool $enableInternalLogs
	 * @param string $fileName
	 */
	public function __construct($passphrase, $keyFile, $crtFile, $crtFileAquirer, $contractID, $contractSubID, $merchantReturnURL, $AcquirerUrl_DirectoryReq, $AcquirerUrl_TransactionReq, $AcquirerUrl_StatusReq, $enableXMLLogs, $logPath, $folderNamePattern, $fileNamePrefix, $enableInternalLogs, $fileName) {
		$this->passphrase = $passphrase;
		$this->keyFile = $keyFile;
		$this->crtFile = $crtFile;
		$this->crtFileAquirer = $crtFileAquirer;
		$this->contractID = $contractID;
		$this->contractSubID = $contractSubID;
		$this->merchantReturnURL = $merchantReturnURL;
		$this->AcquirerUrl_DirectoryReq = $AcquirerUrl_DirectoryReq;
		$this->AcquirerUrl_TransactionReq = $AcquirerUrl_TransactionReq;
		$this->AcquirerUrl_StatusReq = $AcquirerUrl_StatusReq;
		$this->enableXMLLogs = $enableXMLLogs;
		$this->logPath = $logPath;
		$this->folderNamePattern = $folderNamePattern;
		$this->fileNamePrefix = $fileNamePrefix;
		$this->enableInternalLogs = $enableInternalLogs;
		$this->fileName = $fileName;
	}
	
	/**
	 * Returns the Configuration object base on the configuration file settings
	 * 
	 * @global array $emandates_config_params
	 * @return \Configuration
	 */
	public static function getDefault() {
		global $emandates_config_params;
		
		return new Configuration(
				$emandates_config_params['passphrase'],
				$emandates_config_params['keyFile'],
				$emandates_config_params['crtFile'],
				$emandates_config_params['crtFileAquirer'],
				
				$emandates_config_params['contractID'],
				$emandates_config_params['contractSubID'],
				$emandates_config_params['merchantReturnURL'],
				
				$emandates_config_params['AcquirerUrl_DirectoryReq'],
				$emandates_config_params['AcquirerUrl_TransactionReq'],
				$emandates_config_params['AcquirerUrl_StatusReq'],
				
				$emandates_config_params['enableXMLLogs'],
				$emandates_config_params['logPath'],
				$emandates_config_params['folderNamePattern'],
				$emandates_config_params['fileNamePrefix'],
				
				$emandates_config_params['enableInternalLogs'],
				$emandates_config_params['fileName']
		);
	}

}