<?php

require_once 'CoreCommunicator.php';

/**
 * Description of Communicator
 */
class B2BCommunicator extends CoreCommunicator {

	const LOCAL_INSTRUMENT = 'B2B';
	const PRODUCT_ID = 'NL:BVN:eMandatesB2B:1.0';

	/**
	 * Initiates a new B2BCommunicator
	 */
	function __construct(Configuration $configuration, $logger = false) {
		parent::__construct($configuration, $logger);
	}

	/**
	 * Performs a CancellationRequest and returns the appropiate CancellationResponse
	 * 
	 * @param CancellationRequest $cancellationRequest
	 * @return \CancellationResponse
	 */
	public function Cancel(CancellationRequest $cancellationRequest) {
		$cancellationRequest->logger = $this->logger;
		$this->logger->Log("sending cancellation transaction request");
		$c = get_called_class();

		$AcquirerTrxReq = new AcquirerTrxRequest(
				$c::PRODUCT_ID, $c::VERSION, new AcquirerTrxReqMerchant(
				$this->Configuration->contractID, $this->Configuration->contractSubID, $this->Configuration->merchantReturnURL
				), $cancellationRequest->DebtorBankId, new AcquirerTrxReqTransaction(
				$cancellationRequest->EntranceCode, !empty($cancellationRequest->ExpirationPeriod) ? $cancellationRequest->ExpirationPeriod : null, $cancellationRequest->Language, $cancellationRequest
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

			return new CancellationResponse($response);
		} catch (Exception $ex) {
			return new CancellationResponse($ex->getMessage(), (!empty($response) ? $response : ''));
		}
	}

}
