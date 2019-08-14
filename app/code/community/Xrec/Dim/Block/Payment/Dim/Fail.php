<?php

class Xrec_Dim_Block_Payment_Idl_Fail extends Mage_Core_Block_Template
{
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('xrec/page/fail.phtml');
    }

}
