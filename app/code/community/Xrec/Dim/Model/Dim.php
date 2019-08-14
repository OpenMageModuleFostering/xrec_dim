<?php

/**
 * Copyright (c) 2012-2014, xrec.nl
 * All rights reserved.
 **/
class Xrec_Dim_Model_Dim extends Mage_Payment_Model_Method_Abstract
{

    // Payment statusses
    const DIM_OPEN = 'Open';
    const DIM_SUCCESS = 'Success';
    const DIM_CANCELLED = 'Cancelled';
    const DIM_FAILURE = 'Failure';
    const DIM_PENDING = 'Pending';
    const DIM_EXPIRED = 'Expired';

    const SEQUENCE_TYPE_OOFF = 'Machtiging eenmalige SEPA incasso.';
    const SEQUENCE_TYPE_RCUR = 'Machtiging doorlopende SEPA incasso.';

    // Payment flags
    const PAYMENT_FLAG_PROCESSED = 'De betaling is ontvangen en verwerkt.';
    const PAYMENT_FLAG_RETRY = 'De consument probeert het bedrag nogmaals af te rekenen.';
    const PAYMENT_FLAG_CANCELD = 'De consument heeft de betaling geannuleerd.';
    const PAYMENT_FLAG_PENDING = 'Afwachten tot de betaling binnen is.';
    const PAYMENT_FLAG_EXPIRED = 'De betaling is verlopen doordat de consument niets met de betaling heeft gedaan.';
    const PAYMENT_FLAG_INPROGRESS = 'De klant is doorverwezen naar de geselecteerde bank.';
    const PAYMENT_FLAG_FAILED = 'De betaling is niet gelukt (er is geen verdere informatie beschikbaar).';
    const PAYMENT_FLAG_FRAUD = 'Het totale bedrag komt niet overeen met de afgerekende bedrag. (Mogelijke fraude).';
    const PAYMENT_FLAG_DCHECKED = 'De betaalstatus is al een keer opgevraagd.';
    const PAYMENT_FLAG_UNKOWN = 'Er is een onbekende fout opgetreden.';

    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'dim';
    protected $_formBlockType = 'dim/payment_dim_form';
    protected $_infoBlockType = 'dim/payment_dim_info';
    protected $_paymentMethod = 'DIM';

    /**
     * Here are examples of flags that will determine functionality availability
     * of this module to be used by frontend and backend.
     *
     * @see all flags and their defaults in Mage_Payment_Model_Method_Abstract
     *
     * It is possible to have a custom dynamic logic by overloading
     * public function can* for each flag respectively
     */

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture = true;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial = false;

    /**
     * Can refund online?
     */
    protected $_canRefund = false;

    /**
     * Can void transactions online?
     */
    protected $_canVoid = true;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping = true;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;

    public function __construct()
    {
        parent::__construct();

        $this->_dim = Mage::Helper('dim/dim');
        $this->_table = Mage::getSingleton('core/resource')->getTableName('dim_payments');
        $this->_mysqlr = Mage::getSingleton('core/resource')->getConnection('core_read');
        $this->_mysqlw = Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->_getCheckout()->getQuote();
    }

    /**
     * Check whether payment method can be used
     *
     * @param Mage_Sales_Model_Quote
     * @return bool
     */
    public function isAvailable($quote = NULL)
    {
        $enabled = (bool)Mage::Helper('dim/data')->getConfig('active');

        if (!$enabled) {
            return false;
        }

        return parent::isAvailable($quote);
    }


    /**
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!parent::canUseForCurrency($currencyCode)) {
            return false;
        }

        if ($currencyCode !== 'EUR') {
            return false;
        }

        return true;
    }

    /**
     * On click payment button, this function is called to assign data
     *
     * @param mixed $data
     * @return self
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        if (strlen(Mage::registry('dim_bank_id')) == 0) {
            Mage::register('dim_bank_id', $data->getDimBankId());
            Mage::register('dim_sequence_type', $data->getDimSequenceType());
            return $this;
        }
        return $this;
    }


    /**
     * Stores the payment information in the dim_payments table.
     * @param $orderId
     * @param $transactionId
     * @param string $status
     * @param string $sequenceType
     * @param string $method
     * @throws Mage_Core_Exception
     */
    public function setPayment($orderId, $transactionId, $status = 'Pending', $sequenceType = null,  $method = 'dim')
    {
        if (is_null($orderId) || is_null($transactionId)) {
            Mage::throwException('Ongeldige order_id of transaction_id...');
        }

        $data = array(
            'order_id' => $orderId,
            'bank_status' => $status,
            'transaction_id' => $transactionId,
            'method' => $method,
            'sequence_type' => $sequenceType,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );

        $this->_mysqlw->insert($this->_table, $data);
    }

    /**
     * @param string $transactionId
     * @param null|string $status
     * @throws Mage_Core_Exception
     */
    public function updatePayment($transactionId, $status = null)
    {
        if (is_null($transactionId) || is_null($status)) {
            Mage::throwException('Geen transactionId en/of status gevonden!');
        }

        $data = array(
            'bank_status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        );


        $where = sprintf("transaction_id = %s", $this->_mysqlw->quote($transactionId));

        $this->_mysqlw->update($this->_table, $data, $where);
    }

    /**
     * Redirects the client on click 'Place Order' to selected iDEAL bank
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl(
            'dim/dim/payment',
            array(
                '_secure' => TRUE,
                '_query' => array(
                    'dim_bank_id' => Mage::registry('dim_bank_id'),
                    'dim_sequence_type' => Mage::registry('dim_sequence_type')
                )
            )
        );
    }

}
