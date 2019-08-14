<?php

/**
 * Copyright (c) 2012-2014, xrec.nl
 * All rights reserved.
 **/
class Xrec_Dim_DimController extends Mage_Core_Controller_Front_Action
{

    /**
     * @var Xrec_Dim_Helper_Dim
     */
    protected $dim;

    /**
     * @var Xrec_Dim_Helper_Data
     */
    protected $data;

    /**
     * @var Xrec_Dim_Model_Dim
     */
    protected $model;

    /**
     * Get  core
     */
    public function _construct()
    {
        $this->dim = Mage::Helper('dim/dim');
        $this->data = Mage::Helper('dim/data');
        $this->model = Mage::getModel('dim/dim');
        parent::_construct();
    }


    /**
     * @param string $e Exceptiom message
     * @param null $order_id An OrderID
     */
    protected function _showException($e = '', $orderId = NULL)
    {
        $this->loadLayout();
        $order = Mage::getModel('sales/order')->load($orderId);
        $block = $this->getLayout()
            ->createBlock('Mage_Core_Block_Template')
            ->setTemplate('xrec/page/exception.phtml')
            ->setData('exception', $e)
            ->setData('order', $order);

        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    /**
     * Gets the current checkout session with order information
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * After clicking 'Place Order' the method 'getOrderPlaceRedirectUrl()' gets called and redirects to here with the bank_id
     * Then this action creates an payment with a transaction_id that gets inserted in the database (mollie_payments, sales_payment_transaction)
     */
    public function paymentAction()
    {
        if ($this->getRequest()->getParam('order_id')) {
            /** @var $order Mage_Sales_Model_Order */
            $order = Mage::getModel('sales/order')->load($this->getRequest()->getParam('order_id'));
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $this->__(Xrec_Dim_Model_Dim::PAYMENT_FLAG_RETRY), FALSE)->save();
        } else {
            // Load last order by IncrementId
            /** @var $order Mage_Sales_Model_Order */
            $orderIncrementId = $this->_getCheckout()->getLastRealOrderId();
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        }

        try {
            $dimBankId = $this->getRequest()->getParam('dim_bank_id');
            if (!$this->data->getConfig('SequenceTypeCustomer')) {
                $dimSequenceType = $this->data->getConfig('SequenceType');
            } else {
                $dimSequenceType = $this->getRequest()->getParam('dim_sequence_type');
            }

            $this->dim->setDimBankId($dimBankId);
            $this->dim->setDimSequenceType($dimSequenceType);

            if ($this->dim->doNewMandateRequest($order)) {

                if (!$order->getId()) {
                    Mage::log('Geen order voor verwerking gevonden');
                    Mage::throwException('Geen order voor verwerking gevonden');
                }

                $this->model->setPayment($order->getId(), $this->dim->getTransactionId(), Xrec_Dim_Model_Dim::DIM_PENDING, $dimSequenceType);

                // Creates transaction
                /** @var $payment Mage_Sales_Model_Order_Payment */
                $payment = Mage::getModel('sales/order_payment')
                    ->setMethod('dim')
                    ->setTransactionId($this->dim->getTransactionId())
                    ->setIsTransactionClosed(false);


                $order->setPayment($payment);

                $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);

                if ($dimSequenceType == 'OOFF') {
                    $message = Xrec_Dim_Model_Dim::PAYMENT_FLAG_INPROGRESS .' '. Xrec_Dim_Model_Dim::SEQUENCE_TYPE_OOFF;
                } else {
                    $message = Xrec_Dim_Model_Dim::PAYMENT_FLAG_INPROGRESS .' '. Xrec_Dim_Model_Dim::SEQUENCE_TYPE_RCUR;
                }
                $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $message, false)->save();

                $this->_redirectUrl($this->dim->getIssuerAuthenticationUrl());

            } else {
                Mage::throwException($this->dim->getErrorMessage());
            }
        } catch (Exception $e) {
            Mage::log($e);
            $this->_showException($e->getMessage(), $order->getId());
        }
    }

    /**
     * Customer returning from the bank with an transaction_id
     * Depending on what the state of the payment is they get redirected to the corresponding page
     */
    public function returnAction()
    {
        // Get transaction_id from url (Ex: http://yourmagento.com/index.php/dim/dim/return?trxid=0050000000012875&ec=834hn46 )
        $transactionId = $this->getRequest()->getParam('trxid');
        //$orderId = Mage::Helper('dim/data')->getOrderIdByTransactionId($transactionId);
        $dimPayment = Mage::Helper('dim/data')->getPaymentByTransactionId($transactionId);
        $orderId = isset($dimPayment['order_id']) ? $dimPayment['order_id'] : null;

        try {
            if (!empty($transactionId) && $orderId) {
                $dimStatus = $this->dim->doGetStatus($transactionId);

                if ($dimStatus == Xrec_Dim_Model_Dim::DIM_SUCCESS) {
                    if ($this->_getCheckout()->getQuote()->getItemsCount() > 0) {
                        foreach ($this->_getCheckout()->getQuote()->getItemsCollection() as $item) {
                            Mage::getSingleton('checkout/cart')->removeItem($item->getId());
                        }
                        Mage::getSingleton('checkout/cart')->save();
                    }

                    // Load order by id ($oId)
                    /** @var $order Mage_Sales_Model_Order */
                    $order = Mage::getModel('sales/order')->load($orderId);
                    /** @var $payment Mage_Sales_Model_Order_Payment */
                    $payment = $order->getPayment();
                    $payment->setMethod('dim')
                        ->setTransactionId($transactionId)
                        ->setIsTransactionClosed(TRUE);

                    if ($dimPayment['sequence_type'] == 'OOFF') {
                        $message = Xrec_Dim_Model_Dim::PAYMENT_FLAG_PROCESSED .' '. Xrec_Dim_Model_Dim::SEQUENCE_TYPE_OOFF;
                    } else {
                        $message = Xrec_Dim_Model_Dim::PAYMENT_FLAG_PROCESSED .' '. Xrec_Dim_Model_Dim::SEQUENCE_TYPE_RCUR;
                    }

                    $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PROCESSING, $message, TRUE)->save();;

                    /*
                     * Send an email to the customer.
                     */
                    $order->sendNewOrderEmail()->setEmailSent(TRUE);

                    // Redirect to success page
                    $this->_redirect('checkout/onepage/success', array('_secure' => TRUE));
                    return;
                } else {
                    // Create fail page
                    $this->loadLayout();

                    $block = $this->getLayout()
                        ->createBlock('Mage_Core_Block_Template')
                        ->setTemplate('xrec/page/fail.phtml')
                        ->setData('form', Mage::getUrl('dim/dim/form'))
                        ->setData('order', Mage::getModel('sales/order')->load($orderId));

                    $this->getLayout()->getBlock('content')->append($block);

                    $this->renderLayout();
                    return;
                }
            }
        } catch (Exception $e) {
            Mage::log($e);
            $this->_showException($e->getMessage(), $orderId);
            return;
        }

        $this->_redirectUrl(Mage::getBaseUrl());
    }


    /**
     * This action is getting called by ing to report the payment status
     */
    public function reportAction()
    {
        // Get transaction_id from url (Ex: http://yourmagento.com/index.php/dim/dim/report?trxid=0050000000012875&ec=834hn46 )
        $transactionId = $this->getRequest()->getParam('trxid');
        $orderId = $this->getRequest()->getParam('order_id');

        // Get order by transaction_id
        $orderId = Mage::helper('dim/data')->getOrderIdByTransactionId($transactionId);

        // Load order by id ($oId)
        /** @var $order Mage_Sales_Model_Order */
        $order = Mage::getModel('sales/order')->load($orderId);

        try {
            if ($transactionId !== '' && $order->getStatus() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {

                $this->dim->doGetStatus($transactionId);

                // Maakt een Order transactie aan
                /** @var $payment Mage_Sales_Model_Order_Payment */
                $payment = Mage::getModel('sales/order_payment')
                    ->setMethod('dim')
                    ->setTransactionId($transactionId)
                    ->setIsTransactionClosed(TRUE);

                $order->setPayment($payment);

                if ($this->dim->getBankStatus() == Xrec_Dim_Model_Dim::DIM_SUCCESS) {
                    /*
                     * Update the total amount paid, keep that in the order. We do not care if this is the correct
                     * amount or not at this moment.
                     */

                    $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PROCESSING, $this->__(Xrec_Dim_Model_Dim::PAYMENT_FLAG_PROCESSED), TRUE);

                    /*
                     * Send an email to the customer.
                     */
                    $order->sendNewOrderEmail()->setEmailSent(TRUE);

                } else {
                    // Stomme Magento moet eerst op 'cancel' en dan pas setState, andersom dan zet hij de voorraad niet terug.
                    $order->cancel();
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, Mage_Sales_Model_Order::STATE_CANCELED, $this->__(Xrec_Dim_Model_Dim::PAYMENT_FLAG_CANCELD), FALSE);
                }

                $order->save();
            }
        } catch (Exception $e) {
            Mage::log($e);
            $this->_showException($e->getMessage());
        }
    }

    public function formAction()
    {
        if ($this->getRequest()->isPost()) {
            $create_new_payment = Mage::getUrl(
                'dim/dim/payment',
                array(
                    '_secure' => TRUE,
                    '_query' => array(
                        'order_id' => $this->getRequest()->getPost('order_id'),
                        'dim_bank_id' => $this->getRequest()->getPost('dim_bank_id'),
                        'dim_sequence_type' => $this->getRequest()->getPost('dim_sequence_type')
                    )
                )
            );
            $this->_redirectUrl($create_new_payment);
        }
    }

}
