<?php

require_once dirname(__FILE__) . "/Configuration/Configuration.php";
require_once dirname(__FILE__) . "/B2BCommunicator.php";
require_once dirname(__FILE__) . "/Libraries/XmlUtility.php";

/* DIRECTORY */
require_once dirname(__FILE__) . "/Entities/DirectoryRequest.php";
require_once dirname(__FILE__) . "/Entities/DirectoryResponse.php";

/* TRANSACTION */
require_once dirname(__FILE__) . "/Entities/AcquirerTrxRequest.php";

/* NEW eMANDATE */
require_once dirname(__FILE__) . "/Entities/NewMandateRequest.php";
require_once dirname(__FILE__) . "/Entities/NewMandateResponse.php";

/* STATUS */
require_once dirname(__FILE__) . "/Entities/AcquirerStatusRequest.php";
require_once dirname(__FILE__) . "/Entities/AcquirerStatusResponse.php";

/* CANCEL */
require_once dirname(__FILE__) . "/Entities/CancellationRequest.php";
require_once dirname(__FILE__) . "/Entities/CancellationResponse.php";

/* AMEND */
require_once dirname(__FILE__) . "/Entities/AmendmentRequest.php";
require_once dirname(__FILE__) . "/Entities/AmendmentResponse.php";

/* Signer and Validator */
require_once dirname(__FILE__) . "/Libraries/XmlSecurity.php";
require_once dirname(__FILE__) . "/Libraries/XmlValidator.php";

require_once dirname(__FILE__) . '/Libraries/CommunicatorException.php';
require_once dirname(__FILE__) . '/Libraries/MessageIdGenerator.php';
require_once dirname(__FILE__) . '/Libraries/Logger.php';

/**
 * Description of Communicator
 */
class CoreCommunicator {

	const LOCAL_INSTRUMENT = 'CORE';
	const VERSION = '1.0.0';
	const PRODUCT_ID = 'NL:BVN:eMandatesCore:1.0';

	/**
	 * The configuration object used in the Communicator
	 * 
	 * @var Configuration 
	 */
	protected $Configuration;

	/**
	 * The logger object use to log xml and internal messages
	 * 
	 * @var Logger
	 */
	protected $logger;

	/**
	 * The singer object used to sign requests and verify response
	 * 
	 * @var XmlSecurity
	 */
	protected $signer;

	public function __construct(Configuration $configuration, $logger = false) {

		$this->Configuration = $configuration;

		$this->logger = ($logger ? $logger : new Logger($configuration));
		$this->signer = new XmlSecurity($this->logger);
		
		$this->logger->Log(get_called_class() . " initialized");
	}

	/**
	 * Performs a DirectoryRequest and returns the apropiate DirectoryResponse
	 * 
	 * @return \DirectoryResponse
	 */
	public function Directory() {
		$this->logger->Log("sending new directory request");
		$c = get_called_class();
		
		$DirectoryReq = new DirectoryRequest(
				$c::PRODUCT_ID, $c::VERSION, new Merchant($this->Configuration->contractID, $this->Configuration->contractSubID)
		);


		try {
			$this->logger->Log("building idx message");
			// Serialize the DirectoryReq
			$docTree = $DirectoryReq->toXml();

			// Send the Request
			$response = $this->PerformRequest($docTree, $this->Configuration->AcquirerUrl_DirectoryReq);

			// Validate the Response and validate signature

			XmlValidator::isValidatXML($response, XmlValidator::SCHEMA_IDX, $this->logger);
			$this->signer->verify($response, $this->Configuration->crtFileAquirer);

			return new DirectoryResponse($response);
		} catch (Exception $ex) {
			return new DirectoryResponse($ex->getMessage(), (!empty($response) ? $response : ''));
		}
	}

	/**
	 * Performs a new mandate request using the provided mandate
	 * 
	 * @param NewMandateRequest $NewMandateRequest
	 * @return NewMandateResponse
	 */
	public function NewMandate(NewMandateRequest $NewMandateRequest) {
		$NewMandateRequest->logger = $this->logger;
		$this->logger->Log("sending new eMandate transaction");
		$c = get_called_class();

		$AcquirerTrxReq = new AcquirerTrxRequest(
				$c::PRODUCT_ID, $c::VERSION, new AcquirerTrxReqMerchant(
				$this->Configuration->contractID, $this->Configuration->contractSubID, $this->Configuration->merchantReturnURL
				), $NewMandateRequest->DebtorBankId, new AcquirerTrxReqTransaction(
				$NewMandateRequest->EntranceCode, !empty($NewMandateRequest->ExpirationPeriod) ? $NewMandateRequest->ExpirationPeriod : null, $NewMandateRequest->Language, $NewMandateRequest
				)
		);

		try {
			$this->logger->Log("building idx message");
			// Serialize 
			$docTree = $AcquirerTrxReq->toXml($c::LOCAL_INSTRUMENT);


			// Send the Request
			$response = $this->PerformRequest($docTree, $this->Configuration->AcquirerUrl_TransactionReq);

			// Validate the Response and validate signature
			XmlValidator::isValidatXML($response, XmlValidator::SCHEMA_IDX, $this->logger);
			$this->signer->verify($response, $this->Configuration->crtFileAquirer);

			return new NewMandateResponse($response);
		} catch (Exception $ex) {
			return new NewMandateResponse($ex->getMessage(), (!empty($response) ? $response : ''));
		}
	}

	/**
	 * Performs a status request usign the provided $statusRequest
	 * 
	 * @param StatusRequest $statusRequest
	 * @return AcquirerStatusResponse
	 */
	public function GetStatus(StatusRequest $statusRequest) {
		$this->logger->Log("sending new status request");
		$c = get_called_class();

		$AcquirerStatusReq = new AcquirerStatusRequest(
				$c::PRODUCT_ID, $c::VERSION, new AcquirerStatusReqMerchant(
				$this->Configuration->contractID, $this->Configuration->contractSubID
				), new AcquirerStatusReqTransaction(
				$statusRequest->TransactionId
				)
		);
		try {
			$this->logger->Log("building idx message");
			// Serialize 
			$docTree = $AcquirerStatusReq->toXml();

			// Send the Request
			$response = $this->PerformRequest($docTree, $this->Configuration->AcquirerUrl_StatusReq);
			// Validate the Response and validate signature
			XmlValidator::isValidatXML($response, XmlValidator::SCHEMA_IDX, $this->logger);
			$this->signer->verify($response, $this->Configuration->crtFileAquirer);

			return new AcquirerStatusResponse($response, $this->logger);
		} catch (Exception $ex) {
			return new AcquirerStatusResponse($ex->getMessage(), $this->logger, (!empty($response) ? $response : ''));
		}
	}

	/**
	 * Performs an amendment to a mandate using the provided $amendmentRequest
	 * 
	 * @param AmendmentRequest $amendmentRequest
	 * @return AmendmentResponse
	 */
	public function Amend(AmendmentRequest $amendmentRequest) {
		$amendmentRequest->logger = $this->logger;
		$this->logger->Log("sending new amend request");
		$c = get_called_class();

		$AcquirerTrxReq = new AcquirerTrxRequest(
				$c::PRODUCT_ID, $c::VERSION, new AcquirerTrxReqMerchant(
				$this->Configuration->contractID, $this->Configuration->contractSubID, $this->Configuration->merchantReturnURL
				), $amendmentRequest->DebtorBankId, new AcquirerTrxReqTransaction(
				$amendmentRequest->EntranceCode, !empty($amendmentRequest->ExpirationPeriod) ? $amendmentRequest->ExpirationPeriod : null, $amendmentRequest->Language, $amendmentRequest
				)
		);

		try {
			$this->logger->Log("building idx message");
			// Serialize 
			$docTree = $AcquirerTrxReq->toXml($c::LOCAL_INSTRUMENT);

			// Send the Request
			$response = $this->PerformRequest($docTree, $this->Configuration->AcquirerUrl_TransactionReq);

			// Validate the Response and validate signature
			XmlValidator::isValidatXML($response, XmlValidator::SCHEMA_IDX, $this->logger);
			$this->signer->verify($response, $this->Configuration->crtFileAquirer);

			return new AmendmentResponse($response);
		} catch (Exception $ex) {
			return new AmendmentResponse($ex->getMessage(), (!empty($response) ? $response : ''));
		}
	}

	/*
	 * *************************************************************************
	 * PROTECTED METHODS
	 * *************************************************************************
	 */

	/**
	 * Sends the xml to the provided url and returns the response
	 * Throws an Exception if there was something wrong with the curl
	 * 
	 * @param string $xml
	 * @param string $url
	 * @return string
	 * @throws Exception
	 */
	protected function PerformRequest($docTree, $url) {

		// Sign the xml
		$docTree = $this->signer->sign($docTree, $this->Configuration->crtFile, $this->Configuration->keyFile, $this->Configuration->passphrase);

		// Validate the request xml against the .xsd schema
		if (XmlValidator::isValidatXML($docTree->saveXML(), XmlValidator::SCHEMA_IDX, $this->logger)) {

			$this->logger->Log("sending request to {{$url}} ");

			// Log the xml before sending
			$this->logger->LogXmlMessage($docTree);

			//setting the curl parameters.
			$headers = array(
				"Content-type: text/xml;charset=\"UTF-8\"",
			);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);

			// send xml request to server
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

			curl_setopt($ch, CURLOPT_POSTFIELDS, $docTree->saveXML());
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$data = curl_exec($ch);

			// check for curl errors
			if ($data === false) {
				$error = curl_error($ch);
				curl_close($ch);

				$this->logger->Log($error);
				throw new Exception($error);
			} else {
				curl_close($ch);

				$doc = @simplexml_load_string($data);
				if (!$doc) {
					$this->logger->Log("Raw Response : " . $data);
					throw new CommunicatorException($data);
				}

				// Log the xml received
				$this->logger->LogXmlMessage($data, true);

				return $data;
			}
		}
	}

}
