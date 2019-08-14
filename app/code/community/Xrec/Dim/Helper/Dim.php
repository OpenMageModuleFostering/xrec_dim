<?php

require_once 'Communicator/CoreCommunicator.php';
require_once 'Communicator/B2BCommunicator.php';

class Xrec_Dim_Helper_Dim
{
    /** @var  CoreCommunicator */
    protected $coreCommunicator;
    protected $issuerAuthenticationUrl;
    protected $transactionId;
    protected $dimSequenceType;
    protected $dimBankId = null;
    protected $bankStatus = null;

    protected $urlProd = 'https://machtigen.secure-ing.com/EMRoutingWS/handler/ing';
    protected $urlTest = 'https://machtigen.secure-ing.com/TestRoutingWS/handler/ing';

    /**
     * @return mixed
     */
    public function getIssuerAuthenticationUrl()
    {
        return $this->issuerAuthenticationUrl;
    }

    /**
     * @param mixed $issuerAuthenticationUrl
     */
    public function setIssuerAuthenticationUrl($issuerAuthenticationUrl)
    {
        $this->issuerAuthenticationUrl = $issuerAuthenticationUrl;
    }

    /**
     * @return mixed
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @param mixed $transactionId
     */
    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
    }

    /**
     * @return string
     */
    public function getDimBankId()
    {
        return $this->dimBankId;
    }

    /**
     * @param string $dimBankId
     */
    public function setDimBankId($dimBankId)
    {
        $this->dimBankId = $dimBankId;
    }

    /**
     * @return null
     */
    public function getBankStatus()
    {
        return $this->bankStatus;
    }

    /**
     * @param null $bankStatus
     */
    public function setBankStatus($bankStatus)
    {
        $this->bankStatus = $bankStatus;
    }

    /**
     * @return mixed
     */
    public function getDimSequenceType()
    {
        return $this->dimSequenceType;
    }

    /**
     * @param mixed $dimSequenceType
     */
    public function setDimSequenceType($dimSequenceType)
    {
        $this->dimSequenceType = $dimSequenceType;
    }

    protected function getConfiguration()
    {
        $urlDirectoryReq = Mage::Helper('dim/data')->getConfig('AcquirerUrl_DirectoryReq');
        $urlTransactionReq = Mage::Helper('dim/data')->getConfig('AcquirerUrl_TransactionReq');
        $urlStatusReq = Mage::Helper('dim/data')->getConfig('AcquirerUrl_StatusReq');

        $testMode = Mage::Helper('dim/data')->getConfig('testMode');
        if ($testMode) {
            $urlDirectoryReq = Mage::Helper('dim/data')->getConfig('AcquirerUrl_DirectoryReqTest');
            $urlTransactionReq = Mage::Helper('dim/data')->getConfig('AcquirerUrl_TransactionReqTest');
            $urlStatusReq = Mage::Helper('dim/data')->getConfig('AcquirerUrl_StatusReqTest');
        }
        $cerPath = Mage::getBaseDir('var') . '/xrec/dim/cer/';

        $returnUrl = Mage::Helper('dim/data')->getConfig('merchantReturnURL');
        if (substr($returnUrl, -1) !== '/') {
            $returnUrl .= '/';
        }
        /*
         * @param string $passphrase
         * @param string $keyFile
         * @param string $crtFile
         * @param string $crtFileAquirer
         * @param string $contractID
         * @param string $contractSubID
         * @param string $merchantReturnURL
         * @param string $AcquirerUrl_DirectoryReq
         * @param string $AcquirerUrl_TransactionReq
         * @param string $AcquirerUrl_StatusReq
         * @param bool $enableXMLLogs
         * @param string $logPath
         * @param string $folderNamePattern
         * @param string $fileNamePrefix
         * @param bool $enableInternalLogs
         * @param string $fileName
         */
        return new Configuration(
            Mage::Helper('dim/data')->getConfig('passphrase'),
            $cerPath . Mage::Helper('dim/data')->getConfig('keyFile'),
            $cerPath . Mage::Helper('dim/data')->getConfig('crtFile'),
            $cerPath . Mage::Helper('dim/data')->getConfig('crtFileAquirer'),
            Mage::Helper('dim/data')->getConfig('merchantID'),
            Mage::Helper('dim/data')->getConfig('merchantSubID'),
            $returnUrl . 'dim/dim/return',
            $urlDirectoryReq ? $urlDirectoryReq : $this->urlProd,
            $urlTransactionReq ? $urlTransactionReq : $this->urlProd,
            $urlStatusReq ? $urlStatusReq : ($testMode ? $this->urlTest : $this->urlProd),
            true,
            Mage::getBaseDir('var') . '/logs/xrec/dim/',
            'Y-m-d',
            'His.u',
            true,
            'eMandates.txt'
        );
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function doNewMandateRequest($order)
    {
        if (!$order->getPayment()) {
            Mage::throwException('Ongeldige betaling!');
        }
        $dimBankId = $this->dimBankId ? $this->dimBankId : 'INGBNL2A';
        /*if (Mage::Helper('dim/data')->getConfig('SequenceTypeCustomer')) {
            $dimSequenceType = Mage::Helper('dim/data')->getConfig('SequenceType');
        } else {
            $dimSequenceType = $this->dimSequenceType ? $this->dimSequenceType : 'OOFF';
        }*/
        $dimSequenceType = $this->dimSequenceType ? $this->dimSequenceType : 'OOFF';
        $reason = Mage::Helper('dim/data')->getConfig('eMandateReason');

        // Initiate a CoreCommunicator
        $coreCommunicator = new CoreCommunicator($this->getConfiguration());
        /**
         * @param string $entranceCode
         * @param string $language
         * @param string $messageId
         * @param string $eMandateId
         * @param string $eMandateReason
         * @param string $debtorReference
         * @param string $debtorBankId
         * @param string $purchaseId
         * @param string $sequenceType
         * @param string $maxAmount - optional
         * @param DateInterval $expirationPeriod - optional
         */
        $newMandateRequest = new NewMandateRequest(
            $order->getPayment()->getId(),
            'nl',
            MessageIdGenerator::NewMessageId(),
            $order->getPayment()->getId(),
            $reason ? $reason : 'Webshop purchase',
            $order->getCustomerName(),
            $dimBankId,
            $order->getRealOrderId(),
            $dimSequenceType,
            Mage::app()->getStore()->roundPrice($order->getGrandTotal()),
            self::evaluateExpirationPeriod('PT20M')
        );
        $newMandateResponse = $coreCommunicator->NewMandate($newMandateRequest);
        if ($newMandateResponse->IsError) {
            Mage::throwException($newMandateResponse->Error->ErrorCode . ': ' . $newMandateResponse->Error->ConsumerMessage);
            return false;
        } else {
            $this->issuerAuthenticationUrl = $newMandateResponse->IssuerAuthenticationUrl;
            $this->transactionId = $newMandateResponse->TransactionId;
        }

        return true;
    }

    public function doDirectoryRequest()
    {
        try {
            // Initiate a CoreCommunicator
            $coreCommunicator = new CoreCommunicator($this->getConfiguration());
            $diRes = $coreCommunicator->Directory();
            if ($diRes->IsError) {
                Mage::throwException($diRes->Error->ErrorCode . ': ' . $diRes->Error->ConsumerMessage);
            } else {
                $buckets = array();
                foreach ($diRes->DebtorBanks as $bank) {
                    $buckets[$bank->DebtorBankCountry][] = $bank;
                }
                return $buckets;
            }
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }

    }

    public function doGetStatus($transactionId)
    {
        $conf = $this->getConfiguration();
        // Initiate a CoreCommunicator
        $coreCommunicator = new CoreCommunicator($conf);
        $statusRequest = new StatusRequest($transactionId);
        try {
            $sRes = $coreCommunicator->GetStatus($statusRequest);
            if ($sRes->IsError) {
                Mage::getModel('dim/dim')->updatePayment($transactionId, $sRes->Error->ErrorCode);
                Mage::throwException($sRes->Error->ErrorCode . ': ' . $sRes->Error->ConsumerMessage);
            } else {
                $this->bankStatus = $sRes->Status;
                Mage::getModel('dim/dim')->updatePayment($transactionId, $sRes->Status);
            }
            return $sRes->Status;
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }

    }

    /**
     * Parse the $str and return a date interval
     *
     * @param string $str
     * @return string
     */
    public static function evaluateExpirationPeriod($str)
    {
        $interval = '';

        if (is_numeric($str)) { // when only a number is entered
            $interval = 'P' . $str . 'D';
        } else if (strstr($str, ':')) { // when he have a time [hours:minutes:seconds]
            $arr = explode(':', $str);
            $interval = "PT";
            if (!empty($arr[0])) {
                $interval .= $arr[0] . 'H';
            }
            if (!empty($arr[1])) {
                $interval .= $arr[1] . 'M';
            }
            if (!empty($arr[2])) {
                $interval .= $arr[2] . 'S';
            }
        } else {
            $interval = $str;
        }

        if (empty($interval)) {
            return null;
        }

        return new DateInterval(strtoupper($interval));
    }
}
