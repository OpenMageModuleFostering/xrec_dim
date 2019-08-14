<?php

class Xrec_Dim_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * @param string $transactionId
     * @return mixed
     */
    public function getStatusById($transactionId)
    {
        /** @var $connection Varien_Db_Adapter_Interface */
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $status = $connection->fetchAll(
            sprintf(
                "SELECT `bank_status` FROM `%s` WHERE `transaction_id` = %s",
                Mage::getSingleton('core/resource')->getTableName('dim_payments'),
                $connection->quote($transactionId)
            )
        );

        return $status[0];
    }

    /**
     * @param string $transactionId
     * @return null
     */
    public function getOrderIdByTransactionId($transactionId)
    {
        /** @var $connection Varien_Db_Adapter_Interface */
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $id = $connection->fetchAll(
            sprintf(
                "SELECT `order_id` FROM `%s` WHERE `transaction_id` = %s",
                Mage::getSingleton('core/resource')->getTableName('dim_payments'),
                $connection->quote($transactionId)
            )
        );

        if (sizeof($id) > 0)
        {
            return $id[0]['order_id'];
        }
        return null;
    }

    public function getPaymentByTransactionId($transactionId)
    {
        /** @var $connection Varien_Db_Adapter_Interface */
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $data = $connection->fetchAll(
            sprintf(
                "SELECT * FROM `%s` WHERE `transaction_id` = %s",
                Mage::getSingleton('core/resource')->getTableName('dim_payments'),
                $connection->quote($transactionId)
            )
        );

        if (sizeof($data) > 0)
        {
            return $data[0];
        }
        return array();
    }


    /**
     * Check if testmode is enabled.
     */
    public function getTestModeEnabled()
    {
        return $this->getConfig('testmode');
    }

    /**
     * Get store config
     *
     * @param string $key
     * @return string
     */
    public function getConfig($key = null)
    {
        return Mage::getStoreConfig("payment/xrec/{$key}");
    }

}
